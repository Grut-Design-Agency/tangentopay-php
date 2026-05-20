<?php

declare(strict_types=1);

namespace TangentoPay;

use TangentoPay\Exceptions\WebhookSignatureException;
use TangentoPay\Models\WebhookEvent;

/**
 * Webhook signature verification.
 *
 * Security features:
 * - HMAC-SHA256 verified with hash_equals() to prevent timing attacks
 * - Replay protection: timestamps outside the tolerance window are rejected
 * - Hex validation: digest must be exactly 64 hex characters before comparison
 * - Payload size limit: payloads over 10 MB are rejected before any HMAC work
 */
class Webhook
{
    private const MAX_PAYLOAD_BYTES = 10 * 1024 * 1024; // 10 MB
    private const DEFAULT_TOLERANCE_S = 300;             // 5 minutes

    /**
     * Verify the signature and return a parsed WebhookEvent.
     *
     * @param string $payload        Raw request body (do NOT JSON-decode first)
     * @param string $sigHeader      Value of the X-TangentoPay-Signature header
     * @param string $secret         Your webhook secret (whsec_...)
     * @param int    $toleranceS     Reject events older than this many seconds (default 300)
     *
     * @throws WebhookSignatureException on invalid signature, bad format, or replay
     */
    public static function constructEvent(
        string $payload,
        string $sigHeader,
        string $secret,
        int $toleranceS = self::DEFAULT_TOLERANCE_S,
    ): WebhookEvent {
        // Reject oversized payloads before touching HMAC.
        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            throw new WebhookSignatureException(
                'Webhook payload exceeds the 10 MB size limit.',
            );
        }

        [$timestamp, $signature] = self::parseHeader($sigHeader);

        // Replay protection: reject stale events.
        $now = time();
        if (abs($now - $timestamp) > $toleranceS) {
            throw new WebhookSignatureException(
                "Webhook timestamp is outside the {$toleranceS}s tolerance window. " .
                'Possible replay attack — ensure your server clock is synchronized.',
            );
        }

        // Validate hex digest format before attempting comparison.
        // An attacker could supply a non-hex string that passes hash comparison
        // through type juggling or encoding tricks on non-strict implementations.
        if (!preg_match('/^[0-9a-f]{64}$/i', $signature)) {
            throw new WebhookSignatureException(
                'Webhook signature contains an invalid SHA-256 hex digest ' .
                '(expected exactly 64 hexadecimal characters).',
            );
        }

        $expected = self::computeSignature($payload, $secret, $timestamp);

        // Timing-safe comparison — hash_equals() prevents timing side-channels.
        if (!hash_equals($expected, strtolower($signature))) {
            throw new WebhookSignatureException(
                'Webhook signature verification failed. ' .
                'Ensure the payload is the raw request body and the secret is correct.',
            );
        }

        $data = json_decode($payload, associative: true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new WebhookSignatureException('Webhook payload is not a valid JSON object.');
        }

        return WebhookEvent::fromArray($data);
    }

    /**
     * Generate a valid webhook signature.
     * Used in tests to create signed payloads without a real TangentoPay server.
     */
    public static function generateSignature(
        string $payload,
        string $secret,
        ?int $timestamp = null,
    ): string {
        $ts = $timestamp ?? time();
        $digest = self::computeSignature($payload, $secret, $ts);
        return "t={$ts},sha256={$digest}";
    }

    /**
     * @return array{0: int, 1: string}  [timestamp, hex-digest]
     * @throws WebhookSignatureException
     */
    private static function parseHeader(string $header): array
    {
        if ($header === '') {
            throw new WebhookSignatureException(
                'Missing X-TangentoPay-Signature header.',
            );
        }

        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 't=')) {
                $ts = substr($part, 2);
                if (!ctype_digit($ts)) {
                    throw new WebhookSignatureException(
                        'Webhook signature header contains an invalid timestamp.',
                    );
                }
                $timestamp = (int) $ts;
            } elseif (str_starts_with($part, 'sha256=')) {
                $signature = substr($part, 7);
            }
        }

        if ($timestamp === null) {
            throw new WebhookSignatureException(
                'Webhook signature header is missing the timestamp (t=) field.',
            );
        }
        if ($signature === null || $signature === '') {
            throw new WebhookSignatureException(
                'Webhook signature header is missing the sha256= field.',
            );
        }

        return [$timestamp, $signature];
    }

    private static function computeSignature(string $payload, string $secret, int $timestamp): string
    {
        $signed = "{$timestamp}.{$payload}";
        return hash_hmac('sha256', $signed, $secret);
    }
}
