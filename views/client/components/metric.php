<?php
$metricValue = !empty($plain)
    ? (string) $value
    : ($value === null
        ? 'Not calculated'
        : \App\Services\DashboardService::formatMoney($value, $currency ?? 'USD', !empty($signed)));
?>
<article class="client-metric<?= !empty($tone) ? ' is-' . e($tone) : '' ?>"<?= !empty($metricKey) ? ' data-client-metric="' . e($metricKey) . '"' : '' ?>>
    <span class="client-metric-label"><i aria-hidden="true"><?= e($icon ?? '•') ?></i><?= e($label) ?></span>
    <strong class="client-metric-value"<?= !empty($metricKey) ? ' data-client-metric-value="' . e($metricKey) . '"' : '' ?>><?= e($metricValue) ?></strong>
    <small class="client-metric-note"><?= e($note ?? '') ?></small>
</article>
