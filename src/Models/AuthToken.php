<?php

declare(strict_types=1);

namespace TangentoPay\Models;

class AuthToken
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly ?int $expiresIn = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: (string) ($data['access_token'] ?? ''),
            tokenType: (string) ($data['token_type'] ?? 'Bearer'),
            expiresIn: isset($data['expires_in']) ? (int) $data['expires_in'] : null,
        );
    }
}
