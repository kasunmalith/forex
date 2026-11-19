<?php $direction = in_array($direction ?? 'neutral', ['positive','negative','neutral'], true) ? $direction : 'neutral'; ?>
<article class="card card-market">
    <div class="market-card-top"><div><span class="market-symbol"><?= e($symbol ?? 'EURUSD') ?></span><span class="market-instrument-name"><?= e($name ?? 'Demo instrument') ?></span></div><span class="market-visual-mark"><?= ui_icon('trend-up') ?></span></div>
    <div><p class="market-price price"><?= e($price ?? '—') ?></p><span class="market-change text-<?= e($direction) ?>"><?= $direction === 'positive' ? '▲ ' : ($direction === 'negative' ? '▼ ' : '• ') ?><?= e($change ?? '0.00%') ?></span></div>
    <?php if (isset($bid) || isset($ask)): ?><div class="market-card-bottom"><span class="market-quote">Bid <?= e($bid ?? '—') ?></span><span class="market-quote">Ask <?= e($ask ?? '—') ?></span></div><?php endif; ?>
    <span class="badge badge-demo">SIMULATED</span>
</article>
