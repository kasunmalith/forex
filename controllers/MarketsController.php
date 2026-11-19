<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\MarketsModel;

final class MarketsController
{
    public function index(): void
    {
        $result = MarketsModel::categories();
        $rows = $result['rows'];
        $categories = MarketsModel::categoryMeta();
        $merged = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if (!isset($categories[$slug])) { continue; }
            $merged[] = array_merge($categories[$slug], $row, ['url' => \site_url('markets/' . $slug)]);
        }
        $featured = $result['ok'] ? MarketsModel::featured($rows) : [];
        $this->renderPage('markets/index', [
            'title' => 'Explore Global Markets · ' . \site_setting('company_name', 'MultiForex'),
            'metaDescription' => \site_setting('default_seo_description', 'Browse forex, metals, indices, commodities, stocks and digital assets with simulated demo market data from ' . \site_setting('company_name', 'MultiForex') . '.'),
            'canonicalUrl' => \site_url('markets'), 'openGraphTitle' => 'Explore Global Markets · ' . \site_setting('company_name', 'MultiForex'),
            'openGraphDescription' => 'Explore asset classes with simulated demo market data.', 'categories' => $merged,
            'featured' => $featured, 'dataAvailable' => $result['ok'], 'search' => self::query('q'),
        ]);
    }

    public function category(string $slug): void
    {
        if (MarketsModel::category($slug) === null || !in_array($slug, ['forex','metals','indices','commodities','stocks','digital-assets'], true)) {
            http_response_code(404);
            \render('public/not-found', ['title' => 'Market not found']);
            return;
        }
        $configured = MarketsModel::categories();
        if ($configured['ok'] && !in_array($slug, array_column($configured['rows'], 'slug'), true)) {
            http_response_code(404);
            \render('public/not-found', ['title' => 'Market not found']);
            return;
        }
        $meta = MarketsModel::category($slug);
        $data = MarketsModel::instruments($slug);
        $brand = \site_setting('company_name', 'MultiForex');
        $name = (string) $meta['name'];
        $this->renderPage('markets/category', [
            'title' => $name . ' Demo Markets · ' . $brand,
            'metaDescription' => 'Explore ' . strtolower($name) . ' instruments and simulated demo quotes in the ' . $brand . ' market catalogue.',
            'canonicalUrl' => \site_url('markets/' . $slug), 'openGraphTitle' => $name . ' Demo Markets · ' . $brand,
            'openGraphDescription' => 'Browse ' . strtolower($name) . ' in a simulated demo environment.',
            'category' => array_merge($meta, ['slug' => $slug]), 'instruments' => $data['rows'], 'dataAvailable' => $data['ok'] && $configured['ok'],
            'allCategories' => MarketsModel::categoryMeta(), 'configuredCategories' => $configured['rows'], 'search' => self::query('q'), 'symbolFilter' => self::query('symbol'),
            'categoryQueryOk' => $configured['ok'],
        ]);
    }

    private function renderPage(string $view, array $data): void
    {
        $data['companyName'] = \site_setting('company_name', 'MultiForex');
        $data['marketAssets'] = true;
        \render('public/' . $view, $data);
    }

    private static function query(string $key): string
    {
        $value = $_GET[$key] ?? '';
        return is_string($value) ? substr(trim($value), 0, 80) : '';
    }
}
