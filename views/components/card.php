<article class="card <?= e($class ?? '') ?>">
    <?php if (!empty($title) || !empty($description)): ?>
        <div class="card-header"><div><?php if (!empty($title)): ?><h3 class="card-title"><?= e($title) ?></h3><?php endif; ?><?php if (!empty($description)): ?><p class="card-description"><?= e($description) ?></p><?php endif; ?></div><?php if (isset($badgeHtml)): ?><div><?= $badgeHtml ?></div><?php endif; ?></div>
    <?php endif; ?>
    <?= $body ?? '' /* Trusted server-rendered component markup only. */ ?>
</article>
