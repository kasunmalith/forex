<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Database;

final class AuthMiddleware
{
    public static function enforceIdleTimeout(): void
    {
        if (!\auth_check()) { return; }
        $last = $_SESSION['auth']['last_activity'] ?? 0;
        if (!is_int($last) || time() - $last >= AUTH_IDLE_TIMEOUT) {
            self::clearAuthentication();
            \flash_set('auth_info', 'Your session has expired. Please sign in again.');
            return;
        }
        $_SESSION['auth']['last_activity'] = time();
    }

    public static function requireAuthenticated(): array
    {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        $userId = \auth_user_id();
        if ($userId === null || !\auth_check()) { \redirect('login'); }
        $query = Database::connection()->prepare("SELECT id,first_name FROM users WHERE id=:id AND status='active' LIMIT 1");
        $query->execute(['id'=>$userId]);
        $user = $query->fetch();
        if (!$user) {
            self::clearAuthentication();
            \flash_set('auth_error','This account cannot access the demo portal. Contact support if you need help.');
            \redirect('login');
        }
        return $user;
    }

    public static function redirectAuthenticatedGuests(): void
    {
        if (!\auth_check()) { return; }
        $id = \auth_user_id();
        $query = Database::connection()->prepare("SELECT id FROM users WHERE id=:id AND status='active' LIMIT 1");
        $query->execute(['id'=>$id]);
        if ($query->fetchColumn()) { \redirect('client'); }
        self::clearAuthentication();
    }

    public static function clearAuthentication(): void
    {
        unset($_SESSION['auth']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
    }
}
