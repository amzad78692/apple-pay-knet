<?php

namespace Amzad\ApplePayKnet\Services;

use Amzad\ApplePayKnet\Exceptions\ApplePayException;

class MerchantValidator
{
    /** @var string */
    private $displayName;

    /** @var string */
    private $validationUrl;

    /** @var string */
    private $initiative;

    /** @var string */
    private $certificatePath;

    /** @var string */
    private $certificateKeyPath;

    /** @var string */
    private $certificateKeyPassword;

    public function __construct(
        string $displayName,
        string $validationUrl,
        string $initiative,
        string $certificatePath,
        string $certificateKeyPath,
        string $certificateKeyPassword = ''
    ) {
        $this->displayName            = $displayName;
        $this->validationUrl          = $validationUrl;
        $this->initiative             = $initiative;
        $this->certificatePath        = $certificatePath;
        $this->certificateKeyPath     = $certificateKeyPath;
        $this->certificateKeyPassword = $certificateKeyPassword;
    }

    /**
     * Validate the merchant session with Apple's servers.
     *
     * Uses the fixed validation URL from config (same approach as the working
     * apple-pay-php implementation). The merchant identifier is read directly
     * from the certificate so you don't have to configure it separately.
     *
     * @return array  Opaque merchant session to pass to completeMerchantValidation()
     * @throws ApplePayException
     */
    public function validate(): array
    {
        $merchantIdentifier = $this->getMerchantIdentifierFromCert();
        $domain             = $this->getDomainFromRequest();

        $postData = [
            'merchantIdentifier' => $merchantIdentifier,
            'displayName'        => $this->displayName,
            'domainName'         => $domain,
            'initiative'         => $this->initiative,
            'initiativeContext'  => $domain,
        ];

        $curlOptions = [
            CURLOPT_URL            => $this->validationUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_SSLCERT        => $this->certificatePath,
            CURLOPT_SSLKEY         => $this->certificateKeyPath,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($this->certificateKeyPassword !== '') {
            $curlOptions[CURLOPT_SSLKEYPASSWD] = $this->certificateKeyPassword;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw ApplePayException::merchantValidationFailed($curlError);
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw ApplePayException::merchantValidationFailed('Invalid JSON in Apple response: ' . $response);
        }

        return $decoded;
    }

    /**
     * Extract the merchant identifier (UID) from the certificate file.
     * This avoids having to configure it separately — Apple embeds it in the cert.
     *
     * @throws ApplePayException
     */
    private function getMerchantIdentifierFromCert(): string
    {
        if (!file_exists($this->certificatePath)) {
            throw ApplePayException::merchantValidationFailed(
                'Merchant identity certificate not found: ' . $this->certificatePath
            );
        }

        $certContent = file_get_contents($this->certificatePath);
        $parsed      = openssl_x509_parse($certContent);

        if (!$parsed || empty($parsed['subject']['UID'])) {
            throw ApplePayException::merchantValidationFailed(
                'Could not read merchant identifier from certificate.'
            );
        }

        return $parsed['subject']['UID'];
    }

    /**
     * Get the current HTTP host for initiative context.
     */
    private function getDomainFromRequest(): string
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : parse_url($this->validationUrl, PHP_URL_HOST);
    }
}

