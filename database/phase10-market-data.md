# Phase 10: demo market data

The terminal, public market tables, and homepage ticker read from `ProviderFactory::marketData()`. `MarketDataProviderInterface` defines batched quote and candle access. `MARKET_DATA_PROVIDER=demo` selects `DemoMarketDataProvider`; a future provider can implement the same contract and be selected centrally without adding provider branches to pages or consumers.

## Storage and migration

Apply `phase10-market-data.sql` once to an existing Phase 02 database. The migration is additive and safe to repeat. It creates:

- `market_quotes`: one current bid/ask/mid snapshot per enabled instrument.
- `market_simulation_state`: the persisted random-walk position, session bounds, timezone day, and deterministic sequence.
- `market_candles`: OHLC rows unique per instrument, timeframe, and open time.

Price columns use `DECIMAL(24,10)`. API price values are serialized as decimal strings to preserve the stored precision. The instrument catalogue's `spread` is an absolute ask-minus-bid price difference, as documented in Phase 02; it is divided equally around mid. `precision_digits` determines tick size and rounding.

## Simulation and candles

On quote requests the provider locks one instrument state row in an InnoDB transaction, calculates elapsed server time, advances up to 120 bounded deterministic stochastic steps, applies a small mean-reversion term and category volatility factor, updates session open/high/low/change, writes the quote and candle rows, then commits. Longer gaps are capped at one hour and use aggregate-sized steps. Price bounds remain between one quarter and four times the configured base price. The session day follows the configured `TIMEZONE`.

Initial history deterministically seeds 500 one-minute candles per requested instrument. Seed movement scales with each instrument's configured demo volatility and asset category so low precision and higher-price instruments also produce legible candles. Larger periods aggregate that same one-minute sequence, so their price path matches the base data. Available periods are `1m`, `5m`, `15m`, `30m`, `1H`, `4H`, and `1D`. Active candle OHLC values advance from the server quote; quote requests update all period buckets. The data is simulated and is not real or exchange history.

Seed history is generated lazily the first time a chart requests that instrument. To control storage, keep one minute of seed history and seven derived period rows. Old quote and candle retention should be handled by an installation's scheduled database maintenance policy; no rows are automatically purged by market requests.

## HTTP contract and consumers

- `GET /api/market/quotes?symbols=EURUSD,GBPUSD` returns up to 40 enabled symbols in one call. Omitting `symbols` returns every enabled instrument.
- `GET /api/market/quote?symbol=EURUSD` returns one quote or a JSON 404.
- `GET /api/market/candles?symbol=EURUSD&timeframe=1m&limit=300` returns up to 1,000 OHLC candles.

Routes use the configured `SITE_URL` base path, so localhost `/forex/` and a production root deployment use the same templates. Responses are no-store JSON with generic public errors. Each PHP session has a rolling per-minute request cap (80 quote requests and 40 candle requests), above normal terminal polling, plus a 40-symbol batch cap. The terminal batches its watchlist symbols and polls at about 1.6 seconds while visible; it pauses while hidden and uses bounded backoff during quote failures. Chart history reloads only when the selected symbol or timeframe changes. Public markets and homepage ticker use the same endpoint on an 8-second interval.

No real market API is configured. Do not describe these values as live, broker, exchange, or historical market prices.

## Reset and future provider

For development only, run `php database/reset-demo-market-data.php --confirm-reset-demo-market-data`. The script refuses web execution and non-development environments and deletes only the three Phase 10 tables. The next quote/candle request recreates demo history and state.

Phase 11 can obtain executable quotes server-side with `ProviderFactory::marketData()->quotes(['EURUSD'])`; this does not call the application's own HTTP endpoint. The returned quote DTO contains instrument id, symbol, bid, ask, mid, spread, session change and percent, high/low/open/previous close, server timestamp, provider code, demo flag, and precision. Phase 11 must keep order execution separate from this read-only provider. Phase 22 can replace the selected provider through the existing factory while preserving the consumer interface.

Phase 10 does not execute orders, create positions or trades, change balances, or calculate account margin/P&L.
