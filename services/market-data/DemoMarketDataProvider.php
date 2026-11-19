<?php
declare(strict_types=1);

namespace App\Services\MarketData;

final class DemoMarketDataProvider implements MarketDataProviderInterface
{
    public function name(): string { return 'demo'; }

    public function status(): array
    {
        return ['provider'=>'demo','configured'=>true,'connected'=>true,'environment'=>'demo','status'=>'active','mapping_status'=>'not_required'];
    }

    public function supportsSymbol(string $symbol): bool
    {
        return preg_match('/^[A-Z0-9]{2,16}$/D', strtoupper(trim($symbol))) === 1;
    }

    public function isFresh(array $quote, int $maximumAgeSeconds): bool
    {
        $time = strtotime((string)($quote['timestamp'] ?? ''));
        $age = $time === false ? null : time() - $time;
        return $age !== null && $maximumAgeSeconds >= 0 && $age >= 0 && $age <= $maximumAgeSeconds;
    }

    public function quotes(?array $symbols = null): array
    {
        return MarketDataService::demo()->quotes($symbols);
    }

    public function candles(string $symbol, string $timeframe, int $limit): array
    {
        return MarketDataService::demo()->candles($symbol, $timeframe, $limit);
    }
}
