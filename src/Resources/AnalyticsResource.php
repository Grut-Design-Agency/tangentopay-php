<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;

class AnalyticsResource
{
    public function __construct(private readonly HttpClient $http) {}

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return (array) $this->http->get('/analytics/dashboard');
    }

    /**
     * @param array{startDate?: string, endDate?: string, interval?: string} $params
     * @return array<string, mixed>
     */
    public function paymentsChart(array $params = []): array
    {
        return (array) $this->http->get('/analytics/payments-chart', $params);
    }

    /** @return array<string, mixed> */
    public function grossVolume(): array
    {
        return (array) $this->http->get('/analytics/gross-volume');
    }

    /** @return array<string, mixed> */
    public function totalPayouts(): array
    {
        return (array) $this->http->get('/analytics/total-payouts');
    }
}
