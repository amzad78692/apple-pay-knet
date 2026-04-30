<?php

namespace Amzad\ApplePayKnet\Exceptions;

use RuntimeException;

class ApplePayException extends RuntimeException
{
    public static function merchantValidationFailed(string $reason): self
    {
        return new self('Apple Pay merchant validation failed: ' . $reason);
    }

    public static function invalidValidationUrl(string $url): self
    {
        return new self('Invalid Apple Pay validation URL: ' . $url);
    }
}
