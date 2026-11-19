<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$config = require $root . '/config/config.php';
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['DB_HOST'], $config['DB_PORT'], $config['DB_NAME']);
$pdo = new PDO($dsn, (string)$config['DB_USER'], (string)$config['DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$database = (string)$config['DB_NAME'];
$exists = static function (string $query, array $params) use ($pdo): bool { $statement=$pdo->prepare($query); $statement->execute($params); return (int)$statement->fetchColumn()>0; };
$columnExists = static fn(string $table,string $column): bool=>$exists('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',[$database,$table,$column]);
$indexExists = static fn(string $table,string $index): bool=>$exists('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?',[$database,$table,$index]);
$constraintExists = static fn(string $table,string $name): bool=>$exists('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME=?',[$database,$table,$name]);

if (!$columnExists('orders','client_order_id')) $pdo->exec('ALTER TABLE orders ADD COLUMN client_order_id VARCHAR(64) NULL AFTER provider_order_id');
if (!$indexExists('orders','uq_orders_account_client_token')) $pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_account_client_token (demo_account_id,user_id,client_order_id)');
if (!$columnExists('positions','originating_order_id')) $pdo->exec('ALTER TABLE positions ADD COLUMN originating_order_id BIGINT UNSIGNED NULL AFTER instrument_id');
if (!$indexExists('positions','uq_positions_originating_order')) $pdo->exec('ALTER TABLE positions ADD UNIQUE KEY uq_positions_originating_order (originating_order_id)');
if (!$constraintExists('positions','fk_positions_originating_order')) $pdo->exec('ALTER TABLE positions ADD CONSTRAINT fk_positions_originating_order FOREIGN KEY (originating_order_id) REFERENCES orders (id) ON DELETE RESTRICT ON UPDATE CASCADE');
$pdo->exec("CREATE TABLE IF NOT EXISTS trading_rate_limits (
    user_id BIGINT UNSIGNED NOT NULL,
    demo_account_id BIGINT UNSIGNED NOT NULL,
    window_started_at DATETIME NOT NULL,
    request_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,demo_account_id),
    CONSTRAINT fk_trading_rate_limits_account_user FOREIGN KEY (demo_account_id,user_id) REFERENCES demo_accounts (id,user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Phase 11 demo trading migration is applied to {$database}." . PHP_EOL;
