<?php
$fullName = trim((string) (($portalUser['first_name'] ?? '') . ' ' . ($portalUser['last_name'] ?? '')));
if ($fullName === '') { $fullName = (string) ($portalUser['first_name'] ?? 'Client'); }
?>
<header class="client-topbar">
    <button class="client-menu-toggle" type="button" aria-label="Open client navigation" aria-controls="client-sidebar" aria-expanded="false" data-client-drawer-open>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div class="client-topbar-title">
        <span class="client-breadcrumb"><?= e($companyName) ?> <i>/</i> Client Portal</span>
        <strong><?= e($pageTitle) ?></strong>
    </div>
    <div class="client-topbar-actions">
        <span class="client-demo-badge"><i></i> DEMO · VIRTUAL FUNDS</span>
        <details class="client-notifications">
            <summary aria-label="Notifications<?= !empty($portalNotifications['unread']) ? ', ' . (int) $portalNotifications['unread'] . ' unread' : '' ?>" data-notification-bell data-count-url="<?=e(site_url('api/notifications/count'))?>" data-login-url="<?=e(site_url('login'))?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4"/></svg>
                <?php if (!empty($portalNotifications['unread'])): ?><span><?= e((string) $portalNotifications['unread']) ?></span><?php endif; ?>
            </summary>
            <div class="client-popover client-notification-popover">
                <strong>Notifications</strong>
                <?php if (empty($portalNotifications['items'])): ?>
                    <p>No notifications yet.</p>
                <?php else: ?>
                    <?php foreach ($portalNotifications['items'] as $notification): ?>
                        <article class="<?= empty($notification['is_read']) ? 'is-unread' : '' ?>">
                            <?php if (!empty($notification['safe_link'])): ?><a href="<?=e($notification['safe_link'])?>"><?=e($notification['title'])?></a><?php else: ?><span><?=e($notification['title'])?></span><?php endif; ?><p><?= e($notification['message']) ?></p>
                            <small><?= e(\App\Services\DashboardService::formatDate($notification['created_at'])) ?></small>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a class="client-notification-view-all" href="<?=e(client_url('notifications'))?>">View all notifications</a>
            </div>
        </details>
        <details class="client-user-menu">
            <summary aria-label="Open profile menu"><span class="client-avatar"><?= e(strtoupper(substr($fullName, 0, 1))) ?></span><span class="client-user-menu-name"><?= e($fullName) ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
            <div class="client-popover client-user-popover">
                <strong><?= e($fullName) ?></strong><small><?= e($portalUser['email'] ?? '') ?></small>
                <a href="<?= e(client_url('profile')) ?>">View profile</a>
                <a href="<?= e(site_url('contact')) ?>">Contact Support</a>
                <form method="post" action="<?= e(site_url('logout')) ?>"><?= csrf_field() ?><button type="submit">Log out</button></form>
            </div>
        </details>
    </div>
</header>
