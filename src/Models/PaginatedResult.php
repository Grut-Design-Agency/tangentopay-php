<?php

declare(strict_types=1);

namespace TangentoPay\Models;

/**
 * @template T
 */
class PaginatedResult
{
    /**
     * @param T[] $data
     */
    public function __construct(
        public readonly array $data,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
        public readonly int $lastPage,
    ) {}

    /**
     * @template U
     * @param array<string, mixed> $response
     * @param callable(array<string, mixed>): U $factory
     * @return self<U>
     */
    public static function fromResponse(array $response, callable $factory): self
    {
        $items = array_map($factory, (array) ($response['data'] ?? []));

        return new self(
            data: $items,
            total: (int) ($response['total'] ?? count($items)),
            perPage: (int) ($response['per_page'] ?? count($items)),
            currentPage: (int) ($response['current_page'] ?? 1),
            lastPage: (int) ($response['last_page'] ?? 1),
        );
    }
}
