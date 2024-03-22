define([
    'ko',
    'jquery',
    'uiComponent',
    'mage/storage',
    'mage/url'
], function (ko, $, Component, storage, urlBuilder) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Altitude_P21/checkout/payment-message'
        },
        getPaymentMessage: function () {
            var serviceUrl = urlBuilder.build('altitudep21/checkout/message');
            storage.post(
                serviceUrl,
                JSON.stringify({ payment: payment })
            ).done(
                function (response) {
                    if (response) {
                        $(".gw-payment-message").html(response.message);
                    }
                }
            );
        }
    });
});
