<?php
$variant = in_array($variant ?? 'neutral', ['success','warning','danger','info','neutral','demo'], true) ? $variant : 'neutral';
?>
<span class="badge badge-<?= e($variant) ?>" <?= !empty($label) ? 'aria-label="' . e($label) . '"' : '' ?>><?= e($text ?? 'Status') ?></span>
