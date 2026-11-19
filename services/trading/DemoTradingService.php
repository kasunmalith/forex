<?php
declare(strict_types=1);

namespace App\Services\Trading;

use App\Database;
use App\Services\ProviderFactory;
use App\Services\DemoTradingSettings;
use DateTimeImmutable;
use PDO;
use Throwable;

/** Demo market execution. All prices come from the configured server-side demo quote provider. */
final class DemoTradingService
{
    private const SIDES = ['buy', 'sell'];
    private const ORDER_TYPE = 'market';

    private function __construct(private readonly PDO $pdo) {}

    public static function demo(): self { return new self(Database::connection()); }

    /** @return array<string,mixed> */
    public function executeOrder(int $userId, array $request): array
    {
        $validated = $this->validateRequest($request);
        $account = $this->activeAccount($userId);
        if (!$account) throw new TradingException('An active demo account is required to place an order.', 403);

        // Fast idempotency path avoids advancing the simulated quote on a network retry.
        $existing = $this->findByToken($userId, (int) $account['id'], $validated['client_order_id']);
        if ($existing) return $this->formatExisting($existing, true);
        $settings = DemoTradingSettings::all($this->pdo);
        if (!$settings['demo_trading_enabled']) throw new TradingException('Demo Trading is temporarily disabled. Existing positions remain available for management and closure.', 403);
        if (!$settings['market_orders_enabled']) throw new TradingException('Demo market orders are temporarily disabled.', 403);
        if (!$settings['demo_market_data_enabled']) throw new TradingException('Demo Market Data is in maintenance. New demo orders are paused.', 503);
        $instrumentPre = $this->findEnabledInstrument($validated['symbol']);
        if (!$instrumentPre) throw new TradingException('Instrument is unavailable.', 422);
        $preMaxVolume = \App\Services\DecimalMath::compare((string)$instrumentPre['max_volume'], (string)$settings['max_order_volume']) <= 0 ? (string)$instrumentPre['max_volume'] : (string)$settings['max_order_volume'];
        if (!$this->validVolume($validated['volume'], (string)$instrumentPre['min_volume'], $preMaxVolume, (string)$instrumentPre['volume_step'])) throw new TradingException('Enter a valid volume within the demo instrument limits and step.', 422);
        $this->consumeRateLimit($userId, (int)$account['id']);

        try {
            $quotes = ProviderFactory::marketData()->quotes();
            $quotesBySymbol = [];
            foreach ($quotes as $item) if (isset($item['symbol'])) $quotesBySymbol[(string)$item['symbol']] = $item;
            $quote = $quotesBySymbol[$validated['symbol']] ?? null;
            if (!$this->usableQuote($quote, $validated['symbol'])) {
                $quotes = ProviderFactory::marketData()->quotes();
                foreach ($quotes as $item) if (isset($item['symbol'])) $quotesBySymbol[(string)$item['symbol']] = $item;
                $quote = $quotesBySymbol[$validated['symbol']] ?? null;
            }
            if (!$this->usableQuote($quote, $validated['symbol'])) {
                throw new TradingException('Demo market data is temporarily unavailable. Please try again.', 503);
            }
        } catch (TradingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            error_log('Demo order quote retrieval failed for user ' . $userId . ', symbol ' . $validated['symbol'] . '.');
            throw new TradingException('Demo market data is temporarily unavailable. Please try again.', 503);
        }

        $executionPrice = (string) $quote[$validated['side'] === 'buy' ? 'ask' : 'bid'];
        $filledAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $requestId = bin2hex(random_bytes(12));
        $orderReference = 'MF-D-ORD-' . strtoupper(bin2hex(random_bytes(6)));
        $positionReference = 'MF-D-POS-' . strtoupper(bin2hex(random_bytes(6)));

        $this->pdo->beginTransaction();
        try {
            $accountQuery = $this->pdo->prepare(
                "SELECT d.*,u.status AS user_status FROM demo_accounts d JOIN users u ON u.id=d.user_id WHERE d.user_id=? AND d.status='active' AND u.status='active' ORDER BY d.id LIMIT 1 FOR UPDATE"
            );
            $accountQuery->execute([$userId]);
            $account = $accountQuery->fetch();
            if (!$account) throw new TradingException('An active demo account is required to place an order.', 403);

            $accountId = (int) $account['id'];
            $duplicate = $this->findByToken($userId, $accountId, $validated['client_order_id']);
            if ($duplicate) {
                $this->pdo->commit();
                return $this->formatExisting($duplicate, true);
            }

            $settings = DemoTradingSettings::all($this->pdo, true);
            if (!$settings['demo_trading_enabled']) throw new TradingException('Demo Trading is temporarily disabled. Existing positions remain available for management and closure.', 403);
            if (!$settings['market_orders_enabled']) throw new TradingException('Demo market orders are temporarily disabled.', 403);
            if (!$settings['demo_market_data_enabled']) throw new TradingException('Demo Market Data is in maintenance. New demo orders are paused.', 503);

            $instrument = $this->findEnabledInstrument($validated['symbol'], true);
            if (!$instrument) throw new TradingException('Instrument is unavailable.', 422);
            $maxVolume = \App\Services\DecimalMath::compare((string)$instrument['max_volume'], (string)$settings['max_order_volume']) <= 0 ? (string)$instrument['max_volume'] : (string)$settings['max_order_volume'];
            if (!$this->validVolume($validated['volume'], (string)$instrument['min_volume'], $maxVolume, (string)$instrument['volume_step'])) throw new TradingException('Enter a valid volume within the demo instrument limits and step.', 422);
            $positionCount = $this->pdo->prepare("SELECT COUNT(*) FROM positions WHERE demo_account_id=? AND user_id=? AND status='open'");
            $positionCount->execute([$accountId, $userId]);
            if ((int)$positionCount->fetchColumn() >= $settings['max_open_positions_per_account']) throw new TradingException('This demo account has reached the maximum number of open positions.', 422);
            $precision = (int) $instrument['precision_digits'];
            $stopLoss = $this->normalizeProtection($validated['stop_loss'], $precision, 'Stop Loss');
            $takeProfit = $this->normalizeProtection($validated['take_profit'], $precision, 'Take Profit');
            if ($stopLoss !== null && !$this->validProtection($stopLoss, $executionPrice, $validated['side'], 'sl')) {
                throw new TradingException('Stop Loss must be below the execution price for a BUY or above it for a SELL.', 422);
            }
            if ($takeProfit !== null && !$this->validProtection($takeProfit, $executionPrice, $validated['side'], 'tp')) {
                throw new TradingException('Take Profit must be above the execution price for a BUY or below it for a SELL.', 422);
            }

            $requiredMargin = $this->validateCapacity($account, $instrument, $executionPrice, $validated['volume'], $quotesBySymbol);
            $orderInsert = $this->pdo->prepare(
                "INSERT INTO orders (user_id,demo_account_id,instrument_id,provider_order_id,client_order_id,order_type,direction,volume,execution_price,stop_loss,take_profit,status,opened_at,executed_at) VALUES (?,?,?,?,?,'market',?,?,?, ?,?,'executed',?,?)"
            );
            $orderInsert->execute([$userId,$accountId,(int)$instrument['id'],$orderReference,$validated['client_order_id'],$validated['side'],$validated['volume'],$executionPrice,$stopLoss,$takeProfit,$filledAt,$filledAt]);
            $orderId = (int) $this->pdo->lastInsertId();
            $positionInsert = $this->pdo->prepare(
                "INSERT INTO positions (user_id,demo_account_id,instrument_id,originating_order_id,provider_position_id,direction,volume,initial_volume,open_price,current_price,required_margin,stop_loss,take_profit,status,opened_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,?,'open',?)"
            );
            $positionInsert->execute([$userId,$accountId,(int)$instrument['id'],$orderId,$positionReference,$validated['side'],$validated['volume'],$validated['volume'],$executionPrice,null,$requiredMargin,$stopLoss,$takeProfit,$filledAt]);
            $positionId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            try { \App\Services\NotificationService::demo()->createOnceForEvent($userId,'trade_executed','Demo Order Executed',$instrument['symbol'].' '.strtoupper($validated['side']).' '.(string)$validated['volume'].' lots opened.','order',$orderReference,'client/trade'); }
            catch (Throwable $notificationError) { error_log('Demo trade notification failed for order '.$orderReference.': '.$notificationError->getMessage()); }

            error_log('Demo market order executed request=' . $requestId . ' user=' . $userId . ' account=' . $accountId . ' order=' . $orderId . ' position=' . $positionId . ' symbol=' . $instrument['symbol'] . ' side=' . $validated['side'] . ' volume=' . $validated['volume'] . ' price=' . $executionPrice);
            return [
                'success'=>true,
                'duplicate'=>false,
                'message'=>'Demo market order executed.',
                'order'=>['id'=>$orderId,'reference'=>$orderReference,'symbol'=>$instrument['symbol'],'display_name'=>$instrument['display_name'],'side'=>$validated['side'],'type'=>'market','volume'=>$validated['volume'],'execution_price'=>$executionPrice,'status'=>'filled','filled_at'=>$filledAt,'stop_loss'=>$stopLoss,'take_profit'=>$takeProfit],
                'position'=>['id'=>$positionId,'reference'=>$positionReference,'status'=>'open'],
                'execution_quote'=>['bid'=>(string)$quote['bid'],'ask'=>(string)$quote['ask'],'timestamp'=>(string)$quote['timestamp']],
                'required_margin'=>$requiredMargin,
                'message'=>'Demo market order executed.',
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof TradingException) throw $exception;
            $driverCode = $exception instanceof \PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
            if ($driverCode === 1062) {
                $duplicate = $this->findByToken($userId, (int) $account['id'], $validated['client_order_id']);
                if ($duplicate) return $this->formatExisting($duplicate, true);
                throw new TradingException('This demo order request could not be accepted again.', 409);
            }
            error_log('Demo order transaction failed request=' . $requestId . ' user=' . $userId . ' stage=persistence');
            throw new TradingException('The demo order could not be completed. Please try again.', 500);
        }
    }

    private function validateRequest(array $request): array
    {
        $symbol = $request['symbol'] ?? null;
        $side = strtolower(trim((string) ($request['side'] ?? '')));
        $orderType = strtolower(trim((string) ($request['order_type'] ?? '')));
        $token = strtolower(trim((string) ($request['client_order_id'] ?? '')));
        $volume = $this->decimalInput($request['volume'] ?? null);
        $sl = $this->optionalDecimal($request['stop_loss'] ?? null);
        $tp = $this->optionalDecimal($request['take_profit'] ?? null);
        if (!is_string($symbol) || preg_match('/^[A-Z0-9._-]{1,32}$/D', strtoupper(trim($symbol))) !== 1) throw new TradingException('Choose a valid demo instrument.', 422);
        if (!in_array($side, self::SIDES, true)) throw new TradingException('Choose BUY or SELL.', 422);
        if ($orderType !== self::ORDER_TYPE) throw new TradingException('Only demo market orders are available.', 422);
        if ($volume === null) throw new TradingException('Enter a valid volume within the demo instrument limits and step.', 422);
        if ($sl === false || $tp === false) throw new TradingException('Enter valid positive Stop Loss and Take Profit prices.', 422);
        if (preg_match('/^[a-f0-9-]{32,64}$/D', $token) !== 1) throw new TradingException('Refresh the terminal and submit the order again.', 422);
        return ['symbol'=>strtoupper(trim($symbol)),'side'=>$side,'volume'=>$volume,'order_type'=>$orderType,'stop_loss'=>$sl,'take_profit'=>$tp,'client_order_id'=>$token];
    }

    private function activeAccount(int $userId): ?array
    {
        $query = $this->pdo->prepare("SELECT d.* FROM demo_accounts d JOIN users u ON u.id=d.user_id WHERE d.user_id=? AND d.status='active' AND u.status='active' ORDER BY d.id LIMIT 1");
        $query->execute([$userId]);
        $account = $query->fetch();
        return $account ?: null;
    }

    private function findEnabledInstrument(string $symbol, bool $lockForShare = false): ?array
    {
        $query=$this->pdo->prepare("SELECT i.id,i.symbol,i.display_name,i.precision_digits,i.contract_size,i.margin_rate,i.quote_currency,i.profit_currency,i.min_volume,i.max_volume,i.volume_step FROM instruments i JOIN instrument_categories c ON c.id=i.category_id JOIN instrument_provider_mappings m ON m.instrument_id=i.id AND m.provider_code='demo' AND m.is_active=1 WHERE i.symbol=? AND i.status='active' AND i.trading_enabled=1 AND i.market_data_enabled=1 AND c.status='active' LIMIT 1" . ($lockForShare ? ' FOR SHARE' : ''));
        $query->execute([$symbol]); $instrument=$query->fetch(); return $instrument ?: null;
    }

    private function findByToken(int $userId, int $accountId, string $token): ?array
    {
        $query = $this->pdo->prepare('SELECT o.id,o.provider_order_id,o.order_type,o.direction,o.volume,o.execution_price,o.stop_loss,o.take_profit,o.status,o.executed_at,i.symbol,i.display_name,p.id AS position_id,p.provider_position_id,p.status AS position_status FROM orders o JOIN instruments i ON i.id=o.instrument_id LEFT JOIN positions p ON p.originating_order_id=o.id AND p.demo_account_id=o.demo_account_id AND p.user_id=o.user_id WHERE o.user_id=? AND o.demo_account_id=? AND o.client_order_id=? LIMIT 1');
        $query->execute([$userId,$accountId,$token]);
        $row = $query->fetch();
        return $row ?: null;
    }

    private function formatExisting(array $row, bool $duplicate): array
    {
        return ['success'=>true,'duplicate'=>$duplicate,'message'=>'This demo order was already executed.','order'=>['id'=>(int)$row['id'],'reference'=>(string)$row['provider_order_id'],'symbol'=>(string)$row['symbol'],'display_name'=>(string)$row['display_name'],'side'=>(string)$row['direction'],'type'=>(string)$row['order_type'],'volume'=>(string)$row['volume'],'execution_price'=>(string)$row['execution_price'],'status'=>'filled','filled_at'=>(string)$row['executed_at'],'stop_loss'=>$row['stop_loss'],'take_profit'=>$row['take_profit']],'position'=>['id'=>(int)$row['position_id'],'reference'=>(string)$row['provider_position_id'],'status'=>(string)$row['position_status']],'message'=>'This demo order was already executed.'];
    }

    private function usableQuote(mixed $quote, string $symbol): bool
    {
        if (!is_array($quote) || ($quote['symbol'] ?? null) !== $symbol || !is_numeric($quote['bid'] ?? null) || !is_numeric($quote['ask'] ?? null)) return false;
        try { $age = time() - (new DateTimeImmutable((string) ($quote['timestamp'] ?? '')))->getTimestamp(); }
        catch (Throwable) { return false; }
        return $age >= -5 && $age <= max(5, (int) config('DEMO_TRADE_QUOTE_MAX_AGE_SECONDS')) && (float)$quote['bid'] > 0 && (float)$quote['ask'] >= (float)$quote['bid'];
    }

    private function validVolume(string $volume,string $minimum,string $maximum,string $step): bool
    {
        return DemoVolumeService::validForConfiguration($volume,$minimum,$maximum,$step);
    }

    private function decimalInput(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) return null;
        $value = trim((string)$value);
        return preg_match('/^\d{1,12}(?:\.\d{1,10})?$/D', $value) === 1 ? $value : null;
    }

    private function optionalDecimal(mixed $value): string|false|null
    {
        if ($value === null || $value === '') return null;
        $decimal = $this->decimalInput($value);
        return $decimal !== null && (float)$decimal > 0 ? $decimal : false;
    }

    private function normalizeProtection(?string $value, int $precision, string $label): ?string
    {
        if ($value === null) return null;
        if (strlen((string) (explode('.', $value)[1] ?? '')) > $precision && preg_match('/^\d+\.\d{' . ($precision + 1) . ',}$/D', $value) === 1) {
            $fraction = explode('.', $value, 2)[1];
            if (trim(substr($fraction, $precision), '0') !== '') throw new TradingException($label . ' must use the instrument price precision.', 422);
        }
        return number_format((float)$value, $precision, '.', '');
    }

    private function validProtection(string $protection, string $execution, string $side, string $kind): bool
    {
        $compare = $this->comparePositiveDecimals($protection, $execution);
        return $side === 'buy'
            ? ($kind === 'sl' ? $compare < 0 : $compare > 0)
            : ($kind === 'sl' ? $compare > 0 : $compare < 0);
    }

    private function comparePositiveDecimals(string $left, string $right): int
    {
        $parts = static function (string $value): string { [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, ''); return ltrim($whole . str_pad(substr($fraction,0,10),10,'0'), '0') ?: '0'; };
        $a=$parts($left); $b=$parts($right);
        return strlen($a) === strlen($b) ? strcmp($a,$b) : (strlen($a) <=> strlen($b));
    }

    private function validateCapacity(array $account, array $instrument, string $price, string $volume, array $quotes): string
    {
        $query=$this->pdo->prepare("SELECT p.id,p.instrument_id,p.direction,p.volume,p.open_price,p.stop_loss,p.take_profit,p.opened_at,p.required_margin,i.symbol,i.display_name,i.quote_currency,i.profit_currency,i.contract_size,i.precision_digits FROM positions p JOIN instruments i ON i.id=p.instrument_id WHERE p.demo_account_id=? AND p.user_id=? AND p.status='open'");
        $query->execute([(int)$account['id'],(int)$account['user_id']]); $positions=$query->fetchAll(); $floating='0'; $used='0';
        foreach ($positions as $position) {
            if ($position['required_margin']===null) throw new TradingException('Account margin data is being prepared. Please retry shortly.',503);
            $valuation=\App\Services\PositionValuationService::value($position,(string)$account['currency'],$quotes);
            if ($valuation['account_pl']===null) throw new TradingException('Current demo conversion data is unavailable. Please retry shortly.',503);
            $floating=\App\Services\DecimalMath::add($floating,(string)$valuation['account_pl']);
            $used=\App\Services\DecimalMath::add($used,(string)$position['required_margin']);
        }
        $equity=\App\Services\DecimalMath::add((string)$account['balance'],$floating);
        $free=\App\Services\DecimalMath::sub($equity,$used);
        try { $required=\App\Services\MarginService::required($instrument,$price,$volume,(string)$account['currency'],$quotes); }
        catch (Throwable) { throw new TradingException('Current demo conversion data is unavailable. Please retry shortly.',503); }
        if (\App\Services\DecimalMath::compare((string)$account['balance'],'0')<=0) throw new TradingException('This demo account has no available virtual funds.',422);
        if (\App\Services\DecimalMath::compare($free,'0')<0 || \App\Services\DecimalMath::compare($required,$free)>0) throw new TradingException('Insufficient free margin for this demo trade.',422);
        return $required;
    }

    private function consumeRateLimit(int $userId, int $accountId): void
    {
        $now = date('Y-m-d H:i:s');
        $limit=max(1,(int)config('DEMO_TRADE_RATE_LIMIT'));
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT IGNORE INTO trading_rate_limits (user_id,demo_account_id,window_started_at,request_count) VALUES (?,?,?,0)')->execute([$userId,$accountId,$now]);
            $query = $this->pdo->prepare('SELECT window_started_at,request_count FROM trading_rate_limits WHERE user_id=? AND demo_account_id=? FOR UPDATE');
            $query->execute([$userId,$accountId]); $row=$query->fetch();
            if (!$row) throw new TradingException('Order requests are temporarily unavailable. Please try again.', 503);
            $started = strtotime((string)$row['window_started_at']) ?: time();
            $window = max(10,(int)config('DEMO_TRADE_RATE_WINDOW_SECONDS'));
            $count = time()-$started >= $window ? 0 : (int)$row['request_count'];
            $next=min($limit+1,$count+1);
            $this->pdo->prepare('UPDATE trading_rate_limits SET window_started_at=?,request_count=? WHERE user_id=? AND demo_account_id=?')->execute([$count === 0 ? $now : $row['window_started_at'],$next,$userId,$accountId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof TradingException) throw $exception;
            throw $exception;
        }
        if ($next>$limit) throw new TradingException('Too many demo order requests. Please wait a moment and try again.', 429);
    }
}
