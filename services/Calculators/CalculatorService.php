<?php
declare(strict_types=1);

namespace App\Services\Calculators;

use App\Database;
use DomainException;
use InvalidArgumentException;

final class CalculatorService
{
    public static function instruments(?string $category = null): array
    {
        $sql = "SELECT i.symbol, i.display_name, i.base_currency, i.quote_currency, i.bid, i.ask, i.spread, i.precision_digits, i.contract_size, i.margin_rate, c.name AS category_name, c.slug AS category_slug FROM instruments i JOIN instrument_categories c ON c.id = i.category_id WHERE i.status='active' AND c.status='active'";
        $statement = Database::connection()->prepare($sql . ($category !== null ? ' AND c.slug = :category' : '') . ' ORDER BY c.display_order, i.display_order, i.symbol');
        $statement->execute($category !== null ? ['category' => $category] : []);
        return $statement->fetchAll();
    }

    public static function currencies(): array
    {
        $currencies = [];
        foreach (self::instruments('forex') as $row) {
            foreach (['base_currency', 'quote_currency'] as $field) {
                $code = strtoupper((string) ($row[$field] ?? ''));
                if (preg_match('/^[A-Z]{3}$/', $code) === 1) { $currencies[$code] = $code; }
            }
        }
        sort($currencies);
        return array_values($currencies);
    }

    public static function forex(string $symbol, string $accountCurrency, float $lots, ?float $price = null): array
    {
        self::positive($lots, 'Trade size', 100);
        $instrument = self::instrument($symbol, 'forex');
        $accountCurrency = self::currency($accountCurrency);
        $price = $price ?? (float) $instrument['ask'];
        self::positive($price, 'Price', 1000000000000);
        $pipSize = strtoupper((string) $instrument['quote_currency']) === 'JPY' ? 0.01 : 0.0001;
        $units = $lots * (float) $instrument['contract_size'];
        $pipQuote = $units * $pipSize;
        $notionalQuote = $units * $price;
        $quote = strtoupper((string) $instrument['quote_currency']);
        $rate = self::rate($quote, $accountCurrency);
        return [
            'symbol' => $instrument['symbol'], 'account_currency' => $accountCurrency,
            'pip_size' => $pipSize, 'pip_value' => $pipQuote * $rate,
            'trade_value' => $notionalQuote * $rate, 'conversion_rate' => $rate,
            'quote_currency' => $quote, 'price' => $price,
            'explanation' => 'Pip value = lots × configured contract size × pip size, converted from the pair quote currency to your account currency using seeded demo FX midpoints.',
        ];
    }

    public static function margin(string $symbol, float $lots, ?float $price = null): array
    {
        self::positive($lots, 'Volume', 100);
        $instrument = self::instrument($symbol);
        $price = $price ?? (float) $instrument['ask'];
        self::positive($price, 'Demo price', 1000000000000);
        $rate = (float) $instrument['margin_rate'];
        if ($rate <= 0 || $rate > 1) { throw new DomainException('A valid margin setting is unavailable for this instrument.'); }
        $quote = strtoupper((string) $instrument['quote_currency']);
        $quoteNotional = $lots * (float) $instrument['contract_size'] * $price;
        $conversion = self::rate($quote, 'USD');
        return [
            'symbol' => $instrument['symbol'], 'margin' => $quoteNotional * $rate * $conversion,
            'currency' => 'USD', 'margin_rate' => $rate, 'price' => $price,
            'quote_notional' => $quoteNotional, 'quote_currency' => $quote,
            'explanation' => 'Estimated margin = lots × configured contract size × demo price × configured margin rate, converted to USD using seeded demo FX midpoints.',
        ];
    }

    public static function profitLoss(string $symbol, string $direction, float $lots, float $open, float $close): array
    {
        self::positive($lots, 'Volume', 100);
        self::positive($open, 'Open price', 1000000000000);
        self::positive($close, 'Close price', 1000000000000);
        $instrument = self::instrument($symbol);
        $direction = strtolower($direction);
        if (!in_array($direction, ['buy', 'sell'], true)) { throw new InvalidArgumentException('Choose Buy or Sell direction.'); }
        $difference = $close - $open;
        if ($direction === 'sell') { $difference *= -1; }
        $pnl = $difference * $lots * (float) $instrument['contract_size'];
        return [
            'symbol' => $instrument['symbol'], 'direction' => $direction,
            'profit_loss' => $pnl, 'currency' => strtoupper((string) $instrument['quote_currency']),
            'contract_size' => (float) $instrument['contract_size'],
            'explanation' => 'Estimated P/L = (close price − open price) × lots × configured contract size. Sell direction reverses the price difference; result is in the instrument quote currency.',
        ];
    }

    public static function convert(float $amount, string $from, string $to): array
    {
        if (!is_finite($amount) || $amount < 0 || $amount > 1000000000000) { throw new InvalidArgumentException('Enter an amount from 0 to 1,000,000,000,000.'); }
        $from = self::currency($from);
        $to = self::currency($to);
        $rate = self::rate($from, $to);
        return ['amount' => $amount, 'from' => $from, 'to' => $to, 'rate' => $rate, 'converted' => $amount * $rate,
            'explanation' => 'The conversion uses one or more midpoints from configured seeded demo forex quotes. It is educational and is not a live exchange rate.'];
    }

    private static function instrument(string $symbol, ?string $category = null): array
    {
        if (preg_match('/^[A-Z0-9]{2,32}$/', $symbol) !== 1) { throw new InvalidArgumentException('Choose an available instrument.'); }
        $sql = "SELECT i.symbol, i.display_name, i.base_currency, i.quote_currency, i.bid, i.ask, i.precision_digits, i.contract_size, i.margin_rate, c.slug AS category_slug FROM instruments i JOIN instrument_categories c ON c.id=i.category_id WHERE i.status='active' AND c.status='active' AND i.symbol=:symbol";
        if ($category !== null) { $sql .= ' AND c.slug=:category'; }
        $statement = Database::connection()->prepare($sql);
        $params = ['symbol' => $symbol];
        if ($category !== null) { $params['category'] = $category; }
        $statement->execute($params);
        $instrument = $statement->fetch();
        if (!$instrument) { throw new InvalidArgumentException('That instrument is not available in the demo catalogue.'); }
        return $instrument;
    }

    private static function rate(string $from, string $to): float
    {
        if ($from === $to) { return 1.0; }
        $graph = [];
        foreach (self::instruments('forex') as $row) {
            $base = strtoupper((string) $row['base_currency']);
            $quote = strtoupper((string) $row['quote_currency']);
            $mid = ((float) $row['bid'] + (float) $row['ask']) / 2;
            if ($mid <= 0 || $base === '' || $quote === '') { continue; }
            $graph[$base][$quote] = $mid;
            $graph[$quote][$base] = 1 / $mid;
        }
        $queue = [[$from, 1.0]];
        $seen = [$from => true];
        while ($queue !== []) {
            [$currency, $rate] = array_shift($queue);
            foreach ($graph[$currency] ?? [] as $next => $step) {
                if (isset($seen[$next])) { continue; }
                $combined = $rate * $step;
                if ($next === $to) { return $combined; }
                $seen[$next] = true;
                $queue[] = [$next, $combined];
            }
        }
        throw new DomainException('A seeded demo conversion path is unavailable for those currencies.');
    }

    private static function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || !in_array($currency, self::currencies(), true)) {
            throw new InvalidArgumentException('Choose a currency available in the seeded demo forex catalogue.');
        }
        return $currency;
    }

    private static function positive(float $value, string $label, float $maximum): void
    {
        if (!is_finite($value) || $value <= 0 || $value > $maximum) {
            throw new InvalidArgumentException($label . ' must be greater than zero and within the supported range.');
        }
    }
}
