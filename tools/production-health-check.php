<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path(sys_get_temp_dir());
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$failed = false;
$check = static function (bool $ok, string $label) use (&$failed): void {
    printf("%s %s\n", $ok ? 'PASS' : 'FAIL', $label);
    $failed = $failed || !$ok;
};

$check((string) config('APP_ENV') === 'production', 'APP_ENV is production');
$check(config('APP_DEBUG') === false, 'APP_DEBUG is disabled');
$check(strtolower((string) config('TRADING_PROVIDER')) === 'demo', 'Trading provider is Demo');
$check(strtolower((string) config('MARKET_DATA_PROVIDER')) === 'demo', 'Market data provider is Demo');
$check(config('REAL_TRADING_ENABLED') === false, 'Real trading is disabled');
$check(config('KYC_DEMO_MODE') === true, 'KYC Demo mode is enabled');
$siteUrl = (string) config('SITE_URL');
$check(parse_url($siteUrl, PHP_URL_SCHEME) === 'https', 'SITE_URL uses HTTPS');

$privatePath = (string) config('PRIVATE_STORAGE_PATH', '');
$privateReal = $privatePath !== '' ? realpath($privatePath) : false;
$appReal = realpath(APP_ROOT);
$outsideWebRoot = $privateReal !== false && $appReal !== false
    && !str_starts_with(strtolower($privateReal . DIRECTORY_SEPARATOR), strtolower($appReal . DIRECTORY_SEPARATOR));
$privateRules = $privateReal !== false && is_file($privateReal . DIRECTORY_SEPARATOR . '.htaccess')
    ? (string) file_get_contents($privateReal . DIRECTORY_SEPARATOR . '.htaccess') : '';
$protectedInWebRoot = preg_match('/(?:Require\s+all\s+denied|Deny\s+from\s+all)/i', $privateRules) === 1;
$check($privateReal !== false && is_dir($privateReal) && is_writable($privateReal), 'Private storage exists and is writable');
$check($outsideWebRoot || $protectedInWebRoot, 'Private storage is outside the web root or has a protection file');

try {
    \App\Database::connection()->query('SELECT 1')->fetchColumn();
    $check(true, 'Database connection is available');
} catch (Throwable $exception) {
    error_log('Production health check database connection failed: ' . get_class($exception));
    $check(false, 'Database connection is available');
}

exit($failed ? 1 : 0);
