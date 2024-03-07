<?php


use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_BlockChyp_Blocks_Support extends AbstractPaymentMethodType
{
    protected $gateway = null;
    protected $name = 'BlockChyp';

    public function __construct()
    {
        $this->gateway = new WC_BlockChyp_Gateway();
    }

    public function initialize()
    {
        $this->settings = get_option('woocommerce_blockchyp_settings', []);
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        if ('no' === $this->settings['enabled']) {
            return ['Plugin in not enabled'];
        }

        $testmode = false;
        if ($this->settings['testmode'] == 'yes') {
            $testmode = true;
        }

        if ($testmode) {
            wp_register_script(
                'blockchyp',
                $this->settings['test_gateway_host'] .
                    '/static/js/blockchyp-tokenizer-all.min.js',
                array(),
                '1.0.0',
                true
            );
        } else {
            wp_register_script(
                'blockchyp',
                $this->settings['gateway_host'] .
                    '/static/js/blockchyp-tokenizer-all.min.js',
                array(),
                '1.0.0',
                true
            );
        }

        return ['blockchyp'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->name,
        ];
    }
}
