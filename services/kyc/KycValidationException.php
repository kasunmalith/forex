<?php
declare(strict_types=1);
namespace App\Services\Kyc;
use RuntimeException;
final class KycValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors){parent::__construct($errors[0]['message']??'Please check the verification details.');}
}
