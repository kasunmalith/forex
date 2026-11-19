<div class="client-panel-empty">
    <span aria-hidden="true"><?= e($icon ?? '—') ?></span>
    <strong><?= e($title) ?></strong>
    <p><?= e($message) ?></p>
    <?php if (!empty($actionHref) && !empty($actionLabel)): ?><a class="client-text-link" href="<?= e($actionHref) ?>"><?= e($actionLabel) ?> <span aria-hidden="true">↗</span></a><?php endif; ?>
</div>