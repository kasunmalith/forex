<?php
$portalUser = $clientData['user'] ?? [];
$portalAccount = $clientData['account'] ?? null;
$portalNotice = $clientData['notice'] ?? null;
$portalError = $clientData['error'] ?? null;
$portalErrorMessage = $clientData['errorMessage'] ?? null;
$portalNotifications = $clientData['notifications'] ?? ['unread'=>0,'items'=>[]];
$brandName = $companyName ?? site_setting('company_name', 'MultiForex');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07111f">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="description" content="Private MultiForex demo client portal. Virtual funds only.">
    <title><?= e($pageTitle) ?> · <?= e($brandName) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/typography.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/client.css') . '?v=' . (string) filemtime(APP_ROOT . '/assets/css/client.css')) ?>">
    <?php if (($activePage ?? '') === 'funds'): ?><link rel="stylesheet" href="<?=e(asset_url('css/client-funds.css').'?v='.(string)filemtime(APP_ROOT.'/assets/css/client-funds.css'))?>"><?php endif; ?>
    <?php if (!empty($terminalMode)): ?><link rel="stylesheet" href="<?= e(asset_url('css/trading-terminal.css') . '?v=' . (string) filemtime(APP_ROOT . '/assets/css/trading-terminal.css')) ?>"><?php endif; ?>
    <script src="<?= e(asset_url('js/client.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/client.js')) ?>" defer></script>
    <?php if (!empty($terminalMode)): ?><script src="<?= e(asset_url('js/trading-terminal.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/trading-terminal.js')) ?>" defer></script><?php endif; ?>
    <?php if (!empty($terminalMode)): ?><script src="<?= e(asset_url('js/market-data.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/market-data.js')) ?>" defer></script><?php endif; ?>
    <?php if (($activePage ?? '') === 'history'): ?><script src="<?= e(asset_url('js/trading-history.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/trading-history.js')) ?>" defer></script><?php endif; ?>
    <?php if (in_array(($activePage ?? ''),['verification','profile'],true)): ?><link rel="stylesheet" href="<?=e(asset_url('css/client-verification.css').'?v='.(string)filemtime(APP_ROOT.'/assets/css/client-verification.css'))?>"><script src="<?=e(asset_url('js/client-verification.js').'?v='.(string)filemtime(APP_ROOT.'/assets/js/client-verification.js'))?>" defer></script><?php endif; ?>
    <?php if (!empty($communicationPage)): ?><link rel="stylesheet" href="<?=e(asset_url('css/client-communication.css').'?v='.(string)filemtime(APP_ROOT.'/assets/css/client-communication.css'))?>"><script src="<?=e(asset_url('js/client-communication.js').'?v='.(string)filemtime(APP_ROOT.'/assets/js/client-communication.js'))?>" defer></script><?php else: ?><script src="<?=e(asset_url('js/client-notification-count.js').'?v='.(string)filemtime(APP_ROOT.'/assets/js/client-notification-count.js'))?>" defer></script><?php endif; ?>
    <?php if (empty($terminalMode)): ?><script src="<?= e(asset_url('js/public-market-data.js') . '?v=' . (string) filemtime(APP_ROOT . '/assets/js/public-market-data.js')) ?>" data-quotes-url="<?= e(api_url('market/quotes')) ?>" defer></script><?php endif; ?>
</head>
<body class="client-body">
<a class="client-skip-link" href="#client-main">Skip to <?= !empty($terminalMode) ? 'terminal' : 'dashboard' ?> content</a>
<div class="client-app<?= !empty($terminalMode) ? ' client-app-terminal' : '' ?>">
    <?php require APP_ROOT . '/views/client/sidebar.php'; ?>
    <div class="client-main-column">
        <?php require APP_ROOT . '/views/client/topbar.php'; ?>
        <main class="client-main-content<?= !empty($terminalMode) ? ' client-main-content-terminal' : '' ?>" id="client-main" data-client-view="<?= e((string)($activePage??'')) ?>"<?= in_array(($activePage??''),['dashboard','positions','orders','watchlist'],true) ? ' data-client-snapshot-url="'.e(site_url('api/client/snapshot?view='.rawurlencode((string)$activePage))).'" data-client-login-url="'.e(site_url('login')).'"' : '' ?>>
            <div class="client-flash is-error" data-client-connection hidden role="status"></div>
            <?php if ($portalNotice): ?><div class="client-flash is-success" role="status"><?= e($portalNotice) ?></div><?php endif; ?>
            <?php if ($portalError): ?><div class="client-flash is-error" role="alert"><?= e($portalError) ?></div><?php endif; ?>
            <?php if ($portalErrorMessage): ?>
                <section class="client-panel client-error-panel" role="alert"><span class="client-eyebrow">PORTAL UNAVAILABLE</span><h1>We couldn’t load this information.</h1><p><?= e($portalErrorMessage) ?></p><a class="client-button client-button-secondary" href="<?= e(site_url('contact')) ?>">Contact Support</a></section>
            <?php else: ?>
                <?php render($clientView, $clientData); ?>
            <?php endif; ?>
        </main>
    </div>
    <button class="client-drawer-backdrop" type="button" aria-label="Close navigation menu" data-client-drawer-close hidden></button>
</div>
</body>
</html>
