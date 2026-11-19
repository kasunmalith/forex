<div class="markets-table-wrap" tabindex="0" aria-label="Scrollable demo instrument table">
    <table class="markets-table" data-market-table>
        <thead><tr><th scope="col"><button type="button" data-sort="symbol">Instrument <span aria-hidden="true">↕</span></button></th><th scope="col">Bid</th><th scope="col">Ask</th><th scope="col"><button type="button" data-sort="spread">Spread <span aria-hidden="true">↕</span></button></th><th scope="col">Change</th><th scope="col">Change %</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
        <tbody>
        <?php foreach ($tableInstruments as $instrument): ?>
            <?php $aliases = match ($instrument['symbol']) { 'NAS100' => 'NASDAQ US TECH', 'SPX500' => 'S&P 500 SP500', 'GER40' => 'DAX GERMANY', 'UK100' => 'FTSE', 'US30' => 'DOW JONES', default => '' }; $searchText = strtolower($instrument['symbol'] . ' ' . $instrument['display_name'] . ' ' . ($instrument['base_currency'] ?? '') . ' ' . ($instrument['quote_currency'] ?? '') . ' ' . $aliases); ?>
            <tr data-instrument-row data-market-symbol="<?= e($instrument['symbol']) ?>" data-search="<?= e($searchText) ?>" data-symbol="<?= e(strtolower($instrument['symbol'])) ?>" data-spread="<?= e((string) $instrument['spread']) ?>" data-precision="<?= e((string) $instrument['precision_digits']) ?>" data-region="<?= e($instrument['region'] ?? '') ?>" data-pair-group="<?= e($instrument['pair_group'] ?? '') ?>">
                <td data-label="Instrument"><div class="markets-instrument"><span class="markets-symbol-mark" aria-hidden="true"><?= e(substr($instrument['symbol'], 0, 2)) ?></span><span><strong><?= e($instrument['symbol']) ?></strong><small><?= e($instrument['display_name']) ?></small></span></div></td>
                <td data-label="Bid" class="price-bid" data-market-field="bid"><?= e($instrument['bid_formatted']) ?></td><td data-label="Ask" class="price-ask" data-market-field="ask"><?= e($instrument['ask_formatted']) ?></td>
                <td data-label="Spread" data-market-field="spread"><?= e($instrument['spread_formatted']) ?><small class="unit-note">price difference</small></td>
                <td data-label="Change"><span class="change-neutral" data-market-field="change" aria-label="Demo session change">—</span></td>
                <td data-label="Change percent"><span class="change-neutral" data-market-field="change_percent" aria-label="Demo session change percentage">—</span></td>
                <td data-label="Action"><a class="markets-trade-link" href="<?= e(site_url('register')) ?>">Trade Demo <span aria-hidden="true">↗</span></a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($tableInstruments === []): ?><tr class="markets-no-results"><td colspan="7">No instruments match this search. Try a symbol such as EURUSD or a name such as Gold.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<p class="markets-table-note">Quotes and session changes are simulated demo market data. Spread is the configured absolute difference between ask and bid; values are not real market prices.</p>
