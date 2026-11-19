# Phase 11 demo trading migration and assumptions

Apply `phase11-demo-trading.sql` once to an existing Phase 02 database. It adds a nullable per-account request token with a unique index, a nullable originating order link on positions, and per-account rate-limit state. Existing order and position rows are preserved. `trades` remains the completed round-trip history table, so opening a position does not create a trade row.

The execution service accepts market BUY and SELL only. It uses the Phase 10 demo provider quote returned for this request, checks its timestamp, fills BUY at Ask and SELL at Bid, and stores that exact price in both the order and position. A retry with the same client token returns the original order and position.

Instrument configuration currently has no lot bounds, volume step, commission, or stop-distance columns. Demo defaults are configured centrally in `config/config.php`: 0.01 minimum, 100.00 maximum, 0.01 steps. SL/TP are optional, checked against the instrument precision and execution side, and are not triggered automatically. No additional stop-distance rule is invented.

Phase 12 replaces the preliminary capacity estimate with authoritative account-currency valuation. Floating P/L uses the current Bid to mark BUY positions and Ask to mark SELL positions. Initial required margin is converted from quote currency and saved on the open position; available capacity is checked against current equity less saved used margin while the account row is locked. Opening a position does not change balance. See the Phase 12 section in `README.md` for the additive migration.

The demo order rate limit is 20 requests per account per 60 seconds by default. It is independent of idempotency. No external execution provider, payment, liquidity route, slippage, commission, swap, close, modify, or pending-order behavior is enabled.
