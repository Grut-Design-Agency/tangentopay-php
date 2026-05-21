<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\ApiKey;
use TangentoPay\Models\Service;

class ServicesResource
{
    public function __construct(private readonly HttpClient $http) {}

    /** @return Service[] */
    public function listAll(): array
    {
        $data  = $this->http->get('/services');
        $items = (array) ($data['data'] ?? $data);
        return array_map(
            static fn(array $item): Service => Service::fromArray($item),
            $items,
        );
    }

    public function get(int $serviceId): Service
    {
        $data = $this->http->get("/services/{$serviceId}");
        return Service::fromArray((array) ($data['data'] ?? $data));
    }

    /** @param array<string, mixed> $params */
    public function create(array $params): Service
    {
        $data = $this->http->post('/services', $params);
        return Service::fromArray((array) ($data['data'] ?? $data));
    }

    /** @param array<string, mixed> $params */
    public function update(int $serviceId, array $params): Service
    {
        $data = $this->http->put("/services/{$serviceId}", $params);
        return Service::fromArray((array) ($data['data'] ?? $data));
    }

    public function delete(int $serviceId): void
    {
        $this->http->delete("/services/{$serviceId}");
    }

    // -------------------------------------------------------------------------
    // API key pair management
    // -------------------------------------------------------------------------

    /**
     * Create a new API key pair for a service.
     *
     * Returns publicKey, secretKey, and webhookSecret **once only** — store
     * them immediately, they cannot be retrieved again.
     *
     * Only one active key per key_type is allowed per service.  If one already
     * exists, call rotateApiKey() instead.
     *
     * @param  string      $keyName   Human-readable label, e.g. "WordPress production".
     * @param  string      $keyType   "test" for Stripe test environment, "live" for real charges.
     * @param  string|null $expiresAt Optional expiry date in YYYY-MM-DD format.
     *
     * @example
     * $pair = $merchant->services->createApiKey($serviceId, 'WordPress production', 'live');
     * // Store immediately — shown once only:
     * echo $pair->publicKey;      // pk_live_…  → X-Service-Key header
     * echo $pair->secretKey;      // sk_live_…  → privileged calls
     * echo $pair->webhookSecret;  // whs_live_… → webhook verification
     */
    public function createApiKey(
        int     $serviceId,
        string  $keyName,
        string  $keyType    = 'live',
        ?string $expiresAt  = null,
    ): ApiKey {
        $payload = ['key_name' => $keyName, 'key_type' => $keyType];
        if ($expiresAt !== null) {
            $payload['expires_at'] = $expiresAt;
        }
        $data = $this->http->post("/services/{$serviceId}/api-keys", $payload);
        return ApiKey::fromArray((array) ($data['data'] ?? $data));
    }

    /**
     * List all API key pairs for a service.
     *
     * Secrets (secretKey, webhookSecret) are never included in list responses.
     *
     * @return ApiKey[]
     */
    public function listApiKeys(int $serviceId): array
    {
        $data  = $this->http->get("/services/{$serviceId}/api-keys");
        $items = (array) ($data['data'] ?? $data);
        return array_map(
            static fn(array $item): ApiKey => ApiKey::fromArray($item),
            $items,
        );
    }

    /**
     * Rotate a key pair — generates new publicKey, secretKey, and webhookSecret atomically.
     *
     * The old credentials stop working immediately.  New secrets are returned
     * once only — store them immediately.
     *
     * @example
     * $rotated = $merchant->services->rotateApiKey($serviceId, $keyId);
     * echo $rotated->publicKey;      // new pk_live_…
     * echo $rotated->webhookSecret;  // new whs_live_…
     */
    public function rotateApiKey(int $serviceId, int $keyId): ApiKey
    {
        $data = $this->http->post("/services/{$serviceId}/api-keys/{$keyId}/rotate", []);
        return ApiKey::fromArray((array) ($data['data'] ?? $data));
    }

    /**
     * Soft-delete a key pair, vacating the type slot so a new pair can be created.
     */
    public function revokeApiKey(int $serviceId, int $keyId): void
    {
        $this->http->delete("/services/{$serviceId}/api-keys/{$keyId}");
    }

    // -------------------------------------------------------------------------
    // Webhooks
    // -------------------------------------------------------------------------

    /**
     * Set or update the outbound webhook URL for a service.
     *
     * The webhook signing secret comes from the API key pair — it is shown
     * once when you create or rotate a key, not here.
     *
     * @param  string[]|null $events  Event types to subscribe to. Pass null for all events.
     * @return array<string, mixed>
     */
    public function updateWebhook(int $serviceId, string $url, ?array $events = null): array
    {
        $payload = ['webhook_url' => $url];
        if ($events !== null) {
            $payload['webhook_events'] = $events;
        }
        return (array) $this->http->put("/services/{$serviceId}/webhook", $payload);
    }

    // -------------------------------------------------------------------------
    // Payment methods
    // -------------------------------------------------------------------------

    /**
     * List all payment methods for this service with enabled/locked/reason status.
     *
     * Returns an array of associative arrays with keys:
     * `slug`, `name`, `type`, `provider`, `enabled`, `locked`, `reason`, `currencies`.
     *
     * Card is always enabled and cannot be disabled.
     * Premium methods (mtn_momo, orange_money, etc.) require a KYB-verified
     * company account — they will have `locked => true` otherwise.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPaymentMethods(int $serviceId): array
    {
        $data = $this->http->get("/services/{$serviceId}/payment-methods");
        return (array) ($data['data'] ?? []);
    }

    /**
     * Enable or disable a single payment method on this service.
     *
     * @param  string $slug    Payment method slug, e.g. "mtn_momo", "orange_money".
     * @param  bool   $enabled true to enable, false to disable.
     * @return array<string, mixed>
     *
     * @example
     * $merchant->services->setPaymentMethod($serviceId, 'mtn_momo', true);
     */
    public function setPaymentMethod(int $serviceId, string $slug, bool $enabled): array
    {
        $data = $this->http->patch(
            "/services/{$serviceId}/payment-methods/{$slug}",
            ['enabled' => $enabled],
        );
        return (array) ($data['data'] ?? $data);
    }

    /**
     * Replace the full set of enabled payment methods for this service.
     * "card" must always be included or the server returns 422.
     *
     * @param string[] $enabled  Array of method slugs to enable, e.g. ['card', 'mtn_momo'].
     *
     * @example
     * $merchant->services->setPaymentMethods($serviceId, ['card', 'mtn_momo']);
     */
    public function setPaymentMethods(int $serviceId, array $enabled): void
    {
        $this->http->put(
            "/services/{$serviceId}/payment-methods",
            ['enabled' => $enabled],
        );
    }
}
