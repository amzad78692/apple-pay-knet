@props([
    'amount',
    'reference',
    'callbackUrl',
    'label' => config('apple-pay-knet.display_name', 'Pay'),
    'currencyCode' => 'KWD',
    'countryCode' => 'KW',
    'paymentGateway' => 'KNET',
    'onSuccess' => 'null',
    'onError' => 'null',
    'onCancel' => 'null',
])

<style>
    apple-pay-button {
        --apple-pay-button-width: 140px;
        --apple-pay-button-height: 30px;
        --apple-pay-button-border-radius: 5px;
        --apple-pay-button-padding: 5px 0px;
        display: initial;
    }

    .apple-pay-knet-button {
        display: none;
    }
</style>

<apple-pay-button buttonstyle="black" type="plain" locale="en" class="apple-pay-knet-button"></apple-pay-button>

@once
    <script src="https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js"></script>
    <script src="{{ asset('vendor/apple-pay-knet/js/apple-pay-handler.js') }}"></script>
@endonce

<script>
    (function() {
        var config = {
            validateMerchantUrl: '{{ route('apple-pay-knet.validate-merchant') }}',
            processPaymentUrl: '{{ route('apple-pay-knet.process-payment') }}',
            amount: '{{ $amount }}',
            reference: '{{ $reference }}',
            callbackUrl: '{{ $callbackUrl }}',
            label: '{{ $label }}',
            currencyCode: '{{ $currencyCode }}',
            countryCode: '{{ $countryCode }}',
            paymentGateway: '{{ $paymentGateway }}',
            csrfToken: '{{ csrf_token() }}',
            onSuccess: {!! $onSuccess !!},
            onError: {!! $onError !!},
            onCancel: {!! $onCancel !!}
        };

        if (window.ApplePayKnet) {
            window.ApplePayKnet.init(config);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                if (window.ApplePayKnet) window.ApplePayKnet.init(config);
            });
        }
    }());
</script>
