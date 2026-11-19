<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;
use App\Services\AccountMetricsService;
use App\Services\DashboardService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class AdminUserService
{
    private const STATUSES = ['active','suspended','disabled','pending','closed'];
    private const VERIFICATION = ['not_submitted','draft','submitted','in_review','verified','rejected','resubmission_required'];
    private const SORTS = [
        'created' => 'u.created_at', 'name' => 'u.last_name', 'email' => 'u.email',
        'status' => 'u.status', 'balance' => 'COALESCE(latest_account.balance,0)', 'last_login' => 'u.last_login_at',
    ];
    private const ACCOUNT_SUMMARY = "LEFT JOIN (SELECT user_id,COUNT(*) account_count,MAX(id) latest_account_id FROM demo_accounts GROUP BY user_id) ua ON ua.user_id=u.id LEFT JOIN demo_accounts latest_account ON latest_account.id=ua.latest_account_id";
    private const KYC_SUMMARY = "LEFT JOIN (SELECT user_id,MAX(id) latest_kyc_id FROM kyc_submissions GROUP BY user_id) ulk ON ulk.user_id=u.id LEFT JOIN kyc_submissions latest_kyc ON latest_kyc.id=ulk.latest_kyc_id";
    private const POSITION_SUMMARY = "LEFT JOIN (SELECT user_id,COUNT(*) open_positions FROM positions WHERE status='open' GROUP BY user_id) up ON up.user_id=u.id";

    public function listUsers(array $input, array $permissions): array
    {
        $filters = $this->filters($input);
        $canAccounts = in_array('accounts.view', $permissions, true);
        $canKyc = in_array('kyc.view', $permissions, true);
        if (!$canAccounts) $filters['account_status'] = 'all';
        if (!$canKyc) $filters['verification'] = 'all';
        $where = [];
        $params = [];
        if ($filters['search'] !== '') {
            $searchParts = ["u.user_reference LIKE :search0", "u.first_name LIKE :search1", "u.last_name LIKE :search2", "CONCAT_WS(' ',u.first_name,u.last_name) LIKE :search3", "u.email LIKE :search4"];
            if ($canAccounts) $searchParts[] = 'EXISTS (SELECT 1 FROM demo_accounts qs WHERE qs.user_id=u.id AND qs.account_number LIKE :search5)';
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
            $search = '%' . $this->escapeLike($filters['search']) . '%';
            foreach (range(0, $canAccounts ? 5 : 4) as $index) $params['search' . $index] = $search;
        }
        if ($filters['status'] !== 'all') { $where[] = 'u.status=:status'; $params['status'] = $filters['status']; }
        if ($filters['verification'] !== 'all') { $where[] = "COALESCE(latest_kyc.status,'not_submitted')=:verification"; $params['verification'] = $filters['verification']; }
        if ($filters['country'] !== '') { $where[] = 'COALESCE(uprofile.country,u.country)=:country'; $params['country'] = $filters['country']; }
        if ($filters['account_status'] !== 'all') {
            if ($filters['account_status'] === 'none') $where[] = 'ua.account_count IS NULL';
            else { $where[] = 'EXISTS (SELECT 1 FROM demo_accounts fs WHERE fs.user_id=u.id AND fs.status=:account_status)'; $params['account_status'] = $filters['account_status']; }
        }
        if ($filters['from'] !== null) { $where[] = 'u.created_at>=:date_from'; $params['date_from'] = $filters['from']; }
        if ($filters['to'] !== null) { $where[] = 'u.created_at<:date_to'; $params['date_to'] = $filters['to']; }
        $join = self::ACCOUNT_SUMMARY . ' ' . self::KYC_SUMMARY . ' LEFT JOIN user_profiles uprofile ON uprofile.user_id=u.id';
        $base = ' FROM users u ' . $join . (self::POSITION_SUMMARY ? ' ' . self::POSITION_SUMMARY : '');
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $count = Database::connection()->prepare('SELECT COUNT(*)' . $base . $clause);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $page = min($filters['page'], max(1, (int)ceil($total / $filters['per_page'])));
        $offset = ($page - 1) * $filters['per_page'];
        $sort = self::SORTS[$filters['sort']];
        $direction = $filters['direction'];
        $select = "SELECT u.user_reference,u.first_name,u.last_name,u.email,u.country,u.status,u.created_at,u.last_login_at,
                COALESCE(latest_kyc.status,'not_submitted') verification_status";
        $select .= $canAccounts ? ',COALESCE(ua.account_count,0) account_count,latest_account.account_number,latest_account.status account_status,latest_account.balance account_balance,latest_account.currency account_currency,COALESCE(up.open_positions,0) open_positions' : ',NULL account_count,NULL account_number,NULL account_status,NULL account_balance,NULL account_currency,NULL open_positions';
        if (!$canKyc) $select = str_replace("COALESCE(latest_kyc.status,'not_submitted') verification_status", "NULL verification_status", $select);
        $query = Database::connection()->prepare($select . $base . $clause . " ORDER BY {$sort} {$direction},u.id DESC LIMIT {$filters['per_page']} OFFSET {$offset}");
        $query->execute($params);
        $users = $query->fetchAll();
        $countries=Database::connection()->query("SELECT DISTINCT COALESCE(p.country,u.country) country FROM users u LEFT JOIN user_profiles p ON p.user_id=u.id WHERE COALESCE(p.country,u.country) REGEXP '^[A-Z]{2}$' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);
        return ['users'=>$users,'countries'=>$countries,'filters'=>$filters,'pagination'=>['current_page'=>$page,'per_page'=>$filters['per_page'],'total_records'=>$total,'total_pages'=>max(1,(int)ceil($total/$filters['per_page']))]];
    }

    public function getUserDetail(string $reference, array $permissions): ?array
    {
        if (preg_match('/^MFU-[A-F0-9]{16}$/', $reference) !== 1) return null;
        $pdo = Database::connection();
        $q = $pdo->prepare('SELECT u.id,u.user_reference,u.first_name,u.last_name,u.email,u.phone,u.country,u.status,u.email_verified_at,u.last_login_at,u.created_at,u.updated_at,p.address_line_1,p.city,p.state_region,p.postal_code,COALESCE(p.country,u.country) profile_country FROM users u LEFT JOIN user_profiles p ON p.user_id=u.id WHERE u.user_reference=? LIMIT 1');
        $q->execute([$reference]);
        $user = $q->fetch();
        if (!$user) return null;
        $can = static fn(string $key): bool => in_array($key, $permissions, true);
        if (!$can('users.manage') && !$can('kyc.view')) {
            $user['phone'] = null; $user['address_line_1'] = null; $user['city'] = null; $user['state_region'] = null; $user['postal_code'] = null;
        }

        $verification = null;
        if ($can('kyc.view')) {
            $k = $pdo->prepare('SELECT submission_reference,status,submitted_at,updated_at FROM kyc_submissions WHERE user_id=? ORDER BY id DESC LIMIT 1');
            $k->execute([(int)$user['id']]); $verification = $k->fetch() ?: null;
            if (!$verification) $verification = ['submission_reference'=>null,'status'=>'not_submitted','submitted_at'=>null,'updated_at'=>null];
        }

        $accounts = [];
        $selectedMetrics = null;
        $selectedAccount = null;
        $positions = $orders = $trades = [];
        if ($can('accounts.view')) {
            $a = $pdo->prepare("SELECT d.id,d.account_number,d.account_type,d.currency,d.balance,d.realized_pl,d.status,d.created_at,COALESCE(op.open_positions,0) open_positions FROM demo_accounts d LEFT JOIN (SELECT demo_account_id,COUNT(*) open_positions FROM positions WHERE status='open' GROUP BY demo_account_id) op ON op.demo_account_id=d.id WHERE d.user_id=? ORDER BY FIELD(d.status,'active','suspended','disabled','closed'),d.created_at DESC,d.id DESC");
            $a->execute([(int)$user['id']]); $accounts = $a->fetchAll();
            if ($accounts) {
                $selectedAccount = $accounts[0];
                $snapshot = AccountMetricsService::demo()->snapshot((int)$user['id'], (int)$selectedAccount['id']);
                $selectedMetrics = $snapshot['metrics'];
                $positions = $snapshot['positions'];
                if ($can('trading.view')) {
                    $orders = $this->recentOrders($pdo, (int)$user['id'], (int)$selectedAccount['id']);
                    $trades = $this->recentTrades($pdo, (int)$user['id'], (int)$selectedAccount['id']);
                }
            }
        }

        $funds = [];
        if ($can('funds.view')) {
            $f = $pdo->prepare("SELECT transaction_type,amount,balance_before,balance_after,description,status,created_at FROM transactions WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 10");
            $f->execute([(int)$user['id']]); $funds['transactions'] = $f->fetchAll();
            $r = $pdo->prepare("SELECT 'deposit' kind,amount,status,created_at FROM deposit_requests WHERE user_id=? AND status='pending' UNION ALL SELECT 'withdrawal',amount,status,created_at FROM withdrawal_requests WHERE user_id=? AND status='pending' ORDER BY created_at DESC LIMIT 10");
            $r->execute([(int)$user['id'],(int)$user['id']]); $funds['pending_requests'] = $r->fetchAll();
        }

        $support = null;
        if ($can('support.view')) {
            $s = $pdo->prepare("SELECT COUNT(*) open_count FROM support_tickets WHERE user_id=? AND status IN ('open','in_progress','waiting_client')");
            $s->execute([(int)$user['id']]); $support = ['open_count'=>(int)$s->fetchColumn(),'latest_reference'=>null,'latest_status'=>null];
            $latest = $pdo->prepare("SELECT ticket_number,status FROM support_tickets WHERE user_id=? AND status IN ('open','in_progress','waiting_client') ORDER BY updated_at DESC,id DESC LIMIT 1");
            $latest->execute([(int)$user['id']]); $ticket=$latest->fetch();
            if($ticket){$support['latest_reference']=$ticket['ticket_number'];$support['latest_status']=$ticket['status'];}
        }

        $activity = $this->recentUserActivity($pdo, (int)$user['id']);
        $adminActivity = [];
        if ($can('audit.view')) {
            $audit = $pdo->prepare("SELECT action,description,created_at FROM admin_activity_logs WHERE entity_type='user' AND entity_id=? ORDER BY created_at DESC,id DESC LIMIT 20");
            $audit->execute([(int)$user['id']]); $adminActivity = $audit->fetchAll();
        }
        $tradingSummary = null;
        if ($can('trading.view') && $selectedAccount) $tradingSummary = DashboardService::getTradingStats((int)$user['id'], (int)$selectedAccount['id']);

        return [
            'user'=>$user,'verification'=>$verification,'accounts'=>$accounts,'selected_account'=>$selectedAccount,'metrics'=>$selectedMetrics,
            'positions'=>$positions,'orders'=>$orders,'trades'=>$trades,'funds'=>$funds,'support'=>$support,'activity'=>$activity,
            'admin_activity'=>$adminActivity,'trading_summary'=>$tradingSummary,
            'permissions'=>['users_manage'=>$can('users.manage'),'accounts_view'=>$can('accounts.view'),'trading_view'=>$can('trading.view'),'funds_view'=>$can('funds.view'),'kyc_view'=>$can('kyc.view'),'support_view'=>$can('support.view'),'audit_view'=>$can('audit.view')],
        ];
    }

    public function changeUserStatus(string $reference, int $adminId, string $status, string $reason): array
    {
        if (!in_array($status, ['active','suspended','disabled'], true)) throw new RuntimeException('Choose Active, Suspended, or Disabled.');
        $reason = trim($reason);
        if (mb_strlen($reason) > 500 || ($status !== 'active' && $reason === '')) throw new RuntimeException('A reason is required for suspension or disabling and must be 500 characters or fewer.');
        $pdo = Database::connection(); $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT id,user_reference,first_name,last_name,status FROM users WHERE user_reference=? LIMIT 1 FOR UPDATE');
            $q->execute([$reference]); $user = $q->fetch();
            if (!$user) throw new RuntimeException('User not found.',404);
            if (!in_array($user['status'], ['active','suspended','disabled','pending'], true)) throw new RuntimeException('This User status cannot be changed from the Admin area.',409);
            if ($user['status'] === $status) throw new RuntimeException('The User already has that status.',409);
            $update = $pdo->prepare('UPDATE users SET status=?,updated_at=NOW() WHERE id=?');
            $update->execute([$status,(int)$user['id']]);
            AdminActivityLogger::logOrFail($adminId,'user_status_changed','user',(int)$user['id'],sprintf('User %s · %s → %s · Reason: %s',(string)$user['user_reference'],(string)$user['status'],$status,$reason!==''?$reason:'Reactivated by authorized administrator.'));
            $pdo->commit();
            return ['reference'=>$reference,'name'=>trim($user['first_name'].' '.$user['last_name']),'old_status'=>$user['status'],'status'=>$status];
        } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
    }

    private function recentOrders(PDO $pdo,int $userId,int $accountId): array
    {
        $q=$pdo->prepare('SELECT o.order_type,o.direction,o.volume,o.execution_price,o.status,o.created_at,i.symbol FROM orders o JOIN instruments i ON i.id=o.instrument_id WHERE o.user_id=? AND o.demo_account_id=? ORDER BY o.created_at DESC,o.id DESC LIMIT 10');$q->execute([$userId,$accountId]);return $q->fetchAll();
    }
    private function recentTrades(PDO $pdo,int $userId,int $accountId): array
    {
        $q=$pdo->prepare('SELECT t.trade_reference,t.direction,t.volume,t.open_price,t.close_price,t.profit_loss,t.close_reason,t.opened_at,t.closed_at,i.symbol FROM trades t JOIN instruments i ON i.id=t.instrument_id WHERE t.user_id=? AND t.demo_account_id=? ORDER BY t.closed_at DESC,t.id DESC LIMIT 10');$q->execute([$userId,$accountId]);return $q->fetchAll();
    }
    private function recentUserActivity(PDO $pdo,int $userId): array
    {
        $sql="SELECT event,reference,occurred_at FROM (
            SELECT 'User registered' event,u.user_reference reference,u.created_at occurred_at FROM users u WHERE u.id=?
            UNION ALL SELECT 'Demo Account created',d.account_number,d.created_at FROM demo_accounts d WHERE d.user_id=?
            UNION ALL SELECT CONCAT('Demo order ',o.status),i.symbol,o.created_at FROM orders o JOIN instruments i ON i.id=o.instrument_id WHERE o.user_id=?
            UNION ALL SELECT 'Demo position closed',t.trade_reference,t.closed_at FROM trades t WHERE t.user_id=?
            UNION ALL SELECT CONCAT('Virtual ',REPLACE(tx.transaction_type,'_',' ')),tx.description,tx.created_at FROM transactions tx WHERE tx.user_id=?
            UNION ALL SELECT 'Verification submitted',k.submission_reference,k.submitted_at FROM kyc_submissions k WHERE k.user_id=? AND k.submitted_at IS NOT NULL
            UNION ALL SELECT CONCAT('Support ticket ',st.status),st.ticket_number,st.created_at FROM support_tickets st WHERE st.user_id=?
        ) events ORDER BY occurred_at DESC LIMIT 40";
        $q=$pdo->prepare($sql);$q->execute(array_fill(0,7,$userId));return $q->fetchAll();
    }
    private function filters(array $input): array
    {
        $search=isset($input['search'])&&is_string($input['search'])?mb_substr(trim($input['search']),0,100):'';
        $status=isset($input['status'])&&in_array($input['status'],self::STATUSES,true)?$input['status']:'all';
        $verification=isset($input['verification'])&&in_array($input['verification'],self::VERIFICATION,true)?$input['verification']:'all';
        $accountStatus=isset($input['account_status'])&&in_array($input['account_status'],['none','active','suspended','disabled','closed'],true)?$input['account_status']:'all';
        $country=isset($input['country'])&&is_string($input['country'])?strtoupper(trim($input['country'])):'';if(preg_match('/^[A-Z]{2}$/',$country)!==1)$country='';
        $sort=isset($input['sort'])&&isset(self::SORTS[$input['sort']])?$input['sort']:'created';
        $direction=isset($input['direction'])&&strtolower((string)$input['direction'])==='asc'?'ASC':'DESC';
        $per=isset($input['per_page'])&&in_array((int)$input['per_page'],[25,50,100],true)?(int)$input['per_page']:25;
        $page=isset($input['page'])&&filter_var($input['page'],FILTER_VALIDATE_INT)!==false?max(1,min(100000,(int)$input['page'])):1;
        $date=isset($input['date'])&&in_array($input['date'],['all','today','7d','30d','custom'],true)?$input['date']:'all';$from=$to=null;
        $tz=new DateTimeZone(date_default_timezone_get());$now=new DateTimeImmutable('now',$tz);
        if($date==='today'){$from=$now->setTime(0,0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');$to=$now->setTime(0,0)->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
        elseif($date==='7d'||$date==='30d'){$days=$date==='7d'?7:30;$from=$now->modify('-'.($days-1).' days')->setTime(0,0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
        elseif($date==='custom'){
            $fromRaw=isset($input['from'])&&is_string($input['from'])?$input['from']:'';$toRaw=isset($input['to'])&&is_string($input['to'])?$input['to']:'';
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$fromRaw)===1){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$fromRaw,$tz);if($d&&$d->format('Y-m-d')===$fromRaw)$from=$d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$toRaw)===1){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$toRaw,$tz);if($d&&$d->format('Y-m-d')===$toRaw)$to=$d->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
            if($from!==null&&$to!==null&&$from>=$to){$from=$to=null;$date='all';}
        }
        return ['search'=>$search,'status'=>$status,'verification'=>$verification,'country'=>$country,'account_status'=>$accountStatus,'date'=>$date,'from'=>$from,'to'=>$to,'sort'=>$sort,'direction'=>$direction,'page'=>$page,'per_page'=>$per];
    }
    private function escapeLike(string $value): string { return strtr($value,['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']); }
}
