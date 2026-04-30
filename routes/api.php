<?php

use Illuminate\Support\Facades\Route;
use Amzad\ApplePayKnet\Http\Controllers\ApplePayController;

$prefix     = config('apple-pay-knet.route_prefix', 'apple-pay');
$middleware = config('apple-pay-knet.route_middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::post('validate-merchant', [ApplePayController::class, 'validateMerchant'])
            ->name('apple-pay-knet.validate-merchant');

        Route::post('process-payment', [ApplePayController::class, 'processPayment'])
            ->name('apple-pay-knet.process-payment');
    });
