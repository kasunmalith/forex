<?php
declare(strict_types=1);

namespace App\Models;

use Throwable;

final class MarketsModel
{
    private const CATEGORIES = [
        'forex' => ['name' => 'Forex', 'description' => 'Explore major, minor and cross currency pairs in a static demo catalogue.', 'icon' => 'FX', 'intro' => 'Forex pairs show one currency against another. Browse seeded bid and ask examples, then use the demo environment to become familiar with how a quote is presented.', 'groups' => ['major' => 'Major pairs', 'minor' => 'Minor pairs']],
        'metals' => ['name' => 'Metals', 'description' => 'Illustrative precious metal instruments, including gold and silver.', 'icon' => 'Au', 'intro' => 'Precious metals are commonly quoted against a currency. These seeded gold and silver values are examples for exploring the market interface.', 'groups' => []],
        'indices' => ['name' => 'Indices', 'description' => 'Selected global index examples grouped by region.', 'icon' => 'IX', 'intro' => 'An index represents a group of listed companies. The catalogue contains a small selection of regional index examples for demo browsing.', 'groups' => ['us' => 'United States', 'europe' => 'Europe', 'uk' => 'United Kingdom']],
        'commodities' => ['name' => 'Commodities', 'description' => 'Energy market examples with room for additional configured instruments.', 'icon' => 'EN', 'intro' => 'Energy instruments can reflect distinct markets and contract conventions. The demo catalogue displays only instruments configured in the database.', 'groups' => []],
        'stocks' => ['name' => 'Stocks', 'description' => 'Equity instruments will appear here when configured for the demo feed.', 'icon' => 'EQ', 'intro' => 'Stock instruments can be added to this catalogue through future feed configuration. No sample stock quotes are invented when the database has no records.', 'groups' => []],
        'digital-assets' => ['name' => 'Digital Assets', 'description' => 'Digital asset examples with clear demo-mode risk context.', 'icon' => 'DA', 'intro' => 'Digital asset markets can be volatile and carry substantial risk. These static database values are for interface demonstration only and do not represent live prices.', 'groups' => []],
    ];

    public static function categories(): array
    {
        try {
            $rows = \App\Database::connection()->query("SELECT c.id, c.name, c.slug, c.description, COUNT(i.id) AS instrument_count FROM instrument_categories c LEFT JOIN instruments i ON i.category_id = c.id AND i.status = 'active' WHERE c.status = 'active' GROUP BY c.id, c.name, c.slug, c.description, c.display_order ORDER BY c.display_order, c.id")->fetchAll();
            return ['ok' => true, 'rows' => $rows];
        } catch (Throwable $exception) {
            error_log('Markets category query unavailable: ' . $exception->getMessage());
            return ['ok' => false, 'rows' => []];
        }
    }

    public static function category(string $slug): ?array
    {
        return self::CATEGORIES[$slug] ?? null;
    }

    public static function instruments(string $slug): array
    {
        try {
            $statement = \App\Database::connection()->prepare("SELECT i.symbol, i.display_name, i.base_currency, i.quote_currency, i.bid, i.ask, i.spread, i.precision_digits, i.contract_size, i.margin_rate, i.status, c.name AS category_name, c.slug AS category_slug FROM instruments i JOIN instrument_categories c ON c.id = i.category_id WHERE c.slug = :slug AND c.status = 'active' AND i.status = 'active' ORDER BY i.display_order, i.symbol");
            $statement->execute(['slug' => $slug]);
            $rows = $statement->fetchAll();
            return ['ok' => true, 'rows' => self::formatRows($rows)];
        } catch (Throwable $exception) {
            error_log('Markets instrument query unavailable for ' . $slug . ': ' . $exception->getMessage());
            return ['ok' => false, 'rows' => []];
        }
    }

    public static function featured(array $categoryRows): array
    {
        $symbols = ['EURUSD', 'XAUUSD', 'US30', 'WTI', 'BTCUSD'];
        if ($categoryRows === []) { return []; }
        try {
            $marks = implode(',', array_fill(0, count($symbols), '?'));
            $statement = \App\Database::connection()->prepare("SELECT i.symbol, i.display_name, i.base_currency, i.quote_currency, i.bid, i.ask, i.spread, i.precision_digits, i.contract_size, i.margin_rate, i.status, c.name AS category_name, c.slug AS category_slug FROM instruments i JOIN instrument_categories c ON c.id = i.category_id WHERE c.status = 'active' AND i.status = 'active' AND i.symbol IN ($marks) ORDER BY c.display_order, i.display_order, i.symbol");
            $statement->execute($symbols);
            return self::formatRows($statement->fetchAll());
        } catch (Throwable $exception) {
            error_log('Markets featured query unavailable: ' . $exception->getMessage());
            return [];
        }
    }

    public static function categoryMeta(): array
    {
        return self::CATEGORIES;
    }

    private static function region(string $symbol): string
    {
        if (in_array($symbol, ['US30', 'NAS100', 'SPX500'], true)) { return 'us'; }
        if ($symbol === 'UK100') { return 'uk'; }
        if ($symbol === 'GER40') { return 'europe'; }
        return '';
    }

    private static function pairGroup(string $symbol): string
    {
        $majors = ['EURUSD', 'GBPUSD', 'USDJPY', 'AUDUSD', 'USDCAD', 'USDCHF', 'NZDUSD'];
        if (in_array($symbol, $majors, true)) { return 'major'; }
        if (preg_match('/^[A-Z]{6}$/', $symbol) === 1) { return 'minor'; }
        return '';
    }

    private static function formatRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $digits = max(0, min(10, (int) ($row['precision_digits'] ?? 2)));
            foreach (['bid', 'ask', 'spread'] as $field) {
                $row[$field . '_formatted'] = number_format((float) ($row[$field] ?? 0), $digits, '.', ',');
            }
            $row['precision_digits'] = $digits;
            $row['region'] = self::region((string) $row['symbol']);
            $row['pair_group'] = self::pairGroup((string) $row['symbol']);
        }
        unset($row);
        return $rows;
    }
}
