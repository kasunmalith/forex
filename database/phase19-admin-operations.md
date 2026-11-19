# Phase 19 Admin operations

Apply `migrations/phase19_admin_operations.sql` once to an existing database after Phase 18. The migration preserves existing users, accounts, and transactions, assigns stable non-numeric references to existing users, adds the disabled state without removing existing states, and adds actor/idempotency fields for Admin virtual balance adjustments.

New registrations create random `MFU-` references in the application. Admin routes use those references and account numbers; they do not accept raw database IDs.

The Admin user and Demo Account lists use bounded server-side pagination. Detailed account valuation uses the Phase 12 `AccountMetricsService`. The adjustment workflow locks the Demo Account row before checking current metrics and writing the balance, ledger entry, and Admin audit event in one transaction. A debit is allowed only up to `min(current Virtual Balance, max(current authoritative Free Margin, 0))`; if Free Margin cannot be confirmed, the debit is rejected.

All financial values remain virtual Demo values. Status changes preserve history and never close positions. Suspending/disabling an account blocks new trades through the existing active-account check; existing positions remain open and the market engine may continue to apply their configured Stop Loss / Take Profit rules.
