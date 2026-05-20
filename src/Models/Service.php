<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class Service
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $webhookUrl,
        public readonly ?string $createdAt,
        /** @var array<string, mixed> */
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            webhookUrl: isset($data['webhook_url']) ? (string) $data['webhook_url'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            raw: $data,
        );
    }
}
