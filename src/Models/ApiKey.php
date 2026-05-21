<?php

declare(strict_types=1);

namespace TangentoPay\Models;

/**
 * Represents a service API key pair.
 *
 * Each pair bundles three credentials generated together:
 *   - publicKey  (pk_test_… / pk_live_…)  — pass as X-Service-Key header.
 *   - secretKey  (sk_test_… / sk_live_…)  — reserved for privileged server calls.
 *   - webhookSecret (whs_test_… / whs_live_…) — paste into your WordPress plugin
 *     or server webhook handler to verify incoming HMAC-SHA256 signatures.
 *
 * secretKey and webhookSecret are returned **only once** — on creation or rotation.
 * They are null in all subsequent list responses.  Store them immediately.
 */
class ApiKey
{
    public function __construct(
        public readonly int     $id,
        public readonly ?string $keyName,
        /** "test" or "live" */
        public readonly ?string $keyType,
        /** pk_test_… / pk_live_… — used as X-Service-Key header */
        public readonly ?string $publicKey,
        /** sk_test_… / sk_live_… — shown once only on creation/rotation */
        public readonly ?string $secretKey,
        /** whs_test_… / whs_live_… — shown once only on creation/rotation */
        public readonly ?string $webhookSecret,
        public readonly bool    $isActive,
        public readonly ?string $lastUsedAt,
        public readonly ?string $expiresAt,
        public readonly ?string $createdAt,
        /** @var array<string, mixed> Raw API response for forward-compatibility */
        public readonly array   $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:            (int)    ($data['id']             ?? 0),
            keyName:       isset($data['key_name'])    ? (string) $data['key_name']    : null,
            keyType:       isset($data['key_type'])    ? (string) $data['key_type']    : null,
            publicKey:     isset($data['public_key'])  ? (string) $data['public_key']  : null,
            secretKey:     isset($data['secret_key'])  ? (string) $data['secret_key']  : null,
            webhookSecret: isset($data['webhook_secret']) ? (string) $data['webhook_secret'] : null,
            isActive:      (bool) ($data['is_active']   ?? true),
            lastUsedAt:    isset($data['last_used_at']) ? (string) $data['last_used_at'] : null,
            expiresAt:     isset($data['expires_at'])   ? (string) $data['expires_at']   : null,
            createdAt:     isset($data['created_at'])   ? (string) $data['created_at']   : null,
            raw:           $data,
        );
    }
}
