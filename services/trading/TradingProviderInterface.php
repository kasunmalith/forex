<?php
declare(strict_types=1);

namespace App\Services\Trading;

interface TradingProviderInterface
{
    public function name(): string;

    /** @return array{provider:string,configured:bool,connected:bool,environment:string,status:string} */
    public function status(): array;

    /** @return array<string,mixed> */
    public function executeOrder(int $userId, array $request): array;
}
