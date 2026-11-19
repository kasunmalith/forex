<?php
declare(strict_types=1);

namespace App\Services\MarketData;

interface MarketDataProviderInterface
{
    public function name(): string;

    /** @return array{provider:string,configured:bool,connected:bool,environment:string,status:string,mapping_status:string} */
    public function status(): array;

    public function supportsSymbol(string $symbol): bool;

    public function isFresh(array $quote, int $maximumAgeSeconds): bool;

    /** @return list<array<string, mixed>> */
    public function quotes(?array $symbols = null): array;

    /** @return list<array<string, mixed>> */
    public function candles(string $symbol, string $timeframe, int $limit): array;
}
