<?php
/**
 * Product selectors, inert until an exact product is released.
 *
 * Define BACTIVE_CATALOG_VISUALS_REGISTRY in private deployment configuration:
 * ['schema_version' => 1, 'version' => 'release-id', 'enabled' => true,
 *  'products' => [36 => ['enabled' => true, 'palette' => []]]].
 * Optional palette: attribute_pa_colour => slug =>
 * ['term_id' => 123, 'approved' => true, 'hex' => '#aabbcc'].
 * Omit unapproved shades: named text buttons remain fully usable.
 * Rollback: set enabled=false globally or for the individual product.
 * Never stores prices, availability, variation IDs, or gallery assignments.
 */
defined( 'ABSPATH' ) || exit;

function bactive_catalog_visuals_config( $registry, $product_id ) {
    if ( ! is_array( $registry ) || 1 !== ( $registry['schema_version'] ?? null )
        || true !== ( $registry['enabled'] ?? false )
        || ! is_string( $registry['version'] ?? null )
        || ! preg_match( '/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z/', $registry['version'] )
        || ! is_int( $product_id ) || $product_id < 1
        || ! is_array( $registry['products'] ?? null ) ) {
        return null;
    }
    $entry = $registry['products'][ $product_id ] ?? null;
    if ( ! is_array( $entry ) || true !== ( $entry['enabled'] ?? false ) ) {
        return null;
    }
    $palette = array();
    foreach ( array( 'attribute_pa_colour', 'attribute_pa_color' ) as $attribute ) {
        $shades = $entry['palette'][ $attribute ] ?? array();
        if ( ! is_array( $shades ) ) {
            continue;
        }
        foreach ( $shades as $slug => $shade ) {
            if ( ! is_string( $slug ) || ! is_array( $shade )
                || true !== ( $shade['approved'] ?? false )
                || ! is_int( $shade['term_id'] ?? null ) || $shade['term_id'] < 1
                || ! is_string( $shade['hex'] ?? null )
                || ! preg_match( '/\A#[a-fA-F0-9]{6}\z/', $shade['hex'] ) ) {
                continue;
            }
            $term = get_term_by( 'slug', $slug, substr( $attribute, 10 ) );
            if ( $term && ! is_wp_error( $term ) && (int) $term->term_id === $shade['term_id'] ) {
                $palette[ $attribute ][ $slug ] = $shade['hex'];
            }
        }
    }
    return array( 'schemaVersion' => 1, 'version' => $registry['version'],
        'productId' => $product_id, 'palette' => (object) $palette );
}

function bactive_enqueue_catalog_visuals() {
    if ( ! function_exists( 'is_product' ) || ! is_product()
        || ! defined( 'BACTIVE_CATALOG_VISUALS_REGISTRY' ) ) {
        return;
    }
    $product = wc_get_product( get_queried_object_id() );
    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return;
    }
    $config = bactive_catalog_visuals_config( BACTIVE_CATALOG_VISUALS_REGISTRY, $product->get_id() );
    $base = get_stylesheet_directory();
    if ( ! $config || ! is_readable( $base . '/assets/css/catalog-visuals.css' )
        || ! is_readable( $base . '/assets/js/catalog-visuals.js' ) ) {
        return;
    }
    $uri = get_stylesheet_directory_uri();
    wp_enqueue_style( 'bactive-catalog-visuals', $uri . '/assets/css/catalog-visuals.css',
        array( 'bactive-brand-typography' ), filemtime( $base . '/assets/css/catalog-visuals.css' ) );
    wp_enqueue_script( 'bactive-catalog-visuals', $uri . '/assets/js/catalog-visuals.js',
        array( 'jquery', 'wc-add-to-cart-variation' ), filemtime( $base . '/assets/js/catalog-visuals.js' ), true );
    wp_add_inline_script( 'bactive-catalog-visuals', 'window.bactiveCatalogVisuals=' .
        wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'bactive_enqueue_catalog_visuals', 40 );
