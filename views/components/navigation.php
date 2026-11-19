<?php
$brandName = $companyName ?? site_setting('company_name', 'MultiForex');
$activePath = current_path_key();
$activeMenus = [
    'markets' => ['markets'],
    'trading' => ['platform','accounts','tools'],
    'education' => ['education','articles','market-news'],
    'company' => ['company','about','security'],
    'support' => ['contact','faq'],
];
?>
<div class="header-main">
    <div class="header-main-inner">
        <a class="brand" href="<?= e(site_url()) ?>" aria-label="<?= e($brandName) ?> home">
            <?php $siteLogo=site_setting('site_logo',''); if($siteLogo!==''): ?><img class="site-brand-logo" src="<?= e(upload_url(substr($siteLogo,8))) ?>" alt="<?= e($brandName) ?>"><?php else: ?><span class="brand-mark" aria-hidden="true">M</span><span><?= e($brandName) ?></span><?php endif; ?>
        </a>

        <nav class="desktop-navigation" aria-label="Primary navigation">
            <div class="nav-dropdown" data-dropdown>
                <button class="nav-dropdown-toggle <?= in_array($activePath, $activeMenus['markets'], true) ? 'is-active' : '' ?>" type="button" aria-expanded="false" aria-controls="menu-markets" data-dropdown-trigger>
                    Markets <?= ui_icon('chevron-down') ?>
                </button>
                <div class="nav-dropdown-menu" id="menu-markets" hidden>
                    <a href="<?= e(site_url('markets')) ?>">All Markets</a>
                    <?php foreach (['forex'=>'Forex','metals'=>'Metals','indices'=>'Indices','commodities'=>'Commodities','stocks'=>'Stocks','digital-assets'=>'Digital Assets'] as $slug=>$marketCategory): ?><a href="<?= e(site_url('markets/' . $slug)) ?>"><?= e($marketCategory) ?></a><?php endforeach; ?>
                </div>
            </div>
            <div class="nav-dropdown" data-dropdown>
                <button class="nav-dropdown-toggle <?= in_array($activePath, $activeMenus['trading'], true) ? 'is-active' : '' ?>" type="button" aria-expanded="false" aria-controls="menu-trading" data-dropdown-trigger>
                    Trading <?= ui_icon('chevron-down') ?>
                </button>
                <div class="nav-dropdown-menu" id="menu-trading" hidden>
                    <a href="<?= e(site_url('platform')) ?>">Trading Platforms</a>
                    <a href="<?= e(site_url('accounts')) ?>">Demo Accounts</a>
                    <a href="<?= e(site_url('tools')) ?>">Trading Tools</a>
                </div>
            </div>
            <div class="nav-dropdown" data-dropdown>
                <button class="nav-dropdown-toggle <?= in_array($activePath, $activeMenus['education'], true) ? 'is-active' : '' ?>" type="button" aria-expanded="false" aria-controls="menu-education" data-dropdown-trigger>
                    Education <?= ui_icon('chevron-down') ?>
                </button>
                <div class="nav-dropdown-menu" id="menu-education" hidden>
                    <a href="<?= e(site_url('education')) ?>">Learning Centre</a>
                    <a href="<?= e(site_url('articles')) ?>">Articles</a>
                    <a href="<?= e(site_url('market-news')) ?>">Market News</a>
                </div>
            </div>
            <div class="nav-dropdown" data-dropdown>
                <button class="nav-dropdown-toggle <?= in_array($activePath, $activeMenus['company'], true) ? 'is-active' : '' ?>" type="button" aria-expanded="false" aria-controls="menu-company" data-dropdown-trigger>
                    Company <?= ui_icon('chevron-down') ?>
                </button>
                <div class="nav-dropdown-menu" id="menu-company" hidden>
                    <a href="<?= e(site_url('company')) ?>">Company</a>
                    <a href="<?= e(site_url('about')) ?>">About</a>
                    <a href="<?= e(site_url('security')) ?>">Security</a>
                </div>
            </div>
            <div class="nav-dropdown" data-dropdown><button class="nav-dropdown-toggle <?= in_array($activePath,$activeMenus['support'],true) ? 'is-active' : '' ?>" type="button" aria-expanded="false" aria-controls="menu-support" data-dropdown-trigger>Support <?= ui_icon('chevron-down') ?></button><div class="nav-dropdown-menu" id="menu-support" hidden><a href="<?= e(site_url('contact')) ?>">Contact</a><a href="<?= e(site_url('faq')) ?>">FAQ</a></div></div>
        </nav>

        <div class="header-actions">
            <?php if (auth_check()): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(site_url('client/dashboard')) ?>">Client Portal</a>
                <form class="header-logout-form" method="post" action="<?= e(site_url('logout')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit">Logout</button></form>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(site_url('login')) ?>">Login</a>
                <a class="btn btn-primary btn-sm" href="<?= e(site_url('register')) ?>">Open Demo Account <?= ui_icon('arrow-up-right') ?></a>
            <?php endif; ?>
        </div>
        <button class="menu-toggle" type="button" aria-label="Open navigation menu" aria-controls="mobile-menu" aria-expanded="false" data-menu-open>
            <?= ui_icon('bars') ?>
        </button>
    </div>
</div>

<dialog class="mobile-menu" id="mobile-menu" aria-labelledby="mobile-menu-title" data-mobile-menu>
    <div class="mobile-menu-inner">
        <div class="mobile-menu-top">
            <a class="brand" href="<?= e(site_url()) ?>" aria-label="<?= e($brandName) ?> home">
                <span class="brand-mark" aria-hidden="true">M</span><span id="mobile-menu-title"><?= e($brandName) ?></span>
            </a>
            <button class="mobile-menu-close" type="button" aria-label="Close navigation menu" data-menu-close><?= ui_icon('close') ?></button>
        </div>
        <nav class="mobile-navigation" aria-label="Mobile navigation">
            <details><summary>Markets <?= ui_icon('chevron-down') ?></summary><div class="mobile-submenu">
                <a href="<?= e(site_url('markets')) ?>">All Markets</a>
                <?php foreach (['forex'=>'Forex','metals'=>'Metals','indices'=>'Indices','commodities'=>'Commodities','stocks'=>'Stocks','digital-assets'=>'Digital Assets'] as $slug=>$marketCategory): ?><a href="<?= e(site_url('markets/' . $slug)) ?>"><?= e($marketCategory) ?></a><?php endforeach; ?>
            </div></details>
            <details><summary>Trading <?= ui_icon('chevron-down') ?></summary><div class="mobile-submenu">
                <a href="<?= e(site_url('platform')) ?>">Trading Platforms</a><a href="<?= e(site_url('accounts')) ?>">Demo Accounts</a><a href="<?= e(site_url('tools')) ?>">Trading Tools</a>
            </div></details>
            <details><summary>Education <?= ui_icon('chevron-down') ?></summary><div class="mobile-submenu">
                <a href="<?= e(site_url('education')) ?>">Learning Centre</a><a href="<?= e(site_url('articles')) ?>">Articles</a><a href="<?= e(site_url('market-news')) ?>">Market News</a>
            </div></details>
            <details><summary>Company <?= ui_icon('chevron-down') ?></summary><div class="mobile-submenu">
                <a href="<?= e(site_url('company')) ?>">Company</a><a href="<?= e(site_url('about')) ?>">About</a><a href="<?= e(site_url('security')) ?>">Security</a>
            </div></details>
            <details><summary>Support <?= ui_icon('chevron-down') ?></summary><div class="mobile-submenu"><a href="<?= e(site_url('contact')) ?>">Contact</a><a href="<?= e(site_url('faq')) ?>">FAQ</a></div></details>
        </nav>
        <div class="mobile-menu-actions">
            <?php if (auth_check()): ?>
                <a class="btn btn-secondary" href="<?= e(site_url('client')) ?>">Client Portal</a>
                <form method="post" action="<?= e(site_url('logout')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Logout</button></form>
            <?php else: ?>
                <a class="btn btn-secondary" href="<?= e(site_url('login')) ?>">Login</a>
                <a class="btn btn-primary" href="<?= e(site_url('register')) ?>">Open Demo Account <?= ui_icon('arrow-up-right') ?></a>
            <?php endif; ?>
        </div>
        <p class="mobile-menu-note">Demo environment · No real funds are processed.</p>
    </div>
</dialog>
