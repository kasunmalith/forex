<?php if (empty($account)): ?>
    <section class="client-panel client-error-panel">
        <span class="client-eyebrow">DEMO ACCOUNT UNAVAILABLE</span>
        <h1>We couldn’t load your demo account.</h1>
        <p>Your profile is signed in, but there is no available demo account linked to it. Please contact support so the account relationship can be reviewed.</p>
        <a class="client-button client-button-secondary" href="<?= e(site_url('contact')) ?>">Contact Support</a>
    </section>
<?php else:
    $currency = (string) $account['currency'];
    $accountActive = $account['status'] === 'active';
    $money = static fn ($value, bool $signed = false): string => \App\Services\DashboardService::formatMoney($value, $currency, $signed);
?>
    <section class="client-dashboard-welcome">
        <div>
            <span class="client-eyebrow">DEMO ACCOUNT OVERVIEW</span>
            <h1>Welcome back, <?= e($user['first_name'] ?? 'there') ?>.</h1>
            <p>Here is an overview of your MultiForex demo account. Virtual funds only; no real funds are used.</p>
        </div>
        <div class="client-account-summary" aria-label="Demo account summary">
            <div><small>Account number</small><strong><?= e($account['account_number']) ?></strong></div>
            <div><small>Type</small><strong><?= e(ucwords(str_replace('_', ' ', (string) $account['account_type']))) ?></strong></div>
            <div><small>Currency</small><strong><?= e($currency) ?></strong></div>
            <div><small>Status</small><strong class="client-account-status<?= $accountActive ? '' : ' is-inactive' ?>"><?= e(ucfirst((string) $account['status'])) ?></strong></div>
        </div>
    </section>

    <?php if (empty($accountActive)): ?><div class="client-flash is-error" role="status">This demo account is <?= e((string) $account['status']) ?>. Trading actions are unavailable.</div><?php endif; ?>

    <section class="client-metrics-grid" aria-label="Demo account metrics">
        <?php render('client/components/metric',['label'=>'Virtual Balance','value'=>$metrics['balance'],'currency'=>$currency,'icon'=>'B','note'=>'Database account balance','metricKey'=>'balance']); ?>
        <?php render('client/components/metric',['label'=>'Demo Equity','value'=>$metrics['equity'],'currency'=>$currency,'icon'=>'E','note'=>'Balance plus valued open P/L','metricKey'=>'equity']); ?>
        <?php render('client/components/metric',['label'=>'Free Margin','value'=>$metrics['free_margin'],'currency'=>$currency,'icon'=>'F','note'=>'Available when no margin is in use','metricKey'=>'free_margin']); ?>
        <?php render('client/components/metric',['label'=>'Floating P/L','value'=>$metrics['floating_pl'],'currency'=>$currency,'icon'=>'↕','signed'=>true,'note'=>$metrics['floating_pl'] === null ? 'Awaiting valid demo pricing' : 'Open positions','metricKey'=>'floating_pl']); ?>
    </section>
    <?php $verifyStatus=$verification['status']??'not_started';$verifyLabels=['not_started'=>'Not Started','draft'=>'Draft','submitted'=>'Submitted','in_review'=>'Under Review','verified'=>'Verified','rejected'=>'Rejected','resubmission_required'=>'Action Required']; ?>
    <section class="client-panel dashboard-verification-card"><div><span class="client-eyebrow">DEMO PROFILE</span><h2>Identity Verification</h2><p><?=e($verifyLabels[$verifyStatus]??'Not Started')?> · Demo verification workflow</p></div><a class="client-button client-button-secondary" href="<?=e(client_url('verification'))?>"><?=in_array($verifyStatus,['verified','submitted','in_review'],true)?'View Status':'Complete Verification'?></a></section>
    <section class="client-panel dashboard-verification-card"><div><span class="client-eyebrow">CLIENT SERVICES</span><h2>Support & Notifications</h2><p><?=e((string)($supportCounts['open']??0))?> open tickets · <?=e((string)($supportCounts['unread']??0))?> unread support replies · <?=e((string)($notifications['unread']??0))?> unread notifications</p></div><a class="client-button client-button-secondary" href="<?=e(client_url('support'))?>">View Support</a><a class="client-text-link" href="<?=e(client_url('notifications'))?>">Notifications →</a></section>

    <div class="client-dashboard-grid">
        <div class="client-dashboard-column">
            <section class="client-panel client-performance-panel" aria-labelledby="performance-title">
                <div class="client-section-heading"><div><h2 id="performance-title">Account Performance</h2><p>Based on recorded demo activity</p></div><span class="client-demo-label">DEMO DATA</span></div>
                <div class="client-performance-chart" role="img" aria-label="<?= (int) $stats['total'] === 0 ? 'No demo trade history is available yet.' : 'Historical balance snapshots are not available for the recorded demo trades.' ?>">
                    <div class="client-chart-empty"><span aria-hidden="true">⌁</span>
                        <?php if ((int) $stats['total'] === 0): ?>
                            <strong>No performance history yet</strong><small>Your account will show a history when demo activity is recorded.</small>
                        <?php else: ?>
                            <strong>Equity history is not available</strong><small><?= e((string) $stats['total']) ?> completed demo trades are recorded; balance snapshots are not stored.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="client-chart-foot"><span><?= e((string) $stats['total']) ?> completed trades</span><span>No generated performance data</span></div>
            </section>

            <section class="client-panel" aria-labelledby="positions-preview-title" data-client-live-list="positions">
                <div class="client-section-heading"><div><h2 id="positions-preview-title">Open Positions</h2><p><?= (int) $metrics['open_positions'] ?> currently open</p></div><a class="client-text-link" href="<?= e(client_url('positions')) ?>">View positions <span aria-hidden="true">→</span></a></div>
                <?php if ($positions === []): ?>
                    <?php render('client/components/empty-state',['icon'=>'⌁','title'=>'No open positions','message'=>'Your open demo trades will appear here when trading is introduced.','actionHref'=>site_url('markets'),'actionLabel'=>'Explore Markets']); ?>
                <?php else: ?>
                    <div class="client-table-wrap"><table class="client-table"><thead><tr><th>Instrument</th><th>Side</th><th class="numeric">Volume</th><th class="numeric">Open Price</th><th class="numeric">Demo Price</th><th class="numeric">P/L</th></tr></thead><tbody>
                    <?php foreach ($positions as $position): $pl = $position['position_pl'] ?? null; ?>
                        <tr data-instrument-id="<?= e((string) $position['instrument_id']) ?>">
                            <td><span class="client-instrument"><span class="client-instrument-mark"><?= e(substr((string) $position['symbol'],0,2)) ?></span><span class="client-instrument-copy"><strong><?= e($position['symbol']) ?></strong><small><?= e($position['display_name']) ?></small></span></span></td>
                            <td><span class="client-side-pill is-<?= e($position['direction']) ?>"><?= e(strtoupper((string) $position['direction'])) ?></span></td>
                            <td class="numeric"><?= e((string) $position['volume']) ?></td><td class="numeric"><?= e((string) $position['open_price']) ?></td>
                            <td class="numeric"><?= $position['current_price'] === null ? '—' : e((string) $position['current_price']) ?></td>
                            <td class="numeric"><?= $pl === null ? '—' : e($money((string) $pl,true)) ?></td>
                        </tr>
                    <?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </section>

            <section class="client-panel" aria-labelledby="watchlist-preview-title">
                <div class="client-section-heading"><div><h2 id="watchlist-preview-title">Watchlist</h2><p><?= $watchlistSaved ? 'Your saved demo instruments' : 'Popular demo instruments · not saved' ?></p></div><a class="client-text-link" href="<?= e(client_url('watchlist')) ?>">Manage watchlist <span aria-hidden="true">→</span></a></div>
                <?php if ($watchlist === []): ?>
                    <?php render('client/components/empty-state',['icon'=>'☆','title'=>'No instruments available','message'=>'Demo instruments will appear here when configured.','actionHref'=>site_url('markets'),'actionLabel'=>'Browse Markets']); ?>
                <?php else: ?>
                    <div class="client-table-wrap"><table class="client-table"><thead><tr><th>Instrument</th><th>Market</th><th class="numeric">Demo Price</th><th>Data</th></tr></thead><tbody>
                    <?php foreach ($watchlist as $item): ?>
                        <tr data-instrument-id="<?= e((string) $item['instrument_id']) ?>" data-market-symbol="<?= e($item['symbol']) ?>" data-precision="<?= e((string)$item['precision_digits']) ?>">
                            <td><span class="client-instrument"><span class="client-instrument-mark"><?= e(substr((string) $item['symbol'],0,2)) ?></span><span class="client-instrument-copy"><strong><?= e($item['symbol']) ?></strong><small><?= e($item['display_name']) ?></small></span></span></td>
                            <td><?= e($item['category']) ?></td><td class="numeric" data-market-field="mid"><?= e(number_format((float) $item['demo_price'], (int) $item['precision_digits'], '.', ',')) ?></td><td><span class="client-demo-label">SIMULATED</span></td>
                        </tr>
                    <?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="client-dashboard-column" aria-label="Account tools and recent activity">
            <section class="client-panel" aria-labelledby="account-details-title">
                <div class="client-section-heading"><div><h2 id="account-details-title">Account Details</h2><p>Virtual account information</p></div></div>
                <dl class="client-account-details">
                    <div><dt>Account number</dt><dd><?= e($account['account_number']) ?></dd></div>
                    <div><dt>Account type</dt><dd><?= e(ucwords(str_replace('_',' ',(string) $account['account_type']))) ?></dd></div>
                    <div><dt>Base currency</dt><dd><?= e($currency) ?></dd></div>
                    <div><dt>Account status</dt><dd><?= e(ucfirst((string) $account['status'])) ?></dd></div>
                    <div><dt>Created</dt><dd><?= e(\App\Services\DashboardService::formatDate($account['created_at'])) ?></dd></div>
                    <div><dt>Used margin</dt><dd><?= e($metrics['used_margin'] === null ? 'Not calculated' : $money($metrics['used_margin'])) ?></dd></div>
                    <div><dt>Realized P/L</dt><dd><?= e($money($metrics['realized_pl'],true)) ?></dd></div>
                    <div><dt>Margin level</dt><dd><?= e($metrics['margin_level'] === null ? 'Not applicable' : (string) $metrics['margin_level'] . '%') ?></dd></div>
                </dl>
            </section>

            <section class="client-panel" aria-labelledby="quick-actions-title">
                <div class="client-section-heading"><div><h2 id="quick-actions-title">Quick Actions</h2><p>Useful account links</p></div></div>
                <div class="client-quick-actions">
                    <?php if ($accountActive): ?><a class="client-quick-action" href="<?= e(client_url('trade')) ?>"><span>Open Trading Terminal</span><span aria-hidden="true">→</span></a><?php else: ?><span class="client-quick-action" aria-disabled="true"><span>Trading unavailable</span><span aria-hidden="true">·</span></span><?php endif; ?>
                    <a class="client-quick-action" href="<?= e(site_url('markets')) ?>"><span>Explore Markets</span><span aria-hidden="true">→</span></a>
                    <a class="client-quick-action" href="<?= e(client_url('history')) ?>"><span>View Trade History</span><span aria-hidden="true">→</span></a>
                    <a class="client-quick-action" href="<?= e(client_url('profile')) ?>"><span>Manage Profile</span><span aria-hidden="true">→</span></a>
                    <a class="client-quick-action" href="<?= e(site_url('contact')) ?>"><span>Contact Support</span><span aria-hidden="true">→</span></a>
                </div>
            </section>

            <section class="client-panel" aria-labelledby="trading-stats-title">
                <div class="client-section-heading"><div><h2 id="trading-stats-title">Demo Trading Summary</h2><p>Recorded closed trades</p></div></div>
                <div class="client-stats-row"><div><strong><?= e((string) $stats['total']) ?></strong><span>Total trades</span></div><div><strong><?= e((string) $stats['wins']) ?></strong><span>Winners</span></div><div><strong><?= e((string) $stats['losses']) ?></strong><span>Losers</span></div></div>
                <div class="client-section-heading"><div><h2>Recent Orders</h2><p>Latest account requests</p></div><a class="client-text-link" href="<?= e(client_url('orders')) ?>">All <span aria-hidden="true">→</span></a></div>
                <?php if ($orders === []): ?>
                    <?php render('client/components/empty-state',['icon'=>'↗','title'=>'No orders yet','message'=>'Demo order records will appear here when the trading workflow is available.']); ?>
                <?php else: ?>
                    <div class="client-recent-list"><?php foreach ($orders as $order): ?><div class="client-recent-row"><span><strong><?= e($order['symbol']) ?></strong><small><?= e(ucwords(str_replace('_',' ',(string) $order['order_type']))) ?> · <?= e(strtoupper((string) $order['direction'])) ?></small></span><span class="client-status-pill is-<?= e($order['status']) ?>"><?= e($order['status'] === 'executed' ? 'Filled' : ucfirst((string) $order['status'])) ?></span></div><?php endforeach; ?></div>
                <?php endif; ?>
            </section>
        </aside>
    </div>

    <section class="client-panel client-dashboard-trades" aria-labelledby="recent-trades-title">
        <div class="client-section-heading"><div><h2 id="recent-trades-title">Recent Trades</h2><p>Completed demo activity</p></div><a class="client-text-link" href="<?= e(client_url('history')) ?>">View history <span aria-hidden="true">→</span></a></div>
        <?php if ($trades === []): ?>
            <?php render('client/components/empty-state',['icon'=>'◷','title'=>'No completed demo trades yet','message'=>'Completed demo trade history will appear here after trading features are introduced.']); ?>
        <?php else: ?>
            <div class="client-table-wrap"><table class="client-table"><thead><tr><th>Instrument</th><th>Side</th><th class="numeric">Volume</th><th class="numeric">Entry</th><th class="numeric">Exit</th><th class="numeric">Demo P/L</th><th>Closed</th></tr></thead><tbody>
            <?php foreach ($trades as $trade): $pnl=(string) $trade['profit_loss']; ?>
                <tr><td><span class="client-instrument-copy"><strong><?= e($trade['symbol']) ?></strong><small><?= e($trade['display_name']) ?></small></span></td><td><span class="client-side-pill is-<?= e($trade['direction']) ?>"><?= e(strtoupper((string) $trade['direction'])) ?></span></td><td class="numeric"><?= e((string) $trade['volume']) ?></td><td class="numeric"><?= e((string) $trade['open_price']) ?></td><td class="numeric"><?= e((string) $trade['close_price']) ?></td><td class="numeric"><?= e($money($pnl,true)) ?></td><td><?= e(\App\Services\DashboardService::formatDate($trade['closed_at'])) ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
