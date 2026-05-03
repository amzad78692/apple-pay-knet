(function (window) {
  "use strict";

  /**
   * ApplePayKnet — Apple Pay on the Web handler for the amzad/apple-pay-knet package.
   *
   * Mirrors the proven apple-pay-php implementation:
   *  - Merchant validation uses server's fixed URL (no validationUrl sent in request)
   *  - The full event.payment object is sent to processPayment
   *  - On success, a hidden form is auto-submitted to the callbackUrl with the KNET response
   *
   * Usage:
   *   window.ApplePayKnet.init({
   *     validateMerchantUrl : '/apple-pay/validate-merchant',
   *     processPaymentUrl   : '/apple-pay/process-payment',
   *     amount              : '5.250',
   *     reference           : 'ORD-001',
   *     callbackUrl         : '/orders/complete',
   *     csrfToken           : '{{ csrf_token() }}',
   *     onSuccess           : function (knetResponse) {},   // optional, fires before redirect
   *     onError             : function (error) {},          // optional
   *     onCancel            : function () {}                // optional
   *   });
   */

  var ApplePayKnet = {
    /**
     * @param {Object} config
     */
    init: function (config) {
      this.config = Object.assign(
        {
          applePayVersion: 3,
          countryCode: "KW",
          currencyCode: "KWD",
          merchantCapabilities: ["supports3DS"],
          supportedNetworks: ["visa", "masterCard", "amex", "discover"],
          paymentGateway: "KNET",
          onSuccess: null,
          onError: null,
          onCancel: null,
        },
        config,
      );

      this._hideButton();

      if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
        this._log("Apple Pay is not available on this device/browser.");
        return;
      }

      this._showButton();
      this._attachClickHandler();
    },

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    _getButton: function () {
      return document.querySelector(".apple-pay-knet-button");
    },

    _showButton: function () {
      var btn = this._getButton();
      if (btn) btn.style.display = "block";
    },

    _hideButton: function () {
      var btn = this._getButton();
      if (btn) btn.style.display = "none";
    },

    _attachClickHandler: function () {
      var self = this;
      var button = this._getButton();
      if (!button) return;

      button.addEventListener("click", function () {
        self._startSession();
      });
    },

    _startSession: function () {
      var cfg = this.config;
      var self = this;

      var paymentRequest = {
        countryCode: cfg.countryCode,
        currencyCode: cfg.currencyCode,
        merchantCapabilities: cfg.merchantCapabilities,
        supportedNetworks: cfg.supportedNetworks,
        total: {
          label: cfg.label || "Your card will be charged",
          amount: String(cfg.amount),
          type: "final",
        },
      };

      var session = new ApplePaySession(cfg.applePayVersion, paymentRequest);

      // ── Merchant validation ──────────────────────────────────────────
      // Uses a GET to the server's validate-merchant endpoint.
      // The server uses its own fixed validation URL from config — no URL
      // is passed from the browser (matching the working implementation).
      session.onvalidatemerchant = function (event) {
        self._log("Validating merchant with server...", event.validationURL);
        fetch(cfg.validateMerchantUrl, {
          headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": cfg.csrfToken || "",
          },
        })
          .then(function (res) {
            return res.json();
          })
          .then(function (data) {
            if (data.status === true) {
              self._log("Merchant validation successful.", data);
              session.completeMerchantValidation(data.response);
            } else {
              self._log("Merchant validation failed", data);
              session.abort();
              self._triggerError(data || "Merchant validation failed");
            }
          })
          .catch(function (err) {
            self._log("Merchant validation error", err);
            session.abort();
            self._triggerError(err);
          });
      };

      // ── Payment authorised ───────────────────────────────────────────
      session.onpaymentauthorized = function (event) {
        var params = new URLSearchParams();
        params.append("amount", String(cfg.amount));
        params.append("reference", String(cfg.reference));
        params.append("payment_gateway", String(cfg.paymentGateway));
        params.append("apple_pay_response", JSON.stringify(event.payment));

        // Include CSRF token for Laravel
        if (cfg.csrfToken) {
          params.append("_token", cfg.csrfToken);
        }

        fetch(cfg.processPaymentUrl, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: params,
        })
          .then(function (res) {
            return res.json();
          })
          .then(function (data) {
            if (data.status === true) {
              session.completePayment(ApplePaySession.STATUS_SUCCESS);

              if (typeof cfg.onSuccess === "function") {
                cfg.onSuccess(data.response);
              }

              // Auto-submit the KNET response fields to the callback URL
              if (cfg.callbackUrl) {
                self._submitForm(data.response, cfg.callbackUrl);
              }
            } else {
              session.completePayment(ApplePaySession.STATUS_FAILURE);
              self._triggerError(data.message || "Payment failed");
            }
          })
          .catch(function (err) {
            session.completePayment(ApplePaySession.STATUS_FAILURE);
            self._triggerError(err);
          });
      };

      // ── Cancel ───────────────────────────────────────────────────────
      session.oncancel = function () {
        if (typeof cfg.onCancel === "function") cfg.onCancel();
      };

      session.begin();
    },

    /**
     * Create a hidden form from dataObject and submit it to actionUrl.
     * This matches the working apple-pay-php behaviour — KNET response
     * fields are POSTed to the merchant's callback page.
     */
    _submitForm: function (dataObject, actionUrl) {
      var form = document.createElement("form");
      form.method = "POST";
      form.action = actionUrl;

      for (var key in dataObject) {
        if (Object.prototype.hasOwnProperty.call(dataObject, key)) {
          var input = document.createElement("input");
          input.type = "hidden";
          input.name = key;
          input.value = dataObject[key];
          form.appendChild(input);
        }
      }

      document.body.appendChild(form);
      form.submit();
    },

    _triggerError: function (error) {
      this._log("Error", error);
      if (typeof this.config.onError === "function") this.config.onError(error);
    },

    _log: function () {
      if (window.console && console.warn) {
        console.warn.apply(
          console,
          ["[ApplePayKnet]"].concat(Array.prototype.slice.call(arguments)),
        );
      }
    },
  };

  window.ApplePayKnet = ApplePayKnet;
})(window);
