# tangentopay-php

Official PHP SDK for the [TangentoPay](https://tangentopay.com) API — accept payments, issue refunds, manage wallets, and verify webhooks with a clean, fully-typed interface.

[![Packagist Version](https://img.shields.io/packagist/v/tangentopay/tangentopay-php)](https://packagist.org/packages/tangentopay/tangentopay-php)
[![CI](https://github.com/Grut-Design-Agency/tangentopay-php/actions/workflows/ci.yml/badge.svg)](https://github.com/Grut-Design-Agency/tangentopay-php/actions)
[![PHP 8.1+](https://img.shields.io/badge/php-8.1+-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Authentication](#authentication)
- [Test mode](#test-mode)
- [Resources](#resources)
- [Service setup](#service-setup)
- [Wallet top-up](#wallet-top-up)
- [Payment methods](#payment-methods)
- [Error handling](#error-handling)
- [Webhook verification](#webhook-verification)
- [Supported currencies](#supported-currencies)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

---

## Requirements

- PHP 8.1 or higher
- [Composer](https://getcomposer.org/)
- `ext-json` and `ext-openssl` (standard in most PHP distributions)

---

## Installation

```bash
composer require tangentopay/tangentopay-php
```

---

## Quick start

### 1. Accept a customer payment (storefront)

Use `ServiceClient` with your **public service key** (`pk_live_...`).
Get it from: **TangentoPay Dashboard → Services → your service → API Keys**.

```php
use TangentoPay\ServiceClient;

$client = new ServiceClient([
    'serviceKey' => $_ENV['TANGENTOPAY_SERVICE_KEY'],
]);

// Product-based checkout (e-commerce / WooCommerce)
$session = $client->checkout->create([
    'products'      => [['name' => 'Pro Plan', 'price' => 49.99, 'quantity' => 1]],
    'currency_code' => 'USD',
    'customer_email'=> 'buyer@example.com',
    'return_url'    => 'https://myshop.com/thank-you',
    'cancel_url'    => 'https://myshop.com/cart',
]);

// Redirect the customer to the hosted checkout page
header('Location: ' . $session->redirectUrl);
```

#### Amount-only checkout (payfac / money transfer)

Use when you have **no product catalogue** — payfac integrations, wallet top-ups via service key, or any scenario where you just need to collect a fixed amount.

```php
$session = $client->checkout->create([
    'amount'        => 5000,              // total amount — no products array
    'description'   => 'Account top-up', // shown on Stripe checkout page
    'currency_code' => 'XAF',
    'return_url'    => 'https://myapp.com/success',
    'cancel_url'    => 'https://myapp.com/cancel',
]);

header('Location: ' . $session->redirectUrl);
```

### 2. Confirm payment before fulfilling an order

```php
// On your /thank-you page, poll until the payment is confirmed
$status = $client->checkout->waitForCompletion($transactionUid, timeoutS: 60);

if ($status->isCompleted) {
    fulfillOrder($status->transactionUid);
}
```

### 3. Manage payments on the backend (merchant)

Use `MerchantClient` with your **API token** — server-side only, never expose it in browser code.

```php
use TangentoPay\MerchantClient;

$merchant = new MerchantClient([
    'apiToken' => $_ENV['TANGENTOPAY_API_TOKEN'],
]);

// List recent payments
$page = $merchant->payments->list(['perPage' => 20]);
foreach ($page->data as $txn) {
    echo $txn->transactionUid . ' ' . $txn->transactionStatus . ' ' . $txn->finalAmount . PHP_EOL;
}

// Issue a refund
$refund = $merchant->refunds->create([
    'transactionUid' => 'TXN-ABC123',
    'amount'         => 49.99,
    'reason'         => 'Customer request',
    'pin'            => '1234',
    'recipientType'  => 'stripe',
]);

// Check wallet balance
$balances = $merchant->wallets->mainBalance();
echo $balances[0]->availableBalance . ' ' . $balances[0]->currency . PHP_EOL;
```

### 4. Verify incoming webhooks

Always verify the HMAC signature before trusting any webhook payload.

```php
use TangentoPay\Webhook;
use TangentoPay\Exceptions\WebhookSignatureException;

$payload   = file_get_contents('php://input'); // raw body — do NOT parse first
$sigHeader = $_SERVER['HTTP_X_TANGENTOPAY_SIGNATURE'] ?? '';

try {
    $event = Webhook::constructEvent(
        $payload,
        $sigHeader,
        $_ENV['TANGENTOPAY_WEBHOOK_SECRET'],
    );
} catch (WebhookSignatureException $e) {
    http_response_code(400);
    exit('Invalid webhook signature');
}

if ($event->event === 'transaction.payment_completed') {
    fulfillOrder($event->payload['transaction_uid']);
}

http_response_code(200);
```

---

## Authentication

TangentoPay uses two separate credentials depending on what you are doing:

| Client | Credential | Header sent | When to use |
|---|---|---|---|
| `ServiceClient` | Service key (`pk_live_...`) | `X-Service-Key` | Creating checkout sessions, checking payment status — storefront / backend server |
| `MerchantClient` | API token (Bearer) | `Authorization: Bearer` | Everything sensitive — payments, refunds, payouts, wallets, analytics — backend only |

### Getting your credentials

1. Log in to the [TangentoPay Dashboard](https://tangentopay.com)
2. Go to **Services** and open your service
3. Click **API Keys**
4. Copy the **Service Key** and **API Token**
5. Store them as environment variables — never commit them to source control

```bash
# .env (never commit this file)
TANGENTOPAY_SERVICE_KEY=pk_live_xxxxxxxxxxxxxxxxxxxxxxxx
TANGENTOPAY_API_TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6...
TANGENTOPAY_WEBHOOK_SECRET=whs_live_xxxxxxxxxxxxxxxxxxxxxxxx  # from API Keys, not Webhook settings
```

### Obtaining a token programmatically

```php
use TangentoPay\TangentoPay;

// Completes the two-step OTP flow and returns a ready-to-use MerchantClient
$merchant = TangentoPay::login(
    email:    'me@example.com',
    password: 'mypassword',
    otp:      '123456',  // OTP from your registered device
);

$payments = $merchant->payments->list();
```

Or step-by-step:

```php
use TangentoPay\MerchantClient;

$client = new MerchantClient();
$client->auth->login('me@example.com', 'mypassword');
$token = $client->auth->verifyOtp('me@example.com', '123456');

$authed = new MerchantClient(['apiToken' => $token->accessToken]);
```

---

## Test mode

Use a `pk_test_...` service key to run the full checkout flow through Stripe's test environment — no real charges are made.

```php
use TangentoPay\ServiceClient;

$client = new ServiceClient([
    'serviceKey' => $_ENV['TANGENTOPAY_TEST_SERVICE_KEY'],
]);

var_dump($client->testMode); // bool(true)

$session = $client->checkout->create([
    'products'     => [['name' => 'Pro Plan', 'price' => 49.99, 'quantity' => 1]],
    'currencyCode' => 'USD',
    'returnUrl'    => 'https://myshop.com/thank-you',
]);

// $session->redirectUrl points to Stripe test checkout
```

**Stripe test cards:**

| Card number | Behaviour |
|---|---|
| `4242 4242 4242 4242` | Succeeds immediately |
| `4000 0000 0000 0002` | Always declined |
| `4000 0025 0000 3155` | Requires 3D Secure |
| `4000 0000 0000 9995` | Insufficient funds |

Use any future expiry date, any 3-digit CVC, and any postal code.

> Get your test service key from: **Dashboard → Services → your service → API Keys → Create key (type: test)**

---

## Resources

### `ServiceClient` resources

| Resource | Methods | Description |
|---|---|---|
| `checkout` | `create()`, `getStatus()`, `waitForCompletion()` | Hosted Stripe checkout sessions |

### `MerchantClient` resources

| Resource | Methods | Description |
|---|---|---|
| `auth` | `login()`, `verifyOtp()`, `me()`, `logout()`, `changePassword()` | Authentication and profile |
| `payments` | `list()`, `get()`, `createManual()` | View and record payments |
| `refunds` | `create()`, `list()` | Issue and list refunds |
| `topups` | `create()`, `list()` | Add funds to a wallet |
| `payouts` | `create()`, `bulk()`, `list()` | Send funds to recipients |
| `transfers` | `toMain()`, `list()` | Move funds between wallets |
| `wallets` | `mainBalance()`, `serviceBalance()`, `manualBalance()` | Check balances |
| `services` | `listAll()`, `get()`, `create()`, `update()`, `delete()`, `createApiKey()`, `listApiKeys()`, `rotateApiKey()`, `revokeApiKey()`, `updateWebhook()`, `listPaymentMethods()`, `setPaymentMethod()`, `setPaymentMethods()` | Manage services, keys, and payment methods |
| `customers` | `list()`, `get()`, `create()`, `update()`, `delete()`, `importCsv()` | Customer management |
| `analytics` | `dashboard()`, `paymentsChart()`, `grossVolume()`, `totalPayouts()` | Reporting and analytics |

---

## Wallet top-up

Top-up lets authenticated merchants add funds to their TangentoPay wallet via Stripe Checkout. It uses the `MerchantClient`.

### Why `idempotency_key` is required

Every call to `$merchant->topups->create()` is an independent function call. If you retry after a network failure without passing the same key, the server sees a brand-new request and creates a second Stripe Checkout Session — potentially charging the user twice.

The rule is simple: **generate the key once, store it, reuse it on every retry of the same top-up intent**. Passing no key throws an `\InvalidArgumentException` immediately.

```php
use TangentoPay\MerchantClient;
use TangentoPay\Resources\TopupsResource;

$merchant = new MerchantClient([
    'apiToken' => $_ENV['TANGENTOPAY_API_TOKEN'],
]);

// Step 1 — generate ONCE and store in your session / database
$key = TopupsResource::generateIdempotencyKey();

// Step 2 — initiate the top-up (safe to retry with the same key)
$session = $merchant->topups->create([
    'amount'          => 50.00,
    'currency_code'   => 'USD',
    'idempotency_key' => $key,     // required — throws if missing
    'return_url'      => 'https://app.com/topup/success',
    'cancel_url'      => 'https://app.com/topup/cancel',
]);

// Step 3 — redirect the user to complete payment
header('Location: ' . $session->redirectUrl);
```

**On retry (network timeout, double-tap):**

```php
// Same key → server returns the existing session, no new charge
$session = $merchant->topups->create([
    'amount'          => 50.00,
    'currency_code'   => 'USD',
    'idempotency_key' => $key,   // same key as before
    'return_url'      => 'https://app.com/topup/success',
]);
// $session->redirectUrl is the same Stripe URL — user continues where they left off
```

**Storing the key in a Laravel session:**

```php
// Generate and store before showing the top-up form
$key = session()->remember('topup_idempotency_key', fn() => TopupsResource::generateIdempotencyKey());

$session = $merchant->topups->create([
    'amount'          => (float) $request->amount,
    'currency_code'   => 'USD',
    'idempotency_key' => $key,
    'return_url'      => route('topup.success'),
]);

// Clear it only after the webhook confirms completion
```

### Top-up without products

Unlike checkout sessions, top-ups do not require a products array. Pass `amount` + `currency_code` directly — the payment line item is created automatically.

---

## Service setup

### WordPress / WooCommerce plugin

1. Log in to [TangentoPay Dashboard](https://tangentopay.com)
2. **Services → Create service** — type: `plugin`
3. **API Keys → Create key** — type: `live` (and `test` for test mode)
4. Copy all three credentials immediately (shown **once only**):
   - `publicKey` (`pk_live_…`) → **Live Service Key** in WooCommerce plugin settings
   - `webhookSecret` (`whs_live_…`) → **Live Webhook Secret** in WooCommerce plugin settings
5. Copy the **Webhook URL** shown in WooCommerce → paste it into **Dashboard → Webhooks**
6. The `secretKey` (`sk_live_…`) is not needed for the WordPress plugin — store it safely

### SDK / server-side integration

```php
use TangentoPay\TangentoPay;

$merchant = TangentoPay::login('me@example.com', 'password', '123456');

// Create key pair (run once during setup)
$pair = $merchant->services->createApiKey($serviceId, 'Production server', 'live');

// Store immediately — shown once only:
echo $pair->publicKey;      // pk_live_…  → X-Service-Key
echo $pair->secretKey;      // sk_live_…
echo $pair->webhookSecret;  // whs_live_… → webhook verification

// Rotate when needed (old credentials stop working immediately)
$rotated = $merchant->services->rotateApiKey($serviceId, $pair->id);
echo $rotated->publicKey;
echo $rotated->webhookSecret;
```

**Environment variables (.env):**
```
TANGENTOPAY_SERVICE_KEY=pk_live_…        # X-Service-Key for checkout
TANGENTOPAY_SECRET_KEY=sk_live_…         # privileged server calls (future)
TANGENTOPAY_WEBHOOK_SECRET=whs_live_…    # from API Keys, not Webhook settings
TANGENTOPAY_TEST_SERVICE_KEY=pk_test_…
TANGENTOPAY_TEST_WEBHOOK_SECRET=whs_test_…
```

---

## Payment methods

Each service has its own set of enabled payment methods. Cards (Visa/Mastercard) are always enabled. Company accounts that have completed KYB verification can enable additional Stripe methods (Google Pay, Apple Pay, Alipay, WeChat Pay) per service via the SDK.

| Method | Default | Availability |
|---|---|---|
| Visa / Mastercard / Amex (card) | ✅ Always enabled | All account types |
| Google Pay | Off | Company accounts with KYB verification |
| Apple Pay | Off | Company accounts with KYB verification |
| Alipay | Off | Company accounts with KYB verification |
| WeChat Pay | Off | Company accounts with KYB verification |
| MoMo (Mobile Money) | Coming soon | Will be added as a native TangentoPay method |

### Managing payment methods per service

```php
// List all payment methods for a service (with enabled/locked/reason status)
$methods = $merchant->services->listPaymentMethods($serviceId);
// [{ slug: 'card', name: 'Card', enabled: true, locked: false }, ...]

// Toggle a single method on or off
$merchant->services->setPaymentMethod($serviceId, 'google_pay', true);

// Replace the entire set of enabled methods at once
// card must always be included
$merchant->services->setPaymentMethods($serviceId, ['card', 'apple_pay', 'alipay']);
```

Checkout sessions for that service will only show the methods you have enabled. If the account is not KYB-verified, non-card methods are returned as `locked: true` with a human-readable `reason`.

> **MoMo note:** Mobile Money support is on the roadmap and will be integrated as a first-class TangentoPay payment method, separate from Stripe.

---

## Service request logs

Every API request authenticated via `X-Service-Key` is automatically logged. The logs mirror the **Dashboard → Service → Logs** view.

```php
// List recent logs for a service (last 25 by default)
$page = $merchant->logs->list($serviceId, [
    'per_page'  => 25,
    'status'    => 500,         // filter by HTTP status code
    'method'    => 'POST',      // filter by HTTP method
    'date_from' => '2026-05-01',
    'date_to'   => '2026-05-31',
]);

foreach ($page['data']['data'] as $entry) {
    echo $entry['request_id'] . ' ' . $entry['status_code'] . ' '
       . $entry['application'] . ' ' . $entry['duration_ms'] . "ms\n";
}
// a1b2c3d4-...  200  WooCommerce Inc.  342ms

// Fetch a single entry by its request ID
// (same UUID as the X-Request-ID response header)
$detail = $merchant->logs->get($serviceId, 'a1b2c3d4-e5f6-7890-abcd-ef1234567890');
echo $detail['data']['transaction_uid']; // TXN-...
```

---

## Error handling

All SDK exceptions extend `TangentoPayException` so you can catch the whole family with one clause or be specific.

```php
use TangentoPay\Exceptions\TangentoPayException;
use TangentoPay\Exceptions\AuthenticationException;
use TangentoPay\Exceptions\ValidationException;
use TangentoPay\Exceptions\NotFoundException;
use TangentoPay\Exceptions\RateLimitException;
use TangentoPay\Exceptions\ServerException;
use TangentoPay\Exceptions\NetworkException;

try {
    $refund = $merchant->refunds->create([
        'transactionUid' => 'TXN-001',
        'amount'         => 9999.00,
        'reason'         => 'test',
        'pin'            => 'wrong',
        'recipientType'  => 'stripe',
    ]);
} catch (ValidationException $e) {
    // Server-side field validation failed
    print_r($e->errors); // ['amount' => ['exceeds original transaction amount']]
} catch (AuthenticationException $e) {
    // Token is invalid or expired — re-authenticate
    echo 'Invalid or expired token';
} catch (NotFoundException $e) {
    echo 'Transaction not found';
} catch (RateLimitException $e) {
    // SDK already retried with exponential backoff and gave up
    echo "Rate limited — retry after {$e->retryAfter}s";
} catch (ServerException $e) {
    // 5xx — SDK retried 3 times automatically before throwing
    echo 'TangentoPay server error';
} catch (NetworkException $e) {
    // Timeout, DNS failure, connection refused
    echo 'Network error — check your connection';
} catch (TangentoPayException $e) {
    echo 'SDK error: ' . $e->getMessage();
}
```

### Exception reference

| Class | HTTP status | Notes |
|---|---|---|
| `AuthenticationException` | 401 | Invalid or expired API key / token |
| `PermissionException` | 403 | Authenticated but not authorised |
| `NotFoundException` | 404 | Resource does not exist |
| `ValidationException` | 422 | Field-level errors in `$e->errors` |
| `RateLimitException` | 429 | After all retries exhausted; `$e->retryAfter` seconds |
| `ServerException` | 5xx | After 3 automatic retries |
| `NetworkException` | — | Timeout, DNS, connection error |
| `WebhookSignatureException` | — | Invalid HMAC, tampered payload, or replay attack |

---

## Webhook verification

TangentoPay signs every webhook with HMAC-SHA256 and includes a timestamp to prevent replay attacks. The SDK verifies both automatically.

```php
use TangentoPay\Webhook;

$event = Webhook::constructEvent(
    payload:          $rawBody,       // string — raw request body, do NOT parse first
    sigHeader:        $sigHeader,     // value of the X-TangentoPay-Signature header
    secret:           $webhookSecret, // whs_live_... or whs_test_... from API Keys (shown once)
    toleranceS:       300,            // reject events older than 5 min (default)
);
```

**Signature header format:**

```
X-TangentoPay-Signature: t=1716134400,sha256=abcdef1234...
```

**Laravel example:**

```php
use Illuminate\Http\Request;
use TangentoPay\Webhook;
use TangentoPay\Exceptions\WebhookSignatureException;

Route::post('/webhooks/tangentopay', function (Request $request) {
    try {
        $event = Webhook::constructEvent(
            $request->getContent(),
            $request->header('X-TangentoPay-Signature', ''),
            config('services.tangentopay.webhook_secret'),
        );
    } catch (WebhookSignatureException $e) {
        return response('Invalid webhook', 400);
    }

    match ($event->event) {
        'transaction.payment_completed' => handlePayment($event->payload),
        'transaction.refund_completed'  => handleRefund($event->payload),
        default => null,
    };

    return response('', 200);
});
```

**Symfony example:**

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TangentoPay\Webhook;
use TangentoPay\Exceptions\WebhookSignatureException;

#[Route('/webhooks/tangentopay', methods: ['POST'])]
public function handle(Request $request): Response
{
    try {
        $event = Webhook::constructEvent(
            $request->getContent(),
            $request->headers->get('X-TangentoPay-Signature', ''),
            $this->getParameter('tangentopay.webhook_secret'),
        );
    } catch (WebhookSignatureException $e) {
        return new Response('Invalid webhook', 400);
    }

    if ($event->event === 'transaction.payment_completed') {
        $this->orderService->fulfill($event->payload['transaction_uid']);
    }

    return new Response('', 200);
}
```

### Supported webhook events

| Event | When it fires |
|---|---|
| `transaction.payment_completed` | Payment successfully processed |
| `transaction.payment_failed` | Payment attempt failed |
| `transaction.refund_completed` | Refund issued successfully |
| `transaction.payout_completed` | Payout sent to recipient |
| `transaction.topup_completed` | Wallet top-up completed |

### Testing webhooks locally

Use `Webhook::generateSignature()` to create valid signatures in your test suite without a real TangentoPay server:

```php
use TangentoPay\Webhook;

$payload = json_encode(['event' => 'transaction.payment_completed', 'payload' => []]);
$secret  = 'whs_test_yoursecrethere';

// Generate a valid signature (pass a fixed timestamp to avoid flakiness)
$signature = Webhook::generateSignature($payload, $secret, 1716134400);

// Pass PHP_INT_MAX as tolerance so the fixed timestamp never expires
$event = Webhook::constructEvent($payload, $signature, $secret, PHP_INT_MAX);
```

---

## Supported currencies

TangentoPay supports Stripe's full currency list. Commonly used currencies:

| Code | Currency |
|---|---|
| `USD` | US Dollar |
| `EUR` | Euro |
| `GBP` | British Pound |
| `XAF` | Central African CFA Franc (Cameroon, Chad, Congo, Gabon…) |
| `NGN` | Nigerian Naira |
| `GHS` | Ghanaian Cedi |
| `KES` | Kenyan Shilling |
| `ZAR` | South African Rand |

> **XAF note:** Amounts in XAF are zero-decimal — pass `500` not `5.00`. The SDK passes amounts to the API as-is; ensure you are sending the correct unit for your currency.

---

## Contributing

See [CONTRIBUTING.md](https://github.com/Grut-Design-Agency/tangentopay-php/blob/main/CONTRIBUTING.md) for development setup, branch naming, commit conventions, and release instructions.

---

## Security

Security issues should **not** be reported via public GitHub issues.

Please report vulnerabilities by emailing **security@tangentopay.com**. We will acknowledge within 48 hours and aim to release a fix within 7 days for critical issues.

### Security features built into this SDK

- **HTTPS enforced** — rejects any `baseUrl` that does not use `https://`, preventing accidental credential leakage over plain HTTP
- **Header injection protection** — credentials are validated for CR/LF characters at construction time, preventing HTTP header injection attacks
- **Header key sanitisation** — extra header keys are validated to reject CR, LF, colon, and space characters
- **Webhook replay protection** — `constructEvent()` rejects events with timestamps outside a configurable tolerance window (default 5 minutes)
- **Webhook hex validation** — the SHA-256 digest in the signature header is validated as exactly 64 hex characters before comparison
- **Payload size limit** — webhook payloads over 10 MB are rejected before any HMAC computation
- **Timing-safe comparison** — webhook signatures are verified with `hash_equals()` to prevent timing side-channel attacks
- **Credential masking** — API keys and tokens are masked in `__debugInfo()` output so they do not appear in `var_dump()` or debug logs
- **Capped retry backoff** — the `Retry-After` value from the server is capped at 60 seconds to prevent server-controlled denial-of-service
- **Protected auth headers** — `extraHeaders` cannot override `Authorization` or `X-Service-Key`
- **Idempotent-only retries** — only GET, HEAD, OPTIONS, PUT, and DELETE are retried on 5xx; POST and PATCH are not, preventing duplicate records

---

## License

MIT — see [LICENSE](LICENSE) for the full text.

---

<p align="center">
  Built with ❤️ by the <a href="https://tangentopay.com">TangentoPay</a> team
</p>
