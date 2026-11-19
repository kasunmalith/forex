<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class AuthValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Please review the highlighted fields.');
    }
}
