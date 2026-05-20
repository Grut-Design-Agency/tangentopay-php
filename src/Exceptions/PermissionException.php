<?php

declare(strict_types=1);

namespace TangentoPay\Exceptions;

/** Thrown on HTTP 403 — authenticated but not authorised. */
class PermissionException extends TangentoPayException {}
