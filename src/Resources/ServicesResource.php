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
}
