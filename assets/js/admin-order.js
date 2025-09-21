(function ($) {
    'use strict';

    const settings = window.uaShippingProAdmin || {};

    function createTtn($button) {
        const orderId = $button.data('order-id');
        if (!orderId) {
            return;
        }

        const $field = $('#ua_shipping_ttn');
        const defaultLabel = $button.data('default-label') || $button.text();

        $button.prop('disabled', true).text(settings.messages.creating || '...');

        $.post(settings.ajaxUrl, {
            action: 'ua_shipping_pro_create_ttn',
            nonce: settings.nonce,
            order_id: orderId,
        })
            .done((response) => {
                if (response && response.success) {
                    if (response.data && response.data.ttn) {
                        $field.val(response.data.ttn);
                    }
                    window.alert(response.data && response.data.message ? response.data.message : settings.messages.created);
                } else {
                    const message = response && response.data && response.data.message
                        ? response.data.message
                        : settings.messages.error;
                    window.alert(message);
                }
            })
            .fail(() => {
                window.alert(settings.messages.error);
            })
            .always(() => {
                $button.prop('disabled', false).text(defaultLabel);
            });
    }

    function copyTtn($button) {
        const target = $button.data('target');
        const $field = $(target);
        if (!$field.length) {
            return;
        }

        const value = $field.val();
        if (!value) {
            return;
        }

        const notifySuccess = () => window.alert(settings.messages.copySuccess || 'Copied');
        const notifyFail = () => window.alert(settings.messages.copyFail || 'Copy failed');

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(notifySuccess).catch(notifyFail);
            return;
        }

        $field[0].select();
        try {
            document.execCommand('copy');
            notifySuccess();
        } catch (e) {
            notifyFail();
        }
    }

    $(document).on('click', '.ua-shipping-pro-create', function (event) {
        event.preventDefault();
        createTtn($(this));
    });

    $(document).on('click', '.ua-shipping-pro-copy', function (event) {
        event.preventDefault();
        copyTtn($(this));
    });
})(jQuery);
