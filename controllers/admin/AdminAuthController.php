<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Services\Admin\AdminAuthService;

final class AdminAuthController
{
    public function loginForm(): void
    {
        AdminAuthMiddleware::headers();
        AdminAuthMiddleware::redirectAuthenticatedAdmin();
        \render('admin/auth/login', [
            'title' => 'Administrator sign in · MultiForex',
            'error' => \flash_get('admin_error'),
            'notice' => \flash_get('admin_info'),
            'email' => '',
            'csrfToken' => \generate_admin_csrf_token(),
        ]);
    }

    public function login(): void
    {
        AdminAuthMiddleware::headers();
        AdminAuthMiddleware::redirectAuthenticatedAdmin();
        $rawEmail = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $email = strlen($rawEmail) <= 254 ? $rawEmail : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $error = null;
        if (!\verify_admin_csrf_token(is_string($_POST['_admin_csrf'] ?? null) ? $_POST['_admin_csrf'] : null)) {
            http_response_code(403);
            $error = 'Your form expired. Refresh this page and sign in again.';
        } elseif (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '' || strlen($password) > 1024) {
            $error = 'Invalid email or password.';
        } else {
            $result = (new AdminAuthService())->login($email, $password);
            if ($result === 'success') { \redirect('admin'); }
            $error = match ($result) {
                'throttled' => 'Too many sign-in attempts. Please wait and try again.',
                'unavailable' => 'This administrator account is unavailable.',
                default => 'Invalid email or password.',
            };
        }
        \render('admin/auth/login', ['title' => 'Administrator sign in · MultiForex', 'error' => $error, 'notice' => null, 'email' => $email, 'csrfToken' => \generate_admin_csrf_token()]);
    }

    public function logout(): void
    {
        $admin = AdminAuthMiddleware::requireAuthenticated();
        if (!\verify_admin_csrf_token(is_string($_POST['_admin_csrf'] ?? null) ? $_POST['_admin_csrf'] : null)) {
            http_response_code(403);
            \render('admin/error-page', ['title' => 'Request could not be verified', 'status' => 403, 'message' => 'Refresh the administration page and try again.', 'loginUrl' => \admin_url('login')]);
            return;
        }
        (new AdminAuthService())->logout();
        $_SESSION['_flash']['admin_info'] = 'You have signed out of the administration area.';
        \redirect('admin/login');
    }
}
