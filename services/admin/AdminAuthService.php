<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;
use App\Services\AuthService;
use App\Services\AuthAttemptLimiter;
use PDO;
use RuntimeException;

final class AdminAuthService
{
    private const DUMMY_HASH = '$2y$10$1mW5yQcXGE2MFEmbWgVOXOwMz1HfCk3mFaEuAW76pd5NJFf9djLze';

    public function login(string $email, string $password): string
    {
        $email = AuthService::normalizeEmail(substr(trim($email), 0, 254));
        $password = substr($password, 0, 1024);
        $keys = $this->rateKeys($email);
        $pdo = Database::connection();
        AuthAttemptLimiter::prune();
        $max = max(3, min(20, (int) \config('ADMIN_LOGIN_MAX_ATTEMPTS', 5)));
        $window = max(60, min(3600, (int) \config('ADMIN_LOGIN_WINDOW_SECONDS', 900)));
        foreach ($keys as $key) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM auth_rate_limits WHERE bucket_hash=? AND attempted_at>=UTC_TIMESTAMP()-INTERVAL ? SECOND');
            $check->execute([$key, $window]);
            if ((int) $check->fetchColumn() >= $max) {
                AdminActivityLogger::log(null, 'rate_limit_triggered', 'admin_login', null, 'Administrator sign-in rate limit reached.');
                return 'throttled';
            }
        }

        $query = $pdo->prepare('SELECT id,name,email,password_hash,status FROM admins WHERE email=? LIMIT 1');
        $query->execute([$email]);
        $admin = $query->fetch();
        $validPassword = password_verify($password, $admin ? (string) $admin['password_hash'] : self::DUMMY_HASH);
        if (!$admin || !$validPassword) {
            $this->recordFailure($keys);
            AdminActivityLogger::log($admin ? (int) $admin['id'] : null, 'failed_login', 'admin_login', $admin ? (int) $admin['id'] : null, $admin ? 'Administrator sign-in rejected.' : 'Unrecognized administrator sign-in attempt.');
            return 'invalid';
        }
        if ($admin['status'] !== 'active') {
            $this->recordFailure($keys);
            AdminActivityLogger::log((int) $admin['id'], 'unavailable_login', 'admin_login', (int) $admin['id'], 'Unavailable administrator sign-in attempt.');
            return 'unavailable';
        }

        $role = (new AdminAuthorizationService())->admin((int) $admin['id']);
        if (!$role || $role['role_status'] !== 'active') {
            $this->recordFailure($keys);
            AdminActivityLogger::log((int) $admin['id'], 'unavailable_login', 'admin_login', (int) $admin['id'], 'Administrator role is unavailable.');
            return 'unavailable';
        }
        if (!session_regenerate_id(true)) { throw new RuntimeException('Could not renew the administrator session.'); }
        $_SESSION['admin_auth'] = ['admin_id' => (int) $admin['id'], 'authenticated' => true, 'last_activity' => time(), 'session_version' => (int) $role['session_version']];
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE admins SET last_login_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int) $admin['id']]);
        foreach ($keys as $key) { $pdo->prepare('DELETE FROM auth_rate_limits WHERE bucket_hash=?')->execute([$key]); }
        AdminActivityLogger::log((int) $admin['id'], 'login_success', 'admin_login', (int) $admin['id'], 'Administrator signed in.');
        return 'success';
    }

    public function logout(): void
    {
        $id = $this->currentAdminId();
        if ($id !== null) { AdminActivityLogger::log($id, 'logout', 'admin_login', $id, 'Administrator signed out.'); }
        unset($_SESSION['admin_auth'], $_SESSION['admin_csrf_token']);
        if (session_status() === PHP_SESSION_ACTIVE && !session_regenerate_id(true)) { throw new RuntimeException('Could not renew the session after administrator sign-out.'); }
    }

    public function currentAdminId(): ?int
    {
        $auth = $_SESSION['admin_auth'] ?? null;
        $id = is_array($auth) ? ($auth['admin_id'] ?? null) : null;
        return is_int($id) && $id > 0 && ($auth['authenticated'] ?? false) === true ? $id : null;
    }

    private function rateKeys(string $email): array
    {
        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
        return [hash('sha256', "admin-login\0" . $email . "\0" . $ip), hash('sha256', "admin-login-ip\0" . $ip)];
    }

    private function recordFailure(array $keys): void
    {
        $insert = Database::connection()->prepare('INSERT INTO auth_rate_limits (bucket_hash,attempted_at) VALUES (?,UTC_TIMESTAMP())');
        foreach ($keys as $key) { $insert->execute([$key]); }
    }
}
