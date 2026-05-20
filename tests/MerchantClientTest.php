<?php

declare(strict_types=1);

namespace TangentoPay\Tests;

use PHPUnit\Framework\TestCase;
use TangentoPay\MerchantClient;

class MerchantClientTest extends TestCase
{
    public function testInstantiatesWithNoOptions(): void
    {
        $client = new MerchantClient();
        $this->assertInstanceOf(MerchantClient::class, $client);
    }

    public function testInstantiatesWithApiToken(): void
    {
        $client = new MerchantClient(['apiToken' => 'test-token']);
        $this->assertInstanceOf(MerchantClient::class, $client);
    }

    public function testThrowsOnHttpBaseUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/HTTPS/i');
        new MerchantClient([
            'apiToken' => 'test',
            'baseUrl'  => 'http://api.tangentopay.com/api/v1',
        ]);
    }

    public function testThrowsOnCrLfInApiToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/illegal characters/i');
        new MerchantClient(['apiToken' => "eyJhbGci\r\nX-Evil: injected"]);
    }

    public function testAllResourcesAreAccessible(): void
    {
        $client = new MerchantClient();
        $this->assertInstanceOf(\TangentoPay\Resources\AuthResource::class, $client->auth);
        $this->assertInstanceOf(\TangentoPay\Resources\PaymentsResource::class, $client->payments);
        $this->assertInstanceOf(\TangentoPay\Resources\RefundsResource::class, $client->refunds);
        $this->assertInstanceOf(\TangentoPay\Resources\TopupsResource::class, $client->topups);
        $this->assertInstanceOf(\TangentoPay\Resources\PayoutsResource::class, $client->payouts);
        $this->assertInstanceOf(\TangentoPay\Resources\TransfersResource::class, $client->transfers);
        $this->assertInstanceOf(\TangentoPay\Resources\WalletsResource::class, $client->wallets);
        $this->assertInstanceOf(\TangentoPay\Resources\ServicesResource::class, $client->services);
        $this->assertInstanceOf(\TangentoPay\Resources\CustomersResource::class, $client->customers);
        $this->assertInstanceOf(\TangentoPay\Resources\AnalyticsResource::class, $client->analytics);
    }

    public function testDebugInfoDoesNotExposeToken(): void
    {
        $client = new MerchantClient(['apiToken' => 'super-secret-api-token']);
        $debug = $client->__debugInfo();
        $encoded = json_encode($debug);
        $this->assertStringNotContainsString('super-secret-api-token', $encoded);
    }
}
