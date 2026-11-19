# Phase 22: cTrader integration-ready layer

## Current behavior

MultiForex remains on `MARKET_DATA_PROVIDER=demo` and `TRADING_PROVIDER=demo` by default. `REAL_TRADING_ENABLED` is `false`. Selecting `ctrader` resolves to an explicit inactive adapter: market reads return a controlled unavailable status, orders are blocked, and Demo lifecycle/history APIs stop returning Demo account data. No live cTrader socket, token exchange, order, or market-data request is made by this phase.

Provider code uses the existing `MarketDataProviderInterface`, `TradingProviderInterface`, and `ProviderFactory`. cTrader values are normalized at the adapter boundary; the terminal and controllers do not consume Open API payload structures. Provider errors are mapped to safe categories and controller logs omit exception bodies.

## Configuration

Server-side values are in `config/config.php` and `config/config.example.php`:

- `CTRADER_CLIENT_ID` and `CTRADER_CLIENT_SECRET`: credentials from a Spotware-approved Open API application. Keep secrets outside public files and source control.
- `CTRADER_REDIRECT_URI`: exact registered HTTPS URI in production. Development permits HTTP only on localhost.
- `CTRADER_ENVIRONMENT`: `demo` or `live`; defaults to `demo`. This selects a future connection profile only and does not authorize trading.
- `CTRADER_SCOPE`: `accounts` (read-only) or `trading` (Open API grants trading access); defaults to `accounts`.
- `CTRADER_CONNECT_TIMEOUT_SECONDS` and `CTRADER_READ_TIMEOUT_SECONDS`: bounded future transport timeouts.
- `REAL_TRADING_ENABLED`: remains `false`; a separate reviewed scope change is required before any real-money implementation.

The Admin Trading page reports credentials as configured only when the client ID, secret, and redirect URI pass basic validation. “Connected” remains “No · Not verified” because this phase does not perform a connectivity test. No credentials, token, or upstream response body is rendered.

## Official Open API and OAuth

Use the official [cTrader Open API documentation](https://help.ctrader.com/open-api/) and register an application in the [Open API portal](https://openapi.ctrader.com/). The documented authentication uses OAuth 2.0, followed by cTrader application/account authorization messages. The authorization URL is restricted to cTrader's official authorization host, and `CTraderAuthService` provides one-use, admin-session-bound OAuth state validation and an exact configured redirect URI. There is intentionally no public connect route, callback, authorization-code exchange, or token persistence in Phase 22. An admin controller must enforce `trading.manage` and CSRF before using the service.

The official API separates Demo and Live endpoints. The current API client only exposes a trusted endpoint profile (official host, TLS verification required, bounded timeouts); it does not open a connection. The current profile uses the official JSON port. A future transport must follow the official JSON/Protobuf message protocol, rate limits, heartbeat and connection guidance, and must never accept a caller-supplied upstream URL.

Do not store access/refresh tokens until an encrypted server-side token store, key management, expiry/refresh handling, revocation, and redacted audit behavior have been reviewed. Tokens must never enter a browser, session storage, HTML, exception messages, or logs.

## Symbols, timeframes, and data contracts

`instrument_provider_mappings` continues to represent internal instrument-to-provider mappings. Phase 22 adds nullable `provider_symbol_id`; provider symbol name and provider ID are both supported, but this phase inserts no cTrader mappings. The mapper rejects inactive, malformed, or incomplete mappings. Admin symbol-mapping controls are not part of this phase.

Timeframes map centrally: `1m→M1`, `5m→M5`, `15m→M15`, `30m→M30`, `1H→H1`, `4H→H4`, and `1D→D1`.

Quote and candle normalizers validate prices, OHLC bounds, timestamp, precision, and environment. The normalized quote retains the platform's familiar symbol/bid/ask/mid/timestamp/provider/demo/precision fields and adds environment/spread. Candle records use `open_time`, `open`, `high`, `low`, `close`, timeframe, and optional volume. Execution results have a normalized order ID/status/price/volume/time/error shape, but no real execution path exists.

## Account mapping and database migration

`CTraderAccountMapping` defines the future mapping contract (internal account ID, external cTrader account ID, provider, Demo/Live environment, and pending/active/disabled status). No cTrader account mapping or token table is persisted. Current trading accounts are explicitly Demo-account records, so linking those to an external account without a consented identity/ownership model would create a misleading binding. A future account connection phase must define per-user authorization, account ownership, mapping review/approval, and secure token persistence before adding schema. Nothing is auto-bound.

Apply `database/migrations/phase22_ctrader_provider.sql` once; its `information_schema` check makes it safe to repeat on MySQL 8.4. It only adds the optional external symbol ID column. No seed rows, accounts, balances, or trading records are changed.

## Failures, retries, and hosting

Provider errors are normalized to authentication, mapping, rate-limit, invalid-request, unavailable, or execution-blocked categories. Do not log access tokens, client secrets, authorization codes, full payloads, or raw upstream error bodies. Read retries and idempotent order submission are not enabled until a real transport and provider idempotency contract exist; never retry a future order blindly.

Demo mode works on WAMP and shared hosting without cTrader credentials. A persistent cTrader JSON/Protobuf connection may require a long-running worker or WebSocket-capable VPS; PHP request/response hosting is not assumed to maintain a dependable broker stream.

## Remaining work before any cTrader connection or real trading

1. Register and obtain approval for an official Open API application and an exact production redirect URI.
2. Configure server-side credentials through a protected deployment secret mechanism; keep `REAL_TRADING_ENABLED=false`.
3. Implement the reviewed OAuth initiation/callback route with admin authorization, CSRF, state, redirect validation, server-side code exchange, and no open redirect.
4. Implement encrypted token storage/refresh and explicit, user-consented external account mapping.
5. Add and verify cTrader symbol mappings (ID and name) and provider/environment behavior.
6. Implement the official transport, bounded timeouts, TLS verification, rate limits, safe read retry policy, normalized market data and verified connection status.
7. Test against cTrader Demo first, including upstream outage, redaction, idempotency, authorization, and account isolation.
8. Keep order execution blocked unless a later, separately authorized scope explicitly designs and reviews Demo execution. Real-money execution requires a separate explicit authorization, risk/legal review, deployment review, and hard-gate change; credentials or environment selection alone never permit it.
