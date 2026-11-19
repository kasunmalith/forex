<?php
$instruments = $instruments ?? [];
$selectedInstrument = $selectedInstrument ?? ($instruments[0] ?? []);
$account = $account ?? null;
$metrics = $metrics ?? null;
$positions = $positions ?? [];
$orders = $orders ?? [];
$trades = $trades ?? [];
$selectedSymbol = (string) ($selectedInstrument['symbol'] ?? '');
$selectedPrecision = (int) ($selectedInstrument['precision_digits'] ?? 5);
$selectedBid = \App\Services\DashboardService::formatPrice((string) ($selectedInstrument['bid'] ?? ''), $selectedPrecision);
$selectedAsk = \App\Services\DashboardService::formatPrice((string) ($selectedInstrument['ask'] ?? ''), $selectedPrecision);
$selectedSpread = \App\Services\DashboardService::formatPrice((string) ($selectedInstrument['spread'] ?? ''), $selectedPrecision);
$marginRatePercent = isset($selectedInstrument['margin_rate'])
    ? rtrim(rtrim(number_format((float) $selectedInstrument['margin_rate'] * 100, 2, '.', ''), '0'), '.') . '%'
    : '—';
$categories = array_values(array_unique(array_column($instruments, 'category')));
?>
<section class="trading-terminal" data-trading-terminal
         data-initial-symbol="<?= e($selectedSymbol) ?>"
         data-quotes-url="<?= e(api_url('market/quotes')) ?>"
         data-candles-url="<?= e(api_url('market/candles')) ?>"
         data-order-url="<?= e(api_url('trading/order')) ?>"
         data-positions-url="<?= e(api_url('trading/positions')) ?>"
         data-orders-url="<?= e(api_url('trading/orders')) ?>"
         data-state-url="<?= e(api_url('trading/state')) ?>"
         data-close-url="<?= e(api_url('trading/position/close')) ?>"
         data-modify-url="<?= e(api_url('trading/position/modify')) ?>"
         data-csrf="<?= e(generate_csrf_token()) ?>"
         data-volume-min="<?= e((string)config('DEMO_TRADE_VOLUME_MIN')) ?>"
         data-volume-step="<?= e((string)config('DEMO_TRADE_VOLUME_STEP')) ?>"
         data-volume-max="<?= e((string)config('DEMO_TRADE_VOLUME_MAX')) ?>"
         data-global-trading="<?= !empty($tradingSettings['demo_trading_enabled'])?'1':'0' ?>"
         data-global-market-orders="<?= !empty($tradingSettings['market_orders_enabled'])?'1':'0' ?>"
         data-global-market-data="<?= !empty($tradingSettings['demo_market_data_enabled'])?'1':'0' ?>"
         data-global-max-volume="<?= e((string)($tradingSettings['max_order_volume']??config('DEMO_TRADE_VOLUME_MAX'))) ?>"
         data-client-order-id="<?= e(bin2hex(random_bytes(16))) ?>">
    <header class="terminal-account-bar">
        <div class="terminal-identity">
            <span class="terminal-brand-mark" aria-hidden="true">M</span>
            <span><strong>MultiForex</strong><small>DEMO TERMINAL</small></span>
            <span class="terminal-demo-pill">DEMO</span>
            <span class="terminal-virtual-note">Virtual funds only · No real funds are used</span>
        </div>
        <div class="terminal-account-select" aria-label="Current demo account">
            <span class="terminal-status-dot" aria-hidden="true"></span>
            <span><small><?= e(ucwords(str_replace('_', ' ', (string) ($account['account_type'] ?? 'Demo account')))) ?></small><strong><?= e((string) ($account['account_number'] ?? 'Account unavailable')) ?></strong></span>
            <span class="terminal-account-currency"><?= e((string) ($account['currency'] ?? '—')) ?></span>
        </div>
        <div class="terminal-account-metrics" aria-label="Demo account metrics">
            <span><small>Balance</small><strong data-account-metric="balance"><?= $metrics ? e(\App\Services\DashboardService::formatMoney($metrics['balance'], $metrics['currency'])) : '—' ?></strong></span>
            <span><small>Equity</small><strong data-account-metric="equity"><?= $metrics && $metrics['equity'] !== null ? e(\App\Services\DashboardService::formatMoney($metrics['equity'], $metrics['currency'])) : '—' ?></strong></span>
            <span><small>Used margin</small><strong data-account-metric="used_margin"><?= $metrics && $metrics['used_margin'] !== null ? e(\App\Services\DashboardService::formatMoney($metrics['used_margin'], $metrics['currency'])) : '—' ?></strong></span>
            <span><small>Free margin</small><strong data-account-metric="free_margin"><?= $metrics && $metrics['free_margin'] !== null ? e(\App\Services\DashboardService::formatMoney($metrics['free_margin'], $metrics['currency'])) : '—' ?></strong></span>
            <span><small>Floating P/L</small><strong data-account-metric="floating_pl"><?= $metrics && $metrics['floating_pl'] !== null ? e(\App\Services\DashboardService::formatMoney($metrics['floating_pl'], $metrics['currency'], true)) : '—' ?></strong></span>
            <span><small>Margin level</small><strong data-account-metric="margin_level"><?= $metrics && $metrics['margin_level'] !== null ? e(number_format((float)$metrics['margin_level'],2,'.',',').'%') : '—' ?></strong></span>
        </div>
        <a class="terminal-dashboard-link" href="<?= e(client_url('dashboard')) ?>">Dashboard <span aria-hidden="true">↗</span></a>
    </header>

    <nav class="terminal-mobile-nav" aria-label="Trading workspace panels">
        <button type="button" data-terminal-mobile-panel="watchlist" aria-controls="terminal-watchlist">Watchlist</button>
        <button type="button" data-terminal-mobile-panel="trade" aria-controls="terminal-ticket">Trade</button>
        <button type="button" data-terminal-mobile-tab="positions" aria-controls="terminal-bottom-panel">Positions</button>
        <button type="button" data-terminal-mobile-tab="orders" aria-controls="terminal-bottom-panel">Orders</button>
        <button type="button" data-terminal-mobile-tab="history" aria-controls="terminal-bottom-panel">History</button>
    </nav>

    <div class="terminal-workspace-grid">
        <aside class="terminal-panel terminal-watchlist" id="terminal-watchlist" data-terminal-watchlist aria-label="Instrument watchlist">
            <div class="terminal-panel-heading">
                <div><span class="terminal-overline">MARKETS</span><h2>Watchlist</h2></div>
                <button class="terminal-icon-button terminal-mobile-close" type="button" data-terminal-close-panel aria-label="Close watchlist">×</button>
                <span class="terminal-count"><?= e((string) count($instruments)) ?></span>
            </div>
            <label class="terminal-search"><span aria-hidden="true">⌕</span><span class="visually-hidden">Search instruments</span><input type="search" placeholder="Search symbol or name" autocomplete="off" data-terminal-search><kbd>/</kbd></label>
            <div class="terminal-category-filters" role="group" aria-label="Filter instruments by category">
                <button type="button" class="is-active" data-terminal-category="all" aria-pressed="true">All</button>
                <button type="button" data-terminal-category="favorites" aria-pressed="false">★</button>
                <?php foreach ($categories as $category): ?><button type="button" data-terminal-category="<?= e(strtolower((string) $category)) ?>" aria-pressed="false"><?= e((string) $category) ?></button><?php endforeach; ?>
            </div>
            <div class="terminal-quote-label"><span>INSTRUMENT</span><span>DEMO BID / ASK</span></div>
            <div class="terminal-instrument-list" data-terminal-instrument-list>
                <?php foreach ($instruments as $instrument): $precision = (int) $instrument['precision_digits']; $isFavorite = (int) $instrument['is_favorite'] === 1; ?>
                    <div class="terminal-instrument-row<?= $instrument['symbol'] === $selectedSymbol ? ' is-selected' : '' ?>"
                         data-terminal-row data-symbol="<?= e($instrument['symbol']) ?>" data-category="<?= e(strtolower((string) $instrument['category'])) ?>"
                         data-instrument-id="<?= e((string) $instrument['id']) ?>" data-name="<?= e(strtolower((string) $instrument['display_name'])) ?>" data-display-name="<?= e((string) $instrument['display_name']) ?>" data-category-name="<?= e((string) $instrument['category']) ?>" data-bid="<?= e((string) $instrument['bid']) ?>" data-ask="<?= e((string) $instrument['ask']) ?>" data-spread="<?= e((string) $instrument['spread']) ?>" data-precision="<?= e((string) $instrument['precision_digits']) ?>" data-quote-currency="<?= e((string) ($instrument['quote_currency'] ?? '')) ?>" data-contract-size="<?= e((string) $instrument['contract_size']) ?>" data-margin-rate="<?= e((string) $instrument['margin_rate']) ?>" data-volume-min="<?= e((string)$instrument['min_volume']) ?>" data-volume-max="<?= e((string)$instrument['max_volume']) ?>" data-volume-step="<?= e((string)$instrument['volume_step']) ?>" data-instrument-trading="<?= (int)$instrument['trading_enabled'] ?>" data-instrument-market-data="<?= (int)$instrument['market_data_enabled'] ?>" data-favorite="<?= $isFavorite ? 'true' : 'false' ?>">
                        <button class="terminal-instrument-select" type="button" data-terminal-instrument aria-pressed="<?= $instrument['symbol'] === $selectedSymbol ? 'true' : 'false' ?>">
                            <span class="terminal-symbol-copy"><strong><?= e($instrument['symbol']) ?></strong><small><?= e($instrument['display_name']) ?></small></span>
                            <span class="terminal-quote-copy"><strong><?= e(\App\Services\DashboardService::formatPrice((string) $instrument['bid'], $precision)) ?></strong><small><?= e(\App\Services\DashboardService::formatPrice((string) $instrument['ask'], $precision)) ?></small><small data-watchlist-change>—</small></span>
                        </button>
                        <form method="post" action="<?= e(client_url('watchlist')) ?>" class="terminal-favorite-form">
                            <?= csrf_field() ?><input type="hidden" name="instrument_id" value="<?= e((string) $instrument['id']) ?>">
                            <input type="hidden" name="action" value="<?= $isFavorite ? 'remove' : 'add' ?>"><input type="hidden" name="return_to" value="trade"><input type="hidden" name="symbol" value="<?= e($selectedSymbol) ?>">
                            <button type="submit" aria-label="<?= $isFavorite ? 'Remove ' : 'Add ' ?><?= e($instrument['symbol']) ?> <?= $isFavorite ? 'from' : 'to' ?> favorites" title="<?= $isFavorite ? 'Remove favorite' : 'Add favorite' ?>" aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>"><?= $isFavorite ? '★' : '☆' ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <p class="terminal-search-empty" data-terminal-search-empty hidden>No enabled instruments match this filter.</p>
            </div>
            <p class="terminal-panel-footnote">Simulated Demo Market Data · Not live market data</p>
        </aside>

        <main class="terminal-chart-column" aria-label="Selected demo instrument workspace">
            <section class="terminal-instrument-header" data-selected-instrument="<?= e($selectedSymbol) ?>">
                <div class="terminal-selected-name">
                    <button type="button" class="terminal-symbol-button" data-terminal-info-open aria-haspopup="dialog">
                        <strong data-selected-symbol><?= e($selectedSymbol) ?></strong><span data-selected-name><?= e((string) ($selectedInstrument['display_name'] ?? 'Demo instrument')) ?></span><small data-selected-category><?= e((string) ($selectedInstrument['category'] ?? 'Demo')) ?> · Instrument enabled</small>
                    </button>
                    <span class="terminal-demo-data-tag">DEMO MARKET DATA</span>
                </div>
                <div class="terminal-price-summary">
                    <button type="button" class="terminal-price-box is-sell" data-price-side="sell" aria-label="Select Sell direction at demo bid">
                        <span>SELL <small>DEMO BID</small></span><strong data-price-role="bid"><?= e($selectedBid) ?></strong>
                    </button>
                    <span class="terminal-spread"><small>SPREAD</small><strong data-price-role="spread"><?= e($selectedSpread) ?></strong></span>
                    <button type="button" class="terminal-price-box is-buy" data-price-side="buy" aria-label="Select Buy direction at demo ask">
                        <span>BUY <small>DEMO ASK</small></span><strong data-price-role="ask"><?= e($selectedAsk) ?></strong>
                    </button>
                </div>
                <div class="terminal-market-stats" aria-label="Demo session market statistics">
                    <span>Change <strong data-market-stat="change">—</strong></span><span>Change % <strong data-market-stat="change_percent">—</strong></span><span>High <strong data-market-stat="high">—</strong></span><span>Low <strong data-market-stat="low">—</strong></span><span>Updated <strong data-market-stat="timestamp">—</strong></span>
                </div>
            </section>

            <section class="terminal-chart-panel" data-terminal-chart-panel aria-label="Chart workspace">
                <div class="terminal-chart-toolbar">
                    <div class="terminal-timeframes" role="group" aria-label="Chart timeframe">
                        <?php foreach (['1m','5m','15m','30m','1H','4H','1D'] as $index => $timeframe): ?><button type="button" data-terminal-timeframe="<?= e($timeframe) ?>" aria-pressed="<?= $index === 2 ? 'true' : 'false' ?>" class="<?= $index === 2 ? 'is-active' : '' ?>"><?= e($timeframe) ?></button><?php endforeach; ?>
                    </div>
                    <div class="terminal-chart-tools" role="group" aria-label="Chart display options">
                        <button type="button" data-terminal-chart-type="candles" aria-pressed="true" class="is-active">Candles</button>
                        <button type="button" data-terminal-chart-type="line" aria-pressed="false">Line</button>
                        <button type="button" data-terminal-chart-type="area" aria-pressed="false">Area</button>
                        <button type="button" class="terminal-tool-disabled" disabled title="Technical indicators are outside this demo phase">Indicators</button>
                        <button type="button" data-terminal-chart-reset>Reset</button>
                        <button type="button" data-terminal-fullscreen aria-label="Expand chart">⛶</button>
                    </div>
                </div>
                <div class="terminal-chart-state"><span data-chart-context><?= e($selectedSymbol) ?> · 15m</span><span data-chart-state>Loading demo history…</span></div>
                <div class="terminal-chart-canvas" data-chart-symbol="<?= e($selectedSymbol) ?>" data-chart-type="candles" role="img" aria-label="Simulated demo candlestick chart for <?= e($selectedSymbol) ?>">
                    <div class="terminal-chart-grid" aria-hidden="true"></div>
                    <div class="terminal-axis-label terminal-axis-y" aria-hidden="true"><span>—</span><span>—</span><span>—</span><span>—</span></div>
                    <div class="terminal-axis-label terminal-axis-x" aria-hidden="true"><span>SIMULATED DEMO HISTORY</span><span>OHLC CANDLES</span><span>DEMO DATA</span></div>
                    <div class="terminal-chart-empty" role="status"><span class="terminal-chart-empty-mark" aria-hidden="true">⌁</span><strong>Loading simulated candles</strong><p>Historical values are generated demo data, not real market history.</p><small data-chart-empty-context><?= e($selectedSymbol) ?> · 15m · DEMO DATA</small></div>
                </div>
                <div class="terminal-chart-footer"><span><i aria-hidden="true"></i> Demo Market Data</span><span data-chart-footer>Simulated history</span></div>
            </section>

            <section class="terminal-bottom-panel terminal-panel" id="terminal-bottom-panel" aria-label="Account activity">
                <div class="terminal-bottom-heading">
                    <div class="terminal-bottom-tabs" role="tablist" aria-label="Account activity tabs">
                        <?php foreach (['positions'=>'Positions','orders'=>'Orders','history'=>'History'] as $key => $label): ?><button id="terminal-tab-<?= e($key) ?>" type="button" role="tab" data-terminal-tab="<?= e($key) ?>" aria-controls="terminal-pane-<?= e($key) ?>" aria-selected="<?= $key === 'positions' ? 'true' : 'false' ?>" tabindex="<?= $key === 'positions' ? '0' : '-1' ?>" class="<?= $key === 'positions' ? 'is-active' : '' ?>"><?= e($label) ?><span><?= $key === 'positions' ? count($positions) : ($key === 'orders' ? count($orders) : count($trades)) ?></span></button><?php endforeach; ?>
                    </div>
                    <span class="terminal-account-connected"><i aria-hidden="true"></i> Account data loaded</span>
                </div>
                <div class="terminal-bottom-content">
                    <div id="terminal-pane-positions" class="terminal-tab-pane is-active" role="tabpanel" aria-labelledby="terminal-tab-positions" data-terminal-pane="positions">
                        <div class="terminal-empty-row" data-positions-empty <?= $positions !== [] ? 'hidden' : '' ?>><span>◫</span><strong>No open positions</strong><small>New demo positions will appear here after execution.</small></div>
                        <div class="terminal-table-scroll" data-positions-table <?= $positions === [] ? 'hidden' : '' ?>><table class="terminal-table"><thead><tr><th>Instrument</th><th>Side</th><th class="numeric">Volume</th><th class="numeric">Open</th><th class="numeric">Current</th><th class="numeric">S/L</th><th class="numeric">T/P</th><th class="numeric">Demo P/L</th><th>Opened</th><th>Action</th></tr></thead><tbody data-positions-body>
                        <?php foreach ($positions as $position): $positionPrecision = (int) ($position['precision_digits'] ?? 5); ?><tr data-position-id="<?= e((string)$position['id']) ?>" data-position-symbol="<?= e($position['symbol']) ?>" data-position-side="<?= e($position['direction']) ?>" data-position-volume="<?= e((string)$position['volume']) ?>" data-position-open="<?= e((string)$position['open_price']) ?>" data-position-sl="<?= e((string)($position['stop_loss']??'')) ?>" data-position-tp="<?= e((string)($position['take_profit']??'')) ?>" data-position-precision="<?= e((string)$positionPrecision) ?>"><td><strong><?= e($position['symbol']) ?></strong><small><?= e($position['display_name']) ?></small></td><td><span class="terminal-side-tag is-<?= e($position['direction']) ?>"><?= e(strtoupper((string) $position['direction'])) ?></span></td><td class="numeric"><?= e(\App\Services\DashboardService::formatVolume((string) $position['volume'])) ?></td><td class="numeric"><?= e(\App\Services\DashboardService::formatPrice((string) $position['open_price'], $positionPrecision)) ?></td><td class="numeric" data-position-mark><?= $position['current_price'] === null ? '—' : e(\App\Services\DashboardService::formatPrice((string) $position['current_price'], $positionPrecision)) ?></td><td class="numeric"><?= $position['stop_loss'] === null ? '—' : e((string) $position['stop_loss']) ?></td><td class="numeric"><?= $position['take_profit'] === null ? '—' : e((string) $position['take_profit']) ?></td><td class="numeric" data-position-pl><?= $position['position_pl'] === null ? '—' : e(\App\Services\DashboardService::formatMoney((string) $position['position_pl'], (string) ($account['currency'] ?? 'USD'), true)) ?></td><td><?= e(\App\Services\DashboardService::formatDate($position['opened_at'])) ?></td><td><button type="button" data-position-action="close">Close</button> <button type="button" data-position-action="modify">Modify</button></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </div>
                    <div id="terminal-pane-orders" class="terminal-tab-pane" role="tabpanel" aria-labelledby="terminal-tab-orders" data-terminal-pane="orders" hidden>
                        <div class="terminal-empty-row" data-orders-empty <?= $orders !== [] ? 'hidden' : '' ?>><span>↗</span><strong>No orders</strong><small>Executed demo orders will appear here.</small></div>
                        <div class="terminal-table-scroll" data-orders-table <?= $orders === [] ? 'hidden' : '' ?>><table class="terminal-table"><thead><tr><th>Instrument</th><th>Type</th><th>Side</th><th class="numeric">Volume</th><th class="numeric">Execution</th><th class="numeric">S/L</th><th class="numeric">T/P</th><th>Status</th><th>Created</th><th>Action</th></tr></thead><tbody data-orders-body>
                        <?php foreach ($orders as $order): $orderPrecision = (int) ($order['precision_digits'] ?? 5); ?><tr><td><strong><?= e($order['symbol']) ?></strong><small><?= e($order['display_name']) ?></small></td><td><?= e(ucwords(str_replace('_', ' ', (string) $order['order_type']))) ?></td><td><span class="terminal-side-tag is-<?= e($order['direction']) ?>"><?= e(strtoupper((string) $order['direction'])) ?></span></td><td class="numeric"><?= e(\App\Services\DashboardService::formatVolume((string) $order['volume'])) ?></td><td class="numeric"><?= $order['execution_price'] === null ? '—' : e(\App\Services\DashboardService::formatPrice((string) $order['execution_price'], $orderPrecision)) ?></td><td class="numeric"><?= $order['stop_loss'] === null ? '—' : e(\App\Services\DashboardService::formatPrice((string) $order['stop_loss'], $orderPrecision)) ?></td><td class="numeric"><?= $order['take_profit'] === null ? '—' : e(\App\Services\DashboardService::formatPrice((string) $order['take_profit'], $orderPrecision)) ?></td><td><?= e($order['status'] === 'executed' ? 'Filled' : ucfirst((string) $order['status'])) ?></td><td><?= e(\App\Services\DashboardService::formatDate($order['created_at'])) ?></td><td>—</td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </div>
                    <div id="terminal-pane-history" class="terminal-tab-pane" role="tabpanel" aria-labelledby="terminal-tab-history" data-terminal-pane="history" hidden>
                        <div class="terminal-empty-row" data-history-empty <?= $trades !== [] ? 'hidden' : '' ?>><span>◷</span><strong>No trade history</strong><small>Closed demo trade segments will appear here.</small></div>
                        <div class="terminal-table-scroll" data-history-table <?= $trades === [] ? 'hidden' : '' ?>><table class="terminal-table"><thead><tr><th>Instrument</th><th>Side</th><th class="numeric">Volume</th><th class="numeric">Open</th><th class="numeric">Close</th><th class="numeric">Demo P/L</th><th>Reason</th><th>Opened</th><th>Closed</th></tr></thead><tbody data-history-body>
                        <?php foreach ($trades as $trade): $tradePrecision = (int) ($trade['precision_digits'] ?? 5); ?><tr><td><strong><?= e($trade['symbol']) ?></strong><small><?= e($trade['display_name']) ?></small></td><td><span class="terminal-side-tag is-<?= e($trade['direction']) ?>"><?= e(strtoupper((string)$trade['direction'])) ?></span></td><td class="numeric"><?= e(\App\Services\DashboardService::formatVolume((string)$trade['volume'])) ?></td><td class="numeric"><?= e(\App\Services\DashboardService::formatPrice((string)$trade['open_price'],$tradePrecision)) ?></td><td class="numeric"><?= e(\App\Services\DashboardService::formatPrice((string)$trade['close_price'],$tradePrecision)) ?></td><td class="numeric"><?= e(\App\Services\DashboardService::formatMoney((string)$trade['profit_loss'],(string)($account['currency']??'USD'),true)) ?></td><td><?= e(ucwords(str_replace('_',' ',(string)($trade['close_reason']??'manual')))) ?></td><td><?= e(\App\Services\DashboardService::formatDate($trade['opened_at'])) ?></td><td><?= e(\App\Services\DashboardService::formatDate($trade['closed_at'])) ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </div>
                </div>
            </section>
        </main>

        <aside class="terminal-panel terminal-ticket" id="terminal-ticket" data-terminal-ticket aria-label="Demo order ticket">
            <div class="terminal-panel-heading"><div><span class="terminal-overline">DEMO MARKET ORDER</span><h2>Demo ticket</h2></div><button class="terminal-icon-button terminal-mobile-close" type="button" data-terminal-close-panel aria-label="Close order ticket">×</button><span class="terminal-demo-pill">DEMO</span></div>
            <p class="terminal-ticket-message" data-trading-control-message role="status" hidden></p>
            <div class="terminal-ticket-instrument"><span>Instrument</span><strong data-ticket-symbol><?= e($selectedSymbol) ?></strong><small data-ticket-name><?= e((string) ($selectedInstrument['display_name'] ?? 'Demo instrument')) ?></small></div>
            <div class="terminal-direction-toggle" role="group" aria-label="Order direction">
                <button type="button" data-terminal-direction="buy" aria-pressed="true" class="is-active is-buy">Buy <small data-direction-quote><?= e($selectedAsk) ?></small></button>
                <button type="button" data-terminal-direction="sell" aria-pressed="false" class="is-sell">Sell <small data-direction-quote><?= e($selectedBid) ?></small></button>
            </div>
            <label class="terminal-field">Order type<select data-ticket-type><option value="market">Market · Available</option><option value="limit" disabled>Limit · Coming Soon</option><option value="stop" disabled>Stop · Coming Soon</option></select></label>
            <div class="terminal-field"><label for="terminal-volume">Volume (lots)</label><div class="terminal-volume-control"><button type="button" data-volume-step="down" aria-label="Decrease volume">−</button><input id="terminal-volume" type="number" min="<?= e((string)($selectedInstrument['min_volume']??config('DEMO_TRADE_VOLUME_MIN'))) ?>" max="<?= e((string)($selectedInstrument['max_volume']??config('DEMO_TRADE_VOLUME_MAX'))) ?>" step="<?= e((string)($selectedInstrument['volume_step']??config('DEMO_TRADE_VOLUME_STEP'))) ?>" value="<?= e((string)($selectedInstrument['min_volume']??'0.01')) ?>" inputmode="decimal" data-ticket-volume><button type="button" data-volume-step="up" aria-label="Increase volume">+</button></div><small data-volume-limits>Demo volume: <?= e((string)($selectedInstrument['min_volume']??config('DEMO_TRADE_VOLUME_MIN'))) ?>–<?= e((string)($selectedInstrument['max_volume']??config('DEMO_TRADE_VOLUME_MAX'))) ?> lots · <?= e((string)($selectedInstrument['volume_step']??config('DEMO_TRADE_VOLUME_STEP'))) ?> step</small></div>
            <label class="terminal-check-field"><input type="checkbox" data-protection-toggle="sl"><span>Set Stop Loss</span></label>
            <label class="terminal-field terminal-protection-price" data-protection-field="sl" hidden>Stop Loss price<input type="number" min="0" step="any" inputmode="decimal" placeholder="Optional" data-ticket-sl></label>
            <label class="terminal-check-field"><input type="checkbox" data-protection-toggle="tp"><span>Set Take Profit</span></label>
            <label class="terminal-field terminal-protection-price" data-protection-field="tp" hidden>Take Profit price<input type="number" min="0" step="any" inputmode="decimal" placeholder="Optional" data-ticket-tp></label>
            <div class="terminal-order-estimate"><div><span>Spread</span><strong data-ticket-spread><?= e($selectedSpread) ?></strong></div><div><span>Virtual balance</span><strong><?= $metrics ? e(\App\Services\DashboardService::formatMoney($metrics['balance'], $metrics['currency'])) : '—' ?></strong></div><div><span>Estimated margin</span><strong data-estimated-margin>—</strong></div><small>Preliminary demo estimate; final account metrics arrive in a later phase.</small></div>
            <button class="terminal-place-order" type="button" data-terminal-place-order>Place Demo Buy</button>
            <p class="terminal-ticket-disclaimer">Demo Trading · Virtual funds only. No real funds or broker are involved.</p>
            <p class="terminal-ticket-message" data-ticket-message role="status" hidden></p>
        </aside>
    </div>

    <div class="terminal-mobile-backdrop" data-terminal-backdrop hidden></div>

    <dialog class="terminal-info-dialog" data-terminal-info-dialog aria-labelledby="terminal-info-title">
        <div class="terminal-dialog-heading"><div><span class="terminal-overline">INSTRUMENT DETAILS</span><h2 id="terminal-info-title" data-info-symbol><?= e($selectedSymbol) ?></h2><p data-info-name><?= e((string) ($selectedInstrument['display_name'] ?? 'Demo instrument')) ?></p></div><button type="button" class="terminal-icon-button" data-terminal-info-close aria-label="Close instrument details">×</button></div>
        <dl class="terminal-spec-list">
            <div><dt>Category</dt><dd data-info-category><?= e((string) ($selectedInstrument['category'] ?? '—')) ?></dd></div>
            <div><dt>Quote currency</dt><dd data-info-currency><?= e((string) ($selectedInstrument['quote_currency'] ?? '—')) ?></dd></div>
            <div><dt>Contract size</dt><dd data-info-contract><?= e((string) ($selectedInstrument['contract_size'] ?? '—')) ?></dd></div>
            <div><dt>Demo spread</dt><dd data-info-spread><?= e($selectedSpread) ?></dd></div>
            <div><dt>Precision</dt><dd data-info-precision><?= e((string) $selectedPrecision) ?> digits</dd></div>
            <div><dt>Configured margin rate</dt><dd data-info-margin><?= e($marginRatePercent) ?></dd></div>
        </dl>
        <p class="terminal-panel-footnote">Database configuration for demo display; this is not a live broker specification.</p>
    </dialog>

    <dialog class="terminal-order-dialog" data-terminal-order-dialog aria-labelledby="terminal-order-title">
        <div class="terminal-dialog-heading"><div><span class="terminal-overline">DEMO ORDER CONFIRMATION</span><h2 id="terminal-order-title">Review demo market order</h2></div><button type="button" class="terminal-icon-button" data-order-dialog-close aria-label="Close order confirmation">×</button></div>
        <dl class="terminal-spec-list"><div><dt>Instrument</dt><dd data-preview-symbol><?= e($selectedSymbol) ?></dd></div><div><dt>Direction</dt><dd data-preview-direction>BUY</dd></div><div><dt>Order type</dt><dd data-preview-type>Market</dd></div><div><dt>Volume</dt><dd data-preview-volume>0.10 lots</dd></div><div><dt>Indicative Bid / Ask</dt><dd><span data-preview-bid><?= e($selectedBid) ?></span> / <span data-preview-ask><?= e($selectedAsk) ?></span></dd></div><div><dt>Stop Loss</dt><dd data-preview-sl>—</dd></div><div><dt>Take Profit</dt><dd data-preview-tp>—</dd></div></dl>
        <p class="terminal-order-warning">Demo Trading — Virtual funds only. Final demo execution price is determined server-side when the order is submitted.</p>
        <div class="terminal-dialog-actions"><button type="button" data-order-dialog-close>Cancel</button><button class="terminal-place-order" type="button" data-submit-order>Place Demo Buy</button></div>
    </dialog>

    <dialog class="terminal-order-dialog" data-position-dialog aria-labelledby="position-dialog-title">
        <form method="dialog" class="terminal-dialog-heading"><div><span class="terminal-overline" data-position-dialog-kicker>POSITION MANAGEMENT</span><h2 id="position-dialog-title" data-position-dialog-title>Close demo position</h2></div><button type="submit" class="terminal-icon-button" aria-label="Close dialog">×</button></form>
        <p data-position-dialog-summary></p>
        <div data-position-close-fields><label class="terminal-field">Volume to close<input type="number" min="0.01" step="0.01" data-position-close-volume></label><p>Indicative close price: <strong data-position-indicative>—</strong> · Estimated P/L: <strong data-position-estimate>—</strong></p><p class="terminal-position-warning">Final close price is determined server-side. Demo Trading — Virtual funds only.</p></div>
        <div data-position-modify-fields hidden><label class="terminal-field">Stop Loss<input type="number" min="0" step="any" data-position-sl-input placeholder="Leave empty to remove"></label><label class="terminal-field">Take Profit<input type="number" min="0" step="any" data-position-tp-input placeholder="Leave empty to remove"></label><p>Leave a level empty to remove it.</p></div>
        <p data-position-dialog-error role="alert" hidden></p>
        <div class="terminal-dialog-actions"><button type="button" data-position-cancel>Cancel</button><button class="terminal-place-order" type="button" data-position-submit>Close Demo Position</button></div>
    </dialog>
    <div class="terminal-statusbar"><span><i aria-hidden="true"></i> <span data-market-connection>Connecting to Demo Market Data…</span></span><span>Demo Trading · Virtual funds only</span></div>
    <div class="terminal-order-toast" data-order-toast role="status" aria-live="polite" hidden></div>
</section>
