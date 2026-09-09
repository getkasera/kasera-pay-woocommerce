<?php
/**
 * Coffee-shop dressing for the dev store: six products with Unsplash photos,
 * the shop as the homepage, minimalist Storefront CSS. Idempotent — products
 * are matched by title, images sideloaded only when missing.
 *
 * Run: docker compose run --rm cli wp eval-file \
 *        /var/www/html/wp-content/plugins/kasera-pay/dev/seed-store.php
 */

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$catalog = [
    ['Kopi Gayo 250g',       65000,  '1447933601403-0c6688de566e'],
    ['Kopi Toraja 250g',     72000,  '1524350876685-274059332603'],
    ['Drip Bag Isi 10',      48000,  '1495474472287-4d71bcdd2085'],
    ['Cold Brew 1L',         55000,  '1461023058943-07fcbe16d735'],
    ['Gula Aren Cair 500ml', 35000,  '1509042239860-f550ce710b93'],
    ['Tumbler Kasera 350ml', 120000, '1521302080334-4bebac2763a6'],
];

foreach ($catalog as [$name, $price, $photo]) {
    $post = get_posts(['post_type' => 'product', 'title' => $name, 'post_status' => 'any', 'numberposts' => 1])[0] ?? null;
    $product = $post ? wc_get_product($post->ID) : new WC_Product_Simple();
    $product->set_name($name);
    $product->set_regular_price((string) $price);
    $product->set_status('publish');
    $product->save();

    if (!$product->get_image_id()) {
        $url = "https://images.unsplash.com/photo-$photo?w=900&q=80&fm=jpg";
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            echo "img FAIL: $name — " . $tmp->get_error_message() . "\n";
            continue;
        }
        $att = media_handle_sideload(['name' => sanitize_title($name) . '.jpg', 'tmp_name' => $tmp], 0, $name);
        if (is_wp_error($att)) {
            echo "img FAIL: $name — " . $att->get_error_message() . "\n";
            continue;
        }
        $product->set_image_id($att);
        $product->save();
    }
    echo "ok: $name\n";
}

update_option('show_on_front', 'page');
update_option('page_on_front', wc_get_page_id('shop'));
update_option('blogdescription', 'Kopi enak, bayar gampang');

wp_update_custom_css_post(<<<'CSS'
/* minimalist storefront: hide chrome, keep the grid and the cart */
.storefront-breadcrumb, .woocommerce-products-header, .woocommerce-result-count,
.woocommerce-ordering, .site-search, .secondary-navigation,
.storefront-handheld-footer-bar, .site-info, .widget-area,
.storefront-primary-navigation .menu { display: none !important; }
.site-header { background: #fff; border-bottom: 1px solid #eee; padding-top: 1.2em; }
.site-branding { text-align: center; float: none; margin: 0 auto .5em; }
.site-branding .site-title { font-size: 1.6em; letter-spacing: .02em; }
.site-description { display: block; text-align: center; color: #888; clip: unset; position: static; height: auto; width: auto; }
.col-full { max-width: 1080px; }
.content-area { width: 100% !important; float: none; margin: 0 auto; }
ul.products li.product img { border-radius: 14px; aspect-ratio: 1 / 1; object-fit: cover; }
ul.products li.product .woocommerce-loop-product__title { font-size: 1.05em; }
ul.products li.product .price { color: #111; font-weight: 600; }
.add_to_cart_button, .single_add_to_cart_button, .checkout-button, #place_order { border-radius: 999px; }
CSS);

echo "seeded: homepage=shop, css applied\n";
