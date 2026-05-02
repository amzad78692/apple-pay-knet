<?php

use Illuminate\Support\Facades\Route;
use Amzad\ApplePayKnet\Http\Controllers\ApplePayController;

$prefix     = config('apple-pay-knet.route_prefix', 'apple-pay');
$middleware = config('apple-pay-knet.route_middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        // GET — no request body needed; server uses its own fixed validation URL from config
        Route::get('validate-merchant', [ApplePayController::class, 'validateMerchant'])
            ->name('apple-pay-knet.validate-merchant');

        // POST — receives apple_pay_response (full event.payment), amount, reference
        Route::post('process-payment', [ApplePayController::class, 'processPayment'])
            ->name('apple-pay-knet.process-payment');
    });
