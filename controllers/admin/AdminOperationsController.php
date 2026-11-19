<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Services\Admin\AdminAuthorizationService;
use App\Services\Admin\AdminDemoAccountService;
use App\Services\Admin\AdminUserService;
use App\Services\Funds\TransactionService;
use PDOException;
use RuntimeException;
use Throwable;

final class AdminOperationsController
{
    public function __construct(private readonly AdminAuthorizationService $authz = new AdminAuthorizationService()) {}

    public function users(): void
    {
        $admin=$this->authz->requirePermission('users.view');$permissions=$this->authz->permissions((int)$admin['id']);
        try{$listing=(new AdminUserService())->listUsers($_GET,$permissions);if($this->wantsJson()){$this->listJson('admin/users/table',$listing+['role_permissions'=>$permissions],'admin/users/pagination',$listing);return;}$this->page($admin,$permissions,'Users','admin/users',$listing+['role_permissions'=>$permissions],'users');}
        catch(Throwable $e){$this->failure($e,'user list');}
    }

    public function userDetail(string $reference): void
    {
        $admin=$this->authz->requirePermission('users.view');$permissions=$this->authz->permissions((int)$admin['id']);
        try{$detail=(new AdminUserService())->getUserDetail($reference,$permissions);if(!$detail){$this->notFound('User not found.');return;}$this->page($admin,$permissions,'User · '.$reference,'admin/user-detail',$detail,'users');}
        catch(Throwable $e){$this->failure($e,'user detail');}
    }

    public function changeUserStatus(string $reference): void
    {
        $admin=$this->authz->requirePermission('users.manage');if(!$this->csrf()){$this->csrfFailure();return;}
        try{$data=(new AdminUserService())->changeUserStatus($reference,(int)$admin['id'],$this->postString('status'),$this->postString('reason'));if($this->wantsJson()){$this->json(200,true,'User status updated.',$data);return;}
            $_SESSION['_flash']['admin_info']='User status updated.';\redirect('admin/users/'.$reference);
        }catch(Throwable $e){$this->mutationFailure($e,'user status','admin/users/'.rawurlencode($reference));}
    }

    public function accounts(): void
    {
        $admin=$this->authz->requirePermission('accounts.view');$permissions=$this->authz->permissions((int)$admin['id']);
        try{$listing=(new AdminDemoAccountService())->listAccounts($_GET);if($this->wantsJson()){$this->listJson('admin/accounts/table',$listing+['role_permissions'=>$permissions],'admin/accounts/pagination',$listing);return;}$this->page($admin,$permissions,'Demo Accounts','admin/accounts',$listing+['role_permissions'=>$permissions],'accounts');}
        catch(Throwable $e){$this->failure($e,'Demo Account list');}
    }

    public function accountDetail(string $accountNumber): void
    {
        $admin=$this->authz->requirePermission('accounts.view');$permissions=$this->authz->permissions((int)$admin['id']);
        try{$detail=(new AdminDemoAccountService())->getAccountDetail($accountNumber,$permissions);if(!$detail){$this->notFound('Demo Account not found.');return;}$this->page($admin,$permissions,'Demo Account · '.$accountNumber,'admin/account-detail',$detail,'accounts');}
        catch(Throwable $e){$this->failure($e,'Demo Account detail');}
    }

    public function changeAccountStatus(string $accountNumber): void
    {
        $admin=$this->authz->requirePermission('accounts.manage');if(!$this->csrf()){$this->csrfFailure();return;}
        try{$data=(new AdminDemoAccountService())->changeAccountStatus($accountNumber,(int)$admin['id'],$this->postString('status'),$this->postString('reason'));if($this->wantsJson()){$this->json(200,true,'Demo Account status updated.',$data);return;}
            $_SESSION['_flash']['admin_info']='Demo Account status updated.';\redirect('admin/accounts/'.$accountNumber);
        }catch(Throwable $e){$this->mutationFailure($e,'Demo Account status','admin/accounts/'.rawurlencode($accountNumber));}
    }

    public function adjustBalance(string $accountNumber): void
    {
        $admin=$this->authz->requirePermission('accounts.manage');if(!$this->csrf()){$this->csrfFailure();return;}
        try{$data=TransactionService::demo()->adjustVirtualBalance($accountNumber,(int)$admin['id'],$this->postString('direction'),$this->postString('amount'),$this->postString('reason'),$this->postString('idempotency_key'));if($this->wantsJson()){$this->json(200,true,$data['duplicate']?'This adjustment was already applied.':'Virtual Demo Balance adjusted.',$data);return;}
            $_SESSION['_flash']['admin_info']='Virtual Demo Balance adjusted. Reference '.$data['transaction_reference'].'.';\redirect('admin/accounts/'.$accountNumber);
        }catch(Throwable $e){$this->mutationFailure($e,'Virtual Balance adjustment','admin/accounts/'.rawurlencode($accountNumber));}
    }

    private function page(array $admin,array $permissions,string $title,string $view,array $data,string $active):void
    {
        AdminAuthMiddleware::headers();
        \render('admin/layout/layout',['title'=>$title.' · MultiForex Administration','pageTitle'=>$title,'admin'=>$admin,'permissions'=>$permissions,'adminData'=>['role_permissions'=>$permissions]+$data,'contentView'=>$view,'activeRoute'=>$active,'csrfToken'=>\generate_admin_csrf_token(),'notice'=>\flash_get('admin_info'),'error'=>\flash_get('admin_error')]);
    }
    private function notFound(string $message):void
    {
        AdminAuthMiddleware::headers();http_response_code(404);\render('admin/error-page',['title'=>'Not found','status'=>404,'message'=>$message,'loginUrl'=>\admin_url('login')]);
    }
    private function csrf():bool{return \verify_admin_csrf_token(is_string($_POST['_admin_csrf']??null)?$_POST['_admin_csrf']:null);}
    private function postString(string $key):string{return is_string($_POST[$key]??null)?trim($_POST[$key]):'';}
    private function wantsJson():bool{return str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');}
    private function json(int $status,bool $success,string $message,?array $data=null,array $errors=[]):void
    {
        AdminAuthMiddleware::headers();http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data,'errors'=>$errors],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    }
    private function listJson(string $tableView,array $tableData,string $paginationView,array $paginationData):void
    {
        ob_start();\render($tableView,$tableData);$table=ob_get_clean();ob_start();\render($paginationView,$paginationData);$pagination=ob_get_clean();
        $this->json(200,true,'List updated.',['table_html'=>$table,'pagination_html'=>$pagination,'total_records'=>$paginationData['pagination']['total_records'],'current_page'=>$paginationData['pagination']['current_page']]);
    }
    private function csrfFailure():void
    {
        if($this->wantsJson()){$this->json(403,false,'Request verification failed. Refresh and try again.',null,['csrf'=>'Request verification failed.']);return;}
        AdminAuthMiddleware::headers();http_response_code(403);\render('admin/error-page',['title'=>'Request could not be verified','status'=>403,'message'=>'Refresh the administration page and try again.','loginUrl'=>\admin_url('login')]);
    }
    private function mutationFailure(Throwable $e,string $context,string $fallback):void
    {
        $code=(int)$e->getCode();$status=$e instanceof PDOException?503:($e instanceof RuntimeException?($code===404?404:($code===409?409:422)):500);$message=in_array($status,[404,409,422],true)?$e->getMessage():'This request could not be completed. Please try again shortly.';
        if($status!==422)error_log('Admin '.$context.' action failed: '.get_class($e));
        if($this->wantsJson()){$this->json($status,false,$message,null,['request'=>$message]);return;}
        $_SESSION['_flash']['admin_error']=$message;\redirect($fallback);
    }
    private function failure(Throwable $e,string $context):void
    {
        error_log('Admin '.$context.' could not be loaded: '.get_class($e));AdminAuthMiddleware::headers();http_response_code(503);\render('admin/error-page',['title'=>'Page temporarily unavailable','status'=>503,'message'=>'This administration data could not be loaded. Please try again shortly.','loginUrl'=>\admin_url('login')]);
    }
}
