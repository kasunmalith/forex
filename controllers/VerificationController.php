<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\Kyc\KycService;
use App\Services\Kyc\KycValidationException;
use App\Services\Kyc\SecureDocumentService;
use PDOException;
use RuntimeException;
use Throwable;

final class VerificationController
{
    public function saveProfile():void
    {
        $user=AuthMiddleware::requireAuthenticated();if(!$this->csrf()){$this->failure(403,'Request verification failed. Refresh and try again.');return;}
        try{$result=KycService::demo()->saveProfile((int)$user['id'],$_POST);$this->success(['message'=>'Your demo verification profile was saved.','status'=>$result['status']]);}
        catch(KycValidationException $e){$this->failure(422,$e->getMessage(),['errors'=>$e->errors]);}
        catch(Throwable $e){$this->report($e,'profile');$this->failure($e instanceof PDOException?503:422,$this->safeError($e));}
    }
    public function upload():void
    {
        $user=AuthMiddleware::requireAuthenticated();if(!$this->csrf()){$this->failure(403,'Request verification failed. Refresh and try again.');return;}
        $category=is_string($_POST['category']??null)?$_POST['category']:'';$type=is_string($_POST['document_type']??null)?$_POST['document_type']:'';$file=is_array($_FILES['document']??null)?$_FILES['document']:[];
        try{$result=SecureDocumentService::demo()->upload((int)$user['id'],$category,$type,$file);$this->success(['message'=>'Document uploaded to private demo storage.','document'=>$result]);}
        catch(Throwable $e){$this->report($e,'upload');$this->failure($e instanceof PDOException?503:422,$this->safeError($e));}
    }
    public function removeDocument():void
    {
        $user=AuthMiddleware::requireAuthenticated();if(!$this->csrf()){$this->failure(403,'Request verification failed. Refresh and try again.');return;}
        $ref=is_string($_POST['document_reference']??null)?$_POST['document_reference']:'';try{$ok=SecureDocumentService::demo()->removeDraftDocument((int)$user['id'],$ref);if(!$ok)throw new RuntimeException('This document cannot be replaced in the current status.');$this->success(['message'=>'Document marked as replaced.']);}catch(Throwable $e){$this->report($e,'replace');$this->failure($e instanceof PDOException?503:422,$this->safeError($e));}
    }
    public function submit():void
    {
        $user=AuthMiddleware::requireAuthenticated();if(!$this->csrf()){$this->failure(403,'Request verification failed. Refresh and try again.');return;}
        $confirm=is_string($_POST['confirmation']??null)?$_POST['confirmation']:'';try{$result=KycService::demo()->submitVerification((int)$user['id'],$confirm);$this->success(['message'=>'Your demo verification submission has been received.','status'=>$result['status']]);}
        catch(KycValidationException $e){$this->failure(422,$e->getMessage(),['errors'=>$e->errors]);}
        catch(Throwable $e){$this->report($e,'submit');$this->failure($e instanceof PDOException?503:422,$this->safeError($e));}
    }
    public function document():void
    {
        $user=AuthMiddleware::requireAuthenticated();$ref=is_string($_GET['ref']??null)?$_GET['ref']:'';SecureDocumentService::demo()->streamAuthorizedDocument($ref,(int)$user['id']);
    }
    private function csrf():bool{return \verify_csrf_token(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null);}
    private function safeError(Throwable $e):string{return $e instanceof RuntimeException&&!$e instanceof PDOException?$e->getMessage():'The verification request could not be completed. Please try again.';}
    private function report(Throwable $e,string $action):void{error_log('Demo verification '.$action.' failed for authenticated user '.(int)(\auth_user_id()??0).': '.$e->getMessage());}
    private function success(array $payload):void
    {
        if($this->wantsJson()){header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>true]+$payload,JSON_UNESCAPED_UNICODE);return;}
        $_SESSION['_flash']['client_notice']=$payload['message']??'Verification updated.';\redirect('client/verification');
    }
    private function failure(int $status,string $message,array $extra=[]):void
    {
        http_response_code($status);if($this->wantsJson()){header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>$message]+$extra,JSON_UNESCAPED_UNICODE);return;}
        $_SESSION['_flash']['client_error']=$message;\redirect('client/verification');
    }
    private function wantsJson():bool{return str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json')||strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest';}
}
