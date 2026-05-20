<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\PaginatedResult;
use TangentoPay\Models\Transaction;

class RefundsResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * @param array{
     *   transactionUid: string,
     *   amount: float|int,
     *   reason: string,
     *   pin: string,
     *   recipientType: string,
     * } $params
     */
    public function create(array $params): Transaction
    {
        $data = $this->http->post('/refunds', $params);
        return Transaction::fromArray((array) $data);
    }

    /**
     * @param array{perPage?: int, page?: int} $params
     * @return PaginatedResult<Transaction>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->http->get('/refunds', $params);
        return PaginatedResult::fromResponse(
            (array) $data,
            static fn(array $item): Transaction => Transaction::fromArray($item),
        );
    }
}
