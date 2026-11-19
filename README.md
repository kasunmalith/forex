![MultiForex project cover](docs/readme-banner.svg)

<div align="center">
  <h1>MultiForex Demo Platform</h1>
  <p><strong>A paper-trading environment for Forex and CFD workflows.</strong></p>
  <p><img alt="PHP 8+" src="https://img.shields.io/badge/PHP%208%2B-243447?style=flat-square"> <img alt="MySQL" src="https://img.shields.io/badge/MySQL-243447?style=flat-square"> <img alt="Demo trading" src="https://img.shields.io/badge/Demo%20trading-243447?style=flat-square"> <img alt="Admin CMS" src="https://img.shields.io/badge/Admin%20CMS-243447?style=flat-square"> <img alt="Apache" src="https://img.shields.io/badge/Apache-243447?style=flat-square"></p>
</div>

---

## Overview

A multi-phase PHP application for simulated market data and virtual trading. The current route file includes public market pages and calculators, client registration and account tools, a demo trade API, support and KYC demonstration flows, plus an administrator workspace. It is not connected to real-money execution.

> [!NOTE]
> This is a public source snapshot prepared from a local project. It includes implementation code and safe schema/sample data, without live records or credentials.

## What the software can do

| Area | Implemented scope |
| --- | --- |
| **Public website** | Markets, instruments, platform, educational pages, FAQ, contact and four calculators. |
| **Demo accounts** | Registration, sign-in, reset flow and a client dashboard with watchlists and notifications. |
| **Virtual trading** | Simulated quotes/candles, orders, positions, margin, trade history and demo funds. |
| **Client operations** | Verification profile/document workflow and support tickets with replies and attachments. |
| **Admin operations** | Users, accounts, instruments, trading settings, CMS, contacts and protected role permissions. |
| **SEO and content** | Editable articles/pages/FAQs with robots and sitemap routes. |

### Typical workflow

1. A visitor browses market information and creates a virtual account.
2. A demo provider supplies simulated quotes; the user places virtual orders and reviews positions/history.
3. Staff manage instruments, CMS and client requests from the admin area.

## Run locally

1. Use PHP 8+ with PDO MySQL, Apache `mod_rewrite` and a MySQL database.
2. Copy `config/config.example.php` to `config/config.php` and set a local `SITE_URL` and new database credentials.
3. Import `database/database.sql` into an empty database, then follow the numbered/phase migration notes for the current code.
4. Open the site through Apache. Create the first administrator using the CLI-only tools documented in `database/phase18-admin-security.md`.

## Database and sample content

The base schema and fictional market instrument seed are in `database/database.sql`; additive migrations and their notes live under `database/`. The repository contains no account, KYC or support data from a running installation.

## Repository guide

| Path | Purpose |
| --- | --- |
| `index.php` | Front controller and route map |
| `controllers/`, `services/` | Public/client/admin workflows and demo provider logic |
| `views/`, `assets/` | Page components and frontend assets |
| `database/` | Schema, migrations and setup notes |
| `tools/` | CLI setup, health and calculation checks |

## Security and privacy

- Client and admin sessions have separate authorization paths.
- Private KYC files and configuration must be inaccessible over HTTP.
- The production template keeps real trading disabled and demo mode selected.

## Current boundaries

- All balances, prices and trades are simulated; there is no broker feed, real-money execution or payment processor.
- Production hosting, mail delivery and full authenticated browser flows require separate acceptance testing.

## Verification

Run `php tools/phase24-calculation-tests.php`, provider safety checks and the CLI production health check after configuring a disposable environment.

---

<div align="center">

Software Development by <a href="https://www.energeticedge.lk/" target="_blank" rel="noopener noreferrer">Energetic Edge (Pvt) Ltd</a>

</div>
