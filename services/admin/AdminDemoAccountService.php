<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;
use App\Services\AccountMetricsService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class AdminDemoAccountService
{
    private const STATUSES = ['active','suspended','disabled','closed'];
    private const TYPES = ['standard','pro','ecn_demo'];
    private const SORTS = ['created'=>'d.created_at','balance'=>'d.balance','number'=>'d.account_number','owner'=>'u.last_name','status'=>'d.status'];

    public function listAccounts(array $input): array
    {
        $f=$this->filters($input);$where=[];$params=[];
        if($f['search']!==''){
            $where[]="(d.account_number LIKE :q0 OR u.user_reference LIKE :q1 OR u.first_name LIKE :q2 OR u.last_name LIKE :q3 OR CONCAT_WS(' ',u.first_name,u.last_name) LIKE :q4 OR u.email LIKE :q5)";
            $q='%'.$this->escapeLike($f['search']).'%';foreach(range(0,5) as $n)$params['q'.$n]=$q;
        }
        if($f['status']!=='all'){$where[]='d.status=:status';$params['status']=$f['status'];}
        if($f['type']!=='all'){$where[]='d.account_type=:type';$params['type']=$f['type'];}
        if($f['currency']!==''){$where[]='d.currency=:currency';$params['currency']=$f['currency'];}
        if($f['positions']==='yes')$where[]="EXISTS (SELECT 1 FROM positions xp WHERE xp.demo_account_id=d.id AND xp.status='open')";
        if($f['positions']==='no')$where[]="NOT EXISTS (SELECT 1 FROM positions xp WHERE xp.demo_account_id=d.id AND xp.status='open')";
        if($f['from']!==null){$where[]='d.created_at>=:date_from';$params['date_from']=$f['from'];}
        if($f['to']!==null){$where[]='d.created_at<:date_to';$params['date_to']=$f['to'];}
        $join=' FROM demo_accounts d JOIN users u ON u.id=d.user_id LEFT JOIN (SELECT demo_account_id,COUNT(*) open_positions FROM positions WHERE status=\'open\' GROUP BY demo_account_id) op ON op.demo_account_id=d.id';
        $clause=$where?' WHERE '.implode(' AND ',$where):'';
        $count=Database::connection()->prepare('SELECT COUNT(*)'.$join.$clause);$count->execute($params);$total=(int)$count->fetchColumn();
        $page=min($f['page'],max(1,(int)ceil($total/$f['per_page'])));$offset=($page-1)*$f['per_page'];$sort=self::SORTS[$f['sort']];
        $query=Database::connection()->prepare('SELECT d.account_number,d.account_type,d.currency,d.balance,d.status,d.created_at,u.user_reference,u.first_name,u.last_name,u.email,COALESCE(op.open_positions,0) open_positions'.$join.$clause." ORDER BY {$sort} {$f['direction']},d.id DESC LIMIT {$f['per_page']} OFFSET {$offset}");
        $query->execute($params);$accounts=$query->fetchAll();
        $currencies=Database::connection()->query('SELECT DISTINCT currency FROM demo_accounts ORDER BY currency')->fetchAll(PDO::FETCH_COLUMN);
        return ['accounts'=>$accounts,'currencies'=>$currencies,'filters'=>$f,'pagination'=>['current_page'=>$page,'per_page'=>$f['per_page'],'total_records'=>$total,'total_pages'=>max(1,(int)ceil($total/$f['per_page']))]];
    }

    public function getAccountDetail(string $accountNumber,array $permissions): ?array
    {
        if(preg_match('/^[A-Z0-9-]{4,32}$/',$accountNumber)!==1)return null;
        $pdo=Database::connection();$q=$pdo->prepare('SELECT d.*,u.user_reference,u.first_name,u.last_name,u.email,u.phone,u.country,u.status user_status,u.created_at user_created_at FROM demo_accounts d JOIN users u ON u.id=d.user_id WHERE d.account_number=? LIMIT 1');$q->execute([$accountNumber]);$account=$q->fetch();if(!$account)return null;
        $snapshot=AccountMetricsService::demo()->snapshot((int)$account['user_id'],(int)$account['id']);
        $trading=in_array('trading.view',$permissions,true);$funds=in_array('funds.view',$permissions,true);$auditAllowed=in_array('audit.view',$permissions,true);
        $orders=$trades=$transactions=$requests=$adminActivity=[];
        if($trading){$o=$pdo->prepare('SELECT o.order_type,o.direction,o.volume,o.execution_price,o.status,o.created_at,i.symbol FROM orders o JOIN instruments i ON i.id=o.instrument_id WHERE o.demo_account_id=? ORDER BY o.created_at DESC,o.id DESC LIMIT 20');$o->execute([(int)$account['id']]);$orders=$o->fetchAll();$t=$pdo->prepare('SELECT t.trade_reference,t.direction,t.volume,t.open_price,t.close_price,t.profit_loss,t.close_reason,t.opened_at,t.closed_at,i.symbol FROM trades t JOIN instruments i ON i.id=t.instrument_id WHERE t.demo_account_id=? ORDER BY t.closed_at DESC,t.id DESC LIMIT 20');$t->execute([(int)$account['id']]);$trades=$t->fetchAll();}
        if($funds){$t=$pdo->prepare('SELECT transaction_type,amount,balance_before,balance_after,description,status,created_by_admin_id,created_at FROM transactions WHERE demo_account_id=? ORDER BY created_at DESC,id DESC LIMIT 30');$t->execute([(int)$account['id']]);$transactions=$t->fetchAll();$r=$pdo->prepare("SELECT 'deposit' kind,amount,status,admin_note,created_at FROM deposit_requests WHERE demo_account_id=? UNION ALL SELECT 'withdrawal',amount,status,admin_note,created_at FROM withdrawal_requests WHERE demo_account_id=? ORDER BY created_at DESC LIMIT 20");$r->execute([(int)$account['id'],(int)$account['id']]);$requests=$r->fetchAll();}
        if($auditAllowed){$a=$pdo->prepare("SELECT action,description,created_at FROM admin_activity_logs WHERE entity_type='demo_account' AND entity_id=? ORDER BY created_at DESC,id DESC LIMIT 20");$a->execute([(int)$account['id']]);$adminActivity=$a->fetchAll();}
        $activitySql="SELECT event,reference,occurred_at FROM (
            SELECT CONCAT('Demo order ',o.status) event,i.symbol reference,o.created_at occurred_at FROM orders o JOIN instruments i ON i.id=o.instrument_id WHERE o.demo_account_id=?
            UNION ALL SELECT 'Demo trade closed',t.trade_reference,t.closed_at FROM trades t WHERE t.demo_account_id=?
            UNION ALL SELECT CONCAT('Virtual ',REPLACE(tx.transaction_type,'_',' ')),tx.description,tx.created_at FROM transactions tx WHERE tx.demo_account_id=?
        ) events ORDER BY occurred_at DESC LIMIT 40";
        $ev=$pdo->prepare($activitySql);$ev->execute([(int)$account['id'],(int)$account['id'],(int)$account['id']]);$activity=$ev->fetchAll();
        return ['account'=>$account,'metrics'=>$snapshot['metrics'],'positions'=>array_slice($snapshot['positions'],0,100),'orders'=>$orders,'trades'=>$trades,'transactions'=>$transactions,'funding_requests'=>$requests,'activity'=>$activity,'admin_activity'=>$adminActivity,'permissions'=>['accounts_manage'=>in_array('accounts.manage',$permissions,true),'users_view'=>in_array('users.view',$permissions,true),'trading_view'=>$trading,'funds_view'=>$funds,'audit_view'=>$auditAllowed]];
    }

    public function changeAccountStatus(string $accountNumber,int $adminId,string $status,string $reason):array
    {
        if(!in_array($status,['active','suspended','disabled'],true))throw new RuntimeException('Choose Active, Suspended, or Disabled.');
        $reason=trim($reason);if(mb_strlen($reason)>500||($status!=='active'&&$reason===''))throw new RuntimeException('A reason is required for suspension or disabling and must be 500 characters or fewer.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$q=$pdo->prepare('SELECT d.id,d.account_number,d.user_id,d.status,u.user_reference FROM demo_accounts d JOIN users u ON u.id=d.user_id WHERE d.account_number=? LIMIT 1 FOR UPDATE');$q->execute([$accountNumber]);$account=$q->fetch();if(!$account)throw new RuntimeException('Demo Account not found.',404);if(!in_array($account['status'],['active','suspended','disabled'],true))throw new RuntimeException('This Demo Account status cannot be changed from the Admin area.',409);if($account['status']===$status)throw new RuntimeException('The Demo Account already has that status.',409);$u=$pdo->prepare('UPDATE demo_accounts SET status=?,updated_at=NOW() WHERE id=?');$u->execute([$status,(int)$account['id']]);AdminActivityLogger::logOrFail($adminId,'demo_account_status_changed','demo_account',(int)$account['id'],sprintf('Account %s · Owner %s · %s → %s · Reason: %s',$accountNumber,$account['user_reference'],$account['status'],$status,$reason!==''?$reason:'Reactivated by authorized administrator.'));$pdo->commit();return ['account_number'=>$accountNumber,'old_status'=>$account['status'],'status'=>$status];}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private function filters(array $input):array
    {
        $search=isset($input['search'])&&is_string($input['search'])?mb_substr(trim($input['search']),0,100):'';
        $status=isset($input['status'])&&in_array($input['status'],self::STATUSES,true)?$input['status']:'all';$type=isset($input['type'])&&in_array($input['type'],self::TYPES,true)?$input['type']:'all';
        $currency=isset($input['currency'])&&is_string($input['currency'])?strtoupper(trim($input['currency'])):'';if(preg_match('/^[A-Z]{3}$/',$currency)!==1)$currency='';
        $positions=isset($input['positions'])&&in_array($input['positions'],['yes','no'],true)?$input['positions']:'all';$sort=isset($input['sort'])&&isset(self::SORTS[$input['sort']])?$input['sort']:'created';$direction=isset($input['direction'])&&strtolower((string)$input['direction'])==='asc'?'ASC':'DESC';
        $per=isset($input['per_page'])&&in_array((int)$input['per_page'],[25,50,100],true)?(int)$input['per_page']:25;$page=isset($input['page'])&&filter_var($input['page'],FILTER_VALIDATE_INT)!==false?max(1,min(100000,(int)$input['page'])):1;
        $date=isset($input['date'])&&in_array($input['date'],['all','today','7d','30d'],true)?$input['date']:'all';$from=$to=null;$now=new DateTimeImmutable('now',new DateTimeZone(date_default_timezone_get()));$utc=new DateTimeZone('UTC');
        if($date==='today'){$from=$now->setTime(0,0)->setTimezone($utc)->format('Y-m-d H:i:s');$to=$now->setTime(0,0)->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');}elseif($date==='7d'||$date==='30d'){$days=$date==='7d'?7:30;$from=$now->modify('-'.($days-1).' days')->setTime(0,0)->setTimezone($utc)->format('Y-m-d H:i:s');}
        return ['search'=>$search,'status'=>$status,'type'=>$type,'currency'=>$currency,'positions'=>$positions,'date'=>$date,'from'=>$from,'to'=>$to,'sort'=>$sort,'direction'=>$direction,'page'=>$page,'per_page'=>$per];
    }
    private function escapeLike(string $value):string{return strtr($value,['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']);}
}
