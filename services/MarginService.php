<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;

final class MarginService
{
    /** Notional is quote-currency units; margin_rate is a fraction (0.01 = 1%). */
    public static function required(array $instrument,string $price,string $volume,string $accountCurrency,array $quotes): string
    {
        $rate=(string)$instrument['margin_rate'];
        if (DecimalMath::compare($rate,'0')<=0||DecimalMath::compare($rate,'1')>0) throw new DomainException('Instrument margin configuration is invalid.');
        $notional=DecimalMath::mul(DecimalMath::mul($price,(string)$instrument['contract_size']),$volume);
        $notionalCurrency=(string)$instrument['quote_currency'];
        $inAccount=CurrencyConversionService::convert($notional,$notionalCurrency,$accountCurrency,$quotes);
        return DecimalMath::mul($inAccount,$rate);
    }
}
