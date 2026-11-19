-- Phase 11: additive demo execution support. Apply once to an existing Phase 02+ database.
-- Existing orders remain valid: legacy client_order_id and originating_order_id values are NULL.
ALTER TABLE orders
    ADD COLUMN client_order_id VARCHAR(64) NULL AFTER provider_order_id,
    ADD UNIQUE KEY uq_orders_account_client_token (demo_account_id, user_id, client_order_id);

ALTER TABLE positions
    ADD COLUMN originating_order_id BIGINT UNSIGNED NULL AFTER instrument_id,
    ADD UNIQUE KEY uq_positions_originating_order (originating_order_id),
    ADD CONSTRAINT fk_positions_originating_order FOREIGN KEY (originating_order_id) REFERENCES orders (id) ON DELETE RESTRICT ON UPDATE CASCADE;

CREATE TABLE trading_rate_limits (
    user_id BIGINT UNSIGNED NOT NULL,
    demo_account_id BIGINT UNSIGNED NOT NULL,
    window_started_at DATETIME NOT NULL,
    request_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, demo_account_id),
    CONSTRAINT fk_trading_rate_limits_account_user FOREIGN KEY (demo_account_id, user_id) REFERENCES demo_accounts (id, user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
