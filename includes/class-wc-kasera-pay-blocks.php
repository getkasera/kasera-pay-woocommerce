<?php
/**
 * Block-checkout integration: registers Kasera Pay with the WooCommerce
 * Blocks payment method registry so the gateway shows up on the block-based
 * checkout. Payment itself stays the classic redirect flow — the block
 * places the order, WooCommerce calls process_payment(), and the returned
 * redirect is followed.
 */

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Kasera_Pay_Blocks extends AbstractPaymentMethodType
{
    protected $name = 'kasera_pay';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_kasera_pay_settings', []);
    }

    public function is_active()
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        return isset($gateways['kasera_pay']) && $gateways['kasera_pay']->is_available();
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'kasera-pay-blocks',
            plugins_url('assets/blocks.js', dirname(__FILE__)),
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'],
            '0.1.0',
            true
        );
        return ['kasera-pay-blocks'];
    }

    public function get_payment_method_data()
    {
        return [
            'title'       => $this->get_setting('title', 'Kasera Pay'),
            'description' => $this->get_setting('description', ''),
            'supports'    => ['products'],
        ];
    }
}
