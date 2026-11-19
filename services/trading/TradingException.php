<?php
declare(strict_types=1);

namespace App\Services\Trading;

use RuntimeException;

final class TradingException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}
