<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\MarketData\MarketDataService;
use PDO;
use RuntimeException;
use Throwable;

/** One authoritative snapshot for demo equity, floating P/L and margin. */
final class AccountMetricsService
{
    private function __construct(private readonly PDO $pdo) {}
    public static function demo(): self { return new self(Database::connection()); }

    public function snapshot(int $userId, ?int $accountId = null, ?array $quoteSnapshot = null): array
    {
        $sql = "SELECT d.* FROM demo_accounts d WHERE d.user_id=?" . ($accountId ? ' AND d.id=?' : '') . " ORDER BY FIELD(d.status,'active','suspended','disabled','closed'),d.created_at DESC,d.id DESC LIMIT 1";
        $q = $this->pdo->prepare($sql); $q->execute($accountId ? [$userId,$accountId] : [$userId]);
        $account = $q->fetch();
        if (!$account) throw new RuntimeException('Demo account is unavailable.');
        $rowsQ = $this->pdo->prepare("SELECT p.id,p.instrument_id,p.direction,p.volume,p.open_price,p.stop_loss,p.take_profit,p.opened_at,p.required_margin,i.symbol,i.display_name,i.base_currency,i.quote_currency,i.profit_currency,i.contract_size,i.precision_digits,i.margin_rate,c.slug AS category FROM positions p JOIN instruments i ON i.id=p.instrument_id JOIN instrument_categories c ON c.id=i.category_id WHERE p.user_id=? AND p.demo_account_id=? AND p.status='open' ORDER BY p.opened_at DESC,p.id DESC");
        $rowsQ->execute([$userId,(int)$account['id']]); $positions=$rowsQ->fetchAll();
        $all = $this->pdo->query("SELECT i.symbol FROM instruments i JOIN instrument_categories c ON c.id=i.category_id WHERE (i.status='active' AND c.status='active') OR EXISTS (SELECT 1 FROM positions op WHERE op.instrument_id=i.id AND op.status='open')")->fetchAll(PDO::FETCH_COLUMN);
        $quotes = [];
        if ($quoteSnapshot !== null) { foreach ($quoteSnapshot as $quote) if (is_array($quote) && isset($quote['symbol'])) $quotes[$quote['symbol']]=$quote; }
        else { try { foreach (MarketDataService::demo()->quotes(array_values(array_unique($all))) as $quote) if (is_array($quote) && isset($quote['symbol'])) $quotes[$quote['symbol']]=$quote; }
            catch (Throwable $e) { error_log('Account metrics market snapshot unavailable.'); } }
        $convertedPositions=[]; $floating='0'; $floatingComplete=true; $used='0';
        foreach ($positions as $position) {
            $value=PositionValuationService::value($position,$account['currency'],$quotes);
            $convertedPositions[]=$value;
            if ($value['account_pl'] === null) $floatingComplete=false; else $floating=DecimalMath::add($floating,$value['account_pl']);
            if ($position['required_margin'] === null) { $used=null; } elseif ($used !== null) $used=DecimalMath::add($used,(string)$position['required_margin']);
        }
        if ($used===null && $positions===[]) $used='0';
        $equity=$floatingComplete ? DecimalMath::add((string)$account['balance'],$floating) : null;
        $free=($equity!==null && $used!==null) ? DecimalMath::sub($equity,$used) : null;
        $level=($equity!==null && $used!==null && DecimalMath::compare($used,'0')>0) ? DecimalMath::mulDiv($equity,'100',$used) : null;
        $critical=(string)config('DEMO_MARGIN_CRITICAL_LEVEL','50'); $warning=(string)config('DEMO_MARGIN_WARNING_LEVEL','100');
        $marginState=$level===null?'not_applicable':(DecimalMath::compare($level,$critical)<=0?'critical':(DecimalMath::compare($level,$warning)<=0?'warning':'normal'));
        return ['account'=>['id'=>(int)$account['id'],'account_number'=>(string)$account['account_number'],'currency'=>(string)$account['currency'],'status'=>(string)$account['status']],
            'metrics'=>['balance'=>(string)$account['balance'],'floating_pl'=>$floatingComplete?$floating:null,'equity'=>$equity,'used_margin'=>$used,'free_margin'=>$free,'margin_level'=>$level,'margin_state'=>$marginState,'open_positions'=>count($positions),'currency'=>(string)$account['currency'],'valuation_status'=>($floatingComplete&&$used!==null)?'complete':'degraded','margin_warning_level'=>$warning,'margin_critical_level'=>$critical],
            'positions'=>$convertedPositions];
    }

    /** One-time additive migration helper; leaves rows degraded when conversion data is missing. */
    public function backfillMarginSnapshots(): int
    {
        $pending=$this->pdo->query("SELECT p.id,p.open_price,p.volume,i.quote_currency,i.profit_currency,i.contract_size,i.margin_rate,i.symbol,d.currency AS account_currency FROM positions p JOIN instruments i ON i.id=p.instrument_id JOIN demo_accounts d ON d.id=p.demo_account_id WHERE p.status='open' AND p.required_margin IS NULL")->fetchAll();
        if ($pending===[]) return 0;
        $quotes=[];
        try { foreach (MarketDataService::demo()->quotes() as $quote) if (isset($quote['symbol'])) $quotes[$quote['symbol']]=$quote; } catch (Throwable) { error_log('Phase 12 margin snapshot backfill quote retrieval was incomplete.'); }
        $update=$this->pdo->prepare('UPDATE positions SET required_margin=? WHERE id=? AND required_margin IS NULL'); $done=0;
        foreach ($pending as $row) {
            try { $amount=MarginService::required($row,(string)$row['open_price'],(string)$row['volume'],(string)$row['account_currency'],$quotes); $update->execute([$amount,(int)$row['id']]); $done+=$update->rowCount(); }
            catch (Throwable) { /* Keep NULL so account metrics and new orders report degraded capacity safely. */ }
        }
        return $done;
    }
}
