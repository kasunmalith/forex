<?php
declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\CTraderConfiguration;
use App\Services\ProviderException;

/** Inactive official Open API adapter boundary. It never returns demo data. */
final class CTraderMarketDataProvider implements MarketDataProviderInterface
{
    public function name(): string { return 'ctrader'; }

    public function status(): array { return CTraderConfiguration::safeStatus(); }

    public function supportsSymbol(string $symbol): bool { return false; }

    public function isFresh(array $quote, int $maximumAgeSeconds): bool
    {
        $time = strtotime((string)($quote['timestamp'] ?? ''));
        $age = $time === false ? null : time() - $time;
        return $age !== null && $maximumAgeSeconds >= 0 && $age >= 0 && $age <= $maximumAgeSeconds;
    }

    public function quotes(?array $symbols = null): array
    {
        throw new ProviderException('unavailable', CTraderConfiguration::configured()
            ? 'The cTrader connection has not been verified.'
            : 'The cTrader provider is not configured.');
    }

    public function candles(string $symbol, string $timeframe, int $limit): array
    {
        CTraderTimeframeMapper::toProvider($timeframe);
        throw new ProviderException('unavailable', CTraderConfiguration::configured()
            ? 'The cTrader connection has not been verified.'
            : 'The cTrader provider is not configured.');
    }
}
