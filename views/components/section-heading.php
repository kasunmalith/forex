<?php $align = $align ?? 'left'; $align = in_array($align, ['left','center'], true) ? $align : 'left'; ?>
<div class="section-heading <?= $align === 'center' ? 'is-centered' : '' ?>">
    <div class="section-heading-copy">
        <?php if (!empty($eyebrow)): ?><span class="eyebrow"><?= e($eyebrow) ?></span><?php endif; ?>
        <h2><?= e($heading ?? '') ?></h2>
        <?php if (!empty($description)): ?><p><?= e($description) ?></p><?php endif; ?>
    </div>
    <?php if (!empty($actionLabel) && !empty($actionUrl)): ?><a class="text-link section-heading-action" href="<?= e($actionUrl) ?>"><?= e($actionLabel) ?> <?= ui_icon('arrow-right') ?></a><?php endif; ?>
</div>
