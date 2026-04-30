<?php

namespace Amzad\ApplePayKnet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'          => ['required', 'numeric', 'min:0.001'],
            'orderId'         => ['required', 'string', 'max:255'],
            'token'           => ['required', 'array'],
            'token.paymentData' => ['required'],
            'token.header'    => ['required', 'array'],
            'token.signature' => ['required', 'string'],
            'token.version'   => ['required', 'string', 'in:EC_v1,EC_v2'],
            'billingContact'  => ['sometimes', 'nullable', 'array'],
        ];
    }
}
