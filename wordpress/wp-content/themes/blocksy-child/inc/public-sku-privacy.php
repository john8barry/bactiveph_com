<?php
/**
 * SKUs are operational identifiers, never storefront data.
 *
 * Filter output boundaries, not product getters: a checkout request can also
 * run payment/fulfillment code that legitimately needs the stored identifier.
 */
defined( 'ABSPATH' ) || exit;

/** Store API is public even for a logged-in store manager. */
function bactive_sku_store_route( $request ) {
    return (bool) preg_match( '#^/wc/store(?:/|$)#i', $request->get_route() );
}

/** Track nested REST/hydration callbacks without trusting the URL or is_admin. */
function bactive_sku_enter_request( $response, $handler, $request ) {
    $GLOBALS['bactive_sku_request_routes'][] = $request->get_route();
    return $response;
}
add_filter( 'rest_request_before_callbacks', 'bactive_sku_enter_request', 1, 3 );

function bactive_sku_public_context() {
    $routes = $GLOBALS['bactive_sku_request_routes'] ?? array();
    if ( $routes ) {
        $route = end( $routes );
        return (bool) preg_match( '#^/wc/store(?:/|$)#i', $route );
    }
    if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
        return false;
    }
    if ( defined( 'WC_DOING_AJAX' ) && WC_DOING_AJAX ) {
        return true;
    }
    if ( wp_doing_ajax() ) {
        // A public AJAX action stays public even when a manager visits the shop.
        $action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] )
            ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
        return $action && false !== has_action( 'wp_ajax_nopriv_' . $action );
    }
    // REST route/schema registration happens before wp; do not alter backend
    // validation schemas or authorized management search during registration.
    return ! is_admin() && did_action( 'wp' );
}

add_filter( 'wc_product_sku_enabled', function ( $enabled ) {
    return bactive_sku_public_context() ? false : $enabled;
}, PHP_INT_MAX );

/** Remove exact SKU properties, including metadata rows, from public schemas. */
function bactive_sku_strip_data( $data ) {
    if ( ! is_array( $data ) && ! $data instanceof stdClass ) {
        return $data;
    }
    $object = $data instanceof stdClass;
    $values = $object ? get_object_vars( $data ) : $data;
    $list = array_is_list( $values );
    $result = array();
    $keys = array( 'sku', '_sku', 'product_sku', 'variation_sku' );
    foreach ( $values as $key => $value ) {
        if ( is_string( $key ) && in_array( strtolower( $key ), $keys, true ) ) {
            continue;
        }
        $row = $value instanceof stdClass ? get_object_vars( $value ) : $value;
        if ( is_array( $row ) && isset( $row['key'] )
            && is_string( $row['key'] ) && in_array( strtolower( $row['key'] ), $keys, true ) ) {
            continue;
        }
        $result[ $key ] = bactive_sku_strip_data( $value );
    }
    return $object ? (object) $result : ( $list ? array_values( $result ) : $result );
}

add_filter( 'woocommerce_available_variation', 'bactive_sku_strip_data', PHP_INT_MAX );
add_filter( 'woocommerce_structured_data_product', 'bactive_sku_strip_data', PHP_INT_MAX );
add_filter( 'woocommerce_structured_data_order', 'bactive_sku_strip_data', PHP_INT_MAX );
add_filter( 'render_block_woocommerce/product-sku', '__return_empty_string', PHP_INT_MAX );

/** Strip both values and attribute names before HTML reaches the browser. */
function bactive_sku_strip_attributes( $attributes ) {
    unset( $attributes['data-product_sku'], $attributes['data-product-sku'], $attributes['data-sku'] );
    return $attributes;
}
add_filter( 'woocommerce_loop_add_to_cart_args', function ( $args ) {
    if ( isset( $args['attributes'] ) ) {
        $args['attributes'] = bactive_sku_strip_attributes( $args['attributes'] );
    }
    return $args;
}, PHP_INT_MAX );
add_filter( 'woocommerce_blocks_product_grid_add_to_cart_attributes', 'bactive_sku_strip_attributes', PHP_INT_MAX );

function bactive_sku_strip_html_attributes( $html ) {
    $processor = new WP_HTML_Tag_Processor( $html );
    while ( $processor->next_tag() ) {
        foreach ( array( 'data-product_sku', 'data-product-sku', 'data-sku' ) as $attribute ) {
            $processor->remove_attribute( $attribute );
        }
    }
    return $processor->get_updated_html();
}
add_filter( 'woocommerce_cart_item_remove_link', 'bactive_sku_strip_html_attributes', PHP_INT_MAX );
add_filter( 'woocommerce_loop_add_to_cart_link', 'bactive_sku_strip_html_attributes', PHP_INT_MAX );

function bactive_sku_customer_email_args( $args ) {
    if ( empty( $args['sent_to_admin'] ) ) {
        $args['show_sku'] = false;
    }
    return $args;
}
add_filter( 'woocommerce_email_order_items_args', 'bactive_sku_customer_email_args', PHP_INT_MAX );
add_filter( 'woocommerce_email_fulfillment_items_args', 'bactive_sku_customer_email_args', PHP_INT_MAX );

/** Refuse SKU queries rather than exposing product membership by inference. */
function bactive_sku_guard_request( $response, $server, $request ) {
    if ( ! bactive_sku_store_route( $request ) ) {
        return $response;
    }
    foreach ( array( 'sku', '_sku', 'product_sku', 'variation_sku' ) as $key ) {
        if ( $request->has_param( $key ) ) {
            return new WP_Error( 'bactive_private_product_identifier', 'Use product names or product IDs.', array( 'status' => 400 ) );
        }
    }
    $fields = $request->get_param( 'search_fields' );
    if ( null !== $fields && in_array( 'sku', (array) $fields, true ) ) {
        return new WP_Error( 'bactive_private_product_identifier', 'Use product names or product IDs.', array( 'status' => 400 ) );
    }
    return $response;
}
add_filter( 'rest_pre_dispatch', 'bactive_sku_guard_request', PHP_INT_MAX, 3 );
add_filter( 'woocommerce_hydration_dispatch_request', function ( $response, $request, $path, $handler ) {
    bactive_sku_enter_request( $response, $handler, $request );
    return bactive_sku_guard_request( $response, null, $request );
}, PHP_INT_MAX, 4 );

function bactive_sku_public_response( $response, $handler, $request ) {
    if ( ! bactive_sku_store_route( $request ) || is_wp_error( $response ) ) {
        return $response;
    }
    if ( $response instanceof WP_HTTP_Response ) {
        $response->set_data( bactive_sku_strip_data( $response->get_data() ) );
        return $response;
    }
    return bactive_sku_strip_data( $response );
}
add_filter( 'rest_request_after_callbacks', 'bactive_sku_public_response', PHP_INT_MAX - 1, 3 );
add_filter( 'woocommerce_hydration_request_after_callbacks', 'bactive_sku_public_response', PHP_INT_MAX - 1, 3 );
// Includes pre-dispatch short circuits and REST embedding, retaining status/headers.
add_filter( 'rest_post_dispatch', 'bactive_sku_public_response', PHP_INT_MAX, 3 );

function bactive_sku_leave_request( $response, $handler, $request ) {
    if ( ! empty( $GLOBALS['bactive_sku_request_routes'] ) ) {
        array_pop( $GLOBALS['bactive_sku_request_routes'] );
    }
    return $response;
}
add_filter( 'rest_request_after_callbacks', 'bactive_sku_leave_request', PHP_INT_MAX, 3 );
add_filter( 'woocommerce_hydration_request_after_callbacks', 'bactive_sku_leave_request', PHP_INT_MAX, 3 );

/**
 * TikTok 1.4.1 has no identifier-mapping hook and publishes raw SKUs in pixel
 * events. Its pixel/catalog are unconfigured at release discovery. Keep that
 * unsafe emitter disabled; enabling tracking requires an ID-based adapter and
 * matching catalog IDs. Google Listings already uses product/variation IDs.
 */
function bactive_sku_disable_unsafe_pixel() {
    global $wp_filter;
    $emitters = array(
        'woocommerce_add_to_cart' => 'inject_add_to_cart_event',
        'woocommerce_before_single_product_summary' => 'inject_view_content_event',
        'woocommerce_payment_complete' => 'inject_purchase_event',
        'woocommerce_thankyou' => 'inject_purchase_event',
        'woocommerce_after_checkout_form' => 'inject_initiate_checkout_event',
        'woocommerce_blocks_checkout_enqueue_data' => 'inject_initiate_checkout_event',
        'wp_head' => 'print_script',
        'wp_enqueue_scripts' => 'add_ajax_snippet',
    );
    foreach ( $emitters as $hook => $method ) {
        if ( ! isset( $wp_filter[ $hook ] ) ) {
            continue;
        }
        foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $entry ) {
                $callback = $entry['function'];
                if ( is_array( $callback ) && is_object( $callback[0] )
                    && is_a( $callback[0], 'Tt4b_Pixel_Class' ) && $method === $callback[1] ) {
                    remove_action( $hook, $callback, $priority );
                }
            }
        }
    }
}
add_action( 'after_setup_theme', 'bactive_sku_disable_unsafe_pixel', PHP_INT_MAX );
add_action( 'init', 'bactive_sku_disable_unsafe_pixel', PHP_INT_MAX );
