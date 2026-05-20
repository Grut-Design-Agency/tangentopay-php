<?php

declare(strict_types=1);

namespace TangentoPay\Exceptions;

/** Thrown on HTTP 401 — invalid or expired API key / token. */
class AuthenticationException extends TangentoPayException {}
