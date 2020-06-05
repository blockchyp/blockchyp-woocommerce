<?php
/**
 * Plugin Name: BlockChyp WooCommerce Plugin
 * Plugin URI: https://wordpress.org/plugins/blockchyp-woocommerce
 * Description: Connect your WooCommerce store with BlockChyp.
 * Author: BlockChyp, Inc.
 * Author URI: https://www.blockchyp.com
 * Version: 1.0.0
 * Requires at least: 4.4
 * Tested up to: 5.4
 * WC requires at least: 3.0
 * WC tested up to: 4.0
 * Text Domain: blockchyp-woocommerce
 * Domain Path: /languages
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/vendor/autoload.php';

use \BlockChyp\BlockChyp;

/**
 * Required minimums and constants
 */
define( 'WC_BLOCKCHYP_VERSION', '4.4.0' );
define( 'WC_BLOCKCHYP_MIN_PHP_VER', '5.6.0' );
define( 'WC_BLOCKCHYP_MIN_WC_VER', '3.0' );
define( 'WC_BLOCKCHYP_FUTURE_MIN_WC_VER', '3.0' );
define( 'WC_BLOCKCHYP_MAIN_FILE', __FILE__ );
define( 'WC_BLOCKCHYP_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_BLOCKCHYP_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * WooCommerce fallback notice.
 *
 * @return string
 */
function blockchyp_woocommerce_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'BlockChyp requires WooCommerce to be installed and active. You can download %s here.', 'blockchyp-woocommerce' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @return string
 */
function blockchyp_woocommerce_wc_not_supported() {
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'BlockChyp requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'blockchyp-woocommerce' ), WC_STRIPE_MIN_WC_VER, WC_VERSION ) . '</strong></p></div>';
}

add_action( 'plugins_loaded', 'blockchyp_woocommerce_init' );

function blockchyp_woocommerce_init() {


	class WC_BlockChyp extends WC_Payment_Gateway {

		public function __construct() {

			 $this->id 		      			 = 'blockchyp';
			 $this->title 						 = 'Credit Card';
			 $this->method_title       = 'BlockChyp';
			 $this->method_description = 'Connects your WooCommerce store with the BlockChyp gateway.';
			 $this->has_fields 	       = true;

			 $this->init_form_fields();
			 $this->init_settings();

			 $this->testmode 	         = $this->settings['testmode'];
			 $this->api_key  		 			 = $this->settings['api_key'];
			 $this->bearer_token  		 = $this->settings['bearer_token'];
			 $this->signing_key  		   = $this->settings['signing_key'];
			 $this->tokenizing_key  	 = $this->settings['tokenizing_key'];
			 $this->gateway_host  		 = $this->settings['gateway_host'];
			 $this->test_gateway_host  = $this->settings['test_gateway_host'];
			 $this->supports           = array(
				 'products',
				 'refunds'
			 );

			 add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));

			 add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		}

		/**
		 * Render the BlockChyp payment form.
		 */
		public function payment_fields() {

			$testmode = 'false';
			if ($this->settings['testmode'] == 'yes') {
				$testmode = 'true';
			}

			ob_start();

			echo '
				<script>
						jQuery(document).ready(function() {
							var options = {
								postalCode: false
							};
							tokenizer.gatewayHost = \'' . $this->gateway_host . '\';
							tokenizer.testGatewayHost = \'' . $this->test_gateway_host . '\';
							tokenizer.render(\'' . $this->tokenizing_key . '\', true, \'secure-input\', options);
						});
						jQuery(\'form.woocommerce-checkout\' ).on(\'click\', \'button[type="submit"][name="woocommerce_checkout_place_order"]\', function (e) {
							var self = this;
							var bcSelected = jQuery(\'#payment_method_blockchyp\').is(\':checked\');
							if (bcSelected) {
								var tokenInput = jQuery(\'#blockchyp_token\').val();
								var cardholder = jQuery(\'#blockchyp_cardholder\').val();
								var postalCode = jQuery(\'#billing_postcode\').val();
								var postalCodeTokens = postalCode.split(\'-\');
								if (!tokenInput) {
									e.preventDefault();
									var req = {
						        test: ' . $testmode . ',
						        cardholderName: cardholder,
						        postalCode: postalCodeTokens[0]
						      }
									jQuery(\'form.woocommerce-checkout\').off();
									tokenizer.tokenize(\'' . $this->tokenizing_key . '\', req)
								    .then(function (response) {
											if (response.data.success) {
									      console.log(JSON.stringify(response.data));
									      jQuery(\'#blockchyp_token\').val(response.data.token);
												jQuery(\'button[type="submit"][name="woocommerce_checkout_place_order"]\').trigger(\'click\');
											}
								    })
								    .catch(function (error) {
								      console.log(error);
								    })
								}
							}
						});
				</script>';
				?>
				<style>
							.blockchyp-input {
											border: 1px solid #ccc;
											padding: 3px !important;
							}
							.blockchyp-label{
											display:block;
											margin-top: 10px;
							}
				</style>
				<div>
			    <label class="blockchyp-label">Card Number</label>
			    <div id="secure-input"></div>
			    <div id="secure-input-error" class="alert alert-danger" style="display: none;"></div>
			  </div>
			  <div>
			    <label class="blockchyp-label">Cardholder Name</label>
			    <input class="blockchyp-input" style="width: 100%;" id="cardholder" name="blockchyp_cardholder"/>
					<input type="hidden" id="blockchyp_token" name="blockchyp_token"/>
			  </div>
			<?php

			ob_end_flush();

		}

		/**
		 * Invoke the BlockChyp API and capture a payment.
		 * @param int $order_id
		 * @return array
		 **/
		function process_payment($order_id) {

			$testmode = false;
			if ($this->settings['testmode'] == 'yes') {
				$testmode = true;
			}

			global $woocommerce;
			$order    = new WC_Order( $order_id );

			$user     = wp_get_current_user();
			$address  = $_POST['billing_address_1'];
			$postcode = $_POST['billing_postcode'];
			$cardholder  = $_POST['blockchyp_cardholder'];
			$token  = $_POST['blockchyp_token'];
			$total    = $woocommerce->cart->total;

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
				'transactionRef' => strval($order_id)
			];

			$response = [];

			try {

				$response = BlockChyp::charge($request);

				if (!$response["success"] || !$response["approved"]) {
					$order->add_order_note( sprintf("BlockChyp transaction failed: %s", $response["responseDescription"]));
					throw new Exception($response["responseDescription"]);
				}

				if ($response["avsResponse"] == "no_match") {
					$order->add_order_note( sprintf("BlockChyp transaction reversed due to AVS failure: %s", $response["avsResponse"]));
					try {
						$request["paymentType"] = "no-reverse-cache";
						$reverseResponse = BlockChyp::reverse($request);
					} catch (Exception $re) {
						$order->add_order_note( sprintf("Transaction Reversal Failed: %s", $reverseResponse["responseDescription"]));
					}
					throw new Exception("Unable to verify billing address.");
				}

				$transaction_id = $response["transactionId"];
				$order->payment_complete($transaction_id);
				$message = sprintf(
					'BlockChyp payment successful.<br/>
					 Transaction ID: %s<br/>
					 Auth Code: %s<br/>
					 Payment Type: %s (%s)<br/>
					 AVS Response: %s<br/>
					 Authorized Amount: %s
				',
				$response["transactionId"],
				$response["authCode"],
				$response["paymentType"],
				$response["maskedPan"],
				$response["avsResponse"],
				$response["authorizedAmount"]
				);

				$order->add_order_note($message);


			} catch (Exception $e) {
				try {
					$order->add_order_note( sprintf("Reversing Transaction Due To Exception: %s", $e->getMessage()));
					$request["paymentType"] = "no-reverse-cache";
					$reverseResponse = BlockChyp::reverse($request);
				} catch (Exception $re) {
					$order->add_order_note( sprintf("Transaction Reversal Failed: %s", $reverseResponse["responseDescription"]));
				}
				throw new Exception($e->getMessage());
			}

			return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
			);

		}

		/**
		 * Outputs BlockChyp payment scripts.
		 */
		public function payment_scripts() {
			global $wp;

			if ( 'no' === $this->enabled ) {
				return;
			}

			$testmode = $this->settings['testmode'];

			if ($testmode) {
				wp_register_script( 'blockchyp', $this->test_gateway_host . '/static/js/blockchyp-tokenizer-all.min.js', '', '1.0.0', true );
			} else {
				wp_register_script( 'blockchyp', $this->gateway_host . '/static/js/blockchyp-tokenizer-all.min.js', '', '1.0.0', true );
			}

			wp_enqueue_script( 'blockchyp' );

		}

		/*
		 *	Defines the configuration fields needed to setup BlockChyp.
		 */
		function init_form_fields(){
				$this->form_fields = array(
						'enabled' => array(
														'title'       => __('Enable/Disable', 'blockchyp-woocommerce'),
														'type' 	      => 'checkbox',
														'label'       => __('Enable BlockChyp Gateway.', 'blockchyp-woocommerce'),
														'default'     => 'no',
														'description' => 'Include BlockChyp as a WooCommerce payment option.'
										),
						'testmode' => array(
														'title'       => __('Test Mode', 'blockchyp-woocommerce'),
														'type' 	      => 'checkbox',
														'label'       => __('Use Test Merchant Account', 'blockchyp-woocommerce'),
														'default'     => 'no',
														'description' => __('Connect your WooCommerce store with a BlockChyp test merchant account.')
						),
						'api_key' => array(
														'title'       => __('API Key', 'blockchyp-woocommerce'),
														'type' 	      => 'text',
														'desc_tip'    => true,
														'description' => __('Identifies a set of BlockChyp API credentials')
						),
						'bearer_token' => array(
														'title'       => __('Bearer Token', 'blockchyp-woocommerce'),
														'type' 	      => 'text',
														'desc_tip'    => true,
														'description' => __('Secure Bearer Token used to validate a BlockChyp API request.')
						),
						'signing_key' => array(
														'title'       => __('Signing Key', 'blockchyp-woocommerce'),
														'type' 	      => 'textarea',
														'desc_tip'    => true,
														'description' => __('Signing key to be used for creating API request HMAC signatures.')
						),
						'tokenizing_key' => array(
														'title'       => __('Tokenizing Key', 'blockchyp-woocommerce'),
														'type' 	      => 'textarea',
														'desc_tip'    => true,
														'description' => __('Tokenzing key to be used credit card tokenization.')
						),
						'gateway_host' => array(
														'title'       => __('Gateway Host', 'blockchyp-woocommerce'),
														'type' 	      => 'text',
														'default'     => 'https://api.blockchyp.com',
														'desc_tip'    => true,
														'description' => __('BlockChyp Production Gateway')
						),
						'test_gateway_host' => array(
														'title'       => __('Test Gateway Host', 'blockchyp-woocommerce'),
														'type' 	      => 'text',
														'default'     => 'https://test.blockchyp.com',
														'desc_tip'    => true,
														'description' => __('BlockChyp Test Gateway')
						)
				);
		}

		/**
		 * Process a BlockChyp refund.
		 * @param int $order_id
		 * @param float $amount
		 * @param string $reason
		 * @return boolean
		 **/
		public function process_refund( $order_id, $amount = NULL, $reason = '' ) {

			global $woocommerce;
			$order    = new WC_Order( $order_id );
			$transaction_id = $order->transaction_id;

			$testmode = false;
			if ($this->settings['testmode'] == 'yes') {
				$testmode = true;
			}

			BlockChyp::setApiKey($this->api_key);
			BlockChyp::setBearerToken($this->bearer_token);
			BlockChyp::setSigningKey($this->signing_key);
			BlockChyp::setGatewayHost($this->gateway_host);
			BlockChyp::setTestGatewayHost($this->test_gateway_host);

			$request = [
				'transactionId' => $transaction_id,
				'amount' => $amount,
				'test' => $testmode
			];

			try {
				$response = BlockChyp::refund($request);

				if (!$response["success"] || !$response["approved"]) {
					$order->add_order_note( sprintf("BlockChyp refund failed: %s", $response["responseDescription"]));
					throw new Exception($response["responseDescription"]);
				}

				if ($response["approved"]) {
					$order->add_order_note( sprintf("BlockChyp refund approved.<br/>Amount: %s<br/>Auth Code: %s", $amount, $response["authCode"]));
					return true;
				}

				$order->add_order_note( sprintf("BlockChyp refund failed: %s", $response["responseDescription"]));
			} catch (Exception $e) {
				$order->add_order_note( sprintf("Exception processing BlockChyp refund: %s ", $e->getMessage()));
			}

			return false;

		}

	}

	/**
	 * Add the Gateway to WooCommerce
	 **/
	function woocommerce_add_blockchyp($methods) {
					$methods[] = 'WC_BlockChyp';
					return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_blockchyp');

}
