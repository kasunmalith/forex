<?php
declare(strict_types=1);

namespace App\Services\Trading;

use App\Services\ProviderException;

final class CTraderExecutionGuard
{
    public static function assertExternalTradingAllowed(): void
    {
        if (\config('REAL_TRADING_ENABLED', false) !== true) {
            throw new TradingException('External trading is disabled. No order was sent.', 503);
        }
    }
}
