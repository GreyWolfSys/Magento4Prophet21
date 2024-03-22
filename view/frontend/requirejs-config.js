var config = {
    map: {
        '*': {
            'Magento_Tax/js/view/checkout/cart/totals/grand-total':
                'Altitude_P21/js/view/checkout/cart/totals/grand-total',
            'Magento_Tax/js/view/checkout/summary/grand-total':
                'Altitude_P21/js/view/checkout/summary/grand-total',
            'Magento_Checkout/template/shipping.html':
                'Altitude_P21/template/shipping.html',
            'Magento_Tax/js/view/checkout/cart/totals/grand-total':
                'Altitude_P21/js/view/checkout/cart/totals/grand-total',
            'Magento_Tax/js/view/checkout/summary/grand-total':
                'Altitude_P21/js/view/checkout/summary/grand-total'
        }
    },
    config: {
        mixins: {
            'Magento_ConfigurableProduct/js/configurable': {
                'Altitude_P21/js/model/skuswitch': true
            },
            'Magento_Swatches/js/swatch-renderer': {
                'Altitude_P21/js/model/swatch-skuswitch': true
            }
        }
    }
};
