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
	/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'BlockChyp requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'blockchyp-woocommerce' ), WC_STRIPE_MIN_WC_VER, WC_VERSION ) . '</strong></p></div>';
}

add_action( 'plugins_loaded', 'blockchyp_woocommerce_init' );

function blockchyp_woocommerce_init() {


	class WC_BlockChyp extends WC_Payment_Gateway {

		public function __construct() {

			 $this->id 		      			 = 'blockchyp';
			 $this->method_title       = 'BlockChyp';
			 $this->method_description = 'Connects your WooCommerce store with the BlockChyp gateway.';
			 $this->has_fields 	       = true;
			 $this->supports           = array(
				 'products',
				 'refunds',
				 'tokenization',
				 'add_payment_method'
			 );

			 $this->init_form_fields();
			 $this->init_settings();

			 add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));

			 add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		}
		/**
		 * Payment_scripts function.
		 *
		 * Outputs BlockChyp payment scripts.
		 */
		public function payment_scripts() {
			global $wp;

			if ( 'no' === $this->enabled ) {
				return;
			}

			wp_register_script( 'blockchyp', 'https://api.blockchyp.com/static/js/blockchyp-tokenizer-all.min.js', '', '1.0.0', true );

			wp_enqueue_script( 'blockchyp_woocommerce' );

		}

		/*
		 *	admin form and other option title description define from here some are not editable from admin side some like workflowid merchantprofile id are editable from admin *    panel.
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
