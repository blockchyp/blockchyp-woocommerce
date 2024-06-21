var blockchyp_enrolled = false;

jQuery(document).ready(function() {
    console.log('BlockChyp Params: ', blockchyp_params);
    var options = {
        postalCode: false
    };

    tokenizer.gatewayHost = blockchyp_params.gatewayHost;
    tokenizer.testGatewayHost = blockchyp_params.testGatewayHost;
    tokenizer.render(blockchyp_params.tokenizingKey, blockchyp_params.testmode, 'secure-input', options);
});

jQuery('form.woocommerce-checkout').on('checkout_place_order', function (e) {
    var t = e.target;
    var self = this;
    var bcSelected = jQuery('#payment_method_blockchyp').is(':checked');
    if (!bcSelected) {
        return true;
    }
    var tokenInput = jQuery('#blockchyp_token').val();
    if (tokenInput && blockchyp_enrolled) {
        return true;
    }
    if (!blockchyp_enrolled) {
        var tokenInput = jQuery('#blockchyp_token').val();
        var cardholder = jQuery('#blockchyp_cardholder').val();
        var postalCode = jQuery('#blockchyp_postalcode');
        var postalCodeValue = '';
        if (!postalCode) {
            postalCode = jQuery('#billing_postcode');
        }
        if (postalCode) {
            postalCodeValue = postalCode.val();
        }
        if (tokenInput) {
            return true;
        }
        if (!tokenInput) {
            e.preventDefault();
            var req = {
                test: blockchyp_params.testmode,
                cardholderName: cardholder
            };
            if (postalCodeValue) {
                req.postalCode = postalCodeValue.split('-')[0];
            }
            tokenizer.tokenize(blockchyp_params.tokenizingKey, req)
            .then(function (response) {
                if (response.data.success) {
                    jQuery('#blockchyp_token').val(response.data.token);
                    if (!response.data.token) {
                        jQuery( document.body ).trigger( 'checkout_error' );
                        blockchyp_enrolled = false;
                        return;
                    }
                    blockchyp_enrolled = true;
                    jQuery('form.woocommerce-checkout').submit();
                }
            })
            .catch(function (error) {
                jQuery( document.body ).trigger( 'checkout_error' );
                blockchyp_enrolled = false;
                console.log(error);
            });
        }
    }

    return false;
});
