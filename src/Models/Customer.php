<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class Customer
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly ?string $createdAt,
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            email: (string) ($data['email'] ?? ''),
            name: isset($data['name']) ? (string) $data['name'] : null,
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            raw: $data,
        );
    }
}
