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
	
	// Enqueue child custom CSS
	wp_enqueue_style(
		'blocksy-child-custom',
		get_stylesheet_directory_uri() . '/assets/css/custom.css',
		array('blocksy-parent-style'),
		filemtime(get_stylesheet_directory() . '/assets/css/custom.css')
	);

	// Enqueue child custom JS
	wp_enqueue_script(
		'blocksy-child-custom-js',
		get_stylesheet_directory_uri() . '/assets/js/custom.js',
		array(),
		filemtime(get_stylesheet_directory() . '/assets/js/custom.js'),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'blocksy_child_enqueue_styles' );
