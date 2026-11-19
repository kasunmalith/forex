<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use DateTimeImmutable;
use PDO;
use InvalidArgumentException;

final class TradingHistoryService
{
    private const SORTS = ['closed_at'=>'t.closed_at','opened_at'=>'t.opened_at','symbol'=>'i.symbol','volume'=>'t.volume','profit_loss'=>'t.profit_loss'];
    private const REASONS = ['manual','partial','stop_loss','take_profit'];

    public static function filters(array $input): array
    {
        $side = is_string($input['side'] ?? null) ? strtolower($input['side']) : '';
        $reason = is_string($input['reason'] ?? null) ? strtolower($input['reason']) : '';
        $profit = is_string($input['result'] ?? null) ? strtolower($input['result']) : '';
        $range = is_string($input['range'] ?? null) ? strtolower($input['range']) : 'all';
        $sort = is_string($input['sort'] ?? null) ? strtolower($input['sort']) : 'closed_at';
        $direction = is_string($input['direction'] ?? null) ? strtoupper($input['direction']) : 'DESC';
        $perPage = filter_var($input['per_page'] ?? 25, FILTER_VALIDATE_INT);
        if (!in_array($side, ['', 'buy','sell'], true) || !in_array($reason, array_merge([''],self::REASONS), true) || !in_array($profit,['','profit','loss','breakeven'],true) || !in_array($range,['all','today','7d','30d','custom'],true) || !isset(self::SORTS[$sort]) || !in_array($direction,['ASC','DESC'],true) || !in_array($perPage,[25,50,100],true)) {
            throw new InvalidArgumentException('One or more history filters are invalid.');
        }
        $from = self::validDate($input['from'] ?? null);
        $to = self::validDate($input['to'] ?? null);
        if ($range === 'custom' && (($input['from'] ?? '') === '' || ($input['to'] ?? '') === '' || $from === null || $to === null || $from > $to)) {
            throw new InvalidArgumentException('Choose valid start and end dates, with the start date on or before the end date.');
        }
        $query = is_string($input['q'] ?? null) ? trim($input['q']) : '';
        return ['q'=>mb_substr($query,0,80),'symbol'=>is_string($input['symbol']??null)?mb_substr(trim($input['symbol']),0,32):'', 'side'=>$side,'reason'=>$reason,'result'=>$profit,'range'=>$range,'from'=>$from,'to'=>$to,'sort'=>$sort,'direction'=>$direction,'per_page'=>$perPage,'page'=>max(1,min(100000,(int)($input['page']??1)))];
    }

    private static function validDate(mixed $raw): ?string
    {
        if (!is_string($raw) || $raw === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $errors = DateTimeImmutable::getLastErrors();
        return $date && (!$errors || ($errors['warning_count']===0 && $errors['error_count']===0)) && $date->format('Y-m-d')===$raw ? $raw : null;
    }

    private static function where(int $userId,int $accountId,array $f): array
    {
        $parts=['t.user_id=:uid','t.demo_account_id=:aid']; $params=['uid'=>$userId,'aid'=>$accountId];
        if($f['q']!==''){$parts[]='(t.trade_reference LIKE :q OR CAST(t.position_id AS CHAR) LIKE :q OR i.symbol LIKE :q OR i.display_name LIKE :q)';$params['q']='%'.$f['q'].'%';}
        if($f['symbol']!==''){$parts[]='i.symbol=:symbol';$params['symbol']=$f['symbol'];}
        if($f['side']!==''){$parts[]='t.direction=:side';$params['side']=$f['side'];}
        if($f['reason']!==''){$parts[]='t.close_reason=:reason';$params['reason']=$f['reason'];}
        if($f['result']==='profit')$parts[]='t.profit_loss>0'; elseif($f['result']==='loss')$parts[]='t.profit_loss<0'; elseif($f['result']==='breakeven')$parts[]='t.profit_loss=0';
        $today=(new DateTimeImmutable('today'))->format('Y-m-d');
        if($f['range']==='today'){$parts[]='t.closed_at>=:day_start AND t.closed_at<:next_day';$params['day_start']=$today.' 00:00:00';$params['next_day']=(new DateTimeImmutable('tomorrow'))->format('Y-m-d').' 00:00:00';}
        elseif($f['range']==='7d' || $f['range']==='30d'){$days=$f['range']==='7d'?7:30;$parts[]='t.closed_at>=:since';$params['since']=(new DateTimeImmutable('-'.($days-1).' days'))->format('Y-m-d').' 00:00:00';}
        elseif($f['range']==='custom'){$parts[]='t.closed_at>=:custom_from AND t.closed_at<:custom_to';$params['custom_from']=$f['from'].' 00:00:00';$params['custom_to']=(new DateTimeImmutable($f['to'].' +1 day'))->format('Y-m-d').' 00:00:00';}
        return [implode(' AND ',$parts),$params];
    }

    public static function browse(int $userId,int $accountId,array $f): array
    {
        $pdo=Database::connection();[$where,$params]=self::where($userId,$accountId,$f);
        $count=$pdo->prepare("SELECT COUNT(*) FROM trades t JOIN instruments i ON i.id=t.instrument_id WHERE $where");$count->execute($params);$total=(int)$count->fetchColumn();
        $sum=$pdo->prepare("SELECT COUNT(*) total,COALESCE(SUM(t.profit_loss),0) realized_pl,COALESCE(SUM(t.profit_loss>0),0) wins,COALESCE(SUM(t.profit_loss<0),0) losses,COALESCE(SUM(t.profit_loss=0),0) breakeven FROM trades t JOIN instruments i ON i.id=t.instrument_id WHERE $where");$sum->execute($params);$summary=$sum->fetch()?:[];
        $offset=($f['page']-1)*$f['per_page'];$order=self::SORTS[$f['sort']].' '.$f['direction'].',t.id DESC';
        $sql="SELECT t.id,t.trade_reference,t.position_id,t.direction,t.volume,p.initial_volume,t.open_price,t.close_price,t.profit_loss,t.close_reason,t.opened_at,t.closed_at,i.symbol,i.display_name,i.precision_digits FROM trades t JOIN instruments i ON i.id=t.instrument_id LEFT JOIN positions p ON p.id=t.position_id AND p.user_id=t.user_id AND p.demo_account_id=t.demo_account_id WHERE $where ORDER BY $order LIMIT ".(int)$f['per_page'].' OFFSET '.(int)$offset;
        $q=$pdo->prepare($sql);$q->execute($params);
        return ['rows'=>$q->fetchAll(),'total'=>$total,'page_count'=>max(1,(int)ceil($total/$f['per_page'])),'summary'=>['total'=>(int)($summary['total']??0),'realized_pl'=>(string)($summary['realized_pl']??'0'),'wins'=>(int)($summary['wins']??0),'losses'=>(int)($summary['losses']??0),'breakeven'=>(int)($summary['breakeven']??0)]];
    }

    public static function detail(int $userId,int $accountId,int $tradeId): ?array
    {
        $q=Database::connection()->prepare('SELECT t.id,t.trade_reference,t.position_id,t.direction,t.volume,t.open_price,t.close_price,t.profit_loss,t.close_reason,t.raw_profit_loss,t.profit_currency,t.conversion_rate,t.opened_at,t.closed_at,i.symbol,i.display_name,i.precision_digits,d.currency AS account_currency FROM trades t JOIN instruments i ON i.id=t.instrument_id JOIN demo_accounts d ON d.id=t.demo_account_id AND d.user_id=t.user_id WHERE t.id=:id AND t.user_id=:uid AND t.demo_account_id=:aid LIMIT 1');$q->execute(['id'=>$tradeId,'uid'=>$userId,'aid'=>$accountId]);return $q->fetch()?:null;
    }

    public static function symbols(int $userId,int $accountId): array
    {
        $q=Database::connection()->prepare('SELECT DISTINCT i.symbol FROM trades t JOIN instruments i ON i.id=t.instrument_id WHERE t.user_id=? AND t.demo_account_id=? ORDER BY i.symbol');$q->execute([$userId,$accountId]);return array_column($q->fetchAll(),'symbol');
    }

    public static function csv(int $userId,int $accountId,array $f): iterable
    {
        $pdo=Database::connection();[$where,$params]=self::where($userId,$accountId,$f);$sql='SELECT t.trade_reference,t.position_id,i.symbol,t.direction,t.volume,t.open_price,t.close_price,t.profit_loss,t.close_reason,t.opened_at,t.closed_at FROM trades t JOIN instruments i ON i.id=t.instrument_id WHERE '.$where.' ORDER BY '.self::SORTS[$f['sort']].' '.$f['direction'].',t.id DESC';$q=$pdo->prepare($sql);$q->execute($params);while($row=$q->fetch(PDO::FETCH_NUM))yield $row;
    }
}
