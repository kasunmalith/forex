<?php
declare(strict_types=1);

function public_site_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    try {
        $rows = \App\Database::connection()->query(
            'SELECT setting_key, setting_value FROM site_settings WHERE is_public = 1'
        )->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    } catch (Throwable $exception) {
        error_log('Public site settings unavailable: ' . $exception->getMessage());
        $settings = [];
    }

    return $settings;
}

function site_setting(string $key, string $fallback = ''): string
{
    return public_site_settings()[$key] ?? (match ($key) {
        'default_seo_title' => 'MultiForex | Demo Global Trading Platform',
        'default_seo_description' => 'Explore simulated markets and demo trading tools with virtual funds on MultiForex.',
        'footer_copyright' => 'Demo environment',
        'demo_disclaimer' => 'Demo Trading Environment - No real funds are used. All prices and balances are simulated.',
        'risk_warning' => 'Simulated prices and balances do not represent live market conditions or real funds.',
        default => $fallback,
    });
}

function current_path_key(): string
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $base = rtrim((string) (parse_url((string) config('SITE_URL'), PHP_URL_PATH) ?: ''), '/');
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return strtolower((string) (explode('/', trim($path, '/'))[0] ?? ''));
}

function ui_icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'bars' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'chart' => '<path d="M4 19V5M4 19h17M8 15l3-4 3 2 5-7"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'market' => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>',
        'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>',
        'support' => '<path d="M4 14v-3a8 8 0 0 1 16 0v3M4 14H3v4h4v-4H4Zm16 0h1v4h-4v-4h3ZM8 21h5"/>',
        'trend-up' => '<path d="m3 17 6-6 4 4 8-9M15 6h6v6"/>',
        'wallet' => '<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v16H6a3 3 0 0 1-3-3V7a.5.5 0 0 1 .5-.5H20M16 13h4"/><path d="M16 13h.01"/>',
    ];
    $path = $paths[$name] ?? $paths['info'];
    $safeClass = e($class);
    return '<svg class="' . $safeClass . '" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}
