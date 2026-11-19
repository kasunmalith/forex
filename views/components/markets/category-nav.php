<nav class="market-category-nav" aria-label="Market categories">
    <a class="<?= ($activeMarket ?? '') === '' ? 'is-current' : '' ?>" href="<?= e(site_url('markets')) ?>" <?= ($activeMarket ?? '') === '' ? 'aria-current="page"' : '' ?>>All Markets</a>
    <?php foreach (($marketCategories ?? []) as $slug => $meta): ?>
        <?php if (!empty($meta['configured'])): ?>
            <a class="<?= ($activeMarket ?? '') === $slug ? 'is-current' : '' ?>" href="<?= e(site_url('markets/' . $slug)) ?>" <?= ($activeMarket ?? '') === $slug ? 'aria-current="page"' : '' ?>><?= e($meta['name']) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
