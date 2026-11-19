<section class="error-state" aria-labelledby="<?= e($id ?? 'error-title') ?>">
    <span class="error-state-icon"><?= ui_icon('info') ?></span>
    <h3 id="<?= e($id ?? 'error-title') ?>"><?= e($heading ?? 'Unable to load this information') ?></h3>
    <p><?= e($message ?? 'Please try again in a moment.') ?></p>
    <?php if (!empty($retryLabel)): ?><button class="btn btn-secondary" type="button" data-retry><?= e($retryLabel) ?></button><?php endif; ?>
</section>
