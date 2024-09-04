<?php
/*
Plugin Name: BlockChyp Payment Gateway
Plugin URI: https://wordpress.org/plugins/blockchyp-payment-gateway/
Description: Integrates BlockChyp Payment Gateway with WooCommerce.
Author: BlockChyp, Inc.
Author URI: https://www.blockchyp.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Version: 1.3.0
Requires at least: 6.4
Tested up to: 6.5
WC requires at least: 8.5
WC tested up to: 9.0
Text Domain: blockchyp-payment-gateway
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Include BlockChyp PHP SDK
require_once('vendor/autoload.php');

// Import BlockChyp namespace
use BlockChyp\BlockChyp;

/**
 * Required minimums and constants
 */
define('WC_BLOCKCHYP_VERSION', '1.3.0');
define('WC_BLOCKCHYP_MIN_PHP_VER', '7.4');
define('WC_BLOCKCHYP_MIN_WC_VER', '7.4');
define('WC_BLOCKCHYP_FUTURE_MIN_WC_VER', '7.5');
define('WC_BLOCKCHYP_MAIN_FILE', __FILE__);
define('WC_BLOCKCHYP_ABSPATH', __DIR__ . '/');
define(
    'WC_BLOCKCHYP_PLUGIN_URL',
    untrailingslashit(
        plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))
    )
);
define(
    'WC_BLOCKCHYP_PLUGIN_PATH',
    untrailingslashit(plugin_dir_path(__FILE__))
);

/**
 * WooCommerce fallback notice.
 */
function blockchyp_woocommerce_missing_wc_notice()
{
    /* translators: 1. URL link. */
    echo '<div class="error"><p><strong>' .
        sprintf(
            // translators: s. WooCommerce URL link.
            esc_html__(
                'BlockChyp requires WooCommerce to be installed and active. You can download %s here.',
                'blockchyp-woocommerce'
            ),
            '<a href="' . esc_url('https://woocommerce.com/') . '" target="_blank">WooCommerce</a>'
        ) .
        '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 */
function blockchyp_woocommerce_wc_not_supported()
{
    echo '<div class="error"><p><strong>' .
        sprintf(
            // translators: 1. Min WooCommerce version, 2. Current WooCommerce version.
            esc_html__(
                'BlockChyp requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.',
                'blockchyp-woocommerce'
            ),
            esc_html(WC_BLOCKCHYP_MIN_WC_VER),
            esc_html(get_option('woocommerce_version'))
        ) .
        '</strong></p></div>';
}

// Initialize BlockChyp payment gateway class
add_action('plugins_loaded', 'blockchyp_wc_init');
function blockchyp_wc_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'blockchyp_woocommerce_missing_wc_notice');
        return;
    }

    if (version_compare(get_option('woocommerce_version'), WC_BLOCKCHYP_MIN_WC_VER, '<')) {
        add_action('admin_notices', 'blockchyp_woocommerce_wc_not_supported');
        return;
    }

    class WC_BlockChyp_Gateway extends WC_Payment_Gateway
    {
        private $testmode;
        private $preauth_capture_mode;
        private $api_key;
        private $bearer_token;
        private $signing_key;
        private $tokenizing_key;
        private $gateway_host;
        private $test_gateway_host;
        private $render_postalcode;

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
            $this->preauth_capture_mode = $this->settings['preauth_capture_mode'];
            $this->api_key = $this->settings['api_key'];
            $this->bearer_token = $this->settings['bearer_token'];
            $this->signing_key = $this->settings['signing_key'];
            $this->tokenizing_key = $this->settings['tokenizing_key'];
            $this->gateway_host = $this->settings['gateway_host'];
            $this->test_gateway_host = $this->settings['test_gateway_host'];
            $this->render_postalcode = $this->settings['render_postalcode'];

            $this->supports = ['products', 'refunds', 'tokenization', 'add_payment_method'];

            // Hooks
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('before_woocommerce_init', [$this, 'declare_blockchyp_compatibility']);

            add_action('woocommerce_blocks_loaded', [$this, 'woocommerce_blockchyp_block_support']);

            add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);

            add_action('woocommerce_before_checkout_form', [$this, 'add_nonce_to_checkout_form']);

            if ($this->preauth_capture_mode == 'yes') {
                add_action('woocommerce_order_status_processing', [$this, 'capture_payment']);
                add_action('woocommerce_order_status_completed', [$this, 'capture_payment']);
            }

            add_action('woocommerce_order_status_cancelled', [ $this, 'cancel_payment' ]);

        }

        /**
         * Add a nonce to the checkout form.
         */
        public function add_nonce_to_checkout_form()
        {
            wp_nonce_field('process_checkout', 'woocommerce-process-checkout-nonce');
        }

        /**
         * Register the BlockChyp payment method block.
         */
        public function woocommerce_blockchyp_block_support()
        {
            if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                return;
            }

            require_once plugin_dir_path(__FILE__) . 'class-wc-blockchyp-blocks-support.php';

            // Register BlockChyp payment method block using the same action hook as the Stripe registration.
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    // Create a new instance of the WC_BlockChyp_Blocks_Support
                    $container = Automattic\WooCommerce\Blocks\Package::container();
                    $container->register(
                        WC_BlockChyp_Blocks_Support::class,
                        function () {
                            return new WC_BlockChyp_Blocks_Support();
                        }
                    );
                    $payment_method_registry->register(
                        $container->get(WC_BlockChyp_Blocks_Support::class)
                    );
                },
                5
            );
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
                    'description' => __(
                        'Include BlockChyp as a WooCommerce payment option.',
                        'blockchyp-woocommerce'
                    )
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
                        'Connect your WooCommerce store with a BlockChyp test merchant account.',
                        'blockchyp-woocommerce'
                    ),
                ],
                'preauth_capture_mode' => [
                    'title' => __('Preauth/Capture Mode', 'blockchyp-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __(
                        'Use Preauth/Capture Mode',
                        'blockchyp-woocommerce'
                    ),
                    'default' => 'no',
                    'description' => __(
                        'This mode issues a pre-authorization at checkout, the payment is captured at shipment or manually captured later.',
                        'blockchyp-woocommerce'
                    ),
                ],
                'api_key' => [
                    'title' => __('API Key', 'blockchyp-woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __(
                        'Identifies a set of BlockChyp API credentials',
                        'blockchyp-woocommerce'
                    ),
                ],
                'bearer_token' => [
                    'title' => __('Bearer Token', 'blockchyp-woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __(
                        'Secure Bearer Token used to validate a BlockChyp API request.',
                        'blockchyp-woocommerce'
                    ),
                ],
                'signing_key' => [
                    'title' => __('Signing Key', 'blockchyp-woocommerce'),
                    'type' => 'textarea',
                    'desc_tip' => true,
                    'description' => __(
                        'Signing key to be used for creating API request HMAC signatures.',
                        'blockchyp-woocommerce'
                    ),
                ],
                'tokenizing_key' => [
                    'title' => __('Tokenizing Key', 'blockchyp-woocommerce'),
                    'type' => 'textarea',
                    'desc_tip' => true,
                    'description' => __(
                        'Tokenzing key to be used credit card tokenization.',
                        'blockchyp-woocommerce'
                    ),
                ],
                'gateway_host' => [
                    'title' => __('Gateway Host', 'blockchyp-woocommerce'),
                    'type' => 'text',
                    'default' => 'https://api.blockchyp.com',
                    'desc_tip' => true,
                    'description' => __(
                        'BlockChyp Production Gateway',
                        'blockchyp-woocommerce'
                    ),
                ],
                'test_gateway_host' => [
                    'title' => __('Test Gateway Host', 'blockchyp-woocommerce'),
                    'type' => 'text',
                    'default' => 'https://test.blockchyp.com',
                    'desc_tip' => true,
                    'description' => __(
                        'BlockChyp Test Gateway',
                        'blockchyp-woocommerce'
                    ),
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
                        'If your checkout page doesn\'t include a billing address, check this box to add a billing postal code to the payment page',
                        'blockchyp-woocommerce'
                    ),
                ],
            ];
        }

        /**
         * BlockChyp payment fields.
         */
        public function payment_fields()
        {
            ob_start();

            $testmode_str = $this->testmode == 'yes' ? "true" : "false";
            
            ?>
            <script>
                var blockchyp_enrolled = false;
                jQuery(document).ready(function() {
                    var options = {
                        postalCode: false
                    };
                    tokenizer.gatewayHost = "<?php echo esc_js($this->gateway_host); ?>";
                    tokenizer.testGatewayHost = "<?php echo esc_js($this->test_gateway_host); ?>";
                    tokenizer.render("<?php echo esc_js($this->tokenizing_key); ?>", <?php echo esc_js($testmode_str); ?>, 'secure-input', options);
                });
                jQuery('form.woocommerce-checkout').on('checkout_place_order', function (e) {
                    if (!blockchyp_enrolled) {
                        var tokenInput = jQuery('#blockchyp_token');
                        if (tokenInput.val()) {
                            return true;
                        }

                        e.preventDefault();  // Prevent the form from submitting immediately

                        var cardholder = jQuery('#blockchyp_cardholder').val();
                        var postalCode = jQuery('#blockchyp_postalcode').val() || jQuery('#billing_postcode').val() || '';

                        var req = {
                            test: <?php echo esc_js($testmode_str); ?>,
                            cardholderName: cardholder
                        };

                        if (postalCode) {
                            req.postalCode = postalCode.split('-')[0];
                        }

                        tokenizer.tokenize("<?php echo esc_js($this->tokenizing_key); ?>", req)
                        .then(function (response) {
                            if (response.data.success) {
                                jQuery('#blockchyp_token').val(response.data.token);
                                blockchyp_enrolled = true;
                                jQuery('form.woocommerce-checkout').submit();  // Resubmit the form after tokenization
                            } else {
                                jQuery(document.body).trigger('checkout_error');
                                blockchyp_enrolled = false;
                            }
                        })
                        .catch(function (error) {
                            jQuery(document.body).trigger('checkout_error');
                            blockchyp_enrolled = false;
                            console.log(error);
                        });

                        return false;  // Prevent the form from submitting
                    }
                });
                </script>
            <?php

            $this->render_payment_block();
            
            // Render optional postal code field
            if ($this->render_postalcode == 'yes') {
                $this->render_postal_code_field();
            }
           
            ob_end_flush();
        }

        /**
         * Render card input fields.
         */
        private function render_payment_block()
        {
            $paymentBlock = '
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
            ';
            
            // Define a custom allowed HTML tags and attributes array
            $allowed_tags = array(
                'div' => array(
                    'id' => array(),
                    'class' => array(),
                    'style' => array()
                ),
                'label' => array(
                    'class' => array()
                ),
                'input' => array(
                    'class' => array(),
                    'id' => array(),
                    'name' => array(),
                    'type' => array(),
                    'value' => array(),
                    'style' => array(),
                    'maxlength' => array()
                )
            );

            echo wp_kses($paymentBlock, $allowed_tags);
        }

        /**
         * Render optional postal code field.
         */
        private function render_postal_code_field()
        {
            $html = '<div>
                        <label class="blockchyp-label">Postal Code</label>
                        <input class="blockchyp-input" style="width: 100%;" maxlength="5" id="blockchyp_postalcode" name="blockchyp_postalcode"/>
                    </div>';

            // Define a custom allowed HTML tags and attributes array
            $allowed_tags = array(
                'div' => array(
                    'id' => array(),
                    'class' => array(),
                    'style' => array()
                ),
                'label' => array(
                    'class' => array()
                ),
                'input' => array(
                    'class' => array(),
                    'id' => array(),
                    'name' => array(),
                    'type' => array(),
                    'value' => array(),
                    'style' => array(),
                    'maxlength' => array()
                )
            );

            echo wp_kses($html, $allowed_tags);
        }

        /**
         * Outputs BlockChyp payment scripts.
         */
        public function payment_scripts()
        {
            if (is_checkout()) {
                global $wp;
                
                if ('no' === $this->enabled) {
                    return;
                }

                $testmode = $this->testmode == 'yes';

                if ($testmode) {
                    wp_register_script(
                        'blockchyp',
                        $this->test_gateway_host . '/static/js/blockchyp-tokenizer-all.min.js',
                        array(),
                        '1.0.0',
                        true
                    );
                } else {
                    wp_register_script(
                        'blockchyp',
                        $this->gateway_host . '/static/js/blockchyp-tokenizer-all.min.js',
                        array(),
                        '1.0.0',
                        true
                    );
                }
                wp_enqueue_script('blockchyp');
            }
        }

        /**
         * Process a BlockChyp charge.
         * @param int $order_id
         * @return array
         **/
        public function process_payment($order_id)
        {

            $testmode = false;
            if ($this->testmode == 'yes') {
                $testmode = true;
            }

            $preauth_capture_mode = false;
            if ($this->preauth_capture_mode == 'yes') {
                $preauth_capture_mode = true;
            }

            global $woocommerce;
            $order = wc_get_order($order_id);

            // Sanitize the input
            $address = isset($_POST['billing_address_1']) ? sanitize_text_field(wp_unslash($_POST['billing_address_1'])) : '';
            $postcode = isset($_POST['billing_postcode']) ? sanitize_text_field(wp_unslash($_POST['billing_postcode'])) : '';
            $cardholder = isset($_POST['blockchyp_cardholder']) ? sanitize_text_field(wp_unslash($_POST['blockchyp_cardholder'])) : '';
            $token = isset($_POST['blockchyp_token']) ? sanitize_text_field(wp_unslash($_POST['blockchyp_token'])) : '';

            // Validate postal code
            if (!is_numeric($postcode)) {
                wc_add_notice('Invalid postcode. Please enter a valid postcode.', 'error');
            }

            // Validate cardholder name (letters, spaces, dots, and dashes only)
            if (!preg_match('/^[a-zA-Z\s\.\-]+$/', $cardholder)) {
                wc_add_notice('Invalid cardholder name. Please enter a valid cardholder name.', 'error');
            }

            // Validate address (letters, numbers, spaces, and dashes only)
            if (!preg_match('/^[a-zA-Z0-9\s\.\-]+$/', $address)) {
                wc_add_notice('Invalid address. Please enter a valid address.', 'error');
            }

            // Validate token (letters and numbers only)
            if (!preg_match('/^[a-zA-Z0-9]+$/', $token)) {
                wc_add_notice('Invalid token. Please enter a valid token.', 'error');
            }

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
                'cardholder' => $cardholder,
                'transactionRef' => strval($order_id),
            ];

            try {
                // If the preauth/capture mode is enabled, preauth at checkout and capture later
                if ($preauth_capture_mode) {
                    // Process payment using BlockChyp SDK:  Preauth/Capture model
                    $response = BlockChyp::preauth($request);

                    // Handle payment response
                    if ($response['approved'] || $response['success']) {
                        if (isset($response["transactionId"])) {
                            $transactionId = $response["transactionId"];
                            $order->set_transaction_id($transactionId);
                        } else {
                            // Handle the case where transactionId is not set in the response
                            error_log('Transaction ID not found in the payment response.');
                            return array(
                                'result' => 'failed' . $response['responseDescription'],
                                'redirect' => $this->get_return_url($order)
                            );
                        }

                        $order->update_meta_data('_blockchyp_payment_captured', 'no');
                        $order->update_status('on-hold', 'Order status was set to On Hold due to a pre-authorization.');
                        $order->save();

                        $woocommerce->cart->empty_cart();

                        $message = sprintf(
                            'BlockChyp Preauth successful.<br/>'
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


                        $order->add_order_note($message);

                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order)
                        );
                    } else {
                        // Handle failed payment
                        error_log('Payment Failed: ' . $response['responseDescription']);
                        return array(
                            'result' => 'failed' . $response['responseDescription'],
                            'redirect' => $this->get_return_url($order)
                        );
                    }
                } else {
                    // Process payment using BlockChyp SDK: Default Charge (Combined Auth/Capture)
                    $response = BlockChyp::charge($request);
                    
                    // Handle payment response
                    if ($response['approved'] || $response['success']) {
                        if (isset($response["transactionId"])) {
                            $transactionId = $response["transactionId"];
                        } else {
                            // Handle the case where transactionId is not set in the response
                            error_log('Transaction ID not found in the payment response.');
                            return array(
                                'result' => 'failed' . $response['responseDescription'],
                                'redirect' => $this->get_return_url($order)
                            );
                        }

                        $order->payment_complete($transactionId);
                        $order->update_meta_data('_blockchyp_payment_captured', 'yes');
                        $order->save();

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

                        $order->add_order_note($message);

                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order)
                        );
                    } else {
                        // Handle failed payment
                        error_log('Payment Failed: ' . $response['responseDescription']);
                        return array(
                            'result' => 'failed' . $response['responseDescription'],
                            'redirect' => $this->get_return_url($order)
                        );
                    }
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
         * Process a BlockChyp Capture.
         * @param int $order_id
         * @return array
         **/
        public function capture_payment($order_id)
        {

            if ($this->preauth_capture_mode == 'yes') {

                $order = wc_get_order($order_id);
                $transaction_id = $order->get_transaction_id();
                $amount = $order->get_total();
                $payment_captured = $order->get_meta('_blockchyp_payment_captured', true);

                if ($payment_captured == 'yes') {
                    error_log(print_r('Payment already captured.', true));

                    return true;
                }

                $testmode = false;
                if ($this->testmode == 'yes') {
                    $testmode = true;
                }

                BlockChyp::setApiKey($this->api_key);
                BlockChyp::setBearerToken($this->bearer_token);
                BlockChyp::setSigningKey($this->signing_key);
                BlockChyp::setGatewayHost($this->gateway_host);
                BlockChyp::setTestGatewayHost($this->test_gateway_host);

                $request = [
                    'test' => $testmode,
                    'amount' => $amount,
                    'transactionId' => $transaction_id,
                ];

                try {
                    $response = BlockChyp::capture($request);

                    // Handle capture response
                    if ($response['approved']) {
                        $order->update_meta_data('_blockchyp_payment_captured', 'yes');
                        $order->save();
                        $order->add_order_note(sprintf("BlockChyp Capture Approved.<br/>Auth Code: %s", $response["authCode"]));
                        return true;
                    } else {
                        // Handle failed capture
                        error_log('Capture Failed: ' . $response['responseDescription']);
                        $order->add_order_note(sprintf("BlockChyp Capture Failed.<br/>Response Description: %s", $response["responseDescription"]));
                        return false;
                    }
                } catch (Exception $e) {
                    // Handle exceptions or errors during capture processing
                    error_log('Capture Failed: ' . $response['responseDescription']);
                    $order->add_order_note(sprintf("BlockChyp Capture Failed.<br/>Error: %s", $e->getMessage()));
                    return false;
                }
            }

        }

        /**
         * Cancel a BlockChyp order.
         * @param int $order_id
         * @return boolean
         **/
        public function cancel_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order_total = $order->get_total();
            $total_refunded = $order->get_total_refunded();
            $net_payment = number_format($order_total - $total_refunded, 2);

            // If the order is already refunded, return true
            if ($net_payment == '0.00') {
                $order->add_order_note(sprintf("BlockChyp Order has already been refunded."));
                return true;
            }
            
            $this->process_refund($order_id, $net_payment, '');
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
            
            $order = wc_get_order($order_id);
            $transaction_id = $order->get_transaction_id();
            $payment_captured = $order->get_meta('_blockchyp_payment_captured', true);

            $testmode = false;
            if ($this->testmode == 'yes') {
                $testmode = true;
            }

            $preauth_capture_mode = false;
            if ($this->preauth_capture_mode == 'yes') {
                $preauth_capture_mode = true;
            }

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

                if ($preauth_capture_mode) {

                    if ($payment_captured == 'yes') {
                       
                        $response = BlockChyp::refund($request);

                        // Handle refund response
                        if ($response['approved']) {
                            $order->add_order_note(sprintf("BlockChyp Refund Approved.<br/>Amount: %s<br/>Auth Code: %s", $amount, $response["authCode"]));
                            return true;
                        } else {
                            // Handle failed refund
                            error_log('Refund Failed: ' . $response['responseDescription']);
                            $order->add_order_note(sprintf("BlockChyp Refund Failed.<br/>Response Description: %s", $response["responseDescription"]));
                            return false;
                        }
                    } else {
                        // If the payment is not captured, void the preauth instead of refunding
                        $response = BlockChyp::void($request);

                        // Handle void response
                        if ($response['approved']) {
                            $order->add_order_note(sprintf("BlockChyp Payment Voided.<br/>Amount: %s<br/>Auth Code: %s", $amount, $response["authCode"]));
                            return true;
                        } else {
                            // Handle failed void
                            error_log('Void Failed: ' . $response['responseDescription']);
                            $order->add_order_note(sprintf("BlockChyp Void Failed.<br/>Response Description: %s", $response["responseDescription"]));
                            return false;
                        }
                    }
                } else {
                    $response = BlockChyp::refund($request);

                    // Handle refund response
                    if ($response['approved']) {
                        $order->add_order_note(sprintf("BlockChyp Refund Approved.<br/>Amount: %s<br/>Auth Code: %s", $amount, $response["authCode"]));
                        return true;
                    } else {
                        // Handle failed refund
                        error_log('Refund Failed: ' . $response['responseDescription']);
                        $order->add_order_note(sprintf("BlockChyp Refund Failed.<br/>Response Description: %s", $response["responseDescription"]));
                        return false;
                    }
                }
            } catch (Exception $e) {
                // Handle exceptions or errors during refund processing
                wc_add_notice('Refund failed: ' . $e->getMessage(), 'error');
                $order->add_order_note(sprintf("BlockChyp Refund Failed.<br/>Error: %s", $e->getMessage()));
                return false;
            }
        }

        public function declare_blockchyp_compatibility()
        {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
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
