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


function declare_cart_checkout_blocks_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

// Initialize BlockChyp payment gateway class
add_action('plugins_loaded', 'blockchyp_wc_init');
function blockchyp_wc_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_BlockChyp_Gateway extends WC_Payment_Gateway {
        private $testmode;
        private $api_key;
        private $bearer_token;
        private $signing_key;
        private $tokenizing_key;
        private $gateway_host;
        private $test_gateway_host;

        public function __construct() {
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
            // 'preorders'???
            // 'default_credit_card_form'

            // Print out settings
            // $this->print_settings();
        

            // Hooks
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

            // Add a new action to register the payment method using blocks-registry
            add_action('woocommerce_blocks_loaded', [$this, 'blockchyp_register_payment_method_block']);
            // add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
            
            // This one might not be needed
            // add_action('woocommerce_api_blockchyp_payment', [$this, 'blockchyp_payment_process']);

        }

        public function print_settings() {
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

        function blockchyp_register_payment_method_block() {
            if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                return;
            }

            require_once plugin_dir_path(__FILE__) . 'class-wc-blockchyp-blocks-support.php';

            add_action('woocommerce_blocks_payment_method_type_registration', function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                // Create a new instance of the WC_BlockChyp_Blocks_Support
                $payment_method_registry->register(new WC_BlockChyp_Blocks_Support());
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
                // 'render_postalcode' => [
                //     'title' => __('Postal Code Field', 'blockchyp-woocommerce'),
                //     'type' => 'checkbox',
                //     'label' => __(
                //         'Add Postal Code Field',
                //         'blockchyp-woocommerce'
                //     ),
                //     'default' => 'no',
                //     'description' => __(
                //         'If your checkout page doesn\'t include a billing address, check this box to add a billing postal code to the payment page'
                //     ),
                // ],
            ];
        }

        public function payment_fields() {

            // ob_start();
            // echo 'You have entered the payment fields function';
            ?>
            <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
                <?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>

                <div class="blockchyp-payment-form">
                    <label for="blockchyp-card-number"><?php esc_html_e( 'Card Number:', 'woocommerce-gateway-blockchyp' ); ?></label>
                    <input type="text" id="blockchyp-card-number" name="blockchyp-card-number" placeholder="<?php esc_attr_e( 'Enter card number', 'woocommerce-gateway-blockchyp' ); ?>">
                </div>

                <div class="blockchyp-payment-form">
                    <label for="blockchyp-cardholder"><?php esc_html_e( 'Cardholder Name:', 'woocommerce-gateway-blockchyp' ); ?></label>
                    <input type="text" id="blockchyp-cardholder" name="blockchyp-cardholder" placeholder="<?php esc_attr_e( 'Enter cardholder name', 'woocommerce-gateway-blockchyp' ); ?>">
                </div>

                <div class="blockchyp-payment-form">
                    <label for="blockchyp-expiration"><?php esc_html_e( 'Expiration Date:', 'woocommerce-gateway-blockchyp' ); ?></label>
                    <input type="text" id="blockchyp-expiration" name="blockchyp-expiration" placeholder="<?php esc_attr_e( 'MM/YYYY', 'woocommerce-gateway-blockchyp' ); ?>">
                </div>

                <div class="blockchyp-payment-form">
                    <label for="blockchyp-cvv"><?php esc_html_e( 'CVV:', 'woocommerce-gateway-blockchyp' ); ?></label>
                    <input type="text" id="blockchyp-cvv" name="blockchyp-cvv" placeholder="<?php esc_attr_e( 'Enter CVV', 'woocommerce-gateway-blockchyp' ); ?>">
                </div>

             <?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
             <?php
            // ob_end_flush();
        }
        
        /**
         * Process a BlockChyp charge.
         * @param int $order_id
         * @return array
         **/
        public function process_payment($order_id) {
            global $woocommerce;
            $order = wc_get_order($order_id);

            // Process payment using BlockChyp SDK
            BlockChyp::setApiKey($this->api_key);
            BlockChyp::setBearerToken($this->bearer_token);
            BlockChyp::setSigningKey($this->signing_key);
            BlockChyp::setGatewayHost($this->gateway_host);
            BlockChyp::setTestGatewayHost($this->test_gateway_host);

            $address = sanitize_text_field($_POST['billing_address_1']);
            $postcode = sanitize_text_field($_POST['billing_postcode']);
            $token = sanitize_text_field($_POST['blockchyp_token']);
            //$total = $woocommerce->cart->total;

            $request = [
                'token' => $token,
                'amount' => $order->get_total(),
                'test' => $this->testmode,
                'postalCode' => $postcode,
                'address' => $address,
                'transactionRef' => strval($order_id),
            ];

            try {
                // Process payment using BlockChyp SDK
                $response = BlockChyp::charge($request);
        
                // Handle payment response
                if ($response['approved']) {
                    $order->payment_complete();
                    $woocommerce->cart->empty_cart();
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } else {
                    // Handle failed payment
                    wc_add_notice('Payment failed: ' . $response['responseDescription'], 'error');
                    return array(
                        'result' => 'failed' + $response['responseDescription'],
                    );
                }
            } catch (Exception $e) {
                // Handle exceptions or errors during payment processing
                wc_add_notice('Payment failed: ' . $e->getMessage(), 'error');
                return array(
                    'result' => 'failed' + $e->getMessage(),
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
        public function process_refund($order_id, $amount = null, $reason = '') {
            global $woocommerce;
            $order = wc_get_order($order_id);
            $transaction_id = $order->transaction_id;
        
            BlockChyp::setApiKey($this->api_key);
            BlockChyp::setBearerToken($this->bearer_token);
            BlockChyp::setSigningKey($this->signing_key);
        
            $request = [
                'transactionId' => $transaction_id,
                'test' =>$this->testmode,
            ];
        
            // Check if an amount is provided for a partial refund
            if ($amount !== null) {
                $request['amount'] = $amount;
            }
        
            try {
                $response = BlockChyp::refund($request);
        
                // Handle refund response
                if ($response['approved']) {
                    // For example:
                    $order->update_status('refunded', __('Payment refunded via BlockChyp.', 'your-text-domain'));
                    return true;
                } else {
                    // Handle failed refund
                    wc_add_notice('Refund failed: ' . $response['responseDescription'], 'error');
                    return false;
                }
            } catch (Exception $e) {
                // Handle exceptions or errors during refund processing
                wc_add_notice('Refund failed: ' . $e->getMessage(), 'error');
               return false;
            }
        }
        
    }

    // Register the gateway with WooCommerce
    function woocommerce_add_blockchyp($methods) {
        $methods[] = 'WC_BlockChyp_Gateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_blockchyp');
}
