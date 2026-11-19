<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;

final class AdminAuthorizationService
{
    public function admin(int $adminId): ?array
    {
        $query = Database::connection()->prepare("SELECT a.id,a.name,a.email,a.status,a.session_version,a.last_login_at,r.id AS role_id,r.name AS role_name,r.slug AS role_slug,r.status AS role_status FROM admins a JOIN admin_roles r ON r.id=a.role_id WHERE a.id=? LIMIT 1");
        $query->execute([$adminId]);
        $admin = $query->fetch();
        return is_array($admin) ? $admin : null;
    }

    public function permissions(int $adminId): array
    {
        $query = Database::connection()->prepare("SELECT p.permission_key FROM admins a JOIN admin_roles r ON r.id=a.role_id AND r.status='active' JOIN admin_role_permissions rp ON rp.role_id=r.id JOIN admin_permissions p ON p.id=rp.permission_id WHERE a.id=? AND a.status='active' ORDER BY p.permission_key");
        $query->execute([$adminId]);
        return array_values(array_map('strval', $query->fetchAll(\PDO::FETCH_COLUMN)));
    }

    public function hasPermission(int $adminId, string $permission): bool
    {
        if (preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $permission) !== 1) { return false; }
        $query = Database::connection()->prepare("SELECT 1 FROM admins a JOIN admin_roles r ON r.id=a.role_id AND r.status='active' JOIN admin_role_permissions rp ON rp.role_id=r.id JOIN admin_permissions p ON p.id=rp.permission_id WHERE a.id=? AND a.status='active' AND p.permission_key=? LIMIT 1");
        $query->execute([$adminId, $permission]);
        return (bool) $query->fetchColumn();
    }

    public function roleHasPermission(string $roleSlug, string $permission): bool
    {
        $query = Database::connection()->prepare("SELECT 1 FROM admin_roles r JOIN admin_role_permissions rp ON rp.role_id=r.id JOIN admin_permissions p ON p.id=rp.permission_id WHERE r.slug=? AND r.status='active' AND p.permission_key=? LIMIT 1");
        $query->execute([$roleSlug, $permission]);
        return (bool) $query->fetchColumn();
    }

    public function requirePermission(string $permission): array
    {
        $admin = \App\Middleware\AdminAuthMiddleware::requireAuthenticated();
        if (!$this->hasPermission((int) $admin['id'], $permission)) {
            \App\Middleware\AdminAuthMiddleware::deny($permission);
        }
        return $admin;
    }
}
