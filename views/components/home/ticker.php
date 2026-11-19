<section class="home-ticker-section" aria-label="Seeded demo market prices">
    <div class="container-wide home-ticker-shell">
        <div class="home-ticker-title"><span class="ticker-live-dot"></span><span>DEMO MARKET DATA</span><small>Server-simulated quotes</small></div>
        <div class="home-ticker-viewport" tabindex="0" aria-label="Scrollable simulated demo quotes"><div class="home-ticker-track">
            <?php foreach ($tickerInstruments as $item): $marketSlug = strtolower(str_replace(' ', '-', $item['category'])); ?><a class="home-ticker-item" data-market-symbol="<?= e($item['symbol']) ?>" data-precision="<?= e((string) $item['precision']) ?>" href="<?= e(site_url('markets/' . $marketSlug)) ?>" aria-label="Explore <?= e($item['name']) ?> demo market"><span class="ticker-symbol"><?= e($item['symbol']) ?></span><span class="ticker-price" data-market-field="bid"><?= e($item['bid']) ?></span><span class="ticker-movement">Change <b data-market-field="change">—</b></span><span class="ticker-category"><?= e($item['category']) ?> · simulated demo</span></a><?php endforeach; ?>
            <div class="home-ticker-copy" aria-hidden="true"><?php foreach ($tickerInstruments as $item): ?><span class="home-ticker-item" data-market-symbol="<?= e($item['symbol']) ?>" data-precision="<?= e((string) $item['precision']) ?>"><span class="ticker-symbol"><?= e($item['symbol']) ?></span><span class="ticker-price" data-market-field="bid"><?= e($item['bid']) ?></span><span class="ticker-movement">Change <b data-market-field="change">—</b></span><span class="ticker-category"><?= e($item['category']) ?> · simulated demo</span></span><?php endforeach; ?></div>
        </div></div>
        <a class="home-ticker-all" href="<?= e(site_url('markets')) ?>">All markets <?= ui_icon('arrow-right') ?></a>
    </div>
</section>
