<?php
/**
 * Blocksy Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Blocksy Child
 */

/**
 * Enqueue scripts and styles.
 */
function blocksy_child_enqueue_styles() {
	// Enqueue parent style
	wp_enqueue_style(
		'blocksy-parent-style',
		get_template_directory_uri() . '/style.css'
	);
	
	// Enqueue Google Fonts specified by UI/UX Pro Max
	wp_enqueue_style(
		'blocksy-child-fonts',
		'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;500;600;700&family=Barlow:wght@300;400;500;600;700&display=swap',
		array(),
		null
	);
}
add_action( 'wp_enqueue_scripts', 'blocksy_child_enqueue_styles' );
