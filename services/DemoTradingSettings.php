<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/** Reads shared Demo controls directly from site_settings; no stale process cache. */
final class DemoTradingSettings
{
    private const DEFAULTS = [
        'demo_trading_enabled' => '1',
        'demo_market_data_enabled' => '1',
        'market_orders_enabled' => '1',
        'max_open_positions_per_account' => '100',
        'max_order_volume' => '100.00',
    ];

    public static function all(?PDO $pdo = null, bool $lockForShare = false, bool $lockForUpdate = false): array
    {
        $pdo ??= Database::connection();
        $keys = array_keys(self::DEFAULTS);
        $marks = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT setting_key,setting_value FROM site_settings WHERE setting_key IN ($marks)" . ($lockForUpdate ? ' FOR UPDATE' : ($lockForShare ? ' FOR SHARE' : ''));
        $query = $pdo->prepare($sql);
        $query->execute($keys);
        $values = self::DEFAULTS;
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $values[(string)$row['setting_key']] = (string)$row['setting_value'];
        foreach (['demo_trading_enabled','demo_market_data_enabled','market_orders_enabled'] as $key) $values[$key] = $values[$key] === '1';
        $values['max_open_positions_per_account'] = max(1, min(1000, (int)$values['max_open_positions_per_account']));
        $values['max_order_volume'] = (string)$values['max_order_volume'];
        return $values;
    }

    public static function enabled(string $key): bool
    {
        if (!in_array($key, ['demo_trading_enabled','demo_market_data_enabled','market_orders_enabled'], true)) return false;
        return self::all()[$key];
    }
}
