<?php
declare(strict_types=1);

namespace App\Services\Trading;

final class DemoTradingProvider implements TradingProviderInterface
{
    public function name(): string { return 'demo'; }

    public function status(): array
    {
        return ['provider'=>'demo','configured'=>true,'connected'=>true,'environment'=>'demo','status'=>'active'];
    }

    public function executeOrder(int $userId, array $request): array
    {
        return DemoTradingService::demo()->executeOrder($userId, $request);
    }
}
