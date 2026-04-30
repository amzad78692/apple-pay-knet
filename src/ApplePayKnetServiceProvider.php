<?php

namespace Amzad\ApplePayKnet;

use Illuminate\Support\ServiceProvider;
use Amzad\ApplePayKnet\Services\MerchantValidator;
use Amzad\ApplePayKnet\Services\KnetGateway;
use Amzad\ApplePayKnet\Services\PaymentProcessor;

class ApplePayKnetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/apple-pay-knet.php',
            'apple-pay-knet'
        );

        $this->app->singleton(MerchantValidator::class, function () {
            return new MerchantValidator(
                config('apple-pay-knet.merchant_identifier'),
                config('apple-pay-knet.domain_name'),
                config('apple-pay-knet.display_name'),
                config('apple-pay-knet.certificate_path'),
                config('apple-pay-knet.certificate_key_path')
            );
        });

        $this->app->singleton(KnetGateway::class, function () {
            return new KnetGateway(
                config('apple-pay-knet.knet.api_url'),
                config('apple-pay-knet.knet.merchant_id'),
                config('apple-pay-knet.knet.terminal_id'),
                config('apple-pay-knet.knet.api_key'),
                config('apple-pay-knet.knet.api_secret')
            );
        });

        $this->app->singleton(PaymentProcessor::class, function ($app) {
            return new PaymentProcessor(
                $app->make(KnetGateway::class),
                config('apple-pay-knet.currency'),
                config('apple-pay-knet.log_transactions')
            );
        });

        $this->app->alias(PaymentProcessor::class, 'apple-pay-knet');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'apple-pay-knet');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/apple-pay-knet.php' => config_path('apple-pay-knet.php'),
            ], 'apple-pay-knet-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'apple-pay-knet-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/apple-pay-knet'),
            ], 'apple-pay-knet-views');

            $this->publishes([
                __DIR__ . '/../resources/js' => public_path('vendor/apple-pay-knet/js'),
            ], 'apple-pay-knet-assets');
        }
    }
}
