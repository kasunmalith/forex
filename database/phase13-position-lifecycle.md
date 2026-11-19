# Phase 13: demo position lifecycle

Run `php database/apply-phase13-position-lifecycle.php` from the project root after deploying the additive schema update. The migration is safe to re-run and backfills each existing open position's original volume from its current volume.

Authenticated terminal state polling processes stop-loss and take-profit triggers for that user before returning current account metrics, positions and the latest 50 closed trade segments. The public market quote endpoints do not process user positions. A future scheduled processor must run as a CLI task and scope each batch to account owners; do not expose an unauthenticated web trigger.

Manual and automatic closes use the current demo quote and Phase 12 valuation/conversion services. BUY positions close at Bid; SELL positions close at Ask. Partial closes create one completed trade segment and ledger entry, preserve the open position's original entry fields, and release the same fraction of its stored margin. A repeated idempotency key returns the prior segment without applying P/L again.

SL/TP edits and removals are owner-scoped and validated against the current executable side. If an existing protection level has already triggered, the trigger is processed before an edit can remove it. All executions remain simulated and use virtual demo funds.
