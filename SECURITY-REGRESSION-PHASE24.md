# MultiForex Phase 24 security and regression results

Status labels mean: **PASS** = directly exercised in this environment; **REVIEWED** = code/config reviewed but no adversarial runtime test; **BLOCKED** = requires local MySQL/HTTP, an authenticated fixture, or a production host; **N/A** = no such integration exists. Do not promote REVIEWED/BLOCKED to PASS without evidence.

## Inventory

- Public routes: `/`, `/markets` and six market categories, platform/accounts/company/about/security/contact/FAQ/legal, calculators, education/articles/news/CMS pages, robots/sitemap, login/register/password reset, and development-only design-system.
- Client routes: dashboard/trade/positions/orders/history/watchlist/profile/funds/verification/notifications/support, private document download, and trading/account/KYC/support APIs.
- Admin routes: login/logout, dashboard/users/accounts/instruments/trading, CMS articles/pages/FAQs/categories/media/contacts, settings, and explicit permission-check placeholders for funds/KYC/support/admins/roles/audit.
- Upload/download: KYC document upload/removal and authorized document download; support ticket/reply attachment upload and authorized download; CMS image-only upload.
- CLI/tooling: Admin create/reset scripts, Phase 22 provider test, Phase 24 calculation fixtures, production health check, database health/reset utilities, and additive SQL migrations. Web requests to PHP utilities under `tools/` are denied by `.htaccess`; the provider and calculation test scripts also exit on non-CLI SAPIs. Database and migration paths are denied at the application-root rewrite boundary.
- Config/storage: active local `config/config.php`, safe config examples, `storage/logs`, `storage/private/kyc`, and `storage/private/support`. For deployment, private storage should be outside the document root where available.
- Database: fresh schema `database/database.sql`; additive Phase 07, 10–13 helpers/SQL and Phase 15–22 migrations under `database/migrations/`. The active MySQL schema/migration state was not readable from this sandbox.

## Findings fixed in Phase 24

- **PASS** Closed direct HTTP execution of any PHP file in `tools/` at Apache rewrite level; added a CLI-only guard to `phase22-provider-tests.php` as a second control.
- **PASS** Added a secret-free production configuration template with production URL, production/debug flags, Demo providers, hard-disabled real execution, KYC Demo mode, cPanel database placeholders, and an outside-web-root private-storage example.
- **PASS** Added a CLI-only production health check for safe production flags, Demo provider selection, HTTPS URL, writable/protected private storage, and database connectivity. It does not print credentials or connection details.
- **PASS** Added a shared response-header baseline for public/client requests: `nosniff`, frame denial, strict cross-origin referrer handling, a restrictive permissions policy, and a same-origin CSP. The Admin CSP retains same-origin scripts and allows inline styles required by existing Admin charts. HSTS is deliberately left to the host operator after TLS verification.
- **PASS** Added cPanel deployment, migration, first-Admin, HTTPS, storage, backup/rollback, and production smoke-test guidance.

## Direct checks

- **PASS** PHP lint for `index.php`, form-field/design-system Phase 23 touchpoints, new production config template, and new production health check.
- **PASS** `php tools/phase24-calculation-tests.php` passed 15 reproducible arithmetic checks using the production valuation/conversion/margin services. `php tools/phase22-provider-tests.php` passed 36 provider safety checks.
- **PASS** Public/auth/market/SEO PHP CLI route smoke rendered cleanly for home, market pages, platform, accounts, tools/calculators, company/security/contact/FAQ/legal, CMS listings, robots/sitemap, design system, login/register/reset forms, Admin login, API status, and market quotes. This checks dispatch/render output but not HTTP headers, rewrite behavior, DB-backed data, or Apache status codes.
- **PASS** Isolated project service fixtures verified EURUSD BUY/SELL execution-side mark prices and spread effect, USDJPY conversion, XAUUSD/NAS100/BTCUSD valuation, and EURUSD/USDJPY margin conversions.
- **REVIEWED** SQL values use prepared statements; variable sort values are whitelisted; history pagination is bounded. Ownership filters and composite user/account constraints appear in the inspected services/schema.
- **REVIEWED** Order, position-close, and funding workflows use account/position row locks, DB transactions, and idempotency constraints. Admin instrument edits use config versions/transactions and activity records.
- **REVIEWED** KYC/support handlers check extension/MIME/size, randomize stored names, use private storage paths, and stream downloads with owner checks, no-store, attachment disposition, and `nosniff`. CMS media restricts image MIME types and blocks executable extensions in its upload directory.
- **REVIEWED** Admin responses already set no-store, noindex, `nosniff`, frame protection, referrer policy, and a restrictive same-origin CSP. Client middleware sets no-store. Auth/session setup uses strict mode, HttpOnly, SameSite Lax, HTTPS-dependent Secure cookies, and session regeneration paths.
- **PASS** Active local config reports development/local URL, Demo market/trading providers, real execution disabled, and KYC Demo mode. This is a local configuration inspection; production config remains a template.
- **BLOCKED** Local HTTP/browser smoke: localhost requests are denied by the execution sandbox. MySQL TCP/socket access is also denied, so existing DB state and production health check cannot connect here.

## Final checklist

| Area | Result | Remaining evidence |
|---|---|---|
| Authentication, reset, logout, session fixation, rate limits | BLOCKED | Browser tests with disposable client/Admin accounts and live DB |
| RBAC and Admin/client separation | REVIEWED | Limited-role and Super Admin authorization tests |
| CSRF and GET mutation checks | REVIEWED | Adversarial request tests for each mutation route |
| Cross-user IDOR and private attachment ownership | REVIEWED | User A/User B fixtures and HTTP download attempts |
| SQL injection and output encoding/XSS | REVIEWED | Adversarial payload tests against search, references, CMS, support, logs |
| Upload validation/private storage | REVIEWED | Fake MIME, polyglot/double-extension, oversized, traversal tests and HTTP path checks |
| Secrets and log redaction | REVIEWED | Full private-config/log inspection on deployment host |
| Session/cookie headers, CSP, cache | REVIEWED | Actual HTTPS response-header and browser checks |
| Financial calculations | PASS (15 isolated service fixtures) | DB-backed reproducible workflow, SL/TP triggers, and realized/partial close transaction tests |
| Concurrency and idempotency | REVIEWED | Simultaneous DB requests for order, close, withdrawal, and Admin updates |
| Ledger/reconciliation | REVIEWED | Database reconciliation against account balances and transactions |
| Demo/cTrader provider guard | PASS (Phase 22 provider test source exists) | Run `php tools/phase22-provider-tests.php` in supported host environment |
| Production debug and private files | REVIEWED | cPanel Apache requests against protected paths |
| Database schema/index/migration state | BLOCKED | Read-only schema inspection and backups on actual DB |
| Chrome/Edge/Firefox/Safari responsive flows | BLOCKED | Browser matrix at 360/390/430 and 1024/1440/1920 px |
| HTTPS/cPanel deployment/backups | BLOCKED | Host owner deployment and restore drill |

## Regression matrix

| Workflow | Result |
|---|---|
| Public routes, login/register/reset form rendering, market API dispatch | PASS (PHP CLI only) |
| Client registration/login/demo account | BLOCKED (DB + browser required) |
| Market quote continuity/candles/freshness | REVIEWED; DB-backed runtime BLOCKED |
| BUY/SELL, P/L, close/partial close, SL/TP | Calculation fixtures PASS; DB concurrency/workflows BLOCKED |
| History, funds, KYC, notifications, support | REVIEWED; authenticated runtime BLOCKED |
| Admin users/accounts/instruments/trading/CMS/settings/RBAC | REVIEWED; authenticated runtime BLOCKED |

## Limitations and deployment recommendation

- Local Apache/WAMP and MySQL are unreachable from this execution sandbox, so HTTP status, rewrite rules, actual headers, schema/migration state, authenticated journeys, uploaded-file attack cases, and concurrency were not executed.
- Chrome can launch but its localhost navigation is blocked. Edge, Firefox, Safari, cPanel, and the production domain were not tested.
- No production code or database was uploaded or modified. `config/config.production.example.php` is a template; do not use it until every placeholder is replaced and the first Admin bootstrap is handled by an approved CLI-only process.
- First-Admin creation currently has a development-only guard. Production deployment is **NO-GO** until a secure temporary CLI-only bootstrap path is tested and removed, or an equivalent approved procedure is established.
- Password reset uses a development mail log when mail transport is not configured. Production reset delivery must be configured and tested, or reset must be disabled through a reviewed change before public launch.
- Run `php tools/production-health-check.php` only after the active production config and database exist. The Phase 22 provider test is CLI-only.
- Run `php tools/phase24-calculation-tests.php` for the no-database quote valuation, spread, conversion, margin, equity/free-margin arithmetic, and partial-close fixtures.

## Verified demo calculation examples

The direct fixture run uses the application's own decimal/conversion/valuation services and illustrative quotes. EURUSD Bid `1.10000` / Ask `1.10020`, 1 lot and 100,000 contract size produces a `-20 USD` mark-to-market at entry on both sides. USDJPY 150.00/150.02 converts a one-lot BUY spread loss to about `-13.332444502 USD`; margin at 1% for 100,000 notional is about `1000.06666209502 USD`. XAUUSD 2000.00/2000.50 (100-unit contract), NAS100 18000/18002 (unit contract), and BTCUSD 65000/65010 (unit contract) all mark a new BUY below entry. These are arithmetic fixtures, not market observations or full transaction regression tests.
