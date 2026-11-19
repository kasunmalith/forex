<?php $tickerItems = $items ?? []; ?>
<div class="market-ticker" aria-label="Static simulated market ticker">
    <div class="ticker-track">
        <?php foreach ($tickerItems as $item): $direction = in_array($item['direction'] ?? 'neutral', ['positive','negative','neutral'], true) ? $item['direction'] : 'neutral'; ?>
            <span class="ticker-item"><span class="market-symbol"><?= e($item['symbol'] ?? '') ?></span><span class="price"><?= e($item['price'] ?? '') ?></span><span class="change text-<?= e($direction) ?>"><?= $direction === 'positive' ? '▲ ' : ($direction === 'negative' ? '▼ ' : '• ') ?><?= e($item['change'] ?? '') ?></span></span>
        <?php endforeach; ?>
    </div>
</div>
