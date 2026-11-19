<?php
declare(strict_types=1);

namespace App\Services\MarketData;

use InvalidArgumentException;

final class CTraderCandleNormalizer
{
    public static function normalize(array $payload, string $timeframe): array
    {
        CTraderTimeframeMapper::toProvider($timeframe);
        $timestamp = strtotime((string)($payload['open_time'] ?? ''));
        $values = [];
        foreach (['open','high','low','close'] as $field) {
            $value = $payload[$field] ?? null;
            if (!is_numeric($value) || !is_finite((float)$value) || (float)$value <= 0
                || preg_match('/^\d+(?:\.\d{1,10})?$/D', trim((string)$value)) !== 1) throw new InvalidArgumentException('The provider candle could not be normalized.');
            $values[$field] = trim((string)$value);
        }
        if ($timestamp === false || \App\Services\DecimalMath::compare($values['high'], $values['open']) < 0
            || \App\Services\DecimalMath::compare($values['high'], $values['close']) < 0
            || \App\Services\DecimalMath::compare($values['high'], $values['low']) < 0
            || \App\Services\DecimalMath::compare($values['low'], $values['open']) > 0
            || \App\Services\DecimalMath::compare($values['low'], $values['close']) > 0) {
            throw new InvalidArgumentException('The provider candle could not be normalized.');
        }
        $row = ['open_time'=>gmdate('Y-m-d H:i:s', $timestamp)] + $values + ['timeframe'=>$timeframe];
        if (isset($payload['volume'])) {
            if (!is_numeric($payload['volume']) || !is_finite((float)$payload['volume']) || (float)$payload['volume'] < 0
                || preg_match('/^\d+(?:\.\d{1,10})?$/D', trim((string)$payload['volume'])) !== 1) throw new InvalidArgumentException('The provider candle could not be normalized.');
            $row['volume'] = trim((string)$payload['volume']);
        }
        return $row;
    }
}
