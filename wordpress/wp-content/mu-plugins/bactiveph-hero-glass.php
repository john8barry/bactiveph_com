<?php
/**
 * Plugin Name: BactivePH Hero Glass
 * Description: Loads the approved hero treatment on the production homepage only.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', static function () {
    // Both the filesystem and WordPress destination must be the production site.
    $production_root = '/home/waypmvhk/bactiveph.com';
    $production_url  = 'https://bactiveph.com';

    if ( realpath( ABSPATH ) !== $production_root
        || untrailingslashit( home_url() ) !== $production_url
        || untrailingslashit( site_url() ) !== $production_url
        || is_admin()
        || ! is_front_page()
        || get_stylesheet() !== 'blocksy-child'
        || ! wp_style_is( 'blocksy-child-custom', 'registered' ) ) {
        return;
    }

    $theme_dir = get_stylesheet_directory();
    $theme_uri = get_stylesheet_directory_uri();
    $css_path  = $theme_dir . '/assets/css/hero-glass.css';
    $js_path   = $theme_dir . '/assets/js/hero-glass.js';

    if ( realpath( $theme_dir ) !== $production_root . '/wp-content/themes/blocksy-child'
        || $theme_uri !== $production_url . '/wp-content/themes/blocksy-child'
        || ! is_readable( $css_path )
        || ! is_readable( $js_path ) ) {
        return;
    }

    wp_enqueue_style(
        'bactiveph-hero-glass',
        $theme_uri . '/assets/css/hero-glass.css',
        array( 'blocksy-child-custom' ),
        'c88f46ee2997'
    );

    wp_enqueue_script(
        'bactiveph-hero-glass',
        $theme_uri . '/assets/js/hero-glass.js',
        array(),
        'ece9a7cb9808',
        array( 'strategy' => 'defer', 'in_footer' => true )
    );
}, 100 );
