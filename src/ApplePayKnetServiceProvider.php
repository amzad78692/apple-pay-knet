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
                config('apple-pay-knet.display_name'),
                config('apple-pay-knet.validation_url'),
                config('apple-pay-knet.initiative'),
                config('apple-pay-knet.certificate_path'),
                config('apple-pay-knet.certificate_key_path'),
                config('apple-pay-knet.certificate_key_password', '')
            );
        });

        $this->app->singleton(KnetGateway::class, function () {
            return new KnetGateway(
                config('apple-pay-knet.knet.endpoint'),
                config('apple-pay-knet.knet.id'),
                config('apple-pay-knet.knet.password'),
                config('apple-pay-knet.knet.response_url'),
                config('apple-pay-knet.knet.error_url')
            );
        });

        $this->app->singleton(PaymentProcessor::class, function ($app) {
            return new PaymentProcessor(
                $app->make(KnetGateway::class),
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
