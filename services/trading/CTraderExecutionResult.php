<?php
declare(strict_types=1);

namespace App\Services\Trading;

use InvalidArgumentException;

final class CTraderExecutionResult
{
    public static function normalize(array $data): array
    {
        $status = strtolower(trim((string)($data['status'] ?? '')));
        $price = $data['execution_price'] ?? null;
        $volume = $data['filled_volume'] ?? null;
        $time = strtotime((string)($data['timestamp'] ?? ''));
        if (!in_array($status, ['accepted','partially_filled','filled','rejected','cancelled'], true)
            || ($price !== null && (!is_numeric($price) || !is_finite((float)$price) || (float)$price <= 0 || preg_match('/^\d+(?:\.\d{1,10})?$/D', trim((string)$price)) !== 1))
            || ($volume !== null && (!is_numeric($volume) || !is_finite((float)$volume) || (float)$volume < 0 || preg_match('/^\d+(?:\.\d{1,10})?$/D', trim((string)$volume)) !== 1))
            || $time === false) {
            throw new InvalidArgumentException('The provider execution result is invalid.');
        }
        return [
            'provider_order_id'=>isset($data['provider_order_id']) ? substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$data['provider_order_id']), 0, 80) : null,
            'status'=>$status,
            'execution_price'=>$price === null ? null : trim((string)$price),
            'filled_volume'=>$volume === null ? null : trim((string)$volume),
            'timestamp'=>gmdate(DATE_ATOM, $time),
            'error'=>isset($data['error_code']) ? \App\Services\ProviderException::fromUpstream((string)$data['error_code'])->category : null,
        ];
    }
}
