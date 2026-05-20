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
     * Initiate a top-up to fund your TangentoPay wallet via Stripe Checkout.
     *
     * @param array{
     *   amount: float|int,
     *   currency_code?: string,
     *   return_url?: string,
     *   cancel_url?: string,
     *   idempotency_key: string,
     * } $params  idempotency_key is required — generate once with
     *            {@see TopupsResource::generateIdempotencyKey()} and reuse on
     *            every retry of the same top-up intent.
     */
    public function create(array $params): Transaction
    {
        if (empty($params['idempotency_key'])) {
            throw new \InvalidArgumentException(
                'idempotency_key is required. Generate one with TopupsResource::generateIdempotencyKey() ' .
                'before initiating the top-up and reuse the same value on every retry.'
            );
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
