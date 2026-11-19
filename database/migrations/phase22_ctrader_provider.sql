-- Phase 22: additive provider symbol identifier for explicit cTrader mappings.
-- Does not create mappings, connect accounts, store tokens, or change trading state.
SET @phase22_provider_symbol_id_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'instrument_provider_mappings'
       AND COLUMN_NAME = 'provider_symbol_id') = 0,
    'ALTER TABLE instrument_provider_mappings ADD COLUMN provider_symbol_id VARCHAR(80) NULL AFTER provider_symbol',
    'SELECT 1'
);
PREPARE phase22_provider_symbol_id_stmt FROM @phase22_provider_symbol_id_ddl;
EXECUTE phase22_provider_symbol_id_stmt;
DEALLOCATE PREPARE phase22_provider_symbol_id_stmt;
