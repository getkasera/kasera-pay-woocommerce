=== Kasera Pay for WooCommerce ===
Tags: qris, virtual account, payment gateway, indonesia, woocommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.2.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Accept QRIS and Virtual Account payments in Indonesia through the hosted Kasera Pay Checkout.

== Description ==

Kasera Pay for WooCommerce adds Kasera Pay as a payment method on your store. At checkout, the buyer is sent to the hosted Kasera Pay Checkout page to pay with QRIS or a bank Virtual Account, and then returns to your store. The order is marked paid automatically once Kasera Pay confirms the payment through a signed webhook.

* Works with both the block-based checkout and the classic `[woocommerce_checkout]` shortcode.
* Double submits never charge twice: every payment request carries an idempotency key.
* Webhooks are verified with a timestamped HMAC-SHA256 signature before an order is touched. If the paid amount does not match the order total, the order is put on hold for you to check.
* Test mode: with a `kp_test_` API key no real money moves, so you can run the full flow before going live.

Your store currency must be Indonesian Rupiah (IDR). You need a Kasera Pay account; sign up at [pay.kasera.id](https://pay.kasera.id).

== External services ==

This plugin connects to the Kasera Pay API (https://pay.kasera.id), operated by Kasera, to create payment requests. Without it, the payment method cannot work.

* **When:** each time a buyer places an order and chooses Kasera Pay.
* **What is sent:** the order amount, the order ID and order key, your store name with the order number, the buyer's billing name, email and phone number, the return URL of your store, and your Kasera Pay API key.
* **What is received:** Kasera Pay sends a `payment.paid` webhook to your store's URL (`/?wc-api=kasera_pay`) when a payment settles, containing the payment ID, order ID, amount, the buyer details sent above and the paid time.

Kasera Pay [Terms of Service](https://pay.kasera.id/syarat) and [Privacy Policy](https://pay.kasera.id/privasi).

== Installation ==

1. Install and activate the plugin, then open **WooCommerce → Settings → Payments → Kasera Pay**.
2. Paste your **API key** from the Developer page of your Kasera Pay dashboard. Start with a `kp_test_` key.
3. In the Kasera Pay dashboard, set your webhook URL to `https://your-store.com/?wc-api=kasera_pay` and copy the **signing secret** (`whsec_…`) into the plugin settings.
4. Place a test order and check that it moves to *Processing* after payment. Then switch to your `kp_live_` key.

== Frequently Asked Questions ==

= Which payment methods can buyers use? =

Every method enabled on your Kasera Pay account, such as QRIS and bank Virtual Accounts. To offer only some of them, enter their codes (for example `qris,bca_va`) in the plugin's **Method codes** setting.

= Can I refund from the WooCommerce admin? =

Not yet. Refunds are made from the Kasera Pay dashboard.

= Is the source code available? =

Yes, at [github.com/getkasera/kasera-pay-woocommerce](https://github.com/getkasera/kasera-pay-woocommerce).

== Screenshots ==

1. Kasera Pay as a payment method on the WooCommerce checkout.
2. The hosted Kasera Pay Checkout page (test mode).
3. The buyer is returned to the store with the order received.

== Changelog ==

= 0.2.1 =
* Sanitize the webhook signature header and tighten direct file access protection.
* Default checkout copy lists QRIS and Virtual Account only.

= 0.2.0 =
* Block-based checkout support.

= 0.1.0 =
* First release: hosted checkout redirect and signed webhooks.
