<?php
declare(strict_types=1);

namespace App\Services\Support;

use App\Database;
use PDO;
use RuntimeException;

/** Optional single private attachment for a support message. */
final class SupportAttachmentService
{
    private const ALLOWED=['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','txt'=>'text/plain'];
    private function __construct(private readonly PDO $pdo){}
    public static function demo():self{return new self(Database::connection());}
    public function store(int $ticketId,int $messageId,int $userId,array $file):?array
    {
        $err=(int)($file['error']??UPLOAD_ERR_NO_FILE);if($err===UPLOAD_ERR_NO_FILE)return null;$tmp=$file['tmp_name']??'';if($err!==UPLOAD_ERR_OK||!is_string($tmp)||!is_uploaded_file($tmp))throw new RuntimeException('The attachment could not be uploaded.');$max=(int)config('SUPPORT_MAX_FILE_SIZE',5242880);$size=filesize($tmp);if(!$size||$size>$max)throw new RuntimeException('Attachment must be smaller than '.number_format($max/1048576,0).' MB.');$name=basename(str_replace('\\','/',(string)($file['name']??'')));$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!isset(self::ALLOWED[$ext]))throw new RuntimeException('Upload a PDF, JPEG, PNG, or TXT attachment.');$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);if($mime!==self::ALLOWED[$ext])throw new RuntimeException('Attachment extension does not match its actual type.');if(str_starts_with((string)$mime,'image/')){$image=@getimagesize($tmp);if(!$image||($image['mime']??'')!==$mime)throw new RuntimeException('The image attachment is invalid.');}if($mime==='application/pdf'&&!str_starts_with((string)file_get_contents($tmp,false,null,0,8),'%PDF-'))throw new RuntimeException('The PDF attachment is invalid.');
        $usage=$this->pdo->prepare('SELECT COUNT(*),COALESCE(SUM(file_size),0) FROM support_attachments WHERE user_id=?');$usage->execute([$userId]);[$count,$total]=$usage->fetch(\PDO::FETCH_NUM);if((int)$count>=(int)config('SUPPORT_MAX_ATTACHMENTS',10)||(int)$total+$size>(int)config('SUPPORT_TOTAL_STORAGE_LIMIT',52428800))throw new RuntimeException('Support attachment storage limit reached. Please send without an attachment or try again later.');
        $stored=bin2hex(random_bytes(24)).'.'.$ext;$relative=$userId.'/'.$ticketId.'/'.$stored;$root=rtrim((string)config('PRIVATE_STORAGE_PATH',APP_ROOT.'/storage/private'),"/\\").DIRECTORY_SEPARATOR.'support';$dir=$root.DIRECTORY_SEPARATOR.$userId.DIRECTORY_SEPARATOR.$ticketId;if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Private support storage is unavailable.');$realRoot=realpath($root);$realDir=realpath($dir);if(!$realRoot||!$realDir||!str_starts_with($realDir,$realRoot.DIRECTORY_SEPARATOR))throw new RuntimeException('Invalid attachment storage location.');$path=$realDir.DIRECTORY_SEPARATOR.$stored;if(!move_uploaded_file($tmp,$path))throw new RuntimeException('The attachment could not be stored.');@chmod($path,0640);$display=preg_replace('/[\x00-\x1f\x7f"\\\/]/u','_',mb_substr($name,0,160))?:'attachment';$ref='MF-SUP-FILE-'.strtoupper(bin2hex(random_bytes(5)));$q=$this->pdo->prepare('INSERT INTO support_attachments (message_id,ticket_id,user_id,attachment_reference,original_filename,stored_filename,storage_path,mime_type,file_size,sha256) VALUES (?,?,?,?,?,?,?,?,?,?)');try{$q->execute([$messageId,$ticketId,$userId,$ref,$display,$stored,$relative,$mime,$size,hash_file('sha256',$path)]);}catch(\Throwable $e){@unlink($path);throw $e;}return ['reference'=>$ref,'filename'=>$display,'file_size'=>$size,'mime_type'=>$mime,'_relative_path'=>$relative];
    }
    public function listForMessages(array $messageIds,int $userId):array{if($messageIds===[])return [];if(count($messageIds)>100)throw new RuntimeException('Too many messages.');$ids=array_values(array_filter(array_map('intval',$messageIds),static fn($v)=>$v>0));if(!$ids)return [];$marks=implode(',',array_fill(0,count($ids),'?'));$q=$this->pdo->prepare("SELECT message_id,attachment_reference,original_filename,file_size,mime_type FROM support_attachments WHERE user_id=? AND message_id IN ($marks)");$q->execute([$userId,...$ids]);$out=[];foreach($q->fetchAll() as $row)$out[(int)$row['message_id']]=$row;return $out;}
    public function deleteStored(string $relative):void{if(preg_match('~^[1-9][0-9]*/[1-9][0-9]*/[a-f0-9]{48}\.(?:pdf|jpg|jpeg|png|txt)$~',$relative)!==1)return;$root=realpath(rtrim((string)config('PRIVATE_STORAGE_PATH',APP_ROOT.'/storage/private'),"/\\").DIRECTORY_SEPARATOR.'support');$path=realpath(rtrim((string)config('PRIVATE_STORAGE_PATH',APP_ROOT.'/storage/private'),"/\\").DIRECTORY_SEPARATOR.'support'.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative));if($root&&$path&&str_starts_with($path,$root.DIRECTORY_SEPARATOR)&&is_file($path))@unlink($path);}
    public function streamForOwner(string $reference,int $userId):never
    {
        if(preg_match('/^MF-SUP-FILE-[A-F0-9]{10}$/',$reference)!==1){http_response_code(404);exit;}
        $q=$this->pdo->prepare('SELECT a.original_filename,a.storage_path,a.mime_type,a.file_size,a.sha256,a.ticket_id FROM support_attachments a JOIN support_tickets t ON t.id=a.ticket_id AND t.user_id=a.user_id WHERE a.attachment_reference=? AND a.user_id=? LIMIT 1');$q->execute([$reference,$userId]);$file=$q->fetch();
        if(!$file||preg_match('~^'.preg_quote((string)$userId,'~').'/[1-9][0-9]*/[a-f0-9]{48}\.(?:pdf|jpg|jpeg|png|txt)$~',(string)$file['storage_path'])!==1){http_response_code(404);exit;}
        $base=realpath(rtrim((string)config('PRIVATE_STORAGE_PATH',APP_ROOT.'/storage/private'),"/\\").DIRECTORY_SEPARATOR.'support');$path=realpath(rtrim((string)config('PRIVATE_STORAGE_PATH',APP_ROOT.'/storage/private'),"/\\").DIRECTORY_SEPARATOR.'support'.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,(string)$file['storage_path']));
        if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)||filesize($path)!==(int)$file['file_size']||!hash_equals((string)$file['sha256'],(string)hash_file('sha256',$path))){http_response_code(404);exit;}
        $filename=str_replace(['"','\\'],['_','_'],(string)$file['original_filename']);header('Content-Type: '.$file['mime_type']);header('Content-Length: '.(string)$file['file_size']);header('Content-Disposition: attachment; filename="'.$filename.'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');header('Expires: 0');readfile($path);exit;
    }
}
