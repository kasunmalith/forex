<?php
$name = (string) ($name ?? 'field');
$id = (string) ($id ?? $name);
$type = (string) ($type ?? 'text');
$label = (string) ($label ?? ucfirst(str_replace('_', ' ', $name)));
$required = !empty($required);
$fieldClass = 'field-control' . (!empty($error) ? ' is-invalid' : '') . (!empty($success) ? ' is-valid' : '');
$descriptionId = !empty($error) ? $id . '-error' : (!empty($success) ? $id . '-success' : (!empty($help) ? $id . '-help' : ''));
$attributes = ($required ? ' required aria-required="true"' : '') . (!empty($disabled) ? ' disabled' : '') . (!empty($autocomplete) ? ' autocomplete="' . e($autocomplete) . '"' : '') . (!empty($error) ? ' aria-invalid="true"' : '') . ($descriptionId !== '' ? ' aria-describedby="' . e($descriptionId) . '"' : '');
?>
<div class="form-field <?= !empty($fullWidth) ? 'is-full' : '' ?>">
    <?php if (!in_array($type, ['checkbox','radio','toggle'], true)): ?>
        <div class="field-label-row"><label for="<?= e($id) ?>"><?= e($label) ?><?= $required ? ' <span class="required-mark" aria-hidden="true">*</span>' : '' ?></label><?php if (!empty($labelAside)): ?><span class="caption"><?= e($labelAside) ?></span><?php endif; ?></div>
    <?php endif; ?>
    <?php if (in_array($type, ['checkbox','radio','toggle'], true)): ?>
        <label class="choice-control <?= $type === 'toggle' ? 'is-toggle' : '' ?>" for="<?= e($id) ?>"><input id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= $type === 'toggle' ? 'checkbox' : e($type) ?>" value="<?= e($value ?? '1') ?>" <?= !empty($checked) ? 'checked' : '' ?><?= $attributes ?>><span><?= e($label) ?><?= $required ? ' <span class="required-mark" aria-hidden="true">*</span>' : '' ?></span></label>
    <?php elseif ($type === 'textarea'): ?>
        <textarea class="<?= e($fieldClass) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" placeholder="<?= e($placeholder ?? '') ?>"<?= $attributes ?>><?= e($value ?? '') ?></textarea>
    <?php elseif ($type === 'select'): ?>
        <select class="<?= e($fieldClass) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attributes ?>>
            <?php if (!empty($placeholder)): ?><option value="" disabled <?= empty($value) ? 'selected' : '' ?>><?= e($placeholder) ?></option><?php endif; ?>
            <?php foreach (($options ?? []) as $optionValue => $optionLabel): ?><option value="<?= e($optionValue) ?>" <?= (string) ($value ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option><?php endforeach; ?>
        </select>
    <?php elseif ($type === 'password'): ?>
        <div class="password-control"><input class="<?= e($fieldClass) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" type="password" value="<?= e($value ?? '') ?>" placeholder="<?= e($placeholder ?? '') ?>"<?= $attributes ?>><button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle="<?= e($id) ?>">Show</button></div>
    <?php else: ?>
        <?php $safeType = in_array($type, ['text','email','number','tel','date','file'], true) ? $type : 'text'; ?>
        <input class="<?= e($fieldClass) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= e($safeType) ?>" value="<?= $safeType === 'file' ? '' : e($value ?? '') ?>" placeholder="<?= e($placeholder ?? '') ?>"<?= $attributes ?> <?= $safeType === 'number' ? 'step="any"' : '' ?>>
    <?php endif; ?>
    <?php if (!empty($error)): ?><p class="field-error" id="<?= e($id) ?>-error"><?= e($error) ?></p><?php elseif (!empty($success)): ?><p class="field-success" id="<?= e($id) ?>-success"><?= e($success) ?></p><?php elseif (!empty($help)): ?><p class="field-help" id="<?= e($id) ?>-help"><?= e($help) ?></p><?php endif; ?>
</div>
