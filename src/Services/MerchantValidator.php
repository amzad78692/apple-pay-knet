<?php

namespace Amzad\ApplePayKnet\Services;

use Amzad\ApplePayKnet\Exceptions\ApplePayException;

class MerchantValidator
{
    /** @var string */
    private $merchantIdentifier;

    /** @var string */
    private $domainName;

    /** @var string */
    private $displayName;

    /** @var string */
    private $certificatePath;

    /** @var string */
    private $certificateKeyPath;

    public function __construct(
        string $merchantIdentifier,
        string $domainName,
        string $displayName,
        string $certificatePath,
        string $certificateKeyPath
    ) {
        $this->merchantIdentifier = $merchantIdentifier;
        $this->domainName         = $domainName;
        $this->displayName        = $displayName;
        $this->certificatePath    = $certificatePath;
        $this->certificateKeyPath = $certificateKeyPath;
    }

    /**
     * Validate the merchant session with Apple's servers.
     *
     * Called server-side in response to the browser's onvalidatemerchant event.
     * The $validationUrl is provided by Apple's JS and must be used as-is.
     *
     * @param  string $validationUrl  URL provided by Apple Pay JS (onvalidatemerchant event)
     * @return array                  Opaque merchant session to pass to completeMerchantValidation()
     *
     * @throws ApplePayException
     */
    public function validate(string $validationUrl): array
    {
        $this->assertValidAppleUrl($validationUrl);

        $payload = json_encode([
            'merchantIdentifier' => $this->merchantIdentifier,
            'domainName'         => $this->domainName,
            'displayName'        => $this->displayName,
        ]);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $validationUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_SSLCERT        => $this->certificatePath,
            CURLOPT_SSLKEY         => $this->certificateKeyPath,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw ApplePayException::merchantValidationFailed($curlError);
        }

        if ($httpCode !== 200) {
            throw ApplePayException::merchantValidationFailed('Apple returned HTTP ' . $httpCode);
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw ApplePayException::merchantValidationFailed('Invalid JSON in Apple response');
        }

        return $decoded;
    }

    /**
     * Ensure the validation URL belongs to Apple's domains to prevent SSRF.
     *
     * @throws ApplePayException
     */
    private function assertValidAppleUrl(string $url): void
    {
        $parsed = parse_url($url);

        if (!isset($parsed['scheme'], $parsed['host'])) {
            throw ApplePayException::invalidValidationUrl($url);
        }

        if ($parsed['scheme'] !== 'https') {
            throw ApplePayException::invalidValidationUrl($url . ' — must use HTTPS');
        }

        $host = strtolower($parsed['host']);

        // Only allow Apple-owned domains (SSRF protection)
        if (!preg_match('/^(apple-pay-gateway|cn-apple-pay-gateway)(-pr)?\.apple\.com$/', $host)) {
            throw ApplePayException::invalidValidationUrl($url . ' — host is not an Apple Pay gateway domain');
        }
    }
}
