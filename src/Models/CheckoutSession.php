<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class CheckoutSession
{
    public function __construct(
        public readonly string $transactionUid,
        public readonly string $redirectUrl,
        public readonly ?string $status,
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            transactionUid: (string) ($data['transaction_uid'] ?? ''),
            redirectUrl: (string) ($data['redirect_url'] ?? ''),
            status: isset($data['status']) ? (string) $data['status'] : null,
            raw: $data,
        );
    }
}
