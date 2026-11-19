<?php
declare(strict_types=1);

namespace App\Services\MarketData;

use InvalidArgumentException;

final class CTraderTimeframeMapper
{
    private const MAP = [
        '1m'=>'M1', '5m'=>'M5', '15m'=>'M15', '30m'=>'M30',
        '1H'=>'H1', '4H'=>'H4', '1D'=>'D1',
    ];

    public static function toProvider(string $timeframe): string
    {
        return self::MAP[$timeframe] ?? throw new InvalidArgumentException('Unsupported candle timeframe.');
    }

    public static function all(): array { return self::MAP; }
}
