<?php
/**
 * Dev-only override: point the gateway at a local Kasera Pay API instead of
 * production. Loaded because dev/mu is mounted as mu-plugins in the compose
 * store — never ships with the plugin.
 */
if (getenv('KASERA_PAY_API_BASE')) {
    add_filter('kasera_pay_api_base', fn () => getenv('KASERA_PAY_API_BASE'));
}
