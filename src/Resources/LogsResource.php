<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;

/**
 * Per-service API request logs — mirrors the Dashboard → Service → Logs tab.
 *
 * Every request authenticated via X-Service-Key is recorded automatically.
 * Use these methods to retrieve the log data programmatically.
 */
class LogsResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * List API request logs for a service.
     *
     * Returns a paginated list of log entries matching what is shown in
     * the Dashboard → Service → Logs view.
     *
     * @param  int                                    $serviceId  Numeric service ID.
     * @param  array{
     *     per_page?: int,
     *     status?:   int,
     *     method?:   string,
     *     date_from?: string,
     *     date_to?:  string,
     *     page?:     int,
     * }                                              $params     Optional query filters.
     * @return array<string, mixed>                              Paginated response.
     */
    public function list(int $serviceId, array $params = []): array
    {
        return (array) $this->http->get("/services/{$serviceId}/logs", $params);
    }

    /**
     * Retrieve the full detail of a single log entry.
     *
     * @param  int    $serviceId  Numeric service ID.
     * @param  string $requestId  UUID from the X-Request-ID response header.
     * @return array<string, mixed>
     */
    public function get(int $serviceId, string $requestId): array
    {
        return (array) $this->http->get("/services/{$serviceId}/logs/{$requestId}");
    }
}
