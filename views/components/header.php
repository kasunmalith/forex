<?php
$brandName = $companyName ?? site_setting('company_name', 'MultiForex');
$headerClass = 'site-header' . (!empty($transparentHeader) ? ' is-transparent' : '');
$metaDescription = $metaDescription ?? $brandName . ' demo trading platform and simulated market interface.';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07111f">
    <?php if (!empty($noindex) || auth_check()): ?><meta name="robots" content="noindex,nofollow,noarchive"><?php endif; ?>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <title><?= e($title ?? $brandName) ?></title>
    <?php if (!empty($canonicalUrl)): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>"><?php endif; ?>
    <?php if (!empty($siteFavicon = site_setting('site_favicon', ''))): ?><link rel="icon" href="<?= e(upload_url(substr($siteFavicon,8))) ?>"><?php endif; ?>
    <?php if (!empty($openGraphTitle)): ?><meta property="og:type" content="website"><meta property="og:title" content="<?= e($openGraphTitle) ?>"><meta property="og:description" content="<?= e($openGraphDescription ?? $metaDescription) ?>"><meta property="og:url" content="<?= e($canonicalUrl ?? site_url()) ?>"><meta property="og:image" content="<?= e($openGraphImage ?? asset_url('images/multiforex-home-og.svg')) ?>"><meta property="og:image:alt" content="Original illustrative chart for a simulated demo trading platform"><meta property="og:site_name" content="<?= e($brandName) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
    <?php if (!empty($structuredData)): ?><script type="application/ld+json"><?= $structuredData ?></script><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset_url('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/typography.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/components.css') . '?v=' . (string) filemtime(APP_ROOT . '/assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/responsive.css')) ?>">
    <?php if (!empty($homepageAssets)): ?><link rel="stylesheet" href="<?= e(asset_url('css/home.css') . '?v=' . (string) filemtime(APP_ROOT . '/assets/css/home.css')) ?>"><?php endif; ?>
    <?php if (!empty($marketAssets)): ?><link rel="stylesheet" href="<?= e(asset_url('css/markets.css') . '?v=' . (string) filemtime(APP_ROOT . '/assets/css/markets.css')) ?>"><?php endif; ?>
    <?php if (!empty($publicPageAssets)): ?><link rel="stylesheet" href="<?= e(asset_url('css/public-pages.css') . '?v=' . (string) filemtime(APP_ROOT . '/assets/css/public-pages.css')) ?>"><?php endif; ?>
    <?php if (!empty($authAssets)): ?><link rel="stylesheet" href="<?= e(asset_url('css/auth.css') . '?v=' . (string) filemtime(APP_ROOT . '/assets/css/auth.css')) ?>"><?php endif; ?>
    <script src="<?= e(asset_url('js/main.js')) ?>" defer></script>
    <?php if (!empty($homepageAssets)): ?><script src="<?= e(asset_url('js/home.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/home.js')) ?>" defer></script><?php endif; ?>
    <?php if (!empty($marketAssets)): ?><script src="<?= e(asset_url('js/markets.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/markets.js')) ?>" defer></script><?php endif; ?>
    <?php if (!empty($marketAssets) || !empty($homepageAssets)): ?><script src="<?= e(asset_url('js/public-market-data.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/public-market-data.js')) ?>" data-quotes-url="<?= e(api_url('market/quotes')) ?>" defer></script><?php endif; ?>
    <?php if (!empty($toolsAssets)): ?><script src="<?= e(asset_url('js/tools.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/tools.js')) ?>" defer></script><?php endif; ?>
    <?php if (!empty($contactAssets)): ?><script src="<?= e(asset_url('js/contact.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/contact.js')) ?>" defer></script><?php endif; ?>
    <?php if (!empty($authAssets)): ?><script src="<?= e(asset_url('js/auth.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/auth.js')) ?>" defer></script><?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="page-shell">
<header class="<?= e($headerClass) ?>" data-site-header>
    <div class="header-utility">
        <div class="header-utility-inner">
            <span class="utility-demo"><span class="utility-dot" aria-hidden="true"></span> DEMO ENVIRONMENT · SIMULATED VALUES</span>
            <span>Practice with virtual funds</span>
            <a href="<?= e(site_url('support')) ?>">Support</a>
        </div>
    </div>
    <?php require __DIR__ . '/navigation.php'; ?>
</header>
<div class="toast-region" aria-live="polite" aria-atomic="false" data-toast-region></div>
<main id="main-content" class="main-content">
