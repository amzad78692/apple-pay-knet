<?php

namespace Amzad\ApplePayKnet\Exceptions;

use RuntimeException;

class KnetException extends RuntimeException
{
    /** @var string|null */
    private $responseCode;

    public function __construct(string $message, ?string $responseCode = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseCode = $responseCode;
    }

    public static function authorizationFailed(string $responseCode, string $responseMessage): self
    {
        return new self(
            'KNET authorization failed: ' . $responseMessage . ' (code ' . $responseCode . ')',
            $responseCode
        );
    }

    public static function httpError(string $url, int $httpStatus): self
    {
        return new self('KNET HTTP request failed: ' . $url . ' returned HTTP ' . $httpStatus);
    }

    public static function connectionFailed(string $reason): self
    {
        return new self('KNET connection failed: ' . $reason);
    }

    public function getResponseCode(): ?string
    {
        return $this->responseCode;
    }
}
