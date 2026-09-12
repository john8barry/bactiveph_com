<?php
/** Approved collection/editorial presentation, activated only by reviewed release config. */
defined( 'ABSPATH' ) || exit;

function bactive_collection_release() {
	$config = apply_filters( 'bactive_collection_visual_release', get_option( 'bactive_collection_visual_release', array() ) );
	return is_array( $config ) && isset( $config['version'] ) && is_string( $config['version'] ) && preg_match( '/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z/', $config['version'] ) ? $config : array();
}

function bactive_collection_product_classes( $classes, $product ) {
	$config = bactive_collection_release();
	$ids = isset( $config['product_ids'] ) && is_array( $config['product_ids'] ) ? $config['product_ids'] : array();
	if ( true === ( $config['enabled'] ?? false ) && $product instanceof WC_Product && in_array( $product->get_id(), $ids, true ) ) {
		$classes[] = 'bactive-collection-product';
	}
	return $classes;
}
add_filter( 'woocommerce_post_class', 'bactive_collection_product_classes', 20, 2 );

function bactive_collection_styles() {
	$config = bactive_collection_release();
	if ( true !== ( $config['enabled'] ?? false ) && true !== ( $config['editorial']['enabled'] ?? false ) ) {
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
	if ( ! is_int( $story['attachment_id'] ?? null ) || $story['attachment_id'] < 1 || ! is_int( $story['page_id'] ?? null ) || $story['page_id'] < 1 ) {
		return '';
	}
	$page = get_post( $story['page_id'] );
	if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status || ! empty( $page->post_password ) ) {
		return '';
	}
	$image = wp_get_attachment_image( $story['attachment_id'], 'large', false, array( 'class' => 'bactive-editorial-image', 'loading' => 'lazy', 'decoding' => 'async' ) );
	if ( ! $image ) {
		return '';
	}
	$id = wp_unique_id( 'bactive-editorial-' );
	return '<section class="bactive-editorial" aria-labelledby="' . esc_attr( $id ) . '"><div class="bactive-editorial-media">' . $image . '</div><div class="bactive-editorial-copy"><h2 id="' . esc_attr( $id ) . '">' . esc_html( $story['heading'] ) . '</h2><p>' . esc_html( $story['body'] ) . '</p><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( $story['link_label'] ) . '</a></div></section>';
}
add_shortcode( 'bactive_editorial', 'bactive_editorial_shortcode' );

/** Add approved, named previews after native card content; never changes cart actions. */
function bactive_collection_colour_previews() {
	global $product;
	if ( ! $product instanceof WC_Product || ! in_array( 'bactive-collection-product', bactive_collection_product_classes( array(), $product ), true )
		|| ! function_exists( 'bactive_catalog_visuals_registry' ) || ! function_exists( 'bactive_catalog_visuals_palette' ) || ! $product->is_type( 'variable' ) ) {
		return;
	}
	$palette = bactive_catalog_visuals_palette( bactive_catalog_visuals_registry(), $product->get_id() );
	if ( ! $palette ) { return; }
	$attributes = $product->get_variation_attributes();
	$links = array();
	foreach ( $palette as $attribute => $shades ) {
		if ( ! in_array( $attribute, array( 'attribute_pa_colour', 'attribute_pa_color' ), true ) ) { continue; }
		$taxonomy = substr( $attribute, 10 );
		foreach ( (array) $shades as $slug => $hex ) {
			if ( ! is_string( $slug ) || ! is_string( $hex ) || ! preg_match( '/\A#[a-fA-F0-9]{6}\z/', $hex )
				|| ! in_array( $slug, $attributes[ $taxonomy ] ?? array(), true ) ) { continue; }
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) { continue; }
			$links[] = '<a class="bactive-colour-preview" href="' . esc_url( add_query_arg( $attribute, $slug, $product->get_permalink() ) ) . '"><span class="bactive-colour-dot" style="--bactive-preview:' . esc_attr( $hex ) . '" aria-hidden="true"></span><span>' . esc_html( $term->name ) . '</span></a>';
		}
	}
	if ( $links ) { echo '<div class="bactive-colour-previews" aria-label="' . esc_attr__( 'Product colours', 'blocksy-child' ) . '">' . implode( '', $links ) . '</div>'; }
}
add_action( 'woocommerce_after_shop_loop_item', 'bactive_collection_colour_previews', 30 );

/** Render-only transform: exact reviewed block, homepage identity, no database writes. */
function bactive_editorial_existing_block( $html, $block ) {
	$config = bactive_collection_release();
	$story = $config['editorial'] ?? array();
	if ( true !== ( $story['enabled'] ?? false ) || ! is_front_page() || 14 !== get_queried_object_id()
		|| 'core/group' !== ( $block['blockName'] ?? '' ) || ! is_string( $story['source_sha256'] ?? null )
		|| ! preg_match( '/\A[a-f0-9]{64}\z/', $story['source_sha256'] )
		|| ! hash_equals( $story['source_sha256'], hash( 'sha256', serialize_block( $block ) ) )
		|| false === strpos( $html, 'Designed for an Asian fit.' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	// A reviewed replacement is optional. Incomplete attachment data keeps the original.
	if ( is_int( $story['attachment_id'] ?? null ) && $story['attachment_id'] > 0 ) {
		$image = wp_get_attachment_image( $story['attachment_id'], 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async' ) );
		$replacement = new WP_HTML_Tag_Processor( $image );
		$probe = new WP_HTML_Tag_Processor( $html );
		$count = 0;
		while ( $probe->next_tag( 'IMG' ) ) { ++$count; }
		if ( 1 === $count && $replacement->next_tag( 'IMG' ) && $replacement->get_attribute( 'src' ) && (int) $replacement->get_attribute( 'width' ) > 0 && (int) $replacement->get_attribute( 'height' ) > 0 ) {
			$original = new WP_HTML_Tag_Processor( $html );
			$original->next_tag( 'IMG' );
			foreach ( array( 'src', 'srcset', 'sizes', 'width', 'height', 'alt', 'loading', 'decoding' ) as $attribute ) {
				$value = $replacement->get_attribute( $attribute );
				if ( null === $value ) { $original->remove_attribute( $attribute ); }
				else { $original->set_attribute( $attribute, $value ); }
			}
			$html = $original->get_updated_html();
		}
	}
	$processor = new WP_HTML_Tag_Processor( $html );
	if ( $processor->next_tag( array( 'class_name' => 'wp-block-group' ) ) ) {
		$processor->add_class( 'bactive-editorial-existing' );
		return $processor->get_updated_html();
	}
	return $html;
}
add_filter( 'render_block', 'bactive_editorial_existing_block', 20, 2 );
