<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class TransactionStatus
{
    public function __construct(
        public readonly string $transactionUid,
        public readonly string $transactionStatus,
        public readonly bool $isCompleted,
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = (string) ($data['transaction_status'] ?? '');
        return new self(
            transactionUid: (string) ($data['transaction_uid'] ?? ''),
            transactionStatus: $status,
            isCompleted: strtolower($status) === 'completed',
            raw: $data,
        );
    }
}
