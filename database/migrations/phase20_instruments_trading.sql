-- Phase 20: additive Admin instrument and Demo market controls. Apply once after Phase 19 and Phase 10.
ALTER TABLE instruments
    ADD COLUMN profit_currency CHAR(3) NULL AFTER quote_currency,
    ADD COLUMN tick_size DECIMAL(20,10) NULL AFTER precision_digits,
    ADD COLUMN pip_size DECIMAL(20,10) NULL AFTER tick_size,
    ADD COLUMN min_volume DECIMAL(20,8) NOT NULL DEFAULT 0.01000000 AFTER demo_volatility,
    ADD COLUMN max_volume DECIMAL(20,8) NOT NULL DEFAULT 100.00000000 AFTER min_volume,
    ADD COLUMN volume_step DECIMAL(20,8) NOT NULL DEFAULT 0.01000000 AFTER max_volume,
    ADD COLUMN trading_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER status,
    ADD COLUMN market_data_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER trading_enabled,
    ADD COLUMN config_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER market_data_enabled;

UPDATE instruments i
LEFT JOIN instrument_categories c ON c.id=i.category_id
SET i.profit_currency=COALESCE(i.quote_currency,'USD'),
    i.tick_size=POWER(10,-i.precision_digits),
    i.pip_size=CASE WHEN c.slug='forex' THEN POWER(10,-GREATEST(1,i.precision_digits-1)) ELSE NULL END;

ALTER TABLE instruments
    MODIFY COLUMN profit_currency CHAR(3) NOT NULL,
    ADD KEY idx_instruments_controls (category_id,status,trading_enabled,market_data_enabled),
    ADD CONSTRAINT chk_instruments_tick_size CHECK (tick_size IS NULL OR tick_size > 0),
    ADD CONSTRAINT chk_instruments_pip_size CHECK (pip_size IS NULL OR pip_size > 0),
    ADD CONSTRAINT chk_instruments_volumes CHECK (min_volume > 0 AND max_volume >= min_volume AND volume_step > 0),
    ADD CONSTRAINT chk_instruments_volatility CHECK (demo_volatility > 0 AND demo_volatility <= 0.08000000);

ALTER TABLE market_simulation_state
    ADD COLUMN seed_price DECIMAL(24,10) NULL AFTER instrument_id;

UPDATE market_simulation_state s
JOIN instruments i ON i.id=s.instrument_id
SET s.seed_price=i.base_price
WHERE s.seed_price IS NULL;

ALTER TABLE market_simulation_state
    MODIFY COLUMN seed_price DECIMAL(24,10) NOT NULL;

INSERT INTO site_settings (setting_key,setting_value,setting_type,is_public) VALUES
    ('demo_trading_enabled','1','boolean',0),
    ('demo_market_data_enabled','1','boolean',0),
    ('market_orders_enabled','1','boolean',0),
    ('max_open_positions_per_account','100','integer',0),
    ('max_order_volume','100.00','decimal',0);
