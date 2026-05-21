<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\CheckoutSession;
use TangentoPay\Models\TransactionStatus;

class CheckoutResource
{
    private const POLL_INTERVAL_US = 2_000_000; // 2 seconds
    private const DEFAULT_TIMEOUT_S = 60;

    public function __construct(private readonly HttpClient $http) {}

    /**
     * Create a Stripe-hosted checkout session.
     *
     * Two modes are supported — exactly one of `products` or `amount` must be supplied.
     *
     * **Product-based** (e-commerce / WooCommerce):
     * ```php
     * $session = $client->checkout->create([
     *     'products'       => [['name' => 'Pro Plan', 'price' => 49.99, 'quantity' => 1]],
     *     'currency_code'  => 'USD',
     *     'return_url'     => 'https://myshop.com/thank-you',
     * ]);
     * header('Location: ' . $session->redirectUrl);
     * ```
     *
     * **Amount-only** (payfac / money transfer — no product catalogue):
     * ```php
     * $session = $client->checkout->create([
     *     'amount'        => 5000,
     *     'description'   => 'Wallet top-up',
     *     'currency_code' => 'XAF',
     *     'return_url'    => 'https://myapp.com/success',
     * ]);
     * header('Location: ' . $session->redirectUrl);
     * ```
     *
     * @param array{
     *   products?: array<array{name: string, price: float|int, quantity: int}>,
     *   amount?: float|int,
     *   description?: string,
     *   currency_code?: string,
     *   customer_email?: string,
     *   return_url?: string,
     *   cancel_url?: string,
     * } $params
     *
     * @throws \InvalidArgumentException if neither or both of products/amount are supplied
     */
    public function create(array $params): CheckoutSession
    {
        $hasProducts = isset($params['products']) && is_array($params['products']);
        $hasAmount   = isset($params['amount']);

        if (!$hasProducts && !$hasAmount) {
            throw new \InvalidArgumentException(
                'Provide either "products" (array of line items) or "amount" (numeric) — one is required.'
            );
        }
        if ($hasProducts && $hasAmount) {
            throw new \InvalidArgumentException(
                'Provide either "products" or "amount", not both.'
            );
        }

        $data = $this->http->post('/create-checkout-session', $params);
        return CheckoutSession::fromArray((array) $data);
    }

    /**
     * Poll the current status of a checkout transaction.
     */
    public function getStatus(string $transactionUid): TransactionStatus
    {
        $data = $this->http->get("/payments/{$transactionUid}/status");
        return TransactionStatus::fromArray((array) $data);
    }

    /**
     * Poll until the transaction is completed or the timeout is reached.
     *
     * @throws \RuntimeException if the timeout is exceeded before completion
     */
    public function waitForCompletion(
        string $transactionUid,
        int $timeoutS = self::DEFAULT_TIMEOUT_S,
    ): TransactionStatus {
        $deadline = time() + $timeoutS;

        while (true) {
            $status = $this->getStatus($transactionUid);
            if ($status->isCompleted) {
                return $status;
            }
            if (time() >= $deadline) {
                throw new \RuntimeException(
                    "Transaction {$transactionUid} did not complete within {$timeoutS}s.",
                );
            }
            usleep(self::POLL_INTERVAL_US);
        }
    }
}
