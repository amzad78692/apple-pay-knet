<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apple Pay Merchant Configuration
    |--------------------------------------------------------------------------
    */

    // Your Apple Pay display name shown on the payment sheet
    'display_name' => env('APPLE_PAY_DISPLAY_NAME', ''),

    // Absolute path to the merchant identity certificate (.pem file)
    // NEVER place inside public/. Store outside the web root.
    'certificate_path' => env('APPLE_PAY_CERTIFICATE_PATH', ''),

    // Absolute path to the certificate private key (.pem file)
    'certificate_key_path' => env('APPLE_PAY_CERTIFICATE_KEY_PATH', ''),

    // Password used when encrypting the private key (set during key generation)
    // Leave empty string if the key has no password
    'certificate_key_password' => env('APPLE_PAY_CERTIFICATE_KEY_PASSWORD', ''),

    // Apple Pay merchant validation endpoint (fixed — do not change unless Apple updates it)
    'validation_url' => env('APPLE_PAY_VALIDATION_URL', 'https://apple-pay-gateway.apple.com/paymentservices/paymentSession'),

    // Initiative type — always 'web' for web integrations
    'initiative' => 'web',

    /*
    |--------------------------------------------------------------------------
    | KNET Gateway Configuration
    |--------------------------------------------------------------------------
    | Credentials provided by your acquiring bank (KNET).
    */

    'knet' => [
        // KNET payment endpoint (use sandbox URL during development)
        'endpoint' => env('KNET_ENDPOINT', 'https://www.kpaytest.com.kw/kpg/tranPipe.htm?param=tranInit&'),

        // KNET merchant ID
        'id' => env('KNET_ID', ''),

        // KNET merchant password
        'password' => env('KNET_PASSWORD', ''),

        // URL where KNET will POST the successful payment response
        'response_url' => env('KNET_RESPONSE_URL', ''),

        // URL where KNET will redirect on payment error
        'error_url' => env('KNET_ERROR_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */

    // URL prefix for all package routes
    'route_prefix' => env('APPLE_PAY_ROUTE_PREFIX', 'apple-pay'),

    // Middleware applied to all package routes
    'route_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Transaction Logging
    |--------------------------------------------------------------------------
    | When enabled, every charge attempt is recorded in the
    | apple_pay_transactions table (run the package migration first).
    */

    'log_transactions' => env('APPLE_PAY_LOG_TRANSACTIONS', true),

];
