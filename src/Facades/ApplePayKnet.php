<?php

namespace Amzad\ApplePayKnet\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array charge(float $amount, string $orderId, array $encryptedToken, ?array $billingContact = null)
 *
 * @see \Amzad\ApplePayKnet\Services\PaymentProcessor
 */
class ApplePayKnet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'apple-pay-knet';
    }
}
