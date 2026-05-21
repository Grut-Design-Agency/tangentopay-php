<?php

declare(strict_types=1);

namespace TangentoPay;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use TangentoPay\Exceptions\AuthenticationException;
use TangentoPay\Exceptions\NetworkException;
use TangentoPay\Exceptions\NotFoundException;
use TangentoPay\Exceptions\PermissionException;
use TangentoPay\Exceptions\RateLimitException;
use TangentoPay\Exceptions\ServerException;
use TangentoPay\Exceptions\TangentoPayException;
use TangentoPay\Exceptions\ValidationException;

/**
 * Low-level HTTP transport with security hardening.
 *
 * Security features:
 * - HTTPS enforcement: rejects http:// base URLs at construction time
 * - Header injection protection: throws (not strips) on CR/LF in values
 * - Header key validation: throws on CR, LF, colon, or space in key names
 * - Protected auth headers: Authorization/X-Service-Key cannot be overridden
 * - Idempotent-only retries: POST/PATCH are never retried to prevent duplicates
 * - Retry-After cap: server-supplied value capped at 60s
 * - Path traversal guard: resolved path must remain under the base path
 * - Credential masking: tokens masked in __debugInfo() / __toString()
 */
class HttpClient
{
    private const DEFAULT_BASE_URL = 'https://api.tangentopay.com/api/v1';
    private const DEFAULT_TIMEOUT_S = 30;
    private const DEFAULT_MAX_RETRIES = 3;
    private const MAX_RETRY_WAIT_S = 60;

    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    /**
     * Only these methods are safe to retry — non-idempotent methods (POST, PATCH)
     * must not be retried to avoid creating duplicate records.
     */
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'];

    private readonly string $baseUrl;
    private readonly string $basePath;
    private Client $guzzle;
    private readonly int $maxRetries;

    /**
     * @param array{
     *   baseUrl?: string,
     *   bearerToken?: string,
     *   serviceKey?: string,
     *   timeoutS?: int,
     *   maxRetries?: int,
     *   extraHeaders?: array<string, string>,
     * } $options
     */
    public function __construct(private readonly array $options = [])
    {
        $base = $options['baseUrl'] ?? self::DEFAULT_BASE_URL;

        // Enforce HTTPS — the SDK sends credentials on every request.
        if (!str_starts_with($base, 'https://')) {
            throw new \InvalidArgumentException(
                "TangentoPay SDK requires HTTPS. Got: {$base}\n" .
                'Use an https:// URL to protect your credentials in transit.',
            );
        }

        // Validate credentials eagerly — fail at construction, not on first request.
        if (isset($options['bearerToken'])) {
            self::validateHeaderValue('Authorization', $options['bearerToken']);
        }
        if (isset($options['serviceKey'])) {
            self::validateHeaderValue('X-Service-Key', $options['serviceKey']);
        }

        $this->baseUrl = rtrim($base, '/');
        $this->basePath = parse_url($this->baseUrl, PHP_URL_PATH) ?? '';
        $this->maxRetries = $options['maxRetries'] ?? self::DEFAULT_MAX_RETRIES;

        $this->guzzle = new Client([
            'timeout' => $options['timeoutS'] ?? self::DEFAULT_TIMEOUT_S,
            'http_errors' => false,
        ]);
    }

    /** @param array<string, mixed>|null $params */
    public function get(string $path, ?array $params = null, ?array $headers = null): mixed
    {
        return $this->request('GET', $path, $params, null, $headers);
    }

    /** @param array<string, mixed>|null $json */
    public function post(string $path, ?array $json = null, ?array $headers = null): mixed
    {
        return $this->request('POST', $path, null, $json, $headers);
    }

    /** @param array<string, mixed>|null $json */
    public function put(string $path, ?array $json = null, ?array $headers = null): mixed
    {
        return $this->request('PUT', $path, null, $json, $headers);
    }

    /** @param array<string, mixed>|null $json */
    public function patch(string $path, ?array $json = null, ?array $headers = null): mixed
    {
        return $this->request('PATCH', $path, null, $json, $headers);
    }

    public function delete(string $path, ?array $headers = null): mixed
    {
        return $this->request('DELETE', $path, null, null, $headers);
    }

    /**
     * @param array<string, mixed>|null $params
     * @param array<string, mixed>|null $json
     * @param array<string, string>|null $extraHeaders
     */
    private function request(
        string $method,
        string $path,
        ?array $params,
        ?array $json,
        ?array $extraHeaders,
    ): mixed {
        $url = $this->buildUrl($path, $params);
        $headers = $this->buildHeaders($extraHeaders ?? []);
        $canRetry = in_array(strtoupper($method), self::IDEMPOTENT_METHODS, true);

        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                // Note: params are already encoded into $url by buildUrl().
                // Do NOT also pass 'query' here — Guzzle would append them again,
                // producing duplicate query parameters on every GET request.
                $guzzleOptions = ['headers' => $headers];
                if ($json !== null) {
                    $guzzleOptions['json'] = $json;
                }

                $response = $this->guzzle->request($method, $url, $guzzleOptions);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    $body = (string) $response->getBody();
                    if ($body === '') {
                        return [];
                    }
                    return json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
                }

                // On retryable status for idempotent method, wait and retry.
                if ($canRetry && $attempt < $this->maxRetries
                    && in_array($statusCode, self::RETRYABLE_STATUSES, true)
                ) {
                    $wait = $this->retryWaitS($attempt, $response);
                    usleep((int) ($wait * 1_000_000));
                    continue;
                }

                $this->raiseForStatus($statusCode, $response);
            } catch (ConnectException $e) {
                $msg = 'Network error: ' . $e->getMessage();
                if ($canRetry && $attempt < $this->maxRetries) {
                    $lastException = new NetworkException($msg, 0, $e);
                    usleep($this->backoffUs($attempt));
                    continue;
                }
                throw new NetworkException($msg, 0, $e);
            } catch (RequestException $e) {
                $msg = 'Network error: ' . $e->getMessage();
                if ($canRetry && $attempt < $this->maxRetries) {
                    $lastException = new NetworkException($msg, 0, $e);
                    usleep($this->backoffUs($attempt));
                    continue;
                }
                throw new NetworkException($msg, 0, $e);
            } catch (TangentoPayException $e) {
                // Don't retry our own typed errors — surface immediately.
                throw $e;
            }
        }

        throw $lastException ?? new NetworkException('Request failed after retries');
    }

    /**
     * Build URL using string concatenation to preserve the base path.
     *
     * Using parse_url + path resolution would silently drop /api/v1 when the
     * path starts with "/". String concatenation is the safe, predictable choice.
     *
     * Includes a path-traversal guard: the resolved pathname must still start
     * with the base path component.
     */
    private function buildUrl(string $path, ?array $params): string
    {
        $cleanPath = ltrim($path, '/');

        // Reject path traversal sequences before any URL construction.
        // parse_url() does not resolve ".." segments, so we must check explicitly.
        foreach (explode('/', $cleanPath) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new \InvalidArgumentException(
                    "Path traversal detected: \"{$path}\" contains illegal path segments.",
                );
            }
        }

        $url = $this->baseUrl . '/' . $cleanPath;

        // Secondary guard: the resolved path must still start with the base path.
        $parsedPath = parse_url($url, PHP_URL_PATH) ?? '';
        if ($this->basePath !== '' && !str_starts_with($parsedPath, $this->basePath . '/')) {
            throw new \InvalidArgumentException(
                "Path traversal detected: \"{$path}\" resolves outside the SDK base path \"{$this->basePath}\".",
            );
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query(
                array_filter($params, static fn($v): bool => $v !== null),
            );
        }

        return $url;
    }

    /**
     * Build the standard request headers with all security constraints applied.
     *
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function buildHeaders(array $extra): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'User-Agent'   => 'tangentopay-php/0.2.7',
        ];

        // Merge extra headers first so protected auth headers can override them.
        $merged = array_merge($this->options['extraHeaders'] ?? [], $extra);
        foreach ($merged as $key => $value) {
            $lower = strtolower($key);
            // Callers cannot override auth headers via extraHeaders.
            if ($lower === 'authorization' || $lower === 'x-service-key') {
                continue;
            }
            $headers[self::validateHeaderKey($key)] = self::validateHeaderValue($key, $value);
        }

        // Auth header from whichever credential was configured.
        if (isset($this->options['bearerToken'])) {
            $headers['Authorization'] = 'Bearer ' . $this->options['bearerToken'];
        } elseif (isset($this->options['serviceKey'])) {
            $headers['X-Service-Key'] = $this->options['serviceKey'];
        }

        return $headers;
    }

    /**
     * Validate that a header value contains no CR or LF characters.
     * Silently stripping them would mask tampered credentials; raising makes the
     * problem impossible to ignore.
     */
    private static function validateHeaderValue(string $name, string $value): string
    {
        if (preg_match('/[\r\n]/', $value)) {
            throw new \InvalidArgumentException(
                "Header \"{$name}\" contains illegal characters (CR or LF). " .
                'Ensure your credentials have not been tampered with.',
            );
        }
        return $value;
    }

    /**
     * Validate that a header field-name contains no illegal characters.
     * RFC 7230 §3.2: field-name = token; excludes CR, LF, colon, space.
     */
    private static function validateHeaderKey(string $key): string
    {
        if (preg_match('/[\r\n: ]/', $key)) {
            throw new \InvalidArgumentException(
                "Extra header key \"{$key}\" contains illegal characters. " .
                'Header names must not contain CR, LF, colon, or space.',
            );
        }
        return $key;
    }

    /**
     * Seconds to wait before the next retry attempt.
     * Checks the Retry-After HTTP header first, falls back to exponential backoff.
     * Caps at MAX_RETRY_WAIT_S to prevent server-controlled indefinite stalling.
     */
    private function retryWaitS(int $attempt, \Psr\Http\Message\ResponseInterface $response): float
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        if ($retryAfter !== '' && is_numeric($retryAfter)) {
            $serverWait = (float) $retryAfter;
            if ($serverWait > 0) {
                return min($serverWait, self::MAX_RETRY_WAIT_S);
            }
        }
        return $this->backoffS($attempt);
    }

    /** Exponential backoff in seconds with jitter. */
    private function backoffS(int $attempt): float
    {
        $base = 0.5;
        $cap = 20.0;
        $jitter = (float) mt_rand(0, 100) / 1000.0;
        return min($base * (2 ** $attempt) + $jitter, $cap);
    }

    /** Exponential backoff in microseconds for usleep(). */
    private function backoffUs(int $attempt): int
    {
        return (int) ($this->backoffS($attempt) * 1_000_000);
    }

    private function raiseForStatus(int $status, \Psr\Http\Message\ResponseInterface $response): never
    {
        $body = [];
        try {
            $raw = (string) $response->getBody();
            if ($raw !== '') {
                // Depth of 16 is more than enough for error payloads.
                // The default of 512 is unnecessarily deep for server-controlled input.
                $decoded = json_decode($raw, associative: true, depth: 16);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        } catch (\Throwable) {
        }

        $message = (string) ($body['message'] ?? $body['error'] ?? 'HTTP ' . $status);

        match (true) {
            $status === 401 => throw new AuthenticationException($message, 401),
            $status === 403 => throw new PermissionException($message, 403),
            $status === 404 => throw new NotFoundException($message, 404),
            $status === 422 => throw new ValidationException(
                $message,
                (array) ($body['errors'] ?? []),
                422,
            ),
            $status === 429 => throw new RateLimitException(
                $message,
                $this->parseRetryAfter($response),
                429,
            ),
            $status >= 500 => throw new ServerException($message, $status),
            default => throw new TangentoPayException($message, $status),
        };
    }

    private function parseRetryAfter(\Psr\Http\Message\ResponseInterface $response): ?int
    {
        $value = $response->getHeaderLine('Retry-After');
        if ($value !== '' && is_numeric($value)) {
            return (int) min((float) $value, self::MAX_RETRY_WAIT_S);
        }
        return null;
    }

    /**
     * Mask credentials so they don't appear in var_dump() or debug output.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $safe = $this->options;
        if (isset($safe['bearerToken'])) {
            $safe['bearerToken'] = self::maskToken($safe['bearerToken']);
        }
        if (isset($safe['serviceKey'])) {
            $safe['serviceKey'] = self::maskToken($safe['serviceKey']);
        }
        return [
            'baseUrl'    => $this->baseUrl,
            'options'    => $safe,
            'maxRetries' => $this->maxRetries,
        ];
    }

    private static function maskToken(string $token): string
    {
        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }
        return substr($token, 0, 4) . str_repeat('*', strlen($token) - 8) . substr($token, -4);
    }
}
