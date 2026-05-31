<?php
require( dirname( __FILE__ ) . '/wp-load.php' );
require_once( ABSPATH . 'wp-admin/includes/post.php' );

// 1. Permalinks
global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%postname%/');
$wp_rewrite->flush_rules();

$woo_permalinks = get_option('woocommerce_permalinks');
if(!is_array($woo_permalinks)) { $woo_permalinks = array(); }
$woo_permalinks['category_base'] = 'collections';
$woo_permalinks['product_base'] = '/product/';
update_option('woocommerce_permalinks', $woo_permalinks);

// 2. Core WP settings
update_option('blogname', 'B Active');
update_option('blogdescription', 'Women\'s Pickleball Apparel & Activewear — Philippines');
update_option('timezone_string', 'Asia/Manila');
update_option('WPLANG', 'en_US');

// 3. Delete default content
$default_post = get_post(1);
if($default_post && $default_post->post_name == 'hello-world') { wp_delete_post(1, true); }
$sample_page = get_page_by_title('Sample Page');
if($sample_page) { wp_delete_post($sample_page->ID, true); }

// 4. WooCommerce settings
update_option('woocommerce_currency', 'PHP');
update_option('woocommerce_default_country', 'PH:DVO');
update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_enable_checkout_login_reminder', 'yes');
update_option('woocommerce_enable_signup_and_login_from_checkout', 'yes');
update_option('woocommerce_enable_myaccount_registration', 'yes');
update_option('woocommerce_enable_reviews', 'yes');
update_option('woocommerce_review_rating_verification_label', 'yes');
update_option('woocommerce_enable_review_rating', 'yes');
update_option('woocommerce_custom_orders_table_enabled', 'yes'); // Enable HPOS
update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');

echo "Setup script completed successfully.";
?>
