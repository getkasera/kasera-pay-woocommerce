<?php
/**
 * Dev-only override: point the gateway at a local Kasera Pay API instead of
 * production. Loaded because dev/mu is mounted as mu-plugins in the compose
 * store — never ships with the plugin.
 */
if (getenv('KASERA_PAY_API_BASE')) {
    add_filter('kasera_pay_api_base', fn () => getenv('KASERA_PAY_API_BASE'));
}

// Demo checkout: buyer fields come pre-filled so a visitor only clicks
// "Place order" and lands on the Kasera Pay checkout.
add_filter('woocommerce_checkout_get_value', function ($value, $input) {
    $demo = [
        'billing_first_name' => 'Budi',
        'billing_last_name'  => 'Santoso',
        'billing_address_1'  => 'Jl. Jend. Sudirman No. 1',
        'billing_city'       => 'Jakarta Selatan',
        'billing_postcode'   => '12190',
        'billing_country'    => 'ID',
        'billing_state'      => 'JK',
        'billing_phone'      => '08123456789',
        'billing_email'      => 'demo@kasera.id',
    ];
    return $value ?: ($demo[$input] ?? $value);
}, 10, 2);
