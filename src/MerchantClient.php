<?php

declare(strict_types=1);

namespace TangentoPay;

use TangentoPay\Resources\AnalyticsResource;
use TangentoPay\Resources\AuthResource;
use TangentoPay\Resources\CustomersResource;
use TangentoPay\Resources\PaymentsResource;
use TangentoPay\Resources\PayoutsResource;
use TangentoPay\Resources\RefundsResource;
use TangentoPay\Resources\ServicesResource;
use TangentoPay\Resources\TopupsResource;
use TangentoPay\Resources\TransfersResource;
use TangentoPay\Resources\WalletsResource;

/**
 * Client for merchant backend operations using an API token (Bearer).
 *
 * Never expose the API token in browser or client-side code.
 */
class MerchantClient
{
    public readonly AuthResource $auth;
    public readonly PaymentsResource $payments;
    public readonly RefundsResource $refunds;
    public readonly TopupsResource $topups;
    public readonly PayoutsResource $payouts;
    public readonly TransfersResource $transfers;
    public readonly WalletsResource $wallets;
    public readonly ServicesResource $services;
    public readonly CustomersResource $customers;
    public readonly AnalyticsResource $analytics;

    private readonly HttpClient $http;

    /**
     * @param array{
     *   apiToken?: string,
     *   baseUrl?: string,
     *   timeoutS?: int,
     *   maxRetries?: int,
     *   extraHeaders?: array<string, string>,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $this->http = new HttpClient([
            'bearerToken'  => $options['apiToken'] ?? null,
            'baseUrl'      => $options['baseUrl'] ?? null,
            'timeoutS'     => $options['timeoutS'] ?? null,
            'maxRetries'   => $options['maxRetries'] ?? null,
            'extraHeaders' => $options['extraHeaders'] ?? [],
        ]);

        $this->auth      = new AuthResource($this->http);
        $this->payments  = new PaymentsResource($this->http);
        $this->refunds   = new RefundsResource($this->http);
        $this->topups    = new TopupsResource($this->http);
        $this->payouts   = new PayoutsResource($this->http);
        $this->transfers = new TransfersResource($this->http);
        $this->wallets   = new WalletsResource($this->http);
        $this->services  = new ServicesResource($this->http);
        $this->customers = new CustomersResource($this->http);
        $this->analytics = new AnalyticsResource($this->http);
    }

    public function __debugInfo(): array
    {
        return ['authenticated' => true];
    }
}
