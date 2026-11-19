<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;

final class CurrencyConversionService
{
    public static function rate(string $from,string $to,array $quotes): string
    {
        $from=strtoupper($from); $to=strtoupper($to);
        if ($from===$to) return '1';
        $edges=[];
        foreach ($quotes as $quote) {
            $symbol=(string)($quote['symbol']??'');
            $base=strtoupper((string)($quote['base_currency']??substr($symbol,0,3)));
            $counter=strtoupper((string)($quote['quote_currency']??substr($symbol,3,3)));
            if (strlen($base)!==3||strlen($counter)!==3||!isset($quote['bid'],$quote['ask'])) continue;
            $bid=(string)$quote['bid']; $ask=(string)$quote['ask'];
            if (DecimalMath::compare($bid,'0')<=0||DecimalMath::compare($ask,$bid)<0) continue;
            $mid=DecimalMath::div(DecimalMath::add($bid,$ask),'2');
            $edges[$base][$counter]=$mid; $edges[$counter][$base]=DecimalMath::div('1',$mid);
        }
        // Prefer a direct or inverse pair; otherwise bridge once through USD.
        if (isset($edges[$from][$to])) return $edges[$from][$to];
        if ($from!=='USD'&&$to!=='USD'&&isset($edges[$from]['USD'],$edges['USD'][$to])) return DecimalMath::mul($edges[$from]['USD'],$edges['USD'][$to]);
        throw new DomainException('A current demo FX conversion path is unavailable.');
    }
    public static function convert(string $amount,string $from,string $to,array $quotes): string { return DecimalMath::mul($amount,self::rate($from,$to,$quotes)); }
}
