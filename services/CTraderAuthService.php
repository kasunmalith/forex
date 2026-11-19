<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** OAuth state and allowlisted authorization URL foundation; no code exchange or token storage. */
final class CTraderAuthService
{
    public function issueState(int $adminId): string
    {
        if ($adminId < 1) throw new RuntimeException('An authorized administrator context is required.');
        $state = bin2hex(random_bytes(32));
        $_SESSION['_ctrader_oauth_state'] = ['hash'=>hash('sha256', $state), 'admin_id'=>$adminId, 'issued_at'=>time()];
        return $state;
    }

    public function consumeState(string $state, int $adminId): bool
    {
        $saved = $_SESSION['_ctrader_oauth_state'] ?? null;
        unset($_SESSION['_ctrader_oauth_state']);
        return is_array($saved) && $adminId > 0 && (int)($saved['admin_id'] ?? 0) === $adminId
            && is_int($saved['issued_at'] ?? null) && time() - $saved['issued_at'] >= 0 && time() - $saved['issued_at'] <= 600
            && is_string($saved['hash'] ?? null) && hash_equals($saved['hash'], hash('sha256', $state));
    }

    public function authorizationUrl(string $state): string
    {
        if (!CTraderConfiguration::configured() || preg_match('/^[a-f0-9]{64}$/D', $state) !== 1) {
            throw new RuntimeException('cTrader authorization is not configured.');
        }
        $scope = in_array((string)\config('CTRADER_SCOPE', 'accounts'), ['accounts','trading'], true)
            ? (string)\config('CTRADER_SCOPE', 'accounts') : 'accounts';
        return 'https://id.ctrader.com/my/settings/openapi/grantingaccess/?' . http_build_query([
            'client_id'=>(string)\config('CTRADER_CLIENT_ID'),
            'redirect_uri'=>(string)\config('CTRADER_REDIRECT_URI'),
            'scope'=>$scope,
            'product'=>'web',
            'state'=>$state,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
