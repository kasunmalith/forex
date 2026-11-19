# MultiForex Demo Platform

> A PHP paper-trading platform for exploring Forex and CFD workflows. Trading is simulated.

## What it does

- Public market, account, platform and educational pages
- Registration, sign-in and demo client dashboard
- Simulated quotes, candles, orders, positions and funds
- KYC demonstration and support workflows
- Admin content, instruments and operations controls

## Technology

PHP 8+, MySQL, Apache, JavaScript

## Local setup

1. Copy `config/config.example.php` to `config/config.php` and set local values.
2. Import `database/database.sql` and apply later SQL migrations as needed.
3. Enable Apache `mod_rewrite` and open the project under WAMP.

## Database and sample data

Base schema and demo seed in `database/database.sql`, with phase migrations in `database/migrations/`.

Only schema and fictional starter data are intended for this public repository. Live customer, patient, order and enquiry records are never part of this snapshot.

## Publication note

This repository is a source snapshot. It does not include deployment credentials, live database records, customer uploads or server backups. Create fresh secrets and review the application before production use.

## Development credit

Software Development by [Energetic Edge (Pvt) Ltd](https://www.energeticedge.lk/)
