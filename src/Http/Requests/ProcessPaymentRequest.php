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
            'amount'             => ['required', 'numeric', 'min:0.001'],
            'reference'          => ['required', 'string', 'max:255'],
            'apple_pay_response' => ['required'],
            'payment_gateway'    => ['sometimes', 'string'],
        ];
    }
}

