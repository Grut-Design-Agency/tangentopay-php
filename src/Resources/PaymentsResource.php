<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\PaginatedResult;
use TangentoPay\Models\Transaction;

class PaymentsResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * @param array{perPage?: int, page?: int, status?: string} $params
     * @return PaginatedResult<Transaction>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->http->get('/payments', $params);
        return PaginatedResult::fromResponse(
            (array) $data,
            static fn(array $item): Transaction => Transaction::fromArray($item),
        );
    }

    public function get(string $transactionUid): Transaction
    {
        $data = $this->http->get("/payments/{$transactionUid}");
        return Transaction::fromArray((array) $data);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createManual(array $payload): Transaction
    {
        $data = $this->http->post('/payments/manual', $payload);
        return Transaction::fromArray((array) $data);
    }
}
