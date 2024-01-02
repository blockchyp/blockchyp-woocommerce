<?php
/*
Plugin Name: BlockChyp Payment Gateway for WooCommerce
Description: Integrates BlockChyp payment gateway with WooCommerce.
Version: 1.0
*/

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    // WooCommerce is not active
    return;
}

// Include BlockChyp PHP SDK
require_once 'vendor/autoload.php'; // Adjust the path to the autoload.php file

// Import BlockChyp namespace
use BlockChyp\BlockChyp;

// Initialize BlockChyp payment gateway class
add_action('plugins_loaded', 'blockchyp_wc_init');
function blockchyp_wc_init() {
    class WC_BlockChyp extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'blockchyp';
            $this->method_title = 'BlockChyp';
            $this->title = 'BlockChyp Payment Gateway';
            $this->method_description = 'Connects your WooCommerce store with the BlockChyp gateway.';
            $this->has_fields = true;
            
            //Initialize Form Fields
            $this->init_form_fields();
            $this->init_settings();

            // Define settings
            $this->enabled = $this->get_option('enabled');
            $this->testmode = $this->get_option('testmode');
            $this->api_key = $this->get_option('api_key');
            $this->bearer_token = $this->get_option('bearer_token');
            $this->signing_key = $this->get_option('signing_key');
            $this->tokenizing_key = $this->get_option('tokenizing_key');
            $this->gateway_host = $this->get_option('gateway_host');
            $this->test_gateway_host = $this->get_option('test_gateway_host');
            
            $this->supports = ['products', 'refunds'];

            // Hooks
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
            // This one might not be needed
            // add_action('woocommerce_api_blockchyp_payment', [$this, 'blockchyp_payment_process']);

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

        public function init_settings() {
            // This method initializes default values for settings
            $default_settings = array(
                // Define default settings
                'enabled' => 'yes', 
                'testmode' => 'no', 
                'api_key' => '', 
                'bearer_token'=> '',
                'signing_key'=> '',
                'tokenizing_key'=> '',
                'gateway_host'=> '',
                'test_gateway_host'=> '',
            );
    
            foreach ($default_settings as $key => $value) {
                if ($this->get_option($key) === null) {
                    $this->$key = $value;
                    update_option($key, $value);
                } else {
                    $this->$key = $this->get_option($key, $value);
                }
            }
        }    

        public function process_charge($order_id) {
            global $woocommerce;
            $order = wc_get_order($order_id);

            // Process payment using BlockChyp SDK
            BlockChyp::setApiKey($this->api_key);
            BlockChyp::setBearerToken($this->bearer_token);
            BlockChyp::setSigningKey($this->signing_key);

            $request = array(
                'test' => true,
                'terminalName' => 'Test Terminal',
                'amount' => $order->get_total(),
                // Additional transaction details can be added here as needed
                // For instance:
                // 'cardType' => BlockChyp::CARD_TYPE_EBT, // For EBT transactions
                // 'cashBack' => 10.00, // Enable cash back for debit transactions
                // 'manualEntry' => true, // Enable manual card entry
                // 'promptForTip' => true, // Prompt for tips
                // 'enroll' => true, // Enroll the payment method in the token vault inline
                // 'cryptocurrency' => 'BTC', // Switch to cryptocurrency screen (e.g., BTC for Bitcoin)
            );

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
                    return;
                }
            } catch (Exception $e) {
                // Handle exceptions or errors during payment processing
                wc_add_notice('Payment failed: ' . $e->getMessage(), 'error');
                return;
            }
        }

         /**
         * Process a BlockChyp refund.
         * @param int $order_id
         * @param float $amount
         * @param string $reason
         * @param string $transaction_id
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
            ];
        
            // Check if an amount is provided for a partial refund
            if ($amount !== null) {
                $request['amount'] = $amount;
            }
        
            try {
                $response = BlockChyp::refund($request);
        
                // Handle refund response
                if ($response['approved']) {
                    // Update order status or perform any necessary actions for a successful refund
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
    function add_blockchyp_woocommerce($methods) {
        $methods[] = 'WC_Gateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_blockchyp_woocommerce');
}
