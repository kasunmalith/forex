<?php
$variant = $variant ?? 'primary';
$variant = in_array($variant, ['primary','secondary','outline','ghost','danger','success'], true) ? $variant : 'primary';
$size = $size ?? 'default';
$size = in_array($size, ['sm','default','lg'], true) ? ($size === 'default' ? '' : ' btn-' . $size) : '';
$classes = trim('btn btn-' . $variant . $size . ' ' . ($class ?? '') . (!empty($loading) ? ' is-loading' : ''));
$label = (string) ($label ?? 'Continue');
$iconName = $icon ?? null;
?>
<?php if (!empty($href)): ?>
<a class="<?= e($classes) ?>" href="<?= e($href) ?>" <?= !empty($disabled) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
    <?php if ($iconName && ($iconPosition ?? 'before') === 'before') { echo ui_icon($iconName); } ?><?= e($label) ?><?php if ($iconName && ($iconPosition ?? 'before') === 'after') { echo ui_icon($iconName); } ?>
</a>
<?php else: ?>
<button class="<?= e($classes) ?>" type="<?= e($type ?? 'button') ?>" <?= !empty($disabled) ? 'disabled' : '' ?> <?= !empty($loading) ? 'aria-busy="true"' : '' ?>>
    <?php if ($iconName && ($iconPosition ?? 'before') === 'before') { echo ui_icon($iconName); } ?><?= e($label) ?><?php if ($iconName && ($iconPosition ?? 'before') === 'after') { echo ui_icon($iconName); } ?>
</button>
<?php endif; ?>
