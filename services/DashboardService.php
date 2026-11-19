<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class DashboardService
{
    private const DEFAULT_SYMBOLS = ['EURUSD', 'GBPUSD', 'USDJPY', 'XAUUSD', 'NAS100', 'BTCUSD'];

    public static function getUser(int $userId): array
    {
        $query = Database::connection()->prepare(
            'SELECT u.id,u.first_name,u.last_name,u.email,u.phone,u.country,u.created_at,
                    p.country AS profile_country
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id=u.id
             WHERE u.id=:user_id
             LIMIT 1'
        );
        $query->execute(['user_id' => $userId]);
        $user = $query->fetch();
        if (!$user) {
            throw new RuntimeException('Authenticated user profile is unavailable.');
        }
        return $user;
    }

    public static function getAccount(int $userId): ?array
    {
        $query = Database::connection()->prepare(
            "SELECT id,user_id,account_number,account_type,currency,balance,realized_pl,status,created_at
             FROM demo_accounts
             WHERE user_id=:user_id
             ORDER BY FIELD(status,'active','suspended','disabled','closed'),created_at DESC,id DESC
             LIMIT 1"
        );
        $query->execute(['user_id' => $userId]);
        $account = $query->fetch();
        return $account ?: null;
    }

    public static function getDashboard(int $userId): array
    {
        $user = self::getUser($userId);
        $account = self::getAccount($userId);
        $notifications = self::getNotifications($userId);
        if ($account === null) {
            error_log('Dashboard account lookup found no demo account for authenticated user id ' . $userId . '.');
            return [
                'user' => $user, 'account' => null, 'notifications' => $notifications,
                'metrics' => null, 'stats' => ['total'=>0,'wins'=>0,'losses'=>0,'realized_pl'=>'0.00000000'],
                'positions' => [], 'orders' => [], 'trades' => [],
                'watchlist' => self::getWatchlist($userId), 'watchlistSaved' => false,
            ];
        }

        $stats = self::getTradingStats($userId, (int) $account['id']);
        $metrics = self::getMetrics($userId, $account, $stats);
        $watchlist = self::getWatchlist($userId);
        return [
            'user' => $user,
            'account' => $account,
            'notifications' => $notifications,
            'metrics' => $metrics,
            'stats' => $stats,
            'positions' => self::getOpenPositions($userId, (int) $account['id'], 5),
            'orders' => self::getRecentOrders($userId, (int) $account['id'], 5),
            'trades' => self::getRecentTrades($userId, (int) $account['id'], 5),
            'watchlist' => $watchlist['items'],
            'watchlistSaved' => $watchlist['saved'],
        ];
    }

    public static function getTradingTerminalData(int $userId, ?string $requestedSymbol): array
    {
        $query = Database::connection()->prepare(
            "SELECT i.id,i.symbol,i.display_name,i.base_currency,i.quote_currency,i.profit_currency,i.bid,i.ask,i.spread,
                    i.precision_digits,i.tick_size,i.pip_size,i.contract_size,i.margin_rate,i.min_volume,i.max_volume,i.volume_step,i.trading_enabled,i.market_data_enabled,i.status,c.name AS category,
                    CASE WHEN w.id IS NULL THEN 0 ELSE 1 END AS is_favorite
             FROM instruments i
             INNER JOIN instrument_categories c ON c.id=i.category_id
             LEFT JOIN watchlists w ON w.instrument_id=i.id AND w.user_id=:user_id
             WHERE i.status='active' AND c.status='active'
             ORDER BY c.display_order,i.display_order,i.symbol
             LIMIT 250"
        );
        $query->execute(['user_id' => $userId]);
        $instruments = $query->fetchAll();
        if ($instruments === []) {
            throw new RuntimeException('No enabled demo instruments are available.');
        }

        $selected = null;
        if ($requestedSymbol !== null && preg_match('/^[A-Z0-9._-]{1,32}$/', $requestedSymbol) === 1) {
            foreach ($instruments as $instrument) {
                if ($instrument['symbol'] === $requestedSymbol) {
                    $selected = $instrument;
                    break;
                }
            }
        }
        if ($selected === null) {
            foreach ($instruments as $instrument) {
                if ($instrument['symbol'] === 'EURUSD') {
                    $selected = $instrument;
                    break;
                }
            }
        }
        $selected ??= $instruments[0];

        $user = self::getUser($userId);
        $account = self::getAccount($userId);
        $notifications = self::getNotifications($userId);
        $metrics = null;
        $positions = $orders = $trades = [];
        if ($account !== null) {
            $stats = self::getTradingStats($userId, (int) $account['id']);
            $metrics = self::getMetrics($userId, $account, $stats);
            $positions = self::getOpenPositions($userId, (int) $account['id'], 100);
            $orders = self::getRecentOrders($userId, (int) $account['id'], 50);
            $trades = self::getRecentTrades($userId, (int) $account['id'], 50);
        }

        return [
            'user' => $user,
            'account' => $account,
            'notifications' => $notifications,
            'metrics' => $metrics,
            'instruments' => $instruments,
            'selectedInstrument' => $selected,
            'positions' => $positions,
            'orders' => $orders,
            'trades' => $trades,
            'tradingSettings' => DemoTradingSettings::all(),
        ];
    }

    public static function getMetrics(int $userId, array $account, ?array $stats = null): array
    {
        $snapshot = AccountMetricsService::demo()->snapshot($userId,(int)$account['id']);
        return $snapshot['metrics'] + ['realized_pl'=>(string)($stats['realized_pl']??'0.00000000'),'has_complete_position_prices'=>$snapshot['metrics']['floating_pl']!==null];
    }

    public static function getTradingStats(int $userId, int $accountId): array
    {
        $query = Database::connection()->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN profit_loss>0 THEN 1 ELSE 0 END),0) AS wins,
                    COALESCE(SUM(CASE WHEN profit_loss<0 THEN 1 ELSE 0 END),0) AS losses,
                    CAST(COALESCE(SUM(profit_loss),0) AS DECIMAL(20,8)) AS realized_pl
             FROM trades
             WHERE user_id=:user_id AND demo_account_id=:account_id"
        );
        $query->execute(['user_id' => $userId, 'account_id' => $accountId]);
        $row = $query->fetch() ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'wins' => (int) ($row['wins'] ?? 0),
            'losses' => (int) ($row['losses'] ?? 0),
            'realized_pl' => (string) ($row['realized_pl'] ?? '0.00000000'),
        ];
    }

    public static function getOpenPositions(int $userId, int $accountId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        return array_slice(AccountMetricsService::demo()->snapshot($userId,$accountId)['positions'],0,$limit);
    }

    public static function getRecentOrders(int $userId, int $accountId, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $query = Database::connection()->prepare(
            "SELECT o.id,o.instrument_id,o.order_type,o.direction,o.volume,o.requested_price,o.execution_price,
                    o.stop_loss,o.take_profit,o.status,o.created_at,
                    i.symbol,i.display_name,i.precision_digits
             FROM orders o
             INNER JOIN instruments i ON i.id=o.instrument_id
             INNER JOIN demo_accounts d ON d.id=o.demo_account_id AND d.user_id=o.user_id
             WHERE o.user_id=:user_id AND o.demo_account_id=:account_id
             ORDER BY o.created_at DESC,o.id DESC
             LIMIT $limit"
        );
        $query->execute(['user_id' => $userId, 'account_id' => $accountId]);
        return $query->fetchAll();
    }

    public static function getRecentTrades(int $userId, int $accountId, int $limit = 5, int $offset = 0): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $query = Database::connection()->prepare(
            "SELECT t.id,t.instrument_id,t.direction,t.volume,t.open_price,t.close_price,t.profit_loss,t.close_reason,t.trade_reference,t.opened_at,t.closed_at,
                    i.symbol,i.display_name,i.precision_digits
             FROM trades t
             INNER JOIN instruments i ON i.id=t.instrument_id
             INNER JOIN demo_accounts d ON d.id=t.demo_account_id AND d.user_id=t.user_id
             WHERE t.user_id=:user_id AND t.demo_account_id=:account_id
             ORDER BY t.closed_at DESC,t.id DESC
             LIMIT $limit OFFSET $offset"
        );
        $query->execute(['user_id' => $userId, 'account_id' => $accountId]);
        return $query->fetchAll();
    }

    public static function countTrades(int $userId, int $accountId): int
    {
        $query = Database::connection()->prepare(
            'SELECT COUNT(*) FROM trades WHERE user_id=:user_id AND demo_account_id=:account_id'
        );
        $query->execute(['user_id' => $userId, 'account_id' => $accountId]);
        return (int) $query->fetchColumn();
    }

    public static function getWatchlist(int $userId): array
    {
        $query = Database::connection()->prepare(
            'SELECT w.instrument_id,i.symbol,i.display_name,i.quote_currency,i.bid AS demo_price,
                    i.precision_digits,c.name AS category,w.created_at
             FROM watchlists w
             INNER JOIN instruments i ON i.id=w.instrument_id AND i.status=\'active\'
             INNER JOIN instrument_categories c ON c.id=i.category_id AND c.status=\'active\'
             WHERE w.user_id=:user_id
             ORDER BY w.created_at DESC,w.id DESC
             LIMIT 100'
        );
        $query->execute(['user_id' => $userId]);
        $items = $query->fetchAll();
        if ($items !== []) {
            return ['items' => $items, 'saved' => true];
        }

        $symbols = self::DEFAULT_SYMBOLS;
        $placeholders = implode(',', array_fill(0, count($symbols), '?'));
        $order = implode(',', array_fill(0, count($symbols), '?'));
        $fallback = Database::connection()->prepare(
            "SELECT i.id AS instrument_id,i.symbol,i.display_name,i.quote_currency,i.bid AS demo_price,
                    i.precision_digits,c.name AS category,NULL AS created_at
             FROM instruments i
             INNER JOIN instrument_categories c ON c.id=i.category_id
             WHERE i.status='active' AND c.status='active' AND i.symbol IN ($placeholders)
             ORDER BY FIELD(i.symbol,$order)
             LIMIT 8"
        );
        $fallback->execute([...$symbols, ...$symbols]);
        return ['items' => $fallback->fetchAll(), 'saved' => false];
    }

    public static function getAvailableInstruments(): array
    {
        $query = Database::connection()->query(
            "SELECT i.id,i.symbol,i.display_name,i.precision_digits,c.name AS category
             FROM instruments i
             INNER JOIN instrument_categories c ON c.id=i.category_id
             WHERE i.status='active' AND c.status='active'
             ORDER BY c.display_order,i.display_order,i.symbol
             LIMIT 100"
        );
        return $query->fetchAll();
    }

    public static function addWatchlistItem(int $userId, int $instrumentId): bool
    {
        $query = Database::connection()->prepare(
            "INSERT INTO watchlists (user_id,instrument_id)
             SELECT :user_id,i.id FROM instruments i
             JOIN instrument_categories c ON c.id=i.category_id AND c.status='active'
             WHERE i.id=:instrument_id AND i.status='active'"
        );
        try {
            $query->execute(['user_id' => $userId, 'instrument_id' => $instrumentId]);
            return $query->rowCount() === 1;
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return false;
            }
            throw $exception;
        }
    }

    public static function removeWatchlistItem(int $userId, int $instrumentId): bool
    {
        $query = Database::connection()->prepare(
            'DELETE FROM watchlists WHERE user_id=:user_id AND instrument_id=:instrument_id'
        );
        $query->execute(['user_id' => $userId, 'instrument_id' => $instrumentId]);
        return $query->rowCount() === 1;
    }

    public static function getNotifications(int $userId): array
    {
        return NotificationService::demo()->recentForUser($userId, 6);
    }

    public static function formatMoney(string|int|float|null $amount, string $currency = 'USD', bool $signed = false): string
    {
        $value = trim((string) ($amount ?? ''));
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $match) !== 1) {
            return strtoupper($currency) . ' —';
        }
        $negative = $match[1] === '-';
        $whole = ltrim($match[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad(substr($match[3] ?? '', 0, 3), 3, '0');
        $minor = substr($fraction, 0, 2);
        $digits = ltrim($whole . $minor, '0');
        $digits = $digits === '' ? '0' : $digits;
        if ($fraction[2] >= '5') {
            for ($i = strlen($digits) - 1; $i >= 0; $i--) {
                if ($digits[$i] !== '9') {
                    $digits[$i] = (string) ((int) $digits[$i] + 1);
                    break;
                }
                $digits[$i] = '0';
                if ($i === 0) {
                    $digits = '1' . $digits;
                }
            }
        }
        $digits = str_pad($digits, 3, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -2);
        $minor = substr($digits, -2);
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;
        $zero = $whole === '0' && $minor === '00';
        $sign = $negative && !$zero ? '-' : ($signed && !$negative && !$zero ? '+' : '');
        $currency = strtoupper($currency);
        $symbol = $currency === 'USD' ? '$' : $currency . ' ';
        return $sign . $symbol . $whole . '.' . $minor;
    }

    public static function formatPrice(string $price, int $precision): string
    {
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', trim($price), $match) !== 1) {
            return '—';
        }
        $whole = ltrim($match[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;
        $precision = max(0, min(10, $precision));
        if ($precision === 0) {
            return $whole;
        }
        return $whole . '.' . str_pad(substr($match[2] ?? '', 0, $precision), $precision, '0');
    }

    public static function formatVolume(string $volume): string
    {
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', trim($volume), $match) !== 1) {
            return '—';
        }
        $fraction = rtrim($match[2] ?? '', '0');
        if ($fraction === '') {
            return $match[1] . '.00';
        }
        return $match[1] . '.' . str_pad($fraction, 2, '0');
    }

    public static function formatDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        try {
            return (new DateTimeImmutable($value, new DateTimeZone(date_default_timezone_get())))->format('M j, Y · H:i');
        } catch (\Throwable) {
            return '—';
        }
    }
}
