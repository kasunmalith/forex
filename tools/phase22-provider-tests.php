<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require dirname(__DIR__) . '/includes/bootstrap.php';

use App\Services\CTraderApiClient;
use App\Services\CTraderAuthService;
use App\Services\CTraderConfiguration;
use App\Services\ProviderException;
use App\Services\ProviderFactory;
use App\Services\MarketData\CTraderCandleNormalizer;
use App\Services\MarketData\CTraderQuoteNormalizer;
use App\Services\MarketData\CTraderSymbolMapper;
use App\Services\MarketData\CTraderTimeframeMapper;
use App\Services\Trading\CTraderExecutionGuard;
use App\Services\Trading\CTraderExecutionResult;
use App\Services\Trading\CTraderAccountMapping;
use App\Services\Trading\CTraderTradingProvider;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAILED: ' . $message);
};
$throws = static function (callable $callable, string $message) use ($assert): void {
    try { $callable(); } catch (Throwable) { $assert(true, $message); return; }
    $assert(false, $message);
};

$assert(ProviderFactory::marketData()->name() === 'demo', 'Demo is the default market provider');
$assert(ProviderFactory::trading()->name() === 'demo', 'Demo is the default trading provider');
$assert(ProviderFactory::marketData('ctrader')->status()['status'] === 'not_configured', 'cTrader market data is not configured');
$assert(ProviderFactory::trading('ctrader')->status()['execution_status'] === 'blocked', 'cTrader execution is blocked');
$throws(static fn() => ProviderFactory::marketData('ctrader')->quotes(['EURUSD']), 'selected cTrader provider never falls back to Demo quotes');
$throws(static fn() => ProviderFactory::marketData('arbitrary'), 'unknown market provider is rejected');
$throws(static fn() => ProviderFactory::trading('arbitrary'), 'unknown trading provider is rejected');
$assert(CTraderConfiguration::safeStatus()['connected'] === false, 'configuration alone never reports connected');
$assert(CTraderConfiguration::safeStatus()['configured'] === false, 'empty cTrader credentials are not configured');
$throws(static fn() => (new CTraderAuthService())->authorizationUrl(str_repeat('a', 64)), 'authorization URL requires configured credentials');
$assert(!CTraderConfiguration::validRedirectUri('https://multiforex.online/callback#fragment'), 'redirect fragments are rejected');
$assert(!CTraderConfiguration::validRedirectUri('https://user:pass@localhost/forex/callback'), 'redirect userinfo is rejected');
$assert(!CTraderConfiguration::validRedirectUri('https://example.com/callback'), 'redirect host must match configured site host');
$assert(CTraderConfiguration::validRedirectUri('http://localhost/forex/callback'), 'development localhost redirect is allowed');
$auth = new CTraderAuthService();
$state = $auth->issueState(17);
$assert($auth->consumeState($state, 17), 'OAuth state is bound to the initiating administrator');
$assert(!$auth->consumeState($state, 17), 'OAuth state is one-use');
$state = $auth->issueState(17);
$assert(!$auth->consumeState($state, 18), 'OAuth state rejects a different administrator');

$mapping = CTraderSymbolMapper::normalize(['provider_symbol'=>'EURUSD.pro','provider_symbol_id'=>'12345','is_active'=>1]);
$assert($mapping['provider_symbol_id'] === '12345' && $mapping['provider_symbol'] === 'EURUSD.pro', 'symbol ID and name are preserved');
$throws(static fn() => CTraderSymbolMapper::normalize(['provider_symbol'=>'bad/name','is_active'=>1]), 'malformed symbols are rejected');
$throws(static fn() => CTraderSymbolMapper::normalize(['provider_symbol'=>'EURUSD','is_active'=>0]), 'inactive symbols are rejected');
$assert(CTraderTimeframeMapper::toProvider('15m') === 'M15' && CTraderTimeframeMapper::toProvider('1D') === 'D1', 'timeframes use centralized cTrader values');
$throws(static fn() => CTraderTimeframeMapper::toProvider('2m'), 'unsupported timeframe rejected');

$quote = CTraderQuoteNormalizer::normalize(['symbol'=>'EURUSD','bid'=>'1.08320','ask'=>'1.08332','timestamp'=>'2026-10-15T10:00:00Z','precision'=>5]);
$assert($quote['provider'] === 'ctrader' && $quote['is_demo'] === true && $quote['mid'] === '1.08326', 'quote normalization produces safe demo DTO');
$throws(static fn() => CTraderQuoteNormalizer::normalize(['symbol'=>'EURUSD','bid'=>'2','ask'=>'1','timestamp'=>'bad']), 'invalid quote rejected');
$candle = CTraderCandleNormalizer::normalize(['open_time'=>'2026-10-15T10:00:00Z','open'=>'1.1','high'=>'1.3','low'=>'1.0','close'=>'1.2','volume'=>'42'], '1H');
$assert($candle['timeframe'] === '1H' && $candle['volume'] === '42', 'candle normalization uses platform fields');
$throws(static fn() => CTraderCandleNormalizer::normalize(['open_time'=>'2026-10-15','open'=>'1.2','high'=>'1.1','low'=>'1.0','close'=>'1.15'], '1H'), 'invalid OHLC bounds rejected');
$execution = CTraderExecutionResult::normalize(['provider_order_id'=>'ord-12','status'=>'filled','execution_price'=>'1.2','filled_volume'=>'0.4','timestamp'=>'2026-10-15T10:00:00Z']);
$assert($execution['provider_order_id'] === 'ord-12' && $execution['status'] === 'filled', 'execution result contract normalizes');
$throws(static fn() => CTraderExecutionResult::normalize(['status'=>'unknown','timestamp'=>'bad']), 'invalid execution result rejected');
$accountMapping = new CTraderAccountMapping(12, '987654321', 'demo', 'pending');
$assert($accountMapping->toArray()['status'] === 'pending' && $accountMapping->toArray()['provider'] === 'ctrader', 'account mapping model is explicit and pending by default');
$throws(static fn() => new CTraderAccountMapping(12, '987654321', 'live', 'automatic'), 'unsupported account mapping status is rejected');

$profile = (new CTraderApiClient())->connectionProfile();
$assert(in_array($profile['host'], ['demo.ctraderapi.com','live.ctraderapi.com'], true) && $profile['tls_required'] && $profile['verify_peer'], 'API client restricts endpoints and requires TLS verification');
$throws(static fn() => (new CTraderApiClient())->request(['url'=>'https://arbitrary.invalid']), 'transport remains inactive and caller URL is not used');
$throws(static fn() => CTraderExecutionGuard::assertExternalTradingAllowed(), 'REAL_TRADING_ENABLED false blocks external execution');
$throws(static fn() => (new CTraderTradingProvider())->executeOrder(1, []), 'cTrader order submission is blocked without any network call');
$safe = ProviderException::fromUpstream('client_secret=do-not-log');
$assert(!str_contains($safe->getMessage(), 'do-not-log'), 'provider errors do not expose upstream text');
$assert(!array_key_exists('client_secret', CTraderConfiguration::safeStatus()), 'status DTO contains no credentials');

echo 'Phase 22 provider checks passed: ' . $checks . PHP_EOL;
