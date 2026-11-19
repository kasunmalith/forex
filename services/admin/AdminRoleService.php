<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;

final class AdminRoleService
{
    public function findBySlug(string $slug): ?array
    {
        $query = Database::connection()->prepare("SELECT id,name,slug,status,is_system FROM admin_roles WHERE slug=? AND status='active' LIMIT 1");
        $query->execute([$slug]);
        $row = $query->fetch();
        return is_array($row) ? $row : null;
    }

    public function listActive(): array
    {
        return Database::connection()->query("SELECT id,name,slug,description,is_system,status FROM admin_roles WHERE status='active' ORDER BY id")->fetchAll();
    }

    public function listPermissions(): array
    {
        return Database::connection()->query('SELECT permission_key,display_name,module,description FROM admin_permissions ORDER BY module,permission_key')->fetchAll();
    }

    public function createAdmin(string $name, string $email, string $password, string $roleSlug): int
    {
        $role = $this->findBySlug($roleSlug);
        if (!$role) { throw new \InvalidArgumentException('Choose an active administrator role.'); }
        $query = Database::connection()->prepare("INSERT INTO admins (role_id,name,email,password_hash,status) VALUES (?,?,?,?,'active')");
        $query->execute([(int) $role['id'], $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        return (int) Database::connection()->lastInsertId();
    }
}
