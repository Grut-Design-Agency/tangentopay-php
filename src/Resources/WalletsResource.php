<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\WalletBalance;

class WalletsResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * @return WalletBalance[]
     */
    public function mainBalance(): array
    {
        $data = $this->http->get('/wallets/main/balance');
        return $this->parseBalances($data);
    }

    /**
     * @return WalletBalance[]
     */
    public function serviceBalance(): array
    {
        $data = $this->http->get('/wallets/service/balance');
        return $this->parseBalances($data);
    }

    /**
     * @return WalletBalance[]
     */
    public function manualBalance(): array
    {
        $data = $this->http->get('/wallets/manual/balance');
        return $this->parseBalances($data);
    }

    /** @return WalletBalance[] */
    private function parseBalances(mixed $data): array
    {
        if (is_array($data) && isset($data[0])) {
            return array_map(
                static fn(array $item): WalletBalance => WalletBalance::fromArray($item),
                $data,
            );
        }
        return [WalletBalance::fromArray((array) $data)];
    }
}
