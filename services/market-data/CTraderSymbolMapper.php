<?php
declare(strict_types=1);

namespace App\Services\MarketData;

use InvalidArgumentException;

final class CTraderSymbolMapper
{
    /** @return array{provider_symbol_id:?string,provider_symbol:string,status:string} */
    public static function normalize(array $mapping): array
    {
        $name = trim((string)($mapping['provider_symbol'] ?? ''));
        $id = isset($mapping['provider_symbol_id']) && trim((string)$mapping['provider_symbol_id']) !== ''
            ? trim((string)$mapping['provider_symbol_id']) : null;
        if (!empty($mapping['is_active']) === false || $name === '' || strlen($name) > 80
            || preg_match('/^[A-Za-z0-9._-]+$/D', $name) !== 1
            || ($id !== null && (strlen($id) > 80 || preg_match('/^[0-9]+$/D', $id) !== 1))) {
            throw new InvalidArgumentException('The cTrader symbol mapping is invalid or inactive.');
        }
        return ['provider_symbol_id'=>$id,'provider_symbol'=>$name,'status'=>'active'];
    }
}
