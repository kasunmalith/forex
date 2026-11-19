<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$config = require APP_ROOT . '/config/config.php';
if (!is_array($config)) {
    throw new RuntimeException('Invalid application configuration.');
}
foreach (['APP_ENV', 'APP_DEBUG', 'SITE_URL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'TIMEZONE', 'TRADING_PROVIDER', 'MARKET_DATA_PROVIDER', 'REAL_TRADING_ENABLED', 'CTRADER_CLIENT_ID', 'CTRADER_CLIENT_SECRET', 'CTRADER_REDIRECT_URI', 'CTRADER_ENVIRONMENT', 'CTRADER_SCOPE', 'CTRADER_CONNECT_TIMEOUT_SECONDS', 'CTRADER_READ_TIMEOUT_SECONDS', 'DEMO_TRADE_VOLUME_MIN', 'DEMO_TRADE_VOLUME_MAX', 'DEMO_TRADE_VOLUME_STEP', 'DEMO_TRADE_QUOTE_MAX_AGE_SECONDS', 'DEMO_TRADE_RATE_LIMIT', 'DEMO_TRADE_RATE_WINDOW_SECONDS', 'ADMIN_SESSION_IDLE_TIMEOUT_SECONDS', 'ADMIN_LOGIN_MAX_ATTEMPTS', 'ADMIN_LOGIN_WINDOW_SECONDS'] as $key) {
    if (!array_key_exists($key, $config)) {
        throw new RuntimeException('Missing configuration key: ' . $key);
    }
}
define('APP_CONFIG', $config);
date_default_timezone_set((string) $config['TIMEZONE']);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

$debug = $config['APP_ENV'] === 'development' && $config['APP_DEBUG'] === true;
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);
$logFile = APP_ROOT . '/storage/logs/application.log';
ini_set('log_errors', '1');
ini_set('error_log', $logFile);
set_exception_handler(static function (Throwable $exception) use ($debug): void {
    error_log((string) $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo $debug ? 'Application error: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'An application error occurred.';
});

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $segments = explode('\\', substr($class, 4));
    $first = $segments[0] ?? '';
    $directory = match ($first) {
        'Controllers' => 'controllers',
        'Models' => 'models',
        'Services' => 'services',
        'Middleware' => 'middleware',
        default => 'includes',
    };
    if ($directory !== 'includes') {
        array_shift($segments);
    }
    if ($directory === 'services' && isset($segments[0])) {
        $segments[0] = match ($segments[0]) {
            'Trading' => 'trading',
            'MarketData' => 'market-data',
            'Support' => 'support',
            'Funds' => 'funds',
            'Kyc' => 'kyc',
            'Admin' => 'admin',
            default => $segments[0],
        };
    }
    $file = APP_ROOT . '/' . $directory . '/' . implode('/', $segments) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/public-ui.php';

$basePath = parse_url((string) $config['SITE_URL'], PHP_URL_PATH) ?: '';
$cookiePath = rtrim($basePath, '/') . '/';
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('FOREX_DEMO_SESSION');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookiePath,
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) max(1800, min(7200, (int) $config['ADMIN_SESSION_IDLE_TIMEOUT_SECONDS'])));
session_start();
const AUTH_IDLE_TIMEOUT = 1800;
\App\Middleware\AuthMiddleware::enforceIdleTimeout();
