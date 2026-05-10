/* globals ZaplaneAbCart, jQuery */
(function ($) {
    'use strict';

    if (typeof ZaplaneAbCart === 'undefined') {
        return;
    }

    var abCart = ZaplaneAbCart;
    var debounceTimer = null;
    var gdprAccepted = false;

    function getAjaxUrl(action) {
        return abCart.wc_ajaxurl.replace('%%endpoint%%', action);
    }

    function collectBillingData() {
        var data = {};
        var fields = [
            'first_name', 'last_name', 'email', 'phone',
            'address_1', 'address_2', 'city', 'state', 'postcode', 'country'
        ];
        fields.forEach(function (field) {
            var el = document.getElementById('billing_' + field);
            if (el) {
                data[field] = el.value || '';
            }
        });
        return data;
    }

    function collectShippingData() {
        var data = {};
        var fields = [
            'first_name', 'last_name', 'address_1', 'address_2',
            'city', 'state', 'postcode', 'country'
        ];
        fields.forEach(function (field) {
            var el = document.getElementById('shipping_' + field);
            if (el) {
                data[field] = el.value || '';
            }
        });
        return data;
    }

    function sendCartUpdate() {
        var billingEmail = document.getElementById('billing_email');
        if (!billingEmail || !billingEmail.value) {
            return;
        }
        var email = billingEmail.value.trim();
        if (!email || !isValidEmail(email)) {
            return;
        }

        if (abCart.is_gdpr_enabled === '1' && !gdprAccepted) {
            return;
        }

        var orderNote = document.getElementById('order_comments');

        $.ajax({
            url: getAjaxUrl(abCart.update_action),
            method: 'POST',
            data: {
                nonce: abCart.nonce,
                billing_email: email,
                billingAddress: JSON.stringify(collectBillingData()),
                shippingAddress: JSON.stringify(collectShippingData()),
                order_comments: orderNote ? orderNote.value : ''
            }
        });
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function onFieldChange() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(sendCartUpdate, 2000);
    }

    function addGdprCheckbox() {
        if (abCart.is_gdpr_enabled !== '1') {
            return;
        }
        var msg = abCart.gdpr_message || 'By continuing, you agree that we may save your cart data.';
        var wrapper = $('<div class="zaplane-gdpr-consent" style="margin:10px 0;"></div>');
        var label = $('<label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"></label>');
        var checkbox = $('<input type="checkbox" id="zaplane_gdpr_consent" />');
        label.append(checkbox);
        label.append(document.createTextNode(msg));
        wrapper.append(label);

        var emailField = $('#billing_email').closest('.form-row');
        if (emailField.length) {
            emailField.after(wrapper);
        }

        checkbox.on('change', function () {
            gdprAccepted = $(this).is(':checked');
            if (gdprAccepted) {
                sendCartUpdate();
            } else {
                $.ajax({
                    url: getAjaxUrl(abCart.optout_action),
                    method: 'POST',
                    data: { nonce: abCart.nonce }
                });
            }
        });
    }

    function init() {
        addGdprCheckbox();

        $(document).on('change blur', '#billing_email', onFieldChange);
        $(document).on('change', '#billing_first_name, #billing_last_name, #billing_phone,' +
            '#billing_address_1, #billing_city, #billing_state, #billing_postcode, #billing_country,' +
            '#shipping_first_name, #shipping_last_name, #shipping_address_1,' +
            '#shipping_city, #shipping_state, #shipping_postcode, #shipping_country', onFieldChange);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}(jQuery));
