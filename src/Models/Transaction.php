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
        /** Net amount the recipient receives after TangentoPay's fee (payouts/transfers). */
        public readonly ?float $netAmount,
        /** TangentoPay fee amount (e.g. 4% of withdrawal amount). */
        public readonly ?float $tangentoPayFee,
        /** TangentoPay fee rate applied (e.g. 4.0 for 4%). */
        public readonly ?float $tangentoPayFeeRate,
        /** Gross amount charged to user's phone on MoMo top-ups (amount + Fapshi's 2.2% fee). */
        public readonly ?float $grossAmount,
        /** Fapshi's own fee component on MoMo top-ups. */
        public readonly ?float $fapshiFee,
        /** Payment provider: 'stripe' or 'fapshi'. */
        public readonly ?string $paymentProvider,
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
            transactionUid:     (string) ($data['transaction_uid'] ?? ''),
            transactionStatus:  (string) ($data['transaction_status'] ?? ''),
            finalAmount:        (float)  ($data['final_amount'] ?? 0),
            currency:           (string) ($data['currency_code'] ?? $data['currency'] ?? ''),
            customerEmail:      isset($data['customer_email'])       ? (string) $data['customer_email']       : null,
            createdAt:          isset($data['created_at'])           ? (string) $data['created_at']           : null,
            updatedAt:          isset($data['updated_at'])           ? (string) $data['updated_at']           : null,
            redirectUrl:        $redirectUrl !== null                ? (string) $redirectUrl                  : null,
            netAmount:          isset($data['net_amount'])           ? (float)  $data['net_amount']           : null,
            tangentoPayFee:     isset($data['tangentopay_fee'])      ? (float)  $data['tangentopay_fee']      : null,
            tangentoPayFeeRate: isset($data['tangentopay_fee_rate']) ? (float)  $data['tangentopay_fee_rate'] : null,
            grossAmount:        isset($data['gross_amount'])         ? (float)  $data['gross_amount']         : null,
            fapshiFee:          isset($data['fapshi_fee'])           ? (float)  $data['fapshi_fee']           : null,
            paymentProvider:    isset($data['payment_provider'])     ? (string) $data['payment_provider']     : null,
            raw:                $data,
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
