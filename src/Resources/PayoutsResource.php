<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\PaginatedResult;
use TangentoPay\Models\Transaction;

class PayoutsResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Initiate a payout (withdrawal) from the main wallet.
     *
     * TangentoPay charges a **4% fee** deducted from proceeds.
     * The `net_amount` on the returned transaction shows what the recipient receives.
     *
     * @param array{
     *   amount: float|int,
     *   currency_code: string,
     *   pin: string,
     *   recipient_type: 'bank'|'mtn_momo'|'orange_money',
     *   recipient_details: array{
     *     bank_account_id?: string,
     *     bank_name?: string,
     *     account_number?: string,
     *     first_name?: string,
     *     last_name?: string,
     *     mobile_money_number?: string,
     *   },
     *   description?: string,
     * } $params
     *
     * `recipient_type`:
     * - `"bank"` — Stripe bank transfer. Requires `recipient_details.bank_account_id`. 2–7 business days.
     * - `"mtn_momo"` — MTN MoMo via Fapshi (XAF only). Requires `recipient_details.mobile_money_number`. Near-instant.
     * - `"orange_money"` — Orange Money via Fapshi (XAF only). Same as mtn_momo.
     *
     * @throws \InvalidArgumentException if required fields are missing
     */
    public function create(array $params): Transaction
    {
        $validTypes = ['bank', 'mtn_momo', 'orange_money'];
        if (empty($params['recipient_type']) || !in_array($params['recipient_type'], $validTypes, true)) {
            throw new \InvalidArgumentException(
                "recipient_type is required and must be one of: " . implode(', ', $validTypes)
            );
        }
        if (empty($params['pin'])) {
            throw new \InvalidArgumentException('pin is required for payouts.');
        }
        if ($params['recipient_type'] === 'bank' && empty($params['recipient_details']['bank_account_id'])) {
            throw new \InvalidArgumentException('recipient_details.bank_account_id is required for bank payouts.');
        }
        if (in_array($params['recipient_type'], ['mtn_momo', 'orange_money'], true)
            && empty($params['recipient_details']['mobile_money_number'])) {
            throw new \InvalidArgumentException(
                'recipient_details.mobile_money_number is required for MoMo payouts. Format: 6XXXXXXXX'
            );
        }

        $data = $this->http->post('/payouts', $params);
        return Transaction::fromArray((array) $data);
    }

    /**
     * Initiate a bulk payout (payroll / commission disbursement) from a pre-uploaded CSV.
     *
     * TangentoPay charges a **4% fee on the total CSV sum**, added on top and debited upfront.
     * Each recipient receives their full stated amount.
     *
     * @param array{
     *   user_account_id: int,
     *   csv_path: string,
     *   currency_code: string,
     *   batch_name?: string,
     * } $params
     */
    public function bulk(array $params): array
    {
        $data = $this->http->post('/payouts/bulk', $params);
        return (array) ($data['data'] ?? $data);
    }

    /**
     * @param array{perPage?: int, page?: int} $params
     * @return PaginatedResult<Transaction>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->http->get('/payouts', $params);
        return PaginatedResult::fromResponse(
            (array) $data,
            static fn(array $item): Transaction => Transaction::fromArray($item),
        );
    }
}
