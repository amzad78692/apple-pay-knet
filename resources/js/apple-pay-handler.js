;(function (window) {
    'use strict';

    /**
     * ApplePayKnet — lightweight Apple Pay on the Web handler for the amzad/apple-pay-knet package.
     *
     * Usage:
     *   window.ApplePayKnet.init({
     *     validateMerchantUrl : '/apple-pay/validate-merchant',
     *     processPaymentUrl   : '/apple-pay/process-payment',
     *     amount              : '5.250',
     *     orderId             : 'ORD-001',
     *     label               : 'My Store',
     *     currencyCode        : 'KWD',
     *     countryCode         : 'KW',
     *     csrfToken           : 'xxx',
     *     onSuccess           : function (response) {},
     *     onError             : function (error) {},
     *     onCancel            : function () {}
     *   });
     */

    var ApplePayKnet = {

        /**
         * @param {Object} config
         */
        init: function (config) {
            this.config = Object.assign({
                applePayVersion   : 3,
                merchantCapabilities: ['supports3DS'],
                supportedNetworks : ['visa', 'masterCard', 'mada'],
                currencyCode      : 'KWD',
                countryCode       : 'KW',
                onSuccess         : null,
                onError           : null,
                onCancel          : null
            }, config);

            this._hideButton();

            if (!window.ApplePaySession) {
                this._log('Apple Pay is not available (ApplePaySession missing).');
                return;
            }

            if (!ApplePaySession.canMakePayments()) {
                this._log('Apple Pay cannot make payments on this device/browser.');
                return;
            }

            this._showButton();
            this._attachClickHandler();
        },

        // ─────────────────────────────────────────────────────────────────────
        // Private helpers
        // ─────────────────────────────────────────────────────────────────────

        _getButton: function () {
            return document.querySelector('.apple-pay-knet-button');
        },

        _showButton: function () {
            var btn = this._getButton();
            if (btn) btn.style.display = '';
        },

        _hideButton: function () {
            var btn = this._getButton();
            if (btn) btn.style.display = 'none';
        },

        _attachClickHandler: function () {
            var self   = this;
            var button = this._getButton();
            if (!button) return;

            button.addEventListener('click', function () {
                self._startSession();
            });
        },

        _startSession: function () {
            var cfg = this.config;
            var self = this;

            var paymentRequest = {
                countryCode          : cfg.countryCode,
                currencyCode         : cfg.currencyCode,
                merchantCapabilities : cfg.merchantCapabilities,
                supportedNetworks    : cfg.supportedNetworks,
                total                : {
                    label  : cfg.label,
                    amount : String(cfg.amount),
                    type   : 'final'
                }
            };

            var session = new ApplePaySession(cfg.applePayVersion, paymentRequest);

            // ── Merchant validation ──────────────────────────────────────────
            session.onvalidatemerchant = function (event) {
                self._post(cfg.validateMerchantUrl, { validationUrl: event.validationURL })
                    .then(function (merchantSession) {
                        session.completeMerchantValidation(merchantSession);
                    })
                    .catch(function (err) {
                        self._log('Merchant validation failed', err);
                        session.abort();
                        self._triggerError(err);
                    });
            };

            // ── Payment authorised ───────────────────────────────────────────
            session.onpaymentauthorized = function (event) {
                var payload = {
                    amount        : cfg.amount,
                    orderId       : cfg.orderId,
                    token         : event.payment.token,
                    billingContact: event.payment.billingContact || null
                };

                self._post(cfg.processPaymentUrl, payload)
                    .then(function (result) {
                        if (result.success) {
                            session.completePayment(ApplePaySession.STATUS_SUCCESS);
                            if (typeof cfg.onSuccess === 'function') cfg.onSuccess(result);
                        } else {
                            session.completePayment(ApplePaySession.STATUS_FAILURE);
                            self._triggerError(result.error || 'Payment failed');
                        }
                    })
                    .catch(function (err) {
                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                        self._triggerError(err);
                    });
            };

            // ── Cancel / error ───────────────────────────────────────────────
            session.oncancel = function () {
                if (typeof cfg.onCancel === 'function') cfg.onCancel();
            };

            session.begin();
        },

        /**
         * POST JSON with CSRF token.
         *
         * @param  {string} url
         * @param  {Object} data
         * @return {Promise<Object>}
         */
        _post: function (url, data) {
            var cfg = this.config;

            return fetch(url, {
                method : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept'      : 'application/json',
                    'X-CSRF-TOKEN': cfg.csrfToken || ''
                },
                body: JSON.stringify(data)
            }).then(function (response) {
                if (!response.ok) {
                    return response.json().then(function (err) {
                        throw err;
                    });
                }
                return response.json();
            });
        },

        _triggerError: function (error) {
            var cfg = this.config;
            this._log('Apple Pay error', error);
            if (typeof cfg.onError === 'function') cfg.onError(error);
        },

        _log: function () {
            if (window.console && console.warn) {
                console.warn.apply(console, ['[ApplePayKnet]'].concat(Array.prototype.slice.call(arguments)));
            }
        }
    };

    window.ApplePayKnet = ApplePayKnet;

}(window));
