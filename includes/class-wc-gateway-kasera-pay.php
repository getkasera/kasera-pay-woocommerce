<?php
/**
 * Kasera Pay redirect gateway: create a payment request with a `checkout`
 * object, send the buyer to `checkout_url`, complete the order on the
 * signed `payment.paid` webhook.
 */

defined('ABSPATH') || exit;

class WC_Gateway_Kasera_Pay extends WC_Payment_Gateway
{
    public const WEBHOOK_SLUG = 'kasera_pay';

    private string $api_key;
    private string $signing_secret;
    private string $method_codes;

    public function __construct()
    {
        $this->id                 = 'kasera_pay';
        $this->method_title       = 'Kasera Pay';
        $this->method_description = sprintf(
            'Pembeli diarahkan ke Kasera Pay Checkout (QRIS, Virtual Account, kartu). '
            . 'Atur URL webhook di dashboard Kasera Pay ke: %s',
            esc_html($this->webhook_url())
        );
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title          = $this->get_option('title');
        $this->description    = $this->get_option('description');
        $this->api_key        = trim($this->get_option('api_key'));
        $this->signing_secret = trim($this->get_option('signing_secret'));
        $this->method_codes   = trim($this->get_option('method_codes'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_' . self::WEBHOOK_SLUG, [$this, 'handle_webhook']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Aktif',
                'type'    => 'checkbox',
                'label'   => 'Aktifkan Kasera Pay',
                'default' => 'no',
            ],
            'title' => [
                'title'   => 'Judul di checkout',
                'type'    => 'text',
                'default' => 'QRIS / Virtual Account / Kartu (Kasera Pay)',
            ],
            'description' => [
                'title'   => 'Deskripsi di checkout',
                'type'    => 'text',
                'default' => 'Bayar dengan QRIS, Virtual Account, atau kartu.',
            ],
            'api_key' => [
                'title'       => 'API key',
                'type'        => 'password',
                'description' => 'Dari halaman Developer di dashboard. kp_test_… untuk uji coba, kp_live_… untuk uang sungguhan.',
            ],
            'signing_secret' => [
                'title'       => 'Webhook signing secret',
                'type'        => 'password',
                'description' => 'whsec_… dari pengaturan webhook di dashboard. Wajib diisi supaya status pesanan terbarui otomatis.',
            ],
            'method_codes' => [
                'title'       => 'Kode metode (opsional)',
                'type'        => 'text',
                'description' => 'Dipisah koma, mis. qris,bca_va. Kosongkan untuk menawarkan semua metode yang aktif di akun. Daftar kode ada di GET /v1/payment_methods.',
            ],
        ];
    }

    public function is_available(): bool
    {
        return parent::is_available()
            && $this->api_key !== ''
            && get_woocommerce_currency() === 'IDR';
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        $body = [
            'amount'      => (int) round((float) $order->get_total()),
            'description' => sprintf('%s pesanan #%s', get_bloginfo('name'), $order->get_order_number()),
            'external_id' => (string) $order->get_id(),
            'merchant_ref' => $order->get_order_key(),
            'customer'    => array_filter([
                'name'  => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ]),
            'checkout'    => new stdClass(), // hosted Checkout create
        ];
        if ($this->method_codes !== '') {
            $body['payment_methods'] = array_values(array_filter(array_map('trim', explode(',', $this->method_codes))));
        }
        $return_url = $order->get_checkout_order_received_url();
        if (str_starts_with($return_url, 'https://')) { // the API refuses non-https return_url
            $body['return_url'] = $return_url;
        }

        // The Idempotency-Key is the only dedup: a double submit replays the
        // same pending payment request. A replay that comes back no longer
        // payable (expired/failed) bumps the attempt and creates a fresh one.
        $attempt = (int) $order->get_meta('_kasera_pay_attempt');
        $tx = $this->create_transaction($body, $order->get_order_key() . '-' . $attempt);
        if (is_wp_error($tx)) {
            wc_add_notice('Pembayaran gagal dibuat: ' . $tx->get_error_message(), 'error');
            return ['result' => 'failure'];
        }
        if ($tx['status'] !== 'pending') {
            $order->update_meta_data('_kasera_pay_attempt', (string) ($attempt + 1));
            $order->save();
            $tx = $this->create_transaction($body, $order->get_order_key() . '-' . ($attempt + 1));
            if (is_wp_error($tx)) {
                wc_add_notice('Pembayaran gagal dibuat: ' . $tx->get_error_message(), 'error');
                return ['result' => 'failure'];
            }
        }

        $order->update_meta_data('_kasera_pay_payreq_id', $tx['id']);
        $order->save();
        $order->add_order_note('Kasera Pay: payment request ' . $tx['id'] . ' dibuat.');

        return ['result' => 'success', 'redirect' => $tx['checkout_url']];
    }

    /** @return array|WP_Error decoded Transaction */
    private function create_transaction(array $body, string $idempotency_key)
    {
        $response = wp_remote_post($this->api_base() . '/v1/transactions', [
            'timeout' => 30,
            'headers' => [
                'Authorization'   => 'Bearer ' . $this->api_key,
                'Content-Type'    => 'application/json',
                'Idempotency-Key' => $idempotency_key,
            ],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (($code !== 201 && $code !== 200) || !is_array($json) || empty($json['id'])) {
            $message = $json['error']['message'] ?? ('HTTP ' . $code);
            return new WP_Error('kasera_pay_create_failed', $message);
        }
        return $json;
    }

    public function handle_webhook(): void
    {
        if ($this->signing_secret === '') {
            status_header(500);
            exit('signing secret not configured');
        }
        $raw = file_get_contents('php://input');
        $header = $_SERVER['HTTP_KASERA_SIGNATURE_V1'] ?? '';
        if (!kasera_pay_verify_signature($header, $raw, $this->signing_secret)) {
            status_header(400);
            exit('bad signature');
        }

        $event = json_decode($raw, true);
        if (!is_array($event) || empty($event['type'])) {
            status_header(400);
            exit('bad body');
        }
        if ($event['type'] !== 'payment.paid') {
            exit('ignored'); // test.ping and future event types: acked, untouched
        }

        // The account webhook receives every payment on the account, not just
        // this store's — an event we cannot match to an order is acked, not
        // an error.
        $data  = $event['data'] ?? [];
        $order = wc_get_order((int) ($data['external_id'] ?? 0));
        if (!$order || $order->get_meta('_kasera_pay_payreq_id') !== ($data['payment_request_id'] ?? '')) {
            exit('no matching order');
        }
        if ((int) round((float) $order->get_total()) !== (int) ($data['amount'] ?? -1)) {
            $order->update_status('on-hold', sprintf(
                'Kasera Pay: nominal webhook (Rp %s) tidak sama dengan total pesanan — periksa manual.',
                number_format((int) $data['amount'], 0, ',', '.')
            ));
            exit('amount mismatch');
        }

        // payment_complete is a no-op on an already-paid order, which absorbs
        // at-least-once redelivery of the same event.
        $order->payment_complete($data['payment_request_id']);
        $order->add_order_note('Kasera Pay: lunas (event ' . ($event['id'] ?? '?') . ').');
        exit('ok');
    }

    private function webhook_url(): string
    {
        return add_query_arg('wc-api', self::WEBHOOK_SLUG, home_url('/'));
    }

    private function api_base(): string
    {
        return untrailingslashit(apply_filters('kasera_pay_api_base', 'https://pay.kasera.id'));
    }
}
