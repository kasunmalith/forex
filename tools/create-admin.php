<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) { session_save_path(sys_get_temp_dir()); }
require dirname(__DIR__) . '/includes/bootstrap.php';

$options = getopt('', ['name:', 'email:', 'role::']);
$name = trim(is_string($options['name'] ?? null) ? $options['name'] : '');
$email = \App\Services\AuthService::normalizeEmail(is_string($options['email'] ?? null) ? $options['email'] : '');
$role = is_string($options['role'] ?? null) && $options['role'] !== '' ? $options['role'] : 'super-admin';
$password = getenv('MULTIFOREX_ADMIN_PASSWORD');
putenv('MULTIFOREX_ADMIN_PASSWORD');

try {
    if ($name === '' || strlen($name) > 160 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
        throw new InvalidArgumentException('Provide an administrator name using 1 to 160 characters.');
    }
    if (!\App\Services\AuthService::validateEmail($email)) { throw new InvalidArgumentException('Provide a valid administrator email address.'); }
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 128) { throw new InvalidArgumentException('Set MULTIFOREX_ADMIN_PASSWORD to a password between 12 and 128 characters.'); }
    $classes = (int) preg_match('/[a-z]/', $password) + (int) preg_match('/[A-Z]/', $password) + (int) preg_match('/[0-9]/', $password) + (int) preg_match('/[^a-zA-Z0-9]/', $password);
    if ($classes < 3) { throw new InvalidArgumentException('Use at least three of lowercase, uppercase, number, and special character.'); }

    $pdo = \App\Database::connection();
    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($adminCount === 0 && $role !== 'super-admin') { throw new InvalidArgumentException('The first administrator must use the super-admin role.'); }
    $roles = new \App\Services\Admin\AdminRoleService();
    if (!$roles->findBySlug($role)) { throw new InvalidArgumentException('Choose a role from the active seeded administrator roles.'); }
    $id = $roles->createAdmin($name, $email, $password, $role);
    \App\Services\Admin\AdminActivityLogger::log(null, 'admin_bootstrap_created', 'admin', $id, 'Administrator account created through the command-line bootstrap.');
    if ($password !== '' && function_exists('sodium_memzero')) { sodium_memzero($password); }
    unset($password);
    fwrite(STDOUT, "Administrator account created successfully (ID {$id}; role {$role}).\n");
} catch (Throwable $exception) {
    if (isset($password) && is_string($password) && $password !== '' && function_exists('sodium_memzero')) { sodium_memzero($password); }
    fwrite(STDERR, $exception instanceof InvalidArgumentException ? $exception->getMessage() . "\n" : "Administrator bootstrap failed. Verify the database and Phase 18 migration.\n");
    exit(1);
}
