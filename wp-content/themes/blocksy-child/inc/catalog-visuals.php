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

/** Private option only; disabled when absent. No attachment or inventory writes. */
function bactive_catalog_derivative_source( $source, $product_id ) {
    $config = get_option( 'bactive_catalog_lossless_release', array() );
    if ( ! is_string( $source ) || ! is_int( $product_id )
        || ! bactive_catalog_visuals_config( bactive_catalog_visuals_registry(), $product_id )
        || true !== ( bactive_catalog_visuals_entry( bactive_catalog_visuals_registry(), $product_id )['reviewed'] ?? false )
        || ! is_array( $config ) || 1 !== ( $config['schema_version'] ?? null )
        || true !== ( $config['enabled'] ?? false )
        || ! is_array( $config['product_ids'] ?? null )
        || ! in_array( $product_id, $config['product_ids'], true )
        || ! is_array( $config['assets'] ?? null ) || count( $config['assets'] ) > 64 ) { return $source; }
    $uploads = wp_upload_dir( null, false );
    if ( ! empty( $uploads['error'] ) || ! is_string( $uploads['basedir'] ?? null )
        || ! is_string( $uploads['baseurl'] ?? null ) ) { return $source; }
    $scheme = parse_url( $uploads['baseurl'], PHP_URL_SCHEME );
    // The isolated local WordPress instance uses loopback HTTP; production requires HTTPS.
    $local = function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type()
        && in_array( parse_url( $uploads['baseurl'], PHP_URL_HOST ), array( '127.0.0.1', 'localhost', '[::1]' ), true );
    if ( 'https' !== $scheme && ! ( 'http' === $scheme && $local ) ) { return $source; }
    $root = realpath( $uploads['basedir'] );
    if ( ! $root || $root !== $uploads['basedir'] || is_link( $root ) ) { return $source; }
    foreach ( $config['assets'] as $asset ) {
        if ( ! is_array( $asset ) || ! is_int( $asset['attachment_id'] ?? null ) || $asset['attachment_id'] < 1
            || ! is_array( $asset['product_ids'] ?? null ) || ! in_array( $product_id, $asset['product_ids'], true )
            || ! is_string( $asset['source_sha256'] ?? null ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $asset['source_sha256'] )
            || ! is_string( $asset['output_sha256'] ?? null ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $asset['output_sha256'] )
            || ! is_int( $asset['width'] ?? null ) || $asset['width'] < 1
            || ! is_int( $asset['height'] ?? null ) || $asset['height'] < 1 ) { continue; }
        $id = $asset['attachment_id'];
        if ( wp_get_attachment_url( $id ) !== $source ) { continue; }
        $original = get_attached_file( $id );
        $relative = '/bactive-lossless/' . $asset['source_sha256'] . '.webp';
        $output = $root . $relative;
        foreach ( array( $original, $output ) as $path ) {
            if ( ! is_string( $path ) || ! is_file( $path ) || ! is_readable( $path ) || is_link( $path )
                || realpath( $path ) !== $path || 0 !== strpos( $path, $root . '/' ) ) { return $source; }
        }
        // Request-local memo only. New requests always rehash both sources. Stat identity
        // revalidation catches ordinary replacement during a request; release files are immutable.
        clearstatcache( true, $original ); clearstatcache( true, $output );
        $fields = array_flip( array( 'dev', 'ino', 'mode', 'size', 'mtime', 'ctime' ) );
        $source_stat = @stat( $original ); $output_stat = @stat( $output );
        if ( ! $source_stat || ! $output_stat ) { return $source; }
        $key = hash( 'sha256', serialize( array( $asset, array_intersect_key( $source_stat, $fields ), array_intersect_key( $output_stat, $fields ) ) ) );
        static $checked = array();
        if ( ! array_key_exists( $key, $checked ) ) {
            $a = @getimagesize( $original ); $b = @getimagesize( $output );
            $source_hash = @hash_file( 'sha256', $original ); $output_hash = @hash_file( 'sha256', $output );
            $checked[ $key ] = is_string( $source_hash ) && is_string( $output_hash )
                && hash_equals( $asset['source_sha256'], $source_hash )
                && hash_equals( $asset['output_sha256'], $output_hash )
                && $a && $b && 'image/webp' === ( $b['mime'] ?? '' )
                && $a[0] === $asset['width'] && $a[1] === $asset['height']
                && $b[0] === $asset['width'] && $b[1] === $asset['height'];
        }
        return $checked[ $key ] ? rtrim( $uploads['baseurl'], '/' ) . $relative : $source;
    }
    return $source;
}

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
    return bactive_catalog_normalize_gallery_originals( $html, $product_id );
}

/** Reuse for server-loaded Blocksy galleries, where is_product() is false. */
function bactive_catalog_normalize_gallery_originals( $html, $product_id = 0 ) {
    if ( ! is_string( $html ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
        return $html;
    }
    $tags = new WP_HTML_Tag_Processor( $html );
    $original = null;
    while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
        if ( 'FIGURE' === $tags->get_tag() ) {
            $original = $tags->is_tag_closer() ? null : $tags->get_attribute( 'data-src' );
            if ( is_string( $original ) ) {
                $resolved = bactive_catalog_derivative_source( $original, $product_id );
                if ( $resolved !== $original ) { $tags->set_attribute( 'data-src', $resolved ); }
                $original = $resolved;
            }
        }
        // Native Woo's full-size anchor must agree with its img data-large_image.
        if ( 'A' === $tags->get_tag() && ! $tags->is_tag_closer() ) {
            $href = $tags->get_attribute( 'href' );
            if ( is_string( $href ) ) {
                $resolved = bactive_catalog_derivative_source( $href, $product_id );
                if ( $resolved !== $href ) { $tags->set_attribute( 'href', $resolved ); }
            }
        }
        if ( 'IMG' !== $tags->get_tag() ) {
            continue;
        }
        // Blocksy exposes the original on its figure; native Woo exposes it on img.
        $src = $original ?: $tags->get_attribute( 'data-large_image' );
        if ( ! is_string( $src ) || ! preg_match( '~\Ahttps?://~i', $src ) ) {
            continue;
        }
        $src = bactive_catalog_derivative_source( $src, $product_id );
        foreach ( array( 'data-large_image', 'data-src' ) as $attribute ) {
            $value = $tags->get_attribute( $attribute );
            if ( is_string( $value ) ) {
                $tags->set_attribute( $attribute, bactive_catalog_derivative_source( $value, $product_id ) );
            }
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
        $data['blocksy_gallery_html'] = bactive_catalog_normalize_gallery_originals( $data['blocksy_gallery_html'], $product->get_id() );
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
        $full = bactive_catalog_derivative_source( $image['full_src'], $product->get_id() );
        $data[ $key ]['full_src'] = $full;
        if ( isset( $image['url'] ) && $image['url'] === $image['full_src'] ) {
            $data[ $key ]['url'] = $full;
        }
        $data[ $key ]['src'] = $full;
        $data[ $key ]['src_w'] = $image['full_src_w'];
        $data[ $key ]['src_h'] = $image['full_src_h'];
        $data[ $key ]['srcset'] = '';
        $data[ $key ]['sizes'] = '';
    }
    return $data;
}
add_filter( 'woocommerce_available_variation', 'bactive_catalog_variation_original', 30, 2 );
