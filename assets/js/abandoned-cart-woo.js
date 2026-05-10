/* globals ZaplaneAbCart, jQuery */
(function ($) {
    'use strict';

    var zaplaneAbCartWoo = {

        checkoutData:  {},
        $emailField:   null,
        timer:         null,
        optedOut:      false,
        gdprPushed:    false,
        isBlocks:      false,
        cartStore:     null,
        checkoutStore: null,

        init: function () {
            if (typeof ZaplaneAbCart === 'undefined') {
                return;
            }

            // Block checkout: use WC data store (no DOM selectors needed)
            if (window.wp && window.wp.data && window.wp.data.select('wc/store/cart')) {
                this.isBlocks      = true;
                this.cartStore     = window.wp.data.select('wc/store/cart');
                this.checkoutStore = window.wp.data.select('wc/store/checkout');

                this.prepareCheckoutData();
                this.addGdprMessage();

                var that = this;
                this.timer = setInterval(function () {
                    that.prepareCheckoutData();
                }, 5000);

                return;
            }

            // Classic checkout: use DOM selectors + events
            this.$emailField = $('#billing_email');
            if (!this.$emailField.length) {
                return;
            }

            this.addGdprMessage();

            var that = this;

            setTimeout(function () {
                that.prepareCheckoutData();
                that.timer = setInterval(function () {
                    that.prepareCheckoutData();
                }, 15000);
            }, 2000);

            $(document.body).on('updated_checkout', function () {
                that.prepareCheckoutData();
            });

            $(document).on('blur', '#billing_email, #billing_first_name, #billing_last_name, #billing_phone', function () {
                that.prepareCheckoutData();
            });
        },

        // ── GDPR ────────────────────────────────────────────────────────────────

        addGdprMessage: function () {
            if (!ZaplaneAbCart.is_gdpr_enabled || ZaplaneAbCart.is_gdpr_enabled !== '1') {
                return;
            }
            if (this.gdprPushed || $('#zaplane_ab_gdpr_msg').length) {
                return;
            }

            var $anchor = null;

            if (this.isBlocks) {
                // try the block email wrapper, fall back to the input's parent div
                $anchor = $('.wc-block-components-address-form__email');
                if (!$anchor.length) {
                    $anchor = $('input[name="contact_email"]').parent();
                }
                if (!$anchor.length) {
                    var that = this;
                    setTimeout(function () { that.addGdprMessage(); }, 2000);
                    return;
                }
            }

            var msg     = ZaplaneAbCart.gdpr_message || 'By continuing, you agree that we may save your cart data.';
            var noThanks= '<a href="#" id="zaplane_ab_opt_out" style="font-size:small;margin-left:6px;color:inherit;text-decoration:underline;">No thanks</a>';

            var dom = document.createElement('p');
            dom.id        = 'zaplane_ab_gdpr_msg';
            dom.innerHTML = '<label style="font-size:small;font-weight:normal;">' + msg + '</label>' + noThanks;

            if (this.isBlocks) {
                dom.className          = 'wc-block-components-checkout-step__description zaplane-gdpr-msg';
                dom.style.marginTop    = '10px';
                dom.style.marginBottom = '0';
                $anchor.after(dom);
            } else {
                dom.className = 'form-row form-row-wide zaplane-gdpr-msg';
                this.$emailField.closest('.form-row').after(dom);
            }

            this.gdprPushed = true;
            this.handleOptOut();
        },

        handleOptOut: function () {
            var $link = $('#zaplane_ab_opt_out');
            var that  = this;

            $link.on('click', function (e) {
                e.preventDefault();
                $link.css('cursor', 'not-allowed');

                that.ajaxRequest(
                    { nonce: ZaplaneAbCart.nonce },
                    that.getAjaxUrl(ZaplaneAbCart.optout_action)
                ).then(function (response) {
                    if (response.success) {
                        that.optedOut = true;
                        clearInterval(that.timer);
                        $('#zaplane_ab_gdpr_msg')
                            .empty()
                            .append('<span style="font-size:small;">You have opted out.</span>')
                            .delay(2000).fadeOut();
                    }
                }).catch(function () {});
            });
        },

        // ── Data collection ──────────────────────────────────────────────────────

        prepareCheckoutData: function () {
            if (this.optedOut) {
                return false;
            }
            return this.isBlocks
                ? this.prepareBlocksData()
                : this.prepareClassicData();
        },

        prepareBlocksData: function () {
            var cartData       = this.cartStore.getCartData();
            var billingAddress = cartData.billingAddress  || {};
            var shippingAddress= cartData.shippingAddress || {};
            var isSameAddress  = this.checkoutStore.getUseShippingAsBilling
                ? this.checkoutStore.getUseShippingAsBilling()
                : true;
            var orderNotes     = this.checkoutStore.getOrderNotes
                ? this.checkoutStore.getOrderNotes()
                : '';

            var email = billingAddress.email;
            if (!email || !this.validateEmail(email)) {
                return false;
            }

            var data = {
                billing_email:     email,
                billingAddress:    billingAddress,
                shippingAddress:   shippingAddress,
                differentShipping: isSameAddress ? 'no' : 'yes',
                order_comments:    orderNotes,
            };

            // drop falsy top-level values
            data = Object.fromEntries(
                Object.entries(data).filter(function (e) { return e[1]; })
            );

            if (JSON.stringify(this.checkoutData) === JSON.stringify(data)) {
                return false;
            }

            this.checkoutData = data;
            this.sendCartUpdate();
        },

        prepareClassicData: function () {
            if (!this.$emailField.length) {
                return false;
            }
            if (this.$emailField.is(':focus')) {
                return false;
            }

            var email = this.$emailField.val();
            if (!email || !this.validateEmail(email)) {
                return false;
            }

            var differentShipping = $('#ship-to-different-address-checkbox').is(':checked');

            var data = {
                billing_email: email,
                billingAddress: {
                    first_name: $('#billing_first_name').val() || '',
                    last_name:  $('#billing_last_name').val()  || '',
                    company:    $('#billing_company').val()    || '',
                    country:    $('#billing_country').val()    || '',
                    address_1:  $('#billing_address_1').val()  || '',
                    address_2:  $('#billing_address_2').val()  || '',
                    city:       $('#billing_city').val()       || '',
                    state:      $('#billing_state').val()      || '',
                    postcode:   $('#billing_postcode').val()   || '',
                    phone:      $('#billing_phone').val()      || '',
                },
                differentShipping: differentShipping ? 'yes' : 'no',
                order_comments:    $('#order_comments').val()                     || '',
                shipping_method:   $('input[name="shipping_method[0]"]:checked').val() || '',
                payment_method:    $('input[name="payment_method"]:checked').val()     || '',
            };

            if (differentShipping) {
                data.shippingAddress = {
                    first_name: $('#shipping_first_name').val() || '',
                    last_name:  $('#shipping_last_name').val()  || '',
                    company:    $('#shipping_company').val()    || '',
                    country:    $('#shipping_country').val()    || '',
                    address_1:  $('#shipping_address_1').val()  || '',
                    address_2:  $('#shipping_address_2').val()  || '',
                    city:       $('#shipping_city').val()       || '',
                    state:      $('#shipping_state').val()      || '',
                    postcode:   $('#shipping_postcode').val()   || '',
                };
            }

            if (JSON.stringify(this.checkoutData) === JSON.stringify(data)) {
                return false;
            }

            this.checkoutData = data;
            this.sendCartUpdate();
        },

        // ── AJAX ─────────────────────────────────────────────────────────────────

        sendCartUpdate: function () {
            this.ajaxRequest({
                nonce:           ZaplaneAbCart.nonce,
                billing_email:   this.checkoutData.billing_email,
                billingAddress:  JSON.stringify(this.checkoutData.billingAddress  || {}),
                shippingAddress: JSON.stringify(this.checkoutData.shippingAddress || {}),
                order_comments:  this.checkoutData.order_comments || '',
            }, this.getAjaxUrl(ZaplaneAbCart.update_action));
        },

        getAjaxUrl: function (action) {
            return ZaplaneAbCart.wc_ajaxurl.replace('%%endpoint%%', action);
        },

        ajaxRequest: function (data, url) {
            data.query_timestamp = Date.now();
            return new Promise(function (resolve, reject) {
                $.ajax({
                    type: 'POST',
                    url: url,
                    data: data,
                    success: function (response) { resolve(response); },
                    error:   function (error)    { reject(error); }
                });
            });
        },

        validateEmail: function (email) {
            return /^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]{2,7}$/.test(email);
        }
    };

    $(document).ready(function () {
        zaplaneAbCartWoo.init();
    });

})(jQuery);
