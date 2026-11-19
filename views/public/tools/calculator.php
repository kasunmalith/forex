<?php
require APP_ROOT . '/views/components/header.php';
$isForex = $calculatorSlug === 'forex-calculator';
$isMargin = $calculatorSlug === 'margin-calculator';
$isPnl = $calculatorSlug === 'profit-loss-calculator';
$isConverter = $calculatorSlug === 'currency-converter';
$endpoint = site_url('api/calculate/' . $calculatorSlug);
$first = $instruments[0] ?? null;
?>
<div class="public-page public-calculator-page">
    <div class="public-container"><nav class="public-breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(site_url()) ?>">Home</a><span>/</span><a href="<?= e(site_url('tools')) ?>">Tools</a><span>/</span><span aria-current="page"><?= e($calculatorName) ?></span></nav></div>
    <section class="calculator-hero"><div class="public-container"><span class="public-eyebrow"><i></i> EDUCATIONAL DEMO TOOL</span><h1><?= e($calculatorName) ?><span>.</span></h1><p><?= e($calculatorDescription) ?></p></div></section>
    <section class="calculator-section"><div class="public-container calculator-layout">
        <div class="calculator-card">
            <?php if (!$catalogueAvailable): ?><div class="calculator-error" role="status">Calculator instruments could not be loaded. Please try again later.</div><?php else: ?>
            <form class="calculator-form" method="post" action="<?= e($endpoint) ?>" data-calculator-form data-endpoint="<?= e($endpoint) ?>" data-kind="<?= e($calculatorSlug) ?>">
                <?= csrf_field() ?>
                <?php if ($isConverter): ?>
                    <div class="calculator-form-grid"><label class="calculator-field"><span>Amount</span><input name="amount" type="number" inputmode="decimal" min="0" max="1000000000000" step="any" value="100" required></label><label class="calculator-field"><span>From currency</span><select name="from_currency" required><?php foreach($currencies as $currency): ?><option value="<?= e($currency) ?>" <?= $currency==='EUR'?'selected':'' ?>><?= e($currency) ?></option><?php endforeach; ?></select></label><label class="calculator-field is-full"><span>To currency</span><select name="to_currency" required><?php foreach($currencies as $currency): ?><option value="<?= e($currency) ?>" <?= $currency==='USD'?'selected':'' ?>><?= e($currency) ?></option><?php endforeach; ?></select></label></div>
                <?php else: ?>
                    <div class="calculator-form-grid"><label class="calculator-field is-full"><span>Instrument</span><select name="symbol" data-instrument-select required><?php foreach($instruments as $instrument): ?><option value="<?= e($instrument['symbol']) ?>" data-price="<?= e((string)$instrument['ask']) ?>" data-margin="<?= e((string)$instrument['margin_rate']) ?>" data-digits="<?= e((string)$instrument['precision_digits']) ?>"><?= e($instrument['symbol'].' · '.$instrument['display_name']) ?></option><?php endforeach; ?></select></label>
                    <?php if($isForex): ?><label class="calculator-field"><span>Account currency</span><select name="account_currency" required><?php foreach($currencies as $currency): ?><option value="<?= e($currency) ?>" <?= $currency==='USD'?'selected':'' ?>><?= e($currency) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                    <?php if($isPnl): ?><label class="calculator-field"><span>Direction</span><select name="direction"><option value="buy">Buy</option><option value="sell">Sell</option></select></label><?php endif; ?>
                    <label class="calculator-field"><span><?= $isPnl?'Volume (lots)':'Trade size (lots)' ?></span><input name="lots" type="number" inputmode="decimal" min="0.00001" max="100" step="any" value="1" required></label>
                    <?php if($isPnl): ?><label class="calculator-field"><span>Open price</span><input name="open_price" type="number" inputmode="decimal" min="0.00000001" step="any" value="<?= e($first ? (string)$first['ask'] : '') ?>" required></label><label class="calculator-field"><span>Close price</span><input name="close_price" type="number" inputmode="decimal" min="0.00000001" step="any" value="<?= e($first ? (string)$first['ask'] : '') ?>" required></label>
                    <?php elseif($isForex||$isMargin): ?><label class="calculator-field"><span>Demo price <small>Editable estimate input</small></span><input name="price" type="number" inputmode="decimal" min="0.00000001" step="any" value="<?= e($first ? (string)$first['ask'] : '') ?>" required></label>
                    <?php endif; ?>
                    <?php if($isMargin): ?><label class="calculator-field is-full"><span>Configured demo margin rate</span><output data-margin-display><?= e($first ? number_format((float)$first['margin_rate']*100,2).'%' : 'Not available') ?></output><small>Loaded from the selected instrument’s database configuration.</small></label><?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="calculator-form-actions"><button class="btn btn-primary" type="submit">Calculate estimate <span aria-hidden="true">↗</span></button><button class="calculator-reset" type="reset">Reset values</button></div>
                <p class="calculator-form-error" role="alert" data-calculator-error hidden></p>
            </form><?php endif; ?>
        </div>
        <aside class="calculator-result" aria-live="polite" aria-atomic="true" data-calculator-result><span class="public-eyebrow">YOUR ESTIMATE</span><div class="calculator-result-placeholder"><span>ƒx</span><p>Enter your values and calculate to see an estimate.</p></div><div class="calculator-result-content" data-result-content hidden><small data-result-label>Estimated value</small><strong data-result-value>—</strong><span data-result-secondary></span><p data-result-explanation></p></div></aside>
    </div></section>
    <section class="calculator-explanation"><div class="public-container"><div><span class="public-eyebrow">HOW IT IS ESTIMATED</span><h2><?= $isForex?'Pip value and notional':($isMargin?'Configured margin estimate':($isPnl?'Price movement × position size':'Seeded quote midpoint')) ?></h2></div><p><?= $isForex?'Pip size is inferred from the pair’s quote currency (0.01 for JPY quotes and 0.0001 for other standard FX quotes). The configured contract size and a seeded midpoint conversion are used.':($isMargin?'This uses the selected database contract size, entered demo price and database margin rate. It is not a leverage claim or a trade preview.':($isPnl?'This simplified illustration multiplies the signed price change by the configured contract size and lots. It excludes costs and real execution effects.':'The rate is derived from configured bid/ask midpoints and may use more than one seeded pair. It is not current exchange information.')) ?></p></div></section>
    <section class="public-disclaimer"><div class="public-container"><span aria-hidden="true">ⓘ</span><p>For demonstration and education only. No real funds or orders are involved. Values use static seeded demo information and may differ from future platform calculations.</p></div></section>
</div>
<?php require APP_ROOT . '/views/components/footer.php'; ?>
