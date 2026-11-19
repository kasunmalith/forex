<?php
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    return APP_CONFIG[$key] ?? $default;
}

function site_url(string $path = ''): string
{
    $base = rtrim((string) config('SITE_URL'), '/');
    return $base . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

function asset_url(string $path = ''): string { return site_url('assets/' . ltrim($path, '/')); }
function client_url(string $path = ''): string { return site_url('client/' . ltrim($path, '/')); }
function admin_url(string $path = ''): string { return site_url('admin/' . ltrim($path, '/')); }
function api_url(string $path = ''): string { return site_url('api/' . ltrim($path, '/')); }
function upload_url(string $path = ''): string { return site_url('uploads/' . ltrim($path, '/')); }

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(generate_csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool
{
    return is_string($token) && hash_equals(generate_csrf_token(), $token);
}

function generate_admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) { $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32)); }
    return (string) $_SESSION['admin_csrf_token'];
}

function admin_csrf_field(): string
{
    return '<input type="hidden" name="_admin_csrf" value="' . e(generate_admin_csrf_token()) . '">';
}

function verify_admin_csrf_token(?string $token): bool
{
    return is_string($token) && isset($_SESSION['admin_csrf_token']) && is_string($_SESSION['admin_csrf_token']) && hash_equals($_SESSION['admin_csrf_token'], $token);
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . site_url($path), true, $status);
    exit;
}

function render(string $view, array $data = []): void
{
    if (!preg_match('~^[a-z0-9/_-]+$~i', $view)) {
        throw new InvalidArgumentException('Invalid view name.');
    }
    $file = APP_ROOT . '/views/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('View not found.');
    }
    extract($data, EXTR_SKIP);
    require $file;
}

function auth_user_id(): ?int
{
    $id = $_SESSION['auth']['user_id'] ?? null;
    return is_int($id) && $id > 0 ? $id : null;
}

function auth_check(): bool
{
    return auth_user_id() !== null && ($_SESSION['auth']['authenticated'] ?? false) === true;
}

function require_auth(): array
{
    return \App\Middleware\AuthMiddleware::requireAuthenticated();
}

function guest_only(): void
{
    \App\Middleware\AuthMiddleware::redirectAuthenticatedGuests();
}

function flash_set(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($message) ? $message : null;
}

function format_currency_amount(string|int|float $amount, string $currency = 'USD'): string
{
    $value = trim((string) $amount);
    if (preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $match) !== 1) { return strtoupper($currency) . ' —'; }
    $whole = ltrim($match[1], '0');
    $whole = $whole === '' ? '0' : $whole;
    $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;
    $fraction = str_pad(substr($match[2] ?? '', 0, 2), 2, '0');
    return (strtoupper($currency) === 'USD' ? '$' : strtoupper($currency) . ' ') . $whole . '.' . $fraction;
}
