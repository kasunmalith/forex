<?php
$brandName = $companyName ?? site_setting('company_name', 'MultiForex');
$riskWarning = site_setting('risk_warning', 'Prices and account values in this demo environment are simulated and are not live market data or real funds.');
$demoDisclaimer = site_setting('demo_disclaimer', 'Demo Trading Platform - No Real Funds Are Used.');
?>
</main>
<footer class="site-footer">
    <section class="footer-risk" aria-labelledby="risk-note-title">
        <div class="container-wide footer-risk-inner">
            <?= ui_icon('info') ?>
            <div>
                <h2 id="risk-note-title">Demo Trading Platform — No real funds are processed.</h2>
                <p><?= e($demoDisclaimer) ?> <?= e($riskWarning) ?></p>
            </div>
        </div>
    </section>
    <div class="container-wide footer-main">
        <div class="footer-brand">
            <a class="brand" href="<?= e(site_url()) ?>" aria-label="<?= e($brandName) ?> home"><span class="brand-mark" aria-hidden="true">M</span><span><?= e($brandName) ?></span></a>
            <p><?= e(site_setting('company_tagline','A simulated trading environment built for practice with virtual funds and illustrative market values.')) ?></p>
            <a class="text-link" href="<?= e(site_url('company')) ?>">About the platform <?= ui_icon('arrow-right') ?></a>
        </div>
        <div class="footer-column"><h3>Markets</h3><div class="footer-links">
            <a href="<?= e(site_url('markets')) ?>">All Markets</a><a href="<?= e(site_url('markets/forex')) ?>">Forex</a><a href="<?= e(site_url('markets/metals')) ?>">Metals</a><a href="<?= e(site_url('markets/indices')) ?>">Indices</a><a href="<?= e(site_url('markets/commodities')) ?>">Commodities</a><a href="<?= e(site_url('markets/stocks')) ?>">Stocks</a><a href="<?= e(site_url('markets/digital-assets')) ?>">Digital Assets</a>
        </div></div>
        <div class="footer-column"><h3>Trading</h3><div class="footer-links">
            <a href="<?= e(site_url('platform')) ?>">Platforms</a><a href="<?= e(site_url('accounts')) ?>">Demo Accounts</a><a href="<?= e(site_url('tools')) ?>">Tools</a><a href="<?= e(site_url('login')) ?>">Login</a>
        </div></div>
        <div class="footer-column"><h3>Education</h3><div class="footer-links">
            <a href="<?= e(site_url('education')) ?>">Learning Centre</a><a href="<?= e(site_url('articles')) ?>">Articles</a><a href="<?= e(site_url('market-news')) ?>">Market News</a>
        </div></div>
        <div class="footer-column"><h3>Company</h3><div class="footer-links">
            <a href="<?= e(site_url('company')) ?>">Company</a><a href="<?= e(site_url('about')) ?>">About</a><a href="<?= e(site_url('security')) ?>">Security</a>
        </div></div>
        <div class="footer-column"><h3>Support</h3><div class="footer-links">
            <a href="<?= e(site_url('contact')) ?>">Contact</a><a href="<?= e(site_url('faq')) ?>">FAQ</a>
        </div></div>
        <div class="footer-column"><h3>Legal</h3><div class="footer-links">
            <a href="<?= e(site_url('privacy')) ?>">Privacy</a><a href="<?= e(site_url('terms')) ?>">Terms</a><a href="<?= e(site_url('risk-disclosure')) ?>">Risk Disclosure</a>
        </div></div>
    </div>
    <div class="container-wide footer-bottom">
        <p>© <?= e(date('Y')) ?> <?= e($brandName) ?> · <?= e(site_setting('footer_copyright','Demo environment')) ?></p>
        <div class="footer-legal"><span>Prices and balances are simulated.</span><a href="<?= e(site_url('risk-disclosure')) ?>">Risk Disclosure</a></div>
    </div>
    <div class="developer-credit">Software Development by <a href="https://www.energeticedge.lk/" target="_blank" rel="noopener noreferrer">Energetic Edge (Pvt) Ltd</a></div>
</footer>
</div>
</body>
</html>
