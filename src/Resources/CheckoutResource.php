<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\CheckoutSession;
use TangentoPay\Models\MomoCheckoutSession;
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
     * Initiate a Mobile Money (USSD push) checkout session.
     *
     * Sends a USSD prompt to the customer's Cameroon phone (MTN MoMo or Orange Money).
     * Poll getStatus() or call waitForMomoCompletion() to track completion.
     *
     * **Currency is always XAF.** Convert non-XAF amounts first via getExchangeRate().
     *
     * ```php
     * $session = $client->checkout->createMomo([
     *     'amount'      => 5000,        // XAF, net to merchant
     *     'phone'       => '670123456', // 9-digit Cameroon number
     *     'description' => 'Order #42',
     * ]);
     * // Customer receives a USSD prompt; poll until completed:
     * $final = $client->checkout->waitForMomoCompletion($session->transactionUid, 180);
     * ```
     *
     * @param array{
     *   amount: int,
     *   phone: string,
     *   description?: string,
     *   idempotency_key?: string,
     * } $params
     *
     * @throws \InvalidArgumentException if `amount` or `phone` are missing
     */
    public function createMomo(array $params): MomoCheckoutSession
    {
        if (empty($params['amount'])) {
            throw new \InvalidArgumentException('"amount" is required for MoMo checkout.');
        }
        if (empty($params['phone'])) {
            throw new \InvalidArgumentException('"phone" is required for MoMo checkout.');
        }

        $payload = [
            'payment_source' => 'momo',
            'amount'         => (int) $params['amount'],
            'currency_code'  => 'XAF',
            'phone'          => $params['phone'],
            'description'    => $params['description'] ?? 'TangentoPay payment',
        ];
        if (!empty($params['idempotency_key'])) {
            $payload['idempotency_key'] = $params['idempotency_key'];
        }

        $data = $this->http->post('/create-checkout-session', $payload);
        return MomoCheckoutSession::fromArray((array) $data);
    }

    /**
     * Fetch the current exchange rate between two active currencies.
     *
     * Results are cached server-side for 1 hour. Use this to convert a non-XAF
     * order amount to XAF before calling createMomo().
     *
     * ```php
     * $rate = $client->checkout->getExchangeRate('USD', 'XAF');
     * $xafAmount = (int) round($usdAmount * $rate);
     * ```
     *
     * @param string $fromCurrency ISO 4217 source currency (e.g. 'USD')
     * @param string $toCurrency   ISO 4217 target currency (e.g. 'XAF')
     * @return float 1 $fromCurrency = <float> $toCurrency
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $data = $this->http->get(
            '/exchange-rate?from=' . strtoupper($fromCurrency) . '&to=' . strtoupper($toCurrency)
        );
        return (float) (((array) $data)['rate'] ?? 0.0);
    }

    /**
     * Poll until a MoMo payment completes, fails, or the timeout is reached.
     *
     * MoMo payments are confirmed asynchronously (customer approves the USSD
     * prompt on their phone), so this uses a longer default timeout than the
     * Stripe helper.
     *
     * @param string $transactionUid  UID returned by createMomo().
     * @param int    $timeoutS        Max seconds to wait (default 300 = 5 min).
     * @throws \RuntimeException if the timeout is exceeded before completion.
     */
    public function waitForMomoCompletion(
        string $transactionUid,
        int $timeoutS = 300,
    ): TransactionStatus {
        $deadline = time() + $timeoutS;

        while (true) {
            $status = $this->getStatus($transactionUid);
            if (in_array($status->transactionStatus, ['completed', 'failed', 'cancelled'], true)) {
                return $status;
            }
            if (time() >= $deadline) {
                throw new \RuntimeException(
                    "MoMo transaction {$transactionUid} did not complete within {$timeoutS}s.",
                );
            }
            usleep(3_000_000); // 3-second poll interval
        }
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
