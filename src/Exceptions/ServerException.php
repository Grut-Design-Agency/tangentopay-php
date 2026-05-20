<?php

declare(strict_types=1);

namespace TangentoPay\Exceptions;

/** Thrown on HTTP 5xx after all retries are exhausted. */
class ServerException extends TangentoPayException {}
