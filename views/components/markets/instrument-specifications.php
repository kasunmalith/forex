<section class="market-specifications" aria-labelledby="market-specifications-title">
    <div><span class="markets-kicker">QUOTE CONVENTIONS</span><h3 id="market-specifications-title">Instrument specifications</h3></div>
    <p>Values below reflect database configuration. Margin rates are reserved for future demo calculations and are not trading terms.</p>
    <details class="market-specification-list"><summary>View configured specifications <span>(<?= e((string) count($specificationInstruments)) ?> instruments)</span></summary>
        <div class="specification-table-wrap"><table class="specification-table"><thead><tr><th scope="col">Symbol</th><th scope="col">Category</th><th scope="col">Contract size</th><th scope="col">Precision</th><th scope="col">Demo spread</th><th scope="col">Configured margin rate</th></tr></thead><tbody>
        <?php foreach ($specificationInstruments as $instrument): ?><tr><th scope="row"><?= e($instrument['symbol']) ?></th><td><?= e($instrument['category_name']) ?></td><td><?= e(number_format((float) $instrument['contract_size'], 4, '.', ',')) ?></td><td><?= e((string) $instrument['precision_digits']) ?> digits</td><td><?= e($instrument['spread_formatted']) ?></td><td><?= e(number_format((float) $instrument['margin_rate'] * 100, 2)) ?>%</td></tr><?php endforeach; ?>
        </tbody></table></div>
    </details>
</section>
