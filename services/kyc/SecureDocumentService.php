<?php
declare(strict_types=1);

namespace App\Services\Kyc;

use App\Database;
use PDO;
use RuntimeException;
use Throwable;

/** Validates uploads and stores files outside public asset/upload paths. */
final class SecureDocumentService
{
    private const TYPES=[
        'identity'=>['passport'=>['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'],'national_id'=>['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'],'driver_licence'=>['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png']],
        'proof_of_address'=>['utility_bill'=>['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'],'bank_statement'=>['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'],'government_letter'=>['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'],'other'=>['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png']],
    ];
    private function __construct(private readonly PDO $pdo){}
    public static function demo():self{return new self(Database::connection());}

    public function upload(int $userId,string $category,string $type,array $file):array
    {
        if(!isset(self::TYPES[$category][$type]))throw new RuntimeException('Choose a supported document category and type.');
        $tmp=$file['tmp_name']??'';$error=(int)($file['error']??UPLOAD_ERR_NO_FILE);$max=(int)config('KYC_MAX_FILE_SIZE',5242880);
        if($error!==UPLOAD_ERR_OK||!is_string($tmp)||!is_uploaded_file($tmp))throw new RuntimeException($this->uploadError($error));
        $size=filesize($tmp);if($size===false||$size<1)throw new RuntimeException('The selected file is empty or unavailable.');if($size>$max)throw new RuntimeException('File is too large. Maximum allowed size is '.number_format($max/1048576,0).' MB.');
        $name=is_string($file['name']??null)?basename(str_replace('\\','/',$file['name'])):'';$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$allowed=self::TYPES[$category][$type];if(!isset($allowed[$ext]))throw new RuntimeException('Unsupported file type. Upload a PDF, JPEG, or PNG file.');
        $finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($tmp);if(!is_string($mime)||$mime!==$allowed[$ext])throw new RuntimeException('The file extension does not match its actual file type.');
        if(str_starts_with($mime,'image/')){$image=@getimagesize($tmp);if(!$image||($image['mime']??'')!==$mime)throw new RuntimeException('The selected image is not valid or cannot be decoded.');}
        elseif($mime==='application/pdf'){$head=file_get_contents($tmp,false,null,0,8);if(!is_string($head)||!str_starts_with($head,'%PDF-'))throw new RuntimeException('The selected PDF is invalid.');}
        $display=$this->safeDisplayName($name);$hash=hash_file('sha256',$tmp);if(!$hash)throw new RuntimeException('The selected file could not be verified.');
        $submission=(new KycService($this->pdo,$this))->createDraft($userId);$submissionId=(int)$submission['id'];
        $this->assertEditable($submission,$category);
        $reference='MF-KYC-DOC-'.strtoupper(bin2hex(random_bytes(5)));$stored=bin2hex(random_bytes(24)).'.'.$ext;
        $relative=$userId.'/'.$submission['submission_reference'].'/'.$stored;$dir=$this->directory($relative);
        if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Private document storage is unavailable.');@chmod($dir,0750);$destination=$this->resolve($relative,true);
        $this->pdo->beginTransaction();$written=false;
        try{
            $locked=$this->pdo->prepare('SELECT id,status,resubmission_category FROM kyc_submissions WHERE id=? AND user_id=? FOR UPDATE');$locked->execute([$submissionId,$userId]);$fresh=$locked->fetch();if(!$fresh)throw new RuntimeException('Verification application is unavailable.');$this->assertEditable($fresh,$category);$this->limitUploads($userId,$size);
            $active=$this->pdo->prepare("SELECT id FROM kyc_documents WHERE kyc_submission_id=? AND document_category=? AND status<>'superseded' ORDER BY id DESC FOR UPDATE");$active->execute([$submissionId,$category]);$old=$active->fetchAll();
            if($old!==[]){$ids=array_column($old,'id');$marks=implode(',',array_fill(0,count($ids),'?'));$u=$this->pdo->prepare("UPDATE kyc_documents SET status='superseded' WHERE id IN ($marks) AND user_id=?");$u->execute([...$ids,$userId]);}
            if(!move_uploaded_file($tmp,$destination))throw new RuntimeException('The uploaded file could not be saved to private storage.');$written=true;@chmod($destination,0640);
            $q=$this->pdo->prepare("INSERT INTO kyc_documents (document_reference,kyc_submission_id,user_id,document_category,document_type,original_filename,stored_filename,storage_path,mime_type,file_size,sha256,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending')");
            $q->execute([$reference,$submissionId,$userId,$category,$type,$display,$stored,$relative,$mime,$size,$hash]);$this->pdo->commit();$this->audit($userId,$submission['submission_reference'],'kyc_document_uploaded',$reference);return ['reference'=>$reference,'category'=>$category,'type'=>$type,'filename'=>$display,'mime_type'=>$mime,'file_size'=>$size,'status'=>'uploaded'];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($written&&is_file($destination))@unlink($destination);throw $e;}
    }

    public function removeDraftDocument(int $userId,string $reference):bool
    {
        if(preg_match('/^MF-KYC-DOC-[A-F0-9]{10}$/',$reference)!==1)return false;$q=$this->pdo->prepare('SELECT d.id,d.kyc_submission_id,s.status FROM kyc_documents d JOIN kyc_submissions s ON s.id=d.kyc_submission_id AND s.user_id=d.user_id WHERE d.document_reference=? AND d.user_id=?');$q->execute([$reference,$userId]);$row=$q->fetch();if(!$row||!in_array($row['status'],['draft','rejected','resubmission_required'],true))return false;$u=$this->pdo->prepare("UPDATE kyc_documents SET status='superseded' WHERE id=? AND user_id=? AND status<>'superseded'");$u->execute([(int)$row['id'],$userId]);return $u->rowCount()===1;
    }

    public function streamAuthorizedDocument(string $reference,int $userId):never
    {
        if(preg_match('/^MF-KYC-DOC-[A-F0-9]{10}$/',$reference)!==1){http_response_code(404);exit;}
        $q=$this->pdo->prepare('SELECT original_filename,stored_filename,storage_path,mime_type,file_size,status FROM kyc_documents WHERE document_reference=? AND user_id=? LIMIT 1');$q->execute([$reference,$userId]);$doc=$q->fetch();if(!$doc){http_response_code(404);exit;}
        $this->stream($doc);
    }

    /** Future Admin viewers must pass the existing manage_kyc permission check here. */
    public function streamAdminDocument(string $reference,int $adminId):never
    {
        if(preg_match('/^MF-KYC-DOC-[A-F0-9]{10}$/',$reference)!==1){http_response_code(404);exit;}
        $q=$this->pdo->prepare("SELECT d.original_filename,d.stored_filename,d.storage_path,d.mime_type,d.file_size,d.status FROM kyc_documents d JOIN admins a ON a.id=? AND a.status='active' JOIN admin_role_permissions rp ON rp.role_id=a.role_id JOIN admin_permissions ap ON ap.id=rp.permission_id AND ap.permission_key='manage_kyc' WHERE d.document_reference=? LIMIT 1");$q->execute([$adminId,$reference]);$doc=$q->fetch();if(!$doc){http_response_code(404);exit;}
        $this->stream($doc);
    }

    private function stream(array $doc):never
    {
        try{$path=$this->resolve((string)$doc['storage_path'],false);}catch(Throwable){http_response_code(404);exit;}if(!is_file($path)||filesize($path)!==(int)$doc['file_size']){http_response_code(404);exit;}
        $name=$this->safeDisplayName((string)$doc['original_filename']);header('Content-Type: '.$doc['mime_type']);header('Content-Length: '.(string)$doc['file_size']);header('Content-Disposition: attachment; filename="'.str_replace(['"','\\'],['_','_'],$name).'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');header('Expires: 0');readfile($path);exit;
    }

    private function resolve(string $relative,bool $create):string
    {
        if($relative===''||str_contains($relative,'..')||str_contains($relative,'\\')||str_starts_with($relative,'/')||preg_match('~^[A-Za-z]:~',$relative)||preg_match('~^[0-9]+/MF-KYC-[A-F0-9]+/[a-f0-9]{48}\.(?:pdf|jpg|jpeg|png)$~',$relative)!==1)throw new RuntimeException('Invalid private storage reference.');
        $base=rtrim((string)config('PRIVATE_STORAGE_PATH',APP_ROOT.'/storage/private'),"/\\").DIRECTORY_SEPARATOR.'kyc'.DIRECTORY_SEPARATOR;
        if($create){$root=realpath($base);$parent=realpath(dirname($base.str_replace('/',DIRECTORY_SEPARATOR,$relative)));if(!$root||!$parent||!str_starts_with($parent,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Private document path is unavailable.');$path=$parent.DIRECTORY_SEPARATOR.basename($relative);if(file_exists($path))throw new RuntimeException('A private storage name collision occurred.');return $path;}
        $root=realpath($base);$path=realpath($base.str_replace('/',DIRECTORY_SEPARATOR,$relative));if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Private document path is unavailable.');return $path;
    }
    private function directory(string $relative):string{return dirname(rtrim((string)config('PRIVATE_STORAGE_PATH',APP_ROOT.'/storage/private'),"/\\").DIRECTORY_SEPARATOR.'kyc'.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative));}
    private function assertEditable(array $submission,string $category):void
    {
        $status=$submission['status'];if(!in_array($status,['draft','rejected','resubmission_required'],true))throw new RuntimeException('Documents cannot be changed after the application has been submitted.');if($status==='resubmission_required'&&!empty($submission['resubmission_category'])&&$submission['resubmission_category']!=='profile'&&$submission['resubmission_category']!==$category)throw new RuntimeException('Only the requested document category can be replaced.');
    }
    private function limitUploads(int $userId,int $size):void
    {
        $limit=(int)config('KYC_UPLOADS_PER_HOUR',8);$q=$this->pdo->prepare('SELECT COUNT(*) FROM kyc_documents WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)');$q->execute([$userId]);if((int)$q->fetchColumn()>=$limit)throw new RuntimeException('Upload limit reached. Please try again later.');$sum=$this->pdo->prepare('SELECT COALESCE(SUM(file_size),0) FROM kyc_documents WHERE user_id=?');$sum->execute([$userId]);if((int)$sum->fetchColumn()+$size>(int)config('KYC_TOTAL_STORAGE_LIMIT',10485760))throw new RuntimeException('Your demo verification files exceed the storage limit.');
    }
    private function safeDisplayName(string $name):string{$name=preg_replace('/[\x00-\x1F\x7F"\\\/]/u','_',$name)??'document';$name=trim(mb_substr($name,0,180));return $name!==''?$name:'document';}
    private function uploadError(int $error):string{return match($error){UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'File is too large.',UPLOAD_ERR_PARTIAL=>'The file upload was incomplete. Please try again.',UPLOAD_ERR_NO_FILE=>'Choose a document to upload.',default=>'The file upload failed. Please try again.'};}
    private function audit(int $userId,string $submission,string $event,string $doc):void{try{$q=$this->pdo->prepare('INSERT INTO admin_activity_logs (admin_id,action,entity_type,entity_id,description,ip_address,user_agent) VALUES (NULL,\'client_kyc_event\',\'kyc_submission\',NULL,?,?,?)');$q->execute([$event.' user='.$userId.' submission='.preg_replace('/[^A-Z0-9-]/','',$submission).' document='.$doc,filter_var($_SERVER['REMOTE_ADDR']??'',FILTER_VALIDATE_IP)?inet_pton($_SERVER['REMOTE_ADDR']):null,mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);}catch(Throwable){error_log('KYC document audit event failed user='.$userId.' event='.$event);}}
}
