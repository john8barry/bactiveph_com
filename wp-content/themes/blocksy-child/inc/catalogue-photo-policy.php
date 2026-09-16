<?php
/** Product-only photo assignments. Uploads for other uses remain unrestricted. */
defined( 'ABSPATH' ) || exit;

function bactive_catalogue_photo_role( $id ) {
    $role = get_post_meta( $id, '_bactive_photo_role', true );
    return in_array( $role, array( 'detail', 'comparison' ), true ) ? $role : 'portrait';
}

function bactive_catalogue_photo_error( $id, $message ) {
    return new WP_Error( 'bactive_product_photo', sprintf( __( 'Photo #%1$d: %2$s', 'blocksy-child' ), $id, $message ), array( 'status' => 400 ) );
}

/** Decode the complete local file. Do not trust an extension or attachment metadata. */
function bactive_catalogue_photo_decode( $id, $image ) {
    $path = get_attached_file( $id );
    $key = $id . ':' . $image['sha256'];
    if ( isset( $GLOBALS['bactive_photo_decoded'][ $key ] ) ) { return $GLOBALS['bactive_photo_decoded'][ $key ]; }
    $size = @getimagesize( $path );
    if ( ! $size || ! in_array( $size['mime'] ?? '', array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
        return bactive_catalogue_photo_error( $id, __( 'Use a readable JPEG, PNG or WebP file.', 'blocksy-child' ) );
    }
    // Bound decoder memory before touching the pixel payload, including images with huge headers.
    $pixels = $image['width'] * $image['height'];
    clearstatcache( true, $path );
    $bytes = @filesize( $path );
    if ( ! is_int( $bytes ) || $bytes < 1 ) {
        return bactive_catalogue_photo_error( $id, __( 'The file changed during validation. Try again.', 'blocksy-child' ) );
    }
    $limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
    if ( $pixels > 24000000 || $bytes > 32 * 1024 * 1024
        || ( $limit > 0 && memory_get_usage( true ) + $pixels * 8 + $bytes * 2 + 16 * 1024 * 1024 > $limit ) ) {
        return bactive_catalogue_photo_error( $id, __( 'Export a smaller file (at most 24 megapixels and 32 MB), keeping the required resolution.', 'blocksy-child' ) );
    }
    if ( ! function_exists( 'imagecreatefromstring' ) ) {
        return bactive_catalogue_photo_error( $id, __( 'The server image decoder is unavailable. Ask the site administrator to enable GD.', 'blocksy-child' ) );
    }
    $bytes_data = @file_get_contents( $path, false, null, 0, $bytes + 1 );
    if ( ! is_string( $bytes_data ) || strlen( $bytes_data ) !== $bytes || ! hash_equals( $image['sha256'], hash( 'sha256', $bytes_data ) ) ) {
        return bactive_catalogue_photo_error( $id, __( 'The file changed during validation. Try again.', 'blocksy-child' ) );
    }
    $decode_warning = false;
    set_error_handler( function () use ( &$decode_warning ) { $decode_warning = true; return true; } );
    try { $decoded = imagecreatefromstring( $bytes_data ); }
    finally { restore_error_handler(); }
    unset( $bytes_data );
    if ( $decode_warning || ! $decoded || imagesx( $decoded ) !== $image['width'] || imagesy( $decoded ) !== $image['height'] ) {
        if ( $decoded ) { imagedestroy( $decoded ); }
        return bactive_catalogue_photo_error( $id, __( 'The image cannot be fully decoded. Export it again.', 'blocksy-child' ) );
    }
    $opaque = true;
    if ( 'image/jpeg' !== $size['mime'] ) {
        $truecolour = imageistruecolor( $decoded );
        for ( $y = 0; $y < $image['height'] && $opaque; ++$y ) {
            for ( $x = 0; $x < $image['width']; ++$x ) {
                $colour = imagecolorat( $decoded, $x, $y );
                $alpha = $truecolour ? ( ( $colour >> 24 ) & 0x7f ) : imagecolorsforindex( $decoded, $colour )['alpha'];
                if ( $alpha ) { $opaque = false; break; }
            }
        }
    }
    imagedestroy( $decoded );
    if ( ! $opaque ) { return bactive_catalogue_photo_error( $id, __( 'Flatten transparency onto the intended photo background before using this product photo.', 'blocksy-child' ) ); }
    if ( 'image/jpeg' === $size['mime'] && function_exists( 'exif_read_data' ) ) {
        $exif = @exif_read_data( $path );
        if ( ! empty( $exif['Orientation'] ) && 1 !== (int) $exif['Orientation'] ) {
            return bactive_catalogue_photo_error( $id, __( 'Apply the camera orientation and export the upright image before use.', 'blocksy-child' ) );
        }
    }
    return $GLOBALS['bactive_photo_decoded'][ $key ] = true;
}

/** Public internal contract: attachment data or WP_Error; review bypass is for review UI only. */
function bactive_catalogue_photo_validate( $id, $context = 'portrait', $require_review = true ) {
    $image = bactive_catalogue_attachment( $id );
    if ( ! $image ) { return bactive_catalogue_photo_error( $id, __( 'Choose a readable image stored in this site’s Media Library.', 'blocksy-child' ) ); }
    $role = bactive_catalogue_photo_role( $id );
    $exception = 'gallery' === $context && in_array( $role, array( 'detail', 'comparison' ), true );
    $spec = $exception ? 'at least 800 × 800 pixels' : '2:3 portrait, at least 800 × 1200 pixels (1024 × 1536 preferred)';
    if ( $image['width'] < 800 || $image['height'] < ( $exception ? 800 : 1200 )
        || ( ! $exception && ( 'portrait' !== $role || abs( $image['width'] * 3 - $image['height'] * 2 ) * 1000 > $image['height'] * 10 ) ) ) {
        return bactive_catalogue_photo_error( $id, sprintf( __( 'This file is %1$d × %2$d. Required: %3$s. Detail/comparison exceptions are gallery-only.', 'blocksy-child' ), $image['width'], $image['height'], $spec ) );
    }
    $decoded = bactive_catalogue_photo_decode( $id, $image );
    if ( is_wp_error( $decoded ) ) { return $decoded; }
    $review = get_post_meta( $id, '_bactive_photo_review', true );
    if ( $require_review && ( ! is_array( $review ) || 1 !== ( $review['schema_version'] ?? null )
        || $role !== ( $review['role'] ?? null ) || ! is_string( $review['sha256'] ?? null )
        || ! hash_equals( $image['sha256'], $review['sha256'] ) ) ) {
        return bactive_catalogue_photo_error( $id, __( 'Open this image in Media Library and confirm its product-photo framing review. Replacement files need a new review.', 'blocksy-child' ) );
    }
    return $image;
}

function bactive_catalogue_photo_notice( $error ) {
    if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) { WC_Admin_Meta_Boxes::add_error( $error->get_error_message() ); }
    $GLOBALS['bactive_photo_errors'][] = $error->get_error_message();
}

function bactive_catalogue_photo_ids( $value ) {
    return array_values( array_filter( array_map( 'absint', is_array( $value ) ? $value : explode( ',', (string) $value ) ) ) );
}

/** Compare assignments, not a whole old catalogue: unchanged legacy choices survive maintenance. */
function bactive_catalogue_photo_assignment_error( $key, $value, $old ) {
    $next = array(); $before = array(); $context = 'portrait';
    if ( '_thumbnail_id' === $key && ( ! is_scalar( $value ) || ! preg_match( '/\A[0-9]*\z/', (string) $value ) ) ) {
        return bactive_catalogue_photo_error( 0, 'Choose a numeric Media Library attachment ID.' );
    }
    if ( '_product_image_gallery' === $key && ( ! is_string( $value ) || ! preg_match( '/\A(?:[0-9]+(?:,[0-9]+)*)?\z/', $value ) ) ) {
        return bactive_catalogue_photo_error( 0, 'Gallery images must be comma-separated Media Library attachment IDs.' );
    }
    if ( '_thumbnail_id' === $key ) {
        $next = array( (int) $value ); $before = array( (int) $old );
    } elseif ( '_product_image_gallery' === $key ) {
        $next = bactive_catalogue_photo_ids( $value ); $before = bactive_catalogue_photo_ids( $old ); $context = 'gallery';
    } elseif ( '_bactive_colour_settings' === $key ) {
        foreach ( is_array( $value ) ? ( $value['colours'] ?? array() ) : array() as $colour => $row ) {
            $raw = is_array( $row ) ? ( $row['preview_image_id'] ?? 0 ) : null;
            if ( ! is_scalar( $raw ) || ! preg_match( '/\A[0-9]+\z/', (string) $raw ) ) { return bactive_catalogue_photo_error( 0, 'Choose a numeric preview attachment ID.' ); }
            $id = (int) $raw;
            if ( $id && $id !== (int) ( $old['colours'][ $colour ]['preview_image_id'] ?? 0 ) ) { $next[] = $id; }
        }
    } else { return null; }
    foreach ( array_diff( $next, $before, array( 0 ) ) as $id ) {
        $result = bactive_catalogue_photo_validate( $id, $context );
        if ( is_wp_error( $result ) ) { return $result; }
    }
    return null;
}

/** Backstop update_post_meta/add_post_meta and native product assignment helpers. */
function bactive_catalogue_photo_meta_guard( $check, $id, $key, $value ) {
    if ( null !== $check || ! in_array( get_post_type( $id ), array( 'product', 'product_variation' ), true ) ) { return $check; }
    $error = bactive_catalogue_photo_assignment_error( $key, $value, get_post_meta( $id, $key, true ) );
    if ( ! $error ) { return $check; }
    bactive_catalogue_photo_notice( $error );
    return false;
}
add_filter( 'update_post_metadata', 'bactive_catalogue_photo_meta_guard', 20, 4 );
add_filter( 'add_post_metadata', 'bactive_catalogue_photo_meta_guard', 20, 4 );

/** Pending object assignment errors, before Woo writes any product fields. */
function bactive_catalogue_photo_object_errors( $product ) {
    $id = $product->get_id(); $errors = array();
    $values = array( '_thumbnail_id' => $product->get_image_id( 'edit' ), '_product_image_gallery' => implode( ',', $product->get_gallery_image_ids( 'edit' ) ),
        '_bactive_colour_settings' => $product->get_meta( '_bactive_colour_settings', true, 'edit' ) );
    foreach ( $values as $key => $value ) {
        $error = bactive_catalogue_photo_assignment_error( $key, $value, $id ? get_post_meta( $id, $key, true ) : '' );
        if ( $error ) { $errors[ $key ] = $error; }
    }
    if ( ! $product->is_type( 'variation' ) && in_array( $product->get_status( 'edit' ), array( 'publish', 'future' ), true )
        && ( ! empty( $GLOBALS['bactive_photo_new_publication'][ $id ] ) || 'publish' !== ( $id ? get_post_status( $id ) : '' ) ) ) {
        $required = bactive_catalogue_photo_publication_error( $product );
        if ( $required ) { $errors['publication'] = $required; }
    }
    return $errors;
}

/** New publication validates every existing choice, including legacy choices on a draft. */
function bactive_catalogue_photo_publication_error( $product ) {
    $assignments = array( array( (int) $product->get_image_id( 'edit' ), 'portrait' ) );
    foreach ( $product->get_gallery_image_ids( 'edit' ) as $id ) { $assignments[] = array( (int) $id, 'gallery' ); }
    $settings = $product->get_meta( '_bactive_colour_settings', true, 'edit' );
    foreach ( is_array( $settings ) ? ( $settings['colours'] ?? array() ) : array() as $row ) {
        if ( ! empty( $row['preview_image_id'] ) ) { $assignments[] = array( (int) $row['preview_image_id'], 'portrait' ); }
    }
    if ( $product->is_type( 'variable' ) ) {
        foreach ( $product->get_children() as $vid ) {
            $variation = wc_get_product( $vid );
            if ( $variation && 'publish' === $variation->get_status( 'edit' ) && (int) $variation->get_image_id( 'edit' ) ) {
                $assignments[] = array( (int) $variation->get_image_id( 'edit' ), 'portrait' );
            }
        }
    }
    foreach ( $assignments as list( $id, $context ) ) {
        $result = bactive_catalogue_photo_validate( $id, $context );
        if ( is_wp_error( $result ) ) { return $result; }
    }
    return null;
}

/** Ordinary native/AJAX saves retain prior images and save unrelated inventory edits. */
function bactive_catalogue_photo_object_guard( $product ) {
    $id = $product->get_id();
    foreach ( bactive_catalogue_photo_object_errors( $product ) as $key => $error ) {
        if ( '_thumbnail_id' === $key ) { $product->set_image_id( (int) get_post_meta( $id, $key, true ) ); }
        elseif ( '_product_image_gallery' === $key ) { $product->set_gallery_image_ids( bactive_catalogue_photo_ids( get_post_meta( $id, $key, true ) ) ); }
        elseif ( '_bactive_colour_settings' === $key ) { $product->update_meta_data( $key, get_post_meta( $id, $key, true ) ); }
        elseif ( 'publication' === $key ) { $product->set_status( 'draft' ); }
        bactive_catalogue_photo_notice( $error );
    }
    $GLOBALS['bactive_photo_saving_product'] = $product;
}
add_action( 'woocommerce_before_product_object_save', 'bactive_catalogue_photo_object_guard', 90 );
add_action( 'woocommerce_after_product_object_save', function ( $product ) { unset( $GLOBALS['bactive_photo_saving_product'], $GLOBALS['bactive_photo_new_publication'][ $product->get_id() ] ); }, 90 );

/** Validate raw REST image choices before Woo can upload files or save other fields. */
function bactive_catalogue_photo_request_guard( $response, $handler, $request ) {
    if ( is_wp_error( $response ) || in_array( $request->get_method(), array( 'GET', 'HEAD', 'OPTIONS' ), true )
        || ! preg_match( '~\A/(?:wc/v[1-9][0-9]*/products|wp/v2/products?)(?:/|\z)~', $request->get_route() ) ) { return $response; }
    $params = $request->get_params();
    $rows = array( $params );
    // Preflight the entire envelope, before batch dispatch can mutate an earlier valid row.
    if ( str_ends_with( $request->get_route(), '/batch' ) ) {
        $rows = array();
        foreach ( array( 'create', 'update' ) as $operation ) {
            foreach ( is_array( $params[ $operation ] ?? null ) ? $params[ $operation ] : array() as $row ) {
                if ( is_array( $row ) ) { $rows[] = $row; }
            }
        }
    }
    $parents = $rows;
    // v1 embeds variations in the parent request. Check them before any parent save.
    foreach ( $parents as $parent ) {
        foreach ( is_array( $parent['variations'] ?? null ) ? $parent['variations'] : array() as $variation ) {
            if ( is_array( $variation ) ) { $rows[] = $variation; }
        }
    }
    preg_match( '~\A/wc/v([0-9]+)/products(?:/|\z)~', $request->get_route(), $version_match );
    $version = (int) ( $version_match[1] ?? 3 );
    foreach ( $rows as $row ) {
        $id = (int) ( $row['id'] ?? 0 );
        $single = isset( $row['image'] ) || isset( $row['featured_media'] );
        $single_image = $row['image'] ?? array();
        if ( 1 === $version && is_array( $single_image ) && array_is_list( $single_image ) && $single_image ) { $single_image = reset( $single_image ); }
        $images = $row['images'] ?? ( isset( $row['image'] ) ? array( $single_image ) : array() );
        if ( isset( $row['featured_media'] ) ) { $images = array( array( 'id' => $row['featured_media'] ) ); }
        if ( ! is_array( $images ) ) { return bactive_catalogue_photo_error( 0, 'Images must be a list of Media Library attachment IDs.' ); }
        $assignments = array(); $positions = array();
        foreach ( $images as $index => $image ) {
            $raw = is_array( $image ) ? ( $image['id'] ?? null ) : null;
            if ( ! is_scalar( $raw ) || ! preg_match( '/\A[0-9]+\z/', (string) $raw ) || ( 0 === (int) $raw && ! empty( $image['src'] ) ) ) {
                return bactive_catalogue_photo_error( 0, 'Upload and review the photo in Media Library first, then assign its numeric attachment ID.' );
            }
            if ( 2 === $version && ! $single ) {
                // v2 deduplicates by ID (last position wins), then sorts; the first ID is main.
                $positions[ (int) $raw ] = absint( $image['position'] ?? $index );
            } else {
                $main = $single || ( 1 === $version ? isset( $image['position'] ) && 0 === absint( $image['position'] ) : 0 === (int) $index );
                $assignments[] = array( (int) $raw, $main ? '_thumbnail_id' : '_product_image_gallery' );
            }
        }
        if ( $positions ) {
            asort( $positions );
            $ordered = array_keys( $positions );
            foreach ( $ordered as $index => $image_id ) {
                $assignments[] = array( $image_id, 0 === $index ? '_thumbnail_id' : '_product_image_gallery' );
            }
        }
        foreach ( $assignments as list( $image_id, $key ) ) {
            $value = '_thumbnail_id' === $key ? $image_id : (string) $image_id;
            $error = bactive_catalogue_photo_assignment_error( $key, $value, $id ? get_post_meta( $id, $key, true ) : '' );
            if ( $error ) { return $error; }
        }
        foreach ( $row['meta_data'] ?? array() as $meta ) {
            if ( ! is_array( $meta ) || ! isset( $meta['key'], $meta['value'] ) ) { continue; }
            $error = bactive_catalogue_photo_assignment_error( $meta['key'], $meta['value'], $id ? get_post_meta( $id, $meta['key'], true ) : '' );
            if ( $error ) { return $error; }
        }
        foreach ( $row['meta'] ?? array() as $key => $value ) {
            $error = bactive_catalogue_photo_assignment_error( $key, $value, $id ? get_post_meta( $id, $key, true ) : '' );
            if ( $error ) { return $error; }
        }
    }
    return $response;
}
add_filter( 'rest_request_before_callbacks', 'bactive_catalogue_photo_request_guard', 20, 3 );

function bactive_catalogue_photo_rest_guard( $product ) {
    if ( is_wp_error( $product ) ) { return $product; }
    $errors = bactive_catalogue_photo_object_errors( $product );
    return $errors ? reset( $errors ) : $product;
}
add_filter( 'woocommerce_rest_pre_insert_product_object', 'bactive_catalogue_photo_rest_guard', 90 );
add_filter( 'woocommerce_rest_pre_insert_product_variation_object', 'bactive_catalogue_photo_rest_guard', 90 );
/** CSV rows must be checked before Woo's image helper performs an intermediate save. */
function bactive_catalogue_photo_import_attachment( $value ) {
    if ( ! is_scalar( $value ) ) { throw new Exception( 'Choose an existing reviewed Media Library image.', 400 ); }
    $value = trim( (string) $value );
    if ( '' === $value ) { return 0; }
    if ( preg_match( '/\A[0-9]+\z/', $value ) ) { return (int) $value; }
    if ( false !== strpos( $value, '://' ) ) {
        $id = attachment_url_to_postid( $value );
        $image = $id ? bactive_catalogue_attachment( (int) $id ) : null;
        if ( $image && $image['url'] === $value ) { return (int) $id; }
    } else {
        // Exact metadata comparison, never the importer's regex/suffix match.
        $ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids',
            'posts_per_page' => 2, 'meta_key' => '_wp_attached_file', 'meta_value' => $value ) );
        if ( 1 === count( $ids ) ) { return (int) $ids[0]; }
    }
    throw new Exception( 'CSV photos must already exist in Media Library. Upload and review them first; use their exact local URL, upload-relative path, or attachment ID.', 400 );
}

function bactive_catalogue_photo_import_preflight( $data ) {
    $id = (int) ( $data['id'] ?? 0 );
    if ( ! $id && ! empty( $data['sku'] ) ) { $id = (int) wc_get_product_id_by_sku( $data['sku'] ); }
    if ( array_key_exists( 'raw_image_id', $data ) ) {
        $data['image_id'] = bactive_catalogue_photo_import_attachment( $data['raw_image_id'] );
        unset( $data['raw_image_id'] );
    }
    if ( array_key_exists( 'raw_gallery_image_ids', $data ) ) {
        if ( ! is_array( $data['raw_gallery_image_ids'] ) ) { throw new Exception( 'CSV gallery photos must be a list of existing Media Library images.', 400 ); }
        $data['gallery_image_ids'] = array_map( 'bactive_catalogue_photo_import_attachment', $data['raw_gallery_image_ids'] );
        unset( $data['raw_gallery_image_ids'] );
    }
    $changes = array();
    if ( isset( $data['image_id'] ) ) { $changes['_thumbnail_id'] = $data['image_id']; }
    if ( isset( $data['gallery_image_ids'] ) ) { $changes['_product_image_gallery'] = implode( ',', $data['gallery_image_ids'] ); }
    foreach ( $data['meta_data'] ?? array() as $meta ) {
        if ( is_array( $meta ) && isset( $meta['key'], $meta['value'] ) ) {
            $error = bactive_catalogue_photo_assignment_error( $meta['key'], $meta['value'], $id ? get_post_meta( $id, $meta['key'], true ) : '' );
            if ( $error ) { throw new Exception( $error->get_error_message(), 400 ); }
        }
    }
    foreach ( $changes as $key => $value ) {
        $error = bactive_catalogue_photo_assignment_error( $key, $value, $id ? get_post_meta( $id, $key, true ) : '' );
        if ( $error ) { throw new Exception( $error->get_error_message(), 400 ); }
    }
    return $data;
}
add_filter( 'woocommerce_product_import_process_item_data', 'bactive_catalogue_photo_import_preflight', 90 );

add_filter( 'woocommerce_product_import_pre_insert_product_object', function ( $product ) {
    $errors = bactive_catalogue_photo_object_errors( $product );
    // Woo's importer catches Exception and reports a failed CSV row. Native saves never throw.
    if ( $errors ) { throw new Exception( reset( $errors )->get_error_message(), 400 ); }
    return $product;
}, 90 );

/** Direct WordPress publication (including scheduled products) also needs a valid main photo. */
function bactive_catalogue_photo_publish_guard( $data, $postarr ) {
    if ( 'product' !== ( $data['post_type'] ?? '' ) || ! in_array( $data['post_status'] ?? '', array( 'publish', 'future' ), true ) ) { return $data; }
    $id = (int) ( $postarr['ID'] ?? 0 );
    if ( $id && 'publish' === get_post_status( $id ) ) { return $data; }
    $GLOBALS['bactive_photo_new_publication'][ $id ] = true;
    $candidate = $GLOBALS['bactive_photo_saving_product'] ?? null;
    $image_id = $candidate && $candidate->get_id() === $id ? $candidate->get_image_id( 'edit' ) : get_post_meta( $id, '_thumbnail_id', true );
    // Classic editor sets this image later in the same authenticated Woo save.
    if ( ! $candidate && isset( $_POST['_thumbnail_id'] ) && is_scalar( $_POST['_thumbnail_id'] )
        && current_user_can( 'edit_post', $id ) && isset( $_POST['woocommerce_meta_nonce'] )
        && is_string( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( $_POST['woocommerce_meta_nonce'], 'woocommerce_save_data' ) ) {
        $image_id = (int) $_POST['_thumbnail_id'];
    }
    $result = bactive_catalogue_photo_validate( (int) $image_id );
    if ( is_wp_error( $result ) ) { $data['post_status'] = 'draft'; bactive_catalogue_photo_notice( $result ); }
    if ( ! is_wp_error( $result ) && $id && ! $candidate && function_exists( 'wc_get_product' ) ) {
        $pending = wc_get_product( $id );
        if ( $pending ) {
            $pending->set_image_id( (int) $image_id );
            $error = bactive_catalogue_photo_publication_error( $pending );
            if ( $error ) { $data['post_status'] = 'draft'; bactive_catalogue_photo_notice( $error ); }
        }
    }
    return $data;
}
add_filter( 'wp_insert_post_data', 'bactive_catalogue_photo_publish_guard', 90, 2 );

/** Cron publishes with direct SQL; validate before delegating to the core callback. */
function bactive_catalogue_photo_scheduled_guard( $post_id ) {
    $id = is_object( $post_id ) ? (int) $post_id->ID : (int) $post_id;
    if ( 'product' !== get_post_type( $id ) || 'future' !== get_post_status( $id ) ) { return true; }
    $product = wc_get_product( $id );
    $error = $product ? bactive_catalogue_photo_publication_error( $product ) : bactive_catalogue_photo_error( 0, 'The scheduled product could not be validated.' );
    if ( ! $error ) { return true; }
    // Return false even if the status update fails: never call core publication on an error.
    wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
    update_post_meta( $id, '_bactive_photo_publication_error', $error->get_error_message() );
    bactive_catalogue_photo_notice( $error );
    return false;
}
function bactive_catalogue_photo_scheduled_publish( $post_id ) {
    if ( bactive_catalogue_photo_scheduled_guard( $post_id ) ) { check_and_publish_future_post( $post_id ); }
}
remove_action( 'publish_future_post', 'check_and_publish_future_post', 10 );
add_action( 'publish_future_post', 'bactive_catalogue_photo_scheduled_publish', 10 );

/** Uses WordPress's attachment form nonce plus a separate photo-review nonce and capability. */
function bactive_catalogue_photo_fields( $fields, $post ) {
    if ( ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( 'edit_products' ) || ! wp_attachment_is_image( $post->ID ) ) { return $fields; }
    $role = bactive_catalogue_photo_role( $post->ID );
    $html = '<select name="attachments[' . (int) $post->ID . '][bactive_photo_role]">';
    foreach ( array( 'portrait' => 'Product portrait', 'detail' => 'Gallery detail only', 'comparison' => 'Gallery comparison only' ) as $value => $label ) {
        $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $role, $value, false ) . '>' . esc_html( $label ) . '</option>';
    }
    $html .= '</select><input type="hidden" name="attachments[' . (int) $post->ID . '][bactive_photo_nonce]" value="' . esc_attr( wp_create_nonce( 'bactive_photo_' . $post->ID ) ) . '">';
    $fields['bactive_photo_role'] = array( 'label' => 'Product photo use', 'input' => 'html', 'html' => $html,
        'helps' => 'Portrait: 2:3, minimum 800 × 1200; preferred 1024 × 1536. Gallery detail/comparison: minimum 800 × 800. JPEG, PNG or WebP, upright and opaque.' );
    $reviewed = bactive_catalogue_photo_validate( (int) $post->ID, 'gallery' );
    $fields['bactive_photo_review'] = array( 'label' => 'Framing review', 'input' => 'html', 'html' => '<label><input type="checkbox" name="attachments[' . (int) $post->ID . '][bactive_photo_confirm]" value="1"> I inspected the full image: no added border/bars, the model and garment are intact, and colours/details are accurate.</label>',
        'helps' => is_wp_error( $reviewed ) ? $reviewed->get_error_message() : 'Approved for this exact file. A replaced file or changed role needs review again.' );
    return $fields;
}
add_filter( 'attachment_fields_to_edit', 'bactive_catalogue_photo_fields', 20, 2 );

function bactive_catalogue_photo_fields_save( $post, $attachment ) {
    $id = (int) $post['ID'];
    if ( ! isset( $attachment['bactive_photo_nonce'] ) || ! is_string( $attachment['bactive_photo_nonce'] )
        || ! wp_verify_nonce( $attachment['bactive_photo_nonce'], 'bactive_photo_' . $id )
        || ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'edit_products' ) ) { return $post; }
    $role = $attachment['bactive_photo_role'] ?? '';
    if ( ! in_array( $role, array( 'portrait', 'detail', 'comparison' ), true ) ) { return $post; }
    update_post_meta( $id, '_bactive_photo_role', $role );
    if ( '1' === ( $attachment['bactive_photo_confirm'] ?? '' ) ) {
        $result = bactive_catalogue_photo_validate( $id, 'gallery', false );
        if ( is_wp_error( $result ) ) { $post['errors']['bactive_photo_review']['errors'][] = $result->get_error_message(); }
        else { update_post_meta( $id, '_bactive_photo_review', array( 'schema_version' => 1, 'sha256' => $result['sha256'], 'role' => $role ) ); }
    }
    return $post;
}
add_filter( 'attachment_fields_to_save', 'bactive_catalogue_photo_fields_save', 20, 2 );
