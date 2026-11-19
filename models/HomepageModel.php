<?php
declare(strict_types=1);

namespace App\Models;

use Throwable;

final class HomepageModel
{
    private const FALLBACK_CATEGORIES = [
        ['name' => 'Forex', 'slug' => 'forex', 'description' => 'Major, minor and cross currency pairs.', 'instrument_count' => 8],
        ['name' => 'Metals', 'slug' => 'metals', 'description' => 'Illustrative precious metal instruments.', 'instrument_count' => 2],
        ['name' => 'Indices', 'slug' => 'indices', 'description' => 'Selected global index examples.', 'instrument_count' => 5],
        ['name' => 'Commodities', 'slug' => 'commodities', 'description' => 'Energy market examples for practice.', 'instrument_count' => 2],
        ['name' => 'Stocks', 'slug' => 'stocks', 'description' => 'Stock instruments are planned for a later phase.', 'instrument_count' => 0],
        ['name' => 'Digital Assets', 'slug' => 'digital-assets', 'description' => 'Static digital asset examples.', 'instrument_count' => 2],
    ];

    private const FALLBACK_INSTRUMENTS = [
        ['symbol' => 'EURUSD', 'display_name' => 'Euro / US Dollar', 'base_currency' => 'EUR', 'quote_currency' => 'USD', 'bid' => '1.0840800000', 'ask' => '1.0843200000', 'spread' => '0.0002400000', 'precision_digits' => 5, 'category_name' => 'Forex'],
        ['symbol' => 'GBPUSD', 'display_name' => 'British Pound / US Dollar', 'base_currency' => 'GBP', 'quote_currency' => 'USD', 'bid' => '1.2713000000', 'ask' => '1.2717000000', 'spread' => '0.0004000000', 'precision_digits' => 5, 'category_name' => 'Forex'],
        ['symbol' => 'USDJPY', 'display_name' => 'US Dollar / Japanese Yen', 'base_currency' => 'USD', 'quote_currency' => 'JPY', 'bid' => '149.2300000000', 'ask' => '149.2700000000', 'spread' => '0.0400000000', 'precision_digits' => 3, 'category_name' => 'Forex'],
        ['symbol' => 'XAUUSD', 'display_name' => 'Gold / US Dollar', 'base_currency' => 'XAU', 'quote_currency' => 'USD', 'bid' => '2325.2500000000', 'ask' => '2325.7500000000', 'spread' => '0.5000000000', 'precision_digits' => 2, 'category_name' => 'Metals'],
        ['symbol' => 'NAS100', 'display_name' => 'US Tech 100 Index', 'base_currency' => 'NAS100', 'quote_currency' => 'USD', 'bid' => '18098.5000000000', 'ask' => '18101.5000000000', 'spread' => '3.0000000000', 'precision_digits' => 1, 'category_name' => 'Indices'],
        ['symbol' => 'US30', 'display_name' => 'US 30 Index', 'base_currency' => 'US30', 'quote_currency' => 'USD', 'bid' => '38848.5000000000', 'ask' => '38851.5000000000', 'spread' => '3.0000000000', 'precision_digits' => 1, 'category_name' => 'Indices'],
        ['symbol' => 'WTI', 'display_name' => 'WTI Crude Oil', 'base_currency' => 'WTI', 'quote_currency' => 'USD', 'bid' => '78.4500000000', 'ask' => '78.5500000000', 'spread' => '0.1000000000', 'precision_digits' => 2, 'category_name' => 'Commodities'],
        ['symbol' => 'BTCUSD', 'display_name' => 'Bitcoin / US Dollar', 'base_currency' => 'BTC', 'quote_currency' => 'USD', 'bid' => '64975.0000000000', 'ask' => '65025.0000000000', 'spread' => '50.0000000000', 'precision_digits' => 2, 'category_name' => 'Digital Assets'],
        ['symbol' => 'ETHUSD', 'display_name' => 'Ether / US Dollar', 'base_currency' => 'ETH', 'quote_currency' => 'USD', 'bid' => '3398.0000000000', 'ask' => '3402.0000000000', 'spread' => '4.0000000000', 'precision_digits' => 2, 'category_name' => 'Digital Assets'],
        ['symbol' => 'AUDUSD', 'display_name' => 'Australian Dollar / US Dollar', 'base_currency' => 'AUD', 'quote_currency' => 'USD', 'bid' => '0.6619600000', 'ask' => '0.6622400000', 'spread' => '0.0002800000', 'precision_digits' => 5, 'category_name' => 'Forex'],
        ['symbol' => 'USDCAD', 'display_name' => 'US Dollar / Canadian Dollar', 'base_currency' => 'USD', 'quote_currency' => 'CAD', 'bid' => '1.3650200000', 'ask' => '1.3653800000', 'spread' => '0.0003600000', 'precision_digits' => 5, 'category_name' => 'Forex'],
        ['symbol' => 'USDCHF', 'display_name' => 'US Dollar / Swiss Franc', 'base_currency' => 'USD', 'quote_currency' => 'CHF', 'bid' => '0.8948200000', 'ask' => '0.8951800000', 'spread' => '0.0003600000', 'precision_digits' => 5, 'category_name' => 'Forex'],
        ['symbol' => 'EURGBP', 'display_name' => 'Euro / British Pound', 'base_currency' => 'EUR', 'quote_currency' => 'GBP', 'bid' => '0.8528500000', 'ask' => '0.8531500000', 'spread' => '0.0003000000', 'precision_digits' => 5, 'category_name' => 'Forex'],
        ['symbol' => 'EURJPY', 'display_name' => 'Euro / Japanese Yen', 'base_currency' => 'EUR', 'quote_currency' => 'JPY', 'bid' => '161.6950000000', 'ask' => '161.7450000000', 'spread' => '0.0500000000', 'precision_digits' => 3, 'category_name' => 'Forex'],
        ['symbol' => 'XAGUSD', 'display_name' => 'Silver / US Dollar', 'base_currency' => 'XAG', 'quote_currency' => 'USD', 'bid' => '29.1200000000', 'ask' => '29.1800000000', 'spread' => '0.0600000000', 'precision_digits' => 3, 'category_name' => 'Metals'],
        ['symbol' => 'SPX500', 'display_name' => 'US 500 Index', 'base_currency' => 'SPX500', 'quote_currency' => 'USD', 'bid' => '5099.5000000000', 'ask' => '5100.5000000000', 'spread' => '1.0000000000', 'precision_digits' => 1, 'category_name' => 'Indices'],
        ['symbol' => 'UK100', 'display_name' => 'UK 100 Index', 'base_currency' => 'UK100', 'quote_currency' => 'GBP', 'bid' => '7898.5000000000', 'ask' => '7901.5000000000', 'spread' => '3.0000000000', 'precision_digits' => 1, 'category_name' => 'Indices'],
        ['symbol' => 'GER40', 'display_name' => 'Germany 40 Index', 'base_currency' => 'GER40', 'quote_currency' => 'EUR', 'bid' => '17798.5000000000', 'ask' => '17801.5000000000', 'spread' => '3.0000000000', 'precision_digits' => 1, 'category_name' => 'Indices'],
        ['symbol' => 'BRENT', 'display_name' => 'Brent Crude Oil', 'base_currency' => 'BRENT', 'quote_currency' => 'USD', 'bid' => '83.1500000000', 'ask' => '83.2500000000', 'spread' => '0.1000000000', 'precision_digits' => 2, 'category_name' => 'Commodities'],
    ];

    public static function load(): array
    {
        $categories = self::FALLBACK_CATEGORIES;
        $instruments = self::FALLBACK_INSTRUMENTS;
        $articles = [];
        $databaseAvailable = false;

        try {
            $pdo = \App\Database::connection();
            $categories = $pdo->query(
                "SELECT c.name, c.slug, c.description, COUNT(i.id) AS instrument_count
                 FROM instrument_categories c
                 LEFT JOIN instruments i ON i.category_id = c.id AND i.status = 'active'
                 WHERE c.status = 'active'
                 GROUP BY c.id, c.name, c.slug, c.description, c.display_order
                 ORDER BY c.display_order, c.id"
            )->fetchAll();
            $instruments = $pdo->query(
                "SELECT i.symbol, i.display_name, i.base_currency, i.quote_currency,
                        i.bid, i.ask, i.spread, i.precision_digits, c.name AS category_name
                 FROM instruments i
                 JOIN instrument_categories c ON c.id = i.category_id
                 WHERE i.status = 'active' AND c.status = 'active'
                 ORDER BY c.display_order, i.display_order, i.id"
            )->fetchAll();
            $articles = $pdo->query(
                "SELECT a.title, a.slug, a.excerpt, c.name AS category_name, a.published_at
                 FROM articles a
                 JOIN article_categories c ON c.id = a.category_id
                 WHERE a.status = 'published' AND a.published_at <= CURRENT_TIMESTAMP
                 ORDER BY a.published_at DESC, a.id DESC
                 LIMIT 9"
            )->fetchAll();
            $databaseAvailable = true;
        } catch (Throwable $exception) {
            error_log('Homepage content query unavailable; using safe demo fallbacks: ' . $exception->getMessage());
        }

        if ($categories === []) {
            $categories = self::FALLBACK_CATEGORIES;
        }
        if ($instruments === []) {
            $instruments = self::FALLBACK_INSTRUMENTS;
        }

        $preferred = ['EURUSD', 'GBPUSD', 'USDJPY', 'XAUUSD', 'NAS100', 'US30', 'WTI', 'BTCUSD', 'ETHUSD', 'AUDUSD'];
        $instrumentBySymbol = [];
        foreach ($instruments as $instrument) {
            $instrumentBySymbol[(string) $instrument['symbol']] = self::formatInstrument($instrument);
        }
        $popular = [];
        foreach ($preferred as $symbol) {
            if (isset($instrumentBySymbol[$symbol])) {
                $popular[] = $instrumentBySymbol[$symbol];
            }
        }
        foreach ($instrumentBySymbol as $symbol => $instrument) {
            if (count($popular) >= 10) {
                break;
            }
            if (!in_array($symbol, $preferred, true)) {
                $popular[] = $instrument;
            }
        }

        $education = [];
        $insights = [];
        foreach ($articles as $article) {
            $category = strtolower((string) $article['category_name']);
            if (in_array($category, ['market news', 'market insights'], true)) {
                $insights[] = $article;
            } else {
                $education[] = $article;
            }
        }

        $educationFromContent = $education !== [];
        $insightsFromContent = $insights !== [];
        return [
            'categories' => $categories,
            'instruments' => $popular,
            'tickerInstruments' => array_slice($popular, 0, 7),
            'educationArticles' => array_slice($education, 0, 3) ?: self::fallbackArticles('education'),
            'insightArticles' => array_slice($insights, 0, 3) ?: self::fallbackArticles('insights'),
            'educationFromContent' => $educationFromContent,
            'insightsFromContent' => $insightsFromContent,
            'categoryCount' => count($categories),
            'instrumentCount' => count($instruments),
            'databaseAvailable' => $databaseAvailable,
        ];
    }

    private static function formatInstrument(array $row): array
    {
        $digits = max(0, min(8, (int) ($row['precision_digits'] ?? 2)));
        $format = static fn (mixed $value): string => number_format((float) $value, $digits, '.', ',');
        return [
            'symbol' => (string) ($row['symbol'] ?? ''),
            'name' => (string) ($row['display_name'] ?? 'Demo instrument'),
            'category' => (string) ($row['category_name'] ?? 'Markets'),
            'base' => (string) ($row['base_currency'] ?? ''),
            'quote' => (string) ($row['quote_currency'] ?? ''),
            'bid' => $format($row['bid'] ?? 0),
            'ask' => $format($row['ask'] ?? 0),
            'spread' => $format($row['spread'] ?? 0),
            'mid' => $format(((float) ($row['bid'] ?? 0) + (float) ($row['ask'] ?? 0)) / 2),
            'precision' => $digits,
        ];
    }

    private static function fallbackArticles(string $type): array
    {
        $education = [
            ['title' => 'Understanding currency pairs', 'excerpt' => 'A plain-language introduction to base and quote currencies, major pairs and how a demo quote is displayed.', 'category_name' => 'Education'],
            ['title' => 'How spread appears in a quote', 'excerpt' => 'See how the difference between seeded bid and ask values is represented in this static interface.', 'category_name' => 'Platform guide'],
            ['title' => 'A first look at margin', 'excerpt' => 'Learn the terminology behind margin before exploring any simulated account workflow.', 'category_name' => 'Learning basics'],
        ];
        $insights = [
            ['title' => 'Reading a demo market snapshot', 'excerpt' => 'A guided look at the symbols and sample prices in this non-live market catalogue.', 'category_name' => 'Demo insight'],
            ['title' => 'Why seeded prices stay still', 'excerpt' => 'Static values make it easier to review the interface without suggesting a live feed.', 'category_name' => 'Platform note'],
            ['title' => 'Explore assets by category', 'excerpt' => 'Compare the structure of forex, metals, indices and other planned market groups.', 'category_name' => 'Market guide'],
        ];
        return $type === 'education' ? $education : $insights;
    }
}
