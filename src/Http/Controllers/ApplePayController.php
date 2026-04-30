<?php

namespace Amzad\ApplePayKnet\Http\Controllers;

use Illuminate\Routing\Controller;
use Amzad\ApplePayKnet\Http\Requests\ValidateMerchantRequest;
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
     * Called server-side when the browser fires onvalidatemerchant.
     * Returns the opaque merchant session JSON to pass to completeMerchantValidation().
     */
    public function validateMerchant(ValidateMerchantRequest $request): JsonResponse
    {
        try {
            $session = $this->merchantValidator->validate($request->input('validationUrl'));

            return response()->json($session);
        } catch (ApplePayException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Process a payment using the Apple Pay token.
     *
     * Called server-side when the browser fires onpaymentauthorized.
     * Returns a JSON response that the JS uses to call completePayment().
     */
    public function processPayment(ProcessPaymentRequest $request): JsonResponse
    {
        try {
            $result = $this->paymentProcessor->charge(
                (float) $request->input('amount'),
                $request->input('orderId'),
                $request->input('token'),
                $request->input('billingContact')
            );

            return response()->json($result);
        } catch (KnetException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'code'    => $e->getResponseCode(),
            ], 422);
        }
    }
}
