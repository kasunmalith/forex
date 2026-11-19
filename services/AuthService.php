<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDOException;
use RuntimeException;
use Throwable;

final class AuthService
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$1mW5yQcXGE2MFEmbWgVOXOwMz1HfCk3mFaEuAW76pd5NJFf9djLze';

    public static function normalizeEmail(string $email): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower(trim($email), 'UTF-8') : strtolower(trim($email));
    }

    public static function validateEmail(string $email): bool
    {
        return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePassword(string $password): bool
    {
        return strlen($password) >= 10 && strlen($password) <= 128;
    }

    public static function register(array $input): array
    {
        $data = self::validatedRegistration($input);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
            $existing->execute(['email' => $data['email']]);
            if ($existing->fetchColumn()) {
                throw new AuthValidationException(['email' => 'An account already exists with this email address.']);
            }

            $settings = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('default_demo_balance','site_currency')");
            $settings->execute();
            $config = [];
            foreach ($settings->fetchAll() as $setting) { $config[$setting['setting_key']] = $setting['setting_value']; }
            $balance = self::decimalBalance((string) ($config['default_demo_balance'] ?? ''));
            $currency = strtoupper((string) ($config['site_currency'] ?? ''));
            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) { throw new RuntimeException('Demo account currency configuration is unavailable.'); }

            $user = $pdo->prepare("INSERT INTO users (user_reference,first_name,last_name,email,phone,country,password_hash,status) VALUES (:user_reference,:first_name,:last_name,:email,:phone,:country,:password_hash,'active')");
            $user->execute([
                'user_reference' => 'MFU-' . strtoupper(bin2hex(random_bytes(8))),
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'],
                'phone' => $data['phone'] !== '' ? $data['phone'] : null, 'country' => $data['country'] !== '' ? $data['country'] : null,
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();

            $profile = $pdo->prepare('INSERT INTO user_profiles (user_id,country) VALUES (:user_id,:country)');
            $profile->execute(['user_id' => $userId, 'country' => $data['country'] !== '' ? $data['country'] : null]);

            $accountNumber = self::createDemoAccount($pdo, $userId, $balance, $currency);
            $pdo->commit();
            return ['user_id' => $userId, 'account_number' => $accountNumber, 'balance' => $balance, 'currency' => $currency];
        } catch (AuthValidationException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ((string) $exception->getCode() === '23000') {
                $existing = $pdo->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
                $existing->execute(['email' => $data['email']]);
                if ($existing->fetchColumn()) { throw new AuthValidationException(['email' => 'An account already exists with this email address.']); }
            }
            error_log('Registration transaction failed: ' . $exception->getMessage());
            throw new RuntimeException('We could not create your demo account. Please try again.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Registration transaction failed: ' . $exception->getMessage());
            throw new RuntimeException('We could not create your demo account. Please try again.');
        }
    }

    private static function validatedRegistration(array $input): array
    {
        $data = [
            'first_name' => self::text($input, 'first_name', 100), 'last_name' => self::text($input, 'last_name', 100),
            'email' => self::normalizeEmail(self::text($input, 'email', 254)), 'phone' => self::text($input, 'phone', 40),
            'country' => strtoupper(self::text($input, 'country', 2)),
            'password' => is_string($input['password'] ?? null) ? $input['password'] : '',
            'password_confirmation' => is_string($input['password_confirmation'] ?? null) ? $input['password_confirmation'] : '',
        ];
        $errors = [];
        if (self::length($data['first_name']) < 1 || self::length($data['first_name']) > 100 || preg_match('/[\x00-\x1F\x7F]/', $data['first_name'])) { $errors['first_name'] = 'Enter a first name using 1–100 characters.'; }
        if (self::length($data['last_name']) < 1 || self::length($data['last_name']) > 100 || preg_match('/[\x00-\x1F\x7F]/', $data['last_name'])) { $errors['last_name'] = 'Enter a last name using 1–100 characters.'; }
        if (!self::validateEmail($data['email'])) { $errors['email'] = 'Enter a valid email address.'; }
        if ($data['phone'] !== '' && (strlen($data['phone']) > 40 || preg_match('/^[0-9+().\s-]{5,40}$/', $data['phone']) !== 1)) { $errors['phone'] = 'Enter a valid phone number or leave this field blank.'; }
        if ($data['country'] !== '' && preg_match('/^[A-Z]{2}$/', $data['country']) !== 1) { $errors['country'] = 'Use a two-letter country code, such as LK.'; }
        if (!self::validatePassword($data['password'])) { $errors['password'] = 'Use a password between 10 and 128 characters.'; }
        if (!hash_equals($data['password'], $data['password_confirmation'])) { $errors['password_confirmation'] = 'The passwords do not match.'; }
        if (($input['demo_acknowledgement'] ?? '') !== '1') { $errors['demo_acknowledgement'] = 'Please confirm that this is a demo environment with no real funds.'; }
        if ($errors !== []) { throw new AuthValidationException($errors); }
        return $data;
    }

    private static function createDemoAccount(\PDO $pdo, int $userId, string $balance, string $currency): string
    {
        $insert = $pdo->prepare("INSERT INTO demo_accounts (user_id,account_number,account_type,currency,balance,realized_pl,status) VALUES (:user_id,:account_number,'standard',:currency,:balance,0,'active')");
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = 'DM' . strtoupper(bin2hex(random_bytes(6)));
            try {
                $insert->execute(['user_id' => $userId, 'account_number' => $number, 'currency' => $currency, 'balance' => $balance]);
                return $number;
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() !== '23000') { throw $exception; }
                $check = $pdo->prepare('SELECT id FROM demo_accounts WHERE account_number=:number');
                $check->execute(['number' => $number]);
                if (!$check->fetchColumn()) { throw $exception; }
            }
        }
        throw new RuntimeException('Could not allocate a unique demo account number.');
    }

    private static function decimalBalance(string $value): string
    {
        if (preg_match('/^(\d{1,18})(?:\.(\d{1,2}))?$/', trim($value), $match) !== 1) { throw new RuntimeException('Configured demo balance is unavailable.'); }
        $whole = ltrim($match[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($match[2] ?? '', 2, '0');
        if ($whole === '0' && $fraction === '00') { throw new RuntimeException('Configured demo balance must be greater than zero.'); }
        return $whole . '.' . $fraction;
    }

    public static function attemptLogin(string $email, string $password): bool
    {
        $email = self::normalizeEmail(substr(trim($email), 0, 254));
        $password = substr($password, 0, 1024);
        $key = AuthAttemptLimiter::loginKey($email);
        AuthAttemptLimiter::prune();
        if (AuthAttemptLimiter::isBlocked($key)) { throw new AuthValidationException(['_form' => 'Too many login attempts. Please wait a few minutes and try again.']); }
        $statement = Database::connection()->prepare('SELECT id,password_hash,status FROM users WHERE email=:email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        $hash = $user ? (string) $user['password_hash'] : self::DUMMY_PASSWORD_HASH;
        $verified = password_verify($password, $hash);
        if (!$user || !$verified || $user['status'] !== 'active') {
            AuthAttemptLimiter::recordFailure($key);
            self::logEvent($user && $user['status'] !== 'active' ? 'login_rejected_status' : 'login_failed', $user ? (int) $user['id'] : null);
            return false;
        }
        AuthAttemptLimiter::clear($key);
        $update = Database::connection()->prepare('UPDATE users SET last_login_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $update->execute(['id' => (int) $user['id']]);
        self::authenticate((int) $user['id']);
        self::logEvent('login_success', (int) $user['id']);
        return true;
    }

    public static function authenticate(int $userId): void
    {
        if (!session_regenerate_id(true)) { throw new RuntimeException('Could not renew the authenticated session.'); }
        $_SESSION['auth'] = ['user_id' => $userId, 'authenticated' => true, 'last_activity' => time()];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function logEvent(string $event, ?int $userId = null): void
    {
        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
        error_log(sprintf('auth_event=%s user_id=%s ip=%s', preg_replace('/[^a-z_]/', '', $event), $userId === null ? 'unknown' : (string) $userId, $ip));
    }

    private static function text(array $input, string $key, int $max): string
    {
        $value = $input[$key] ?? '';
        return is_string($value) ? trim(substr($value, 0, $max * 4)) : '';
    }

    private static function length(string $value): int
    {
        if (function_exists('mb_strlen')) { return mb_strlen($value, 'UTF-8'); }
        $length = preg_match_all('/./us', $value);
        return $length === false ? 0 : $length;
    }
}
