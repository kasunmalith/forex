<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (session_status() !== PHP_SESSION_ACTIVE) { session_save_path(sys_get_temp_dir()); }
require dirname(__DIR__) . '/includes/bootstrap.php';
$options = getopt('', ['email:']);
$email = App\Services\AuthService::normalizeEmail(is_string($options['email'] ?? null) ? $options['email'] : '');
$password = getenv('MULTIFOREX_ADMIN_PASSWORD');
putenv('MULTIFOREX_ADMIN_PASSWORD');
try {
    if (!App\Services\AuthService::validateEmail($email)) { throw new InvalidArgumentException('Provide a valid administrator email address.'); }
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 128) { throw new InvalidArgumentException('Set MULTIFOREX_ADMIN_PASSWORD to a password between 12 and 128 characters.'); }
    $classes = (int) preg_match('/[a-z]/', $password) + (int) preg_match('/[A-Z]/', $password) + (int) preg_match('/[0-9]/', $password) + (int) preg_match('/[^a-zA-Z0-9]/', $password);
    if ($classes < 3) { throw new InvalidArgumentException('Use at least three of lowercase, uppercase, number, and special character.'); }
    if (!(new App\Services\Admin\AdminPasswordService())->resetByEmail($email, $password)) { throw new InvalidArgumentException('No administrator account matches that email address.'); }
    if ($password !== '' && function_exists('sodium_memzero')) { sodium_memzero($password); }
    unset($password);
    fwrite(STDOUT, "Administrator password reset. Existing administrator sessions have been revoked.\n");
} catch (Throwable $exception) {
    if (isset($password) && is_string($password) && $password !== '' && function_exists('sodium_memzero')) { sodium_memzero($password); }
    fwrite(STDERR, $exception instanceof InvalidArgumentException ? $exception->getMessage() . "\n" : "Administrator password reset failed. Verify the database and Phase 18 migration.\n");
    exit(1);
}
