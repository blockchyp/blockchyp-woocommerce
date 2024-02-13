<?php 

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_BlockChyp_Blocks_Support extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'blockchyp';

    public function initialize() {
        // TODO might need to change the settings variable
        $this->settings = get_option('woocommerce_blockchyp_settings', []);
        $this->gateway = new WC_BlockChyp_Gateway();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        wp_register_script('blockchyp-gateway-blocks-integration', plugin_dir_url(__FILE__) . 'checkout.js', ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'], null, true);

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'blockchyp-gateway-blocks-integration' );
        }

        return ['blockchyp-gateway-blocks-integration'];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
        ];
    }
}