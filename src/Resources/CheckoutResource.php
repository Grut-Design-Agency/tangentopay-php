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
     * @param array{
     *   products: array<array{name: string, price: float|int, quantity: int}>,
     *   currencyCode: string,
     *   returnUrl: string,
     *   cancelUrl?: string,
     *   customerEmail?: string,
     * } $params
     */
    public function create(array $params): CheckoutSession
    {
        $data = $this->http->post('/checkout', $params);
        return CheckoutSession::fromArray((array) $data);
    }

    public function getStatus(string $transactionUid): TransactionStatus
    {
        $data = $this->http->get("/checkout/{$transactionUid}/status");
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
