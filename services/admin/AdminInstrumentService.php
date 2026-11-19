<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;
use App\Services\MarketData\MarketDataService;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class AdminInstrumentService
{
    private const SORTS=['symbol'=>'i.symbol','name'=>'i.display_name','category'=>'c.name','spread'=>'i.spread','margin'=>'i.margin_rate','updated'=>'i.updated_at'];

    public function listInstruments(array $input):array
    {
        $f=$this->filters($input);$where=[];$params=[];
        if($f['search']!==''){$where[]="(i.symbol LIKE :q0 OR i.display_name LIKE :q1 OR c.name LIKE :q2)";$q='%'.$this->escapeLike($f['search']).'%';$params=['q0'=>$q,'q1'=>$q,'q2'=>$q];}
        if($f['category']!==''){$where[]='c.slug=:category';$params['category']=$f['category'];}
        if($f['status']!=='all'){$where[]='i.status=:status';$params['status']=$f['status'];}
        if($f['trading']!=='all'){$where[]='i.trading_enabled=:trading';$params['trading']=$f['trading']==='enabled'?1:0;}
        if($f['market_data']!=='all'){$where[]='i.market_data_enabled=:market_data';$params['market_data']=$f['market_data']==='enabled'?1:0;}
        $from=' FROM instruments i JOIN instrument_categories c ON c.id=i.category_id LEFT JOIN market_quotes q ON q.instrument_id=i.id';
        $clause=$where?' WHERE '.implode(' AND ',$where):'';
        $count=Database::connection()->prepare('SELECT COUNT(*)'.$from.$clause);$count->execute($params);$total=(int)$count->fetchColumn();
        $page=min($f['page'],max(1,(int)ceil($total/$f['per_page'])));$offset=($page-1)*$f['per_page'];$sort=self::SORTS[$f['sort']];
        $sql="SELECT i.id,i.symbol,i.display_name,i.status,i.trading_enabled,i.market_data_enabled,i.spread,i.precision_digits,i.contract_size,i.margin_rate,i.min_volume,i.max_volume,i.volume_step,i.updated_at,c.name category_name,c.slug category_slug,q.bid current_bid,q.ask current_ask,q.quote_timestamp,(SELECT COUNT(*) FROM positions op WHERE op.instrument_id=i.id AND op.status='open') open_positions".$from.$clause." ORDER BY $sort {$f['direction']},i.id DESC LIMIT {$f['per_page']} OFFSET $offset";
        $query=Database::connection()->prepare($sql);$query->execute($params);
        return ['instruments'=>$query->fetchAll(),'categories'=>$this->categories(),'filters'=>$f,'pagination'=>['current_page'=>$page,'per_page'=>$f['per_page'],'total_records'=>$total,'total_pages'=>max(1,(int)ceil($total/$f['per_page']))]];
    }

    public function getInstrument(int $id):?array
    {
        if($id<1)return null;$pdo=Database::connection();
        $q=$pdo->prepare('SELECT i.*,c.name category_name,c.slug category_slug,c.status category_status,q.bid current_bid,q.ask current_ask,q.spread current_spread,q.quote_timestamp,s.current_mid_price,s.session_open,s.session_high,s.session_low,s.previous_close,s.last_simulated_at,s.volatility_factor,s.seed_price FROM instruments i JOIN instrument_categories c ON c.id=i.category_id LEFT JOIN market_quotes q ON q.instrument_id=i.id LEFT JOIN market_simulation_state s ON s.instrument_id=i.id WHERE i.id=? LIMIT 1');$q->execute([$id]);$instrument=$q->fetch(PDO::FETCH_ASSOC);if(!$instrument)return null;
        $instrument['usage']=$this->usage($pdo,$id);
        $q=$pdo->prepare("SELECT l.action,l.description,l.created_at,a.name AS admin_name FROM admin_activity_logs l LEFT JOIN admins a ON a.id=l.admin_id WHERE l.entity_type='instrument' AND l.entity_id=? ORDER BY l.created_at DESC,l.id DESC LIMIT 30");$q->execute([$id]);$instrument['activity']=$q->fetchAll(PDO::FETCH_ASSOC);
        $instrument['quote_age_seconds']=$instrument['quote_timestamp']?max(0,time()-(strtotime((string)$instrument['quote_timestamp'])?:time())):null;
        $instrument['categories']=$this->categories();return $instrument;
    }

    public function createInstrument(int $adminId,array $input):int
    {
        $data=$this->validated($input,true);$pdo=Database::connection();$pdo->beginTransaction();
        try{$category=$this->activeCategory($pdo,$data['category_id']);$this->assertSymbolAvailable($pdo,$data['symbol']);
            $half=(float)$data['spread']/2;$seed=(float)$data['demo_seed_price'];$precision=$data['precision_digits'];$bid=number_format(round($seed-$half,$precision),10,'.','');$ask=number_format(round($seed+$half,$precision),10,'.','');
            if((float)$bid<=0||$ask<$bid)throw new RuntimeException('Seed price and spread do not produce a valid Demo quote.');
            $sql='INSERT INTO instruments (category_id,symbol,display_name,base_currency,quote_currency,profit_currency,base_price,bid,ask,spread,precision_digits,tick_size,pip_size,contract_size,margin_rate,demo_volatility,min_volume,max_volume,volume_step,status,trading_enabled,market_data_enabled,display_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $pdo->prepare($sql)->execute([$category['id'],$data['symbol'],$data['display_name'],$data['base_currency']?:null,$data['quote_currency'],$data['profit_currency'],$data['demo_seed_price'],$bid,$ask,$data['spread'],$precision,$data['tick_size'],$data['pip_size']?:null,$data['contract_size'],$data['margin_rate'],$data['demo_volatility'],$data['min_volume'],$data['max_volume'],$data['volume_step'],$data['status'],$data['trading_enabled'],$data['market_data_enabled'],$data['display_order']]);
            $id=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO instrument_provider_mappings(instrument_id,provider_code,provider_symbol,is_active) VALUES(?,'demo',?,1)")->execute([$id,$data['symbol']]);
            MarketDataService::demo()->initializeInstrumentState($id);
            AdminActivityLogger::logOrFail($adminId,'instrument_created','instrument',$id,json_encode(['symbol'=>$data['symbol'],'fields'=>array_keys($data)],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
            $pdo->commit();return $id;
        }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((int)($e->errorInfo[1]??0)===1062)throw new RuntimeException('That instrument symbol is already in use.',409);throw $e;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function updateInstrument(int $id,int $adminId,array $input):array
    {
        $data=$this->validated($input,false);$pdo=Database::connection();$pdo->beginTransaction();
        try{$q=$pdo->prepare('SELECT * FROM instruments WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new RuntimeException('Instrument not found.',404);
            if((int)($input['config_version']??0)!==(int)$old['config_version'])throw new RuntimeException('This instrument was updated by another administrator. Reload before saving.',409);
            $category=$this->activeCategory($pdo,$data['category_id']);$usage=$this->usage($pdo,$id);
            $structural=['symbol','base_currency','quote_currency','precision_digits','tick_size','pip_size'];foreach($structural as $field)if($this->different($old[$field]??null,$data[$field]??null)&&$usage['has_market_or_trade_history'])throw new RuntimeException('Structural fields are read-only after an instrument has market or trading history.',409);
            if($this->different($old['profit_currency'],$data['profit_currency'])&&($usage['open_positions']>0||$usage['trades']>0))throw new RuntimeException('Profit currency cannot change while open positions or historical trades depend on it.',409);
            if($this->different($old['contract_size'],$data['contract_size'])&&$usage['open_positions']>0)throw new RuntimeException('Contract size cannot change while open positions use this instrument.',409);
            $newTrading=(int)$data['trading_enabled'];$newMarket=(int)$data['market_data_enabled'];$newStatus=$data['status'];$reason=$data['reason'];
            if((int)$old['trading_enabled']===1&&$newTrading===0&&$reason==='')throw new RuntimeException('Add a reason before disabling Demo Trading for this instrument.');
            if((int)$old['market_data_enabled']===1&&$newMarket===0){if($reason==='')throw new RuntimeException('Add a reason before disabling Demo Market Data.');if($usage['open_positions']>0)throw new RuntimeException('Demo market data cannot be disabled while open positions require valuation.',409);}
            if($old['status']==='active'&&$newStatus==='disabled'&&$reason==='')throw new RuntimeException('Add a reason before disabling this instrument.');
            if($this->different($old['spread'],$data['spread'])&&$usage['open_positions']>0&&($input['confirm_spread_change']??'')!=='1')throw new RuntimeException('Confirm the spread change after reviewing its effect on open Demo positions.');
            $next=['category_id'=>$category['id'],'symbol'=>$data['symbol'],'display_name'=>$data['display_name'],'base_currency'=>$data['base_currency']?:null,'quote_currency'=>$data['quote_currency'],'profit_currency'=>$data['profit_currency'],'base_price'=>$data['demo_seed_price'],'spread'=>$data['spread'],'precision_digits'=>$data['precision_digits'],'tick_size'=>$data['tick_size'],'pip_size'=>$data['pip_size']?:null,'contract_size'=>$data['contract_size'],'margin_rate'=>$data['margin_rate'],'demo_volatility'=>$data['demo_volatility'],'min_volume'=>$data['min_volume'],'max_volume'=>$data['max_volume'],'volume_step'=>$data['volume_step'],'status'=>$newStatus,'trading_enabled'=>$newTrading,'market_data_enabled'=>$newMarket,'display_order'=>$data['display_order']];
            $diff=[];foreach($next as $field=>$value)if($this->different($old[$field]??null,$value))$diff[$field]=['old'=>$old[$field]??null,'new'=>$value];
            if($diff===[])throw new RuntimeException('No instrument settings changed.',409);
            if(isset($diff['symbol'])){$this->assertSymbolAvailable($pdo,$data['symbol'],$id);$pdo->prepare("UPDATE instrument_provider_mappings SET provider_symbol=? WHERE instrument_id=? AND provider_code='demo'")->execute([$data['symbol'],$id]);}
            $sets=[];foreach(array_keys($next) as $field)$sets[]="`$field`=?";$sets[]='config_version=config_version+1';$sets[]='updated_at=NOW()';$sql='UPDATE instruments SET '.implode(',',$sets).' WHERE id=? AND config_version=?';$pdo->prepare($sql)->execute([...array_values($next),$id,(int)$old['config_version']]);if($pdo->rowCount()!==1)throw new RuntimeException('This instrument was updated by another administrator. Reload before saving.',409);
            if(isset($diff['demo_volatility']))$pdo->prepare('UPDATE market_simulation_state SET volatility_factor=? WHERE instrument_id=?')->execute([$data['demo_volatility'],$id]);
            $action=$newTrading===0&&$old['trading_enabled']?'instrument_trading_disabled':($newMarket===0&&$old['market_data_enabled']?'instrument_market_data_disabled':($newStatus==='disabled'&&$old['status']==='active'?'instrument_disabled':'instrument_updated'));
            AdminActivityLogger::logOrFail($adminId,$action,'instrument',$id,json_encode(['symbol'=>$data['symbol'],'changed'=>$diff,'reason'=>$reason],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
            $pdo->commit();return ['id'=>$id,'symbol'=>$data['symbol'],'changed'=>$diff,'config_version'=>(int)$old['config_version']+1];
        }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((int)($e->errorInfo[1]??0)===1062)throw new RuntimeException('That instrument symbol is already in use.',409);throw $e;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function categories():array
    {
        return Database::connection()->query("SELECT c.id,c.name,c.slug,c.description,c.status,c.display_order,c.updated_at,COUNT(i.id) instrument_count,COALESCE(SUM(i.status='active'),0) enabled_count,(SELECT COUNT(*) FROM positions p JOIN instruments pi ON pi.id=p.instrument_id WHERE pi.category_id=c.id AND p.status='open') open_positions FROM instrument_categories c LEFT JOIN instruments i ON i.category_id=c.id GROUP BY c.id ORDER BY c.display_order,c.id")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateCategory(int $categoryId,int $adminId,array $input):void
    {
        $name=trim((string)($input['name']??''));$order=filter_var($input['display_order']??null,FILTER_VALIDATE_INT);$status=(string)($input['status']??'active');if($categoryId<1||$name===''||mb_strlen($name)>80||$order===false||$order<0||$order>65535||!in_array($status,['active','disabled'],true))throw new RuntimeException('Enter a valid category name, display order, and status.');
        $pdo=Database::connection();$pdo->beginTransaction();try{$q=$pdo->prepare('SELECT * FROM instrument_categories WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$categoryId]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new RuntimeException('Category not found.',404);$open=(int)$pdo->query("SELECT COUNT(*) FROM positions p JOIN instruments i ON i.id=p.instrument_id WHERE i.category_id=".(int)$categoryId." AND p.status='open'")->fetchColumn();if($old['status']==='active'&&$status==='disabled'&&$open>0)throw new RuntimeException('This category cannot be disabled while open Demo positions use its instruments.',409);if($old['name']===$name&&(int)$old['display_order']===$order&&$old['status']===$status)throw new RuntimeException('No category settings changed.',409);$pdo->prepare('UPDATE instrument_categories SET name=?,display_order=?,status=?,updated_at=NOW() WHERE id=?')->execute([$name,$order,$status,$categoryId]);AdminActivityLogger::logOrFail($adminId,'instrument_category_updated','instrument_category',$categoryId,json_encode(['slug'=>$old['slug'],'old'=>['name'=>$old['name'],'display_order'=>(int)$old['display_order'],'status'=>$old['status']],'new'=>['name'=>$name,'display_order'=>$order,'status'=>$status]],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function resetMarketState(int $id,int $adminId,string $reason,string $confirmation):void
    {
        $reason=trim($reason);if($id<1||$reason===''||mb_strlen($reason)>500||$confirmation!=='RESET')throw new RuntimeException('Enter a reason and type RESET to confirm the Demo Market maintenance action.');
        $pdo=Database::connection();$pdo->beginTransaction();try{$q=$pdo->prepare('SELECT id,symbol,status,trading_enabled FROM instruments WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$id]);$instrument=$q->fetch(PDO::FETCH_ASSOC);if(!$instrument)throw new RuntimeException('Instrument not found.',404);$usage=$this->usage($pdo,$id);if($usage['open_positions']>0)throw new RuntimeException('Market data cannot be reset while open positions require valuation.',409);if((int)$instrument['trading_enabled']===1)throw new RuntimeException('Disable new Demo Trading for this instrument before resetting its market state.',409);$pdo->prepare('DELETE FROM market_candles WHERE instrument_id=?')->execute([$id]);$pdo->prepare('DELETE FROM market_quotes WHERE instrument_id=?')->execute([$id]);$pdo->prepare('DELETE FROM market_simulation_state WHERE instrument_id=?')->execute([$id]);MarketDataService::demo()->initializeInstrumentState($id);AdminActivityLogger::logOrFail($adminId,'instrument_market_state_reset','instrument',$id,json_encode(['symbol'=>$instrument['symbol'],'reason'=>$reason],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private function usage(PDO $pdo,int $id):array
    {
        $q=$pdo->prepare("SELECT (SELECT COUNT(*) FROM orders WHERE instrument_id=?) orders_count,(SELECT COUNT(*) FROM positions WHERE instrument_id=?) position_count,(SELECT COUNT(*) FROM positions WHERE instrument_id=? AND status='open') open_positions,(SELECT COUNT(*) FROM trades WHERE instrument_id=?) trades_count,(SELECT COUNT(*) FROM watchlists WHERE instrument_id=?) watchlists_count,(SELECT COUNT(*) FROM market_quotes WHERE instrument_id=?) quote_count,(SELECT COUNT(*) FROM market_candles WHERE instrument_id=?) candle_count,(SELECT COUNT(*) FROM market_simulation_state WHERE instrument_id=?) state_count");$q->execute(array_fill(0,8,$id));$r=$q->fetch(PDO::FETCH_ASSOC);$history=(int)$r['orders_count']>0||(int)$r['position_count']>0||(int)$r['trades_count']>0||(int)$r['quote_count']>0||(int)$r['candle_count']>0;return ['orders'=>(int)$r['orders_count'],'positions'=>(int)$r['position_count'],'open_positions'=>(int)$r['open_positions'],'trades'=>(int)$r['trades_count'],'watchlists'=>(int)$r['watchlists_count'],'quotes'=>(int)$r['quote_count'],'candles'=>(int)$r['candle_count'],'simulation_state'=>(int)$r['state_count']>0,'has_market_or_trade_history'=>$history];
    }

    private function validated(array $input,bool $creating):array
    {
        $symbol=strtoupper(trim((string)($input['symbol']??'')));if(preg_match('/^[A-Z0-9._-]{1,32}$/D',$symbol)!==1)throw new RuntimeException('Use 1–32 letters, numbers, dots, underscores, or hyphens for the symbol.');
        $name=trim((string)($input['display_name']??''));if($name===''||mb_strlen($name)>120||preg_match('/[\x00-\x1F\x7F]/',$name))throw new RuntimeException('Enter an instrument name of 1–120 characters.');
        $category=filter_var($input['category_id']??null,FILTER_VALIDATE_INT);$precision=filter_var($input['precision_digits']??null,FILTER_VALIDATE_INT);if($category===false||$category<1||$precision===false||$precision<0||$precision>8)throw new RuntimeException('Choose a category and precision between 0 and 8 digits.');
        $base=strtoupper(trim((string)($input['base_currency']??'')));$quote=strtoupper(trim((string)($input['quote_currency']??'')));$profit=strtoupper(trim((string)($input['profit_currency']??'')));if(($base!==''&&preg_match('/^[A-Z0-9]{2,10}$/D',$base)!==1)||preg_match('/^[A-Z]{3}$/D',$quote)!==1||preg_match('/^[A-Z]{3}$/D',$profit)!==1)throw new RuntimeException('Use three-letter currency codes for quote and profit currency; base currency may be blank for non-Forex instruments.');
        $seed=$this->decimal($input['demo_seed_price']??null,10,12,'Demo Seed Price');$spread=$this->decimal($input['spread']??null,10,12,'Spread');$tick=$this->decimal($input['tick_size']??null,10,10,'Tick Size');$pip=trim((string)($input['pip_size']??''));$pip=$pip===''?'':$this->decimal($pip,10,10,'Pip Size');$contract=$this->decimal($input['contract_size']??null,4,12,'Contract Size');$margin=$this->decimal($input['margin_rate']??null,8,2,'Margin Rate');$volatility=$this->decimal($input['demo_volatility']??null,8,2,'Demo Volatility');$minimum=$this->decimal($input['min_volume']??null,8,6,'Minimum Volume');$maximum=$this->decimal($input['max_volume']??null,8,6,'Maximum Volume');$step=$this->decimal($input['volume_step']??null,8,6,'Volume Step');
        if((float)$seed<=0||(float)$tick<=0||((float)$pip<=0&&$pip!=='')||(float)$contract<=0||(float)$margin<=0||(float)$margin>1||(float)$volatility<=0||(float)$volatility>0.08||(float)$minimum<=0||(float)$maximum<(float)$minimum||(float)$maximum>10000||(float)$step<=0)throw new RuntimeException('Price, tick, contract, margin, volatility, or volume settings are outside the supported positive ranges.');
        if($this->scale($seed)>$precision||$this->scale($tick)>$precision||($pip!==''&&$this->scale($pip)>$precision)||$this->scale($spread)>10)throw new RuntimeException('Seed price, tick size, pip size, and spread must fit the supported price precision.');
        if((float)$spread<0||(float)$spread>1000000000||(float)$seed<=(float)$spread/2)throw new RuntimeException('Spread must be non-negative and less than twice the positive seed price.');
        if(!$this->volumeAligned($maximum,$minimum,$step))throw new RuntimeException('Maximum Volume must align with the configured minimum and volume step.');
        $status=(string)($input['status']??'active');if(!in_array($status,['active','disabled'],true))throw new RuntimeException('Choose Enabled or Disabled for Instrument Status.');
        $displayOrder=filter_var($input['display_order']??0,FILTER_VALIDATE_INT);if($displayOrder===false||$displayOrder<0||$displayOrder>65535)throw new RuntimeException('Display order must be between 0 and 65535.');
        return ['symbol'=>$symbol,'display_name'=>$name,'category_id'=>$category,'base_currency'=>$base?:null,'quote_currency'=>$quote,'profit_currency'=>$profit,'demo_seed_price'=>$seed,'spread'=>$spread,'precision_digits'=>$precision,'tick_size'=>$tick,'pip_size'=>$pip?:null,'contract_size'=>$contract,'margin_rate'=>$margin,'demo_volatility'=>$volatility,'min_volume'=>$minimum,'max_volume'=>$maximum,'volume_step'=>$step,'status'=>$status,'trading_enabled'=>$this->boolean($input['trading_enabled']??'0'),'market_data_enabled'=>$this->boolean($input['market_data_enabled']??'0'),'display_order'=>$displayOrder,'reason'=>mb_substr(trim((string)($input['reason']??'')),0,500)];
    }

    private function decimal(mixed $value,int $scale,int $integerDigits,string $label):string
    {
        if(!is_string($value)&&!is_int($value))throw new RuntimeException($label.' must be a decimal number.');$raw=trim((string)$value);if(preg_match('/^\d{1,'.$integerDigits.'}(?:\.\d{1,'.$scale.'})?$/D',$raw)!==1)throw new RuntimeException($label.' must be a valid decimal number.');[$whole,$fraction]=array_pad(explode('.',$raw,2),2,'');return ltrim($whole,'0')===''?'0'.($fraction!==''?'.'.rtrim($fraction,'0'): ''):ltrim($whole,'0').($fraction!==''?'.'.rtrim($fraction,'0'): '');
    }
    private function scale(string $n):int{return strlen(explode('.',$n,2)[1]??'');}
    private function volumeAligned(string $max,string $min,string $step):bool
    {
        $units=static function(string $s):int{[$w,$f]=array_pad(explode('.',$s,2),2,'');return (int)$w*100000000+(int)str_pad($f,8,'0');};$a=$units($max)-$units($min);$b=$units($step);return $b>0&&$a%$b===0;
    }
    private function boolean(mixed $v):int{return in_array((string)$v,['1','true','on'],true)?1:0;}
    private function activeCategory(PDO $pdo,int $id):array{$q=$pdo->prepare("SELECT id,name,slug FROM instrument_categories WHERE id=? AND status='active' LIMIT 1");$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Choose an enabled instrument category.');return $r;}
    private function assertSymbolAvailable(PDO $pdo,string $symbol,int $exceptId=0):void{$q=$pdo->prepare('SELECT id FROM instruments WHERE UPPER(symbol)=? AND id<>? LIMIT 1');$q->execute([$symbol,$exceptId]);if($q->fetchColumn())throw new RuntimeException('That instrument symbol is already in use.',409);}
    private function different(mixed $a,mixed $b):bool{if($a===null||$b===null)return $a!==$b;if(is_numeric($a)&&is_numeric($b))return \App\Services\DecimalMath::compare((string)$a,(string)$b)!==0;return (string)$a!==(string)$b;}
    private function filters(array $input):array{$search=isset($input['search'])&&is_string($input['search'])?mb_substr(trim($input['search']),0,100):'';$category=isset($input['category'])&&is_string($input['category'])&&preg_match('/^[a-z0-9-]{1,90}$/D',$input['category'])===1?$input['category']:'';$status=isset($input['status'])&&in_array($input['status'],['active','disabled'],true)?$input['status']:'all';$trading=isset($input['trading'])&&in_array($input['trading'],['enabled','disabled'],true)?$input['trading']:'all';$market=isset($input['market_data'])&&in_array($input['market_data'],['enabled','disabled'],true)?$input['market_data']:'all';$sort=isset($input['sort'])&&isset(self::SORTS[$input['sort']])?$input['sort']:'symbol';$direction=strtolower((string)($input['direction']??'asc'))==='desc'?'DESC':'ASC';$per=isset($input['per_page'])&&in_array((int)$input['per_page'],[25,50,100],true)?(int)$input['per_page']:25;$page=isset($input['page'])&&filter_var($input['page'],FILTER_VALIDATE_INT)!==false?max(1,min(100000,(int)$input['page'])):1;return compact('search','category','status','trading')+['market_data'=>$market,'sort'=>$sort,'direction'=>$direction,'per_page'=>$per,'page'=>$page];}
    private function escapeLike(string $v):string{return strtr($v,['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']);}
}
