-- Phase 19: additive Admin operations support. Apply once after Phase 18.
-- Existing users receive stable references derived from their primary keys.
ALTER TABLE users
    ADD COLUMN user_reference VARCHAR(20) NULL AFTER id,
    MODIFY COLUMN status ENUM('active','suspended','disabled','pending','closed') NOT NULL DEFAULT 'pending';

UPDATE users
SET user_reference = CONCAT('MFU-', UPPER(SUBSTRING(SHA2(CONCAT('MultiForex-user-reference:', id), 256), 1, 16)))
WHERE user_reference IS NULL OR user_reference = '';

ALTER TABLE users
    MODIFY COLUMN user_reference VARCHAR(20) NOT NULL,
    ADD UNIQUE KEY uq_users_reference (user_reference);

ALTER TABLE demo_accounts
    MODIFY COLUMN status ENUM('active','suspended','disabled','closed') NOT NULL DEFAULT 'active',
    ADD KEY idx_demo_accounts_status_created (status, created_at);

ALTER TABLE transactions
    ADD COLUMN idempotency_key CHAR(32) NULL AFTER amount,
    ADD COLUMN created_by_admin_id BIGINT UNSIGNED NULL AFTER reference_id,
    ADD UNIQUE KEY uq_transactions_admin_adjustment (demo_account_id, idempotency_key),
    ADD KEY idx_transactions_admin_actor (created_by_admin_id, created_at),
    ADD CONSTRAINT fk_transactions_admin_actor FOREIGN KEY (created_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE;
