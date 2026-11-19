# Phase 17: Notifications and Demo Support

Apply `migrations/phase17_notifications_support.sql` once to the existing Phase 02 database. It adds event references and a per-user idempotency key, expands ticket statuses, adds message read/idempotency fields, creates private attachment metadata, and registers the future `manage_support` permission. Existing tickets and notifications are retained.

Support attachments are optional, limited to one per message, validated by extension and server MIME, and stored under `storage/private/support/` with random names. Downloads require an authenticated owner check. Keep `storage/private/` denied by Apache and outside any public web root; grant the PHP process write access to the private storage directory on WAMP/cPanel.

Demo limits are configurable in `config/config.php`: 5 MiB per attachment, 50 MiB and 10 attachments per user, three ticket creations per ten minutes, six replies per minute, and 10,000 message characters. Support file types are PDF, JPEG, PNG, and TXT only.

Notifications are retained and paginated; there is no automatic deletion in this phase. Review retention needs as activity grows, then apply an explicit retention policy without deleting ticket or trading records. All client pages remain private/no-store and noindex.

This provides a demo support inbox only. It does not provide live chat, external support providers, or a response-time guarantee. Future staff service calls must use an authenticated admin with the `manage_support` permission. No Admin UI is included.
