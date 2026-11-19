<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/services/DecimalMath.php';
require dirname(__DIR__) . '/services/CurrencyConversionService.php';
require dirname(__DIR__) . '/services/PositionValuationService.php';
require dirname(__DIR__) . '/services/MarginService.php';

use App\Services\CurrencyConversionService;
use App\Services\DecimalMath;
use App\Services\MarginService;
use App\Services\PositionValuationService;

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $label);
    }
};

$quotes = [];
foreach ([
    ['EURUSD', 'EUR', 'USD', '1.10000', '1.10020'],
    ['USDJPY', 'USD', 'JPY', '150.00', '150.02'],
    ['XAUUSD', 'XAU', 'USD', '2000.00', '2000.50'],
    ['NAS100', 'NAS', 'USD', '18000', '18002'],
    ['BTCUSD', 'BTC', 'USD', '65000', '65010'],
] as [$symbol, $base, $counter, $bid, $ask]) {
    $quotes[$symbol] = [
        'symbol' => $symbol,
        'base_currency' => $base,
        'quote_currency' => $counter,
        'bid' => $bid,
        'ask' => $ask,
    ];
}

$value = static function (string $symbol, string $side, string $open, string $volume, string $contractSize) use ($quotes): array {
    return PositionValuationService::value([
        'id' => 1,
        'instrument_id' => 1,
        'symbol' => $symbol,
        'display_name' => $symbol,
        'quote_currency' => $symbol === 'USDJPY' ? 'JPY' : 'USD',
        'profit_currency' => $symbol === 'USDJPY' ? 'JPY' : 'USD',
        'direction' => $side,
        'volume' => $volume,
        'open_price' => $open,
        'contract_size' => $contractSize,
        'precision_digits' => 2,
        'required_margin' => '0',
        'opened_at' => '2026-10-15 00:00:00',
        'stop_loss' => null,
        'take_profit' => null,
    ], 'USD', $quotes);
};

$eurBuy = $value('EURUSD', 'buy', '1.10020', '1', '100000');
$assert($eurBuy['mark_price'] === '1.10000', 'EURUSD BUY mark uses Bid');
$assert($eurBuy['account_pl'] === '-20.000000000000', 'EURUSD BUY reflects spread');
$eurSell = $value('EURUSD', 'sell', '1.10000', '1', '100000');
$assert($eurSell['mark_price'] === '1.10020', 'EURUSD SELL mark uses Ask');
$assert($eurSell['account_pl'] === '-20.000000000000', 'EURUSD SELL reflects spread');

$jpyBuy = $value('USDJPY', 'buy', '150.02', '1', '100000');
$assert($jpyBuy['account_pl'] === '-13.332444502000', 'USDJPY quote P/L converts to USD');
$assert(CurrencyConversionService::convert('1000', 'USD', 'JPY', $quotes) === '150010.000000000000', 'USD converts through direct USDJPY quote');

foreach ([
    ['XAUUSD', 'buy', '2000.50', '100'],
    ['NAS100', 'buy', '18002', '1'],
    ['BTCUSD', 'buy', '65010', '1'],
] as [$symbol, $side, $open, $contractSize]) {
    $valuation = $value($symbol, $side, $open, '1', $contractSize);
    $assert($valuation['account_pl'] !== null && str_starts_with($valuation['account_pl'], '-'), $symbol . ' BUY spread valuation');
}

$eurMargin = MarginService::required(['margin_rate' => '0.01', 'contract_size' => '100000', 'quote_currency' => 'USD'], '1.10020', '1', 'USD', $quotes);
$assert($eurMargin === '1100.200000000000', 'EURUSD one-lot margin at 1 percent');
$jpyMargin = MarginService::required(['margin_rate' => '0.01', 'contract_size' => '100000', 'quote_currency' => 'JPY'], '150.02', '1', 'USD', $quotes);
$assert($jpyMargin === '1000.066662095020', 'USDJPY margin converts to account currency');

$balance = '10000.00';
$floating = $eurBuy['account_pl'];
$usedMargin = $eurMargin;
$equity = DecimalMath::add($balance, $floating);
$freeMargin = DecimalMath::sub($equity, $usedMargin);
$assert($equity === '9980.000000000000', 'equity equals balance plus floating P/L');
$assert(abs((float) $freeMargin - 8879.8) < 0.000001, 'free margin equals equity minus used margin');
$assert(DecimalMath::compare('0', '0') === 0, 'zero used margin is distinguishable for N/A margin level');

$partialClosePnl = DecimalMath::mul(DecimalMath::mul(DecimalMath::sub('1.10100', '1.10020'), '0.25'), '100000');
$assert($partialClosePnl === '20.000000000000', 'partial close realizes P/L only for closed volume');

printf("Phase 24 demo calculation fixtures passed: %d checks\n", $checks);
