# amzad/apple-pay-knet

Apple Pay on the Web + KNET Direct integration for Laravel.  
Designed for Kuwaiti merchants who want to accept Apple Pay payments through the KNET payment gateway with minimal setup.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/amzad/apple-pay-knet.svg)](https://packagist.org/packages/amzad/apple-pay-knet)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E7.4%7C%5E8.0-blue)](composer.json)

---

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Apple Pay Setup](#apple-pay-setup)
4. [Configuration](#configuration)
5. [Database Migration](#database-migration)
6. [Publishing Assets](#publishing-assets)
7. [Environment Variables](#environment-variables)
8. [Frontend Integration](#frontend-integration)
9. [Handling the Callback](#handling-the-callback)
10. [Using the Facade](#using-the-facade)
11. [API Endpoints](#api-endpoints)
12. [Transaction Model](#transaction-model)
13. [Exception Handling](#exception-handling)
14. [Sandbox vs Production](#sandbox-vs-production)
15. [Troubleshooting](#troubleshooting)

---

## Requirements

- PHP `^7.4` or `^8.0`
- Laravel `^8.0` | `^9.0` | `^10.0` | `^11.0` | `^12.0`
- PHP extensions: `curl`, `openssl`, `json`
- An **Apple Developer account** with Apple Pay enabled
- An **Apple Pay Merchant Identity Certificate** (`.pem`)
- A **KNET merchant account** with API credentials
- Your site must be served over **HTTPS** with a **registered Apple Pay domain**

---

## Installation

### Step 1 — Install the package via Composer

```bash
composer require amzad/apple-pay-knet
```

### Step 2 — Auto-discovery (Laravel 8+)

Laravel will automatically register the service provider and facade via package auto-discovery. No manual registration is required.

If you have disabled auto-discovery, add the following to your `config/app.php`:

```php
'providers' => [
    // ...
    Amzad\ApplePayKnet\ApplePayKnetServiceProvider::class,
],

'aliases' => [
    // ...
    'ApplePayKnet' => Amzad\ApplePayKnet\Facades\ApplePayKnet::class,
],
```

---

## Apple Pay Setup

Before any code runs, you must complete the Apple Pay merchant setup. These steps are **required** and must be done in order.

### Step 1 — Enroll in the Apple Developer Program

Go to [developer.apple.com](https://developer.apple.com) and ensure you have an active paid developer account.

### Step 2 — Create a Merchant ID

1. In the Apple Developer portal, go to **Certificates, Identifiers & Profiles → Identifiers**.
2. Click **+** and select **Merchant IDs**.
3. Enter a description and identifier (e.g. `merchant.com.yourstore`).
4. Click **Register**.

### Step 3 — Register your domain

1. In the Apple Developer portal, open your Merchant ID.
2. Under **Apple Pay on the Web**, click **Add Domain**.
3. Download the domain verification file Apple provides.
4. Place it at this exact path on your server:

```
https://yourdomain.com/.well-known/apple-developer-merchantid-domain-association
```

5. Click **Verify** in the developer portal.

> The file must be accessible without redirect or authentication.

### Step 4 — Create a Merchant Identity Certificate

1. In the Apple Developer portal, open your Merchant ID.
2. Under **Merchant Identity Certificate**, click **Create Certificate**.
3. Generate a CSR (Certificate Signing Request) on your server:

```bash
openssl req -new -newkey rsa:2048 -nodes \
  -keyout merchant.key \
  -out merchant.csr \
  -subj "/CN=merchant.com.yourstore"
```

4. Upload the `.csr` file to Apple and download the resulting `.cer` file.

### Step 5 — Convert the certificate to PEM format

Apple issues `.cer` files in DER format. Convert it:

```bash
openssl x509 -inform DER -in merchant_id.cer -out merchant.pem
```

### Step 6 — Store certificates securely

- Store `merchant.pem` and `merchant.key` **outside your web root** (e.g. `/etc/ssl/apple-pay/`).
- Set restrictive permissions:

```bash
chmod 600 /etc/ssl/apple-pay/merchant.pem
chmod 600 /etc/ssl/apple-pay/merchant.key
```

---

## Configuration

### Step 1 — Publish the config file

```bash
php artisan vendor:publish --tag=apple-pay-knet-config
```

This creates `config/apple-pay-knet.php`.

### Step 2 — Review the config file

```php
// config/apple-pay-knet.php

return [

    // Apple Pay display name shown on the payment sheet
    'display_name' => env('APPLE_PAY_DISPLAY_NAME', ''),

    // Absolute path to the merchant identity certificate (.pem file)
    'certificate_path' => env('APPLE_PAY_CERTIFICATE_PATH', ''),

    // Absolute path to the certificate private key (.pem file)
    'certificate_key_path' => env('APPLE_PAY_CERTIFICATE_KEY_PATH', ''),

    // Password used when encrypting the private key (leave empty if none)
    'certificate_key_password' => env('APPLE_PAY_CERTIFICATE_KEY_PASSWORD', ''),

    // Apple Pay merchant validation URL (do not change unless Apple updates it)
    'validation_url' => env('APPLE_PAY_VALIDATION_URL', 'https://apple-pay-gateway-cert.apple.com/paymentservices/startSession'),

    'initiative' => 'web',

    'knet' => [
        // KNET payment endpoint (use sandbox during development)
        'endpoint' => env('KNET_ENDPOINT', 'https://www.kpaytest.com.kw/kpg/tranPipe.htm?param=tranInit&'),

        'id'           => env('KNET_ID', ''),
        'password'     => env('KNET_PASSWORD', ''),

        // URL where KNET will POST the successful payment response
        'response_url' => env('KNET_RESPONSE_URL', ''),

        // URL where KNET will redirect on payment error
        'error_url'    => env('KNET_ERROR_URL', ''),
    ],

    // URL prefix for all package routes
    'route_prefix' => env('APPLE_PAY_ROUTE_PREFIX', 'apple-pay'),

    // Middleware applied to all package routes
    'route_middleware' => ['web'],

    // Log every charge attempt to the apple_pay_transactions table
    'log_transactions' => env('APPLE_PAY_LOG_TRANSACTIONS', true),
];
```

---

## Database Migration

### Step 1 — Publish the migration

```bash
php artisan vendor:publish --tag=apple-pay-knet-migrations
```

### Step 2 — Run the migration

```bash
php artisan migrate
```

This creates the `apple_pay_transactions` table with the following columns:

| Column                      | Type              | Description                                   |
| --------------------------- | ----------------- | --------------------------------------------- |
| `id`                        | bigint            | Auto-increment primary key                    |
| `order_id`                  | string            | Your order/reference ID                       |
| `amount`                    | string            | KWD amount as decimal string (e.g. `"5.250"`) |
| `currency`                  | string            | ISO 4217 numeric code (`414` = KWD)           |
| `apple_transaction_id`      | string (nullable) | Apple Pay transaction identifier              |
| `knet_transaction_id`       | string (nullable) | KNET transaction ID                           |
| `status`                    | enum              | `pending`, `authorized`, `captured`, `failed` |
| `response_code`             | string (nullable) | KNET result code                              |
| `auth_code`                 | string (nullable) | KNET authorization code                       |
| `raw_response`              | json (nullable)   | Full KNET response payload                    |
| `created_at` / `updated_at` | timestamps        | Laravel timestamps                            |

> Skip this step if you set `'log_transactions' => false` in your config.

---

## Publishing Assets

### Publish the JavaScript handler

```bash
php artisan vendor:publish --tag=apple-pay-knet-assets
```

This copies `apple-pay-handler.js` to `public/vendor/apple-pay-knet/js/`. The Blade component references it automatically.

### Publish the Blade views (optional)

```bash
php artisan vendor:publish --tag=apple-pay-knet-views
```

This copies the views to `resources/views/vendor/apple-pay-knet/` so you can customise them.

---

## Environment Variables

Add the following to your `.env` file:

```dotenv
# ── Apple Pay ─────────────────────────────────────────────────────────────
APPLE_PAY_DISPLAY_NAME="Your Store Name"
APPLE_PAY_CERTIFICATE_PATH=/etc/ssl/apple-pay/merchant.pem
APPLE_PAY_CERTIFICATE_KEY_PATH=/etc/ssl/apple-pay/merchant.key
APPLE_PAY_CERTIFICATE_KEY_PASSWORD=         # leave blank if no password
APPLE_PAY_ROUTE_PREFIX=apple-pay
APPLE_PAY_LOG_TRANSACTIONS=true

# ── KNET ──────────────────────────────────────────────────────────────────
KNET_ENDPOINT=https://www.kpaytest.com.kw/kpg/tranPipe.htm?param=tranInit&
KNET_ID=your_knet_merchant_id
KNET_PASSWORD=your_knet_password
KNET_RESPONSE_URL=https://yourdomain.com/payment/callback
KNET_ERROR_URL=https://yourdomain.com/payment/error
```

> Replace `KNET_ENDPOINT` with the production URL when going live (provided by your bank).

---

## Frontend Integration

### Option A — Blade Component (Recommended)

#### Step 1 — Publish the JS asset

```bash
php artisan vendor:publish --tag=apple-pay-knet-assets
```

#### Step 2 — Add the component to your Blade view

```blade
<x-apple-pay-knet::apple-pay-button
    amount="5.250"
    reference="{{ $order->id }}"
    callbackUrl="{{ route('payment.callback') }}"
/>
```

The component automatically:

- Loads the Apple Pay SDK and the `apple-pay-handler.js` script (once per page)
- Shows/hides the button based on device support
- Handles merchant validation and payment processing

#### Available Component Props

| Prop             | Required | Default                                 | Description                                    |
| ---------------- | -------- | --------------------------------------- | ---------------------------------------------- |
| `amount`         | Yes      | —                                       | Payment amount as a string (e.g. `"5.250"`)    |
| `reference`      | Yes      | —                                       | Your unique order/reference ID                 |
| `callbackUrl`    | Yes      | —                                       | URL to POST the KNET response to after payment |
| `label`          | No       | `config('apple-pay-knet.display_name')` | Label shown on the payment sheet               |
| `currencyCode`   | No       | `KWD`                                   | ISO 4217 currency code                         |
| `countryCode`    | No       | `KW`                                    | ISO 3166 country code                          |
| `paymentGateway` | No       | `KNET`                                  | Payment gateway identifier                     |
| `onSuccess`      | No       | `null`                                  | JavaScript callback function on success        |
| `onError`        | No       | `null`                                  | JavaScript callback function on error          |
| `onCancel`       | No       | `null`                                  | JavaScript callback function on cancel         |

#### Custom JS Callbacks Example

```blade
<x-apple-pay-knet::apple-pay-button
    amount="{{ $order->total }}"
    reference="{{ $order->id }}"
    callbackUrl="{{ route('payment.callback') }}"
    onSuccess="function(response) { console.log('Payment successful', response); }"
    onError="function(error) { alert('Payment failed: ' + error); }"
    onCancel="function() { console.log('Payment cancelled'); }"
/>
```

---

### Option B — Manual JavaScript Integration

If you prefer not to use the Blade component, include the scripts manually and initialise `ApplePayKnet` directly.

#### Step 1 — Include the scripts

```html
<script src="https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js"></script>
<script src="/vendor/apple-pay-knet/js/apple-pay-handler.js"></script>
```

#### Step 2 — Add the Apple Pay button element

```html
<apple-pay-button
  buttonstyle="black"
  type="plain"
  locale="en"
  class="apple-pay-knet-button"
  style="display:none;"
>
</apple-pay-button>
```

#### Step 3 — Initialise the handler

```javascript
window.ApplePayKnet.init({
  validateMerchantUrl: "/apple-pay/validate-merchant",
  processPaymentUrl: "/apple-pay/process-payment",
  amount: "5.250",
  reference: "ORD-001",
  callbackUrl: "/payment/callback",
  csrfToken: document.querySelector('meta[name="csrf-token"]').content,

  // Optional callbacks
  onSuccess: function (knetResponse) {
    console.log("Payment authorized", knetResponse);
  },
  onError: function (error) {
    console.error("Payment error", error);
  },
  onCancel: function () {
    console.log("Payment cancelled by user");
  },
});
```

#### Full init options

| Option                 | Required | Default                                      | Description                                       |
| ---------------------- | -------- | -------------------------------------------- | ------------------------------------------------- |
| `validateMerchantUrl`  | Yes      | —                                            | Route to the package's validate-merchant endpoint |
| `processPaymentUrl`    | Yes      | —                                            | Route to the package's process-payment endpoint   |
| `amount`               | Yes      | —                                            | Payment amount string                             |
| `reference`            | Yes      | —                                            | Unique order reference                            |
| `callbackUrl`          | Yes      | —                                            | Your callback URL that receives the KNET response |
| `csrfToken`            | Yes      | —                                            | Laravel CSRF token                                |
| `label`                | No       | `"Your card will be charged"`                | Label on the payment sheet total line             |
| `applePayVersion`      | No       | `3`                                          | Apple Pay JS API version                          |
| `countryCode`          | No       | `"KW"`                                       | ISO 3166 country code                             |
| `currencyCode`         | No       | `"KWD"`                                      | ISO 4217 currency code                            |
| `merchantCapabilities` | No       | `["supports3DS"]`                            | Array of merchant capabilities                    |
| `supportedNetworks`    | No       | `["visa", "masterCard", "amex", "discover"]` | Array of supported card networks                  |
| `paymentGateway`       | No       | `"KNET"`                                     | Gateway identifier sent to the server             |
| `onSuccess`            | No       | `null`                                       | JS function called on successful authorization    |
| `onError`              | No       | `null`                                       | JS function called on any error                   |
| `onCancel`             | No       | `null`                                       | JS function called when the user cancels          |

---

## Handling the Callback

After a successful payment, the package auto-submits a hidden form that POSTs the KNET response fields to your `callbackUrl`. Create a route and controller action to handle this:

### Step 1 — Define the route

```php
// routes/web.php
Route::post('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::get('/payment/error',     [PaymentController::class, 'error'])->name('payment.error');
```

### Step 2 — Handle the callback

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function callback(Request $request)
    {
        // KNET posts these fields:
        $result    = $request->input('result');       // "CAPTURED" on success
        $trackId   = $request->input('trackid');      // Your reference/order ID
        $tranId    = $request->input('tranid');        // KNET transaction ID
        $authCode  = $request->input('auth');          // Authorization code
        $paymentId = $request->input('paymentid');     // KNET payment ID
        $amount    = $request->input('amt');           // Amount charged

        if ($result === 'CAPTURED') {
            // Mark your order as paid
            $order = Order::where('id', $trackId)->firstOrFail();
            $order->update([
                'status'         => 'paid',
                'knet_tran_id'   => $tranId,
                'knet_auth_code' => $authCode,
            ]);

            return redirect()->route('orders.success', $order);
        }

        return redirect()->route('payment.error')->with('error', 'Payment was not captured.');
    }

    public function error(Request $request)
    {
        return view('payment.error');
    }
}
```

> **Important:** Always verify the `result` field equals `"CAPTURED"` before marking an order as paid. Do not rely solely on the callback being called.

---

## Using the Facade

You can charge directly from your own controller without going through the package's HTTP endpoints:

```php
use Amzad\ApplePayKnet\Facades\ApplePayKnet;
use Amzad\ApplePayKnet\Exceptions\KnetException;
use Amzad\ApplePayKnet\Exceptions\ApplePayException;

// $applePayment is the full event.payment object from the JS onpaymentauthorized event
try {
    $response = ApplePayKnet::charge(
        amount: '5.250',        // KWD amount as string
        reference: 'ORD-001',   // Your unique order ID (becomes KNET trackid)
        applePayment: $applePayment
    );

    // $response is the parsed KNET response array
    if (($response['result'] ?? '') === 'CAPTURED') {
        // Payment successful
    }

} catch (KnetException $e) {
    // KNET authorization failed
    $code    = $e->getResponseCode();
    $message = $e->getMessage();
} catch (ApplePayException $e) {
    // Apple Pay merchant validation failed
    $message = $e->getMessage();
}
```

### charge() method signature

```php
ApplePayKnet::charge(string $amount, string $reference, array $applePayment): array
```

| Parameter       | Type   | Description                                                                   |
| --------------- | ------ | ----------------------------------------------------------------------------- |
| `$amount`       | string | KWD amount (e.g. `"5.250"`)                                                   |
| `$reference`    | string | Your unique order/reference ID                                                |
| `$applePayment` | array  | Full `event.payment` object from the Apple Pay JS `onpaymentauthorized` event |

---

## API Endpoints

The package registers the following routes automatically under the configured `route_prefix` (default: `apple-pay`):

| Method | URL                            | Name                               | Description                                         |
| ------ | ------------------------------ | ---------------------------------- | --------------------------------------------------- |
| `GET`  | `/apple-pay/validate-merchant` | `apple-pay-knet.validate-merchant` | Validates the merchant session with Apple's servers |
| `POST` | `/apple-pay/process-payment`   | `apple-pay-knet.process-payment`   | Processes the Apple Pay payment through KNET        |

### POST /apple-pay/process-payment

**Request body:**

| Field                | Type          | Required | Description                                   |
| -------------------- | ------------- | -------- | --------------------------------------------- |
| `amount`             | numeric       | Yes      | Payment amount (min: 0.001)                   |
| `reference`          | string        | Yes      | Unique order reference (max: 255 chars)       |
| `apple_pay_response` | object/string | Yes      | Full `event.payment` object from Apple Pay JS |
| `payment_gateway`    | string        | No       | Gateway identifier (default: `KNET`)          |

**Success response:**

```json
{
  "status": true,
  "response": {
    "result": "CAPTURED",
    "trackid": "ORD-001",
    "tranid": "...",
    "auth": "...",
    "paymentid": "..."
  }
}
```

**Error response:**

```json
{
  "status": false,
  "message": "KNET authorization failed: ...",
  "code": "NOT_CAPTURED"
}
```

### Changing the route prefix

```dotenv
APPLE_PAY_ROUTE_PREFIX=payments/apple
```

This changes the routes to `/payments/apple/validate-merchant` and `/payments/apple/process-payment`.

### Adding custom middleware

```php
// config/apple-pay-knet.php
'route_middleware' => ['web', 'auth'],
```

---

## Transaction Model

When `log_transactions` is enabled, you can query the `Transaction` model directly:

```php
use Amzad\ApplePayKnet\Models\Transaction;

// All successful transactions
$successfulPayments = Transaction::successful()->get();

// All failed transactions
$failedPayments = Transaction::failed()->get();

// Transactions for a specific order
$orderTransactions = Transaction::where('order_id', 'ORD-001')->get();

// Amount formatted as KWD float
$transaction = Transaction::find(1);
$kwd = $transaction->amount_in_kwd; // e.g. 5.25
```

### Available scopes

| Scope                       | Description                                  |
| --------------------------- | -------------------------------------------- |
| `Transaction::successful()` | Filters `authorized` and `captured` statuses |
| `Transaction::failed()`     | Filters `failed` status                      |

### Transaction statuses

| Status       | Description                                      |
| ------------ | ------------------------------------------------ |
| `pending`    | Charge initiated, awaiting KNET response         |
| `authorized` | KNET returned a successful authorization         |
| `captured`   | Payment captured (set manually in your callback) |
| `failed`     | KNET returned an error or connection failed      |

---

## Exception Handling

The package throws two exception types:

### `ApplePayException`

Thrown when Apple merchant validation fails.

```php
use Amzad\ApplePayKnet\Exceptions\ApplePayException;

try {
    // ...
} catch (ApplePayException $e) {
    // Reasons include:
    // - Certificate file not found or unreadable
    // - Certificate not in PEM format
    // - Certificate expired
    // - Certificate/key mismatch
    // - Apple server returned non-200 response
    logger()->error('Apple Pay error: ' . $e->getMessage());
}
```

### `KnetException`

Thrown when KNET authorization fails or a network error occurs.

```php
use Amzad\ApplePayKnet\Exceptions\KnetException;

try {
    // ...
} catch (KnetException $e) {
    $responseCode = $e->getResponseCode(); // e.g. "NOT_CAPTURED"
    $message      = $e->getMessage();
    logger()->error('KNET error: ' . $message, ['code' => $responseCode]);
}
```

---

## Sandbox vs Production

### Sandbox (testing)

Use the KNET test endpoint:

```dotenv
KNET_ENDPOINT=https://www.kpaytest.com.kw/kpg/tranPipe.htm?param=tranInit&
```

> Apple Pay requires a real device with a real card even in sandbox mode. Safari on Mac with a linked iPhone/Apple Watch will work.

### Production

Replace with your bank-provided production endpoint:

```dotenv
KNET_ENDPOINT=https://www.kpay.com.kw/kpg/tranPipe.htm?param=tranInit&
```

Also update `APPLE_PAY_VALIDATION_URL` if Apple provides a different production URL:

```dotenv
APPLE_PAY_VALIDATION_URL=https://apple-pay-gateway.apple.com/paymentservices/startSession
```

---

## Troubleshooting

### Apple Pay button not showing

- Ensure the page is served over HTTPS.
- Ensure you are using Safari on a compatible Apple device.
- Verify the domain is registered and verified in the Apple Developer portal.
- Check browser console for `[ApplePayKnet]` log messages.

### Merchant validation returns 400

- Confirm your certificate is in **PEM format** (not DER). Run:
  ```bash
  openssl x509 -inform DER -in merchant_id.cer -out merchant.pem
  ```
- Verify your domain is registered in the Apple Developer portal under the correct Merchant ID.
- Make sure the `.well-known/apple-developer-merchantid-domain-association` file is publicly accessible.
- Check the certificate has not expired.

### Certificate errors on boot

The package validates your certificate on every merchant validation call. Common errors and their fixes:

| Error message                                | Fix                                                                                 |
| -------------------------------------------- | ----------------------------------------------------------------------------------- |
| `Certificate file not found or not readable` | Check `APPLE_PAY_CERTIFICATE_PATH` points to the correct file with read permissions |
| `Certificate file is not in PEM format`      | Convert: `openssl x509 -inform DER -in file.cer -out file.pem`                      |
| `Merchant Identity Certificate expired`      | Renew in the Apple Developer portal                                                 |
| `Certificate and private key do not match`   | Ensure you use the key that generated the CSR for this specific certificate         |
| `Private key file could not be loaded`       | Check `APPLE_PAY_CERTIFICATE_KEY_PATH` and `APPLE_PAY_CERTIFICATE_KEY_PASSWORD`     |

### KNET returns no trackid

- Verify `KNET_ID` and `KNET_PASSWORD` are correct.
- Ensure `KNET_RESPONSE_URL` and `KNET_ERROR_URL` are publicly accessible HTTPS URLs.
- Check you are using the correct endpoint (sandbox vs production).

### CSRF token mismatch

- Ensure `csrfToken` is passed in the JS init config.
- The Blade component handles this automatically via `{{ csrf_token() }}`.
- If using the API from a SPA, include the `X-CSRF-TOKEN` header or use a Sanctum token.

---

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

## Author

**Amzad** — [amzad.nnt@gmail.com](mailto:amzad.nnt@gmail.com)
