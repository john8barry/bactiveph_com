<?php
require( dirname( __FILE__ ) . '/wp-load.php' );
require_once( ABSPATH . 'wp-admin/includes/post.php' );
require_once( ABSPATH . 'wp-admin/includes/taxonomy.php' );

// 1. Create Pages
$pages = [
    'Home', 'Shop', 'The Court Edit', 'About / Our Story', 'Journal', 'Our Store',
    'Size Guide', 'Shipping & Returns', 'FAQ', 'Contact', 'Fabric & Care',
    'Privacy Policy', 'Terms & Conditions', 'Returns Policy'
];

$page_ids = [];

foreach($pages as $page_title) {
    $page = get_page_by_title($page_title);
    if(!$page) {
        $page_id = wp_insert_post([
            'post_title' => $page_title,
            'post_type' => 'page',
            'post_status' => 'publish'
        ]);
        $page_ids[$page_title] = $page_id;
    } else {
        $page_ids[$page_title] = $page->ID;
    }
}

// Set Front Page and Shop Page
update_option('show_on_front', 'page');
update_option('page_on_front', $page_ids['Home']);
update_option('woocommerce_shop_page_id', $page_ids['Shop']);

// 2. Create WooCommerce Categories and Tags
$categories = [
    'Activewear' => 'Premium women\'s activewear designed for the court and beyond. Soft, breathable, and squat-proof.',
    'Pickleball Paddles' => 'High-performance graphite and carbon fiber pickleball paddles designed for spin, power, and control.',
    'Accessories' => 'Court-ready accessories to complete your look, from visors and sweatbands to premium paddle covers.'
];

foreach($categories as $cat => $desc) {
    if(!term_exists($cat, 'product_cat')) {
        wp_insert_term($cat, 'product_cat', ['description' => $desc]);
    }
}

$tags = ['The Court Edit', 'Soft Sculpt', 'Core Essentials'];
foreach($tags as $tag) {
    if(!term_exists($tag, 'product_tag')) {
        wp_insert_term($tag, 'product_tag');
    }
}

// 3. Setup Navigation Menus
$menu_name = 'Main Menu';
$menu_exists = wp_get_nav_menu_object($menu_name);
if(!$menu_exists){
    $menu_id = wp_create_nav_menu($menu_name);
    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' =>  'Shop',
        'menu-item-object-id' => $page_ids['Shop'],
        'menu-item-object' => 'page',
        'menu-item-type' => 'post_type',
        'menu-item-status' => 'publish'
    ]);
    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' =>  'The Court Edit',
        'menu-item-object-id' => $page_ids['The Court Edit'],
        'menu-item-object' => 'page',
        'menu-item-type' => 'post_type',
        'menu-item-status' => 'publish'
    ]);
    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' =>  'About',
        'menu-item-object-id' => $page_ids['About / Our Story'],
        'menu-item-object' => 'page',
        'menu-item-type' => 'post_type',
        'menu-item-status' => 'publish'
    ]);
    
    // Assign menu to primary location if Blocksy supports it
    $locations = get_theme_mod('nav_menu_locations');
    $locations['menu_1'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}

echo "IA Setup completed successfully.";
?>
