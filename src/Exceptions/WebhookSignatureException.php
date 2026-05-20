<?php

declare(strict_types=1);

namespace TangentoPay\Exceptions;

/** Thrown when a webhook signature is invalid, tampered, or replayed. */
class WebhookSignatureException extends TangentoPayException {}
