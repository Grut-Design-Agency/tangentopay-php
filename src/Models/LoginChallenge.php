<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class LoginChallenge
{
    public function __construct(
        public readonly string $message,
        public readonly string $email,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            message: (string) ($data['message'] ?? ''),
            email: (string) ($data['email'] ?? ''),
        );
    }
}
