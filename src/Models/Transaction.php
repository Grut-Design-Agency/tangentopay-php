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
        /**
         * Stripe Checkout redirect URL, present on card top-ups and checkout sessions.
         * Maps from either `payment_link` or `redirect_url` in the API response.
         */
        public readonly ?string $redirectUrl,
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        // `payment_link` is the canonical field; `redirect_url` is an alias
        // returned by the top-up endpoint and some other flows.
        $redirectUrl = $data['payment_link'] ?? $data['redirect_url'] ?? null;

        return new self(
            transactionUid:    (string) ($data['transaction_uid'] ?? ''),
            transactionStatus: (string) ($data['transaction_status'] ?? ''),
            finalAmount:       (float)  ($data['final_amount'] ?? 0),
            currency:          (string) ($data['currency'] ?? ''),
            customerEmail:     isset($data['customer_email'])  ? (string) $data['customer_email']  : null,
            createdAt:         isset($data['created_at'])      ? (string) $data['created_at']      : null,
            updatedAt:         isset($data['updated_at'])      ? (string) $data['updated_at']      : null,
            redirectUrl:       $redirectUrl !== null ? (string) $redirectUrl : null,
            raw:               $data,
        );
    }

    public function isCompleted(): bool
    {
        return $this->transactionStatus === 'completed';
    }

    public function isPending(): bool
    {
        return in_array($this->transactionStatus, ['pending', 'processing'], true);
    }

    public function isFailed(): bool
    {
        return in_array($this->transactionStatus, ['failed', 'cancelled'], true);
    }
}
