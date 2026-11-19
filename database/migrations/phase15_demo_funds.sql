-- Phase 15: additive support for idempotent virtual funding requests and client notes.
ALTER TABLE deposit_requests
    ADD COLUMN idempotency_key CHAR(32) NULL AFTER amount,
    ADD COLUMN user_note VARCHAR(200) NULL AFTER idempotency_key,
    ADD UNIQUE KEY uq_deposit_requests_idempotency (demo_account_id, idempotency_key);

ALTER TABLE withdrawal_requests
    ADD COLUMN idempotency_key CHAR(32) NULL AFTER amount,
    ADD UNIQUE KEY uq_withdrawal_requests_idempotency (demo_account_id, idempotency_key);

ALTER TABLE deposit_requests
    MODIFY status ENUM('pending','approved','rejected','cancelled','processed') NOT NULL DEFAULT 'pending';

ALTER TABLE withdrawal_requests
    MODIFY status ENUM('pending','approved','rejected','cancelled','processed') NOT NULL DEFAULT 'pending';
