@props([
    'amount',
    'orderId',
    'label'          => config('apple-pay-knet.display_name', 'Pay'),
    'currencyCode'   => 'KWD',
    'countryCode'    => 'KW',
    'onSuccess'      => 'null',
    'onError'        => 'null',
    'onCancel'       => 'null',
])

<apple-pay-button
    buttonstyle="black"
    type="plain"
    locale="en-US"
    class="apple-pay-knet-button"
    style="display:none; --apple-pay-button-width:200px; --apple-pay-button-height:48px; --apple-pay-button-border-radius:6px;"
></apple-pay-button>

<script>
    (function () {
        var config = {
            validateMerchantUrl : '{{ route('apple-pay-knet.validate-merchant') }}',
            processPaymentUrl   : '{{ route('apple-pay-knet.process-payment') }}',
            amount              : '{{ $amount }}',
            orderId             : '{{ $orderId }}',
            label               : '{{ $label }}',
            currencyCode        : '{{ $currencyCode }}',
            countryCode         : '{{ $countryCode }}',
            csrfToken           : '{{ csrf_token() }}',
            onSuccess           : {!! $onSuccess !!},
            onError             : {!! $onError !!},
            onCancel            : {!! $onCancel !!}
        };

        if (window.ApplePayKnet) {
            window.ApplePayKnet.init(config);
        } else {
            document.addEventListener('DOMContentLoaded', function () {
                if (window.ApplePayKnet) window.ApplePayKnet.init(config);
            });
        }
    }());
</script>

@once
    <script src="https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js" crossorigin="anonymous"></script>
    <script src="{{ asset('vendor/apple-pay-knet/js/apple-pay-handler.js') }}"></script>
@endonce
