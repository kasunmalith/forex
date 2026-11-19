<?php
$navGroups = [
    'Workspace' => [
        ['page'=>'dashboard','label'=>'Dashboard','href'=>client_url('dashboard'),'icon'=>'grid'],
        ['page'=>'trade','label'=>'Trading Terminal','href'=>client_url('trade'),'icon'=>'candles'],
        ['page'=>'markets','label'=>'Markets','href'=>site_url('markets'),'icon'=>'globe'],
    ],
    'Activity' => [
        ['page'=>'positions','label'=>'Positions','href'=>client_url('positions'),'icon'=>'layers'],
        ['page'=>'orders','label'=>'Orders','href'=>client_url('orders'),'icon'=>'clipboard'],
        ['page'=>'history','label'=>'Trade History','href'=>client_url('history'),'icon'=>'clock'],
        ['page'=>'watchlist','label'=>'Watchlist','href'=>client_url('watchlist'),'icon'=>'star'],
    ],
    'Account' => [
        ['page'=>'profile','label'=>'Profile','href'=>client_url('profile'),'icon'=>'user'],
        ['page'=>'funds','label'=>'Demo Funds','href'=>client_url('funds'),'icon'=>'wallet'],
        ['page'=>'verification','label'=>'Verification','href'=>client_url('verification'),'icon'=>'shield'],
        ['page'=>'support','label'=>'Support','href'=>client_url('support'),'icon'=>'help'],
        ['page'=>'notifications','label'=>'Notifications','href'=>client_url('notifications'),'icon'=>'bell'],
    ],
];
$icons = [
    'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'candles'=>'<path d="M7 3v4m0 4v10M17 3v7m0 4v7M4 7h6v4H4zm10 3h6v4h-6z"/>',
    'globe'=>'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
    'layers'=>'<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>',
    'clipboard'=>'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5h6v3H9zm-1 7h8m-8 4h8"/>',
    'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'star'=>'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
    'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'wallet'=>'<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 9h18m-5 5h2"/>',
    'shield'=>'<path d="M12 3 20 6v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-3Z"/><path d="m9 12 2 2 4-4"/>',
    'help'=>'<circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.5 1.5c-1.2 1.2-2.1 1.3-2.1 3m0 3h.01"/>',
    'bell'=>'<path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4"/>',
];
?>
<aside class="client-sidebar" id="client-sidebar" aria-label="Client portal navigation" data-client-sidebar>
    <div class="client-sidebar-head">
        <a class="client-brand" href="<?= e(client_url('dashboard')) ?>" aria-label="<?= e($companyName) ?> dashboard">
            <span class="client-brand-mark" aria-hidden="true">M</span><span><?= e($companyName) ?><small>CLIENT PORTAL</small></span>
        </a>
        <button class="client-sidebar-close" type="button" aria-label="Close navigation menu" data-client-drawer-close>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
    </div>
    <nav class="client-nav">
        <?php foreach ($navGroups as $group => $links): ?>
            <div class="client-nav-group">
                <span class="client-nav-heading"><?= e($group) ?></span>
                <?php foreach ($links as $item): $isActive = $activePage === $item['page']; ?>
                    <a class="client-nav-link<?= $isActive ? ' is-active' : '' ?><?= !empty($item['later']) ? ' has-later-label' : '' ?>"
                       href="<?= e($item['href']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icons[$item['icon']] ?></svg>
                        <span><?= e($item['label']) ?></span>
                        <?php if (!empty($item['later'])): ?><small><?= e($item['later'] === true ? 'Later' : $item['later']) ?></small><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
    <div class="client-sidebar-bottom">
        <div class="client-account-chip">
            <span class="client-account-chip-icon">D</span>
            <span><strong><?= e($portalAccount['account_number'] ?? 'Demo account') ?></strong><small>Virtual funds only</small></span>
            <i aria-hidden="true"></i>
        </div>
        <form method="post" action="<?= e(site_url('logout')) ?>" class="client-logout-form">
            <?= csrf_field() ?>
            <button class="client-logout-button" type="submit"><span aria-hidden="true">↗</span> Sign out securely</button>
        </form>
        <p class="client-software-credit">Software Development by <a href="https://www.energeticedge.lk/" target="_blank" rel="noopener noreferrer">Energetic Edge (Pvt) Ltd</a></p>
    </div>
</aside>
