<?php

declare(strict_types=1);

namespace TangentoPay\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TangentoPay\Exceptions\AuthenticationException;
use TangentoPay\Exceptions\NotFoundException;
use TangentoPay\Exceptions\PermissionException;
use TangentoPay\Exceptions\RateLimitException;
use TangentoPay\Exceptions\ServerException;
use TangentoPay\Exceptions\ValidationException;
use TangentoPay\HttpClient;

class HttpClientTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction-time security checks
    // -------------------------------------------------------------------------

    public function testRejectsHttpBaseUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/HTTPS/i');
        new HttpClient(['baseUrl' => 'http://api.tangentopay.com/api/v1']);
    }

    public function testAcceptsHttpsBaseUrl(): void
    {
        $client = new HttpClient(['baseUrl' => 'https://api.tangentopay.com/api/v1']);
        $this->assertInstanceOf(HttpClient::class, $client);
    }

    public function testThrowsOnCrLfInBearerToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/illegal characters/i');
        new HttpClient([
            'baseUrl'     => 'https://api.tangentopay.com/api/v1',
            'bearerToken' => "token\r\nX-Evil: injected",
        ]);
    }

    public function testThrowsOnLfOnlyInBearerToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpClient([
            'baseUrl'     => 'https://api.tangentopay.com/api/v1',
            'bearerToken' => "token\nX-Evil: injected",
        ]);
    }

    public function testThrowsOnCrLfInServiceKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/illegal characters/i');
        new HttpClient([
            'baseUrl'    => 'https://api.tangentopay.com/api/v1',
            'serviceKey' => "pk_live_\r\nX-Inject: bad",
        ]);
    }

    public function testThrowsOnCrLfInExtraHeaderKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/illegal characters/i');

        $client = $this->makeClient(new Response(200, [], '{}'));
        $client->get('/test', null, ["X-Custom\r\nX-Evil" => 'val']);
    }

    public function testThrowsOnColonInExtraHeaderKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/illegal characters/i');

        $client = $this->makeClient(new Response(200, [], '{}'));
        $client->get('/test', null, ['X-Bad:Key' => 'val']);
    }

    public function testThrowsOnSpaceInExtraHeaderKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/illegal characters/i');

        $client = $this->makeClient(new Response(200, [], '{}'));
        $client->get('/test', null, ['X Bad Key' => 'val']);
    }

    public function testThrowsOnPathTraversalWithDotDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/traversal/i');

        $client = $this->makeClient(new Response(200, [], '{}'));
        $client->get('/payments/../../etc/passwd');
    }

    public function testThrowsOnPathTraversalSingleDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/traversal/i');

        $client = $this->makeClient(new Response(200, [], '{}'));
        $client->get('/payments/./secret');
    }

    // -------------------------------------------------------------------------
    // Protected auth headers cannot be overridden
    // -------------------------------------------------------------------------

    public function testCannotOverrideAuthorizationViaExtraHeaders(): void
    {
        $history = [];
        $client = $this->makeClientWithHistory($history, new Response(200, [], '{}'), [
            'bearerToken' => 'real-token',
        ]);
        $client->get('/test', null, ['Authorization' => 'Bearer evil-token']);

        $sent = $history[0]['request']->getHeaderLine('Authorization');
        $this->assertStringContainsString('real-token', $sent);
        $this->assertStringNotContainsString('evil-token', $sent);
    }

    public function testCannotOverrideServiceKeyViaExtraHeaders(): void
    {
        $history = [];
        $client = $this->makeClientWithHistory($history, new Response(200, [], '{}'), [
            'serviceKey' => 'pk_live_realkey',
        ]);
        $client->get('/test', null, ['X-Service-Key' => 'pk_live_evil']);

        $sent = $history[0]['request']->getHeaderLine('X-Service-Key');
        $this->assertStringContainsString('pk_live_realkey', $sent);
        $this->assertStringNotContainsString('pk_live_evil', $sent);
    }

    // -------------------------------------------------------------------------
    // HTTP responses
    // -------------------------------------------------------------------------

    public function testGetReturnsDecodedJson(): void
    {
        $client = $this->makeClient(new Response(200, [], '{"id":1,"name":"test"}'));
        $result = $client->get('/test');
        $this->assertEquals(['id' => 1, 'name' => 'test'], $result);
    }

    public function testEmptyBodyReturnsEmptyArray(): void
    {
        $client = $this->makeClient(new Response(204, [], ''));
        $result = $client->get('/test');
        $this->assertEquals([], $result);
    }

    public function testBearerTokenSentInAuthorizationHeader(): void
    {
        $history = [];
        $client = $this->makeClientWithHistory($history, new Response(200, [], '{}'), [
            'bearerToken' => 'mytoken123',
        ]);
        $client->get('/test');
        $this->assertSame('Bearer mytoken123', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function testServiceKeySentInXServiceKeyHeader(): void
    {
        $history = [];
        $client = $this->makeClientWithHistory($history, new Response(200, [], '{}'), [
            'serviceKey' => 'pk_live_abc',
        ]);
        $client->get('/test');
        $this->assertSame('pk_live_abc', $history[0]['request']->getHeaderLine('X-Service-Key'));
    }

    // -------------------------------------------------------------------------
    // Error mapping
    // -------------------------------------------------------------------------

    public function testRaises401AsAuthenticationException(): void
    {
        $this->expectException(AuthenticationException::class);
        $client = $this->makeClient(new Response(401, [], '{"message":"Unauthorized"}'));
        $client->get('/test');
    }

    public function testRaises403AsPermissionException(): void
    {
        $this->expectException(PermissionException::class);
        $client = $this->makeClient(new Response(403, [], '{"message":"Forbidden"}'));
        $client->get('/test');
    }

    public function testRaises404AsNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $client = $this->makeClient(new Response(404, [], '{"message":"Not found"}'));
        $client->get('/test');
    }

    public function testRaises422AsValidationExceptionWithErrors(): void
    {
        $body = json_encode([
            'message' => 'Validation failed',
            'errors'  => ['amount' => ['Too large']],
        ]);
        $client = $this->makeClient(new Response(422, [], $body));

        try {
            $client->post('/test');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['amount' => ['Too large']], $e->errors);
            $this->assertSame(422, $e->getCode());
        }
    }

    public function testRaises429AsRateLimitExceptionWithRetryAfter(): void
    {
        $client = $this->makeClient(
            new Response(429, ['Retry-After' => '30'], '{"message":"Rate limited"}'),
            ['maxRetries' => 0],
        );
        try {
            $client->get('/test');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(30, $e->retryAfter);
        }
    }

    public function testRetryAfterIsCappedAt60Seconds(): void
    {
        $client = $this->makeClient(
            new Response(429, ['Retry-After' => '120'], '{"message":"Rate limited"}'),
            ['maxRetries' => 0],
        );
        try {
            $client->get('/test');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(60, $e->retryAfter);
        }
    }

    public function testRaises500AsServerException(): void
    {
        // POST is not retried, so a single 500 raises immediately.
        $this->expectException(ServerException::class);
        $client = $this->makeClient(new Response(500, [], '{"message":"Server error"}'));
        $client->post('/test');
    }

    // -------------------------------------------------------------------------
    // Retry behaviour — idempotent methods only
    // -------------------------------------------------------------------------

    public function testGetRetries5xxAndSucceedsOnSecondAttempt(): void
    {
        $mock = new MockHandler([
            new Response(503, [], '{"message":"unavailable"}'),
            new Response(200, [], '{"ok":true}'),
        ]);
        $client = $this->makeClientFromMock($mock);
        $result = $client->get('/test');
        $this->assertEquals(['ok' => true], $result);
    }

    public function testPutRetries5xxAndSucceeds(): void
    {
        $mock = new MockHandler([
            new Response(500, [], '{}'),
            new Response(200, [], '{"updated":true}'),
        ]);
        $client = $this->makeClientFromMock($mock);
        $result = $client->put('/test', ['key' => 'value']);
        $this->assertEquals(['updated' => true], $result);
    }

    public function testPostDoesNotRetry5xx(): void
    {
        // POST sees one 503, should throw immediately — never reaching the 200.
        $mock = new MockHandler([
            new Response(503, [], '{"message":"unavailable"}'),
            new Response(200, [], '{"ok":true}'),
        ]);
        $this->expectException(ServerException::class);
        $client = $this->makeClientFromMock($mock);
        $client->post('/test');
    }

    public function testPatchDoesNotRetry5xx(): void
    {
        $mock = new MockHandler([
            new Response(502, [], '{"message":"gateway"}'),
            new Response(200, [], '{"ok":true}'),
        ]);
        $this->expectException(ServerException::class);
        $client = $this->makeClientFromMock($mock);
        $client->patch('/test', ['x' => 1]);
    }

    public function testGetExhaustsRetriesAndThrowsServerException(): void
    {
        $mock = new MockHandler(array_fill(0, 4, new Response(503, [], '{}')));
        $this->expectException(ServerException::class);
        $client = $this->makeClientFromMock($mock, ['maxRetries' => 3]);
        $client->get('/test');
    }

    // -------------------------------------------------------------------------
    // Credential masking
    // -------------------------------------------------------------------------

    public function testBearerTokenMaskedInDebugInfo(): void
    {
        $client = new HttpClient([
            'baseUrl'     => 'https://api.tangentopay.com/api/v1',
            'bearerToken' => 'super-secret-token-12345',
        ]);
        $debug = $client->__debugInfo();
        $masked = $debug['options']['bearerToken'];
        $this->assertStringNotContainsString('super-secret-token-12345', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function testServiceKeyMaskedInDebugInfo(): void
    {
        $client = new HttpClient([
            'baseUrl'    => 'https://api.tangentopay.com/api/v1',
            'serviceKey' => 'pk_live_abc123xyz789',
        ]);
        $debug = $client->__debugInfo();
        $masked = $debug['options']['serviceKey'];
        $this->assertStringNotContainsString('pk_live_abc123xyz789', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeClient(Response $response, array $options = []): HttpClient
    {
        return $this->makeClientFromMock(new MockHandler([$response]), $options);
    }

    private function makeClientFromMock(MockHandler $mock, array $options = []): HttpClient
    {
        $stack = HandlerStack::create($mock);
        $guzzle = new Client(['handler' => $stack, 'http_errors' => false]);

        $client = new HttpClient(array_merge([
            'baseUrl'    => 'https://api.tangentopay.com/api/v1',
            'maxRetries' => 3,
        ], $options));

        $this->injectGuzzle($client, $guzzle);
        return $client;
    }

    /**
     * @param array<int, mixed> $history  Populated with Guzzle history entries after the request
     */
    private function makeClientWithHistory(array &$history, Response $response, array $options = []): HttpClient
    {
        $mock = new MockHandler([$response]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $guzzle = new Client(['handler' => $stack, 'http_errors' => false]);

        $client = new HttpClient(array_merge([
            'baseUrl'    => 'https://api.tangentopay.com/api/v1',
            'maxRetries' => 0,
        ], $options));

        $this->injectGuzzle($client, $guzzle);
        return $client;
    }

    private function injectGuzzle(HttpClient $client, Client $guzzle): void
    {
        $prop = new \ReflectionProperty(HttpClient::class, 'guzzle');
        $prop->setAccessible(true);
        $prop->setValue($client, $guzzle);
    }
}
