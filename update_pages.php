<?php
require_once 'wp-load.php';

$cart_page_id = get_option('woocommerce_cart_page_id');
$checkout_page_id = get_option('woocommerce_checkout_page_id');

wp_update_post(array(
    'ID' => $cart_page_id,
    'post_content' => '[woocommerce_cart]'
));

wp_update_post(array(
    'ID' => $checkout_page_id,
    'post_content' => '[woocommerce_checkout]'
));

echo "Cart and Checkout pages updated to shortcodes.\n";
