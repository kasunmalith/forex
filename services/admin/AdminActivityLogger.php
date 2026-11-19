<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;

final class AdminActivityLogger
{
    public static function log(?int $adminId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
    {
        try {
            self::write($adminId, $action, $entityType, $entityId, $description);
        } catch (\Throwable $exception) {
            error_log('Admin activity event could not be recorded: ' . substr($action, 0, 100) . ' (' . get_class($exception) . ')');
        }
    }

    /** Use inside an Admin mutation transaction when audit failure must roll back the change. */
    public static function logOrFail(?int $adminId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
    {
        self::write($adminId, $action, $entityType, $entityId, $description);
    }

    private static function write(?int $adminId, string $action, ?string $entityType, ?int $entityId, ?string $description): void
    {
        $action = preg_replace('/[^a-z0-9_.-]/i', '_', substr($action, 0, 100)) ?: 'security_event';
        $entityType = $entityType !== null ? substr(preg_replace('/[^a-z0-9_.-]/i', '_', $entityType) ?: '', 0, 100) : null;
        $description = $description !== null ? substr(self::safeText($description), 0, 1000) : null;
        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);
        $binaryIp = $ip ? inet_pton($ip) : null;
        $userAgent = substr(self::safeText((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);
        $query = Database::connection()->prepare('INSERT INTO admin_activity_logs (admin_id,action,entity_type,entity_id,description,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)');
        $query->execute([$adminId, $action, $entityType !== '' ? $entityType : null, $entityId, $description !== '' ? $description : null, $binaryIp, $userAgent !== '' ? $userAgent : null]);
    }

    private static function safeText(string $value): string
    {
        $value = preg_replace('/\b(password(?:_hash)?|csrf(?:_token)?|session(?:_id)?|api[_-]?(?:key|secret)|secret|token)\b\s*[:=]\s*(?:"[^"]*"|\x27[^\x27]*\x27|[^\s,;]+)/i', '$1=[redacted]', $value) ?? '';
        $value = preg_replace('/\bBearer\s+\S+/i', 'Bearer [redacted]', $value) ?? '';
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?: '';
    }
}
