<?php if (!empty($items)): ?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <?php foreach (array_values($items) as $index => $item): ?>
        <?php if ($index > 0): ?><span class="breadcrumb-separator" aria-hidden="true">/</span><?php endif; ?>
        <?php if (!empty($item['url']) && $index < count($items) - 1): ?>
            <a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
        <?php else: ?>
            <span <?= $index === count($items) - 1 ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
