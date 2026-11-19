<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli') ini_set('session.save_path', sys_get_temp_dir());
require dirname(__DIR__) . '/includes/bootstrap.php';

use App\Database;

$pdo = Database::connection();
$columns = static function (string $table) use ($pdo): array {
    $q = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
    return array_column($q->fetchAll(), 'Field');
};
$add = static function (string $table, string $column, string $sql) use ($pdo, $columns): void {
    if (!in_array($column, $columns($table), true)) $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN ' . $sql);
};
$add('positions', 'initial_volume', '`initial_volume` DECIMAL(20,8) NOT NULL DEFAULT 0 AFTER `volume`');
$add('positions', 'close_reason', "`close_reason` ENUM('manual','stop_loss','take_profit') NULL AFTER `realized_pl`");
$add('trades', 'trade_reference', '`trade_reference` VARCHAR(40) NULL AFTER `id`');
$add('trades', 'idempotency_key', '`idempotency_key` CHAR(64) NULL AFTER `trade_reference`');
$add('trades', 'close_reason', "`close_reason` ENUM('manual','partial','stop_loss','take_profit') NOT NULL DEFAULT 'manual' AFTER `profit_loss`");
$add('trades', 'raw_profit_loss', '`raw_profit_loss` DECIMAL(20,8) NULL AFTER `close_reason`');
$add('trades', 'profit_currency', '`profit_currency` CHAR(3) NULL AFTER `raw_profit_loss`');
$add('trades', 'conversion_rate', '`conversion_rate` DECIMAL(20,12) NULL AFTER `profit_currency`');
$pdo->exec('UPDATE positions SET initial_volume=volume WHERE initial_volume=0');
$indexes = $pdo->query("SELECT DISTINCT INDEX_NAME FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='trades'")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('uq_trades_reference', $indexes, true)) $pdo->exec('ALTER TABLE trades ADD UNIQUE KEY uq_trades_reference (trade_reference)');
if (!in_array('uq_trades_idempotency', $indexes, true)) $pdo->exec('ALTER TABLE trades ADD UNIQUE KEY uq_trades_idempotency (demo_account_id,idempotency_key)');
$pdo->exec("CREATE TABLE IF NOT EXISTS position_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, demo_account_id BIGINT UNSIGNED NOT NULL,
 position_id BIGINT UNSIGNED NOT NULL, event_type ENUM('close','partial_close','protection_modified','stop_loss','take_profit') NOT NULL,
 volume DECIMAL(20,8) NULL, execution_price DECIMAL(20,10) NULL, realized_pl DECIMAL(20,8) NULL,
 old_stop_loss DECIMAL(20,10) NULL, new_stop_loss DECIMAL(20,10) NULL, old_take_profit DECIMAL(20,10) NULL, new_take_profit DECIMAL(20,10) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_position_events_position(position_id,created_at), KEY idx_position_events_owner(user_id,demo_account_id,created_at),
 CONSTRAINT fk_position_events_position FOREIGN KEY(position_id,demo_account_id,user_id) REFERENCES positions(id,demo_account_id,user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT fk_position_events_account FOREIGN KEY(demo_account_id,user_id) REFERENCES demo_accounts(id,user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Phase 13 migration complete.\n";
