<?php

declare(strict_types=1);

namespace TangentoPay\Tests;

use PHPUnit\Framework\TestCase;
use TangentoPay\Exceptions\WebhookSignatureException;
use TangentoPay\Models\WebhookEvent;
use TangentoPay\Webhook;

class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret_abc123';

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testConstructEventReturnsWebhookEvent(): void
    {
        $payload = json_encode(['event' => 'transaction.payment_completed', 'payload' => ['transaction_uid' => 'TXN-001']]);
        $sig = Webhook::generateSignature($payload, self::SECRET);

        $event = Webhook::constructEvent($payload, $sig, self::SECRET);

        $this->assertInstanceOf(WebhookEvent::class, $event);
        $this->assertSame('transaction.payment_completed', $event->event);
        $this->assertSame('TXN-001', $event->payload['transaction_uid']);
    }

    public function testGenerateSignatureProducesVerifiableSignature(): void
    {
        $payload = json_encode(['event' => 'transaction.refund_completed', 'payload' => []]);
        $timestamp = time();
        $sig = Webhook::generateSignature($payload, self::SECRET, $timestamp);

        $event = Webhook::constructEvent($payload, $sig, self::SECRET, 300);
        $this->assertSame('transaction.refund_completed', $event->event);
    }

    public function testFixedTimestampSignatureVerifies(): void
    {
        $payload = '{"event":"transaction.payout_completed","payload":{}}';
        $timestamp = 1_716_134_400;
        $sig = Webhook::generateSignature($payload, self::SECRET, $timestamp);

        // Pass large tolerance so the fixed timestamp doesn't expire in future test runs.
        $event = Webhook::constructEvent($payload, $sig, self::SECRET, PHP_INT_MAX);
        $this->assertSame('transaction.payout_completed', $event->event);
    }

    // -------------------------------------------------------------------------
    // Signature verification failures
    // -------------------------------------------------------------------------

    public function testThrowsOnTamperedPayload(): void
    {
        $this->expectException(WebhookSignatureException::class);

        $payload = json_encode(['event' => 'transaction.payment_completed', 'payload' => []]);
        $sig = Webhook::generateSignature($payload, self::SECRET);
        $tampered = json_encode(['event' => 'transaction.payment_completed', 'payload' => ['injected' => true]]);

        Webhook::constructEvent($tampered, $sig, self::SECRET);
    }

    public function testThrowsOnWrongSecret(): void
    {
        $this->expectException(WebhookSignatureException::class);

        $payload = json_encode(['event' => 'test', 'payload' => []]);
        $sig = Webhook::generateSignature($payload, 'wrong-secret');

        Webhook::constructEvent($payload, $sig, self::SECRET);
    }

    public function testThrowsOnMissingSignatureHeader(): void
    {
        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessageMatches('/Missing/i');

        $payload = json_encode(['event' => 'test', 'payload' => []]);
        Webhook::constructEvent($payload, '', self::SECRET);
    }

    public function testThrowsOnMissingTimestampField(): void
    {
        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessageMatches('/timestamp/i');

        $payload = json_encode(['event' => 'test', 'payload' => []]);
        Webhook::constructEvent($payload, 'sha256=abcdef1234', self::SECRET);
    }

    public function testThrowsOnMissingDigestField(): void
    {
        $this->expectException(WebhookSignatureException::class);

        $payload = json_encode(['event' => 'test', 'payload' => []]);
        Webhook::constructEvent($payload, 't=1234567890', self::SECRET);
    }

    public function testThrowsOnInvalidHexDigest(): void
    {
        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessageMatches('/invalid SHA-256 hex digest/i');

        $payload = json_encode(['event' => 'test', 'payload' => []]);
        // Digest is only 10 chars, not 64.
        Webhook::constructEvent($payload, 't=1716134400,sha256=notvalidhex', self::SECRET);
    }

    public function testThrowsOnDigestWithNonHexChars(): void
    {
        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessageMatches('/invalid SHA-256 hex digest/i');

        $payload = json_encode(['event' => 'test', 'payload' => []]);
        $badDigest = str_repeat('g', 64); // 'g' is not a valid hex char
        Webhook::constructEvent($payload, "t=1716134400,sha256={$badDigest}", self::SECRET);
    }

    // -------------------------------------------------------------------------
    // Replay protection
    // -------------------------------------------------------------------------

    public function testThrowsOnStaleTimestamp(): void
    {
        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessageMatches('/tolerance window/i');

        $payload = json_encode(['event' => 'test', 'payload' => []]);
        $staleTimestamp = time() - 600; // 10 minutes ago
        $sig = Webhook::generateSignature($payload, self::SECRET, $staleTimestamp);

        Webhook::constructEvent($payload, $sig, self::SECRET, 300);
    }

    public function testThrowsOnFutureTimestampBeyondTolerance(): void
    {
        $this->expectException(WebhookSignatureException::class);

        $payload = json_encode(['event' => 'test', 'payload' => []]);
        $futureTimestamp = time() + 600; // 10 minutes in the future
        $sig = Webhook::generateSignature($payload, self::SECRET, $futureTimestamp);

        Webhook::constructEvent($payload, $sig, self::SECRET, 300);
    }

    public function testAcceptsEventWithinTolerance(): void
    {
        $payload = json_encode(['event' => 'transaction.topup_completed', 'payload' => []]);
        $recentTimestamp = time() - 60; // 1 minute ago — within 5-minute window
        $sig = Webhook::generateSignature($payload, self::SECRET, $recentTimestamp);

        $event = Webhook::constructEvent($payload, $sig, self::SECRET, 300);
        $this->assertSame('transaction.topup_completed', $event->event);
    }

    // -------------------------------------------------------------------------
    // Payload size limit
    // -------------------------------------------------------------------------

    public function testThrowsOnOversizedPayload(): void
    {
        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessageMatches('/10 MB/i');

        // Build a payload slightly over 10 MB.
        $oversized = str_repeat('x', 10 * 1024 * 1024 + 1);
        $sig = Webhook::generateSignature($oversized, self::SECRET);

        Webhook::constructEvent($oversized, $sig, self::SECRET);
    }
}
