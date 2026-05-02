<?php

namespace Amzad\ApplePayKnet\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Amzad\ApplePayKnet\Http\Requests\ProcessPaymentRequest;
use Amzad\ApplePayKnet\Services\MerchantValidator;
use Amzad\ApplePayKnet\Services\PaymentProcessor;
use Amzad\ApplePayKnet\Exceptions\ApplePayException;
use Amzad\ApplePayKnet\Exceptions\KnetException;
use Illuminate\Http\JsonResponse;

class ApplePayController extends Controller
{
    /** @var MerchantValidator */
    private $merchantValidator;

    /** @var PaymentProcessor */
    private $paymentProcessor;

    public function __construct(MerchantValidator $merchantValidator, PaymentProcessor $paymentProcessor)
    {
        $this->merchantValidator = $merchantValidator;
        $this->paymentProcessor  = $paymentProcessor;
    }

    /**
     * Validate the Apple Pay merchant session.
     *
     * Called by JS onvalidatemerchant. Uses the fixed validation URL from config —
     * no URL is taken from the request (matches the proven working implementation).
     */
    public function validateMerchant(Request $request): JsonResponse
    {
        try {
            $session = $this->merchantValidator->validate();

            return response()->json(['status' => true, 'response' => $session]);
        } catch (ApplePayException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Process the Apple Pay payment through KNET.
     *
     * Expects the full event.payment object from the JS onpaymentauthorized event,
     * plus amount and reference. On success returns the full KNET response so the
     * frontend can POST it to the callback URL.
     */
    public function processPayment(ProcessPaymentRequest $request): JsonResponse
    {
        $applePayResponse = $request->input('apple_pay_response');

        // Accept either a JSON string (from form-urlencoded) or already-decoded array
        if (is_string($applePayResponse)) {
            $applePayResponse = json_decode($applePayResponse, true);
        }

        try {
            $response = $this->paymentProcessor->charge(
                (string) $request->input('amount'),
                (string) $request->input('reference'),
                $applePayResponse
            );

            return response()->json(['status' => true, 'response' => $response]);
        } catch (KnetException $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
                'code'    => $e->getResponseCode(),
            ], 422);
        }
    }
}

