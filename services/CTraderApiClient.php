<?php
declare(strict_types=1);

namespace App\Services;

/** Trusted official Open API endpoint profile. The transport is intentionally inactive in Phase 22. */
final class CTraderApiClient
{
    public function connectionProfile(): array
    {
        $environment = CTraderConfiguration::environment();
        if ($environment === 'invalid') throw new ProviderException('invalid_request', 'The cTrader environment configuration is invalid.', 422);
        return [
            'host'=>$environment === 'live' ? 'live.ctraderapi.com' : 'demo.ctraderapi.com',
            'port'=>5036,
            'protocol'=>'official-json-websocket-or-tcp',
            'tls_required'=>true,
            'verify_peer'=>true,
            'connect_timeout'=>max(1, min(15, (int)\config('CTRADER_CONNECT_TIMEOUT_SECONDS', 5))),
            'read_timeout'=>max(1, min(30, (int)\config('CTRADER_READ_TIMEOUT_SECONDS', 10))),
        ];
    }

    public function request(array $message): never
    {
        throw new ProviderException('unavailable', 'The cTrader transport is not enabled in this phase.');
    }
}
