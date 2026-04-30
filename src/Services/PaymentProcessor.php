<?php

namespace Amzad\ApplePayKnet\Services;

use Amzad\ApplePayKnet\Exceptions\KnetException;
use Amzad\ApplePayKnet\Models\Transaction;

class PaymentProcessor
{
    /** @var KnetGateway */
    private $knet;

    /** @var string */
    private $currency;

    /** @var bool */
    private $logTransactions;

    public function __construct(KnetGateway $knet, string $currency, bool $logTransactions)
    {
        $this->knet            = $knet;
        $this->currency        = $currency;
        $this->logTransactions = $logTransactions;
    }

    /**
     * Charge a customer via Apple Pay + KNET.
     *
     * Forwards the encrypted Apple Pay token directly to KNET, which handles
     * its own token decryption. No server-side decryption is performed here.
     *
     * @param  float       $amount          Amount in KWD (e.g. 5.250)
     * @param  string      $orderId         Your unique order reference
     * @param  array       $encryptedToken  Full Apple Pay payment token from onpaymentauthorized
     * @param  array|null  $billingContact  Optional billing contact from Apple Pay
     * @return array                        ['success' => true, 'transactionId' => '...', 'authCode' => '...']
     *
     * @throws KnetException
     */
    public function charge(
        float $amount,
        string $orderId,
        array $encryptedToken,
        ?array $billingContact = null
    ): array {
        // Convert KWD → fils (1 KWD = 1000 fils)
        $amountInFils = (int) round($amount * 1000);

        $appleTransactionId = $encryptedToken['paymentData']['header']['transactionId']
            ?? $encryptedToken['header']['transactionId']
            ?? null;

        $transaction = null;

        if ($this->logTransactions) {
            $transaction = Transaction::create([
                'order_id'            => $orderId,
                'amount'              => $amountInFils,
                'currency'            => $this->currency,
                'apple_transaction_id' => $appleTransactionId,
                'status'              => 'pending',
            ]);
        }

        try {
            $response = $this->knet->authorize([
                'Amount'      => $amountInFils,
                'Currency'    => $this->currency,
                'OrderId'     => $orderId,
                'Token'       => $encryptedToken,
                'BillingContact' => $billingContact,
            ]);

            if ($transaction) {
                $transaction->update([
                    'knet_transaction_id' => $response['TransactionId'] ?? null,
                    'status'              => 'authorized',
                    'response_code'       => $response['ResponseCode'] ?? null,
                    'auth_code'           => $response['AuthCode'] ?? null,
                    'raw_response'        => $response,
                ]);
            }

            return [
                'success'       => true,
                'transactionId' => $response['TransactionId'] ?? null,
                'authCode'      => $response['AuthCode'] ?? null,
            ];
        } catch (KnetException $e) {
            if ($transaction) {
                $transaction->update([
                    'status'        => 'failed',
                    'response_code' => $e->getResponseCode(),
                    'raw_response'  => ['error' => $e->getMessage()],
                ]);
            }

            throw $e;
        }
    }
}
