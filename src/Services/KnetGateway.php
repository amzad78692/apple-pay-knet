<?php

namespace Amzad\ApplePayKnet\Services;

use Amzad\ApplePayKnet\Exceptions\KnetException;

class KnetGateway
{
    /** @var string */
    private $apiUrl;

    /** @var string */
    private $merchantId;

    /** @var string */
    private $terminalId;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    public function __construct(
        string $apiUrl,
        string $merchantId,
        string $terminalId,
        string $apiKey,
        string $apiSecret
    ) {
        $this->apiUrl     = rtrim($apiUrl, '/');
        $this->merchantId = $merchantId;
        $this->terminalId = $terminalId;
        $this->apiKey     = $apiKey;
        $this->apiSecret  = $apiSecret;
    }

    /**
     * Authorize a payment.
     *
     * @param  array $payload  Fields: Amount (in fils), Currency, OrderId, Token, Timestamp, etc.
     * @return array           KNET response with ResponseCode, TransactionId, AuthCode, etc.
     *
     * @throws KnetException
     */
    public function authorize(array $payload): array
    {
        $payload['TransactionType'] = 'SALE';
        $payload['MerchantId']      = $this->merchantId;
        $payload['TerminalId']      = $this->terminalId;

        return $this->post('/payment/authorize', $payload);
    }

    /**
     * Capture a previously authorized transaction.
     *
     * @throws KnetException
     */
    public function capture(string $transactionId): array
    {
        return $this->post('/payment/capture', [
            'TransactionId' => $transactionId,
            'MerchantId'    => $this->merchantId,
            'TerminalId'    => $this->terminalId,
        ]);
    }

    /**
     * Query the status of a transaction.
     *
     * @throws KnetException
     */
    public function inquiry(string $transactionId): array
    {
        return $this->post('/payment/inquiry', [
            'TransactionId' => $transactionId,
            'MerchantId'    => $this->merchantId,
            'TerminalId'    => $this->terminalId,
        ]);
    }

    /**
     * Send a signed POST request to the KNET API.
     *
     * @throws KnetException
     */
    private function post(string $endpoint, array $payload): array
    {
        $payload['ApiKey']    = $this->apiKey;
        $payload['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
        $payload['Signature'] = $this->sign($payload);

        $body = json_encode($payload);
        $url  = $this->apiUrl . $endpoint;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw KnetException::connectionFailed($curlError);
        }

        if ($httpCode !== 200) {
            throw KnetException::httpError($url, $httpCode);
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw KnetException::connectionFailed('Invalid JSON in KNET response');
        }

        $responseCode = $decoded['ResponseCode'] ?? null;

        if ($responseCode !== '00') {
            throw KnetException::authorizationFailed(
                (string) $responseCode,
                $decoded['ResponseMessage'] ?? 'Unknown error'
            );
        }

        return $decoded;
    }

    /**
     * Generate HMAC-SHA256 signature from the payload.
     * The Signature field itself must not be included when computing the hash.
     */
    private function sign(array $payload): string
    {
        unset($payload['Signature']);

        ksort($payload);

        return hash_hmac('sha256', json_encode($payload), $this->apiSecret);
    }
}
