<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class Transaction
{
    public function __construct(
        public readonly string $transactionUid,
        public readonly string $transactionStatus,
        public readonly float $finalAmount,
        public readonly string $currency,
        public readonly ?string $customerEmail,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            transactionUid: (string) ($data['transaction_uid'] ?? ''),
            transactionStatus: (string) ($data['transaction_status'] ?? ''),
            finalAmount: (float) ($data['final_amount'] ?? 0),
            currency: (string) ($data['currency'] ?? ''),
            customerEmail: isset($data['customer_email']) ? (string) $data['customer_email'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            raw: $data,
        );
    }
}
