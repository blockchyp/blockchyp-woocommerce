<?php
/*
Plugin Name: BlockChyp Payment Gateway for WooCommerce
Description: Integrates BlockChyp Payment Gateway with WooCommerce.
Version: 2.0
*/

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    // WooCommerce is not active
    return;
}

// Include BlockChyp PHP SDK
// require_once dirname(__FILE__) . '/vendor/autoload.php';

require_once('vendor/autoload.php');

// Import BlockChyp namespace
use BlockChyp\BlockChyp;

function declare_cart_checkout_blocks_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

// Initialize BlockChyp payment gateway class
add_action('plugins_loaded', 'blockchyp_wc_init', 0);
function blockchyp_wc_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // include(plugin_dir_path(__FILE__) . 'blockchyp-woocommerce.php');

    class WC_BlockChyp_Gateway extends WC_Payment_Gateway
    {
        private $testmode;
        private $api_key;
        private $bearer_token;
        private $signing_key;
        private $tokenizing_key;
        private $gateway_host;
        private $test_gateway_host;

        public function __construct()
        {
            $this->id = 'blockchyp';
            $this->method_title = 'BlockChyp';
            $this->title = 'BlockChyp Payment Gateway';
            $this->method_description = 'Connects your WooCommerce store with the BlockChyp Gateway.';
            $this->has_fields = true;

            //Initialize Form Fields
            $this->init_form_fields();
            $this->init_settings();

            // Define settings
            $this->enabled = $this->settings['enabled'];
            $this->testmode = $this->settings['testmode'];
            $this->api_key = $this->settings['api_key'];
            $this->bearer_token = $this->settings['bearer_token'];
            $this->signing_key = $this->settings['signing_key'];
            $this->tokenizing_key = $this->settings['tokenizing_key'];
            $this->gateway_host = $this->settings['gateway_host'];
            $this->test_gateway_host = $this->settings['test_gateway_host'];

            $this->supports = ['products', 'refunds', 'tokenization', 'add_payment_method'];

            // Print out settings
            // $this->print_settings();

            // Hooks
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Add a new action to register the payment method using blocks-registry
            add_action('woocommerce_blocks_loaded', [$this, 'blockchyp_register_payment_method_block']);

            add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        }

        public function print_settings()
        {
            // Array containing all the settings
            $settings = [
                'enabled' => $this->enabled,
                'testmode' => $this->testmode,
                'api_key' => $this->api_key,
                'bearer_token' => $this->bearer_token,
                'signing_key' => $this->signing_key,
                'tokenizing_key' => $this->tokenizing_key,
                'gateway_host' => $this->gateway_host,
                'test_gateway_host' => $this->test_gateway_host,
            ];

            // Print settings
            foreach ($settings as $key => $value) {
                echo $key . ': ' . $value . '<br>';
            }
        }

        function blockchyp_register_payment_method_block()
        {
            if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                return;
            }

            require_once plugin_dir_path(__FILE__) . 'class-wc-blockchyp-blocks-support.php';

            add_action('woocommerce_blocks_payment_method_type_registration', function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                // Create a new instance of the WC_BlockChyp_Blocks_Support
                $payment_method_registry->register(new WC_BlockChyp_Blocks_Support);
            });
        }


        /*
         * Defines the configuration fields needed to setup BlockChyp.
         */
        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'blockchyp-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __(
                        'Enable BlockChyp Gateway',
                        'blockchyp-woocommerce'
                    ),
                    'default' => 'yes',
                    'description' =>
                    'Include BlockChyp as a WooCommerce payment option.',
                ],
                'testmode' => [
                    'title' => __('Test Mode', 'blockchyp-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __(
                        'Use Test Merchant Account',
                        'blockchyp-woocommerce'
                    ),
                    'default' => 'no',
                    'description' => __(
                        'Connect your WooCommerce store with a BlockChyp test merchant account.'
                    ),
                ],
                'api_key' => [
                    'title' => __('API Key', 'blockchyp-woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __(
                        'Identifies a set of BlockChyp API credentials'
                    ),
                ],
                'bearer_token' => [
                    'title' => __('Bearer Token', 'blockchyp-woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __(
                        'Secure Bearer Token used to validate a BlockChyp API request.'
                    ),
                ],
                'signing_key' => [
                    'title' => __('Signing Key', 'blockchyp-woocommerce'),
                    'type' => 'textarea',
                    'desc_tip' => true,
                    'description' => __(
                        'Signing key to be used for creating API request HMAC signatures.'
                    ),
                ],
                'tokenizing_key' => [
                    'title' => __('Tokenizing Key', 'blockchyp-woocommerce'),
                    'type' => 'textarea',
                    'desc_tip' => true,
                    'description' => __(
                        'Tokenzing key to be used credit card tokenization.'
                    ),
                ],
                'gateway_host' => [
                    'title' => __('Gateway Host', 'blockchyp-woocommerce'),
                    'type' => 'text',
                    'default' => 'https://api.blockchyp.com',
                    'desc_tip' => true,
                    'description' => __('BlockChyp Production Gateway'),
                ],
                'test_gateway_host' => [
                    'title' => __('Test Gateway Host', 'blockchyp-woocommerce'),
                    'type' => 'text',
                    'default' => 'https://test.blockchyp.com',
                    'desc_tip' => true,
                    'description' => __('BlockChyp Test Gateway'),
                ],
                'render_postalcode' => [
                    'title' => __('Postal Code Field', 'blockchyp-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __(
                        'Add Postal Code Field',
                        'blockchyp-woocommerce'
                    ),
                    'default' => 'no',
                    'description' => __(
                        'If your checkout page doesn\'t include a billing address, check this box to add a billing postal code to the payment page'
                    ),
                ],
            ];
        }

        public function payment_fields()
        {
            ob_start();

            $this->render_blockchyp_tokenizer_scripts();
            $this->render_payment_block_styling();

            $this->render_payment_block();

            // Render optional postal code field
            if ($this->settings['render_postalcode'] == 'yes') {
                $this->render_postal_code_field();
            }

            ob_end_flush();
        }

        /**
         * Render BlockChyp JavaScript.
         */
        private function render_blockchyp_tokenizer_scripts()
        {
            $testmode = $this->settings['testmode'] == 'yes' ? 'true' : 'false';

            echo <<<EOT
                <script>
                    var blockchyp_enrolled = false;
                    jQuery(document).ready(function() {
                        var options = {
                            postalCode: false
                        };
                        tokenizer.gatewayHost = '{$this->gateway_host}';
                        tokenizer.testGatewayHost = '{$this->test_gateway_host}';
                        tokenizer.render('{$this->tokenizing_key}', {$testmode}, 'secure-input', options);
                    });
                    jQuery('form.woocommerce-checkout').on('checkout_place_order', function (e) {
                        var t = e.target;
                        var self = this;
                        var bcSelected = jQuery('#payment_method_blockchyp').is(':checked');
                        if (!bcSelected) {
                            return true
                        }
                        var tokenInput = jQuery('#blockchyp_token').val();
                        if (tokenInput && blockchyp_enrolled) {
                            return true
                        }
                        if (!blockchyp_enrolled) {
                            var tokenInput = jQuery('#blockchyp_token').val();
                            var cardholder = jQuery('#blockchyp_cardholder').val();
                            var postalCode = jQuery('#blockchyp_postalcode')
                            var postalCodeValue = '';
                            if (!postalCode) {
                                postalCode = jQuery('#billing_postcode')
                            }
                            if (postalCode) {
                                postalCodeValue = postalCode.val();
                            }
                            if (tokenInput) {
                                return true
                            }
                            if (!tokenInput) {
                                e.preventDefault();
                                var req = {
                                    test: {$testmode},
                                    cardholderName: cardholder
                                }
                                if (postalCodeValue) {
                                    req.postalCode = postalCodeValue.split('-')[0];
                                }
                                tokenizer.tokenize('{$this->tokenizing_key}', req)
                                .then(function (response) {
                                    if (response.data.success) {
                                        jQuery('#blockchyp_token').val(response.data.token);
                                        if (!response.data.token) {
                                            jquery( document.body ).trigger( 'checkout_error' );
                                            blockchyp_enrolled = false
                                            return
                                        }
                                        blockchyp_enrolled = true
                                        jQuery('form.woocommerce-checkout').submit()
                                    }
                                })
                                .catch(function (error) {
                                    jquery( document.body ).trigger( 'checkout_error' );
                                    blockchyp_enrolled = false;
                                    console.log(error);
                                })
                            }
                        }

                        return false
                    });
                </script>
            EOT;
        }

        /**
         * Render BlockChyp CSS.
         */
        private function render_payment_block_styling()
        {
            echo <<<EOT
                <style>
                    .blockchyp-input {
                        border: 1px solid #ccc;
                        padding: 3px !important;
                    }
                    .blockchyp-label {
                        display: block;
                        margin-top: 10px;
                    }
                </style>
            EOT;
        }

        /**
         * Render card input fields.
         */
        private function render_payment_block()
        {
            echo <<<EOT
                <div>
                    <label class="blockchyp-label">Card Number</label>
                    <div id="secure-input"></div>
                    <div id="secure-input-error" class="alert alert-danger" style="display: none; color: red;"></div>
                </div>
                <div>
                    <label class="blockchyp-label">Cardholder Name</label>
                    <input class="blockchyp-input" style="width: 100%;" id="blockchyp_cardholder" name="blockchyp_cardholder"/>
                    <input type="hidden" id="blockchyp_token" name="blockchyp_token"/>
                </div>
            EOT;
        }

        /**
         * Render optional postal code field.
         */
        private function render_postal_code_field()
        {
            echo <<<EOT
                <div>
                    <label class="blockchyp-label">Postal Code</label>
                    <input class="blockchyp-input" style="width: 100%;" maxlength="5" id="blockchyp_postalcode" name="blockchyp_postalcode"/>
                </div>
            EOT;
        }

        /**
         * Outputs BlockChyp payment scripts.
         */
        public function payment_scripts()
        {
            global $wp;

            if ('no' === $this->enabled) {
                return;
            }

            $testmode = false;
            if ($this->settings['testmode'] == 'yes') {
                $testmode = true;
            }

            if ($testmode) {
                wp_register_script(
                    'blockchyp',
                    $this->test_gateway_host .
                        '/static/js/blockchyp-tokenizer-all.min.js',
                    array(),
                    '1.0.0',
                    true
                );
            } else {
                wp_register_script(
                    'blockchyp',
                    $this->gateway_host .
                        '/static/js/blockchyp-tokenizer-all.min.js',
                    array(),
                    '1.0.0',
                    true
                );
            }

            wp_enqueue_script('blockchyp');
        }

        /**
         * Process a BlockChyp charge.
         * @param int $order_id
         * @return array
         **/
        public function process_payment($order_id)
        {

            $testmode = false;
            if ($this->settings['testmode'] == 'yes') {
                $testmode = true;
            }

            global $woocommerce;
            // $order = new WC_Order($order_id);
            $order = wc_get_order($order_id);

            $user = wp_get_current_user();
            $address = sanitize_text_field($_POST['billing_address_1']);
            $postcode = sanitize_text_field($_POST['billing_postcode']);
            $cardholder = sanitize_text_field($_POST['blockchyp_cardholder']);
            $token = sanitize_text_field($_POST['blockchyp_token']);
            $total = $woocommerce->cart->total;

            BlockChyp::setApiKey($this->api_key);
            BlockChyp::setBearerToken($this->bearer_token);
            BlockChyp::setSigningKey($this->signing_key);
            BlockChyp::setGatewayHost($this->gateway_host);
            BlockChyp::setTestGatewayHost($this->test_gateway_host);

            $request = [
                'token' => $token,
                'amount' => $total,
                'test' => $testmode,
                'postalCode' => $postcode,
                'address' => $address,
                'transactionRef' => strval($order_id),
            ];

            // $response = [];

            try {
                // Process payment using BlockChyp SDK
                $response = BlockChyp::charge($request);
                // echo $response;

                //Log the response
                error_log(print_r($response, true));

                // Handle payment response
                if ($response['approved'] || $response['success']) {
                    if(isset($response["transactionId"])) {
                        $transactionId = $response["transactionId"];
                    } else {
                        // Handle the case where transactionId is not set in the response
                        wc_add_notice('Transaction ID not found in the payment response.', 'error');
                        return array(
                            'result' => 'failed' . $response['responseDescription'],
                            'redirect' => $this->get_return_url($order)
                        );
                    }

                    $order->payment_complete($transactionId);
                    $woocommerce->cart->empty_cart();

                    $message = sprintf(
                        'BlockChyp payment successful.<br/>'
                                . 'Transaction ID: %s<br/>'
                                . 'Auth Code: %s<br/>'
                                . 'Payment Type: %s (%s)<br/>'
                                . 'AVS Response: %s<br/>'
                                . 'Authorized Amount: %s',
                        $response["transactionId"],
                        $response["authCode"],
                        $response["paymentType"],
                        $response["maskedPan"],
                        $response["avsResponse"],
                        $response["authorizedAmount"]
                    );

                    // Might want to update the status to completed over processing???
                    // $order->update_status('completed');
                    $order->add_order_note($message);
                    
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } else {
                    // Handle failed payment
                    wc_add_notice('Payment failed: ' . $response['responseDescription'], 'error');
                    return array(
                        'result' => 'failed' . $response['responseDescription'],
                        'redirect' => $this->get_return_url($order)
                    );
                }
            } catch (Exception $e) {
                // Handle exceptions or errors during payment processing
                wc_add_notice('Payment failed: ' . $e->getMessage(), 'error');
                return array(
                    'result' => 'failed' . $e->getMessage(),
                    'redirect' => $this->get_return_url($order)
                );
            }
        }

        /**
         * Process a BlockChyp refund.
         * @param int $order_id
         * @param float $amount
         * @param string $reason
         * @return boolean
         **/
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            global $woocommerce;
            $order = wc_get_order($order_id);
            $transaction_id = $order->transaction_id;

            $testmode = false;
            if ($this->settings['testmode'] == 'yes') {
                $testmode = true;
            }

            error_log("OrderId: " . $order_id);
            error_log("Amount: " . $amount);
            error_log("Reason: " . $reason);
            error_log("Transaction ID: " . $transaction_id);
            error_log("Order: " . $order);


            BlockChyp::setApiKey($this->api_key);
            BlockChyp::setBearerToken($this->bearer_token);
            BlockChyp::setSigningKey($this->signing_key);
            BlockChyp::setGatewayHost($this->gateway_host);
            BlockChyp::setTestGatewayHost($this->test_gateway_host);

            $request = [
                'transactionId' => $transaction_id,
                'amount' => $amount,
                'test' => $testmode,
            ];

            try {
                $response = BlockChyp::refund($request);

                // Handle refund response
                if ($response['approved']) {
                    $order->update_status('refunded', __('Payment refunded via BlockChyp.', 'your-text-domain'));
                    $order->add_order_note(sprintf( "BlockChyp refund approved.<br/>Amount: %s<br/>Auth Code: %s", $amount, $response["authCode"]));
                    return true;
                } else {
                    // Handle failed refund
                    wc_add_notice('Refund failed: ' . $response['responseDescription'], 'error');
                    $order->add_order_note(sprintf( "BlockChyp refund failed.<br/>Response Description: %s", $response["responseDescription"]));
                    return false;
                }
            } catch (Exception $e) {
                // Handle exceptions or errors during refund processing
                wc_add_notice('Refund failed: ' . $e->getMessage(), 'error');
                $order->add_order_note(sprintf( "BlockChyp refund failed.<br/>Error: %s", $e->getMessage()));
                return false;
            }
        }
    }

    // Register the gateway with WooCommerce
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_blockchyp');
    function woocommerce_add_blockchyp($methods)
    {
        $methods[] = 'WC_BlockChyp_Gateway';
        return $methods;
    }
}
