<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class AdminDashboardService
{
    public function load(array $permissions): array
    {
        $pdo=Database::connection();$can=static fn(string $key):bool=>in_array($key,$permissions,true);
        $data=['cards'=>[],'can'=>['users'=>$can('users.view'),'accounts'=>$can('accounts.view'),'trading'=>$can('trading.view'),'funds'=>$can('funds.view'),'kyc'=>$can('kyc.view'),'support'=>$can('support.view'),'audit'=>$can('audit.view')],'recent_users'=>[],'recent_activity'=>[],'admin_activity'=>[],'registrations'=>[],'today'=>[]];
        if($can('users.view')){
            $row=$pdo->query("SELECT COUNT(*) total,COALESCE(SUM(status='active'),0) active FROM users")->fetch(PDO::FETCH_ASSOC)?:[];
            $data['cards']['users_total']=(int)($row['total']??0);$data['cards']['users_active']=(int)($row['active']??0);
            $q=$pdo->query("SELECT user_reference,first_name,last_name,email,status,created_at FROM users ORDER BY created_at DESC,id DESC LIMIT 7");$data['recent_users']=$q->fetchAll();
            $data['registrations']=$this->registrationChart($pdo);
        }
        if($can('accounts.view')){
            $row=$pdo->query("SELECT COUNT(*) total,COALESCE(SUM(status='active'),0) active FROM demo_accounts")->fetch(PDO::FETCH_ASSOC)?:[];
            $data['cards']['accounts_total']=(int)($row['total']??0);$data['cards']['accounts_active']=(int)($row['active']??0);
            $data['cards']['virtual_balances']=$pdo->query('SELECT currency,SUM(balance) amount FROM demo_accounts GROUP BY currency ORDER BY currency')->fetchAll(PDO::FETCH_ASSOC);
        }
        if($can('trading.view')){
            $data['cards']['open_positions']=(int)$pdo->query("SELECT COUNT(*) FROM positions WHERE status='open'")->fetchColumn();
            [$start,$end]=$this->localDayUtcRange();
            $q=$pdo->prepare("SELECT (SELECT COUNT(*) FROM orders WHERE status='executed' AND executed_at>=? AND executed_at<?) demo_orders,(SELECT COUNT(*) FROM trades WHERE closed_at>=? AND closed_at<?) demo_trades_closed");$q->execute([$start,$end,$start,$end]);$row=$q->fetch(PDO::FETCH_ASSOC)?:[];
            $data['today']=['orders'=>(int)($row['demo_orders']??0),'trades_closed'=>(int)($row['demo_trades_closed']??0)];
        }
        if($can('funds.view')){$q=$pdo->query("SELECT (SELECT COUNT(*) FROM deposit_requests WHERE status='pending')+(SELECT COUNT(*) FROM withdrawal_requests WHERE status='pending')");$data['cards']['funding_pending']=(int)$q->fetchColumn();}
        if($can('kyc.view')){$q=$pdo->query("SELECT COUNT(*) FROM kyc_submissions WHERE status IN ('submitted','in_review')");$data['cards']['verification_pending']=(int)$q->fetchColumn();}
        if($can('support.view')){$data['cards']['support_open']=$this->supportAwaitingCount($pdo);}

        $data['recent_activity']=$this->recentSystemActivity($pdo,$can);
        if($can('audit.view')){
            $q=$pdo->query('SELECT action,entity_type,description,created_at FROM admin_activity_logs ORDER BY created_at DESC,id DESC LIMIT 8');$data['admin_activity']=$q->fetchAll();
        }
        return $data;
    }

    private function supportAwaitingCount(PDO $pdo):int
    {
        $sql="SELECT COUNT(*) FROM support_tickets t WHERE t.status IN ('open','in_progress') AND NOT EXISTS (SELECT 1 FROM support_messages m WHERE m.ticket_id=t.id AND m.id=(SELECT MAX(mx.id) FROM support_messages mx WHERE mx.ticket_id=t.id) AND m.sender_type='admin')";
        return (int)$pdo->query($sql)->fetchColumn();
    }

    private function recentSystemActivity(PDO $pdo,callable $can):array
    {
        $parts=[];
        if($can('users.view'))$parts[]="SELECT 'User registered' event,u.user_reference reference,u.created_at occurred_at FROM users u";
        if($can('accounts.view'))$parts[]="SELECT 'Demo Account created',d.account_number,d.created_at FROM demo_accounts d";
        if($can('trading.view'))$parts[]="SELECT CONCAT('Demo order ',o.status),i.symbol,o.created_at FROM orders o JOIN instruments i ON i.id=o.instrument_id";
        if($can('trading.view'))$parts[]="SELECT 'Demo position closed',t.trade_reference,t.closed_at FROM trades t";
        if($can('kyc.view'))$parts[]="SELECT 'Verification submitted',k.submission_reference,k.submitted_at FROM kyc_submissions k WHERE k.submitted_at IS NOT NULL";
        if($can('funds.view'))$parts[]="SELECT CONCAT('Virtual ',REPLACE(x.transaction_type,'_',' ')),x.description,x.created_at FROM transactions x WHERE x.transaction_type IN ('demo_deposit','demo_withdrawal','admin_adjustment')";
        if($can('support.view'))$parts[]="SELECT CONCAT('Support ticket ',s.status),s.ticket_number,s.created_at FROM support_tickets s";
        if($parts===[])return [];
        $sql='SELECT event,reference,occurred_at FROM ('.implode(' UNION ALL ',$parts).') events WHERE occurred_at IS NOT NULL ORDER BY occurred_at DESC LIMIT 12';
        return $pdo->query($sql)->fetchAll();
    }

    private function registrationChart(PDO $pdo):array
    {
        $tz=new DateTimeZone(date_default_timezone_get());$utc=new DateTimeZone('UTC');$today=new DateTimeImmutable('today',$tz);$start=$today->modify('-29 days');$from=$start->setTimezone($utc)->format('Y-m-d H:i:s');$to=$today->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
        $q=$pdo->prepare('SELECT DATE(created_at) day,COUNT(*) total FROM users WHERE created_at>=? AND created_at<? GROUP BY DATE(created_at) ORDER BY day');$q->execute([$from,$to]);$values=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$values[(string)$row['day']]=(int)$row['total'];
        $out=[];for($i=0;$i<30;$i++){$day=$start->modify('+'.$i.' days');$key=$day->format('Y-m-d');$out[]=['date'=>$key,'label'=>$day->format('M j'),'count'=>$values[$key]??0];}return $out;
    }

    private function localDayUtcRange():array
    {
        $tz=new DateTimeZone(date_default_timezone_get());$start=(new DateTimeImmutable('today',$tz));$end=$start->modify('+1 day');$utc=new DateTimeZone('UTC');return [$start->setTimezone($utc)->format('Y-m-d H:i:s'),$end->setTimezone($utc)->format('Y-m-d H:i:s')];
    }
}
