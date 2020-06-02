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
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 * @return string
 */
function blockchyp_woocommerce_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'BlockChyp requires WooCommerce to be installed and active. You can download %s here.', 'blockchyp-woocommerce' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}
