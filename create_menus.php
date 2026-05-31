<?php
require( dirname( __FILE__ ) . '/wp-load.php' );

// 1. Create Main Menu
$menu_name = 'Main Menu';
$menu_exists = wp_get_nav_menu_object( $menu_name );
if ( ! $menu_exists ) {
    $menu_id = wp_create_nav_menu($menu_name);
    
    // Add items
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title' => 'Shop',
        'menu-item-url' => home_url( '/collections/' ),
        'menu-item-status' => 'publish'
    ));
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title' => 'Pickleball',
        'menu-item-url' => home_url( '/collections/pickleball/' ),
        'menu-item-status' => 'publish'
    ));
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title' => 'About',
        'menu-item-url' => home_url( '/about/' ),
        'menu-item-status' => 'publish'
    ));
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title' => 'Journal',
        'menu-item-url' => home_url( '/journal/' ),
        'menu-item-status' => 'publish'
    ));
    
    // Assign to Blocksy primary location
    $locations = get_theme_mod('nav_menu_locations');
    $locations['menu_1'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}

// 2. Create Footer Menu
$footer_menu = 'Footer Menu';
$f_exists = wp_get_nav_menu_object( $footer_menu );
if ( ! $f_exists ) {
    $f_id = wp_create_nav_menu($footer_menu);
    wp_update_nav_menu_item($f_id, 0, array(
        'menu-item-title' => 'Shipping & Returns',
        'menu-item-url' => home_url( '/shipping-returns/' ),
        'menu-item-status' => 'publish'
    ));
    wp_update_nav_menu_item($f_id, 0, array(
        'menu-item-title' => 'Contact Us',
        'menu-item-url' => home_url( '/contact/' ),
        'menu-item-status' => 'publish'
    ));
}

echo "Menus created successfully.";
unlink(__FILE__);
?>
