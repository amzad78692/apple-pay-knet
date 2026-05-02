<?php

namespace Amzad\ApplePayKnet\Services;

use Amzad\ApplePayKnet\Exceptions\KnetException;
use Amzad\ApplePayKnet\Models\Transaction;

class PaymentProcessor
{
    /** @var KnetGateway */
    private $knet;

    /** @var bool */
    private $logTransactions;

    public function __construct(KnetGateway $knet, bool $logTransactions)
    {
        $this->knet            = $knet;
        $this->logTransactions = $logTransactions;
    }

    /**
     * Charge a customer via Apple Pay + KNET.
     *
     * Pass the full event.payment object from the Apple Pay JS onpaymentauthorized event.
     * KNET receives the encrypted token directly and handles decryption on their end.
     *
     * @param  string $amount         Payment amount in KWD (e.g. "5.250")
     * @param  string $reference      Your unique order / reference ID (becomes KNET trackid)
     * @param  array  $applePayment   Full event.payment object from Apple Pay JS
     * @return array                  Full KNET response array
     *
     * @throws KnetException
     */
    public function charge(string $amount, string $reference, array $applePayment): array
    {
        $appleToken = $applePayment['token'] ?? $applePayment;

        $transaction = null;

        if ($this->logTransactions) {
            $transaction = Transaction::create([
                'order_id'             => $reference,
                'amount'               => $amount,
                'currency'             => '414',
                'apple_transaction_id' => $appleToken['transactionIdentifier'] ?? null,
                'status'               => 'pending',
            ]);
        }

        try {
            $response = $this->knet->authorize($amount, $reference, $appleToken);

            if ($transaction) {
                $transaction->update([
                    'knet_transaction_id' => $response['tranid']    ?? null,
                    'status'              => 'authorized',
                    'response_code'       => $response['result']    ?? null,
                    'auth_code'           => $response['auth']      ?? null,
                    'raw_response'        => $response,
                ]);
            }

            return $response;
        } catch (KnetException $e) {
            if ($transaction) {
                $transaction->update([
                    'status'       => 'failed',
                    'response_code' => $e->getResponseCode(),
                    'raw_response'  => ['error' => $e->getMessage()],
                ]);
            }

            throw $e;
        }
    }
}

