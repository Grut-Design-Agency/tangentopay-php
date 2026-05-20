<?php

declare(strict_types=1);

namespace TangentoPay;

use TangentoPay\Resources\CheckoutResource;

/**
 * Client for storefront operations using a public Service Key (pk_live_... / pk_test_...).
 *
 * Use this client to create checkout sessions and poll payment status.
 * The service key is safe to use on backend servers; never expose it in browser code.
 */
class ServiceClient
{
    public readonly bool $testMode;
    public readonly CheckoutResource $checkout;

    private readonly HttpClient $http;

    /**
     * @param array{
     *   serviceKey: string,
     *   baseUrl?: string,
     *   timeoutS?: int,
     *   maxRetries?: int,
     *   extraHeaders?: array<string, string>,
     * } $options
     */
    public function __construct(array $options)
    {
        if (!isset($options['serviceKey']) || $options['serviceKey'] === '') {
            throw new \InvalidArgumentException('ServiceClient requires a non-empty serviceKey.');
        }

        $this->testMode = str_starts_with($options['serviceKey'], 'pk_test_');
        $this->http = new HttpClient([
            'serviceKey'   => $options['serviceKey'],
            'baseUrl'      => $options['baseUrl'] ?? null,
            'timeoutS'     => $options['timeoutS'] ?? null,
            'maxRetries'   => $options['maxRetries'] ?? null,
            'extraHeaders' => $options['extraHeaders'] ?? [],
        ]);

        $this->checkout = new CheckoutResource($this->http);
    }

    public function __debugInfo(): array
    {
        return ['testMode' => $this->testMode];
    }
}
