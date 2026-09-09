<?php
/**
 * Plugin Name: Kasera Pay for WooCommerce
 * Plugin URI: https://github.com/getkasera/kasera-pay-woocommerce
 * Description: Terima pembayaran QRIS, Virtual Account, dan kartu lewat Kasera Pay Checkout.
 * Version: 0.1.0
 * Author: Kasera
 * Author URI: https://pay.kasera.id
 * License: MIT
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/signature.php';

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    require_once __DIR__ . '/includes/class-wc-gateway-kasera-pay.php';
    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Gateway_Kasera_Pay';
        return $gateways;
    });
});
