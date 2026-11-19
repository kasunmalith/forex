<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Database;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminAuthService;
use App\Services\Admin\AdminAuthorizationService;

final class AdminAuthMiddleware
{
    public static function headers(): void
    {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    }

    public static function requireAuthenticated(): array
    {
        self::headers();
        $service = new AdminAuthService();
        $id = $service->currentAdminId();
        if ($id === null) { self::unauthorized('Administrator sign in is required.'); }
        $last = $_SESSION['admin_auth']['last_activity'] ?? null;
        $timeout = max(300, min(7200, (int) \config('ADMIN_SESSION_IDLE_TIMEOUT_SECONDS', 1800)));
        if (!is_int($last) || time() - $last >= $timeout) {
            self::clearAdminSession();
            $_SESSION['_flash']['admin_info'] = 'Your administrator session has expired. Please sign in again.';
            self::unauthorized('Your administrator session has expired.');
        }
        $admin = (new AdminAuthorizationService())->admin($id);
        if (!$admin || $admin['status'] !== 'active' || $admin['role_status'] !== 'active') {
            AdminActivityLogger::log($id, 'session_rejected_status', 'admin_session', $id, 'Administrator access is unavailable.');
            self::clearAdminSession();
            $_SESSION['_flash']['admin_error'] = 'This administrator account is unavailable.';
            self::unauthorized('This administrator account is unavailable.');
        }
        if (!is_int($_SESSION['admin_auth']['session_version'] ?? null) || (int) $_SESSION['admin_auth']['session_version'] !== (int) $admin['session_version']) {
            self::clearAdminSession();
            $_SESSION['_flash']['admin_info'] = 'Your administrator session has expired. Please sign in again.';
            self::unauthorized('Your administrator session has expired.');
        }
        $_SESSION['admin_auth']['last_activity'] = time();
        return $admin;
    }

    public static function redirectAuthenticatedAdmin(): void
    {
        self::headers();
        if ((new AdminAuthService())->currentAdminId() !== null) {
            self::requireAuthenticated();
            \redirect('admin');
        }
    }

    public static function clearAdminSession(): void
    {
        unset($_SESSION['admin_auth'], $_SESSION['admin_csrf_token']);
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
    }

    public static function deny(string $permission): never
    {
        self::headers();
        http_response_code(403);
        $id = (new AdminAuthService())->currentAdminId();
        AdminActivityLogger::log($id, 'permission_denied', 'admin_route', null, 'Access denied for permission ' . substr(preg_replace('/[^a-z0-9_.-]/i', '', $permission) ?: 'unknown', 0, 80) . '.');
        if (self::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'message'=>'You do not have permission to perform this action.','data'=>null,'errors'=>[]], JSON_THROW_ON_ERROR);
            exit;
        }
        \render('admin/error-page', ['title' => 'Access denied', 'status' => 403, 'message' => 'You do not have permission to open this administration page.', 'loginUrl' => \admin_url('login')]);
        exit;
    }

    public static function notFound(): never
    {
        self::headers();
        http_response_code(404);
        \render('admin/error-page', ['title' => 'Page not found', 'status' => 404, 'message' => 'This administration page is not available.', 'loginUrl' => \admin_url('login')]);
        exit;
    }

    private static function unauthorized(string $message): never
    {
        if (self::wantsJson()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'message'=>$message,'data'=>null,'errors'=>[],'login_url'=>\admin_url('login')], JSON_THROW_ON_ERROR);
            exit;
        }
        \redirect('admin/login');
    }

    private static function wantsJson(): bool
    {
        return str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }
}
