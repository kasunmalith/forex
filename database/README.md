# Phase 02 database architecture

Phase 10 adds demo market-data storage to an existing installation. Apply `phase10-market-data.sql` separately; see `phase10-market-data.md` for table purpose, quote/candle contracts, reset instructions, retention, and provider extension notes. It does not alter account or trade tables.

Database: `forex`. Import `database.sql` once into MySQL 8.0+ using phpMyAdmin or the MySQL client. It creates the database when absent, selects it, creates all tables, and inserts the initial categories, instruments, provider mappings, admin roles/permissions, settings, article categories, and draft page placeholders. It is designed for an empty database. It does not drop or overwrite an existing schema; if tables already exist, stop and inspect the database before proceeding.

The application's central `config/config.php` now uses `DB_NAME=forex`. Keep database credentials only in that config file. For another machine or hosting provider, change its DB settings there.

## Tables and purpose

- `users`, `user_profiles`: client identity and one-to-one profile data.
- `demo_accounts`: virtual account balance and realized P/L summary. Equity, used margin, free margin, and floating P/L are derived later from account balance and open positions rather than copied into unrelated records.
- `instrument_categories`, `instruments`, `instrument_provider_mappings`: catalog and external symbol mapping. Provider-specific identifiers stay in the mapping table.
- `watchlists`: user instrument selections, unique by user and instrument.
- `orders`, `positions`, `trades`: pending/executed order records, open/closed position records, and completed history.
- `transactions`, `deposit_requests`, `withdrawal_requests`: virtual ledger and demo adjustment requests only. They do not represent real funds or payment processing.
- `kyc_submissions`, `kyc_documents`: private verification metadata and private relative file paths. Do not put KYC documents under a public URL.
- `notifications`: per-user read/unread messages.
- `admin_roles`, `admins`, `admin_permissions`, `admin_role_permissions`, `admin_activity_logs`: role/permission foundation, no seeded administrator credential, and secret-free admin audit records.
- `support_tickets`, `support_messages`: user tickets and user/admin messages.
- `article_categories`, `articles`, `faqs`, `contact_messages`, `pages`: content and contact foundations. Legal pages are draft placeholders and make no regulatory or security claims.
- `site_settings`: typed settings with an explicit public/private flag.

## Relationships and retention

Foreign keys use InnoDB. A composite account/user foreign key on orders, positions, trades, ledger rows, and demo funding requests prevents attaching one user's activity to another user's account. Completed trading and financial history uses `RESTRICT` so parent records cannot be casually deleted. User profiles and watchlists are the limited ephemeral records that can cascade on parent deletion. Admin assignment/reviewer references become NULL if the admin record is removed. Instrument category and article category records are restricted while referenced. Support message sender type and its matching user/admin sender column must be validated by the application when messages are written. The `transactions.reference_type/reference_id` pair is intentionally polymorphic and is indexed but not a foreign key.

## Numeric choices

No financial or price field uses FLOAT/DOUBLE. Account balances and virtual funding requests use `DECIMAL(20,2)`. Prices use `DECIMAL(20,10)` to cover FX, metals, indices and digital-asset precision with ample integer range. Volumes use `DECIMAL(20,8)`. P/L and ledger amounts use `DECIMAL(20,8)`. Contract size uses `DECIMAL(20,4)`. Margin rates and demo volatility are decimal fractions (`DECIMAL(10,8)`), not percentages; for example, a 1% margin rate is stored as `0.01000000`. Instrument `spread` is an absolute price difference and matches `ask - bid` in the seeds.

## Indexes

Unique indexes protect user/admin emails, account numbers, instrument symbols, provider symbol pairs, category/page/article slugs, ticket numbers, settings keys, and user watchlist pairs. Composite indexes support common status/date queries for users, accounts, orders, positions, trades, transactions, KYC, notifications, tickets, articles, and content. Foreign-key columns are indexed by their constraints or explicit query indexes.

## Demo seeds and provider compatibility

The supplied prices, spreads, margin rates, contract sizes, and volatility parameters are illustrative demo configuration only. They are not current, live, or indicative prices. No user or admin account is seeded, and no password is included. Create future accounts through a password-hashing application workflow. `instrument_provider_mappings` maps the internal instrument symbol to provider code/symbol; cTrader credentials and provider settings belong in protected configuration, never in this table or browser code. Order/position provider IDs are nullable integration references; execution logic is out of scope for Phase 02.

## Health check

From PowerShell or Command Prompt, run:

```text
C:\wamp64\bin\php\php8.4.15\php.exe C:\wamp64\www\forex\database\health-check.php
```

The command is CLI-only and development-only. Root rewrite rules also deny HTTP access to the `database` directory. It prints table and seed counts without connection details or secrets.
## Phase 07 authentication migrations

Apply the additive migrations to the existing `forex` schema; do not re-import `database.sql` over a populated database:

1. `phase07-auth-rate-limits.sql` adds hashed, short-lived auth throttling buckets.
2. `phase07-password-reset-tokens.sql` adds expiring, single-use password-reset token hashes with a user foreign key.

No Phase 02 tables are recreated or altered. Development reset messages are written only to `storage/logs/mail.log`, which is inside the protected storage directory. Configure a production mail sender before using password reset outside development.

## Phase 11 demo trading migration

Apply `phase11-demo-trading.sql` once to preserve existing records while adding order idempotency, the position's originating order relationship, and server-side trade rate-limit state. For an existing local WAMP database, run:

```text
C:\wamp64\bin\php\php8.4.15\php.exe C:\wamp64\www\forex\database\apply-phase11-demo-trading.php
```

The helper refuses HTTP execution. See `phase11-demo-trading.md` for execution and preliminary margin assumptions.

## Phase 12 account metrics migration

For an existing database, run `apply-phase12-account-metrics.php` once after Phase 11. It adds the nullable account-currency `positions.required_margin` snapshot without replacing existing records, then backfills open positions from their stored open price and current demo FX conversion rates. If a conversion quote is unavailable, that position remains explicitly unpriced for margin and new orders fail safely until the migration is rerun successfully. The command is idempotent and CLI-only:

```text
C:\wamp64\bin\php\php8.4.15\php.exe C:\wamp64\www\forex\database\apply-phase12-account-metrics.php
```

Fresh installs already include the margin snapshot column in `database.sql`. Margin rates are fractions (`0.01` means 1%); margin warning and critical levels are configured in `config/config.php`.

## Phase 15 demo funding migration

For an existing installation, apply `migrations/phase15_demo_funds.sql` once. It adds idempotency keys and a short client note to demo funding requests. Application configuration controls demo auto approval and amount bounds; no payment provider or real funding rail is configured. The authoritative balance remains `demo_accounts.balance`, with completed virtual funding movements recorded atomically in `transactions`.

## Phase 16 demo identity verification

For an existing installation, apply `migrations/phase16_demo_kyc.sql` once. It extends the existing KYC submission/document tables with controlled workflow statuses, references, a submitted profile snapshot, reviewer messages, document category/checksum, and superseded history. Private storage and Apache/cPanel protection, permissions, limits, and cleanup guidance are documented in `phase16-demo-kyc.md`.

## Phase 18 Admin security migration

After Phase 17, apply `migrations/phase18_admin_rbac.sql` once to an existing database. It extends the existing admin/role/permission tables, seeds the six system roles and permission catalogue, and explicitly maps permissions. See `phase18-admin-security.md` for the CLI bootstrap workflow, role summary, route protection pattern, and production notes. The CLI bootstrap creates no credentials by default.

## Phase 19 Admin operations migration

For an existing installation, apply `migrations/phase19_admin_operations.sql` once after Phase 18. It adds stable `MFU-` user references, the `disabled` state for users and demo accounts, and transaction idempotency/admin attribution for virtual balance adjustments. It preserves existing records and must not be applied by re-importing `database.sql`. See `phase19-admin-operations.md` for Admin workflows, account lock order, authoritative Free Margin debit limits, and deployment notes.

## Phase 20 instrument and Demo operations controls

See phase20-instruments-trading.md for the additive CLI migration, Admin permissions, safety constraints, and Demo-only behavior. The migration helper is idempotent and does not reset existing client activity or current global setting values.

