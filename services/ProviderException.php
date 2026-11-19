<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ProviderException extends RuntimeException
{
    public function __construct(public readonly string $category, string $safeMessage, public readonly int $httpStatus = 503)
    {
        parent::__construct($safeMessage);
    }

    public static function fromUpstream(string $code): self
    {
        $category = match (strtolower($code)) {
            'auth', 'unauthorized', 'invalid_token' => 'authentication',
            'mapping', 'symbol_not_found' => 'mapping',
            'rate_limit', 'too_many_requests' => 'rate_limit',
            'invalid_request', 'bad_request' => 'invalid_request',
            'execution_blocked' => 'execution_blocked',
            default => 'unavailable',
        };
        $message = match ($category) {
            'authentication' => 'The provider authorization is unavailable.',
            'mapping' => 'A required provider symbol mapping is unavailable.',
            'rate_limit' => 'The provider request limit was reached.',
            'invalid_request' => 'The provider rejected the request.',
            'execution_blocked' => 'External execution is disabled.',
            default => 'The provider is temporarily unavailable.',
        };
        return new self($category, $message, $category === 'invalid_request' ? 422 : 503);
    }
}
