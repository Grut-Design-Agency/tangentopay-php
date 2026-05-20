<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class WalletBalance
{
    public function __construct(
        public readonly float $availableBalance,
        public readonly float $pendingBalance,
        public readonly string $currency,
        public readonly ?string $walletType,
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            availableBalance: (float) ($data['available_balance'] ?? 0),
            pendingBalance: (float) ($data['pending_balance'] ?? 0),
            currency: (string) ($data['currency'] ?? ''),
            walletType: isset($data['wallet_type']) ? (string) $data['wallet_type'] : null,
            raw: $data,
        );
    }
}
