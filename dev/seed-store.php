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

// stock Storefront, minus the blog defaults: no Hello World, no Sample Page,
// no Recent Posts sidebar. The one CSS line lets the grid use the width the
// removed sidebar leaves behind.
foreach (get_posts(['post_type' => ['post', 'page'], 'title' => null, 'numberposts' => -1, 'post_status' => 'any']) as $p) {
    if (in_array($p->post_title, ['Hello world!', 'Sample Page'], true)) {
        wp_delete_post($p->ID, true);
    }
}
update_option('sidebars_widgets', ['wp_inactive_widgets' => [], 'sidebar-1' => [], 'array_version' => 3]);
wp_update_custom_css_post('.content-area { width: 100%; }');

update_option('show_on_front', 'page');
update_option('page_on_front', wc_get_page_id('shop'));
update_option('blogdescription', 'Kopi enak, bayar gampang');


echo "seeded: homepage=shop, css applied\n";
