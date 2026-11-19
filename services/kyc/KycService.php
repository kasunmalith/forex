<?php
declare(strict_types=1);

namespace App\Services\Kyc;

use App\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/** Demo KYC workflow. Review actions are callable by a future authorization-checked admin service. */
final class KycService
{
    private const COUNTRIES='AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';
    public function __construct(private readonly PDO $pdo,private readonly SecureDocumentService $documents){}
    public static function demo():self{return new self(Database::connection(),SecureDocumentService::demo());}
    public static function countryOptions():array{return ['AU'=>'Australia','CA'=>'Canada','CN'=>'China','FR'=>'France','DE'=>'Germany','IN'=>'India','ID'=>'Indonesia','IT'=>'Italy','JP'=>'Japan','MY'=>'Malaysia','MV'=>'Maldives','NP'=>'Nepal','NZ'=>'New Zealand','PK'=>'Pakistan','SG'=>'Singapore','LK'=>'Sri Lanka','AE'=>'United Arab Emirates','GB'=>'United Kingdom','US'=>'United States'];}

    public function getStatus(int $userId):array
    {
        $profile=$this->profile($userId);$q=$this->pdo->prepare('SELECT * FROM kyc_submissions WHERE user_id=? ORDER BY id DESC LIMIT 1');$q->execute([$userId]);$submission=$q->fetch()?:null;
        $docs=$submission?$this->getSubmissionDocuments((int)$submission['id'],$userId):[];
        return ['profile'=>$profile,'submission'=>$submission,'documents'=>$docs,'status'=>$submission['status']??'not_submitted'];
    }

    public function createDraft(int $userId):array
    {
        $this->pdo->beginTransaction();try{$u=$this->pdo->prepare('SELECT first_name,last_name FROM users WHERE id=? AND status=\'active\' FOR UPDATE');$u->execute([$userId]);$user=$u->fetch();if(!$user)throw new RuntimeException('Your account is not available.');$q=$this->pdo->prepare('SELECT * FROM kyc_submissions WHERE user_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');$q->execute([$userId]);$existing=$q->fetch();if($existing){$this->pdo->commit();return $existing;}$ref='MF-KYC-'.strtoupper(bin2hex(random_bytes(5)));$i=$this->pdo->prepare("INSERT INTO kyc_submissions (user_id,submission_reference,legal_first_name,legal_last_name,status) VALUES (?,?,?,?,'draft')");$i->execute([$userId,$ref,$user['first_name'],$user['last_name']]);$this->pdo->commit();$this->audit($userId,$ref,'kyc_draft_created');return $this->current($userId);}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function saveProfile(int $userId,array $input):array
    {
        $d=['first_name'=>$this->text($input,'first_name',100),'last_name'=>$this->text($input,'last_name',100),'phone'=>$this->text($input,'phone',40),'date_of_birth'=>$this->text($input,'date_of_birth',10),'country'=>strtoupper($this->text($input,'country',2)),'nationality'=>strtoupper($this->text($input,'nationality',2)),'address_line_1'=>$this->text($input,'address_line_1',190),'address_line_2'=>$this->text($input,'address_line_2',190),'city'=>$this->text($input,'city',120),'state_region'=>$this->text($input,'state_region',120),'postal_code'=>$this->text($input,'postal_code',32)];
        $errors=[];foreach(['first_name','last_name','address_line_1','city'] as $k)if($d[$k]==='')$errors[]=['field'=>$k,'message'=>'This field is required.'];
        if($d['phone']!==''&&preg_match('/^[0-9+().\s-]{5,40}$/',$d['phone'])!==1)$errors[]=['field'=>'phone','message'=>'Enter a valid phone number.'];
        if($d['date_of_birth']!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$d['date_of_birth']);$dateErrors=DateTimeImmutable::getLastErrors();if(!$date||($dateErrors&&($dateErrors['warning_count']||$dateErrors['error_count']))||$date->format('Y-m-d')!==$d['date_of_birth']||$date>new DateTimeImmutable('today'))$errors[]=['field'=>'date_of_birth','message'=>'Enter a valid date that is not in the future.'];}
        foreach(['country','nationality'] as $k)if($d[$k]!==''&&!in_array($d[$k],explode(' ',self::COUNTRIES),true))$errors[]=['field'=>$k,'message'=>'Choose a country from the list.'];
        if($errors)throw new KycValidationException($errors);
        $submission=$this->current($userId);if($submission&&in_array($submission['status'],['submitted','in_review'],true))throw new RuntimeException('Submitted verification details are locked while awaiting review.');
        $this->pdo->beginTransaction();try{
            $locked=$this->pdo->prepare('SELECT id,status FROM kyc_submissions WHERE user_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');$locked->execute([$userId]);$sub=$locked->fetch();$before=$this->profile($userId);
            $u=$this->pdo->prepare('UPDATE users SET first_name=?,last_name=?,phone=?,country=? WHERE id=?');$u->execute([$d['first_name'],$d['last_name'],$d['phone']?:null,$d['country']?:null,$userId]);
            $p=$this->pdo->prepare('INSERT INTO user_profiles (user_id,date_of_birth,address_line_1,address_line_2,city,state_region,postal_code,country,nationality) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE date_of_birth=VALUES(date_of_birth),address_line_1=VALUES(address_line_1),address_line_2=VALUES(address_line_2),city=VALUES(city),state_region=VALUES(state_region),postal_code=VALUES(postal_code),country=VALUES(country),nationality=VALUES(nationality)');
            $p->execute([$userId,$d['date_of_birth']?:null,$d['address_line_1']?:null,$d['address_line_2']?:null,$d['city']?:null,$d['state_region']?:null,$d['postal_code']?:null,$d['country']?:null,$d['nationality']?:null]);
            if(!$sub){$ref='MF-KYC-'.strtoupper(bin2hex(random_bytes(5)));$i=$this->pdo->prepare("INSERT INTO kyc_submissions (user_id,submission_reference,legal_first_name,legal_last_name,status) VALUES (?,?,?,?,'draft')");$i->execute([$userId,$ref,$d['first_name'],$d['last_name']]);$sub=['id'=>(int)$this->pdo->lastInsertId(),'status'=>'draft'];}
            $profileChanged=$before['first_name']!==$d['first_name']||$before['last_name']!==$d['last_name']||$before['date_of_birth']!==($d['date_of_birth']?:null)||$before['country']!==($d['country']?:null)||$before['nationality']!==($d['nationality']?:null)||$before['address_line_1']!==($d['address_line_1']?:null)||$before['city']!==($d['city']?:null);
            $next=$sub['status'];if($next==='verified'&&$profileChanged)$next='resubmission_required';if($next==='not_submitted')$next='draft';
            $this->pdo->prepare('UPDATE kyc_submissions SET legal_first_name=?,legal_last_name=?,date_of_birth=?,country=?,address=?,city=?,postal_code=?,status=?,resubmission_category=IF(?=\'resubmission_required\',\'profile\',resubmission_category),updated_at=NOW() WHERE id=? AND user_id=?')->execute([$d['first_name'],$d['last_name'],$d['date_of_birth']?:null,$d['country']?:null,trim($d['address_line_1'].' '.$d['address_line_2'])?:null,$d['city']?:null,$d['postal_code']?:null,$next,$next,(int)$sub['id'],$userId]);
            $this->pdo->commit();$this->audit($userId,(string)($sub['submission_reference']??''),'kyc_profile_saved');return $this->getStatus($userId);
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function submitVerification(int $userId,string $confirmation):array
    {
        if($confirmation!=='1')throw new KycValidationException([['field'=>'confirmation','message'=>'Confirm this demonstration submission to continue.']]);
        $this->pdo->beginTransaction();try{$q=$this->pdo->prepare('SELECT * FROM kyc_submissions WHERE user_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');$q->execute([$userId]);$s=$q->fetch();if(!$s)throw new KycValidationException([['field'=>'profile','message'=>'Save your personal information first.']]);if(!in_array($s['status'],['draft','rejected','resubmission_required'],true))throw new RuntimeException('This verification application cannot be changed in its current status.');
            $p=$this->profile($userId);$missing=[];foreach(['first_name'=>'First name','last_name'=>'Last name','phone'=>'Phone','date_of_birth'=>'Date of birth','country'=>'Country','nationality'=>'Nationality','address_line_1'=>'Residential address','city'=>'City'] as $k=>$label)if(empty($p[$k]))$missing[]=$label;
            $docs=$this->getSubmissionDocuments((int)$s['id'],$userId);$identity=array_values(array_filter($docs,static fn($d)=>$d['document_category']==='identity'&&$d['status']!=='superseded'));$address=array_values(array_filter($docs,static fn($d)=>$d['document_category']==='proof_of_address'&&$d['status']!=='superseded'));
            if($missing)throw new KycValidationException([['field'=>'profile','message'=>'Complete these fields: '.implode(', ',$missing).'.']]);if(!$identity)throw new KycValidationException([['field'=>'identity','message'=>'Upload an identity document before submitting.']]);if(!$address)throw new KycValidationException([['field'=>'proof_of_address','message'=>'Upload proof of address before submitting.']]);
            $activeDocs=array_values(array_filter($docs,static fn($d)=>$d['status']!=='superseded'));$snapshot=['name'=>$p['first_name'].' '.$p['last_name'],'date_of_birth'=>$p['date_of_birth'],'country'=>$p['country'],'nationality'=>$p['nationality'],'phone'=>$p['phone'],'address'=>$p['address_line_1'],'city'=>$p['city'],'postal_code'=>$p['postal_code'],'documents'=>array_map(static fn($d)=>['reference'=>$d['document_reference'],'category'=>$d['document_category'],'type'=>$d['document_type'],'filename'=>$d['original_filename']],$activeDocs)];
            $status=(bool)config('KYC_DEMO_AUTO_VERIFY',false)?'verified':'submitted';$u=$this->pdo->prepare('UPDATE kyc_submissions SET status=?,profile_snapshot=?,submitted_at=NOW(),resubmission_category=NULL,updated_at=NOW() WHERE id=? AND user_id=?');$u->execute([$status,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),(int)$s['id'],$userId]);$this->notify($userId,'Your demo verification submission has been received.',$s['submission_reference'],'verification_submitted');$this->pdo->commit();$this->audit($userId,$s['submission_reference'],'kyc_submitted');return $this->getStatus($userId);
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function startReview(int $submissionId,int $adminId):void{$this->changeStatus($submissionId,'in_review',$adminId,null,null,null);}
    public function markVerified(int $submissionId,int $adminId,?string $message=null):void{$this->changeStatus($submissionId,'verified',$adminId,$message,null,null);}
    public function reject(int $submissionId,int $adminId,string $message,?string $internalNote=null):void{$this->changeStatus($submissionId,'rejected',$adminId,$message,null,$internalNote);}
    public function requestResubmission(int $submissionId,int $adminId,string $category,string $message,?string $internalNote=null):void{if(!in_array($category,['identity','proof_of_address','profile'],true))throw new RuntimeException('Invalid resubmission category.');$this->changeStatus($submissionId,'resubmission_required',$adminId,$message,$category,$internalNote);}

    public function getSubmissionDocuments(int $submissionId,?int $ownerId=null):array
    {
        if(!$ownerId)throw new RuntimeException('Document ownership is required.');$q=$this->pdo->prepare('SELECT id,document_reference,document_category,document_type,original_filename,mime_type,file_size,status,created_at FROM kyc_documents WHERE kyc_submission_id=? AND user_id=? ORDER BY id');$q->execute([$submissionId,$ownerId]);return $q->fetchAll();
    }
    public function getSubmissionDocumentsForAdmin(int $submissionId,int $adminId):array{$this->assertAdminPermission($adminId);$q=$this->pdo->prepare('SELECT id,document_reference,document_category,document_type,original_filename,mime_type,file_size,status,created_at FROM kyc_documents WHERE kyc_submission_id=? ORDER BY id');$q->execute([$submissionId]);return $q->fetchAll();}

    private function changeStatus(int $id,string $status,int $adminId,?string $message,?string $category,?string $internalNote):void
    {
        $this->assertAdminPermission($adminId);$this->pdo->beginTransaction();try{$q=$this->pdo->prepare('SELECT user_id,submission_reference,status FROM kyc_submissions WHERE id=? FOR UPDATE');$q->execute([$id]);$s=$q->fetch();if(!$s)throw new RuntimeException('Verification application not found.');$allowed=match($status){'in_review'=>['submitted'],'verified','rejected','resubmission_required'=>['submitted','in_review'],default=>[]};if(!in_array($s['status'],$allowed,true))throw new RuntimeException('This verification status transition is not allowed.');$u=$this->pdo->prepare('UPDATE kyc_submissions SET status=?,reviewed_by=?,reviewed_at=NOW(),reviewer_message=?,rejection_reason=?,internal_review_note=?,resubmission_category=?,updated_at=NOW() WHERE id=?');$u->execute([$status,$adminId,$message,$status==='rejected'?$message:null,$internalNote,$category,$id]);$this->notify((int)$s['user_id'],$status==='resubmission_required'?'Your demo verification requires additional information.':'Your demo verification status has been updated.',$s['submission_reference'],'verification_status_changed');$this->pdo->commit();$this->audit((int)$s['user_id'],$s['submission_reference'],'kyc_'.$status);}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function assertAdminPermission(int $adminId):void{$q=$this->pdo->prepare("SELECT a.id FROM admins a JOIN admin_role_permissions rp ON rp.role_id=a.role_id JOIN admin_permissions p ON p.id=rp.permission_id AND p.permission_key='manage_kyc' WHERE a.id=? AND a.status='active' LIMIT 1");$q->execute([$adminId]);if(!$q->fetchColumn())throw new RuntimeException('KYC review permission is required.');}
    private function current(int $userId):array{$q=$this->pdo->prepare('SELECT * FROM kyc_submissions WHERE user_id=? ORDER BY id DESC LIMIT 1');$q->execute([$userId]);return $q->fetch()?:[];}
    private function profile(int $userId):array{$q=$this->pdo->prepare('SELECT u.first_name,u.last_name,u.email,u.phone,u.country AS account_country,p.date_of_birth,p.address_line_1,p.address_line_2,p.city,p.state_region,p.postal_code,p.country,p.nationality FROM users u LEFT JOIN user_profiles p ON p.user_id=u.id WHERE u.id=?');$q->execute([$userId]);$p=$q->fetch()?:[];$p['country']=$p['country']?:($p['account_country']??'');return $p;}
    private function text(array $input,string $key,int $max):string{$v=$input[$key]??'';return is_string($v)?trim(mb_substr($v,0,$max)):'';}
    private function notify(int $userId,string $message,string $reference,string $type):void{\App\Services\NotificationService::demo()->createOnceForEvent($userId,$type,'Demo Verification',$message,'kyc_submission',$reference.'-'.$type,'client/verification');}
    private function audit(int $userId,string $ref,string $event):void{try{$q=$this->pdo->prepare('INSERT INTO admin_activity_logs (admin_id,action,entity_type,entity_id,description,ip_address,user_agent) VALUES (NULL,\'client_kyc_event\',\'kyc_submission\',NULL,?,?,?)');$ip=filter_var($_SERVER['REMOTE_ADDR']??'',FILTER_VALIDATE_IP)?inet_pton($_SERVER['REMOTE_ADDR']):null;$agent=mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);$q->execute([$event.' user='.$userId.' submission='.preg_replace('/[^A-Z0-9-]/','',$ref),$ip,$agent]);}catch(Throwable){error_log('KYC audit event could not be recorded event='.$event.' user='.$userId);}}
}
