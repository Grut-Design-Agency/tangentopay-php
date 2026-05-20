<?php

declare(strict_types=1);

namespace TangentoPay\Exceptions;

/** Thrown on HTTP 422 — field-level validation errors from the server. */
class ValidationException extends TangentoPayException
{
    /** @param array<string, string[]> $errors */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        int $code = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
