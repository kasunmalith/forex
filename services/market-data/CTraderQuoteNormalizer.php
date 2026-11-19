<?php
declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\DecimalMath;
use InvalidArgumentException;

final class CTraderQuoteNormalizer
{
    public static function normalize(array $payload, string $environment = 'demo'): array
    {
        $symbol = strtoupper(trim((string)($payload['symbol'] ?? '')));
        $bid = $payload['bid'] ?? null;
        $ask = $payload['ask'] ?? null;
        $precision = filter_var($payload['precision'] ?? 5, FILTER_VALIDATE_INT);
        $timestamp = strtotime((string)($payload['timestamp'] ?? ''));
        if (preg_match('/^[A-Z0-9._-]{1,32}$/D', $symbol) !== 1 || !is_numeric($bid) || !is_numeric($ask)
            || !is_finite((float)$bid) || !is_finite((float)$ask) || (float)$bid <= 0
            || $precision === false || $precision < 0 || $precision > 8 || $timestamp === false) {
            throw new InvalidArgumentException('The provider quote could not be normalized.');
        }
        $bidText = self::decimal($bid);
        $askText = self::decimal($ask);
        if (DecimalMath::compare($askText, $bidText) < 0) throw new InvalidArgumentException('The provider quote could not be normalized.');
        $mid = self::trimDecimal(DecimalMath::div(DecimalMath::add($bidText, $askText), '2'));
        return [
            'symbol'=>$symbol, 'bid'=>$bidText, 'ask'=>$askText,
            'mid'=>$mid,
            'timestamp'=>gmdate(DATE_ATOM, $timestamp), 'provider'=>'ctrader',
            'environment'=>$environment === 'live' ? 'live' : 'demo',
            'is_demo'=>$environment !== 'live', 'precision'=>(int)$precision,
            'spread'=>self::trimDecimal(DecimalMath::sub($askText, $bidText)),
        ];
    }

    private static function decimal(mixed $value): string
    {
        $text = trim((string)$value);
        if (preg_match('/^\d+(?:\.\d{1,10})?$/D', $text) !== 1) throw new InvalidArgumentException('Invalid provider price.');
        return $text;
    }

    private static function trimDecimal(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}
