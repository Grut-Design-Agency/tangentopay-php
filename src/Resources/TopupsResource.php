<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\PaginatedResult;
use TangentoPay\Models\Transaction;

class TopupsResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Initiate a top-up to fund your TangentoPay wallet.
     *
     * @param array{
     *   amount: float|int,
     *   currency_code?: string,
     *   idempotency_key: string,
     *   payment_source?: 'card'|'mtn_momo'|'orange_money'|'bank',
     *   account_details?: array{
     *     phone_number?: string,
     *     bank_name?: string,
     *     account_number?: string,
     *   },
     *   return_url?: string,
     *   cancel_url?: string,
     * } $params
     *
     * `idempotency_key` is required — generate once with
     * {@see TopupsResource::generateIdempotencyKey()} and reuse on every retry.
     *
     * `payment_source` defaults to `"card"` (Stripe Checkout).
     * - `"card"`:         Visa/Mastercard/Amex. Returns `redirect_url`.
     * - `"mtn_momo"`:     MTN Mobile Money (XAF). Requires `account_details.phone_number`. Coming soon.
     * - `"orange_money"`: Orange Money (XAF).   Requires `account_details.phone_number`. Coming soon.
     * - `"bank"`:         Bank transfer. Requires `account_details.bank_name` + `account_number`. Coming soon.
     */
    public function create(array $params): Transaction
    {
        if (empty($params['idempotency_key'])) {
            throw new \InvalidArgumentException(
                'idempotency_key is required. Generate one with TopupsResource::generateIdempotencyKey() ' .
                'before initiating the top-up and reuse the same value on every retry.'
            );
        }

        $source = $params['payment_source'] ?? 'card';
        $validSources = ['card', 'mtn_momo', 'orange_money', 'bank'];
        if (!in_array($source, $validSources, true)) {
            throw new \InvalidArgumentException(
                "Invalid payment_source '{$source}'. Must be one of: " . implode(', ', $validSources)
            );
        }

        if (in_array($source, ['mtn_momo', 'orange_money'], true) && empty($params['account_details']['phone_number'])) {
            throw new \InvalidArgumentException("account_details.phone_number is required for {$source} top-ups.");
        }
        if ($source === 'bank' && (empty($params['account_details']['bank_name']) || empty($params['account_details']['account_number']))) {
            throw new \InvalidArgumentException('account_details.bank_name and account_number are required for bank top-ups.');
        }

        $data = $this->http->post('/topups', $params);
        return Transaction::fromArray((array) $data);
    }

    /**
     * Generate a unique idempotency key for a top-up request.
     *
     * Call this once per top-up intent, store the result, and pass it as
     * `idempotency_key` on every attempt (including retries).
     */
    public static function generateIdempotencyKey(): string
    {
        return \bin2hex(\random_bytes(16));
    }

    /**
     * @param array{perPage?: int, page?: int} $params
     * @return PaginatedResult<Transaction>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->http->get('/topups', $params);
        return PaginatedResult::fromResponse(
            (array) $data,
            static fn(array $item): Transaction => Transaction::fromArray($item),
        );
    }
}
