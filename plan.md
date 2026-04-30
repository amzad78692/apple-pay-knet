# Plan: `amzad/apple-pay-knet` PHP/Laravel Package

## Overview

A Composer package that integrates **Apple Pay on the Web** with **KNET Direct** (Kuwait's national payment network via your acquiring bank's API).

The encrypted Apple Pay token is forwarded to KNET's API without server-side decryption, keeping the integration simple and secure. The package ships with a Blade component (button + JS), two backend endpoints, and transaction logging — auto-discovered in Laravel 8+.

---

## Decisions & Scope

| Concern | Decision |
|---|---|
| Package name | `amzad/apple-pay-knet` |
| PHP namespace | `Amzad\ApplePayKnet` |
| KNET provider | KNET Direct via acquiring bank's API |
| Apple Pay mode | Web only (ApplePaySession JS API) |
| Token handling | Forward encrypted token to KNET (no server-side decryption) |
| Laravel support | Laravel 8, 9, 10, 11 |
| PHP support | PHP 7.4+ and 8.x |
| v1 features | Merchant validation, authorize+capture, transaction logging, Blade button component |
| Excluded from v1 | Refunds, mobile/native SDK, Nova/Filament, async webhooks |

---

## Directory Structure

```
apple-pay-knet/
├── src/
│   ├── ApplePayKnetServiceProvider.php
│   ├── Facades/
│   │   └── ApplePayKnet.php
│   ├── Services/
│   │   ├── MerchantValidator.php
│   │   ├── KnetGateway.php
│   │   └── PaymentProcessor.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── ApplePayController.php
│   │   └── Requests/
│   │       ├── ValidateMerchantRequest.php
│   │       └── ProcessPaymentRequest.php
│   ├── Models/
│   │   └── Transaction.php
│   └── Exceptions/
│       ├── ApplePayException.php
│       └── KnetException.php
├── config/
│   └── apple-pay-knet.php
├── routes/
│   └── api.php
├── resources/
│   ├── views/
│   │   └── components/
│   │       └── apple-pay-button.blade.php
│   └── js/
│       └── apple-pay-handler.js
├── database/
│   └── migrations/
│       └── create_apple_pay_transactions_table.php
├── tests/
│   ├── Unit/
│   │   ├── MerchantValidatorTest.php
│   │   ├── KnetGatewayTest.php
│   │   └── PaymentProcessorTest.php
│   └── Feature/
│       └── ApplePayControllerTest.php
├── composer.json
├── phpunit.xml
├── README.md
└── .gitignore
```

---

## Phase 1 — Package Skeleton

**Files:** `composer.json`, `ApplePayKnetServiceProvider.php`, `Facades/ApplePayKnet.php`, `config/apple-pay-knet.php`

### `composer.json`
- Name: `amzad/apple-pay-knet`
- Require: `php ^7.4|^8.0`, `illuminate/support ^8|^9|^10|^11`, `ext-curl`
- Dev require: `orchestra/testbench ^6|^7|^8|^9`, `phpunit/phpunit ^9|^10`
- PSR-4 autoload: `Amzad\ApplePayKnet\` → `src/`
- Laravel auto-discovery: registers provider + facade

### `ApplePayKnetServiceProvider`
- `register()` — binds `MerchantValidator`, `KnetGateway`, `PaymentProcessor` as singletons; all read from config
- `boot()` — loads routes and views; publishes config, migrations, views, and JS assets

### `Facades/ApplePayKnet.php`
- Extends `Facade`; `getFacadeAccessor()` returns `PaymentProcessor::class`

### `config/apple-pay-knet.php`

| Key | Description |
|---|---|
| `merchant_identifier` | Apple merchant ID (e.g. `merchant.com.example`) |
| `domain_name` | Registered domain for Apple Pay |
| `display_name` | Name shown on Apple Pay sheet |
| `certificate_path` | Path to merchant identity `.pem` certificate |
| `certificate_key_path` | Path to certificate private key |
| `knet.api_url` | Bank KNET API base URL |
| `knet.merchant_id` | KNET merchant ID |
| `knet.terminal_id` | KNET terminal ID |
| `knet.api_key` | API key |
| `knet.api_secret` | API secret (used for HMAC signing) |
| `route_prefix` | URL prefix for package routes (default: `apple-pay`) |
| `route_middleware` | Middleware for package routes (default: `['web']`) |
| `currency` | ISO 4217 numeric code (default: `414` for KWD) |
| `log_transactions` | Enable DB transaction logging (default: `true`) |

---

## Phase 2 — Core Services *(can be built in parallel)*

### `MerchantValidator`
- Method: `validate(string $validationUrl): array`
- Makes an mTLS POST to Apple's `$validationUrl` using the merchant identity `.pem` certificate
- Sends payload: `merchantIdentifier`, `domainName`, `displayName`
- Returns the opaque Apple merchant session JSON to pass back to the browser
- Throws `ApplePayException` on failure

### `KnetGateway`
- Constructor reads from config: `api_url`, `merchant_id`, `terminal_id`, `api_key`, `api_secret`
- Auto-signs every request with `HMAC-SHA256(json_encode($payload), $api_secret)`
- Methods:
  - `authorize(array $payload): array` — submit payment authorization
  - `capture(string $transactionId): array` — capture authorized transaction
  - `inquiry(string $transactionId): array` — query transaction status
- Throws `KnetException` on non-`00` response code or HTTP error

### `PaymentProcessor`
- Method: `charge(float $amount, string $orderId, array $encryptedToken, ?array $billingContact = null): array`
- Converts amount to fils (`$amount * 1000`), calls `KnetGateway::authorize()`
- If `log_transactions` is enabled, creates a `Transaction` record
- Returns `['success' => true, 'transactionId' => ..., 'authCode' => ...]`
- On failure: updates transaction status to `failed`, throws `KnetException`

---

## Phase 3 — HTTP Layer *(depends on Phase 2)*

### `ApplePayController`

**`validateMerchant(ValidateMerchantRequest $request)`**
- Input: `{ "validationUrl": "https://apple-pay-gateway.apple.com/..." }`
- Calls `MerchantValidator::validate()`
- Returns Apple merchant session JSON
- Used by JS `onvalidatemerchant` event

**`processPayment(ProcessPaymentRequest $request)`**
- Input: `{ "amount": 5.000, "orderId": "ORD-001", "token": { ... } }`
- Calls `PaymentProcessor::charge()`
- Returns `{ "success": true, "transactionId": "...", "authCode": "..." }` or `{ "success": false, "error": "..." }`

### Form Requests

| Request | Rules |
|---|---|
| `ValidateMerchantRequest` | `validationUrl`: required, URL |
| `ProcessPaymentRequest` | `amount`: required, numeric, min:0.001 · `orderId`: required, string · `token`: required, array · `token.paymentData`: required · `token.header`: required · `token.signature`: required · `token.version`: required, in:EC_v1,EC_v2 |

### `routes/api.php`
```
POST  {prefix}/validate-merchant   → ApplePayController@validateMerchant
POST  {prefix}/process-payment     → ApplePayController@processPayment
```
- Both routes wrapped in configurable `route_prefix` and `route_middleware`

---

## Phase 4 — Frontend Assets *(parallel with Phase 3)*

### `apple-pay-handler.js`
Full `ApplePaySession` lifecycle exposed as `window.ApplePayKnet.init(config)`:

1. Check `window.ApplePaySession` exists
2. Call `ApplePaySession.canMakePayments()` — hide button if false
3. On button click, create `new ApplePaySession(version, paymentRequest)` where `paymentRequest` includes `countryCode`, `currencyCode`, `merchantCapabilities`, `supportedNetworks`, `lineItems`, `total`
4. `onvalidatemerchant` → POST to `{prefix}/validate-merchant` → call `completeMerchantValidation()`
5. `onpaymentauthorized` → POST to `{prefix}/process-payment` with `event.payment.token` → call `completePayment(STATUS_SUCCESS or STATUS_FAILURE)`
6. `oncancel` / `onerror` → fire optional callbacks from config

Config object passed at init:
```js
{
  validateMerchantUrl: '/apple-pay/validate-merchant',
  processPaymentUrl:   '/apple-pay/process-payment',
  amount:              '5.000',
  orderId:             'ORD-001',
  label:               'My Store',
  currencyCode:        'KWD',
  countryCode:         'KW',
  csrfToken:           '...',
  onSuccess:           function(response) {},
  onError:             function(error) {}
}
```

### `apple-pay-button.blade.php` (Blade Component)
```blade
<x-apple-pay-knet::button
    :amount="$order->total"
    :order-id="$order->id"
    label="Pay with Apple Pay"
/>
```
- Renders the `apple-pay-button` CSS element
- Inlines the config object as a `<script>` block
- Includes (or stacks) `apple-pay-handler.js`
- Accepts optional `onSuccess` and `onError` JS callback strings

---

## Phase 5 — Database *(parallel with Phase 3–4)*

### Migration: `create_apple_pay_transactions_table`

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | PK |
| `order_id` | string, indexed | Merchant order reference |
| `amount` | unsignedBigInteger | In fils (KWD × 1000) |
| `currency` | string(3) | Default `414` |
| `apple_transaction_id` | string, nullable | From Apple Pay token |
| `knet_transaction_id` | string, nullable | From KNET response |
| `status` | enum | `pending`, `authorized`, `captured`, `failed` |
| `response_code` | string(4), nullable | KNET response code e.g. `00` |
| `auth_code` | string, nullable | KNET auth code |
| `raw_response` | json, nullable | Full KNET response |
| `created_at` / `updated_at` | timestamps | |

### `Transaction` Model
- `$fillable` — all columns
- `$casts` — `raw_response → array`, `amount → integer`
- Scopes: `successful()` (status = authorized/captured), `failed()` (status = failed)

---

## Phase 6 — Developer Experience *(depends on all above)*

### `README.md` sections
1. Requirements (PHP 7.4+, HTTPS domain, Apple Developer account, acquiring bank KNET credentials)
2. Installation — `composer require amzad/apple-pay-knet` + publish commands
3. Apple Pay setup checklist (domain verification file, certificate enrollment steps)
4. Configuration reference (table of all config keys)
5. Usage — Blade component (one-liner) and raw PHP API
6. Handling responses / events
7. Troubleshooting

### Publish Tags
| Tag | What it publishes |
|---|---|
| `apple-pay-knet-config` | `config/apple-pay-knet.php` |
| `apple-pay-knet-migrations` | `database/migrations/` |
| `apple-pay-knet-views` | `resources/views/vendor/apple-pay-knet/` |
| `apple-pay-knet-assets` | `public/vendor/apple-pay-knet/` |

### `.gitignore`
Always exclude certificate files: `*.pem`, `*.p8`, `*.p12`, `*.cer`

---

## Phase 7 — Tests *(parallel with Phase 6)*

| Test | What it verifies |
|---|---|
| `Unit/MerchantValidatorTest` | Correct mTLS request shape sent to Apple's URL; exception on HTTP error |
| `Unit/KnetGatewayTest` | HMAC-SHA256 signature attached; response parsed; `KnetException` on non-`00` code |
| `Unit/PaymentProcessorTest` | `Transaction` created on success; status set to `failed` + exception thrown on failure |
| `Feature/ApplePayControllerTest` | `POST /apple-pay/validate-merchant` returns 200 with session JSON; `POST /apple-pay/process-payment` returns success/failure JSON; validation errors return 422 |

---

## Integration Sequence (Full Flow)

```
Browser                   Your Server                   Apple             KNET Bank
  │                            │                            │                  │
  │── Click Apple Pay btn ──►  │                            │                  │
  │                            │                            │                  │
  │  onvalidatemerchant fires  │                            │                  │
  │── POST /validate-merchant ►│                            │                  │
  │                            │── mTLS POST validationUrl ►│                  │
  │                            │◄── Merchant Session JSON ──│                  │
  │◄── Merchant Session JSON ──│                            │                  │
  │  completeMerchantValidation│                            │                  │
  │                            │                            │                  │
  │  User authenticates (FaceID / TouchID / Passcode)       │                  │
  │                            │                            │                  │
  │  onpaymentauthorized fires │                            │                  │
  │── POST /process-payment ──►│                            │                  │
  │   (encrypted token)        │── HMAC-signed POST ───────────────────────►  │
  │                            │                            │              authorize()
  │                            │◄─── KNET response ─────────────────────────  │
  │                            │  {ResponseCode: "00", ...}                    │
  │◄── { success: true } ──────│                            │                  │
  │  completePayment(SUCCESS)  │                            │                  │
```

---

## Further Considerations

1. **KNET API field names** — Each acquiring bank may use slightly different field names. The `KnetGateway` uses a configurable `api_url` with HMAC auth as a baseline. Treat it as a thin adapter: field mapping can be overridden by extending `KnetGateway` and rebinding it in the service provider.

2. **Apple Pay domain verification file** — The `/.well-known/apple-developer-merchantid-domain-association` file must be served over HTTPS before any live test works. Consider adding a publishable public asset or artisan command to place this file.

3. **Certificate security** — Never commit `.pem`/`.p8` files to version control. Store them outside the web root and reference them via absolute paths in config, ideally from environment variables.

4. **Currency** — Amount is stored and sent in **fils** (smallest KWD unit: 1 KWD = 1000 fils). The `PaymentProcessor` handles the conversion from decimal KWD automatically.
