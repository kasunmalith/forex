<?php require APP_ROOT . '/views/components/header.php'; ?>
<section class="status-card" aria-labelledby="page-title">
    <span class="eyebrow">404</span>
    <h1 id="page-title">Page not found</h1>
    <p>The requested page is unavailable.</p>
    <a class="button" href="<?= e(site_url()) ?>">Return home</a>
</section>
<?php require APP_ROOT . '/views/components/footer.php'; ?>
