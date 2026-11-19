<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use RuntimeException;

/** Server-side notification creation and user-owned inbox operations. */
final class NotificationService
{
    private const TYPES = [
        'trade_executed'=>'Trading','position_closed'=>'Trading','stop_loss_triggered'=>'Trading','take_profit_triggered'=>'Trading',
        'demo_deposit_processed'=>'Funds','demo_withdrawal_processed'=>'Funds','kyc'=>'Verification',
        'verification_submitted'=>'Verification','verification_status_changed'=>'Verification','support_reply'=>'Support','system'=>'System',
    ];
    private function __construct(private readonly PDO $pdo) {}
    public static function demo(): self { return new self(Database::connection()); }

    public function create(int $userId,string $type,string $title,string $message,?string $relatedType=null,?string $relatedReference=null,?string $link=null): int
    {
        if($userId<1||!isset(self::TYPES[$type])||trim($title)===''||mb_strlen($title)>180||trim($message)===''||mb_strlen($message)>5000) throw new RuntimeException('Invalid notification data.');
        $safeLink=$this->safeLink($type,$relatedReference,$link);
        $q=$this->pdo->prepare('INSERT INTO notifications (user_id,type,title,message,link,related_type,related_reference) VALUES (?,?,?,?,?,?,?)');
        $q->execute([$userId,$type,trim($title),trim($message),$safeLink,$relatedType,$relatedReference]);
        return (int)$this->pdo->lastInsertId();
    }
    public function createOnceForEvent(int $userId,string $type,string $title,string $message,string $relatedType,string $relatedReference,?string $link=null): bool
    {
        if($relatedType===''||$relatedReference==='') throw new RuntimeException('An event reference is required.');
        if($userId<1||!isset(self::TYPES[$type])||trim($title)===''||mb_strlen($title)>180||trim($message)===''||mb_strlen($message)>5000)throw new RuntimeException('Invalid notification data.');
        $safeLink=$this->safeLink($type,$relatedReference,$link);
        $q=$this->pdo->prepare('INSERT INTO notifications (user_id,type,title,message,link,related_type,related_reference) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $q->execute([$userId,$type,trim($title),trim($message),$safeLink,$relatedType,$relatedReference]);
        return $q->rowCount()===1;
    }
    public function listForUser(int $userId,int $page=1,string $filter='all',int $perPage=20):array
    {
        $page=max(1,min(100000,$page));$perPage=max(10,min(25,$perPage));$where='user_id=:user_id';$params=['user_id'=>$userId];
        if($filter==='unread')$where.=' AND is_read=0';elseif(isset(self::categoryTypes()[$filter])){$marks=[];foreach(self::categoryTypes()[$filter] as $i=>$type){$key='type'.$i;$marks[]=':'.$key;$params[$key]=$type;}$where.=' AND type IN ('.implode(',',$marks).')';}elseif($filter!=='all')$filter='all';
        $q=$this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $where");$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$q=$this->pdo->prepare("SELECT id,type,title,message,link,related_type,related_reference,is_read,created_at,read_at FROM notifications WHERE $where ORDER BY created_at DESC,id DESC LIMIT $perPage OFFSET ".(($page-1)*$perPage));$q->execute($params);
        $rows=$q->fetchAll();foreach($rows as &$row){$row['category']=self::TYPES[$row['type']]??'System';$row['safe_link']=$this->safeLink((string)$row['type'],$row['related_reference']??null,$row['link']??null);}unset($row);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'page_count'=>$pages,'per_page'=>$perPage,'filter'=>$filter];
    }
    public function getUnreadCount(int $userId):int{$q=$this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$q->execute([$userId]);return (int)$q->fetchColumn();}
    public function markRead(int $userId,int $id):bool{$q=$this->pdo->prepare('UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=? AND is_read=0');$q->execute([$id,$userId]);return $q->rowCount()===1;}
    public function markAllRead(int $userId):int{$q=$this->pdo->prepare('UPDATE notifications SET is_read=1,read_at=NOW() WHERE user_id=? AND is_read=0');$q->execute([$userId]);return $q->rowCount();}
    public function recentForUser(int $userId,int $limit=6):array{$limit=max(1,min(8,$limit));$q=$this->pdo->prepare("SELECT id,type,title,message,link,related_type,related_reference,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT $limit");$q->execute([$userId]);$rows=$q->fetchAll();foreach($rows as &$row){$row['category']=self::TYPES[$row['type']]??'System';$row['safe_link']=$this->safeLink((string)$row['type'],$row['related_reference']??null,$row['link']??null);}unset($row);return ['unread'=>$this->getUnreadCount($userId),'items'=>$rows];}
    private function safeLink(string $type,?string $reference,?string $stored):?string
    {
        if($type==='support_reply'&&is_string($reference)&&preg_match('/^(MF-SUP-[A-F0-9]{8})(?:-M[1-9][0-9]*)?$/',$reference,$match))return \site_url('client/support/'.$match[1]);
        $route=match($type){'trade_executed','stop_loss_triggered','take_profit_triggered'=>'client/trade','position_closed'=>'client/history','demo_deposit_processed','demo_withdrawal_processed'=>'client/funds','kyc','verification_submitted','verification_status_changed'=>'client/verification','system'=>null,default=>null};
        if($route===null||!is_string($stored)||!hash_equals($route,$stored))return null;return \site_url($route);
    }
    private static function categoryTypes():array{return ['trading'=>['trade_executed','position_closed','stop_loss_triggered','take_profit_triggered'],'funds'=>['demo_deposit_processed','demo_withdrawal_processed'],'verification'=>['kyc','verification_submitted','verification_status_changed'],'support'=>['support_reply'],'system'=>['system']];}
}
