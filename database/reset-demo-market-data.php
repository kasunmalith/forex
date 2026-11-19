<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/config/config.php';
if (PHP_SAPI !== 'cli' || ($config['APP_ENV'] ?? '') !== 'development' || !in_array('--confirm-reset-demo-market-data', $argv ?? [], true)) {
    fwrite(STDERR, "Refused. Run only in development CLI with --confirm-reset-demo-market-data.\n");
    exit(2);
}
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['DB_HOST'], $config['DB_PORT'], $config['DB_NAME']);
$pdo = new PDO($dsn, (string)$config['DB_USER'], (string)$config['DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM market_candles');
    $pdo->exec('DELETE FROM market_quotes');
    $pdo->exec('DELETE FROM market_simulation_state');
    $pdo->commit();
    fwrite(STDOUT, "Demo market state, quotes, and candles cleared. Seed history and state will regenerate on the next market-data request.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Reset failed; no trading tables were changed.\n");
    exit(1);
}
