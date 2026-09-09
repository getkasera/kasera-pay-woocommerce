# Kasera Pay for WooCommerce

Payment gateway [Kasera Pay](https://pay.kasera.id) untuk WooCommerce: pembeli diarahkan ke halaman Kasera Pay Checkout untuk membayar dengan QRIS, Virtual Account, atau kartu, lalu status pesanan terbarui otomatis lewat webhook bertanda tangan.

## Kebutuhan

- WordPress 6.0+, WooCommerce 8.0+, PHP 8.1+
- Mata uang toko **IDR**
- Akun Kasera Pay dengan API key (`kp_test_…` / `kp_live_…`)

## Pemasangan

1. Unduh zip dari [Releases](https://github.com/getkasera/kasera-pay-woocommerce/releases), lalu di WordPress admin: **Plugins → Add New → Upload Plugin**.
2. Aktifkan, lalu buka **WooCommerce → Settings → Payments → Kasera Pay**.
3. Isi **API key** dari halaman Developer di dashboard Kasera Pay. Pakai `kp_test_…` dulu — semua transaksi jadi objek uji, tidak ada uang berpindah.
4. Di dashboard Kasera Pay, atur URL webhook ke `https://toko-anda.com/?wc-api=kasera_pay`, lalu salin **signing secret** (`whsec_…`) ke pengaturan plugin.
5. Uji satu checkout, cek pesanan berubah jadi *Processing* setelah dibayar, lalu ganti ke `kp_live_…`.

## Cara kerja

- Saat pembeli memilih Kasera Pay, plugin memanggil `POST /v1/transactions` dengan object `checkout` dan mengarahkan pembeli ke `checkout_url`.
- Header `Idempotency-Key` (kunci pesanan + nomor percobaan) mencegah tagihan ganda saat tombol bayar diklik dua kali.
- Webhook `payment.paid` diverifikasi terhadap header `Kasera-Signature-V1` (HMAC-SHA256 bertimestamp, toleransi 5 menit) sebelum pesanan ditandai lunas. Nominal webhook dicocokkan dengan total pesanan; kalau beda, pesanan masuk *on-hold* untuk diperiksa manual.
- Event yang bukan milik toko ini (akun yang sama bisa menerima pembayaran dari sumber lain) di-ack tanpa mengubah apa pun.

## Batasan versi ini

- Redirect ke Kasera Pay Checkout saja — belum ada QRIS tampil langsung di halaman toko (Direct API).
- Refund dilakukan dari dashboard Kasera Pay, bukan dari admin WooCommerce.

## Pengembangan

```sh
php tests/signature-test.php   # verifikasi tanda tangan webhook
```

Override endpoint API untuk pengujian lokal:

```php
add_filter('kasera_pay_api_base', fn() => 'http://localhost:8080');
```

## Lisensi

MIT
