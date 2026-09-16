<?php
/** Native WooCommerce catalogue colour and photo controls. */
defined( 'ABSPATH' ) || exit;

function bactive_catalogue_editor_error( $message ) {
    if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) { WC_Admin_Meta_Boxes::add_error( $message ); }
}

/** All-or-nothing validation; no stock, price, attribute or variation writes. */
function bactive_catalogue_editor_validate( $product, $input, $pending = null ) {
    if ( ! is_array( $input ) || ! is_string( $input['stamp'] ?? null )
        || ! preg_match( '/\A[a-f0-9]{64}\z/', $input['stamp'] )
        || ( isset( $input['settings_stamp'] )
            ? ( ! is_string( $input['settings_stamp'] ) || ! hash_equals( bactive_catalogue_editor_stamp( $product ), $input['settings_stamp'] ) )
            : ! hash_equals( bactive_catalogue_product_stamp( $product ), $input['stamp'] ) ) ) {
        return new WP_Error( 'stale', __( 'Colours and photos were not saved because the product changed. Reload this page and review again.', 'blocksy-child' ) );
    }
    if ( '' === ( $input['colours'] ?? null ) ) { $input['colours'] = array(); }
    if ( ! in_array( $input['layout'] ?? null, array( 'auto', 'native' ), true ) || ! is_array( $input['colours'] ?? null ) ) {
        return new WP_Error( 'invalid', __( 'Colours and photos were not saved. Check the submitted settings.', 'blocksy-child' ) );
    }
    $target = $pending ?: $product;
    $allowed = bactive_catalogue_product_colours( $target );
    $mapping_changed = ! hash_equals( bactive_catalogue_product_stamp( $target ), $input['stamp'] );
    if ( ! $mapping_changed && ( array_diff( array_keys( $input['colours'] ), array_keys( $allowed ) ) || array_diff( array_keys( $allowed ), array_keys( $input['colours'] ) ) ) ) {
        return new WP_Error( 'terms', __( 'The colour list changed. Reload before saving colours and photos.', 'blocksy-child' ) );
    }
    $old = bactive_catalogue_product_settings( $product );
    $settings = array( 'schema_version' => 1, 'colours' => array() );
    foreach ( $allowed as $key => $row ) {
        $value = $input['colours'][ $key ] ?? $old['colours'][ $key ] ?? array( 'mode' => 'inherit', 'hex' => '', 'preview_image_id' => '0' );
        if ( ! is_array( $value ) || ! in_array( $value['mode'] ?? null, array( 'inherit', 'custom', 'none' ), true )
            || ! is_string( $value['hex'] ?? null ) || ( ! is_int( $value['preview_image_id'] ?? null ) && ! is_string( $value['preview_image_id'] ?? null ) ) ) {
            return new WP_Error( 'row', __( 'A colour setting is invalid. No colour settings were saved.', 'blocksy-child' ) );
        }
        $hex = trim( $value['hex'] );
        if ( ( '' !== $hex && ! preg_match( '/\A#[a-fA-F0-9]{6}\z/', $hex ) ) || ( 'custom' === $value['mode'] && '' === $hex ) ) {
            return new WP_Error( 'hex', __( 'Use a six-digit colour value, such as #A4C8EC.', 'blocksy-child' ) );
        }
        $raw_id = (string) $value['preview_image_id'];
        $id = filter_var( $raw_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
        if ( false === $id || ( $id && ! bactive_catalogue_attachment( $id ) ) ) {
            return new WP_Error( 'image', __( 'Choose an existing image from the media library.', 'blocksy-child' ) );
        }
        $next = array( 'mode' => $value['mode'], 'hex' => strtolower( $hex ), 'preview_image_id' => $id, 'review' => '' );
        $fingerprint = bactive_catalogue_review_fingerprint( $target, $row, $id );
        $previous = $old['colours'][ $key ] ?? array();
        $same = $next['mode'] === ( $previous['mode'] ?? null ) && $next['hex'] === strtolower( $previous['hex'] ?? '' ) && $id === ( $previous['preview_image_id'] ?? 0 );
        if ( ! bactive_catalogue_held( $product->get_id() ) && preg_match( '/\A[a-f0-9]{64}\z/', $fingerprint ) ) {
            if ( ( ! $mapping_changed && '1' === ( $value['confirm'] ?? null ) ) || ( $same && hash_equals( $fingerprint, $previous['review'] ?? '' ) ) ) { $next['review'] = $fingerprint; }
        }
        $settings['colours'][ $key ] = $next;
    }
    return array( 'settings' => $settings, 'layout' => $input['layout'], 'mapping_changed' => $mapping_changed );
}

function bactive_catalogue_editor_save( $product ) {
    $id = $product->get_id();
    if ( ! isset( $_POST['bactive_catalogue'] ) || ! current_user_can( 'edit_post', $id )
        || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id )
        || ! is_string( $_POST['bactive_catalogue_nonce'] ?? null )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bactive_catalogue_nonce'] ) ), 'bactive_catalogue_' . $id ) ) { return; }
    bactive_catalogue_forget( $id );
    $persisted = wc_get_product( $id );
    if ( ! $persisted ) { return; }
    $result = bactive_catalogue_editor_validate( $persisted, wp_unslash( $_POST['bactive_catalogue'] ), $product );
    if ( is_wp_error( $result ) ) { bactive_catalogue_editor_error( $result->get_error_message() ); return; }
    $product->update_meta_data( '_bactive_colour_settings', $result['settings'] );
    $product->update_meta_data( '_bactive_layout_mode', $result['layout'] );
    if ( $result['mapping_changed'] ) {
        bactive_catalogue_editor_error( __( 'Your colour settings were saved. Colours or size photos changed during editing, so new photo confirmations were not applied. Reopen Colours & photos, check the current photos, and confirm again.', 'blocksy-child' ) );
    }
    bactive_catalogue_invalidate( $id );
}
add_action( 'woocommerce_admin_process_product_object', 'bactive_catalogue_editor_save' );

function bactive_catalogue_editor_tabs( $tabs ) {
    $tabs['bactive_colours'] = array( 'label' => __( 'Colours & photos', 'blocksy-child' ), 'target' => 'bactive_catalogue_panel', 'class' => array(), 'priority' => 65 );
    return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'bactive_catalogue_editor_tabs' );

function bactive_catalogue_editor_display_photo( $selected_id, $automatic ) {
    return $selected_id ? bactive_catalogue_attachment( $selected_id ) : $automatic;
}

function bactive_catalogue_editor_panel() {
    global $product_object;
    $product = $product_object;
    if ( ! $product || ! current_user_can( 'edit_post', $product->get_id() ) ) { return; }
    $settings = bactive_catalogue_product_settings( $product );
    $rows = bactive_catalogue_product_colours( $product );
    echo '<div id="bactive_catalogue_panel" class="panel woocommerce_options_panel hidden"><div class="bactive-catalogue-editor">';
    wp_nonce_field( 'bactive_catalogue_' . $product->get_id(), 'bactive_catalogue_nonce' );
    echo '<input type="hidden" name="bactive_catalogue[stamp]" value="' . esc_attr( bactive_catalogue_product_stamp( $product ) ) . '">';
    echo '<input type="hidden" name="bactive_catalogue[settings_stamp]" value="' . esc_attr( bactive_catalogue_editor_stamp( $product ) ) . '">';
    echo '<h2>' . esc_html__( 'Colours & photos', 'blocksy-child' ) . '</h2><p>' . esc_html__( 'Set one shade for each colour. Sizes can keep different model photos. Preview photos do not replace variation photos.', 'blocksy-child' ) . '</p>';
    echo '<label for="bactive-layout">' . esc_html__( 'Product layout', 'blocksy-child' ) . '</label> <select id="bactive-layout" name="bactive_catalogue[layout]">';
    foreach ( array( 'auto' => __( 'Automatic enhancements', 'blocksy-child' ), 'native' => __( 'Native WooCommerce controls', 'blocksy-child' ) ) as $value => $label ) {
        echo '<option value="' . esc_attr( $value ) . '" ' . selected( $product->get_meta( '_bactive_layout_mode' ) ?: 'auto', $value, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    if ( bactive_catalogue_held( $product->get_id() ) ) { echo '<p class="notice notice-warning">' . esc_html__( 'This product has a catalogue mapping hold. Settings can be prepared, but review approval remains unavailable.', 'blocksy-child' ) . '</p>'; }
    if ( ! $rows ) { echo '<p>' . esc_html__( 'Add a global Colour attribute and save the product to configure its shades here.', 'blocksy-child' ) . '</p><input type="hidden" name="bactive_catalogue[colours]" value="">'; }
    foreach ( $rows as $key => $row ) {
        $value = $settings['colours'][ $key ] ?? array( 'mode' => 'inherit', 'hex' => '', 'preview_image_id' => 0, 'review' => '' );
        $prefix = 'bactive_catalogue[colours][' . $key . ']';
        $uid = 'bactive-colour-' . sanitize_html_class( $key );
        $fingerprint = bactive_catalogue_review_fingerprint( $product, $row, $value['preview_image_id'] );
        $reviewed = ! empty( $value['review'] ) && hash_equals( $fingerprint, $value['review'] );
        $automatic = bactive_catalogue_automatic_preview( $product, $row );
        $review_status = bactive_catalogue_held( $product->get_id() ) ? __( 'Catalogue hold — approval unavailable.', 'blocksy-child' )
            : ( $reviewed ? __( 'Reviewed custom preview.', 'blocksy-child' )
            : ( $automatic ? ( $value['preview_image_id'] ? __( 'Automatic photo from variations. The selected custom preview needs review.', 'blocksy-child' ) : __( 'Automatic photo from variations.', 'blocksy-child' ) )
            : __( 'Choose and review a preview — size photos differ or are missing.', 'blocksy-child' ) ) );
        echo '<section class="bactive-colour-row" data-global-shade="' . esc_attr( bactive_catalogue_hex( get_term_meta( $row['term_id'], '_bactive_colour_hex', true ) ) ) . '" data-held="' . ( bactive_catalogue_held( $product->get_id() ) ? '1' : '0' ) . '"><h3>' . esc_html( $row['name'] ) . '</h3><p>' . esc_html( $review_status ) . '</p><div class="bactive-colour-controls"><label for="' . esc_attr( $uid ) . '">' . esc_html__( 'Colour circle', 'blocksy-child' ) . '</label><select id="' . esc_attr( $uid ) . '" name="' . esc_attr( $prefix . '[mode]' ) . '">';
        foreach ( array( 'inherit' => __( 'Use global shade', 'blocksy-child' ), 'custom' => __( 'Custom shade for this product', 'blocksy-child' ), 'none' => __( 'Name only', 'blocksy-child' ) ) as $mode => $label ) {
            echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $value['mode'], $mode, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select><label class="screen-reader-text" for="' . esc_attr( $uid . '-hex' ) . '">' . esc_html__( 'Custom shade', 'blocksy-child' ) . '</label><input id="' . esc_attr( $uid . '-hex' ) . '" class="bactive-colour-picker" name="' . esc_attr( $prefix . '[hex]' ) . '" value="' . esc_attr( $value['hex'] ) . '" placeholder="#A4C8EC">';
        $effective = bactive_catalogue_effective_hex( $product, $row );
        $shade_status = bactive_catalogue_held( $product->get_id() ) ? __( 'Catalogue hold: saved shades cannot display yet.', 'blocksy-child' ) : ( $effective ? sprintf( __( 'Current shade: %s', 'blocksy-child' ), $effective ) : ( 'none' === $value['mode'] ? __( 'Name only: no circle will display.', 'blocksy-child' ) : __( 'No shade set. Choose a custom shade here or set the global colour default.', 'blocksy-child' ) ) );
        echo '<span class="bactive-shade-status" aria-live="polite">' . esc_html( $shade_status ) . '</span></div>';
        // Always show the photo that the confirmation checkbox would approve.
        $image = bactive_catalogue_editor_display_photo( $value['preview_image_id'], $automatic );
        echo '<div class="bactive-preview"><img alt="' . esc_attr__( 'Colour preview', 'blocksy-child' ) . '" ' . ( $image ? 'src="' . esc_url( $image['url'] ) . '"' : 'hidden' ) . '><input type="hidden" class="bactive-preview-id" name="' . esc_attr( $prefix . '[preview_image_id]' ) . '" value="' . esc_attr( $value['preview_image_id'] ) . '"><button type="button" class="button bactive-select-preview">' . esc_html__( 'Choose preview photo', 'blocksy-child' ) . '</button> <button type="button" class="button-link bactive-clear-preview">' . esc_html__( 'Remove preview photo', 'blocksy-child' ) . '</button></div>';
        echo '<details><summary>' . esc_html__( 'Size photos — edit individually in Variations', 'blocksy-child' ) . '</summary><ul class="bactive-size-photos">';
        foreach ( bactive_catalogue_variations( $product ) as $variation ) {
            $attrs = $variation['attributes'];
            if ( ( $attrs[ $row['taxonomy'] ] ?? $attrs[ 'attribute_' . $row['taxonomy'] ] ?? '' ) !== $row['slug'] ) { continue; }
            $size = $attrs['pa_size'] ?? $attrs['attribute_pa_size'] ?? $attrs['size'] ?? __( 'Unassigned size', 'blocksy-child' );
            $photo = bactive_catalogue_attachment( $variation['image_id'] );
            echo '<li>' . ( $photo ? '<img src="' . esc_url( $photo['url'] ) . '" alt="">' : '' ) . '<span>' . esc_html( $size ) . '</span> <a href="#variable_product_options" class="bactive-edit-variation">' . esc_html__( 'Open Variations', 'blocksy-child' ) . '</a></li>';
        }
        echo '</ul></details><label class="bactive-review"><input type="checkbox" name="' . esc_attr( $prefix . '[confirm]' ) . '" value="1" ' . disabled( bactive_catalogue_held( $product->get_id() ), true, false ) . '> ' . esc_html__( 'I have checked this colour and its size photos. Confirm this mapping when I update the product.', 'blocksy-child' ) . '</label></section>';
    }
    echo '<p>' . esc_html__( 'Use Publish or Update to save. Checking a review box confirms only that colour; normal updates do not approve new mappings.', 'blocksy-child' ) . '</p></div></div>';
}
add_action( 'woocommerce_product_data_panels', 'bactive_catalogue_editor_panel' );

function bactive_catalogue_editor_assets() {
    $screen = get_current_screen();
    if ( ! $screen || ! ( ( 'product' === $screen->post_type && 'post' === $screen->base ) || in_array( $screen->taxonomy, array( 'pa_colour', 'pa_color' ), true ) ) ) { return; }
    if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) { return; }
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_media();
    $base = get_stylesheet_directory();
    wp_enqueue_style( 'bactive-catalogue-editor', get_stylesheet_directory_uri() . '/assets/css/catalogue-editor.css', array( 'wp-color-picker' ), filemtime( $base . '/assets/css/catalogue-editor.css' ) );
    wp_enqueue_script( 'bactive-catalogue-editor', get_stylesheet_directory_uri() . '/assets/js/catalogue-editor.js', array( 'jquery', 'wp-color-picker', 'media-editor' ), filemtime( $base . '/assets/js/catalogue-editor.js' ), true );
    wp_localize_script( 'bactive-catalogue-editor', 'bactiveCatalogueEditor', array( 'title' => __( 'Choose colour preview photo', 'blocksy-child' ), 'button' => __( 'Use this photo', 'blocksy-child' ),
        'held' => __( 'Catalogue hold: saved shades cannot display yet.', 'blocksy-child' ),
        'nameOnly' => __( 'Name only: no circle will display.', 'blocksy-child' ),
        'missingGlobal' => __( 'No global shade set. Choose Custom shade for this product, or set the global colour default.', 'blocksy-child' ),
        'missingCustom' => __( 'Choose a six-digit custom shade, then Update.', 'blocksy-child' ),
        'shade' => __( 'Selected shade: %s. Use Update to save changes.', 'blocksy-child' ) ) );
}
add_action( 'admin_enqueue_scripts', 'bactive_catalogue_editor_assets' );

/** Count assigned products that inherit this global shade; admin only. */
function bactive_catalogue_editor_inheritors( $term_id, $taxonomy ) {
    if ( ! $term_id ) { return 0; }
    $count = 0;
    $ids = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'private', 'draft', 'pending', 'future' ), 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array( $term_id ) ) ) ) );
    foreach ( $ids as $id ) {
        $product = wc_get_product( $id );
        if ( ! $product || bactive_catalogue_held( $id ) ) { continue; }
        $settings = bactive_catalogue_product_settings( $product );
        if ( 'inherit' === ( $settings['colours'][ $taxonomy . ':' . $term_id ]['mode'] ?? 'inherit' ) ) { $count++; }
    }
    return $count;
}

function bactive_catalogue_term_field( $term_or_taxonomy ) {
    $edit = is_object( $term_or_taxonomy );
    $taxonomy = $edit ? $term_or_taxonomy->taxonomy : $term_or_taxonomy;
    $id = $edit ? $term_or_taxonomy->term_id : 0;
    echo $edit ? '<tr class="form-field"><th><label for="bactive-global-hex">' : '<div class="form-field"><label for="bactive-global-hex">';
    echo esc_html__( 'Default colour circle', 'blocksy-child' ) . '</label>' . ( $edit ? '</th><td>' : '' );
    wp_nonce_field( 'bactive_colour_term_' . $taxonomy . '_' . $id, 'bactive_colour_term_nonce' );
    echo '<input id="bactive-global-hex" class="bactive-colour-picker" name="bactive_colour_hex" value="' . esc_attr( $edit ? get_term_meta( $id, '_bactive_colour_hex', true ) : '' ) . '"><p class="description">' . esc_html__( 'Use a six-digit shade or leave blank for a name-only option. Products can override this shade.', 'blocksy-child' ) . '</p>';
    echo '<p class="description">' . esc_html( sprintf( __( '%d products currently inherit this shade. Changing it updates their colour circles.', 'blocksy-child' ), bactive_catalogue_editor_inheritors( $id, $taxonomy ) ) ) . '</p>';
    echo $edit ? '</td></tr>' : '</div>';
}
function bactive_catalogue_term_save( $term_id, $tt_id, $taxonomy ) {
    if ( ! in_array( $taxonomy, array( 'pa_colour', 'pa_color' ), true ) ) { return; }
    $tax = get_taxonomy( $taxonomy );
    $nonce = $_POST['bactive_colour_term_nonce'] ?? null;
    if ( ! $tax || ! current_user_can( $tax->cap->edit_terms ) || ! is_string( $nonce ) || ! is_string( $_POST['bactive_colour_hex'] ?? null ) ) { return; }
    $nonce = sanitize_text_field( wp_unslash( $nonce ) );
    $action_id = 'created_term' === current_filter() ? 0 : $term_id;
    if ( ! wp_verify_nonce( $nonce, 'bactive_colour_term_' . $taxonomy . '_' . $action_id ) ) { return; }
    $hex = trim( wp_unslash( $_POST['bactive_colour_hex'] ) );
    if ( '' !== $hex && ! preg_match( '/\A#[a-fA-F0-9]{6}\z/', $hex ) ) { return; }
    update_term_meta( $term_id, '_bactive_colour_hex', strtolower( $hex ) );
    bactive_catalogue_invalidate_colour_term( $term_id, $taxonomy );
}
foreach ( array( 'pa_colour', 'pa_color' ) as $taxonomy ) {
    add_action( $taxonomy . '_add_form_fields', 'bactive_catalogue_term_field' );
    add_action( $taxonomy . '_edit_form_fields', 'bactive_catalogue_term_field' );
}
add_action( 'created_term', 'bactive_catalogue_term_save', 10, 3 );
add_action( 'edited_term', 'bactive_catalogue_term_save', 10, 3 );
