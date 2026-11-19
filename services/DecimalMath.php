<?php
declare(strict_types=1);
namespace App\Services;

/** Decimal operations use BCMath when present and a bounded float fallback otherwise. */
final class DecimalMath
{
    private const SCALE=12;
    public static function add(string $a,string $b): string { return self::op('bcadd',$a,$b,'+'); }
    public static function sub(string $a,string $b): string { return self::op('bcsub',$a,$b,'-'); }
    public static function mul(string $a,string $b): string { return self::op('bcmul',$a,$b,'*'); }
    public static function div(string $a,string $b): string { return self::op('bcdiv',$a,$b,'/'); }
    public static function compare(string $a,string $b): int { return function_exists('bccomp') ? bccomp($a,$b,self::SCALE) : ((float)$a <=> (float)$b); }
    public static function mulDiv(string $a,string $b,string $c): string { return self::div(self::mul($a,$b),$c); }
    private static function op(string $fn,string $a,string $b,string $symbol): string
    {
        if (function_exists($fn)) return $fn($a,$b,self::SCALE);
        $x=(float)$a; $y=(float)$b;
        if (!is_finite($x)||!is_finite($y)||($symbol==='/'&&$y==0.0)) throw new \DomainException('Decimal calculation unavailable.');
        $result=match($symbol){'+'=>$x+$y,'-'=>$x-$y,'*'=>$x*$y,'/'=>$x/$y};
        if (!is_finite($result)) throw new \DomainException('Decimal calculation overflow.');
        return number_format($result,self::SCALE,'.','');
    }
}
