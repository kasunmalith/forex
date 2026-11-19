<?php
declare(strict_types=1);

namespace App\Services\Trading;

use App\Services\CTraderConfiguration;

/** Inactive adapter. No order submission transport is implemented in this phase. */
final class CTraderTradingProvider implements TradingProviderInterface
{
    public function name(): string { return 'ctrader'; }

    public function status(): array
    {
        return CTraderConfiguration::safeStatus() + ['execution_status'=>'blocked'];
    }

    public function executeOrder(int $userId, array $request): array
    {
        CTraderExecutionGuard::assertExternalTradingAllowed();
        throw new TradingException('cTrader order execution is not implemented; no order was sent.', 503);
    }
}
