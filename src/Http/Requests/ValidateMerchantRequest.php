<?php

namespace Amzad\ApplePayKnet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'validationUrl' => ['required', 'url'],
        ];
    }
}
