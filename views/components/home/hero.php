<section class="home-hero" aria-labelledby="home-title">
    <div class="home-hero-grid" aria-hidden="true"></div>
    <div class="container-wide home-hero-inner">
        <div class="home-hero-copy">
            <span class="home-eyebrow" data-home-reveal><span></span> GLOBAL MARKETS. MODERN TECHNOLOGY.</span>
            <h1 id="home-title"><span class="home-title-line"><span>Trade Global Markets.</span></span><span class="home-title-line home-title-accent"><span>One Powerful Platform.</span></span></h1>
            <p class="home-hero-description" data-home-reveal>Explore global markets through a modern demo trading environment designed for speed, clarity and control.</p>
            <div class="home-hero-actions" data-home-reveal>
                <?php render('components/button', ['href' => site_url('register'), 'label' => 'Open Demo Account', 'variant' => 'primary', 'size' => 'lg', 'icon' => 'arrow-up-right']); ?>
                <?php render('components/button', ['href' => site_url('markets'), 'label' => 'Explore Markets', 'variant' => 'outline', 'size' => 'lg', 'icon' => 'arrow-right']); ?>
            </div>
            <div class="home-hero-assurance" data-home-reveal><span class="assurance-mark"><?= ui_icon('shield') ?></span><span><?= e(site_setting('demo_disclaimer', 'Demo Trading Environment — No Real Funds Are Used.')) ?></span></div>
        </div>
        <div class="home-terminal-wrap" data-home-reveal>
            <div class="home-terminal-glow" aria-hidden="true"></div>
            <div class="home-terminal" aria-label="Illustrative trading interface preview using simulated demo price snapshots; no real market prices or orders.">
                <div class="terminal-topbar"><div class="terminal-brand"><span class="terminal-brand-mark">M</span><span><?= e($companyName) ?><small>WEB TRADING · PREVIEW</small></span></div><span class="terminal-demo-label"><i></i> DEMO DATA</span></div>
                <div class="terminal-layout">
                    <div class="terminal-chart-panel">
                        <div class="terminal-instrument" data-market-symbol="EURUSD" data-precision="5"><div><span class="terminal-symbol">EUR / USD</span><span class="terminal-market-state">Forex · Simulated quote</span></div><div class="terminal-quote"><span data-market-field="bid"><?= e($heroInstrument['bid'] ?? '1.08408') ?></span><small>Bid</small></div><div class="terminal-quote terminal-quote-ask"><span data-market-field="ask"><?= e($heroInstrument['ask'] ?? '1.08432') ?></span><small>Ask</small></div></div>
                        <div class="terminal-chart-legend"><span>Illustrative movement</span><span>1D&nbsp;&nbsp; 1W&nbsp;&nbsp; 1M</span></div>
                        <div class="terminal-chart" role="img" aria-label="Decorative example chart. It does not represent historical or live market movement.">
                            <svg viewBox="0 0 680 280" preserveAspectRatio="none" aria-hidden="true">
                                <defs><linearGradient id="home-chart-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#78d1cf" stop-opacity=".27"/><stop offset="1" stop-color="#78d1cf" stop-opacity="0"/></linearGradient></defs>
                                <path class="terminal-chart-fill" d="M0 214 C35 194 42 204 68 174 S112 171 139 189 S179 143 210 155 S249 186 282 146 S326 157 355 117 S395 143 423 127 S462 99 491 118 S530 71 555 87 S593 116 622 73 S657 67 680 44 V280 H0Z"/>
                                <path class="terminal-chart-line" d="M0 214 C35 194 42 204 68 174 S112 171 139 189 S179 143 210 155 S249 186 282 146 S326 157 355 117 S395 143 423 127 S462 99 491 118 S530 71 555 87 S593 116 622 73 S657 67 680 44"/>
                            </svg>
                            <span class="chart-axis chart-axis-top">1.08432</span><span class="chart-axis chart-axis-mid">1.08420</span><span class="chart-axis chart-axis-low">1.08408</span><span class="chart-demo-stamp">ILLUSTRATIVE ONLY</span>
                        </div>
                        <div class="terminal-time-labels"><span>08:00</span><span>10:00</span><span>12:00</span><span>14:00</span><span>16:00</span></div>
                    </div>
                    <aside class="terminal-watchlist" aria-label="Simulated demo market examples"><div class="terminal-watchlist-head"><span>WATCHLIST</span><span>DEMO DATA</span></div>
                        <?php foreach (array_slice($tickerInstruments, 0, 4) as $watchItem): ?><div class="terminal-watch-row" data-market-symbol="<?= e($watchItem['symbol']) ?>" data-precision="<?= e((string)$watchItem['precision']) ?>"><span><b><?= e($watchItem['symbol']) ?></b><small><?= e($watchItem['category']) ?></small></span><span><b data-market-field="bid"><?= e($watchItem['bid']) ?></b><small class="watch-static">Demo</small></span></div><?php endforeach; ?>
                        <div class="terminal-preview-note">Interface concept<br><strong>No orders are active</strong></div>
                    </aside>
                </div>
                <div class="terminal-statusbar"><span><i></i> Responsive web experience</span><span>Market snapshot · Demo</span></div>
            </div>
            <div class="hero-float-card hero-float-one" data-market-symbol="XAUUSD" data-precision="2" aria-hidden="true"><span>XAU / USD</span><strong data-market-field="bid"><?= e($goldInstrument['bid'] ?? '2,325.25') ?></strong><small>Simulated demo quote</small></div>
            <div class="hero-float-card hero-float-two" aria-hidden="true"><span><?= e($companyName) ?></span><strong>Virtual funds</strong><small>Practice environment</small></div>
        </div>
    </div>
    <div class="container-wide home-hero-foot"><span><?= e($companyTagline ?? 'Practice in a simulated trading environment') ?></span><span>Demo trading · No real funds</span></div>
</section>
