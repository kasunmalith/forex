<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;

final class AuthAttemptLimiter
{
    private const MAX_ATTEMPTS = 5;

    public static function loginKey(string $email): string
    {
        return self::key('login', strtolower(substr(trim($email), 0, 254)));
    }

    public static function resetKey(): string
    {
        return self::key('password-reset', '');
    }

    private static function key(string $purpose, string $identity): string
    {
        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
        return hash('sha256', $purpose . "\0" . $identity . "\0" . $ip);
    }

    public static function isBlocked(string $key): bool
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM auth_rate_limits WHERE bucket_hash=:bucket AND attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE');
        $statement->execute(['bucket' => $key]);
        return (int) $statement->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function recordFailure(string $key): void
    {
        $statement = Database::connection()->prepare('INSERT INTO auth_rate_limits (bucket_hash, attempted_at) VALUES (:bucket, UTC_TIMESTAMP())');
        $statement->execute(['bucket' => $key]);
    }

    public static function clear(string $key): void
    {
        $statement = Database::connection()->prepare('DELETE FROM auth_rate_limits WHERE bucket_hash=:bucket');
        $statement->execute(['bucket' => $key]);
    }

    public static function prune(): void
    {
        Database::connection()->exec('DELETE FROM auth_rate_limits WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    }
}
