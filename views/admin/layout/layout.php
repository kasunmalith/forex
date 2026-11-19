<?php
$activeRoute = $activeRoute ?? '';
$nav = [
    ['dashboard','Overview','dashboard.view'],['users','Users','users.view'],['accounts','Demo Accounts','accounts.view'],
    ['trading','Trading','trading.view'],['instruments','Instruments','instruments.view'],['funds','Demo Funds','funds.view'],
    ['kyc','Verification','kyc.view'],['support','Support','support.view'],['cms','Content','cms.view'],
    ['admins','Administrators','admins.view'],['roles','Roles & Permissions','roles.view'],['audit','Audit Logs','audit.view'],['settings','Settings','settings.view'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>">
</head>
<body class="admin-app">
<div class="admin-app-shell">
    <button class="admin-sidebar-scrim" type="button" data-sidebar-close aria-label="Close navigation"></button>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Administration navigation">
        <div class="admin-sidebar-heading">
            <a class="admin-sidebar-brand" href="<?= e(admin_url()) ?>"><span class="admin-brand-mark">MF</span><span>MultiForex<small>Admin workspace</small></span></a>
            <button class="admin-sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">×</button>
        </div>
        <div class="admin-sidebar-label">WORKSPACE</div>
        <nav class="admin-nav">
            <?php foreach ($nav as [$slug,$label,$permission]): if (!in_array($permission,$permissions,true) && !($slug==='cms'&&in_array('support.view',$permissions,true))) continue; ?>
                <a class="admin-nav-link<?= $activeRoute === $slug ? ' is-active' : '' ?>" href="<?= e(admin_url($slug === 'dashboard' ? '' : ($slug === 'cms' ? 'cms/articles' : $slug))) ?>"<?= $activeRoute === $slug ? ' aria-current="page"' : '' ?>><span class="admin-nav-dot" aria-hidden="true"></span><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar-bottom">
            <div class="admin-demo-badge"><span aria-hidden="true"></span> DEMO ENVIRONMENT</div>
            <div class="admin-credit">Software Development by <a href="https://www.energeticedge.lk/" target="_blank" rel="noopener noreferrer">Energetic Edge (Pvt) Ltd</a></div>
        </div>
    </aside>
    <div class="admin-main-column">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" type="button" data-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open navigation"><span></span><span></span><span></span></button>
            <div class="admin-topbar-title"><span>MultiForex / Administration</span><strong><?= e($pageTitle) ?></strong></div>
            <div class="admin-topbar-actions">
                <span class="admin-environment-pill"><i></i> Demo environment</span>
                <details class="admin-account-menu"><summary><span class="admin-avatar"><?= e(strtoupper(substr((string)$admin['name'],0,1))) ?></span><span class="admin-account-copy"><strong><?= e($admin['name']) ?></strong><small><?= e($admin['role_name']) ?></small></span><span class="admin-chevron" aria-hidden="true">⌄</span></summary><div class="admin-account-popover"><strong><?= e($admin['email']) ?></strong><small><?= e($admin['role_name']) ?></small><form method="post" action="<?= e(admin_url('logout')) ?>"><?= admin_csrf_field() ?><button type="submit" class="admin-logout-button">Sign out</button></form></div></details>
            </div>
        </header>
        <main class="admin-content">
            <?php if (!empty($notice)): ?><div class="admin-alert admin-alert-info" role="status"><?= e($notice) ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?php render($contentView, $adminData ?? []); ?>
        </main>
    </div>
</div>
<script src="<?= e(asset_url('js/admin.js')) ?>" defer></script>
</body>
</html>
