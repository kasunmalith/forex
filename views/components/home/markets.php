<?php
$categoryArt = [
    'forex' => ['code' => 'FX', 'line' => 'EUR · USD · JPY', 'featured' => true],
    'metals' => ['code' => 'Au', 'line' => 'GOLD · SILVER'],
    'indices' => ['code' => 'IX', 'line' => 'GLOBAL INDEXES'],
    'commodities' => ['code' => 'CM', 'line' => 'ENERGY · MATERIALS'],
    'stocks' => ['code' => 'EQ', 'line' => 'EQUITY EXAMPLES'],
    'digital-assets' => ['code' => 'DA', 'line' => 'DIGITAL ASSETS'],
];
$leadCategory = null;
foreach ($categories as $category) { if (($category['slug'] ?? '') === 'forex') { $leadCategory = $category; break; } }
$otherCategories = array_values(array_filter($categories, static fn ($category): bool => ($category['slug'] ?? '') !== 'forex'));
?>
<section class="home-section home-markets-section" id="home-markets" aria-labelledby="markets-title">
    <div class="container-wide">
        <div class="home-section-heading" data-home-reveal><div><span class="home-kicker">ONE VIEW · SIX CATEGORIES</span><h2 id="markets-title">Explore global markets<span class="heading-period">.</span></h2></div><p>Move across market groups with a single, clear view. Quotes in the preview use simulated demo market data.</p></div>
        <div class="market-editorial-grid">
            <?php if ($leadCategory !== null): $art = $categoryArt[$leadCategory['slug']] ?? ['code' => 'FX', 'line' => 'DEMO MARKETS']; ?>
                <a class="market-editorial-feature" href="<?= e(site_url('markets/forex')) ?>" data-home-reveal>
                    <div class="market-feature-art"><span class="market-art-orbit market-art-orbit-a"></span><span class="market-art-orbit market-art-orbit-b"></span><span class="market-art-code"><?= e($art['code']) ?></span><div class="market-art-pair"><span>EUR</span><i></i><span>USD</span></div><div class="market-art-caption">CURRENCY MARKETS<br>DEMO CATALOGUE</div></div>
                    <div class="market-feature-copy"><span class="market-count"><?= e((string) ($leadCategory['instrument_count'] ?? 0)) ?> INSTRUMENTS</span><h3><?= e($leadCategory['name']) ?></h3><p><?= e($leadCategory['description'] ?: 'Explore seeded currency pair examples in the demo catalogue.') ?></p><span class="home-text-link">Explore forex <?= ui_icon('arrow-up-right') ?></span></div>
                </a>
            <?php endif; ?>
            <div class="market-editorial-list">
                <?php foreach ($otherCategories as $index => $category): $art = $categoryArt[$category['slug'] ?? ''] ?? ['code' => 'MK', 'line' => 'MARKET GROUP']; ?>
                    <a class="market-editorial-row" href="<?= e(site_url('markets/' . $category['slug'])) ?>" data-home-reveal><span class="market-row-number">0<?= e((string) ($index + 2)) ?></span><span class="market-row-code"><?= e($art['code']) ?></span><span class="market-row-copy"><strong><?= e($category['name']) ?></strong><small><?= e($category['description'] ?: $art['line']) ?></small></span><span class="market-row-count"><?= e((string) ($category['instrument_count'] ?? 0)) ?><small>examples</small></span><?= ui_icon('arrow-up-right', 'icon market-row-arrow') ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="home-section-footnote"><span><?= ui_icon('info') ?></span>Category availability reflects the current demo catalogue. Some groups are reserved for later phases.</div>
    </div>
</section>
