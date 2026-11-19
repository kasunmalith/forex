<?php
declare(strict_types=1);

namespace App\Services;

final class CTraderConfiguration
{
    public static function environment(): string
    {
        $environment = strtolower(trim((string)\config('CTRADER_ENVIRONMENT', 'demo')));
        return in_array($environment, ['demo','live'], true) ? $environment : 'invalid';
    }

    public static function credentialsConfigured(): bool
    {
        return trim((string)\config('CTRADER_CLIENT_ID', '')) !== ''
            && trim((string)\config('CTRADER_CLIENT_SECRET', '')) !== '';
    }

    public static function configured(): bool
    {
        return self::credentialsConfigured() && self::environment() !== 'invalid'
            && self::validRedirectUri((string)\config('CTRADER_REDIRECT_URI', ''));
    }

    public static function validRedirectUri(string $uri): bool
    {
        $uri = trim($uri);
        if ($uri === '' || filter_var($uri, FILTER_VALIDATE_URL) === false) return false;
        $parts = parse_url($uri);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['query'])) return false;
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $site = parse_url((string)\config('SITE_URL', ''));
        if (!is_array($site) || $host === '' || $host !== strtolower((string)($site['host'] ?? ''))) return false;
        if (($parts['port'] ?? null) !== ($site['port'] ?? null)) return false;
        $sitePath = rtrim((string)($site['path'] ?? ''), '/');
        $redirectPath = (string)($parts['path'] ?? '');
        if ($sitePath !== '' && $redirectPath !== $sitePath && !str_starts_with($redirectPath, $sitePath . '/')) return false;
        if ($scheme === 'https') return strtolower((string)($site['scheme'] ?? '')) === 'https';
        return $scheme === 'http' && strtolower((string)($site['scheme'] ?? '')) === 'http'
            && in_array($host, ['localhost', '127.0.0.1'], true) && (string)\config('APP_ENV', '') === 'development';
    }

    public static function safeStatus(): array
    {
        $configured = self::configured();
        return [
            'provider' => 'ctrader',
            'configured' => $configured,
            'connected' => false,
            'environment' => self::environment(),
            'status' => self::environment() === 'invalid' ? 'invalid_configuration' : ($configured ? 'not_verified' : 'not_configured'),
            'mapping_status' => 'not_checked',
        ];
    }
}
