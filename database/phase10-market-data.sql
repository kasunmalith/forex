-- Phase 10 additive demo market data storage. Safe to run repeatedly.
CREATE TABLE IF NOT EXISTS market_quotes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, instrument_id BIGINT UNSIGNED NOT NULL,
 bid DECIMAL(24,10) NOT NULL, ask DECIMAL(24,10) NOT NULL, mid_price DECIMAL(24,10) NOT NULL, spread DECIMAL(24,10) NOT NULL,
 change_value DECIMAL(24,10) NOT NULL DEFAULT 0, change_percent DECIMAL(18,10) NOT NULL DEFAULT 0,
 day_open DECIMAL(24,10) NOT NULL, day_high DECIMAL(24,10) NOT NULL, day_low DECIMAL(24,10) NOT NULL, previous_close DECIMAL(24,10) NOT NULL,
 quote_timestamp DATETIME NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY uq_market_quotes_instrument (instrument_id), KEY idx_market_quotes_timestamp (quote_timestamp),
 CONSTRAINT fk_market_quotes_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT chk_market_quotes_bid_ask CHECK (ask >= bid), CONSTRAINT chk_market_quotes_spread CHECK (spread >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_simulation_state (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, instrument_id BIGINT UNSIGNED NOT NULL,
 current_mid_price DECIMAL(24,10) NOT NULL, previous_mid_price DECIMAL(24,10) NOT NULL,
 session_open DECIMAL(24,10) NOT NULL, session_high DECIMAL(24,10) NOT NULL, session_low DECIMAL(24,10) NOT NULL,
 previous_close DECIMAL(24,10) NOT NULL, volatility_factor DECIMAL(12,10) NOT NULL, trend_bias DECIMAL(12,10) NOT NULL DEFAULT 0,
 last_simulated_at DATETIME NOT NULL, session_day DATE NOT NULL, seed_value BIGINT UNSIGNED NOT NULL DEFAULT 1,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY uq_market_sim_state_instrument (instrument_id),
 CONSTRAINT fk_market_sim_state_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_candles (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, instrument_id BIGINT UNSIGNED NOT NULL, timeframe VARCHAR(3) NOT NULL, open_time DATETIME NOT NULL,
 open DECIMAL(24,10) NOT NULL, high DECIMAL(24,10) NOT NULL, low DECIMAL(24,10) NOT NULL, close DECIMAL(24,10) NOT NULL,
 tick_count INT UNSIGNED NOT NULL DEFAULT 0, is_closed BOOLEAN NOT NULL DEFAULT FALSE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY uq_market_candle_bucket (instrument_id,timeframe,open_time), KEY idx_market_candle_history (instrument_id,timeframe,open_time),
 CONSTRAINT fk_market_candles_instrument FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT chk_market_candles_range CHECK (low <= high AND open >= low AND open <= high AND close >= low AND close <= high)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
