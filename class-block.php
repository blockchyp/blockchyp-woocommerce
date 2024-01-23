<?php 

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class BlockChyp_Gateway_Blocks extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'blockchyp-gateway';

    public function initialize() {
        // TODO might need to change the settings variable
        $this->settings = get_option('woocommerce_blockchyp_gateway_settings', []);
        $this->gateway = new BlockChyp_Gateway_Blocks();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }
}