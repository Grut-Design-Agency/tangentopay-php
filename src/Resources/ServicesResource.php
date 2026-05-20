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
        $data = $this->http->get('/services');
        $items = (array) ($data['data'] ?? $data);
        return array_map(
            static fn(array $item): Service => Service::fromArray($item),
            $items,
        );
    }

    public function get(int $serviceId): Service
    {
        $data = $this->http->get("/services/{$serviceId}");
        return Service::fromArray((array) $data);
    }

    /** @param array<string, mixed> $params */
    public function create(array $params): Service
    {
        $data = $this->http->post('/services', $params);
        return Service::fromArray((array) $data);
    }

    /** @param array<string, mixed> $params */
    public function update(int $serviceId, array $params): Service
    {
        $data = $this->http->put("/services/{$serviceId}", $params);
        return Service::fromArray((array) $data);
    }

    public function delete(int $serviceId): void
    {
        $this->http->delete("/services/{$serviceId}");
    }

    public function createApiKey(int $serviceId, string $type = 'live'): ApiKey
    {
        $data = $this->http->post("/services/{$serviceId}/api-keys", ['type' => $type]);
        return ApiKey::fromArray((array) $data);
    }

    /** @return ApiKey[] */
    public function listApiKeys(int $serviceId): array
    {
        $data = $this->http->get("/services/{$serviceId}/api-keys");
        $items = (array) ($data['data'] ?? $data);
        return array_map(
            static fn(array $item): ApiKey => ApiKey::fromArray($item),
            $items,
        );
    }

    public function revokeApiKey(int $serviceId, int $keyId): void
    {
        $this->http->delete("/services/{$serviceId}/api-keys/{$keyId}");
    }

    public function updateWebhook(int $serviceId, string $url): Service
    {
        $data = $this->http->put("/services/{$serviceId}/webhook", ['url' => $url]);
        return Service::fromArray((array) $data);
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
     * @param string $slug   Payment method slug, e.g. `"mtn_momo"`, `"orange_money"`.
     * @param bool   $enabled `true` to enable, `false` to disable.
     *
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
     * `"card"` must always be included or the server returns 422.
     *
     * @param string[] $enabled  Array of method slugs to enable, e.g. `['card', 'mtn_momo']`.
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
