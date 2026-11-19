<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\ProviderFactory;
use App\Services\ProviderException;
use RuntimeException;
use Throwable;

final class MarketDataController
{
    public function quotes(): void
    {
        $raw=$_GET['symbols']??null; $symbols=null;
        if($raw!==null){if(!is_string($raw)||trim($raw)===''){$this->error(400,'invalid_symbols','Provide one or more instrument symbols.');return;}$parts=array_values(array_unique(array_map('strtoupper',array_map('trim',explode(',',$raw)))));if(count($parts)>40||count($parts)<1||count(array_filter($parts,static fn($s)=>preg_match('/^[A-Z0-9]{2,16}$/D',$s)===1))!==count($parts)){$this->error(400,'invalid_symbols','The symbol list is invalid or exceeds 40 instruments.');return;}$symbols=$parts;}
        if(!$this->allowRequests('quotes',80))return;
        try{$provider=ProviderFactory::marketData();$this->json(['success'=>true,'provider'=>$provider->name(),'is_demo'=>$provider->name()==='demo','timestamp'=>gmdate('c'),'quotes'=>$provider->quotes($symbols)]);}catch(ProviderException $e){error_log('Market quote provider failure category='.$e->category);$this->error($e->httpStatus,$e->category,$e->getMessage());}catch(Throwable $e){error_log('Market quote API failed type='.get_class($e));$this->error(503,'market_data_unavailable','The selected market-data provider is temporarily unavailable.');}
    }
    public function quote(): void
    {
        $symbol=$_GET['symbol']??'';if(!is_string($symbol)||preg_match('/^[A-Za-z0-9]{2,16}$/D',$symbol)!==1){$this->error(400,'invalid_symbol','Provide a valid instrument symbol.');return;}
        if(!$this->allowRequests('quotes',80))return;
        try{$provider=ProviderFactory::marketData();$quotes=$provider->quotes([strtoupper($symbol)]);if(!$quotes){$this->error(404,'instrument_not_found','The requested enabled instrument was not found.');return;}$this->json(['success'=>true,'provider'=>$provider->name(),'is_demo'=>$provider->name()==='demo','timestamp'=>gmdate('c'),'quote'=>$quotes[0]]);}catch(ProviderException $e){error_log('Single quote provider failure category='.$e->category);$this->error($e->httpStatus,$e->category,$e->getMessage());}catch(Throwable $e){error_log('Single market quote API failed type='.get_class($e));$this->error(503,'market_data_unavailable','The selected market-data provider is temporarily unavailable.');}
    }
    public function candles(): void
    {
        $symbol=$_GET['symbol']??'';$tf=$_GET['timeframe']??'1m';$rawLimit=$_GET['limit']??'300';
        if(!is_string($symbol)||preg_match('/^[A-Za-z0-9]{2,16}$/D',$symbol)!==1){$this->error(400,'invalid_symbol','Provide a valid instrument symbol.');return;}
        if(!is_string($tf)||!isset(\App\Services\MarketData\MarketDataService::TIMEFRAMES[$tf])){$this->error(400,'invalid_timeframe','Choose a supported candle timeframe.');return;}
        $limit=filter_var($rawLimit,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000]]);if($limit===false){$this->error(400,'invalid_limit','Candle limit must be between 1 and 1000.');return;}
        if(!$this->allowRequests('candles',40))return;
        try{$provider=ProviderFactory::marketData();$candles=$provider->candles(strtoupper($symbol),$tf,(int)$limit);$this->json(['success'=>true,'provider'=>$provider->name(),'is_demo'=>$provider->name()==='demo','symbol'=>strtoupper($symbol),'timeframe'=>$tf,'timestamp'=>gmdate('c'),'candles'=>array_map(static function(array $c):array{$c['time']=strtotime((string)$c['open_time']);return $c;},$candles)]);}catch(ProviderException $e){error_log('Market candle provider failure category='.$e->category);$this->error($e->httpStatus,$e->category,$e->getMessage());}catch(RuntimeException $e){if($e->getMessage()==='Instrument not found.'){$this->error(404,'instrument_not_found','The requested enabled instrument was not found.');return;}$this->error(400,'invalid_candle_request','The candle request is invalid.');}catch(Throwable $e){error_log('Market candle API failed type='.get_class($e));$this->error(503,'market_data_unavailable','The selected market-data provider is temporarily unavailable.');}
    }
    private function json(array $data):void{header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function error(int $status,string $code,string $message):void{http_response_code($status);$provider=(string)(APP_CONFIG['MARKET_DATA_PROVIDER']??'demo');$this->json(['success'=>false,'error'=>['code'=>$code,'message'=>$message],'provider'=>$provider,'is_demo'=>$provider==='demo','timestamp'=>gmdate('c')]);}
    private function allowRequests(string $bucket,int $maximum):bool
    {
        $minute=intdiv(time(),60);$key='_market_api_rate_'.$bucket;$state=$_SESSION[$key]??null;
        if(!is_array($state)||($state['minute']??null)!==$minute)$state=['minute'=>$minute,'count'=>0];
        if((int)$state['count'] >= $maximum){header('Retry-After: '.(string)(60-(time()%60)));$this->error(429,'rate_limited','Too many market-data requests. Please retry shortly.');return false;}
        $state['count']=(int)$state['count']+1;$_SESSION[$key]=$state;return true;
    }
}
