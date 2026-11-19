<section class="hero <?= e($class ?? '') ?>" aria-labelledby="<?= e($id ?? 'hero-title') ?>">
    <div class="container-wide hero-grid <?= !empty($centered) ? 'hero-centered' : '' ?>">
        <div class="hero-copy">
            <?php if (!empty($eyebrow)): ?><span class="eyebrow"><?= e($eyebrow) ?></span><?php endif; ?>
            <h1 class="<?= !empty($display) ? 'display-heading' : '' ?>" id="<?= e($id ?? 'hero-title') ?>"><?= $headingHtml ?? e($heading ?? '') ?></h1>
            <?php if (!empty($description)): ?><p class="body-large"><?= e($description) ?></p><?php endif; ?>
            <?php if (!empty($actions)): ?><div class="hero-actions"><?php foreach ($actions as $action): ?><?php render('components/button', $action); ?><?php endforeach; ?></div><?php endif; ?>
        </div>
        <?php if (!empty($visual)): ?><div class="hero-visual"><?= $visual /* Trusted server-rendered component markup only. */ ?></div><?php endif; ?>
    </div>
</section>
