<div class="admin-breadcrumb"><a href="<?= e(admin_url()) ?>">Administration</a><span>/</span><span><?= e($module ?? '') ?></span></div>
<section class="admin-panel admin-placeholder-panel">
    <span class="admin-eyebrow">ACCESS VERIFIED</span>
    <div class="admin-placeholder-icon" aria-hidden="true">✓</div>
    <h1><?= e($module ?? 'Admin module') ?></h1>
    <p class="admin-placeholder-permission">Permission confirmed: <code><?= e($permission ?? '') ?></code></p>
    <div class="admin-coming-soon"><strong>Module will be implemented in a later phase.</strong><span>This protected route is a permission check placeholder. It does not display or change operational data.</span></div>
    <a class="admin-button admin-button-secondary" href="<?= e(admin_url()) ?>">Back to overview</a>
</section>
