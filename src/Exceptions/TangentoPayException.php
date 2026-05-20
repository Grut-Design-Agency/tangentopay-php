<?php

declare(strict_types=1);

namespace TangentoPay\Exceptions;

use RuntimeException;

/**
 * Base exception for all TangentoPay SDK errors.
 */
class TangentoPayException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
