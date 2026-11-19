<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__); $config=require $root.'/config/config.php';
$dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$config['DB_HOST'],$config['DB_PORT'],$config['DB_NAME']);
$pdo=new PDO($dsn,(string)$config['DB_USER'],(string)$config['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
$check->execute([(string)$config['DB_NAME'],'positions','required_margin']);
if ((int)$check->fetchColumn()===0) $pdo->exec("ALTER TABLE positions ADD COLUMN required_margin DECIMAL(20,8) NULL COMMENT 'Account-currency margin snapshot reserved when this demo position opened' AFTER current_price");
require $root.'/includes/bootstrap.php';
$filled=\App\Services\AccountMetricsService::demo()->backfillMarginSnapshots();
$pending=(int)\App\Database::connection()->query("SELECT COUNT(*) FROM positions WHERE status='open' AND required_margin IS NULL")->fetchColumn();
echo "Phase 12 account metrics migration is applied to {$config['DB_NAME']}; backfilled {$filled} open-position margin snapshot(s); {$pending} remain degraded.".PHP_EOL;
