-- Forex Platform Phase 02 installation source
-- Demo / paper trading data only. Instrument values below are seeded demo configuration,
-- not live or indicative market prices. Import into an empty MySQL 8.0+ server/schema.
CREATE DATABASE IF NOT EXISTS forex
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE forex;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_reference VARCHAR(20) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(40) NULL,
    country CHAR(2) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','suspended','disabled','pending','closed') NOT NULL DEFAULT 'pending',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_reference (user_reference),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    date_of_birth DATE NULL,
    address_line_1 VARCHAR(190) NULL,
    address_line_2 VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    state_region VARCHAR(120) NULL,
    postal_code VARCHAR(32) NULL,
    country CHAR(2) NULL,
    nationality CHAR(2) NULL,
    profile_image VARCHAR(255) NULL COMMENT 'Relative, non-sensitive media path only',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_profiles_user (user_id),
    CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE demo_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    account_number VARCHAR(32) NOT NULL,
    account_type ENUM('standard','pro','ecn_demo') NOT NULL DEFAULT 'standard',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    balance DECIMAL(20,2) NOT NULL DEFAULT 0.00 COMMENT 'Demo ledger balance snapshot; adjusted through transactions',
    realized_pl DECIMAL(20,8) NOT NULL DEFAULT 0.00000000 COMMENT 'Demo realized P/L summary; completed trades remain the source history',
    status ENUM('active','suspended','disabled','closed') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_demo_accounts_number (account_number),
    UNIQUE KEY uq_demo_accounts_id_user (id, user_id),
    KEY idx_demo_accounts_user_status (user_id, status),
    CONSTRAINT fk_demo_accounts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE instrument_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(90) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_instrument_categories_slug (slug),
    KEY idx_instrument_categories_status_order (status, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE instruments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    symbol VARCHAR(32) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    base_currency CHAR(10) NULL,
    quote_currency CHAR(10) NULL,
    profit_currency CHAR(3) NULL,
    base_price DECIMAL(20,10) NOT NULL COMMENT 'Seeded demo reference price, not a live quote',
    bid DECIMAL(20,10) NOT NULL COMMENT 'Seeded demo quote only',
    ask DECIMAL(20,10) NOT NULL COMMENT 'Seeded demo quote only',
    spread DECIMAL(20,10) NOT NULL COMMENT 'Absolute demo price difference: ask minus bid',
    precision_digits TINYINT UNSIGNED NOT NULL,
    tick_size DECIMAL(20,10) NULL,
    pip_size DECIMAL(20,10) NULL,
    contract_size DECIMAL(20,4) NOT NULL DEFAULT 1.0000,
    margin_rate DECIMAL(10,8) NOT NULL DEFAULT 0.01000000 COMMENT 'Fraction of notional value, for future demo calculations',
    demo_volatility DECIMAL(10,8) NOT NULL DEFAULT 0.00100000 COMMENT 'Illustrative fractional parameter for a future simulator',
    min_volume DECIMAL(20,8) NOT NULL DEFAULT 0.01000000,
    max_volume DECIMAL(20,8) NOT NULL DEFAULT 100.00000000,
    volume_step DECIMAL(20,8) NOT NULL DEFAULT 0.01000000,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    trading_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    market_data_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    config_version INT UNSIGNED NOT NULL DEFAULT 1,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_instruments_symbol (symbol),
    KEY idx_instruments_category_status_order (category_id, status, display_order),
    KEY idx_instruments_status_symbol (status, symbol),
    KEY idx_instruments_controls (category_id, status, trading_enabled, market_data_enabled),
    CONSTRAINT fk_instruments_category FOREIGN KEY (category_id) REFERENCES instrument_categories (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_instruments_quote_order CHECK (ask >= bid),
    CONSTRAINT chk_instruments_spread CHECK (spread >= 0),
    CONSTRAINT chk_instruments_margin CHECK (margin_rate > 0 AND margin_rate <= 1),
    CONSTRAINT chk_instruments_contract_size CHECK (contract_size > 0),
    CONSTRAINT chk_instruments_tick_size CHECK (tick_size IS NULL OR tick_size > 0),
    CONSTRAINT chk_instruments_pip_size CHECK (pip_size IS NULL OR pip_size > 0),
    CONSTRAINT chk_instruments_volumes CHECK (min_volume > 0 AND max_volume >= min_volume AND volume_step > 0),
    CONSTRAINT chk_instruments_volatility CHECK (demo_volatility > 0 AND demo_volatility <= 0.08000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE instrument_provider_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    instrument_id BIGINT UNSIGNED NOT NULL,
    provider_code VARCHAR(40) NOT NULL,
    provider_symbol VARCHAR(80) NOT NULL,
    provider_symbol_id VARCHAR(80) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_mapping_symbol (provider_code, provider_symbol),
    UNIQUE KEY uq_provider_mapping_instrument (instrument_id, provider_code),
    CONSTRAINT fk_provider_mappings_instrument FOREIGN KEY (instrument_id) REFERENCES instruments (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE watchlists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    instrument_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_watchlists_user_instrument (user_id, instrument_id),
    KEY idx_watchlists_instrument (instrument_id),
    CONSTRAINT fk_watchlists_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_watchlists_instrument FOREIGN KEY (instrument_id) REFERENCES instruments (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    demo_account_id BIGINT UNSIGNED NOT NULL,
    instrument_id BIGINT UNSIGNED NOT NULL,
    provider_order_id VARCHAR(120) NULL,
    order_type ENUM('market','buy_limit','sell_limit','buy_stop','sell_stop') NOT NULL,
    direction ENUM('buy','sell') NOT NULL,
    volume DECIMAL(20,8) NOT NULL,
    requested_price DECIMAL(20,10) NULL,
    execution_price DECIMAL(20,10) NULL,
    stop_loss DECIMAL(20,10) NULL,
    take_profit DECIMAL(20,10) NULL,
    status ENUM('pending','executed','cancelled','rejected') NOT NULL DEFAULT 'pending',
    opened_at DATETIME NULL,
    executed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user_status_created (user_id, status, created_at),
    KEY idx_orders_account_status_created (demo_account_id, status, created_at),
    KEY idx_orders_instrument_status (instrument_id, status),
    KEY idx_orders_provider_order (provider_order_id),
    CONSTRAINT fk_orders_account_user FOREIGN KEY (demo_account_id, user_id) REFERENCES demo_accounts (id, user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_orders_instrument FOREIGN KEY (instrument_id) REFERENCES instruments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_orders_volume CHECK (volume > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE positions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    demo_account_id BIGINT UNSIGNED NOT NULL,
    instrument_id BIGINT UNSIGNED NOT NULL,
    provider_position_id VARCHAR(120) NULL,
    direction ENUM('buy','sell') NOT NULL,
    volume DECIMAL(20,8) NOT NULL,
    initial_volume DECIMAL(20,8) NOT NULL DEFAULT 0,
    open_price DECIMAL(20,10) NOT NULL,
    current_price DECIMAL(20,10) NULL COMMENT 'Optional demo quote snapshot; floating P/L is calculated later',
    required_margin DECIMAL(20,8) NULL COMMENT 'Account-currency margin snapshot reserved when this demo position opened',
    stop_loss DECIMAL(20,10) NULL,
    take_profit DECIMAL(20,10) NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    opened_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    close_price DECIMAL(20,10) NULL,
    realized_pl DECIMAL(20,8) NULL,
    close_reason ENUM('manual','stop_loss','take_profit') NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_positions_id_account_user (id, demo_account_id, user_id),
    KEY idx_positions_user_status_opened (user_id, status, opened_at),
    KEY idx_positions_account_status_opened (demo_account_id, status, opened_at),
    KEY idx_positions_instrument_status (instrument_id, status),
    KEY idx_positions_provider_id (provider_position_id),
    CONSTRAINT fk_positions_account_user FOREIGN KEY (demo_account_id, user_id) REFERENCES demo_accounts (id, user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_positions_instrument FOREIGN KEY (instrument_id) REFERENCES instruments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_positions_volume CHECK (volume > 0),
    CONSTRAINT chk_positions_close_fields CHECK ((status = 'open' AND closed_at IS NULL) OR (status = 'closed' AND closed_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trade_reference VARCHAR(40) NULL,
    idempotency_key CHAR(64) NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    demo_account_id BIGINT UNSIGNED NOT NULL,
    position_id BIGINT UNSIGNED NULL,
    instrument_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('buy','sell') NOT NULL,
    volume DECIMAL(20,8) NOT NULL,
    open_price DECIMAL(20,10) NOT NULL,
    close_price DECIMAL(20,10) NOT NULL,
    profit_loss DECIMAL(20,8) NOT NULL,
    close_reason ENUM('manual','partial','stop_loss','take_profit') NOT NULL DEFAULT 'manual',
    raw_profit_loss DECIMAL(20,8) NULL,
    profit_currency CHAR(3) NULL,
    conversion_rate DECIMAL(20,12) NULL,
    opened_at DATETIME NOT NULL,
    closed_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_trades_reference (trade_reference),
    UNIQUE KEY uq_trades_idempotency (demo_account_id,idempotency_key),
    KEY idx_trades_user_closed (user_id, closed_at),
    KEY idx_trades_account_closed (demo_account_id, closed_at),
    KEY idx_trades_instrument_closed (instrument_id, closed_at),
    CONSTRAINT fk_trades_account_user FOREIGN KEY (demo_account_id, user_id) REFERENCES demo_accounts (id, user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_trades_position_owner FOREIGN KEY (position_id, demo_account_id, user_id) REFERENCES positions (id, demo_account_id, user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_trades_instrument FOREIGN KEY (instrument_id) REFERENCES instruments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_trades_volume CHECK (volume > 0),
    CONSTRAINT chk_trades_dates CHECK (closed_at >= opened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE position_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    demo_account_id BIGINT UNSIGNED NOT NULL,
    position_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('close','partial_close','protection_modified','stop_loss','take_profit') NOT NULL,
    volume DECIMAL(20,8) NULL,
    execution_price DECIMAL(20,10) NULL,
    realized_pl DECIMAL(20,8) NULL,
    old_stop_loss DECIMAL(20,10) NULL,
    new_stop_loss DECIMAL(20,10) NULL,
    old_take_profit DECIMAL(20,10) NULL,
    new_take_profit DECIMAL(20,10) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_position_events_position (position_id,created_at),
    KEY idx_position_events_owner (user_id,demo_account_id,created_at),
    CONSTRAINT fk_position_events_position FOREIGN KEY (position_id,demo_account_id,user_id) REFERENCES positions (id,demo_account_id,user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_position_events_account FOREIGN KEY (demo_account_id,user_id) REFERENCES demo_accounts (id,user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    demo_account_id BIGINT UNSIGNED NOT NULL,
    transaction_type ENUM('demo_deposit','demo_withdrawal','trade_profit','trade_loss','admin_adjustment','account_reset') NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    idempotency_key CHAR(32) NULL,
    created_by_admin_id BIGINT UNSIGNED NULL,
    balance_before DECIMAL(20,2) NOT NULL,
    balance_after DECIMAL(20,2) NOT NULL,
    reference_type VARCHAR(50) NULL COMMENT 'Polymorphic audit reference; intentionally not a foreign key',
    reference_id BIGINT UNSIGNED NULL,
    description VARCHAR(500) NULL,
    status ENUM('pending','completed','rejected') NOT NULL DEFAULT 'completed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_transactions_account_type_created (demo_account_id, transaction_type, created_at),
    KEY idx_transactions_user_created (user_id, created_at),
    KEY idx_transactions_reference (reference_type, reference_id),
    UNIQUE KEY uq_transactions_admin_adjustment (demo_account_id, idempotency_key),
    KEY idx_transactions_admin_actor (created_by_admin_id, created_at),
    CONSTRAINT fk_transactions_account_user FOREIGN KEY (demo_account_id, user_id) REFERENCES demo_accounts (id, user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE deposit_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    demo_account_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(20,2) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(500) NULL,
    processed_by BIGINT UNSIGNED NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_deposit_requests_user_status_created (user_id, status, created_at),
    KEY idx_deposit_requests_account_status (demo_account_id, status),
    KEY idx_deposit_requests_processor (processed_by),
    CONSTRAINT fk_deposit_requests_account_user FOREIGN KEY (demo_account_id, user_id) REFERENCES demo_accounts (id, user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_deposit_requests_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE withdrawal_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    demo_account_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(20,2) NOT NULL,
    user_note VARCHAR(500) NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(500) NULL,
    processed_by BIGINT UNSIGNED NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_withdrawal_requests_user_status_created (user_id, status, created_at),
    KEY idx_withdrawal_requests_account_status (demo_account_id, status),
    KEY idx_withdrawal_requests_processor (processed_by),
    CONSTRAINT fk_withdrawal_requests_account_user FOREIGN KEY (demo_account_id, user_id) REFERENCES demo_accounts (id, user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_withdrawal_requests_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kyc_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    legal_first_name VARCHAR(100) NOT NULL,
    legal_last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NULL,
    country CHAR(2) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    postal_code VARCHAR(32) NULL,
    status ENUM('not_submitted','pending','verified','rejected') NOT NULL DEFAULT 'not_submitted',
    rejection_reason VARCHAR(500) NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kyc_submissions_id_user (id, user_id),
    KEY idx_kyc_submissions_user_status (user_id, status),
    KEY idx_kyc_submissions_status_created (status, created_at),
    KEY idx_kyc_submissions_reviewer (reviewed_by),
    CONSTRAINT fk_kyc_submissions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kyc_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kyc_submission_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    document_type ENUM('passport','national_id','driver_licence','proof_of_address','other') NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL COMMENT 'Private relative storage path; never a public URL',
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kyc_documents_submission_status (kyc_submission_id, status),
    KEY idx_kyc_documents_user_created (user_id, created_at),
    CONSTRAINT fk_kyc_documents_submission_owner FOREIGN KEY (kyc_submission_id, user_id) REFERENCES kyc_submissions (id, user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) NULL COMMENT 'Internal relative path only',
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_notifications_user_unread_created (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_roles_slug (slug),
    UNIQUE KEY uq_admin_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email),
    KEY idx_admins_role_status (role_id, status),
    CONSTRAINT fk_admins_role FOREIGN KEY (role_id) REFERENCES admin_roles (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transactions
    ADD CONSTRAINT fk_transactions_admin_actor FOREIGN KEY (created_by_admin_id) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE admin_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    permission_key VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_permissions_key (permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_admin_role_permissions_permission (permission_id),
    CONSTRAINT fk_admin_role_permissions_role FOREIGN KEY (role_id) REFERENCES admin_roles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_admin_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES admin_permissions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE deposit_requests
    ADD CONSTRAINT fk_deposit_requests_processor FOREIGN KEY (processed_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE withdrawal_requests
    ADD CONSTRAINT fk_withdrawal_requests_processor FOREIGN KEY (processed_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE kyc_submissions
    ADD CONSTRAINT fk_kyc_submissions_reviewer FOREIGN KEY (reviewed_by) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE support_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    ticket_number VARCHAR(32) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    category VARCHAR(80) NOT NULL,
    priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    status ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
    assigned_admin_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_support_tickets_number (ticket_number),
    KEY idx_support_tickets_user_status (user_id, status),
    KEY idx_support_tickets_status_priority_created (status, priority, created_at),
    KEY idx_support_tickets_assigned_admin (assigned_admin_id),
    CONSTRAINT fk_support_tickets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_support_tickets_admin FOREIGN KEY (assigned_admin_id) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('user','admin') NOT NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    sender_admin_id BIGINT UNSIGNED NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_support_messages_ticket_created (ticket_id, created_at),
    KEY idx_support_messages_sender_user (sender_user_id),
    KEY idx_support_messages_sender_admin (sender_admin_id),
    CONSTRAINT fk_support_messages_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_support_messages_user FOREIGN KEY (sender_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_support_messages_admin FOREIGN KEY (sender_admin_id) REFERENCES admins (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    description VARCHAR(1000) NULL COMMENT 'Never include passwords, tokens, or other secrets',
    ip_address VARBINARY(16) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_activity_admin_created (admin_id, created_at),
    KEY idx_admin_activity_action (action),
    KEY idx_admin_activity_created (created_at),
    CONSTRAINT fk_admin_activity_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(110) NOT NULL,
    description VARCHAR(255) NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_article_categories_slug (slug),
    KEY idx_article_categories_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE articles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(240) NOT NULL,
    excerpt TEXT NULL,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(255) NULL COMMENT 'Relative path only',
    image_alt VARCHAR(255) NULL,
    author_admin_id BIGINT UNSIGNED NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    seo_title VARCHAR(220) NULL,
    meta_description VARCHAR(320) NULL,
    og_image VARCHAR(255) NULL COMMENT 'Relative path only',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_articles_slug (slug),
    KEY idx_articles_category_status_published (category_id, status, published_at),
    KEY idx_articles_status_published (status, published_at),
    KEY idx_articles_author (author_admin_id),
    CONSTRAINT fk_articles_category FOREIGN KEY (category_id) REFERENCES article_categories (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_articles_author FOREIGN KEY (author_admin_id) REFERENCES admins (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faqs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category VARCHAR(100) NOT NULL,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_faqs_status_category_order (status, category, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(40) NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
    admin_note VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_messages_status_created (status, created_at),
    KEY idx_contact_messages_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string','text','integer','decimal','boolean','json') NOT NULL DEFAULT 'string',
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_settings_key (setting_key),
    KEY idx_site_settings_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    content LONGTEXT NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    seo_title VARCHAR(220) NULL,
    meta_description VARCHAR(320) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pages_slug (slug),
    KEY idx_pages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_slug_redirects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type ENUM('article','page') NOT NULL,
    old_slug VARCHAR(240) NOT NULL,
    target_slug VARCHAR(240) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cms_slug_redirect (entity_type, old_slug),
    KEY idx_cms_slug_target (entity_type, target_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO instrument_categories (name, slug, description, display_order) VALUES
('Forex', 'forex', 'Currency pair demo instruments.', 1),
('Metals', 'metals', 'Metal demo instruments.', 2),
('Indices', 'indices', 'Index demo instruments.', 3),
('Commodities', 'commodities', 'Commodity demo instruments.', 4),
('Stocks', 'stocks', 'Stock category reserved for later demo instruments.', 5),
('Digital Assets', 'digital-assets', 'Digital asset demo instruments.', 6);

INSERT INTO instruments (category_id, symbol, display_name, base_currency, quote_currency, base_price, bid, ask, spread, precision_digits, contract_size, margin_rate, demo_volatility, display_order) VALUES
((SELECT id FROM instrument_categories WHERE slug='forex'),'EURUSD','Euro / US Dollar','EUR','USD',1.0842000000,1.0840800000,1.0843200000,0.0002400000,5,100000.0000,0.01000000,0.00120000,1),
((SELECT id FROM instrument_categories WHERE slug='forex'),'GBPUSD','British Pound / US Dollar','GBP','USD',1.2715000000,1.2713000000,1.2717000000,0.0004000000,5,100000.0000,0.01000000,0.00150000,2),
((SELECT id FROM instrument_categories WHERE slug='forex'),'USDJPY','US Dollar / Japanese Yen','USD','JPY',149.2500000000,149.2300000000,149.2700000000,0.0400000000,3,100000.0000,0.01000000,0.00140000,3),
((SELECT id FROM instrument_categories WHERE slug='forex'),'AUDUSD','Australian Dollar / US Dollar','AUD','USD',0.6621000000,0.6619600000,0.6622400000,0.0002800000,5,100000.0000,0.01000000,0.00150000,4),
((SELECT id FROM instrument_categories WHERE slug='forex'),'USDCAD','US Dollar / Canadian Dollar','USD','CAD',1.3652000000,1.3650200000,1.3653800000,0.0003600000,5,100000.0000,0.01000000,0.00140000,5),
((SELECT id FROM instrument_categories WHERE slug='forex'),'USDCHF','US Dollar / Swiss Franc','USD','CHF',0.8950000000,0.8948200000,0.8951800000,0.0003600000,5,100000.0000,0.01000000,0.00130000,6),
((SELECT id FROM instrument_categories WHERE slug='forex'),'EURGBP','Euro / British Pound','EUR','GBP',0.8530000000,0.8528500000,0.8531500000,0.0003000000,5,100000.0000,0.01000000,0.00120000,7),
((SELECT id FROM instrument_categories WHERE slug='forex'),'EURJPY','Euro / Japanese Yen','EUR','JPY',161.7200000000,161.6950000000,161.7450000000,0.0500000000,3,100000.0000,0.01000000,0.00150000,8),
((SELECT id FROM instrument_categories WHERE slug='metals'),'XAUUSD','Gold / US Dollar','XAU','USD',2325.5000000000,2325.2500000000,2325.7500000000,0.5000000000,2,100.0000,0.02000000,0.00350000,1),
((SELECT id FROM instrument_categories WHERE slug='metals'),'XAGUSD','Silver / US Dollar','XAG','USD',29.1500000000,29.1200000000,29.1800000000,0.0600000000,3,5000.0000,0.02000000,0.00450000,2),
((SELECT id FROM instrument_categories WHERE slug='indices'),'US30','US 30 Index','US30','USD',38850.0000000000,38848.5000000000,38851.5000000000,3.0000000000,1,1.0000,0.05000000,0.00250000,1),
((SELECT id FROM instrument_categories WHERE slug='indices'),'NAS100','US Tech 100 Index','NAS100','USD',18100.0000000000,18098.5000000000,18101.5000000000,3.0000000000,1,1.0000,0.05000000,0.00300000,2),
((SELECT id FROM instrument_categories WHERE slug='indices'),'SPX500','US 500 Index','SPX500','USD',5100.0000000000,5099.5000000000,5100.5000000000,1.0000000000,1,1.0000,0.05000000,0.00200000,3),
((SELECT id FROM instrument_categories WHERE slug='indices'),'UK100','UK 100 Index','UK100','GBP',7900.0000000000,7898.5000000000,7901.5000000000,3.0000000000,1,1.0000,0.05000000,0.00250000,4),
((SELECT id FROM instrument_categories WHERE slug='indices'),'GER40','Germany 40 Index','GER40','EUR',17800.0000000000,17798.5000000000,17801.5000000000,3.0000000000,1,1.0000,0.05000000,0.00250000,5),
((SELECT id FROM instrument_categories WHERE slug='commodities'),'WTI','WTI Crude Oil','WTI','USD',78.5000000000,78.4500000000,78.5500000000,0.1000000000,2,1000.0000,0.05000000,0.00400000,1),
((SELECT id FROM instrument_categories WHERE slug='commodities'),'BRENT','Brent Crude Oil','BRENT','USD',83.2000000000,83.1500000000,83.2500000000,0.1000000000,2,1000.0000,0.05000000,0.00400000,2),
((SELECT id FROM instrument_categories WHERE slug='digital-assets'),'BTCUSD','Bitcoin / US Dollar','BTC','USD',65000.0000000000,64975.0000000000,65025.0000000000,50.0000000000,2,1.0000,0.10000000,0.01500000,1),
((SELECT id FROM instrument_categories WHERE slug='digital-assets'),'ETHUSD','Ether / US Dollar','ETH','USD',3400.0000000000,3398.0000000000,3402.0000000000,4.0000000000,2,1.0000,0.10000000,0.01800000,2);

UPDATE instruments i
JOIN instrument_categories c ON c.id=i.category_id
SET i.profit_currency=COALESCE(i.quote_currency,'USD'),
    i.tick_size=POWER(10,-i.precision_digits),
    i.pip_size=CASE WHEN c.slug='forex' THEN POWER(10,-GREATEST(1,i.precision_digits-1)) ELSE NULL END;

INSERT INTO instrument_provider_mappings (instrument_id, provider_code, provider_symbol)
SELECT id, 'demo', symbol FROM instruments;

INSERT INTO admin_roles (name, slug, description) VALUES
('Super Admin','super-admin','Full administrative role; no admin login is seeded.'),
('Administrator','administrator','General administration role.'),
('Support','support','Customer support role.');

INSERT INTO admin_permissions (permission_key, description) VALUES
('manage_users','Manage user records and status'),
('manage_accounts','Manage demo accounts'),
('manage_trading','Review demo orders, positions, trades, and ledger records'),
('manage_instruments','Manage instrument configuration'),
('manage_kyc','Review identity submissions and documents'),
('manage_funding','Review virtual demo deposit and withdrawal requests'),
('manage_support','Manage support tickets and messages'),
('manage_content','Manage articles, pages, FAQs, and contact messages'),
('manage_admins','Manage administrators, roles, and permissions'),
('manage_settings','Manage site settings'),
('view_logs','View administrative activity logs');

INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM admin_roles r CROSS JOIN admin_permissions p WHERE r.slug = 'super-admin';
INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM admin_roles r CROSS JOIN admin_permissions p
WHERE r.slug = 'administrator' AND p.permission_key IN ('manage_users','manage_accounts','manage_trading','manage_instruments','manage_kyc','manage_funding','manage_support','manage_content','manage_settings','view_logs');
INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM admin_roles r CROSS JOIN admin_permissions p
WHERE r.slug = 'support' AND p.permission_key IN ('manage_support','manage_kyc');

INSERT INTO article_categories (name, slug, description, display_order) VALUES
('Market News','market-news','Reserved category for original demo education content.',1),
('Education','education','Learning content category.',2),
('Forex','forex','Forex education category.',3),
('Commodities','commodities','Commodities education category.',4),
('Digital Assets','digital-assets','Digital asset education category.',5),
('Platform Updates','platform-updates','Platform update category.',6);

INSERT INTO site_settings (setting_key, setting_value, setting_type, is_public) VALUES
('company_name','MultiForex','string',1),
('company_tagline','Practice in a simulated trading environment','string',1),
('default_seo_title','MultiForex | Demo Global Trading Platform','string',1),
('default_seo_description','Explore simulated markets and demo trading tools with virtual funds on MultiForex.','text',1),
('footer_copyright','Demo environment','string',1),
('social_links','{}','json',1),
('default_demo_balance','100000.00','decimal',0),
('site_currency','USD','string',1),
('support_email','','string',1),
('demo_disclaimer','Demo Trading Environment - No Real Funds Are Used. All balances, instruments, and prices are simulated configuration for practice only.','text',1),
('risk_warning','Demo values are simulated and do not represent live market conditions or real funds.','text',1),
('trading_provider','demo','string',0),
('market_data_provider','demo','string',0),
('demo_trading_enabled','1','boolean',0),
('demo_market_data_enabled','1','boolean',0),
('market_orders_enabled','1','boolean',0),
('max_open_positions_per_account','100','integer',0),
('max_order_volume','100.00','decimal',0),
('environment_message','Demo Trading Platform','string',1);

INSERT INTO pages (title, slug, content, status, seo_title, meta_description) VALUES
('About','about','Placeholder page. Add accurate original company information before publication.','draft','About | MultiForex','Draft placeholder for the About page.'),
('Security','security','Placeholder page. Publish only security controls that have been implemented and verified.','draft','Security | MultiForex','Draft placeholder for the Security page.'),
('Terms','terms','Draft placeholder. Add terms reviewed for the actual operating jurisdiction before publication.','draft','Terms | MultiForex','Draft placeholder for Terms.'),
('Privacy','privacy','Draft placeholder. Add a privacy notice matching actual data collection and retention before publication.','draft','Privacy | MultiForex','Draft placeholder for Privacy.'),
('Risk Disclosure','risk-disclosure','Demo environment only. This page is a placeholder and is not a completed legal disclosure.','draft','Risk Disclosure | MultiForex','Draft placeholder for Risk Disclosure.');
