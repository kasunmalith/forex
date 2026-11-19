<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);$config=require $root.'/config/config.php';
$dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$config['DB_HOST'],$config['DB_PORT'],$config['DB_NAME']);
$pdo=new PDO($dsn,(string)$config['DB_USER'],(string)$config['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db=(string)$config['DB_NAME'];
$hasColumn=static function(string $table,string $column)use($pdo,$db):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$db,$table,$column]);return(int)$q->fetchColumn()>0;};
$hasIndex=static function(string $table,string $name)use($pdo,$db):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?');$q->execute([$db,$table,$name]);return(int)$q->fetchColumn()>0;};
$hasConstraint=static function(string $table,string $name)use($pdo,$db):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME=?');$q->execute([$db,$table,$name]);return(int)$q->fetchColumn()>0;};
$columns=[
 'profit_currency'=>"ADD COLUMN profit_currency CHAR(3) NULL AFTER quote_currency",
 'tick_size'=>"ADD COLUMN tick_size DECIMAL(20,10) NULL AFTER precision_digits",
 'pip_size'=>"ADD COLUMN pip_size DECIMAL(20,10) NULL AFTER tick_size",
 'min_volume'=>"ADD COLUMN min_volume DECIMAL(20,8) NOT NULL DEFAULT 0.01000000 AFTER demo_volatility",
 'max_volume'=>"ADD COLUMN max_volume DECIMAL(20,8) NOT NULL DEFAULT 100.00000000 AFTER min_volume",
 'volume_step'=>"ADD COLUMN volume_step DECIMAL(20,8) NOT NULL DEFAULT 0.01000000 AFTER max_volume",
 'trading_enabled'=>"ADD COLUMN trading_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER status",
 'market_data_enabled'=>"ADD COLUMN market_data_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER trading_enabled",
 'config_version'=>"ADD COLUMN config_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER market_data_enabled",
];
foreach($columns as $name=>$ddl)if(!$hasColumn('instruments',$name))$pdo->exec('ALTER TABLE instruments '.$ddl);
$pdo->exec("UPDATE instruments i LEFT JOIN instrument_categories c ON c.id=i.category_id SET i.profit_currency=COALESCE(NULLIF(i.profit_currency,''),i.quote_currency,'USD'),i.tick_size=COALESCE(i.tick_size,POWER(10,-i.precision_digits)),i.pip_size=COALESCE(i.pip_size,CASE WHEN c.slug='forex' THEN POWER(10,-GREATEST(1,i.precision_digits-1)) ELSE NULL END)");
$pdo->exec('ALTER TABLE instruments MODIFY COLUMN profit_currency CHAR(3) NOT NULL');
if(!$hasIndex('instruments','idx_instruments_controls'))$pdo->exec('ALTER TABLE instruments ADD KEY idx_instruments_controls (category_id,status,trading_enabled,market_data_enabled)');
foreach(['chk_instruments_tick_size'=>'tick_size IS NULL OR tick_size > 0','chk_instruments_pip_size'=>'pip_size IS NULL OR pip_size > 0','chk_instruments_volumes'=>'min_volume > 0 AND max_volume >= min_volume AND volume_step > 0','chk_instruments_volatility'=>'demo_volatility > 0 AND demo_volatility <= 0.08000000'] as $name=>$check)if(!$hasConstraint('instruments',$name))$pdo->exec("ALTER TABLE instruments ADD CONSTRAINT `$name` CHECK ($check)");
if(!$hasColumn('market_simulation_state','seed_price'))$pdo->exec('ALTER TABLE market_simulation_state ADD COLUMN seed_price DECIMAL(24,10) NULL AFTER instrument_id');
$pdo->exec('UPDATE market_simulation_state s JOIN instruments i ON i.id=s.instrument_id SET s.seed_price=i.base_price WHERE s.seed_price IS NULL');
$pdo->exec('UPDATE market_simulation_state SET seed_price=1 WHERE seed_price IS NULL');
$pdo->exec('ALTER TABLE market_simulation_state MODIFY COLUMN seed_price DECIMAL(24,10) NOT NULL');
$settings=[['demo_trading_enabled','1','boolean'],['demo_market_data_enabled','1','boolean'],['market_orders_enabled','1','boolean'],['max_open_positions_per_account','100','integer'],['max_order_volume','100.00','decimal']];
$q=$pdo->prepare('INSERT IGNORE INTO site_settings(setting_key,setting_value,setting_type,is_public) VALUES(?,?,?,0)');foreach($settings as $setting)$q->execute($setting);
echo "Phase 20 migration is applied to {$db}.".PHP_EOL;
