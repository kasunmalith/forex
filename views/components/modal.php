<dialog class="modal" id="<?= e($id ?? 'dialog') ?>" aria-labelledby="<?= e(($id ?? 'dialog') . '-title') ?>">
    <div class="modal-content">
        <div class="modal-header"><div><span class="eyebrow"><?= e($eyebrow ?? 'Information') ?></span><h2 id="<?= e(($id ?? 'dialog') . '-title') ?>"><?= e($title ?? '') ?></h2></div><button class="modal-close" type="button" aria-label="Close dialog" data-modal-close><?= ui_icon('close') ?></button></div>
        <div class="modal-body"><?= $body ?? '<p>' . e($message ?? '') . '</p>' /* Trusted server-rendered component markup only. */ ?></div>
        <div class="modal-actions"><button class="btn btn-secondary" type="button" data-modal-close>Close</button><?php if (!empty($confirmLabel)): ?><button class="btn btn-primary" type="button" data-modal-confirm><?= e($confirmLabel) ?></button><?php endif; ?></div>
    </div>
</dialog>
