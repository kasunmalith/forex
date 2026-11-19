<?php
declare(strict_types=1);

namespace App\Controllers;

final class PageController
{
    public function home(): void
    {
        $companyName = \site_setting('company_name', 'MultiForex');
        $homeData = \App\Models\HomepageModel::load();
        $instrumentBySymbol = [];
        foreach ($homeData['instruments'] as $instrument) {
            $instrumentBySymbol[$instrument['symbol']] = $instrument;
        }
        $canonicalUrl = \site_url();
        $companyTagline = \site_setting('company_tagline', 'Practice in a simulated trading environment');
        $supportEmail = \site_setting('support_email', '');
        $metaDescription = \site_setting('default_seo_description', $companyName . ' — ' . $companyTagline . '. Explore seeded global market examples with virtual funds.');
        $openGraphImage = \asset_url('images/multiforex-home-og.svg');
        $structuredData = json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'Organization', 'name' => $companyName, 'url' => $canonicalUrl, 'logo' => $openGraphImage],
                ['@type' => 'WebSite', 'name' => $companyName, 'url' => $canonicalUrl, 'inLanguage' => 'en'],
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        \render('public/home', array_merge($homeData, [
            'title' => \site_setting('default_seo_title', $companyName . ' | Demo Global Trading Platform'),
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'openGraphTitle' => $companyName . ' | Demo Global Trading Platform',
            'openGraphDescription' => $metaDescription,
            'openGraphImage' => $openGraphImage,
            'structuredData' => $structuredData,
            'companyName' => $companyName,
            'companyTagline' => $companyTagline,
            'supportEmail' => $supportEmail,
            'homepageAssets' => true,
            'heroInstrument' => $instrumentBySymbol['EURUSD'] ?? null,
            'goldInstrument' => $instrumentBySymbol['XAUUSD'] ?? null,
            'tickerInstruments' => $homeData['tickerInstruments'],
        ]));
    }

    public function designSystem(): void
    {
        if (\config('APP_ENV') !== 'development') {
            http_response_code(404);
            \render('public/not-found', ['title' => 'Page not found']);
            return;
        }
        \render('public/design-system', [
            'title' => 'Design System · ' . \site_setting('company_name', 'MultiForex'),
            'companyName' => \site_setting('company_name', 'MultiForex'),
        ]);
    }

    public function placeholder(string $area, string $page): void
    {
        \render('public/placeholder', [
            'title' => ucfirst($page),
            'area' => ucfirst($area),
            'page' => ucfirst($page),
        ]);
    }
}
