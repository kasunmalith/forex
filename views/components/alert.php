<?php
$type = in_array($type ?? 'info', ['info','success','warning','error'], true) ? $type : 'info';
$iconName = ['info' => 'info', 'success' => 'check', 'warning' => 'info', 'error' => 'close'][$type];
?>
<div class="alert alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>" data-alert>
    <?= ui_icon($iconName, 'icon alert-icon') ?>
    <div>
        <?php if (!empty($title)): ?><h3 class="alert-title"><?= e($title) ?></h3><?php endif; ?>
        <p class="alert-message"><?= e($message ?? '') ?></p>
    </div>
    <?php if (!empty($dismissible)): ?><button class="alert-dismiss" type="button" aria-label="Dismiss notification" data-dismiss-alert><?= ui_icon('close') ?></button><?php endif; ?>
</div>
