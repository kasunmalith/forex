<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\DashboardService;
use App\Services\ProviderFactory;
use App\Services\Trading\TradingException;
use PDO;
use Throwable;

final class TradingController
{
    public function placeOrder(): void
    {
        $this->headers();
        $userId = $this->authenticatedUserId();
        if ($userId === null) { $this->respond(['success'=>false,'message'=>'Your session has expired. Please sign in again.','login_url'=>\site_url('login')],401); return; }
        $request = $this->requestBody();
        if (!\verify_csrf_token(is_string($request['_csrf'] ?? null) ? $request['_csrf'] : null)) {
            $this->respond(['success'=>false,'message'=>'Your request could not be verified. Refresh the terminal and try again.'],403); return;
        }
        try {
            $result = ProviderFactory::trading()->executeOrder($userId, $request);
            $this->respond($result,200);
        } catch (TradingException $exception) {
            $this->respond(['success'=>false,'message'=>$exception->getMessage()],$exception->httpStatus);
        } catch (Throwable $exception) {
            error_log('Trading API failed for user ' . $userId . ' stage=controller');
            $this->respond(['success'=>false,'message'=>'The demo order could not be completed. Please try again.'],500);
        }
    }

    public function closePosition(): void
    {
        $this->headers(); $userId=$this->authenticatedUserId();
        if($userId===null){$this->respond(['success'=>false,'message'=>'Your session has expired. Please sign in again.'],401);return;}
        if(!$this->demoTradingActive())return;
        $request=$this->requestBody();
        if(!\verify_csrf_token(is_string($request['_csrf']??null)?$request['_csrf']:null)){$this->respond(['success'=>false,'message'=>'Your request could not be verified. Refresh the terminal and try again.'],403);return;}
        try{$id=filter_var($request['position_id']??null,FILTER_VALIDATE_INT);$volume=$request['volume']??null;if($volume!==null&&!is_string($volume)&&!is_int($volume))throw new TradingException('Enter a valid close volume.',422);$result=\App\Services\Trading\PositionLifecycleService::demo()->close($userId,(int)$id,$volume===null?null:(string)$volume,is_string($request['idempotency_key']??null)?$request['idempotency_key']:'');$this->respond($result);}
        catch(TradingException $e){$this->respond(['success'=>false,'message'=>$e->getMessage()],$e->httpStatus);}
        catch(Throwable){$this->respond(['success'=>false,'message'=>'The demo position could not be updated. Please try again.'],500);}
    }

    public function modifyPosition(): void
    {
        $this->headers(); $userId=$this->authenticatedUserId();
        if($userId===null){$this->respond(['success'=>false,'message'=>'Your session has expired. Please sign in again.'],401);return;}
        if(!$this->demoTradingActive())return;
        $request=$this->requestBody();
        if(!\verify_csrf_token(is_string($request['_csrf']??null)?$request['_csrf']:null)){$this->respond(['success'=>false,'message'=>'Your request could not be verified. Refresh the terminal and try again.'],403);return;}
        try{$id=filter_var($request['position_id']??null,FILTER_VALIDATE_INT);$result=\App\Services\Trading\PositionLifecycleService::demo()->modify($userId,(int)$id,$request);$this->respond($result);}
        catch(TradingException $e){$this->respond(['success'=>false,'message'=>$e->getMessage()],$e->httpStatus);}
        catch(Throwable){$this->respond(['success'=>false,'message'=>'Protection levels could not be updated. Please try again.'],500);}
    }

    public function positions(): void
    {
        $this->headers(); $userId=$this->authenticatedUserId();
        if ($userId===null) { $this->respond(['success'=>false,'message'=>'Your session has expired. Please sign in again.','login_url'=>\site_url('login')],401); return; }
        if(!$this->demoTradingActive())return;
        try {
            $account=DashboardService::getAccount($userId);
            $rows=$account ? DashboardService::getOpenPositions($userId,(int)$account['id'],100) : [];
            $this->respond(['success'=>true,'positions'=>array_map(static fn(array $row): array=>[
                'id'=>(int)$row['id'],'symbol'=>(string)$row['symbol'],'display_name'=>(string)$row['display_name'],'direction'=>(string)$row['direction'],
                'volume'=>(string)$row['volume'],'open_price'=>(string)$row['open_price'],'current_price'=>$row['current_price']===null?null:(string)$row['current_price'],
                'stop_loss'=>$row['stop_loss']===null?null:(string)$row['stop_loss'],'take_profit'=>$row['take_profit']===null?null:(string)$row['take_profit'],
                'position_pl'=>$row['position_pl']===null?null:(string)$row['position_pl'],'opened_at'=>(string)$row['opened_at'],'precision'=>(int)$row['precision_digits'],
            ],$rows)]);
        } catch (Throwable) { $this->respond(['success'=>false,'message'=>'Positions are temporarily unavailable.'],503); }
    }

    public function orders(): void
    {
        $this->headers(); $userId=$this->authenticatedUserId();
        if ($userId===null) { $this->respond(['success'=>false,'message'=>'Your session has expired. Please sign in again.','login_url'=>\site_url('login')],401); return; }
        if(!$this->demoTradingActive())return;
        try {
            $account=DashboardService::getAccount($userId);
            $rows=$account ? DashboardService::getRecentOrders($userId,(int)$account['id'],50) : [];
            $this->respond(['success'=>true,'orders'=>array_map(static fn(array $row): array=>[
                'id'=>(int)$row['id'],'symbol'=>(string)$row['symbol'],'display_name'=>(string)$row['display_name'],'type'=>(string)$row['order_type'],
                'direction'=>(string)$row['direction'],'volume'=>(string)$row['volume'],'requested_price'=>$row['requested_price']===null?null:(string)$row['requested_price'],
                'execution_price'=>$row['execution_price']===null?null:(string)$row['execution_price'],'stop_loss'=>$row['stop_loss']===null?null:(string)$row['stop_loss'],
                'take_profit'=>$row['take_profit']===null?null:(string)$row['take_profit'],'status'=>(string)$row['status'],'created_at'=>(string)$row['created_at'],'precision'=>(int)$row['precision_digits'],
            ],$rows)]);
        } catch (Throwable) { $this->respond(['success'=>false,'message'=>'Orders are temporarily unavailable.'],503); }
    }

    public function state(): void
    {
        $this->headers(); $userId=$this->authenticatedUserId();
        if ($userId===null) { $this->respond(['success'=>false,'message'=>'Your session has expired. Please sign in again.','login_url'=>\site_url('login')],401); return; }
        if(!$this->demoTradingActive())return;
        try {
            \App\Services\Trading\PositionLifecycleService::demo()->processTriggers($userId);
            $snapshot=\App\Services\AccountMetricsService::demo()->snapshot($userId);
            $trades=\App\Services\DashboardService::getRecentTrades($userId,(int)$snapshot['account']['id'],50);
            $orders=\App\Services\DashboardService::getRecentOrders($userId,(int)$snapshot['account']['id'],50);
            $symbol=is_string($_GET['symbol']??null)?strtoupper(trim($_GET['symbol'])):'';
            $instrument=null;
            if($symbol!==''&&preg_match('/^[A-Z0-9._-]{1,32}$/D',$symbol)===1){$q=Database::connection()->prepare("SELECT i.status,i.trading_enabled,i.market_data_enabled,c.status AS category_status FROM instruments i JOIN instrument_categories c ON c.id=i.category_id WHERE i.symbol=? LIMIT 1");$q->execute([$symbol]);$instrument=$q->fetch(PDO::FETCH_ASSOC)?:null;}
            $this->respond(['success'=>true,'account'=>$snapshot['account'],'metrics'=>$snapshot['metrics'],'positions'=>$snapshot['positions'],'orders'=>$orders,'trades'=>$trades,'trading_controls'=>\App\Services\DemoTradingSettings::all(),'selected_instrument'=>$instrument,'server_time'=>gmdate(DATE_ATOM)]);
        } catch (Throwable) { error_log('Trading state snapshot failed for user '.$userId.'.'); $this->respond(['success'=>false,'message'=>'Account metrics are temporarily unavailable.'],503); }
    }

    public function clientSnapshot(): void
    {
        $this->headers();$userId=$this->authenticatedUserId();if($userId===null){$this->respond(['success'=>false,'message'=>'Your session has expired.','login_url'=>\site_url('login')],401);return;}
        if(!$this->demoTradingActive())return;
        try{\App\Services\Trading\PositionLifecycleService::demo()->processTriggers($userId);$account=DashboardService::getAccount($userId);if(!$account){$this->respond(['success'=>true,'account'=>null,'metrics'=>null,'positions'=>[],'orders'=>[],'trades'=>[],'stats'=>[]]);return;}$accountId=(int)$account['id'];$snapshot=\App\Services\AccountMetricsService::demo()->snapshot($userId,$accountId);$view=is_string($_GET['view']??null)?$_GET['view']:'';$positions=$view==='positions'?$snapshot['positions']:array_slice($snapshot['positions'],0,5);$orders=DashboardService::getRecentOrders($userId,$accountId,$view==='orders'?50:5);$trades=DashboardService::getRecentTrades($userId,$accountId,5);$this->respond(['success'=>true,'account'=>$account,'metrics'=>$snapshot['metrics'],'positions'=>$positions,'orders'=>$orders,'trades'=>$trades,'stats'=>DashboardService::getTradingStats($userId,$accountId),'server_time'=>gmdate(DATE_ATOM)]);}
        catch(Throwable){$this->respond(['success'=>false,'message'=>'Account information is temporarily unavailable.'],503);}
    }

    public function history(): void
    {
        $this->headers();$userId=$this->authenticatedUserId();if($userId===null){$this->respond(['success'=>false,'message'=>'Your session has expired.','login_url'=>\site_url('login')],401);return;}
        if(!$this->demoTradingActive())return;
        try{$account=DashboardService::getAccount($userId);$filters=\App\Services\TradingHistoryService::filters($_GET);$data=$account?\App\Services\TradingHistoryService::browse($userId,(int)$account['id'],$filters):['rows'=>[],'total'=>0,'page_count'=>1,'summary'=>[]];$this->respond(['success'=>true]+$data);}
        catch(\InvalidArgumentException $e){$this->respond(['success'=>false,'message'=>$e->getMessage()],422);}catch(Throwable){$this->respond(['success'=>false,'message'=>'History is temporarily unavailable.'],503);}
    }

    public function historyDetail(): void
    {
        $this->headers();$userId=$this->authenticatedUserId();if($userId===null){$this->respond(['success'=>false,'message'=>'Your session has expired.','login_url'=>\site_url('login')],401);return;}
        if(!$this->demoTradingActive())return;
        $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);$account=DashboardService::getAccount($userId);$detail=$account&&$id?\App\Services\TradingHistoryService::detail($userId,(int)$account['id'],(int)$id):null;
        if(!$detail){$this->respond(['success'=>false,'message'=>'Trade record was not found.'],404);return;}$this->respond(['success'=>true,'trade'=>$detail]);
    }

    public function historyExport(): void
    {
        $userId=$this->authenticatedUserId();if($userId===null){http_response_code(401);header('Content-Type: text/plain; charset=utf-8');echo 'Session expired';return;}
        if(strtolower((string)\config('TRADING_PROVIDER','demo'))!=='demo'){http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'Demo history is unavailable while another trading provider is selected.';return;}
        try{$filters=\App\Services\TradingHistoryService::filters($_GET);$account=DashboardService::getAccount($userId);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="multiforex-demo-history.csv"');header('Cache-Control: private, no-store, no-cache, must-revalidate');header('X-Content-Type-Options: nosniff');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Trade reference','Position reference','Symbol','Side','Closed volume','Open price','Close price','Realized P/L ('.($account['currency']??'USD').')','Close reason','Open time','Close time']);if($account)foreach(\App\Services\TradingHistoryService::csv($userId,(int)$account['id'],$filters) as $row){$row=array_map(static function($v){$s=(string)$v;return preg_match('/^[\s]*[=+@\-]/u',$s)===1?"'".$s:$s;},$row);fputcsv($out,$row);}fclose($out);}
        catch(\InvalidArgumentException $e){http_response_code(422);header('Content-Type: text/plain; charset=utf-8');echo $e->getMessage();}
        catch(Throwable){http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'History export is temporarily unavailable.';}
    }

    private function authenticatedUserId(): ?int
    {
        $id=\auth_user_id();
        if ($id===null || !\auth_check()) return null;
        $query=Database::connection()->prepare("SELECT id FROM users WHERE id=? AND status='active' LIMIT 1"); $query->execute([$id]);
        return $query->fetchColumn() ? $id : null;
    }

    private function demoTradingActive(): bool
    {
        if (strtolower((string)\config('TRADING_PROVIDER', 'demo')) === 'demo') return true;
        $this->respond(['success'=>false,'error'=>['code'=>'provider_unavailable','message'=>'Demo account data is unavailable while another trading provider is selected.'],'provider'=>(string)\config('TRADING_PROVIDER','unknown')],503);
        return false;
    }

    private function requestBody(): array
    {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>16384) return [];
        $contentType=strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType,'application/json')) {
            $decoded=json_decode((string)file_get_contents('php://input'),true);
            return is_array($decoded)?$decoded:[];
        }
        return $_POST;
    }

    private function headers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache'); header('X-Content-Type-Options: nosniff');
    }

    private function respond(array $data,int $status=200): void
    {
        http_response_code($status); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
}
