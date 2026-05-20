<?php

declare(strict_types=1);

namespace TangentoPay\Tests;

use PHPUnit\Framework\TestCase;
use TangentoPay\ServiceClient;

class ServiceClientTest extends TestCase
{
    public function testTestModeDetectedFromPkTestPrefix(): void
    {
        $client = new ServiceClient(['serviceKey' => 'pk_test_abc123']);
        $this->assertTrue($client->testMode);
    }

    public function testLiveModeWhenPkLivePrefix(): void
    {
        $client = new ServiceClient(['serviceKey' => 'pk_live_abc123']);
        $this->assertFalse($client->testMode);
    }

    public function testThrowsWithEmptyServiceKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ServiceClient(['serviceKey' => '']);
    }

    public function testThrowsWithMissingServiceKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ServiceClient([]);
    }

    public function testThrowsOnHttpBaseUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/HTTPS/i');
        new ServiceClient([
            'serviceKey' => 'pk_live_abc',
            'baseUrl'    => 'http://api.tangentopay.com/api/v1',
        ]);
    }

    public function testDebugInfoDoesNotExposeKey(): void
    {
        $client = new ServiceClient(['serviceKey' => 'pk_live_supersecret']);
        $debug = $client->__debugInfo();
        $this->assertArrayNotHasKey('serviceKey', $debug);
    }

    public function testCheckoutResourceIsAccessible(): void
    {
        $client = new ServiceClient(['serviceKey' => 'pk_live_abc123']);
        $this->assertInstanceOf(\TangentoPay\Resources\CheckoutResource::class, $client->checkout);
    }
}
