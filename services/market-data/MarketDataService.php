<?php
declare(strict_types=1);

namespace App\Services\MarketData;

use App\Database;
use App\Services\DemoTradingSettings;
use PDO;
use RuntimeException;
use Throwable;

/** Server-side access point used by APIs and future trading services. */
final class MarketDataService
{
    public const TIMEFRAMES = ['1m'=>60, '5m'=>300, '15m'=>900, '30m'=>1800, '1H'=>3600, '4H'=>14400, '1D'=>86400];
    private const MAX_STEPS = 120;
    private const MAX_CANDLES = 1000;
    private const HISTORY_MINUTES = 500;

    private function __construct(private readonly PDO $pdo) {}

    public static function demo(): self { return new self(Database::connection()); }

    public function quotes(?array $symbols = null): array
    {
        if (!DemoTradingSettings::enabled('demo_market_data_enabled')) return $this->cachedQuotes($symbols, true);
        $sql = "SELECT i.id,i.symbol,i.display_name,i.base_currency,i.quote_currency,i.base_price,i.bid AS seed_bid,i.ask AS seed_ask,i.spread,i.precision_digits,i.tick_size,i.demo_volatility,c.name AS category_name FROM instruments i JOIN instrument_categories c ON c.id=i.category_id WHERE ((i.status='active' AND c.status='active' AND i.market_data_enabled=1) OR EXISTS (SELECT 1 FROM positions op WHERE op.instrument_id=i.id AND op.status='open'))";
        $params = [];
        if ($symbols !== null) {
            if ($symbols === []) return [];
            $marks = implode(',', array_fill(0, count($symbols), '?'));
            $sql .= " AND i.symbol IN ($marks)";
            $params = $symbols;
        }
        $sql .= ' ORDER BY i.display_order,i.id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $quotes = [];
        $failures = 0;
        foreach ($statement->fetchAll() as $instrument) {
            try {
                for ($attempt=0; ; $attempt++) {
                    try { $quotes[] = $this->advance($instrument); break; }
                    catch (Throwable $exception) {
                        $driverCode = $exception instanceof \PDOException ? (int)($exception->errorInfo[1] ?? 0) : 0;
                        $retryable = $exception instanceof \PDOException && (in_array($exception->getCode(), ['40001','HY000'], true) && in_array($driverCode, [1205,1213], true));
                        if (!$retryable || $attempt >= 2) throw $exception;
                        usleep(random_int(15000, 50000));
                    }
                }
            } catch (Throwable $exception) { $failures++; }
        }
        if ($failures > 0) error_log('Demo quote provider skipped ' . $failures . ' instrument update(s).');
        if ($failures > 0 && $quotes === []) throw new RuntimeException('Demo quote provider returned no data.');
        return $quotes;
    }

    public function candles(string $symbol, string $timeframe, int $limit): array
    {
        if (!isset(self::TIMEFRAMES[$timeframe]) || $limit < 1 || $limit > self::MAX_CANDLES) throw new RuntimeException('Invalid candle request.');
        $statement = $this->pdo->prepare("SELECT i.id,i.symbol,i.display_name,i.base_price,i.bid AS seed_bid,i.ask AS seed_ask,i.spread,i.precision_digits,i.tick_size,i.demo_volatility,c.name AS category_name FROM instruments i JOIN instrument_categories c ON c.id=i.category_id WHERE i.symbol=:symbol AND i.status='active' AND c.status='active' LIMIT 1");
        $statement->execute(['symbol'=>$symbol]);
        $instrument = $statement->fetch();
        if (!$instrument) throw new RuntimeException('Instrument not found.');
        if (DemoTradingSettings::enabled('demo_market_data_enabled') && (bool)$this->instrumentMarketDataEnabled((int)$instrument['id'])) {
            $this->ensureHistory($instrument);
            $this->quotes([$symbol]);
        }
        $query = $this->pdo->prepare('SELECT timeframe,open_time,open,high,low,close,tick_count,is_closed FROM market_candles WHERE instrument_id=:id AND timeframe=:tf ORDER BY open_time DESC LIMIT ' . (int)$limit);
        $query->execute(['id'=>$instrument['id'], 'tf'=>$timeframe]);
        return array_reverse($query->fetchAll());
    }

    /** Initialize the existing simulator state for a newly created or explicitly reset instrument. */
    public function initializeInstrumentState(int $instrumentId): void
    {
        $query=$this->pdo->prepare("SELECT i.id,i.symbol,i.display_name,i.base_price,i.bid AS seed_bid,i.ask AS seed_ask,i.spread,i.precision_digits,i.tick_size,i.demo_volatility,c.name AS category_name FROM instruments i JOIN instrument_categories c ON c.id=i.category_id WHERE i.id=? LIMIT 1");
        $query->execute([$instrumentId]);$instrument=$query->fetch();
        if(!$instrument)throw new RuntimeException('Instrument configuration is unavailable.');
        $this->initialize($instrument);
    }

    private function advance(array $instrument): array
    {
        $this->pdo->beginTransaction();
        try {
            $id = (int)$instrument['id'];
            $this->initialize($instrument);
            $stateQuery = $this->pdo->prepare('SELECT * FROM market_simulation_state WHERE instrument_id=? FOR UPDATE');
            $stateQuery->execute([$id]);
            $state = $stateQuery->fetch();
            if (!$state) throw new RuntimeException('Simulation state could not be initialized.');
            $now = time();
            $last = strtotime((string)$state['last_simulated_at']) ?: $now;
            $elapsed = max(0, min(3600, $now - $last));
            $mid = (float)$state['current_mid_price'];
            $previousMid = $mid;
            $precision = max(0, min(8, (int)$instrument['precision_digits']));
            $tick = max(10 ** (-$precision), (float)($instrument['tick_size'] ?? (10 ** (-$precision))));
            $seed = (int)$state['seed_value'];
            $vol = max(0.0000001, min(0.08, (float)$instrument['demo_volatility']));
            $class = strtolower((string)$instrument['category_name']);
            $classFactor = str_contains($class,'digital') ? 3.4 : (str_contains($class,'indice') ? 1.7 : (str_contains($class,'metal') ? 1.45 : (str_contains($class,'commod') ? 1.8 : 1.0)));
            $stepCount = min(self::MAX_STEPS, max(1, $elapsed));
            if ($elapsed > 0) {
                $perStep = max(1.0, $elapsed / $stepCount);
                for ($step=0; $step<$stepCount; $step++) {
                    $seed++;
                    $random = $this->noise($id, $seed);
                    $anchor = max(0.0000000001, (float)$state['seed_price']);
                    $meanReversion = (($anchor - $mid) / max($anchor, 0.0000001)) * 0.000008;
                    $move = $mid * (($random * $vol * 0.0009 * $classFactor * sqrt($perStep)) + $meanReversion + (sin($seed / 17) * 0.000004));
                    $bound = max($tick, $mid * min(0.004, $vol * 0.035 * sqrt($perStep)));
                    $mid = $this->roundToTick(max($anchor * 0.25, min($anchor * 4, $mid + max(-$bound, min($bound, $move)))), $tick, $precision);
                    $this->recordMinutePrice($id, $mid, $now - ($stepCount - $step - 1));
                }
            }
            $spread = max($tick, (float)$instrument['spread']); // Phase 02 spread is the absolute ask-minus-bid price difference.
            $bid = round($mid - ($spread / 2), $precision);
            $ask = round($mid + ($spread / 2), $precision);
            $day = date('Y-m-d', $now);
            if ((string)$state['session_day'] !== $day) {
                $this->pdo->prepare('UPDATE market_simulation_state SET previous_close=current_mid_price,session_open=current_mid_price,session_high=current_mid_price,session_low=current_mid_price,session_day=? WHERE instrument_id=?')->execute([$day,$id]);
                $state['previous_close'] = $state['current_mid_price'];
                $state['session_open'] = $state['current_mid_price'];
                $state['session_high'] = $state['current_mid_price'];
                $state['session_low'] = $state['current_mid_price'];
            }
            $high = max((float)$state['session_high'], $mid);
            $low = min((float)$state['session_low'], $mid);
            $prevClose = (float)$state['previous_close'];
            $change = $mid - $prevClose;
            $storedTimestamp = date('Y-m-d H:i:s', $now);
            $timestamp = date(DATE_ATOM, $now);
            $this->pdo->prepare('UPDATE market_simulation_state SET current_mid_price=?,previous_mid_price=?,session_high=?,session_low=?,volatility_factor=?,last_simulated_at=?,seed_value=?,updated_at=CURRENT_TIMESTAMP WHERE instrument_id=?')->execute([$mid,$previousMid,$high,$low,(float)$instrument['demo_volatility'],$timestamp,$seed,$id]);
            $quote = ['instrument_id'=>$id,'symbol'=>(string)$instrument['symbol'],'display_name'=>(string)$instrument['display_name'],'category'=>(string)$instrument['category_name'],'bid'=>$bid,'ask'=>$ask,'mid'=>$mid,'spread'=>$spread,'change'=>$change,'change_percent'=>$prevClose != 0 ? ($change/$prevClose)*100 : 0.0,'high'=>$high,'low'=>$low,'open'=>(float)$state['session_open'],'previous_close'=>$prevClose,'timestamp'=>$timestamp,'provider'=>'demo','is_demo'=>true,'precision'=>(int)$instrument['precision_digits']];
            $this->pdo->prepare('INSERT INTO market_quotes (instrument_id,bid,ask,mid_price,spread,change_value,change_percent,day_open,day_high,day_low,previous_close,quote_timestamp) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE bid=VALUES(bid),ask=VALUES(ask),mid_price=VALUES(mid_price),spread=VALUES(spread),change_value=VALUES(change_value),change_percent=VALUES(change_percent),day_open=VALUES(day_open),day_high=VALUES(day_high),day_low=VALUES(day_low),previous_close=VALUES(previous_close),quote_timestamp=VALUES(quote_timestamp)')->execute([$id,$bid,$ask,$mid,$spread,$change,$quote['change_percent'],$quote['open'],$high,$low,$prevClose,$storedTimestamp]);
            $this->updateHigherCandles($id, $mid, $now);
            $this->pdo->commit();
            foreach (['bid','ask','mid','spread','change','high','low','open','previous_close'] as $field) $quote[$field] = number_format((float)$quote[$field], 10, '.', '');
            $quote['change_percent'] = number_format((float)$quote['change_percent'], 8, '.', '');
            return $quote;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function initialize(array $instrument): void
    {
        $mid = round((float)$instrument['base_price'], max(0,min(8,(int)$instrument['precision_digits'])));
        $id=(int)$instrument['id']; $now=date('Y-m-d H:i:s'); $day=date('Y-m-d');
        $this->pdo->prepare('INSERT IGNORE INTO market_simulation_state (instrument_id,seed_price,current_mid_price,previous_mid_price,session_open,session_high,session_low,previous_close,volatility_factor,trend_bias,last_simulated_at,session_day,seed_value) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,$mid,$mid,$mid,$mid,$mid,$mid,$mid,(float)$instrument['demo_volatility'],0,$now,$day,abs(crc32((string)$instrument['symbol']))]);
    }

    private function instrumentMarketDataEnabled(int $instrumentId): bool
    {
        $query=$this->pdo->prepare('SELECT market_data_enabled FROM instruments WHERE id=?');$query->execute([$instrumentId]);
        return (bool)$query->fetchColumn();
    }

    private function cachedQuotes(?array $symbols,bool $paused): array
    {
        $sql="SELECT i.id AS instrument_id,i.symbol,i.display_name,c.name AS category_name,i.precision_digits,q.bid,q.ask,q.mid_price AS mid,q.spread,q.change_value AS `change`,q.change_percent,q.day_high AS high,q.day_low AS low,q.day_open AS `open`,q.previous_close,q.quote_timestamp FROM market_quotes q JOIN instruments i ON i.id=q.instrument_id JOIN instrument_categories c ON c.id=i.category_id WHERE ((i.status='active' AND c.status='active') OR EXISTS (SELECT 1 FROM positions p WHERE p.instrument_id=i.id AND p.status='open'))";
        $params=[];
        if($symbols!==null){if($symbols===[])return [];$marks=implode(',',array_fill(0,count($symbols),'?'));$sql.=" AND i.symbol IN ($marks)";$params=$symbols;}
        $sql.=' ORDER BY c.display_order,i.display_order,i.id';$query=$this->pdo->prepare($sql);$query->execute($params);$out=[];$now=time();
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){$timestamp=strtotime((string)$row['quote_timestamp'])?:$now;$out[]=['instrument_id'=>(int)$row['instrument_id'],'symbol'=>(string)$row['symbol'],'display_name'=>(string)$row['display_name'],'category'=>(string)$row['category_name'],'bid'=>(string)$row['bid'],'ask'=>(string)$row['ask'],'mid'=>(string)$row['mid'],'spread'=>(string)$row['spread'],'change'=>(string)$row['change'],'change_percent'=>(string)$row['change_percent'],'high'=>(string)$row['high'],'low'=>(string)$row['low'],'open'=>(string)$row['open'],'previous_close'=>(string)$row['previous_close'],'timestamp'=>date(DATE_ATOM,$timestamp),'provider'=>'demo','is_demo'=>true,'precision'=>(int)$row['precision_digits'],'market_data_paused'=>$paused,'quote_age_seconds'=>max(0,$now-$timestamp)];}
        return $out;
    }

    private function roundToTick(float $price,float $tick,int $precision):float
    {
        if(!is_finite($tick)||$tick<=0)$tick=10**(-$precision);
        return round(round($price/$tick)*$tick,$precision);
    }

    private function ensureHistory(array $instrument): void
    {
        $id=(int)$instrument['id'];
        $check=$this->pdo->prepare("SELECT COUNT(*) FROM market_candles WHERE instrument_id=? AND timeframe='1m'"); $check->execute([$id]);
        if ((int)$check->fetchColumn() >= 100) return;
        $this->pdo->beginTransaction();
        try {
            $this->initialize($instrument);
            $lock=$this->pdo->prepare("SELECT instrument_id,seed_price FROM market_simulation_state WHERE instrument_id=? FOR UPDATE"); $lock->execute([$id]); $simulation=$lock->fetch();
            $again=$this->pdo->prepare("SELECT COUNT(*) FROM market_candles WHERE instrument_id=? AND timeframe='1m'"); $again->execute([$id]);
            if ((int)$again->fetchColumn() < 100) {
                $precision=max(0,min(8,(int)$instrument['precision_digits'])); $tick=max(10**(-$precision),(float)($instrument['tick_size']??(10**(-$precision)))); $price=$this->roundToTick((float)($simulation['seed_price']??$instrument['base_price']),$tick,$precision); $seed=abs(crc32('history:'.$instrument['symbol'])); $start=intdiv(time()-self::HISTORY_MINUTES*60,60)*60; $base=[];
                $category=strtolower((string)$instrument['category_name']); $classFactor=str_contains($category,'digital')?3.4:(str_contains($category,'indice')?1.7:(str_contains($category,'metal')?1.45:(str_contains($category,'commod')?1.8:1.0))); $vol=max(0.0000001,(float)$instrument['demo_volatility']);
                for($i=0;$i<self::HISTORY_MINUTES;$i++){ $open=$price; $seed++; $trend=sin($i/31)*0.2; $delta=$price*($this->noise($id,$seed)+$trend)*$vol*0.012*$classFactor; $price=$this->roundToTick(max($price*0.25,$price+$delta),$tick,$precision); $high=$this->roundToTick(max($open,$price)+$tick,$tick,$precision); $low=$this->roundToTick(max($tick,min($open,$price)-$tick),$tick,$precision); $base[]=['t'=>$start+$i*60,'o'=>$open,'h'=>$high,'l'=>$low,'c'=>$price]; }
                $insert=$this->pdo->prepare('INSERT IGNORE INTO market_candles (instrument_id,timeframe,open_time,open,high,low,close,tick_count,is_closed) VALUES (?,?,?,?,?,?,?,?,1)');
                foreach(self::TIMEFRAMES as $tf=>$seconds){ $group=[]; foreach($base as $c){ $bucket=intdiv($c['t'],$seconds)*$seconds; if(!isset($group[$bucket])) $group[$bucket]=['o'=>$c['o'],'h'=>$c['h'],'l'=>$c['l'],'c'=>$c['c'],'n'=>0]; else {$group[$bucket]['h']=max($group[$bucket]['h'],$c['h']);$group[$bucket]['l']=min($group[$bucket]['l'],$c['l']);$group[$bucket]['c']=$c['c'];} $group[$bucket]['n']++; } foreach($group as $t=>$c) $insert->execute([$id,$tf,date('Y-m-d H:i:s',$t),$c['o'],$c['h'],$c['l'],$c['c'],$c['n']]); }
            }
            $this->pdo->commit();
        } catch(Throwable $exception){ if($this->pdo->inTransaction())$this->pdo->rollBack(); throw $exception; }
    }

    private function recordMinutePrice(int $id,float $mid,int $timestamp): void
    {
        $bucket=intdiv($timestamp,60)*60; $openTime=date('Y-m-d H:i:s',$bucket);
        $stmt=$this->pdo->prepare("SELECT open,high,low,close FROM market_candles WHERE instrument_id=? AND timeframe='1m' AND open_time=? FOR UPDATE"); $stmt->execute([$id,$openTime]); $c=$stmt->fetch();
        if(!$c){ $this->pdo->prepare("UPDATE market_candles SET is_closed=1 WHERE instrument_id=? AND timeframe='1m' AND is_closed=0")->execute([$id]); $this->pdo->prepare("INSERT IGNORE INTO market_candles (instrument_id,timeframe,open_time,open,high,low,close,tick_count,is_closed) VALUES (?,'1m',?,?,?,?,?,1,0)")->execute([$id,$openTime,$mid,$mid,$mid,$mid]); return; }
        $this->pdo->prepare("UPDATE market_candles SET high=?,low=?,close=?,tick_count=tick_count+1,is_closed=0 WHERE instrument_id=? AND timeframe='1m' AND open_time=?")->execute([max((float)$c['high'],$mid),min((float)$c['low'],$mid),$mid,$id,$openTime]);
    }

    private function updateHigherCandles(int $id,float $mid,int $now): void
    {
        foreach(self::TIMEFRAMES as $tf=>$seconds){ if($tf==='1m') continue; $bucket=intdiv($now,$seconds)*$seconds; $openTime=date('Y-m-d H:i:s',$bucket); $stmt=$this->pdo->prepare('SELECT open,high,low FROM market_candles WHERE instrument_id=? AND timeframe=? AND open_time=? FOR UPDATE'); $stmt->execute([$id,$tf,$openTime]); $row=$stmt->fetch(); if(!$row){ $this->pdo->prepare('UPDATE market_candles SET is_closed=1 WHERE instrument_id=? AND timeframe=? AND is_closed=0')->execute([$id,$tf]); $this->pdo->prepare('INSERT IGNORE INTO market_candles (instrument_id,timeframe,open_time,open,high,low,close,tick_count,is_closed) VALUES (?,?,?,?,?,?,?,1,0)')->execute([$id,$tf,$openTime,$mid,$mid,$mid,$mid]); } else $this->pdo->prepare('UPDATE market_candles SET high=?,low=?,close=?,tick_count=tick_count+1,is_closed=0 WHERE instrument_id=? AND timeframe=? AND open_time=?')->execute([max((float)$row['high'],$mid),min((float)$row['low'],$mid),$mid,$id,$tf,$openTime]); }
    }

    private function noise(int $id,int $seed): float
    {
        $raw=unpack('N',substr(hash('sha256',$id.':'.$seed,true),0,4))[1] ?? 0;
        return (($raw / 4294967295) * 2) - 1;
    }
}
