#!/bin/sh
# One-shot dev store: WordPress + WooCommerce + this plugin, on
# http://localhost:8090 (admin/admin123).
#
#   KASERA_API_KEY=kp_test_... KASERA_SIGNING_SECRET=whsec_... ./setup.sh
#
# Both env vars are optional; without them, paste the keys later in
# WooCommerce -> Settings -> Payments -> Kasera Pay. Re-running is safe.
set -e
cd "$(dirname "$0")"

docker compose up -d wordpress db

echo "waiting for wordpress..."
until curl -so /dev/null http://localhost:8090; do sleep 2; done

wp() { docker compose run --rm cli wp "$@"; }

wp core install --url=http://localhost:8090 --title="Toko Demo Kasera" \
  --admin_user=admin --admin_password=admin123 --admin_email=dev@kasera.id --skip-email

wp plugin install woocommerce --activate
wp plugin activate kasera-pay
wp theme install storefront --activate

wp option update woocommerce_currency IDR
wp option update woocommerce_price_num_decimals 0
wp option update woocommerce_price_thousand_sep .
wp option update woocommerce_default_country "ID:JK"
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
wp wc tool run install_pages --user=admin

wp option update woocommerce_kasera_pay_settings "$(printf '{"enabled":"yes","title":"QRIS / Virtual Account / Kartu (Kasera Pay)","description":"Bayar dengan QRIS, Virtual Account, atau kartu.","api_key":"%s","signing_secret":"%s","method_codes":""}' "${KASERA_API_KEY:-}" "${KASERA_SIGNING_SECRET:-}")" --format=json


wp eval-file /var/www/html/wp-content/plugins/kasera-pay/dev/seed-store.php

echo
echo "store:   http://localhost:8090 (shop at /?post_type=product)"
echo "admin:   http://localhost:8090/wp-admin (admin / admin123)"
echo "webhook: set the dashboard webhook URL to http://<reachable-host>:8090/?wc-api=kasera_pay"
