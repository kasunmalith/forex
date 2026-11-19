<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Trading\DemoTradingProvider;
use App\Services\Trading\CTraderTradingProvider;
use App\Services\Trading\TradingProviderInterface;
use App\Services\MarketData\DemoMarketDataProvider;
use App\Services\MarketData\CTraderMarketDataProvider;
use App\Services\MarketData\MarketDataProviderInterface;
use RuntimeException;

final class ProviderFactory
{
    public static function trading(?string $selected = null): TradingProviderInterface
    {
        return match (strtolower($selected ?? (string) APP_CONFIG['TRADING_PROVIDER'])) {
            'demo' => new DemoTradingProvider(),
            'ctrader' => new CTraderTradingProvider(),
            default => throw new RuntimeException('Unsupported trading provider.'),
        };
    }

    public static function marketData(?string $selected = null): MarketDataProviderInterface
    {
        return match (strtolower($selected ?? (string) APP_CONFIG['MARKET_DATA_PROVIDER'])) {
            'demo' => new DemoMarketDataProvider(),
            'ctrader' => new CTraderMarketDataProvider(),
            default => throw new RuntimeException('Unsupported market data provider.'),
        };
    }
}
