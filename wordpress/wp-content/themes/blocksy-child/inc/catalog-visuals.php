<?php
/**
 * Product selectors, inert until an exact product is released.
 *
 * Store bactive_catalog_visuals_release as a private option, or define
 * BACTIVE_CATALOG_VISUALS_REGISTRY in private deployment configuration:
 * ['schema_version' => 1, 'version' => 'release-id', 'enabled' => true,
 *  'products' => [36 => ['enabled' => true, 'reviewed' => true, 'palette' => []]]].
 * Optional palette: attribute_pa_colour => slug =>
 * ['term_id' => 123, 'approved' => true, 'hex' => '#aabbcc'].
 * Omit unapproved shades: named text buttons remain fully usable.
 * Rollback: set enabled=false globally or for the individual product.
 * Never stores prices, availability, variation IDs, or gallery assignments.
 */
defined( 'ABSPATH' ) || exit;

/** Private release option; constants retain precedence for existing deployments. */
function bactive_catalog_visuals_registry() {
    $registry = defined( 'BACTIVE_CATALOG_VISUALS_REGISTRY' )
        ? BACTIVE_CATALOG_VISUALS_REGISTRY : get_option( 'bactive_catalog_visuals_release', array() );
    return is_array( $registry ) ? $registry : array();
}

/** Validated review data is independent of which presentation is released. */
function bactive_catalog_visuals_entry( $registry, $product_id ) {
    if ( ! is_array( $registry ) || 1 !== ( $registry['schema_version'] ?? null )
        || ! is_string( $registry['version'] ?? null )
        || ! preg_match( '/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z/', $registry['version'] )
        || ! is_int( $product_id ) || $product_id < 1
        || ! is_array( $registry['products'] ?? null ) ) {
        return null;
    }
    $entry = $registry['products'][ $product_id ] ?? null;
    return is_array( $entry ) ? $entry : null;
}

function bactive_catalog_visuals_palette( $registry, $product_id ) {
    $entry = bactive_catalog_visuals_entry( $registry, $product_id );
    if ( ! $entry || true !== ( $entry['reviewed'] ?? false ) ) {
        return array();
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
    return $palette;
}

function bactive_catalog_visuals_config( $registry, $product_id ) {
    $entry = bactive_catalog_visuals_entry( $registry, $product_id );
    if ( ! $entry || true !== ( $registry['enabled'] ?? false ) || true !== ( $entry['enabled'] ?? false ) ) {
        return null;
    }
    return array( 'schemaVersion' => 1, 'version' => $registry['version'],
        'productId' => $product_id, 'palette' => (object) bactive_catalog_visuals_palette( $registry, $product_id ) );
}

function bactive_enqueue_catalog_visuals() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }
    $product = wc_get_product( get_queried_object_id() );
    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return;
    }
    $config = bactive_catalog_visuals_config( bactive_catalog_visuals_registry(), $product->get_id() );
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

function bactive_catalog_body_classes( $classes ) {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return $classes;
    }
    $product = wc_get_product( get_queried_object_id() );
    if ( $product && $product->is_type( 'variable' )
        && bactive_catalog_visuals_config( bactive_catalog_visuals_registry(), $product->get_id() ) ) {
        $classes[] = 'bactive-product-page';
    }
    return $classes;
}
add_filter( 'body_class', 'bactive_catalog_body_classes' );

/** Serve the existing original in the large gallery; thumbnail strips stay small. */
function bactive_catalog_gallery_originals( $html ) {
    $product_id = function_exists( 'is_product' ) && is_product() ? get_queried_object_id() : 0;
    // Blocksy's native Reset endpoint renders a fresh gallery outside a product query.
    if ( ! $product_id && function_exists( 'wp_doing_ajax' ) && wp_doing_ajax()
        && function_exists( 'doing_action' )
        && ( doing_action( 'wp_ajax_blocksy_get_product_view_for_variation' )
            || doing_action( 'wp_ajax_nopriv_blocksy_get_product_view_for_variation' ) ) ) {
        $requested_id = $_GET['product_id'] ?? null;
        $validated_id = is_string( $requested_id ) && preg_match( '/\A[1-9][0-9]*\z/', $requested_id )
            ? filter_var( $requested_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
        $product = $validated_id ? wc_get_product( $validated_id ) : false;
        if ( $product && 'publish' === $product->get_status() && $product->is_type( 'variable' ) ) {
            $product_id = $product->get_id();
        }
    }
    if ( ! bactive_catalog_visuals_config( bactive_catalog_visuals_registry(), $product_id ) ) {
        return $html;
    }
    return bactive_catalog_normalize_gallery_originals( $html );
}

/** Reuse for server-loaded Blocksy galleries, where is_product() is false. */
function bactive_catalog_normalize_gallery_originals( $html ) {
    if ( ! is_string( $html ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
        return $html;
    }
    $tags = new WP_HTML_Tag_Processor( $html );
    $original = null;
    while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
        if ( 'FIGURE' === $tags->get_tag() ) {
            $original = $tags->is_tag_closer() ? null : $tags->get_attribute( 'data-src' );
        }
        if ( 'IMG' !== $tags->get_tag() ) {
            continue;
        }
        // Blocksy exposes the original on its figure; native Woo exposes it on img.
        $src = $original ?: $tags->get_attribute( 'data-large_image' );
        if ( ! is_string( $src ) || ! preg_match( '~\Ahttps?://~i', $src ) ) {
            continue;
        }
        $tags->set_attribute( 'src', $src );
        $tags->remove_attribute( 'srcset' );
        $tags->remove_attribute( 'sizes' );
    }
    return $tags->get_updated_html();
}
add_filter( 'woocommerce_single_product_image_thumbnail_html', 'bactive_catalog_gallery_originals', 30 );

/** Keep Woo and Blocksy's first-slide reset on the existing full originals. */
function bactive_catalog_variation_original( $data, $product ) {
    if ( ! bactive_catalog_visuals_config( bactive_catalog_visuals_registry(), $product->get_id() ) ) {
        return $data;
    }
    // The product object supplies the release gate even during native AJAX lookups.
    if ( isset( $data['blocksy_gallery_html'] ) && is_string( $data['blocksy_gallery_html'] ) ) {
        $data['blocksy_gallery_html'] = bactive_catalog_normalize_gallery_originals( $data['blocksy_gallery_html'] );
    }
    // Blocksy restores its separate original payload when returning to a gallery slide.
    foreach ( array( 'image', 'blocksy_original_image' ) as $key ) {
        $image = $data[ $key ] ?? null;
        if ( ! is_array( $image )
            || ! is_string( $image['full_src'] ?? null )
            || ! preg_match( '~\Ahttps?://~i', $image['full_src'] )
            || ! is_int( $image['full_src_w'] ?? null ) || $image['full_src_w'] < 1
            || ! is_int( $image['full_src_h'] ?? null ) || $image['full_src_h'] < 1 ) {
            continue;
        }
        $data[ $key ]['src'] = $image['full_src'];
        $data[ $key ]['src_w'] = $image['full_src_w'];
        $data[ $key ]['src_h'] = $image['full_src_h'];
        $data[ $key ]['srcset'] = '';
        $data[ $key ]['sizes'] = '';
    }
    return $data;
}
add_filter( 'woocommerce_available_variation', 'bactive_catalog_variation_original', 30, 2 );
