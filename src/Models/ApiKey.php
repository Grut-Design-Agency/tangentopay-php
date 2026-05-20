<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class ApiKey
{
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $type,
        public readonly ?string $createdAt,
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            key: (string) ($data['key'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            raw: $data,
        );
    }
}
