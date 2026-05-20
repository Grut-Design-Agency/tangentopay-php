<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\PaginatedResult;
use TangentoPay\Models\Transaction;

class TransfersResource
{
    public function __construct(private readonly HttpClient $http) {}

    /** @param array<string, mixed> $params */
    public function toMain(array $params): Transaction
    {
        $data = $this->http->post('/transfers/to-main', $params);
        return Transaction::fromArray((array) $data);
    }

    /**
     * @param array{perPage?: int, page?: int} $params
     * @return PaginatedResult<Transaction>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->http->get('/transfers', $params);
        return PaginatedResult::fromResponse(
            (array) $data,
            static fn(array $item): Transaction => Transaction::fromArray($item),
        );
    }
}
