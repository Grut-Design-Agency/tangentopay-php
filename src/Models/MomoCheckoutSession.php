<?php

declare(strict_types=1);

namespace TangentoPay\Models;

/**
 * Response from a MoMo (USSD-push) checkout session creation.
 *
 * @see \TangentoPay\Resources\CheckoutResource::createMomo()
 */
class MomoCheckoutSession
{
    public function __construct(
        /** Unique TangentoPay transaction identifier — pass to getStatus(). */
        public readonly string $transactionUid,
        /** Initial status, typically "pending" while awaiting customer PIN. */
        public readonly string $status,
        /** Amount the merchant wallet will be credited after Fapshi's fee (XAF). */
        public readonly int $netAmount,
        /** Amount charged to the customer's MoMo account (XAF, includes fee). */
        public readonly int $grossAmount,
        /** Fapshi's 2.2% processing fee deducted from the gross amount (XAF). */
        public readonly int $feeXaf,
        /** Detected provider: "mtn_momo" or "orange_money". Null if not yet determined. */
        public readonly ?string $provider,
        /** Full raw API response for forward-compatibility. */
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            transactionUid: (string) ($data['transaction_uid'] ?? ''),
            status:         (string) ($data['status']          ?? 'pending'),
            netAmount:      (int)    ($data['net_amount']      ?? 0),
            grossAmount:    (int)    ($data['gross_amount']    ?? 0),
            feeXaf:         (int)    ($data['fee_xaf']         ?? 0),
            provider:       isset($data['provider']) ? (string) $data['provider'] : null,
            raw:            $data,
        );
    }
}
