<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Services\Admin\AdminAuthorizationService;
use App\Services\Admin\AdminTradingSettingsService;
use PDOException;
use RuntimeException;
use Throwable;

final class AdminTradingController
{
    public function __construct(private readonly AdminAuthorizationService $authz=new AdminAuthorizationService()){}
    public function index():void{$admin=$this->authz->requirePermission('trading.view');try{$data=(new AdminTradingSettingsService())->getSettings();$this->page($admin,'Demo Trading Controls',$data);}catch(Throwable $e){$this->failure($e);}}
    public function update():void{$admin=$this->authz->requirePermission('trading.manage');if(!$this->csrfFailure())return;try{(new AdminTradingSettingsService())->updateSettings((int)$admin['id'],$_POST);$_SESSION['_flash']['admin_info']='Demo Trading configuration saved.';\redirect('admin/trading');}catch(Throwable $e){$this->mutationFailure($e);}}
    private function page(array $admin,string $title,array $data):void{AdminAuthMiddleware::headers();$permissions=$this->authz->permissions((int)$admin['id']);\render('admin/layout/layout',['title'=>$title.' · MultiForex Administration','pageTitle'=>$title,'admin'=>$admin,'permissions'=>$permissions,'adminData'=>['role_permissions'=>$permissions]+$data,'contentView'=>'admin/trading/index','activeRoute'=>'trading','csrfToken'=>\generate_admin_csrf_token(),'notice'=>\flash_get('admin_info'),'error'=>\flash_get('admin_error')]);}
    private function csrfFailure():bool{if(\verify_admin_csrf_token(is_string($_POST['_admin_csrf']??null)?$_POST['_admin_csrf']:null))return true;AdminAuthMiddleware::headers();http_response_code(403);\render('admin/error-page',['title'=>'Request could not be verified','status'=>403,'message'=>'Refresh the administration page and try again.','loginUrl'=>\admin_url('login')]);return false;}
    private function failure(Throwable $e):void{error_log('Admin Demo Trading controls could not be loaded: '.get_class($e));AdminAuthMiddleware::headers();http_response_code(503);\render('admin/error-page',['title'=>'Page temporarily unavailable','status'=>503,'message'=>'This administration data could not be loaded. Please try again shortly.','loginUrl'=>\admin_url('login')]);}
    private function mutationFailure(Throwable $e):void{$code=(int)$e->getCode();$status=$e instanceof PDOException?503:($e instanceof RuntimeException?($code===409?409:422):500);$message=in_array($status,[409,422],true)?$e->getMessage():'This request could not be completed. Please try again shortly.';if($status!==422)error_log('Admin Demo Trading settings action failed: '.get_class($e));$_SESSION['_flash']['admin_error']=$message;\redirect('admin/trading');}
}
