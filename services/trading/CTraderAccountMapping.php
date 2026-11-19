<?php
declare(strict_types=1);

namespace App\Services\Trading;

use InvalidArgumentException;

/** Validated future mapping value object; it does not persist or bind accounts. */
final class CTraderAccountMapping
{
    public function __construct(
        public readonly int $internalAccountId,
        public readonly string $providerAccountId,
        public readonly string $environment,
        public readonly string $status,
    ) {
        if ($internalAccountId < 1 || preg_match('/^[0-9]{1,24}$/D', $providerAccountId) !== 1
            || !in_array($environment, ['demo','live'], true)
            || !in_array($status, ['pending','active','disabled'], true)) {
            throw new InvalidArgumentException('The provider account mapping is invalid.');
        }
    }

    public function toArray(): array
    {
        return ['internal_account_id'=>$this->internalAccountId,'provider'=>'ctrader',
            'provider_account_id'=>$this->providerAccountId,'environment'=>$this->environment,'status'=>$this->status];
    }
}
