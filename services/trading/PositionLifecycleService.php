<?php
declare(strict_types=1);

namespace App\Services\Trading;

use App\Database;
use App\Services\DecimalMath;
use App\Services\PositionValuationService;
use App\Services\CurrencyConversionService;
use App\Services\MarketData\MarketDataService;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

/** Authoritative demo position close, protection and trigger lifecycle. */
final class PositionLifecycleService
{
    private function __construct(private readonly PDO $pdo) {}
    public static function demo(): self { return new self(Database::connection()); }

    public function close(int $userId, int $positionId, ?string $volume, string $key, string $reason='manual'): array
    {
        if ($positionId < 1 || !preg_match('/^[a-f0-9-]{32,64}$/D', $key)) throw new TradingException('Refresh the terminal and try again.',422);
        if (!in_array($reason,['manual','stop_loss','take_profit'],true)) $reason='manual';
        $existing=$this->findIdempotent($userId,$key);
        if ($existing) { if((int)$existing['position_id']!==$positionId)throw new TradingException('This close request key was already used.',409); return $this->tradeResponse($existing,true); }
        $quotes=$this->freshQuotes();
        $this->pdo->beginTransaction();
        try {
            $account=$this->lockAccount($userId);
            if (!$account) throw new TradingException('An active demo account is required.',403);
            $dupe=$this->findIdempotent($userId,$key,(int)$account['id']);
            if ($dupe) { if((int)$dupe['position_id']!==$positionId)throw new TradingException('This close request key was already used.',409); $this->pdo->commit(); return $this->tradeResponse($dupe,true); }
            $position=$this->lockPosition($userId,(int)$account['id'],$positionId);
            if (!$position) throw new TradingException('Demo position was not found.',404);
            if ($position['status']!=='open') throw new TradingException('This demo position is already closed.',409);
            $quote=$quotes[$position['symbol']]??null;
            if (!$this->usable($quote,(string)$position['symbol'])) throw new TradingException('Demo market price is temporarily unavailable. Please try again.',503);
            $closePrice=(string)$quote[$position['direction']==='buy'?'bid':'ask'];
            if ($reason==='stop_loss' && !(($position['direction']==='buy' && DecimalMath::compare($closePrice,(string)$position['stop_loss'])<=0) || ($position['direction']==='sell' && DecimalMath::compare($closePrice,(string)$position['stop_loss'])>=0))) throw new TradingException('The stop trigger is no longer active.',409);
            if ($reason==='take_profit' && !(($position['direction']==='buy' && DecimalMath::compare($closePrice,(string)$position['take_profit'])>=0) || ($position['direction']==='sell' && DecimalMath::compare($closePrice,(string)$position['take_profit'])<=0))) throw new TradingException('The target trigger is no longer active.',409);
            $closedVolume=$volume ?? (string)$position['volume'];
            if (!$this->validVolume($closedVolume) || DecimalMath::compare($closedVolume,(string)$position['volume'])>0) throw new TradingException('Enter a valid close volume within the position size.',422);
            $remaining=DemoVolumeService::canonical(DecimalMath::sub((string)$position['volume'],$closedVolume));
            if (DecimalMath::compare($remaining,'0')>0 && !$this->validVolume($remaining)) throw new TradingException('The remaining position must meet the demo volume step.',422);
            $slice=$position; $slice['volume']=$closedVolume;
            $valuation=PositionValuationService::value($slice,(string)$account['currency'],$quotes);
            if ($valuation['account_pl']===null) throw new TradingException('Current demo conversion data is unavailable. Please retry shortly.',503);
            $raw=(string)$valuation['raw_pl']; $profit=(string)$valuation['account_pl'];
            $profitCurrency=(string)($position['profit_currency']??$position['quote_currency']);
            $conversion=CurrencyConversionService::rate($profitCurrency,(string)$account['currency'],$quotes);
            $now=(new DateTimeImmutable())->format('Y-m-d H:i:s');
            $newBalance=DecimalMath::add((string)$account['balance'],$profit);
            if($position['required_margin']===null) throw new TradingException('Account margin data is being prepared. Please retry shortly.',503);
            $newMargin=DecimalMath::mulDiv((string)$position['required_margin'],$remaining,(string)$position['volume']);
            if (DecimalMath::compare($remaining,'0')===0) {
                $this->pdo->prepare("UPDATE positions SET status='closed',closed_at=?,close_price=?,realized_pl=COALESCE(realized_pl,0)+?,close_reason=? WHERE id=? AND status='open'")->execute([$now,$closePrice,$profit,$reason,$positionId]);
            } else {
                $this->pdo->prepare('UPDATE positions SET volume=?,required_margin=?,realized_pl=COALESCE(realized_pl,0)+? WHERE id=? AND status=\'open\'')->execute([$remaining,$newMargin,$profit,$positionId]);
                $reason='partial';
            }
            $this->pdo->prepare('UPDATE demo_accounts SET balance=?,realized_pl=realized_pl+? WHERE id=? AND user_id=?')->execute([$newBalance,$profit,(int)$account['id'],$userId]);
            $balanceQuery=$this->pdo->prepare('SELECT balance FROM demo_accounts WHERE id=?');$balanceQuery->execute([(int)$account['id']]);$newBalance=(string)$balanceQuery->fetchColumn();
            $ref='MF-D-TRD-'.strtoupper(bin2hex(random_bytes(6)));
            $this->pdo->prepare('INSERT INTO trades (trade_reference,idempotency_key,user_id,demo_account_id,position_id,instrument_id,direction,volume,open_price,close_price,profit_loss,close_reason,raw_profit_loss,profit_currency,conversion_rate,opened_at,closed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$ref,$key,$userId,(int)$account['id'],$positionId,(int)$position['instrument_id'],$position['direction'],$closedVolume,$position['open_price'],$closePrice,$profit,$reason,$raw,$profitCurrency,$conversion,$position['opened_at'],$now]);
            $tradeId=(int)$this->pdo->lastInsertId();
            $type=DecimalMath::compare($profit,'0')>=0?'trade_profit':'trade_loss';
            $this->pdo->prepare('INSERT INTO transactions (user_id,demo_account_id,transaction_type,amount,balance_before,balance_after,reference_type,reference_id,description,status) VALUES (?,?,?,?,?,?,?,?,?,\'completed\')')
                ->execute([$userId,(int)$account['id'],$type,$profit,$account['balance'],$newBalance,'trade',$tradeId,'Demo position '.$reason.' close']);
            $event=DecimalMath::compare($remaining,'0')>0?'partial_close':(in_array($reason,['stop_loss','take_profit'],true)?$reason:'close');
            $this->pdo->prepare('INSERT INTO position_events (user_id,demo_account_id,position_id,event_type,volume,execution_price,realized_pl) VALUES (?,?,?,?,?,?,?)')->execute([$userId,(int)$account['id'],$positionId,$event,$closedVolume,$closePrice,$profit]);
            $this->pdo->commit();
            if(DecimalMath::compare($remaining,'0')===0){$notificationType=match($reason){'stop_loss'=>'stop_loss_triggered','take_profit'=>'take_profit_triggered',default=>'position_closed'};try{\App\Services\NotificationService::demo()->createOnceForEvent($userId,$notificationType,match($notificationType){'stop_loss_triggered'=>'Demo Stop Loss Triggered','take_profit_triggered'=>'Demo Take Profit Triggered',default=>'Demo Position Closed'},$position['symbol'].' '.$position['direction'].' position closed.','trade',$ref,'client/history');}catch(Throwable $notificationError){error_log('Demo position notification failed for trade '.$ref.': '.$notificationError->getMessage());}}
            $metrics=null;
            try { $metrics=\App\Services\AccountMetricsService::demo()->snapshot($userId,(int)$account['id'])['metrics']; }
            catch(Throwable) { error_log('Post-close account metrics refresh deferred for user '.$userId.'.'); }
            return ['success'=>true,'duplicate'=>false,'message'=>DecimalMath::compare($remaining,'0')===0?'Demo position closed.':'Demo position partially closed.','position'=>['id'=>$positionId,'reference'=>$position['provider_position_id'],'symbol'=>$position['symbol'],'side'=>$position['direction'],'volume'=>$closedVolume,'remaining_volume'=>DecimalMath::compare($remaining,'0')===0?'0':$remaining,'open_price'=>$position['open_price'],'close_price'=>$closePrice,'realized_pnl'=>$profit,'close_reason'=>$reason,'closed_at'=>$now],'trade'=>['id'=>$tradeId,'reference'=>$ref],'account'=>['balance'=>$newBalance,'currency'=>$account['currency'],'metrics'=>$metrics]];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof TradingException) throw $e;
            $code=$e instanceof \PDOException?(int)($e->errorInfo[1]??0):0;
            if ($code===1062) { $prior=$this->findIdempotent($userId,$key); if ($prior) return $this->tradeResponse($prior,true); }
            error_log('Demo position lifecycle transaction failed for user '.$userId.'.');
            throw new TradingException('The demo position could not be updated. Please try again.',500);
        }
    }

    public function modify(int $userId,int $positionId,array $input): array
    {
        // A crossed existing level wins over an edit/removal request for that position.
        $this->processTriggers($userId);
        $quotes=$this->freshQuotes(); $this->pdo->beginTransaction();
        try {
            $account=$this->lockAccount($userId); if(!$account) throw new TradingException('An active demo account is required.',403);
            $p=$this->lockPosition($userId,(int)$account['id'],$positionId); if(!$p) throw new TradingException('Demo position was not found.',404);
            if($p['status']!=='open') throw new TradingException('Only open demo positions can be modified.',409);
            $q=$quotes[$p['symbol']]??null; if(!$this->usable($q,(string)$p['symbol'])) throw new TradingException('Demo market price is temporarily unavailable. Please try again.',503);
            $sl=array_key_exists('stop_loss',$input)?$this->level($input['stop_loss']):$p['stop_loss'];
            $tp=array_key_exists('take_profit',$input)?$this->level($input['take_profit']):$p['take_profit'];
            foreach(['stop_loss'=>$sl,'take_profit'=>$tp] as $field=>$level) if($level!==null && strlen((string)(explode('.',$level,2)[1]??''))>(int)$p['precision_digits']) throw new TradingException('Protection price exceeds this instrument precision.',422);
            $mark=(string)$q[$p['direction']==='buy'?'bid':'ask'];
            if($sl!==null && (($p['direction']==='buy' && DecimalMath::compare($sl,$mark)>=0)||($p['direction']==='sell'&&DecimalMath::compare($sl,$mark)<=0))) throw new TradingException('Stop Loss must remain beyond the current executable market price.',422);
            if($tp!==null && (($p['direction']==='buy' && DecimalMath::compare($tp,$mark)<=0)||($p['direction']==='sell'&&DecimalMath::compare($tp,$mark)>=0))) throw new TradingException('Take Profit must remain beyond the current executable market price.',422);
            $this->pdo->prepare('UPDATE positions SET stop_loss=?,take_profit=? WHERE id=?')->execute([$sl,$tp,$positionId]);
            $this->pdo->prepare('INSERT INTO position_events (user_id,demo_account_id,position_id,event_type,old_stop_loss,new_stop_loss,old_take_profit,new_take_profit) VALUES (?,?,?,\'protection_modified\',?,?,?,?)')->execute([$userId,(int)$account['id'],$positionId,$p['stop_loss'],$sl,$p['take_profit'],$tp]);
            $this->pdo->commit(); return ['success'=>true,'message'=>'Demo protection levels updated.','position'=>['id'=>$positionId,'stop_loss'=>$sl,'take_profit'=>$tp]];
        } catch(Throwable $e){ if($this->pdo->inTransaction())$this->pdo->rollBack(); if($e instanceof TradingException)throw $e; error_log('Demo protection update failed for user '.$userId.'.'); throw new TradingException('Protection levels could not be updated. Please try again.',500); }
    }

    /** Process this authenticated user's SL/TP triggers only. */
    public function processTriggers(int $userId): int
    {
        $q=$this->pdo->prepare("SELECT p.id,p.direction,p.stop_loss,p.take_profit,i.symbol FROM positions p JOIN instruments i ON i.id=p.instrument_id WHERE p.user_id=? AND p.status='open' AND (p.stop_loss IS NOT NULL OR p.take_profit IS NOT NULL)");
        $q->execute([$userId]); $positions=$q->fetchAll(); if(!$positions)return 0;
        $quotes=$this->freshQuotes(array_values(array_unique(array_column($positions,'symbol')))); $count=0;
        foreach($positions as $p){ $quote=$quotes[$p['symbol']]??null; if(!$this->usable($quote,(string)$p['symbol']))continue; $price=(string)$quote[$p['direction']==='buy'?'bid':'ask']; $reason=null;
            if($p['stop_loss']!==null && (($p['direction']==='buy'&&DecimalMath::compare($price,(string)$p['stop_loss'])<=0)||($p['direction']==='sell'&&DecimalMath::compare($price,(string)$p['stop_loss'])>=0)))$reason='stop_loss';
            elseif($p['take_profit']!==null && (($p['direction']==='buy'&&DecimalMath::compare($price,(string)$p['take_profit'])>=0)||($p['direction']==='sell'&&DecimalMath::compare($price,(string)$p['take_profit'])<=0)))$reason='take_profit';
            if($reason){try{$this->close($userId,(int)$p['id'],null,bin2hex(random_bytes(16)),$reason);$count++;}catch(TradingException $e){if($e->httpStatus!==409)error_log('Position trigger deferred id='.(int)$p['id']);}}
        } return $count;
    }

    private function freshQuotes(?array $symbols=null): array { try{$rows=MarketDataService::demo()->quotes($symbols);$map=[];foreach($rows as $q)if(isset($q['symbol']))$map[$q['symbol']]=$q;return $map;}catch(Throwable){throw new TradingException('Demo market price is temporarily unavailable. Please try again.',503);} }
    private function usable(mixed $q,string $symbol): bool { if(!is_array($q)||($q['symbol']??null)!==$symbol||!is_numeric($q['bid']??null)||!is_numeric($q['ask']??null))return false;try{$age=time()-(new DateTimeImmutable((string)$q['timestamp']))->getTimestamp();}catch(Throwable){return false;}return $age>=-5&&$age<=max(5,(int)config('DEMO_TRADE_QUOTE_MAX_AGE_SECONDS'))&&DecimalMath::compare((string)$q['bid'],'0')>0&&DecimalMath::compare((string)$q['ask'],(string)$q['bid'])>=0; }
    private function lockAccount(int $userId): ?array { $q=$this->pdo->prepare("SELECT d.* FROM demo_accounts d JOIN users u ON u.id=d.user_id WHERE d.user_id=? AND d.status='active' AND u.status='active' ORDER BY d.id LIMIT 1 FOR UPDATE");$q->execute([$userId]);return $q->fetch()?:null; }
    private function lockPosition(int $userId,int $accountId,int $id): ?array { $q=$this->pdo->prepare("SELECT p.*,i.symbol,i.display_name,i.quote_currency,i.profit_currency,i.contract_size,i.precision_digits FROM positions p JOIN instruments i ON i.id=p.instrument_id WHERE p.id=? AND p.user_id=? AND p.demo_account_id=? FOR UPDATE");$q->execute([$id,$userId,$accountId]);return $q->fetch()?:null; }
    private function validVolume(string $v): bool { return DemoVolumeService::valid($v); }
    private function level(mixed $v): ?string { if($v===null||$v==='')return null;if(!is_string($v)&&!is_int($v))throw new TradingException('Enter a valid positive protection price.',422);$s=trim((string)$v);if(!preg_match('/^\d{1,12}(?:\.\d{1,10})?$/D',$s)||DecimalMath::compare($s,'0')<=0)throw new TradingException('Enter a valid positive protection price.',422);return $s; }
    private function findIdempotent(int $userId,string $key,?int $accountId=null): ?array { $sql='SELECT t.*,i.symbol FROM trades t JOIN instruments i ON i.id=t.instrument_id WHERE t.user_id=? AND t.idempotency_key=?'.($accountId?' AND t.demo_account_id=?':'').' LIMIT 1';$q=$this->pdo->prepare($sql);$q->execute($accountId?[$userId,$key,$accountId]:[$userId,$key]);return $q->fetch()?:null; }
    private function tradeResponse(array $t,bool $duplicate): array { return ['success'=>true,'duplicate'=>$duplicate,'message'=>'This demo close request was already completed.','position'=>['id'=>(int)$t['position_id'],'symbol'=>$t['symbol'],'side'=>$t['direction'],'volume'=>(string)$t['volume'],'close_price'=>(string)$t['close_price'],'realized_pnl'=>(string)$t['profit_loss'],'close_reason'=>$t['close_reason']],'trade'=>['id'=>(int)$t['id'],'reference'=>$t['trade_reference']]]; }
}
