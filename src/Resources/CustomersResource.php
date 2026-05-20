<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\Customer;
use TangentoPay\Models\PaginatedResult;

class CustomersResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * @param array{perPage?: int, page?: int, search?: string} $params
     * @return PaginatedResult<Customer>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->http->get('/customers', $params);
        return PaginatedResult::fromResponse(
            (array) $data,
            static fn(array $item): Customer => Customer::fromArray($item),
        );
    }

    public function get(int $customerId): Customer
    {
        $data = $this->http->get("/customers/{$customerId}");
        return Customer::fromArray((array) $data);
    }

    /** @param array<string, mixed> $params */
    public function create(array $params): Customer
    {
        $data = $this->http->post('/customers', $params);
        return Customer::fromArray((array) $data);
    }

    /** @param array<string, mixed> $params */
    public function update(int $customerId, array $params): Customer
    {
        $data = $this->http->put("/customers/{$customerId}", $params);
        return Customer::fromArray((array) $data);
    }

    public function delete(int $customerId): void
    {
        $this->http->delete("/customers/{$customerId}");
    }

    /**
     * Import customers from a CSV string.
     *
     * @return array<string, mixed>
     */
    public function importCsv(string $csvContent): array
    {
        return (array) $this->http->post('/customers/import', ['csv' => $csvContent]);
    }
}
