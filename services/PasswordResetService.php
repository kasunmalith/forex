<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use RuntimeException;
use Throwable;

final class PasswordResetService
{
    public const TOKEN_TTL_MINUTES = 30;

    public static function request(string $email): void
    {
        $key = AuthAttemptLimiter::resetKey();
        AuthAttemptLimiter::prune();
        if (AuthAttemptLimiter::isBlocked($key)) { return; }
        AuthAttemptLimiter::recordFailure($key);

        $email = AuthService::normalizeEmail(substr(trim($email), 0, 254));
        if (!AuthService::validateEmail($email)) { return; }
        $pdo = Database::connection();
        $lookup = $pdo->prepare("SELECT id,email FROM users WHERE email=:email AND status='active' LIMIT 1");
        $lookup->execute(['email' => $email]);
        $user = $lookup->fetch();
        if (!$user) { return; }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $pdo->beginTransaction();
        try {
            $invalidate = $pdo->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=:user_id AND used_at IS NULL');
            $invalidate->execute(['user_id' => (int) $user['id']]);
            $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) VALUES (:user_id,:token_hash,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))');
            $insert->execute(['user_id' => (int) $user['id'], 'token_hash' => $tokenHash]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            AuthService::logEvent('password_reset_request_failed', (int) $user['id']);
            error_log('Password reset token creation failed: ' . $exception->getMessage());
            return;
        }

        $resetUrl = \site_url('reset-password') . '?token=' . rawurlencode($rawToken);
        if (!MailService::sendPasswordReset((string) $user['email'], $resetUrl)) {
            $disable = $pdo->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE token_hash=:token_hash AND used_at IS NULL');
            $disable->execute(['token_hash' => $tokenHash]);
            AuthService::logEvent('password_reset_delivery_failed', (int) $user['id']);
            return;
        }
        AuthService::logEvent('password_reset_requested', (int) $user['id']);
    }

    public static function isValid(string $rawToken): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $rawToken) !== 1) { return false; }
        $statement = Database::connection()->prepare("SELECT t.id FROM password_reset_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=:token_hash AND t.used_at IS NULL AND t.expires_at>UTC_TIMESTAMP() AND u.status='active' LIMIT 1");
        $statement->execute(['token_hash' => hash('sha256', $rawToken)]);
        return (bool) $statement->fetchColumn();
    }

    public static function reset(string $rawToken, string $password, string $confirmation): void
    {
        if (!AuthService::validatePassword($password)) { throw new AuthValidationException(['password'=>'Use a password between 10 and 128 characters.']); }
        if (!hash_equals($password, $confirmation)) { throw new AuthValidationException(['password_confirmation'=>'The passwords do not match.']); }
        if (preg_match('/^[a-f0-9]{64}$/i', $rawToken) !== 1) { throw new AuthValidationException(['_form'=>'This password reset link is invalid or has expired.']); }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare("SELECT t.id,t.user_id FROM password_reset_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=:token_hash AND t.used_at IS NULL AND t.expires_at>UTC_TIMESTAMP() AND u.status='active' LIMIT 1 FOR UPDATE");
            $find->execute(['token_hash'=>hash('sha256',$rawToken)]);
            $token = $find->fetch();
            if (!$token) { throw new AuthValidationException(['_form'=>'This password reset link is invalid or has expired.']); }

            $updateUser = $pdo->prepare('UPDATE users SET password_hash=:password_hash,updated_at=CURRENT_TIMESTAMP WHERE id=:user_id');
            $updateUser->execute(['password_hash'=>password_hash($password,PASSWORD_DEFAULT),'user_id'=>(int)$token['user_id']]);
            $consume = $pdo->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE id=:id AND used_at IS NULL AND expires_at>UTC_TIMESTAMP()');
            $consume->execute(['id'=>(int)$token['id']]);
            if ($consume->rowCount() !== 1) { throw new AuthValidationException(['_form'=>'This password reset link is invalid or has expired.']); }
            $invalidateOthers = $pdo->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=:user_id AND id<>:id AND used_at IS NULL');
            $invalidateOthers->execute(['user_id'=>(int)$token['user_id'],'id'=>(int)$token['id']]);
            $pdo->commit();
            AuthService::logEvent('password_reset_completed',(int)$token['user_id']);
        } catch (AuthValidationException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Password reset transaction failed: ' . $exception->getMessage());
            throw new RuntimeException('We could not update your password. Please request a new reset link.');
        }
    }
}
