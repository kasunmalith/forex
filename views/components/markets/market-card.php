<a class="market-category-card" href="<?= e($market['url']) ?>" id="<?= e($market['slug']) ?>" data-category-card data-category-slug="<?= e($market['slug']) ?>" data-search="<?= e(strtolower($market['name'] . ' ' . $market['description'])) ?>">
    <span class="market-card-top"><span class="market-card-icon" aria-hidden="true"><?= e($market['icon']) ?></span><span class="market-card-count"><?= e((string) $market['instrument_count']) ?> instruments</span></span>
    <h3><?= e($market['name']) ?></h3><p><?= e($market['description']) ?></p>
    <?php if (!empty($market['examples'])): ?><span class="market-card-examples"><?php foreach ($market['examples'] as $example): ?><i><?= e($example) ?></i><?php endforeach; ?></span><?php endif; ?>
    <span class="market-card-link">View <?= e($market['name']) ?> <span aria-hidden="true">↗</span></span>
</a>
