<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\DashboardService;
use Throwable;

final class ClientController
{
    public function show(string $page = 'dashboard'): void
    {
        $allowed=['dashboard','trade','positions','orders','history','watchlist','profile','funds','verification'];
        if(!in_array($page,$allowed,true)){http_response_code(404);$page='dashboard';}
        $authUser=AuthMiddleware::requireAuthenticated();$userId=(int)$authUser['id'];$brand=\site_setting('company_name','MultiForex');
        $data=['user'=>$authUser,'account'=>null,'notifications'=>['unread'=>0,'items'=>[]],'errorMessage'=>null,'notice'=>\flash_get('client_notice'),'error'=>\flash_get('client_error')];
        $view='client/pages/'.$page;$historyPage=filter_var($_GET['page']??1,FILTER_VALIDATE_INT);$historyPage=$historyPage===false||$historyPage<1?1:min(100000,$historyPage);
        $demoAccountPages=['dashboard','trade','positions','orders','history','watchlist','funds'];
        if(!$this->demoAccountOperationAvailable()&&in_array($page,$demoAccountPages,true)){
            http_response_code(503);$data['errorMessage']='Demo account features are unavailable while another trading provider is selected.';$view='client/pages/error';
        }else try {
            if($page==='dashboard'){$data=array_merge($data,DashboardService::getDashboard($userId));$data['verification']=\App\Services\Kyc\KycService::demo()->getStatus($userId);$data['supportCounts']=\App\Services\Support\SupportTicketService::demo()->getTicketCounts($userId);}
            elseif($page==='trade'){$raw=$_GET['symbol']??null;$symbol=is_string($raw)?strtoupper(trim($raw)):null;$data=array_merge($data,DashboardService::getTradingTerminalData($userId,$symbol));}
            else {
                $data['user']=DashboardService::getUser($userId);$data['account']=$this->demoAccountOperationAvailable()?DashboardService::getAccount($userId):null;$data['notifications']=DashboardService::getNotifications($userId);$accountId=$data['account']?(int)$data['account']['id']:0;
                switch($page){
                    case 'positions':$data['rows']=$accountId?DashboardService::getOpenPositions($userId,$accountId,100):[];break;
                    case 'orders':$data['rows']=$accountId?DashboardService::getRecentOrders($userId,$accountId,50):[];break;
                    case 'history':
                        try{$filters=\App\Services\TradingHistoryService::filters($_GET);}catch(\InvalidArgumentException $invalid){$filters=\App\Services\TradingHistoryService::filters([]);$data['filterError']=$invalid->getMessage();}
                        $history=$accountId?\App\Services\TradingHistoryService::browse($userId,$accountId,$filters):['rows'=>[],'total'=>0,'page_count'=>1,'summary'=>['total'=>0,'wins'=>0,'losses'=>0,'breakeven'=>0,'realized_pl'=>'0']];
                        $data['filters']=$filters;$data['stats']=$history['summary'];$data['totalRows']=$history['total'];$data['page']=$filters['page'];$data['pageSize']=$filters['per_page'];$data['pageCount']=$history['page_count'];$data['rows']=$history['rows'];$data['symbols']=$accountId?\App\Services\TradingHistoryService::symbols($userId,$accountId):[];break;
                    case 'watchlist':$watchlist=DashboardService::getWatchlist($userId);$data['rows']=$watchlist['items'];$data['watchlistSaved']=$watchlist['saved'];$data['instrumentOptions']=DashboardService::getAvailableInstruments();break;
                    case 'funds':$data=array_merge($data,\App\Services\Funds\DemoFundingService::demo()->overview($userId));$view='client/pages/funds';break;
                    case 'profile':case 'verification':$data['verification']=\App\Services\Kyc\KycService::demo()->getStatus($userId);$data['countryOptions']=\App\Services\Kyc\KycService::countryOptions();break;
                }
            }
        }catch(Throwable $exception){error_log('Client portal page data load failed for authenticated user id '.$userId.': '.$exception->getMessage());http_response_code(503);$data['errorMessage']='We couldn’t load this portal information right now. Please try again shortly.';$view='client/pages/error';}
        $titles=['dashboard'=>'Dashboard','trade'=>'Trading Terminal','positions'=>'Open Positions','orders'=>'Orders','history'=>'Trade History','watchlist'=>'Watchlist','profile'=>'Profile','funds'=>'Demo Funds','verification'=>'Verification'];
        \render('client/layout',['pageTitle'=>$titles[$page]??'Client Portal','companyName'=>$brand,'activePage'=>$page,'clientView'=>$view,'clientData'=>$data,'terminalMode'=>$page==='trade','clientPageTitles'=>$titles]);
    }

    public function updateWatchlist(): void
    {
        $user=AuthMiddleware::requireAuthenticated();$returnPage=$this->watchlistReturnPage();
        if(!\verify_csrf_token(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null)){http_response_code(403);$_SESSION['_flash']['client_error']='Your request could not be verified. Refresh the page and try again.';$this->show($returnPage);return;}
        $action=is_string($_POST['action']??null)?$_POST['action']:'';$rawId=$_POST['instrument_id']??null;$id=is_string($rawId)||is_int($rawId)?filter_var($rawId,FILTER_VALIDATE_INT):false;
        if(!in_array($action,['add','remove'],true)||$id===false||$id<1){http_response_code(422);$_SESSION['_flash']['client_error']='Choose a valid demo instrument and try again.';\redirect($this->watchlistReturnPath());}
        try{if($action==='add'){$saved=DashboardService::addWatchlistItem((int)$user['id'],$id);$_SESSION['_flash'][$saved?'client_notice':'client_error']=$saved?'Instrument added to your watchlist.':'That instrument is unavailable or already in your watchlist.';}else{$removed=DashboardService::removeWatchlistItem((int)$user['id'],$id);$_SESSION['_flash'][$removed?'client_notice':'client_error']=$removed?'Instrument removed from your watchlist.':'That saved instrument was not found in your watchlist.';}}
        catch(Throwable $exception){error_log('Watchlist update failed for authenticated user id '.(int)$user['id'].': '.$exception->getMessage());http_response_code(503);$_SESSION['_flash']['client_error']='Your watchlist could not be updated. Please try again.';}
        \redirect($this->watchlistReturnPath());
    }

    public function submitFunding(): void
    {
        $user=AuthMiddleware::requireAuthenticated();if(!\verify_csrf_token(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null)){http_response_code(403);$_SESSION['_flash']['client_error']='Request verification failed. Refresh and try again.';\redirect('client/funds');}
        if(!$this->demoAccountOperationAvailable()){http_response_code(503);$_SESSION['_flash']['client_error']='Demo funding is unavailable while another trading provider is selected.';\redirect('client/funds');}
        $kind=is_string($_POST['kind']??null)?$_POST['kind']:'';$amount=is_string($_POST['amount']??null)?$_POST['amount']:'';$note=is_string($_POST['note']??null)?$_POST['note']:null;$key=is_string($_POST['idempotency_key']??null)?$_POST['idempotency_key']:'';
        try{$service=\App\Services\Funds\DemoFundingService::demo();$result=$kind==='deposit'?$service->requestDeposit((int)$user['id'],$amount,$note,$key):($kind==='withdrawal'?$service->requestWithdrawal((int)$user['id'],$amount,$note,$key):throw new \RuntimeException('Choose a Demo Deposit or Demo Withdrawal.'));$_SESSION['_flash']['client_notice']=$result['status']==='pending'?'Your Demo funding request is pending review.':($result['status']==='approved'?'Your virtual Demo funding request was processed.':'Your request was already received.');}
        catch(Throwable $exception){http_response_code(422);$_SESSION['_flash']['client_error']=$exception->getMessage();}
        \redirect('client/funds');
    }

    public function cancelFundingRequest(): void
    {
        $user=AuthMiddleware::requireAuthenticated();if(!\verify_csrf_token(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null)){http_response_code(403);$_SESSION['_flash']['client_error']='Request verification failed. Refresh and try again.';\redirect('client/funds');}
        if(!$this->demoAccountOperationAvailable()){http_response_code(503);$_SESSION['_flash']['client_error']='Demo funding is unavailable while another trading provider is selected.';\redirect('client/funds');}
        $kind=is_string($_POST['kind']??null)?$_POST['kind']:'';$id=filter_var($_POST['request_id']??null,FILTER_VALIDATE_INT);
        try{$ok=$id!==false&&\App\Services\Funds\DemoFundingService::demo()->cancelRequest((int)$user['id'],$kind,(int)$id);$_SESSION['_flash'][$ok?'client_notice':'client_error']=$ok?'Pending request cancelled.':'That pending request could not be cancelled.';}catch(Throwable){$_SESSION['_flash']['client_error']='Request could not be cancelled.';}
        \redirect('client/funds');
    }

    public function watchlistApi(): void
    {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store, no-cache, must-revalidate');$userId=\auth_user_id();
        if($userId===null||!\auth_check()){http_response_code(401);echo json_encode(['success'=>false,'message'=>'Your session has expired.','login_url'=>\site_url('login')]);return;}
        $active=\App\Database::connection()->prepare("SELECT id FROM users WHERE id=? AND status='active' LIMIT 1");$active->execute([$userId]);if(!$active->fetchColumn()){http_response_code(401);echo json_encode(['success'=>false,'message'=>'Your session has expired.','login_url'=>\site_url('login')]);return;}
        if(!\verify_csrf_token(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null)){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Request verification failed.']);return;}
        $id=filter_var($_POST['instrument_id']??null,FILTER_VALIDATE_INT);$action=$_POST['action']??'';if($id===false||$id<1||!in_array($action,['add','remove'],true)){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Choose a valid instrument.']);return;}
        try{$ok=$action==='add'?DashboardService::addWatchlistItem($userId,$id):DashboardService::removeWatchlistItem($userId,$id);$items=DashboardService::getWatchlist($userId);echo json_encode(['success'=>$ok,'message'=>$ok?($action==='add'?'Added to watchlist.':'Removed from watchlist.'):'No change was made.','items'=>$items['items']]);}catch(Throwable){http_response_code(503);echo json_encode(['success'=>false,'message'=>'Watchlist is temporarily unavailable.']);}
    }

    private function watchlistReturnPage(): string { return ($_POST['return_to']??'')==='trade'?'trade':'watchlist'; }
    private function watchlistReturnPath(): string
    {
        if($this->watchlistReturnPage()!=='trade')return 'client/watchlist';$symbol=$_POST['symbol']??'';$symbol=is_string($symbol)?strtoupper(trim($symbol)):'';
        return preg_match('/^[A-Z0-9._-]{1,32}$/',$symbol)===1?'client/trade?symbol='.rawurlencode($symbol):'client/trade';
    }
    private function demoAccountOperationAvailable(): bool { return strtolower((string)\config('TRADING_PROVIDER','demo'))==='demo'; }
}
