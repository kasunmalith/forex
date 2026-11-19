<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthMiddleware;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\AdminAuthorizationService;

final class AdminHomeController
{
    public function index(): void
    {
        $authz = new AdminAuthorizationService();
        $admin = $authz->requirePermission('dashboard.view');
        $permissions = $authz->permissions((int)$admin['id']);
        try {
            $data = (new AdminDashboardService())->load($permissions);
            $this->render($admin, $permissions, 'Operations dashboard', 'index', $data, 'dashboard');
        } catch (\Throwable $exception) {
            error_log('Admin dashboard could not be loaded: ' . get_class($exception));
            AdminAuthMiddleware::headers();
            http_response_code(503);
            \render('admin/error-page', ['title'=>'Dashboard temporarily unavailable','status'=>503,'message'=>'Administration data could not be loaded. Please try again shortly.','loginUrl'=>\admin_url('login')]);
        }
    }

    public function placeholder(string $permission, string $module, string $route): void
    {
        $authz = new AdminAuthorizationService();
        $admin = $authz->requirePermission($permission);
        $this->render($admin, $authz->permissions((int)$admin['id']), $module, 'placeholder', ['module' => $module, 'permission' => $permission], $route);
    }

    public function accessDenied(): never
    {
        AdminAuthMiddleware::deny('dashboard.view');
    }

    private function render(array $admin, array $permissions, string $title, string $view, array $data, string $route): void
    {
        AdminAuthMiddleware::headers();
        \render('admin/layout/layout', [
            'title' => $title . ' · MultiForex Administration',
            'pageTitle' => $title,
            'admin' => $admin,
            'permissions' => $permissions,
            'adminData' => ['admin' => $admin, 'permissions' => $permissions] + $data,
            'contentView' => 'admin/' . $view,
            'activeRoute' => $route,
            'csrfToken' => \generate_admin_csrf_token(),
            'notice' => \flash_get('admin_info'),
            'error' => \flash_get('admin_error'),
        ]);
    }
}
