<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;
use App\Services\AuthService;

final class AdminPasswordService
{
    public function resetByEmail(string $email, string $password): bool
    {
        $email = AuthService::normalizeEmail(trim($email));
        $query = Database::connection()->prepare('UPDATE admins SET password_hash=?,session_version=session_version+1 WHERE email=?');
        $query->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
        if ($query->rowCount() !== 1) { return false; }
        $find = Database::connection()->prepare('SELECT id FROM admins WHERE email=? LIMIT 1');
        $find->execute([$email]);
        $id = (int) $find->fetchColumn();
        AdminActivityLogger::log(null, 'password_reset_cli', 'admin', $id, 'Administrator password reset through trusted command-line tooling; existing sessions revoked.');
        return true;
    }
}
