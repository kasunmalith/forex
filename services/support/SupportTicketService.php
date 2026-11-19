<?php
declare(strict_types=1);

namespace App\Services\Support;

use App\Database;
use App\Services\NotificationService;
use PDO;
use RuntimeException;
use Throwable;

final class SupportTicketService
{
    public const CATEGORIES=['account'=>'Account','trading_platform'=>'Trading Platform','demo_trading'=>'Demo Trading','demo_funds'=>'Demo Funds','verification'=>'Verification','technical_issue'=>'Technical Issue','other'=>'Other'];
    public const STATUSES=['open'=>'Open','in_progress'=>'Awaiting Support','waiting_client'=>'Awaiting Your Reply','resolved'=>'Resolved','closed'=>'Closed'];
    private function __construct(private readonly PDO $pdo){}
    public static function demo():self{return new self(Database::connection());}
    public function createTicket(int $userId,string $subject,string $category,string $priority,string $message,string $requestToken,?array $file=null):array
    {
        $subject=trim($subject);$message=trim($message);if($subject===''||mb_strlen($subject)>200)throw new RuntimeException('Enter a subject up to 200 characters.');if(!isset(self::CATEGORIES[$category]))throw new RuntimeException('Choose a support category.');if(!in_array($priority,['low','normal','high'],true))throw new RuntimeException('Choose a valid priority.');$this->validateMessage($message);if(preg_match('/^[a-f0-9]{64}$/',$requestToken)!==1)throw new RuntimeException('Refresh this page and submit again.');$this->rateLimit($userId,false);
        $attachmentRelative=null;$this->pdo->beginTransaction();try{$q=$this->pdo->prepare('SELECT id,ticket_number,subject,category,priority,status,created_at,updated_at FROM support_tickets WHERE user_id=? AND client_request_token=? LIMIT 1 FOR UPDATE');$q->execute([$userId,$requestToken]);$existing=$q->fetch();if($existing){$this->pdo->commit();return $existing;}$reference='MF-SUP-'.strtoupper(bin2hex(random_bytes(4)));$q=$this->pdo->prepare("INSERT INTO support_tickets (user_id,ticket_number,subject,category,priority,status,client_request_token) VALUES (?,?,?,?,?,'open',?)");$q->execute([$userId,$reference,$subject,$category,$priority,$requestToken]);$ticketId=(int)$this->pdo->lastInsertId();$q=$this->pdo->prepare("INSERT INTO support_messages (ticket_id,sender_type,sender_user_id,message,client_request_token) VALUES (?,'user',?,?,?)");$q->execute([$ticketId,$userId,$message,$requestToken]);$messageId=(int)$this->pdo->lastInsertId();if($file!==null&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$stored=SupportAttachmentService::demo()->store($ticketId,$messageId,$userId,$file);$attachmentRelative=$stored['_relative_path']??null;}$this->pdo->commit();return ['id'=>$ticketId,'ticket_number'=>$reference,'subject'=>$subject,'category'=>$category,'priority'=>$priority,'status'=>'open'];}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($attachmentRelative)SupportAttachmentService::demo()->deleteStored($attachmentRelative);throw $e;}
    }
    public function listUserTickets(int $userId,int $page=1,string $status='all',string $query=''):array
    {
        $page=max(1,min(100000,$page));$where='t.user_id=:uid';$params=['uid'=>$userId];if($status!=='all'&&isset(self::STATUSES[$status])){$where.=' AND t.status=:status';$params['status']=$status;}$query=trim(mb_substr($query,0,80));if($query!==''){$where.=' AND (t.ticket_number LIKE :searchref OR t.subject LIKE :searchsubject)';$params['searchref']='%'.$query.'%';$params['searchsubject']='%'.$query.'%';}$q=$this->pdo->prepare("SELECT COUNT(*) FROM support_tickets t WHERE $where");$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=min($page,$pages);$q=$this->pdo->prepare("SELECT t.id,t.ticket_number,t.subject,t.category,t.priority,t.status,t.created_at,t.updated_at,t.closed_at,(SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id=t.id AND m.sender_type='admin' AND m.is_read=0) AS unread_replies,(SELECT MAX(m.created_at) FROM support_messages m WHERE m.ticket_id=t.id) AS last_activity FROM support_tickets t WHERE $where ORDER BY COALESCE(last_activity,t.updated_at) DESC,t.id DESC LIMIT 20 OFFSET ".(($page-1)*20));$q->execute($params);return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'page_count'=>$pages,'status'=>$status,'query'=>$query];
    }
    public function getUserTicket(int $userId,string $reference):?array{if(preg_match('/^MF-SUP-[A-F0-9]{8}$/',$reference)!==1)return null;$q=$this->pdo->prepare('SELECT id,user_id,ticket_number,subject,category,priority,status,assigned_admin_id,created_at,updated_at,closed_at FROM support_tickets WHERE user_id=? AND ticket_number=? LIMIT 1');$q->execute([$userId,$reference]);$ticket=$q->fetch();if(!$ticket)return null;$q=$this->pdo->prepare('SELECT id,sender_type,sender_user_id,sender_admin_id,message,created_at,is_read FROM support_messages WHERE ticket_id=? ORDER BY id');$q->execute([(int)$ticket['id']]);$ticket['messages']=$q->fetchAll();$attachmentMap=SupportAttachmentService::demo()->listForMessages(array_column($ticket['messages'],'id'),$userId);foreach($ticket['messages'] as &$m){$m['attachment']=$attachmentMap[(int)$m['id']]??null;$m['sender_label']=$m['sender_type']==='admin'?'MultiForex Support':'You';}unset($m);$this->pdo->prepare("UPDATE support_messages SET is_read=1 WHERE ticket_id=? AND sender_type='admin' AND is_read=0")->execute([(int)$ticket['id']]);return $ticket;}
    public function addClientReply(int $userId,string $reference,string $message,string $requestToken,?array $file=null):array
    {
        $message=trim($message);$this->validateMessage($message);$this->rateLimit($userId,true);if(preg_match('/^[a-f0-9]{64}$/',$requestToken)!==1)throw new RuntimeException('Refresh the page and submit again.');$attachmentRelative=null;$this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT id,status FROM support_tickets WHERE user_id=? AND ticket_number=? LIMIT 1 FOR UPDATE');$q->execute([$userId,$reference]);$ticket=$q->fetch();if(!$ticket)throw new RuntimeException('Ticket not found.');if($ticket['status']==='closed')throw new RuntimeException('This ticket is closed. Create a new ticket to continue.');
            $dupe=$this->pdo->prepare('SELECT id FROM support_messages WHERE ticket_id=? AND client_request_token=? LIMIT 1');$dupe->execute([(int)$ticket['id'],$requestToken]);
            if(!$dupe->fetchColumn()){
                $q=$this->pdo->prepare("INSERT INTO support_messages (ticket_id,sender_type,sender_user_id,message,client_request_token) VALUES (?,'user',?,?,?)");$q->execute([(int)$ticket['id'],$userId,$message,$requestToken]);$messageId=(int)$this->pdo->lastInsertId();
                if($file!==null&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$stored=SupportAttachmentService::demo()->store((int)$ticket['id'],$messageId,$userId,$file);$attachmentRelative=$stored['_relative_path']??null;}
                $this->pdo->prepare("UPDATE support_tickets SET status='in_progress',updated_at=NOW() WHERE id=? AND user_id=?")->execute([(int)$ticket['id'],$userId]);
            }
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($attachmentRelative)SupportAttachmentService::demo()->deleteStored($attachmentRelative);throw $e;}
        return $this->getUserTicket($userId,$reference)??[];
    }
    /** Future Admin callers must enforce their own authenticated support permission before calling. */
    public function addSupportReply(int $adminId,int $ticketId,string $message):void
    {
        $this->assertSupportAdmin($adminId);$this->validateMessage(trim($message));$this->pdo->beginTransaction();try{$q=$this->pdo->prepare('SELECT id,user_id,ticket_number,status FROM support_tickets WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$ticketId]);$t=$q->fetch();if(!$t||$t['status']==='closed')throw new RuntimeException('Ticket is unavailable.');$this->pdo->prepare("INSERT INTO support_messages (ticket_id,sender_type,sender_admin_id,message) VALUES (?,'admin',?,?)")->execute([$ticketId,$adminId,trim($message)]);$messageId=(int)$this->pdo->lastInsertId();$this->pdo->prepare("UPDATE support_tickets SET status='waiting_client',updated_at=NOW() WHERE id=?")->execute([$ticketId]);try{NotificationService::demo()->createOnceForEvent((int)$t['user_id'],'support_reply','New Support Reply','Your ticket '.$t['ticket_number'].' has a new response.','support_message',$t['ticket_number'].'-M'.$messageId,'client/support/'.$t['ticket_number']);}catch(Throwable $e){error_log('Support reply notification failed for ticket '.$t['ticket_number'].': '.$e->getMessage());}$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function changeStatus(int $actorUserId,string $reference,string $status):bool
    {
        $allowed=['resolved'=>['in_progress','waiting_client','open'],'closed'=>['resolved','in_progress','waiting_client','open'],'open'=>['resolved']];if(!isset($allowed[$status]))return false;$this->pdo->beginTransaction();try{$q=$this->pdo->prepare('SELECT id,status FROM support_tickets WHERE user_id=? AND ticket_number=? LIMIT 1 FOR UPDATE');$q->execute([$actorUserId,$reference]);$t=$q->fetch();if(!$t||!in_array($t['status'],$allowed[$status],true)){$this->pdo->rollBack();return false;}$q=$this->pdo->prepare('UPDATE support_tickets SET status=?,closed_at=?,updated_at=NOW() WHERE id=? AND user_id=?');$q->execute([$status,$status==='closed'?date('Y-m-d H:i:s'):null,(int)$t['id'],$actorUserId]);$this->pdo->commit();return true;}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    /** Future Admin callers must enforce their own permission before using this service method. */
    public function setStatus(int $adminId,int $ticketId,string $status):bool
    {
        $this->assertSupportAdmin($adminId);if(!in_array($status,['open','in_progress','waiting_client','resolved','closed'],true))return false;$q=$this->pdo->prepare("UPDATE support_tickets SET status=?,closed_at=IF(?='closed',NOW(),NULL),resolved_at=IF(?='resolved',NOW(),NULL),updated_at=NOW() WHERE id=?");$q->execute([$status,$status,$status,$ticketId]);return $q->rowCount()===1;
    }
    /** Future Admin callers must enforce their own permission before using this service method. */
    public function assignTicket(int $adminId,int $ticketId,?int $assignedAdminId):bool
    {
        $this->assertSupportAdmin($adminId);if($assignedAdminId!==null){$q=$this->pdo->prepare("SELECT id FROM admins WHERE id=? AND status='active'");$q->execute([$assignedAdminId]);if(!$q->fetchColumn())return false;}$q=$this->pdo->prepare('UPDATE support_tickets SET assigned_admin_id=?,updated_at=NOW() WHERE id=?');$q->execute([$assignedAdminId,$ticketId]);return $q->rowCount()===1;
    }
    public function reopen(int $userId,string $reference):bool{return $this->changeStatus($userId,$reference,'open');}
    public function close(int $userId,string $reference):bool{return $this->changeStatus($userId,$reference,'closed');}
    public function getUnreadSupportCount(int $userId):int{$q=$this->pdo->prepare("SELECT COUNT(*) FROM support_messages m JOIN support_tickets t ON t.id=m.ticket_id WHERE t.user_id=? AND m.sender_type='admin' AND m.is_read=0");$q->execute([$userId]);return (int)$q->fetchColumn();}
    public function getTicketCounts(int $userId):array{$q=$this->pdo->prepare("SELECT status,COUNT(*) AS total FROM support_tickets WHERE user_id=? GROUP BY status");$q->execute([$userId]);$r=['open'=>0,'waiting'=>0,'resolved'=>0,'unread'=>$this->getUnreadSupportCount($userId)];foreach($q->fetchAll() as $row){if($row['status']==='open')$r['open']+=(int)$row['total'];elseif(in_array($row['status'],['in_progress','waiting_client'],true))$r['waiting']+=(int)$row['total'];elseif(in_array($row['status'],['resolved','closed'],true))$r['resolved']+=(int)$row['total'];}return $r;}
    private function validateMessage(string $message):void{$max=(int)config('SUPPORT_MAX_MESSAGE_LENGTH',10000);if($message===''||mb_strlen($message)>$max)throw new RuntimeException($message===''?'Enter a message before sending.':'Messages must be '.$max.' characters or fewer.');}
    private function assertSupportAdmin(int $adminId):void{$q=$this->pdo->prepare("SELECT a.id FROM admins a JOIN admin_role_permissions rp ON rp.role_id=a.role_id JOIN admin_permissions p ON p.id=rp.permission_id AND p.permission_key='manage_support' WHERE a.id=? AND a.status='active' LIMIT 1");$q->execute([$adminId]);if(!$q->fetchColumn())throw new RuntimeException('Support management permission is required.');}
    private function rateLimit(int $userId,bool $reply):void{$limit=$reply?(int)config('SUPPORT_REPLIES_PER_MINUTE',6):(int)config('SUPPORT_TICKETS_PER_10_MINUTES',3);$q=$this->pdo->prepare($reply?"SELECT COUNT(*) FROM support_messages WHERE sender_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)":"SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)");$q->execute([$userId]);if((int)$q->fetchColumn()>=$limit)throw new RuntimeException($reply?'You have sent several replies recently. Please wait a moment.':'You have opened several tickets recently. Please try again later.');}
}
