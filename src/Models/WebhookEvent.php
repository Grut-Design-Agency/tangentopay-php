<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class WebhookEvent
{
    public function __construct(
        public readonly string $event,
        /** @var array<string, mixed> */
        public readonly array $payload,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            event: (string) ($data['event'] ?? ''),
            payload: (array) ($data['payload'] ?? []),
        );
    }
}
