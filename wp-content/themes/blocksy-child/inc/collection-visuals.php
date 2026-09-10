<?php
/** Approved collection/editorial presentation, activated only by reviewed release config. */
defined( 'ABSPATH' ) || exit;

function bactive_collection_release() {
	$config = apply_filters( 'bactive_collection_visual_release', array() );
	return is_array( $config ) && true === ( $config['enabled'] ?? false ) && isset( $config['version'] ) && is_string( $config['version'] ) && preg_match( '/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z/', $config['version'] ) ? $config : array();
}

function bactive_collection_product_classes( $classes, $product ) {
	$config = bactive_collection_release();
	$ids = isset( $config['product_ids'] ) && is_array( $config['product_ids'] ) ? $config['product_ids'] : array();
	if ( $product instanceof WC_Product && in_array( $product->get_id(), $ids, true ) ) {
		$classes[] = 'bactive-collection-product';
	}
	return $classes;
}
add_filter( 'woocommerce_post_class', 'bactive_collection_product_classes', 20, 2 );

function bactive_collection_styles() {
	if ( ! bactive_collection_release() ) {
		return;
	}
	$relative = '/assets/css/collection-visuals.css';
	$path = get_stylesheet_directory() . $relative;
	if ( is_readable( $path ) ) {
		wp_enqueue_style( 'bactive-collection-visuals', get_stylesheet_directory_uri() . $relative, array( 'bactive-brand-typography' ), filemtime( $path ) );
	}
}
add_action( 'wp_enqueue_scripts', 'bactive_collection_styles', 40 );

/** Page placement is explicit. No automatic injection into existing homepage content. */
function bactive_editorial_shortcode() {
	$config = bactive_collection_release();
	$story = isset( $config['editorial'] ) && is_array( $config['editorial'] ) ? $config['editorial'] : array();
	if ( empty( $story['enabled'] ) || true !== $story['enabled'] || ! is_front_page() ) {
		return '';
	}
	foreach ( array( 'heading', 'body', 'link_label' ) as $field ) {
		if ( ! isset( $story[ $field ] ) || ! is_string( $story[ $field ] ) || '' === trim( $story[ $field ] ) ) {
			return '';
		}
	}
	if ( empty( $story['attachment_id'] ) || ! is_int( $story['attachment_id'] ) || empty( $story['product_id'] ) || ! is_int( $story['product_id'] ) || ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$product = wc_get_product( $story['product_id'] );
	if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_visible() ) {
		return '';
	}
	$image = wp_get_attachment_image( $story['attachment_id'], 'large', false, array( 'class' => 'bactive-editorial-image', 'loading' => 'lazy', 'decoding' => 'async' ) );
	if ( ! $image ) {
		return '';
	}
	$id = wp_unique_id( 'bactive-editorial-' );
	return '<section class="bactive-editorial" aria-labelledby="' . esc_attr( $id ) . '"><div class="bactive-editorial-media">' . $image . '</div><div class="bactive-editorial-copy"><h2 id="' . esc_attr( $id ) . '">' . esc_html( $story['heading'] ) . '</h2><p>' . esc_html( $story['body'] ) . '</p><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $story['link_label'] ) . '</a></div></section>';
}
add_shortcode( 'bactive_editorial', 'bactive_editorial_shortcode' );
