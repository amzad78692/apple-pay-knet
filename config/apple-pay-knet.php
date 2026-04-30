<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apple Pay Merchant Configuration
    |--------------------------------------------------------------------------
    */

    // Your Apple Pay merchant identifier (e.g. merchant.com.yourcompany)
    'merchant_identifier' => env('APPLE_PAY_MERCHANT_IDENTIFIER', ''),

    // The registered domain where Apple Pay is used (e.g. yourstore.com)
    'domain_name' => env('APPLE_PAY_DOMAIN_NAME', ''),

    // The name displayed on the Apple Pay payment sheet
    'display_name' => env('APPLE_PAY_DISPLAY_NAME', ''),

    // Absolute path to the merchant identity certificate (.pem file)
    // NEVER place these inside the public/ directory
    'certificate_path' => env('APPLE_PAY_CERTIFICATE_PATH', ''),

    // Absolute path to the certificate private key file (.pem file)
    'certificate_key_path' => env('APPLE_PAY_CERTIFICATE_KEY_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | KNET Gateway Configuration
    |--------------------------------------------------------------------------
    */

    'knet' => [
        // Base URL provided by your acquiring bank (no trailing slash)
        'api_url' => env('KNET_API_URL', ''),

        // Merchant ID issued by KNET / your acquiring bank
        'merchant_id' => env('KNET_MERCHANT_ID', ''),

        // Terminal ID issued by KNET / your acquiring bank
        'terminal_id' => env('KNET_TERMINAL_ID', ''),

        // API key for request authentication
        'api_key' => env('KNET_API_KEY', ''),

        // API secret used to generate HMAC-SHA256 request signatures
        'api_secret' => env('KNET_API_SECRET', ''),
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
    | Currency
    |--------------------------------------------------------------------------
    | ISO 4217 numeric currency code.
    | 414 = Kuwaiti Dinar (KWD). Amounts are stored and sent in fils (× 1000).
    */

    'currency' => env('APPLE_PAY_CURRENCY', '414'),

    /*
    |--------------------------------------------------------------------------
    | Transaction Logging
    |--------------------------------------------------------------------------
    | When enabled, every charge attempt is recorded in the
    | apple_pay_transactions table (run the package migration first).
    */

    'log_transactions' => env('APPLE_PAY_LOG_TRANSACTIONS', true),

];
