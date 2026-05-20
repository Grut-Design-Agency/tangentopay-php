<?php

declare(strict_types=1);

namespace TangentoPay\Exceptions;

/** Thrown on HTTP 429 after all retries are exhausted. */
class RateLimitException extends TangentoPayException
{
    public function __construct(
        string $message,
        /** Seconds to wait before retrying, or null if not specified. */
        public readonly ?int $retryAfter = null,
        int $code = 429,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
