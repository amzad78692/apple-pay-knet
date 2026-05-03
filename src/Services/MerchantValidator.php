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
        $this->assertCertificatesAreValid();

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
            CURLOPT_URL             => $this->validationUrl,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => json_encode($postData),
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
            CURLOPT_SSLCERT         => $this->certificatePath,
            CURLOPT_SSLCERTTYPE     => 'PEM',
            CURLOPT_SSLKEY          => $this->certificateKeyPath,
            CURLOPT_SSLKEYTYPE      => 'PEM',
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CERTINFO        => true,
        ];

        if ($this->certificateKeyPassword !== '') {
            $curlOptions[CURLOPT_SSLKEYPASSWD] = $this->certificateKeyPassword;
        }

        // Capture verbose TLS output to a temp file so we can include it in error messages
        $verboseHandle = fopen('php://temp', 'w+');
        $curlOptions[CURLOPT_VERBOSE] = true;
        $curlOptions[CURLOPT_STDERR]  = $verboseHandle;

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $certInfo  = curl_getinfo($ch, CURLINFO_CERTINFO);
        curl_close($ch);

        rewind($verboseHandle);
        $verboseLog = stream_get_contents($verboseHandle);
        fclose($verboseHandle);

        if ($curlError) {
            throw ApplePayException::merchantValidationFailed(
                'cURL error: ' . $curlError . "\nTLS details: " . $verboseLog
            );
        }

        if ($httpCode !== 200) {
            throw ApplePayException::merchantValidationFailed(
                'Apple returned HTTP ' . $httpCode . '. Response: ' . $response .
                "\nTLS details: " . $verboseLog
            );
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw ApplePayException::merchantValidationFailed('Invalid JSON in Apple response: ' . $response);
        }

        return $decoded;
    }

    /**
     * Pre-flight checks before contacting Apple.
     * Catches certificate problems locally with clear messages instead of a generic 400 from Apple.
     *
     * @throws ApplePayException
     */
    private function assertCertificatesAreValid(): void
    {
        // ── 1. Files exist and are readable ─────────────────────────────────
        if (!file_exists($this->certificatePath) || !is_readable($this->certificatePath)) {
            throw ApplePayException::merchantValidationFailed(
                'Certificate file not found or not readable: ' . $this->certificatePath
            );
        }

        if (!file_exists($this->certificateKeyPath) || !is_readable($this->certificateKeyPath)) {
            throw ApplePayException::merchantValidationFailed(
                'Certificate key file not found or not readable: ' . $this->certificateKeyPath
            );
        }

        // ── 2. Certificate is PEM, not DER ───────────────────────────────────
        $certContent = file_get_contents($this->certificatePath);

        if (strpos($certContent, '-----BEGIN CERTIFICATE-----') === false) {
            throw ApplePayException::merchantValidationFailed(
                'Certificate file is not in PEM format (missing -----BEGIN CERTIFICATE----- header). ' .
                'Apple issues .cer files in DER format — convert it first: ' .
                'openssl x509 -inform DER -in merchant_id.cer -out merchant.pem'
            );
        }

        // ── 3. Certificate can be parsed ─────────────────────────────────────
        $parsed = openssl_x509_parse($certContent);

        if ($parsed === false) {
            throw ApplePayException::merchantValidationFailed(
                'Certificate file could not be parsed by OpenSSL. It may be corrupt or malformed.'
            );
        }

        // ── 4. Certificate is not expired ────────────────────────────────────
        if (isset($parsed['validTo_time_t']) && $parsed['validTo_time_t'] < time()) {
            throw ApplePayException::merchantValidationFailed(
                'Merchant Identity Certificate expired on ' .
                date('Y-m-d', $parsed['validTo_time_t']) .
                '. Renew it in the Apple Developer portal.'
            );
        }

        // ── 5. Certificate is a Merchant Identity cert (has UID) ─────────────
        if (empty($parsed['subject']['UID'])) {
            throw ApplePayException::merchantValidationFailed(
                'This does not appear to be a Merchant Identity Certificate ' .
                '(no UID in subject). Make sure you downloaded the Merchant Identity ' .
                'Certificate from the Apple Developer portal, not the Payment Processing Certificate.'
            );
        }

        // ── 6. Private key can be loaded ─────────────────────────────────────
        $keyContent = file_get_contents($this->certificateKeyPath);
        $privateKey = $this->certificateKeyPassword !== ''
            ? openssl_pkey_get_private($keyContent, $this->certificateKeyPassword)
            : openssl_pkey_get_private($keyContent);

        if ($privateKey === false) {
            throw ApplePayException::merchantValidationFailed(
                'Private key file could not be loaded by OpenSSL. ' .
                (openssl_error_string() ?: 'Check the key file format and password.')
            );
        }

        // ── 7. Certificate and private key match ─────────────────────────────
        $certResource = openssl_x509_read($certContent);

        if (!openssl_x509_check_private_key($certResource, $privateKey)) {
            throw ApplePayException::merchantValidationFailed(
                'Certificate and private key do not match. ' .
                'Ensure you are using the key that was used to generate the CSR for this certificate.'
            );
        }
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
     * Port is stripped — Apple rejects domainName/initiativeContext values that include a port.
     */
    private function getDomainFromRequest(): string
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : parse_url($this->validationUrl, PHP_URL_HOST);

        // Strip port (e.g. yourstore.com:443 → yourstore.com)
        return strtolower(explode(':', $host)[0]);
    }
}

