<section class="empty-state" aria-labelledby="<?= e($id ?? 'empty-title') ?>">
    <span class="empty-state-icon"><?= ui_icon($icon ?? 'chart') ?></span>
    <h3 id="<?= e($id ?? 'empty-title') ?>"><?= e($heading ?? 'Nothing here yet') ?></h3>
    <p><?= e($message ?? '') ?></p>
    <?php if (!empty($actionLabel) && !empty($actionUrl)): ?><?php render('components/button', ['href' => $actionUrl, 'label' => $actionLabel, 'variant' => 'outline']); ?><?php endif; ?>
</section>
