<?php
declare(strict_types=1);

// Copy reviewed values into config.php during deployment. Replace every CPANEL_* value.
// Never upload this template as the active configuration without setting a unique DB password.
$config = require __DIR__ . '/config.example.php';

return array_replace($config, [
    'APP_ENV' => 'production',
    'APP_DEBUG' => false,
    'SITE_URL' => 'https://multiforex.online',
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_NAME' => 'CPANEL_DATABASE',
    'DB_USER' => 'CPANEL_DATABASE_USER',
    'DB_PASSWORD' => 'SET_A_UNIQUE_DATABASE_PASSWORD',
    'TRADING_PROVIDER' => 'demo',
    'MARKET_DATA_PROVIDER' => 'demo',
    'REAL_TRADING_ENABLED' => false,
    'KYC_DEMO_MODE' => true,
    'DEMO_TRADE_VOLUME_MIN' => '0.01',
    'DEMO_TRADE_VOLUME_MAX' => '100.00',
    'DEMO_TRADE_VOLUME_STEP' => '0.01',
    'DEMO_TRADE_QUOTE_MAX_AGE_SECONDS' => 30,
    'DEMO_TRADE_RATE_LIMIT' => 20,
    'DEMO_TRADE_RATE_WINDOW_SECONDS' => 60,
    'ADMIN_SESSION_IDLE_TIMEOUT_SECONDS' => 1800,
    'ADMIN_LOGIN_MAX_ATTEMPTS' => 5,
    'ADMIN_LOGIN_WINDOW_SECONDS' => 900,
    // Prefer a directory outside public_html. Replace CPANEL_USER with the account's home directory.
    'PRIVATE_STORAGE_PATH' => '/home/CPANEL_USER/multiforex-private',
]);
