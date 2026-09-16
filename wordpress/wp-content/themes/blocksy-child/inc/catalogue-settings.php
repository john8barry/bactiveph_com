<?php
/** Native catalogue metadata. Presentation never owns inventory or variation matching. */
defined( 'ABSPATH' ) || exit;

function bactive_catalogue_defaults() {
    $value = get_option( 'bactive_catalogue_defaults', array() );
    return is_array( $value ) && 1 === ( $value['schema_version'] ?? null )
        && is_string( $value['version'] ?? null )
        && preg_match( '/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z/', $value['version'] ) ? $value : array();
}

function bactive_catalogue_feature( $feature ) {
    $settings = bactive_catalogue_defaults();
    return in_array( $feature, array( 'layout', 'cards', 'selectors', 'image_quality', 'editor' ), true )
        && true === ( $settings['enabled'] ?? false ) && true === ( $settings[ $feature ] ?? false );
}

function bactive_catalogue_held( $id ) {
    // Removing a hold requires a separate catalogue repair and review, never an editor save.
    // Sculpt 238 now uses explicit merchant-assigned colours; the obsolete
    // Almond/Stone hold must not suppress those colours or future editor work.
    return in_array( (int) $id, array( 56, 160, 211, 148, 347 ), true );
}

function bactive_catalogue_native( $id ) {
    return 'native' === get_post_meta( $id, '_bactive_layout_mode', true );
}

function bactive_catalogue_product_colours( $product ) {
    if ( ! $product instanceof WC_Product ) { return array(); }
    $rows = array();
    foreach ( $product->get_attributes() as $attribute ) {
        if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->is_taxonomy()
            || ! in_array( $attribute->get_name(), array( 'pa_colour', 'pa_color' ), true ) ) { continue; }
        foreach ( $attribute->get_options() as $id ) {
            $term = get_term( (int) $id, $attribute->get_name() );
            if ( ! $term || is_wp_error( $term ) ) { continue; }
            $key = $term->taxonomy . ':' . $term->term_id;
            $rows[ $key ] = array( 'key' => $key, 'taxonomy' => $term->taxonomy,
                'term_id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name );
        }
    }
    return $rows;
}

function bactive_catalogue_hex( $value ) {
    return is_string( $value ) && preg_match( '/\A#[a-fA-F0-9]{6}\z/', $value ) ? strtolower( $value ) : '';
}

function bactive_catalogue_product_settings( $product ) {
    $raw = $product->get_meta( '_bactive_colour_settings', true );
    $out = array( 'schema_version' => 1, 'colours' => array() );
    if ( ! is_array( $raw ) || 1 !== ( $raw['schema_version'] ?? null ) || ! is_array( $raw['colours'] ?? null ) ) { return $out; }
    foreach ( bactive_catalogue_product_colours( $product ) as $key => $row ) {
        $entry = $raw['colours'][ $key ] ?? null;
        if ( ! is_array( $entry ) || ! in_array( $entry['mode'] ?? null, array( 'inherit', 'custom', 'none' ), true ) ) { continue; }
        $out['colours'][ $key ] = array( 'mode' => $entry['mode'], 'hex' => bactive_catalogue_hex( $entry['hex'] ?? '' ),
            'preview_image_id' => is_int( $entry['preview_image_id'] ?? null ) && $entry['preview_image_id'] > 0 ? $entry['preview_image_id'] : 0,
            'review' => is_string( $entry['review'] ?? null ) && preg_match( '/\A[a-f0-9]{64}\z/', $entry['review'] ) ? $entry['review'] : '' );
    }
    return $out;
}

function bactive_catalogue_effective_hex( $product, $row ) {
    if ( bactive_catalogue_held( $product->get_id() ) ) { return ''; }
    $entry = bactive_catalogue_product_settings( $product )['colours'][ $row['key'] ] ?? null;
    if ( $entry && 'none' === $entry['mode'] ) { return ''; }
    if ( $entry && 'custom' === $entry['mode'] ) { return $entry['hex']; }
    // Preserve old approved shades until the journalled metadata migration is applied.
    $stored = $product->get_meta( '_bactive_colour_settings', true );
    $migrated = is_array( $stored ) && 1 === ( $stored['schema_version'] ?? null ) && is_array( $stored['colours'] ?? null );
    if ( ! $entry && ! $migrated && function_exists( 'bactive_catalog_visuals_registry' ) ) {
        $legacy = bactive_catalog_visuals_entry( bactive_catalog_visuals_registry(), $product->get_id() );
        $shade = $legacy['palette'][ 'attribute_' . $row['taxonomy'] ][ $row['slug'] ] ?? null;
        if ( true === ( $legacy['reviewed'] ?? false ) && true === ( $shade['approved'] ?? false )
            && $row['term_id'] === ( $shade['term_id'] ?? null ) ) { return bactive_catalogue_hex( $shade['hex'] ?? '' ); }
        // An intentional legacy omission must not inherit another garment's shade.
        if ( true === ( $legacy['reviewed'] ?? false ) ) { return ''; }
    }
    return bactive_catalogue_hex( get_term_meta( $row['term_id'], '_bactive_colour_hex', true ) );
}

function bactive_catalogue_variations( $product ) {
    $id = $product->get_id();
    if ( isset( $GLOBALS['bactive_catalogue_children'][ $id ] ) ) { return $GLOBALS['bactive_catalogue_children'][ $id ]; }
    $rows = array();
    if ( $product->is_type( 'variable' ) ) {
        foreach ( $product->get_children() as $vid ) {
            $variation = wc_get_product( $vid );
            if ( ! $variation instanceof WC_Product_Variation || $variation->get_parent_id() !== $id ) { continue; }
            $attributes = $variation->get_attributes(); ksort( $attributes );
            $rows[] = array( 'id' => (int) $vid, 'attributes' => $attributes,
                'image_id' => (int) $variation->get_image_id( 'edit' ), 'status' => $variation->get_status() );
        }
    }
    usort( $rows, function ( $a, $b ) { return $a['id'] <=> $b['id']; } );
    return $GLOBALS['bactive_catalogue_children'][ $id ] = $rows;
}

function bactive_catalogue_attachment( $id ) {
    if ( ! is_int( $id ) || $id < 1 || ! wp_attachment_is_image( $id ) ) { return null; }
    $file = get_attached_file( $id ); $uploads = wp_get_upload_dir();
    $root = realpath( $uploads['basedir'] ); $path = $file ? realpath( $file ) : false;
    if ( ! $root || ! $path || is_link( $file ) || ! is_file( $path )
        || ! str_starts_with( $path, $root . DIRECTORY_SEPARATOR )
        || wp_normalize_path( $file ) !== wp_normalize_path( $path ) ) { return null; }
    $url = wp_get_attachment_url( $id );
    $base_url = wp_parse_url( $uploads['baseurl'] ); $image_url = is_string( $url ) ? wp_parse_url( $url ) : false;
    if ( ! $base_url || ! $image_url || ! in_array( $base_url['scheme'] ?? '', array( 'http', 'https' ), true ) ) { return null; }
    if ( 'https' !== $base_url['scheme'] && ( 'local' !== wp_get_environment_type()
        || ! in_array( $base_url['host'] ?? '', array( 'localhost', '127.0.0.1', '[::1]' ), true ) ) ) { return null; }
    foreach ( array( 'scheme', 'host', 'port' ) as $part ) {
        if ( ( $base_url[ $part ] ?? null ) !== ( $image_url[ $part ] ?? null ) ) { return null; }
    }
    foreach ( array( 'user', 'pass', 'query', 'fragment' ) as $part ) { if ( isset( $image_url[ $part ] ) ) { return null; } }
    $relative = substr( wp_normalize_path( $path ), strlen( wp_normalize_path( $root ) ) + 1 );
    if ( rawurldecode( $image_url['path'] ?? '' ) !== rtrim( rawurldecode( $base_url['path'] ?? '' ), '/' ) . '/' . $relative ) { return null; }
    clearstatcache( true, $path ); $stat = @stat( $path );
    if ( ! $stat || ! is_readable( $path ) ) { return null; }
    $key = $path . ':' . implode( ':', array_intersect_key( $stat, array_flip( array( 'dev', 'ino', 'mode', 'size', 'mtime', 'ctime' ) ) ) );
    if ( ! isset( $GLOBALS['bactive_catalogue_files'][ $key ] ) ) {
        $size = wp_getimagesize( $path );
        $hash = @hash_file( 'sha256', $path );
        clearstatcache( true, $path ); $after = @stat( $path );
        $GLOBALS['bactive_catalogue_files'][ $key ] = $size && $size[0] > 0 && $size[1] > 0
            && is_string( $hash ) && preg_match( '/\A[a-f0-9]{64}\z/', $hash ) && $after
            && $stat['ino'] === $after['ino'] && $stat['size'] === $after['size'] && $stat['mtime'] === $after['mtime'] && $stat['ctime'] === $after['ctime']
            ? array( 'width' => (int) $size[0], 'height' => (int) $size[1], 'sha256' => $hash ) : null;
    }
    $data = $GLOBALS['bactive_catalogue_files'][ $key ];
    return $data ? array_merge( array( 'id' => $id, 'url' => $url ), $data ) : null;
}

function bactive_catalogue_review_fingerprint( $product, $row, $preview_id ) {
    if ( bactive_catalogue_held( $product->get_id() ) || ! isset( bactive_catalogue_product_colours( $product )[ $row['key'] ] ) ) { return ''; }
    $preview = bactive_catalogue_attachment( $preview_id );
    if ( ! $preview ) { return ''; }
    $entries = array();
    foreach ( bactive_catalogue_variations( $product ) as $variation ) {
        if ( 'publish' !== $variation['status'] ) { continue; }
        $colour = $variation['attributes'][ $row['taxonomy'] ] ?? '';
        // Wildcard or missing colours remain ambiguous even with an explicit preview.
        if ( '' === $colour ) { return ''; }
        if ( $colour !== $row['slug'] ) { continue; }
        $image = $variation['image_id'] ? bactive_catalogue_attachment( $variation['image_id'] ) : null;
        if ( $variation['image_id'] && ! $image ) { return ''; }
        $entries[] = array( 'variation' => $variation, 'image_sha' => $image['sha256'] ?? null );
    }
    if ( $product->is_type( 'variable' ) && ! $entries ) { return ''; }
    return hash( 'sha256', wp_json_encode( array( 'product_id' => $product->get_id(), 'colour' => $row,
        'preview_id' => $preview_id, 'preview_sha' => $preview['sha256'], 'variations' => $entries ) ) );
}

/** Owned settings conflict independently of WooCommerce's AJAX variation saves. */
function bactive_catalogue_editor_stamp( $product ) {
    return hash( 'sha256', wp_json_encode( array( 'id' => $product->get_id(),
        'settings' => $product->get_meta( '_bactive_colour_settings', true ),
        'layout' => $product->get_meta( '_bactive_layout_mode', true ) ) ) );
}

function bactive_catalogue_product_stamp( $product ) {
    $files = array();
    $global_shades = array();
    foreach ( bactive_catalogue_product_colours( $product ) as $row ) {
        $global_shades[ $row['key'] ] = get_term_meta( $row['term_id'], '_bactive_colour_hex', true );
    }
    foreach ( bactive_catalogue_variations( $product ) as $variation ) {
        if ( $variation['image_id'] ) { $files[ $variation['image_id'] ] = bactive_catalogue_attachment( $variation['image_id'] )['sha256'] ?? null; }
    }
    foreach ( bactive_catalogue_product_settings( $product )['colours'] as $entry ) {
        if ( $entry['preview_image_id'] ) { $files[ $entry['preview_image_id'] ] = bactive_catalogue_attachment( $entry['preview_image_id'] )['sha256'] ?? null; }
    }
    ksort( $files );
    return hash( 'sha256', wp_json_encode( array( 'id' => $product->get_id(),
        'colours' => bactive_catalogue_product_colours( $product ), 'variations' => bactive_catalogue_variations( $product ),
        'image_bytes' => $files,
        'global_shades' => $global_shades,
        'settings' => $product->get_meta( '_bactive_colour_settings', true ),
        'layout' => $product->get_meta( '_bactive_layout_mode', true ) ) ) );
}

function bactive_catalogue_can_view( $product ) {
    $id = $product->get_id();
    if ( ! is_post_publicly_viewable( $id ) && ! current_user_can( 'read_post', $id ) ) { return false; }
    return ! post_password_required( $id ) || current_user_can( 'edit_post', $id );
}

function bactive_catalogue_reviewed_preview( $product, $row ) {
    if ( bactive_catalogue_held( $product->get_id() ) || ! bactive_catalogue_can_view( $product ) ) { return null; }
    $entry = bactive_catalogue_product_settings( $product )['colours'][ $row['key'] ] ?? null;
    if ( ! $entry || ! $entry['review'] ) { return null; }
    $current = bactive_catalogue_review_fingerprint( $product, $row, $entry['preview_image_id'] );
    return $current && hash_equals( $entry['review'], $current ) ? bactive_catalogue_attachment( $entry['preview_image_id'] ) : null;
}

/** A colour has an automatic preview only when every published size has one exact photo. */
function bactive_catalogue_automatic_preview( $product, $row ) {
    if ( bactive_catalogue_held( $product->get_id() ) || ! bactive_catalogue_can_view( $product )
        || ! $product->is_type( 'variable' ) || ! isset( bactive_catalogue_product_colours( $product )[ $row['key'] ] ) ) { return null; }
    $image_id = 0;
    $matches = 0;
    foreach ( bactive_catalogue_variations( $product ) as $variation ) {
        if ( 'publish' !== $variation['status'] ) { continue; }
        $colour = $variation['attributes'][ $row['taxonomy'] ] ?? '';
        if ( '' === $colour ) { return null; }
        if ( $colour !== $row['slug'] ) { continue; }
        if ( ! $variation['image_id'] || ( $image_id && $image_id !== $variation['image_id'] ) ) { return null; }
        $image_id = $variation['image_id'];
        ++$matches;
    }
    return $matches ? bactive_catalogue_attachment( $image_id ) : null;
}

/** One resolver serves product galleries, listing cards and related products. */
function bactive_catalogue_preview( $product, $row ) {
    return bactive_catalogue_reviewed_preview( $product, $row ) ?: bactive_catalogue_automatic_preview( $product, $row );
}

function bactive_catalogue_palette( $product ) {
    $palette = array();
    foreach ( bactive_catalogue_product_colours( $product ) as $row ) {
        $hex = bactive_catalogue_effective_hex( $product, $row );
        if ( $hex ) { $palette[ 'attribute_' . $row['taxonomy'] ][ $row['slug'] ] = $hex; }
    }
    return $palette;
}

function bactive_catalogue_previews( $product ) {
    $previews = array();
    foreach ( bactive_catalogue_product_colours( $product ) as $row ) {
        $image = bactive_catalogue_preview( $product, $row );
        if ( $image ) { $previews[ 'attribute_' . $row['taxonomy'] ][ $row['slug'] ] = array(
            'src' => $image['url'], 'width' => $image['width'], 'height' => $image['height'],
            'alt' => $product->get_name() . ' — ' . $row['name'] ); }
    }
    return $previews;
}

function bactive_catalogue_forget( $id ) {
    unset( $GLOBALS['bactive_catalogue_children'][ $id ] );
    $GLOBALS['bactive_catalogue_files'] = array();
    clearstatcache();
}

function bactive_catalogue_invalidate( $id ) {
    bactive_catalogue_forget( $id );
    if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients( $id ); }
    clean_post_cache( $id );
    do_action( 'litespeed_purge_post', $id );
    // Product, archive and related-card caches share these visual values.
    do_action( 'litespeed_purge_all' );
}

function bactive_catalogue_invalidate_variation( $id ) {
    $parent = wp_get_post_parent_id( $id );
    if ( $parent ) { bactive_catalogue_invalidate( $parent ); }
}
add_action( 'woocommerce_save_product_variation', 'bactive_catalogue_invalidate_variation' );
add_action( 'woocommerce_update_product_variation', 'bactive_catalogue_invalidate_variation' );
add_action( 'woocommerce_new_product_variation', 'bactive_catalogue_invalidate_variation' );
add_action( 'woocommerce_update_product', 'bactive_catalogue_invalidate' );
add_action( 'edit_attachment', function () {
    $GLOBALS['bactive_catalogue_files'] = array();
    do_action( 'litespeed_purge_all' );
} );

function bactive_catalogue_invalidate_colour_term( $term_id, $taxonomy ) {
    if ( ! in_array( $taxonomy, array( 'pa_colour', 'pa_color' ), true ) ) { return; }
    $ids = get_objects_in_term( (int) $term_id, $taxonomy );
    if ( is_wp_error( $ids ) ) { return; }
    foreach ( $ids as $id ) { bactive_catalogue_invalidate( (int) $id ); }
    do_action( 'litespeed_purge_all' );
}

/** Track actual REST callbacks, including method overrides and nested batch requests. */
function bactive_catalogue_rest_before( $response, $handler, $request ) {
    $GLOBALS['bactive_catalogue_rest_writes'][] = ! in_array( $request->get_method(), array( 'GET', 'HEAD' ), true )
        && (bool) preg_match( '~\A/wc/v[1-9][0-9]*/products(?:/|\z)~i', $request->get_route() );
    return $response;
}
function bactive_catalogue_rest_after( $response ) {
    if ( ! empty( $GLOBALS['bactive_catalogue_rest_writes'] ) ) { array_pop( $GLOBALS['bactive_catalogue_rest_writes'] ); }
    return $response;
}
add_filter( 'rest_request_before_callbacks', 'bactive_catalogue_rest_before', 0, 3 );
add_filter( 'rest_request_after_callbacks', 'bactive_catalogue_rest_after', PHP_INT_MAX, 1 );

/** View-only fallback keeps Woo payloads and Blocksy's native AJAX gallery in agreement. */
function bactive_catalogue_variation_image( $image_id, $variation ) {
    // Woo's REST editor also reads the view getter while saving gallery IDs.
    // Display fallbacks must never influence an administrative write/export.
    if ( in_array( true, $GLOBALS['bactive_catalogue_rest_writes'] ?? array(), true ) ) { return $image_id; }
    if ( is_admin() && ! ( doing_action( 'wp_ajax_blocksy_get_product_view_for_variation' )
        || doing_action( 'wp_ajax_nopriv_blocksy_get_product_view_for_variation' )
        || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && doing_action( 'wc_ajax_get_variation' ) ) ) ) { return $image_id; }
    if ( ! bactive_catalogue_feature( 'selectors' ) || ! $variation instanceof WC_Product_Variation
        || $variation->get_image_id( 'edit' ) || bactive_catalogue_native( $variation->get_parent_id() ) ) { return $image_id; }
    $product = wc_get_product( $variation->get_parent_id() );
    if ( ! $product ) { return $image_id; }
    $attributes = $variation->get_attributes();
    foreach ( bactive_catalogue_product_colours( $product ) as $row ) {
        if ( ( $attributes[ $row['taxonomy'] ] ?? null ) !== $row['slug'] ) { continue; }
        $image = bactive_catalogue_preview( $product, $row );
        if ( $image ) { return $image['id']; }
    }
    return $image_id;
}
add_filter( 'woocommerce_product_variation_get_image_id', 'bactive_catalogue_variation_image', 20, 2 );

// Copies retain usable choices but cannot inherit approval of a different product.
add_action( 'woocommerce_product_duplicate_before_save', function ( $duplicate ) {
    $settings = $duplicate->get_meta( '_bactive_colour_settings', true );
    if ( ! is_array( $settings ) || ! is_array( $settings['colours'] ?? null ) ) { return; }
    foreach ( $settings['colours'] as &$entry ) { if ( is_array( $entry ) ) { $entry['review'] = ''; } }
    unset( $entry ); $duplicate->update_meta_data( '_bactive_colour_settings', $settings );
} );
