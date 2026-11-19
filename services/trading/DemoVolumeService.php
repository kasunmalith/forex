<?php
declare(strict_types=1);

namespace App\Services\Trading;

/** Decimal-safe demo volume validation shared by entry and position lifecycle. */
final class DemoVolumeService
{
    public static function canonical(string $volume): string
    {
        if (str_contains($volume,'.')) { $volume=rtrim(rtrim($volume,'0'),'.'); }
        return $volume===''?'0':$volume;
    }

    public static function valid(string $volume): bool
    {
        $volume=self::canonical($volume);
        return self::validForConfiguration($volume,(string)config('DEMO_TRADE_VOLUME_MIN'),(string)config('DEMO_TRADE_VOLUME_MAX'),(string)config('DEMO_TRADE_VOLUME_STEP'));
    }

    public static function validForConfiguration(string $volume,string $minimum,string $maximum,string $stepSize):bool
    {
        $volume=self::canonical($volume);
        if(!preg_match('/^\d{1,6}(?:\.\d{1,8})?$/D',$volume))return false;
        $n=self::units($volume);$min=self::units(self::canonical($minimum));$max=self::units(self::canonical($maximum));$step=self::units(self::canonical($stepSize));
        return $n>0&&$n>=$min&&$n<=$max&&$step>0&&($n-$min)%$step===0;
    }

    private static function units(string $value): int
    {
        if(!preg_match('/^\d{1,6}(?:\.\d{1,8})?$/D',$value))return -1;
        [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
        return (int)$whole*100000000+(int)str_pad($fraction,8,'0');
    }
}
