<?php
declare(strict_types=1);

namespace App\Services\Funds;

use App\Database;
use App\Services\AccountMetricsService;
use App\Services\DecimalMath;
use App\Services\MarketData\MarketDataService;
use PDO;
use RuntimeException;
use Throwable;

/** Demo-only account funding and its audit ledger. No external payment rails are used. */
final class DemoFundingService
{
    private function __construct(private readonly PDO $pdo) {}
    public static function demo(): self { return new self(Database::connection()); }

    public function overview(int $userId): array
    {
        $snapshot=AccountMetricsService::demo()->snapshot($userId);
        return $snapshot + ['deposits'=>$this->requests('deposit_requests',$userId),'withdrawals'=>$this->requests('withdrawal_requests',$userId),'transactions'=>$this->transactions($userId)];
    }

    public function requestDeposit(int $userId,string $amount,?string $note,string $key): array
    {
        $amount=$this->amount($amount,(string)config('DEMO_DEPOSIT_MIN','10'),(string)config('DEMO_DEPOSIT_MAX','100000'));
        return $this->request($userId,'deposit',$amount,$note,$key);
    }

    public function requestWithdrawal(int $userId,string $amount,?string $note,string $key): array
    {
        $amount=$this->amount($amount,(string)config('DEMO_WITHDRAWAL_MIN','10'),(string)config('DEMO_DEPOSIT_MAX','100000'));
        return $this->request($userId,'withdrawal',$amount,$note,$key);
    }

    public function cancelRequest(int $userId,string $kind,int $requestId): bool
    {
        if(!in_array($kind,['deposit','withdrawal'],true)||$requestId<1)return false;
        $table=$kind==='deposit'?'deposit_requests':'withdrawal_requests';
        $this->pdo->beginTransaction();
        try{$account=$this->lockAccount($userId);$q=$this->pdo->prepare("UPDATE {$table} SET status='cancelled',admin_note='Cancelled by client',updated_at=NOW() WHERE id=? AND user_id=? AND demo_account_id=? AND status='pending'");$q->execute([$requestId,$userId,(int)$account['id']]);$ok=$q->rowCount()===1;$this->pdo->commit();return $ok;}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** Future admin controllers should call approveDeposit(), processWithdrawal(), or rejectRequest(). */
    public function approveDeposit(int $requestId,int $processorId=0): array { return $this->processExisting('deposit',$requestId,$processorId); }
    public function processWithdrawal(int $requestId,int $processorId=0): array { return $this->processExisting('withdrawal',$requestId,$processorId); }
    public function rejectRequest(string $kind,int $requestId,int $processorId=0,?string $note=null): bool
    {
        if(!in_array($kind,['deposit','withdrawal'],true))throw new RuntimeException('Invalid demo request type.');$table=$kind==='deposit'?'deposit_requests':'withdrawal_requests';
        $q=$this->pdo->prepare("UPDATE {$table} SET status='rejected',processed_by=?,processed_at=NOW(),admin_note=?,updated_at=NOW() WHERE id=? AND status='pending'");$q->execute([$processorId?:null,mb_substr((string)$note,0,500),$requestId]);return $q->rowCount()===1;
    }

    private function request(int $userId,string $kind,string $amount,?string $note,string $key): array
    {
        if(preg_match('/^[a-f0-9]{32}$/',$key)!==1)throw new RuntimeException('Refresh the page and submit again.');$note=$note===null?null:trim($note);if($note!==null&&mb_strlen($note)>200)throw new RuntimeException('Note must be 200 characters or fewer.');
        $table=$kind==='deposit'?'deposit_requests':'withdrawal_requests';$auto=(bool)config($kind==='deposit'?'DEMO_FUNDING_AUTO_APPROVE':'DEMO_WITHDRAWAL_AUTO_APPROVE',false);
        $quotes=$kind==='withdrawal'?MarketDataService::demo()->quotes():null;
        $this->pdo->beginTransaction();
        try{$account=$this->lockAccount($userId);$accountId=(int)$account['id'];
            $dupe=$this->pdo->prepare("SELECT id,status,amount FROM {$table} WHERE demo_account_id=? AND idempotency_key=? LIMIT 1");$dupe->execute([$accountId,$key]);if($existing=$dupe->fetch()){$this->pdo->commit();return ['duplicate'=>true,'id'=>(int)$existing['id'],'status'=>$existing['status'],'amount'=>$existing['amount']];}
            if($kind==='withdrawal')$this->assertCapacity($userId,$account,$amount,AccountMetricsService::demo()->snapshot($userId,$accountId,$quotes));
            $status=$auto?'approved':'pending';$insert=$this->pdo->prepare("INSERT INTO {$table} (user_id,demo_account_id,amount,idempotency_key,user_note,status,processed_at) VALUES (?,?,?,?,?,?,?)");$insert->execute([$userId,$accountId,$amount,$key,$note,$status,$auto?date('Y-m-d H:i:s'):null]);$id=(int)$this->pdo->lastInsertId();
            if($auto)$this->applyBalance($kind,$userId,$account,$amount,$table,$id);
            $this->pdo->commit();if($auto)$this->notifyProcessed($userId,$kind,$id,$amount);return ['duplicate'=>false,'id'=>$id,'status'=>$status,'amount'=>$amount];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function processExisting(string $kind,int $id,int $processorId): array
    {
        $table=$kind==='deposit'?'deposit_requests':'withdrawal_requests';$lookup=$this->pdo->prepare("SELECT user_id,demo_account_id FROM {$table} WHERE id=?");$lookup->execute([$id]);$owner=$lookup->fetch();if(!$owner)throw new RuntimeException('Demo request not found.');$quotes=$kind==='withdrawal'?MarketDataService::demo()->quotes():null;$this->pdo->beginTransaction();try{$account=$this->lockAccount((int)$owner['user_id'],(int)$owner['demo_account_id']);$q=$this->pdo->prepare("SELECT * FROM {$table} WHERE id=? AND status='pending' FOR UPDATE");$q->execute([$id]);$row=$q->fetch();if(!$row)throw new RuntimeException('Demo request is no longer pending.');if($kind==='withdrawal')$this->assertCapacity((int)$row['user_id'],$account,(string)$row['amount'],AccountMetricsService::demo()->snapshot((int)$row['user_id'],(int)$account['id'],$quotes),$id);$u=$this->pdo->prepare("UPDATE {$table} SET status='approved',processed_by=?,processed_at=NOW(),updated_at=NOW() WHERE id=? AND status='pending'");$u->execute([$processorId?:null,$id]);$this->applyBalance($kind,(int)$row['user_id'],$account,(string)$row['amount'],$table,$id);$this->pdo->commit();$this->notifyProcessed((int)$row['user_id'],$kind,$id,(string)$row['amount']);return ['id'=>$id,'status'=>'approved','amount'=>$row['amount']];}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function lockAccount(int $userId,?int $accountId=null): array
    {
        $sql="SELECT d.* FROM demo_accounts d JOIN users u ON u.id=d.user_id WHERE d.user_id=? AND d.status='active' AND u.status='active'".($accountId?' AND d.id=?':'')." ORDER BY d.id LIMIT 1 FOR UPDATE";$q=$this->pdo->prepare($sql);$q->execute($accountId?[$userId,$accountId]:[$userId]);$account=$q->fetch();if(!$account)throw new RuntimeException('An active demo account is required.');return $account;
    }
    private function assertCapacity(int $userId,array $account,string $amount,?array $snapshot,?int $excludeRequestId=null): void
    {
        $m=$snapshot['metrics']??[];if(($m['free_margin']??null)===null)throw new RuntimeException('Withdrawal capacity cannot be confirmed while market valuation is unavailable.');$capacity=DecimalMath::compare((string)$m['balance'],(string)$m['free_margin'])<0?(string)$m['balance']:(string)$m['free_margin'];if(DecimalMath::compare($capacity,'0')<0)$capacity='0';$q=$this->pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawal_requests WHERE demo_account_id=? AND status='pending' AND id<>COALESCE(?,0)");$q->execute([(int)$account['id'],$excludeRequestId]);$reserved=(string)$q->fetchColumn();$capacity=DecimalMath::sub($capacity,$reserved);if(DecimalMath::compare($capacity,'0')<0)$capacity='0';if(DecimalMath::compare($amount,$capacity)>0)throw new RuntimeException('The requested Demo Withdrawal exceeds available Balance and Free Margin capacity.');
    }
    private function applyBalance(string $kind,int $userId,array $account,string $amount,string $table,int $requestId): void
    {
        $before=(string)$account['balance'];$after=$kind==='deposit'?DecimalMath::add($before,$amount):DecimalMath::sub($before,$amount);if(DecimalMath::compare($after,'0')<0)throw new RuntimeException('Insufficient Virtual Balance.');$u=$this->pdo->prepare('UPDATE demo_accounts SET balance=? WHERE id=? AND user_id=?');$u->execute([$after,(int)$account['id'],$userId]);if($u->rowCount()!==1)throw new RuntimeException('Demo Balance could not be updated.');$t=$this->pdo->prepare('INSERT INTO transactions (user_id,demo_account_id,transaction_type,amount,balance_before,balance_after,reference_type,reference_id,description,status) VALUES (?,?,?,?,?,?,?,?,?,\'completed\')');$t->execute([$userId,(int)$account['id'],$kind==='deposit'?'demo_deposit':'demo_withdrawal',$amount,$before,$after,$table,$requestId,'Virtual Demo '.ucfirst($kind).' · MF-'.($kind==='deposit'?'DDEP':'DWDR').'-'.str_pad((string)$requestId,8,'0',STR_PAD_LEFT)]);
    }
    private function notifyProcessed(int $userId,string $kind,int $id,string $amount):void
    {
        try{\App\Services\NotificationService::demo()->createOnceForEvent($userId,$kind==='deposit'?'demo_deposit_processed':'demo_withdrawal_processed',$kind==='deposit'?'Demo Deposit Processed':'Demo Withdrawal Processed','A virtual Demo '.ucfirst($kind).' of '.format_currency_amount($amount).' was processed.','funding_request',$kind.'-'.$id,'client/funds');}
        catch(Throwable $e){error_log('Demo funding notification failed for '.$kind.' request '.$id.': '.$e->getMessage());}
    }
    private function amount(string $raw,string $min,string $max): string
    {
        $raw=trim($raw);if(!preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/',$raw,$parts))throw new RuntimeException('Enter a valid amount with up to two decimal places.');$value=$parts[1].'.'.str_pad($parts[2]??'',2,'0');if(DecimalMath::compare($value,$min)<0||DecimalMath::compare($value,$max)>0)throw new RuntimeException('The amount is outside the configured Demo funding limits.');return $value;
    }
    private function requests(string $table,int $userId): array {$q=$this->pdo->prepare("SELECT id,amount,status,user_note,admin_note,processed_at,created_at FROM {$table} WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 50");$q->execute([$userId]);return $q->fetchAll();}
    private function transactions(int $userId): array {$q=$this->pdo->prepare("SELECT transaction_type,amount,balance_before,balance_after,reference_type,reference_id,description,status,created_at FROM transactions WHERE user_id=? AND transaction_type IN ('demo_deposit','demo_withdrawal') ORDER BY created_at DESC,id DESC LIMIT 100");$q->execute([$userId]);return $q->fetchAll();}
}
