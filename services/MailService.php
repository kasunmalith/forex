<?php
declare(strict_types=1);

namespace App\Services;

final class MailService
{
    public static function sendPasswordReset(string $recipient, string $resetUrl): bool
    {
        $subject = 'Password reset instructions';
        $body = "Use this link to set a new password. It expires in 30 minutes and can be used once:\n\n" . $resetUrl . "\n\nIf you did not request this, you can ignore this message.";
        if ((APP_CONFIG['APP_ENV'] ?? '') === 'development') {
            $log = APP_ROOT . '/storage/logs/mail.log';
            $record = json_encode(['time_utc'=>gmdate(DATE_ATOM),'to'=>$recipient,'subject'=>$subject,'reset_url'=>$resetUrl], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
            if (file_put_contents($log, $record, FILE_APPEND|LOCK_EX) === false) {
                error_log('Password reset development mail could not be recorded.');
                return false;
            }
            return true;
        }

        $from = (string) (APP_CONFIG['MAIL_FROM_EMAIL'] ?? '');
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            error_log('Password reset delivery unavailable: MAIL_FROM_EMAIL is not configured.');
            return false;
        }
        $name = str_replace(["\r", "\n", '"'], '', (string) (APP_CONFIG['MAIL_FROM_NAME'] ?? 'MultiForex'));
        $headers = "From: \"{$name}\" <{$from}>\r\n" . "Content-Type: text/plain; charset=UTF-8\r\n";
        $sent = mail($recipient, $subject, $body, $headers);
        if (!$sent) { error_log('Password reset delivery failed through configured PHP mail transport.'); }
        return $sent;
    }
}
