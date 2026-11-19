<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;

final class PositionValuationService
{
    public static function value(array $position,string $accountCurrency,array $quotes): array
    {
        $quote=$quotes[(string)$position['symbol']]??null;
        $mark=null; $raw=null; $account=null; $state='degraded';
        if (is_array($quote) && is_numeric($quote['bid']??null) && is_numeric($quote['ask']??null)
            && DecimalMath::compare((string)$quote['bid'],'0')>0 && DecimalMath::compare((string)$quote['ask'],(string)$quote['bid'])>=0) {
            $mark=(string)$quote[$position['direction']==='buy'?'bid':'ask'];
            $direction=(string)$position['direction'];
            $difference=$direction==='buy'?DecimalMath::sub($mark,(string)$position['open_price']):DecimalMath::sub((string)$position['open_price'],$mark);
            $raw=DecimalMath::mul(DecimalMath::mul($difference,(string)$position['volume']),(string)$position['contract_size']);
            $profitCurrency=(string)($position['profit_currency']??$position['quote_currency']);
            try { $account=CurrencyConversionService::convert($raw,$profitCurrency,$accountCurrency,$quotes); $state='complete'; } catch (DomainException) {}
        }
        $profitCurrency=(string)($position['profit_currency']??$position['quote_currency']);
        return ['id'=>(int)$position['id'],'instrument_id'=>(int)$position['instrument_id'],'symbol'=>(string)$position['symbol'],'display_name'=>(string)$position['display_name'],'quote_currency'=>(string)$position['quote_currency'],'profit_currency'=>$profitCurrency,'direction'=>(string)$position['direction'],'volume'=>(string)$position['volume'],'open_price'=>(string)$position['open_price'],'mark_price'=>$mark,'current_price'=>$mark,'position_pl'=>$account,'raw_pl'=>$raw,'account_pl'=>$account,'pl_currency'=>strtoupper($accountCurrency),'raw_pl_currency'=>$profitCurrency,'stop_loss'=>$position['stop_loss'],'take_profit'=>$position['take_profit'],'required_margin'=>$position['required_margin']===null?null:(string)$position['required_margin'],'opened_at'=>(string)$position['opened_at'],'precision_digits'=>(int)$position['precision_digits'],'valuation_status'=>$state];
    }
}
