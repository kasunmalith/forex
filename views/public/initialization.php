<?php require APP_ROOT . '/views/components/header.php'; ?>
<section class="hero" aria-labelledby="page-title">
    <div class="container-wide hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">A simulated trading environment</span>
            <h1 id="page-title">Global markets.<br>Modern technology.</h1>
            <p class="body-large">Explore a considered platform foundation for practicing with virtual funds. Market values shown here are illustrative demo data, not live prices.</p>
            <div class="hero-actions">
                <?php render('components/button', ['href' => site_url('register'), 'label' => 'Open Demo Account', 'variant' => 'primary', 'size' => 'lg', 'icon' => 'arrow-up-right']); ?>
                <?php render('components/button', ['href' => site_url('markets'), 'label' => 'Explore Markets', 'variant' => 'outline', 'size' => 'lg', 'icon' => 'arrow-right']); ?>
            </div>
            <p class="caption caption-spaced">Phase 03 design preview · No real funds are processed.</p>
        </div>
        <div class="hero-visual" aria-label="Illustrative demo market chart">
            <div class="hero-visual-frame">
                <div class="hero-market-head"><div><span class="market-symbol">EURUSD</span><span class="market-instrument-name">Demo illustration</span></div><span class="badge badge-demo">SIMULATED</span></div>
                <?php render('components/chart-placeholder', ['height' => 230]); ?>
                <p class="caption caption-small-spaced">Illustrative values only · Not live market data</p>
            </div>
        </div>
    </div>
</section>
<section class="section-compact">
    <div class="container-wide">
        <?php render('components/market-ticker', ['items' => [
            ['symbol' => 'EURUSD', 'price' => '1.08420', 'change' => '+0.24%', 'direction' => 'positive'],
            ['symbol' => 'XAUUSD', 'price' => '2,325.50', 'change' => '+0.31%', 'direction' => 'positive'],
            ['symbol' => 'US30', 'price' => '38,850.0', 'change' => '−0.12%', 'direction' => 'negative'],
            ['symbol' => 'BTCUSD', 'price' => '65,000.00', 'change' => '+0.82%', 'direction' => 'positive'],
        ]]); ?>
        <p class="caption caption-small-spaced">Design sample values are static and simulated. They do not reflect current market conditions.</p>
    </div>
</section>
<?php require APP_ROOT . '/views/components/footer.php'; ?>
