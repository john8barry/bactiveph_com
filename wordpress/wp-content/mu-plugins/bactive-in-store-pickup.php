<?php
/**
 * Plugin Name: B Active In-Store Pickup
 * Description: Clarifies that the Davao City pickup option requires collection from the store.
 * Version: 1.0.0
 */

namespace BactivePH\InStorePickup;

defined( 'ABSPATH' ) || exit;

const LABEL = 'In-Store Pickup';
const NOTE  = 'Collect your order from our Davao City store. This option does not include delivery.';

/** Identify pickup by its stable WooCommerce method ID, never by editable copy. */
function is_pickup_rate( $rate ) {
	return is_object( $rate )
		&& method_exists( $rate, 'get_method_id' )
		&& method_exists( $rate, 'get_instance_id' )
		&& 'local_pickup' === $rate->get_method_id()
		&& 14 === (int) $rate->get_instance_id();
}

/** Normalize cached and freshly calculated rates before display and order creation. */
function normalize_rate_label( $label, $rate ) {
	return is_pickup_rate( $rate ) ? LABEL : $label;
}
add_filter( 'woocommerce_shipping_rate_label', __NAMESPACE__ . '\\normalize_rate_label', 20, 2 );

/** Keep historical order data intact while presenting the current customer-facing term. */
function normalize_order_shipping_method( $shipping_method, $order ) {
	if ( ! is_object( $order ) || ! method_exists( $order, 'get_shipping_methods' ) ) {
		return $shipping_method;
	}

	foreach ( $order->get_shipping_methods() as $item ) {
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_method_id' ) || ! method_exists( $item, 'get_name' )
			|| ! method_exists( $item, 'get_instance_id' ) || 'local_pickup' !== $item->get_method_id() ) {
			continue;
		}
		$name = $item->get_name();
		$is_davao_method = 14 === (int) $item->get_instance_id();
		$is_legacy_cashier = 0 === (int) $item->get_instance_id() && 'In-store pickup — Davao City' === $name;
		if ( $is_davao_method || $is_legacy_cashier ) {
			$shipping_method = str_replace( $name, LABEL, $shipping_method );
		}
	}

	return $shipping_method;
}
add_filter( 'woocommerce_order_shipping_method', __NAMESPACE__ . '\\normalize_order_shipping_method', 20, 2 );

/** Render one persistent clarification directly beneath each available pickup rate. */
function render_pickup_note( $rate, $index ) {
	if ( ! is_pickup_rate( $rate ) || ! method_exists( $rate, 'get_id' ) ) {
		return;
	}

	$input_id = sprintf( 'shipping_method_%d_%s', absint( $index ), sanitize_title( $rate->get_id() ) );
	$note_id  = sprintf( 'bactive_in_store_pickup_note_%d_%s', absint( $index ), sanitize_html_class( $rate->get_id() ) );
	?>
	<p
		id="<?php echo esc_attr( $note_id ); ?>"
		class="bactive-in-store-pickup-note"
		data-shipping-control="<?php echo esc_attr( $input_id ); ?>"
	>
		<?php echo esc_html( NOTE ); ?>
	</p>
	<?php
}
add_action( 'woocommerce_after_shipping_rate', __NAMESPACE__ . '\\render_pickup_note', 20, 2 );

/** Add scoped styling and maintain aria-describedby through WooCommerce AJAX refreshes. */
function enqueue_assets() {
	if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) || ( ! is_cart() && ! is_checkout() ) ) {
		return;
	}

	wp_register_style( 'bactive-in-store-pickup', false, array(), '1.0.0' );
	wp_enqueue_style( 'bactive-in-store-pickup' );
	wp_add_inline_style(
		'bactive-in-store-pickup',
		'.bactive-in-store-pickup-note{max-width:42rem;margin:.35rem 0 .25rem 1.75rem;color:var(--theme-text-color,#2b2a28);font:inherit;font-size:.875rem;font-weight:500;line-height:1.5;overflow-wrap:anywhere}@media(max-width:689px){.bactive-in-store-pickup-note{margin-left:1.5rem}}'
	);

	wp_register_script( 'bactive-in-store-pickup', false, array( 'jquery' ), '1.0.0', true );
	wp_enqueue_script( 'bactive-in-store-pickup' );
	wp_add_inline_script(
		'bactive-in-store-pickup',
		"(function($){'use strict';function syncPickupDescriptions(){document.querySelectorAll('.bactive-in-store-pickup-note[data-shipping-control]').forEach(function(note){var control=document.getElementById(note.getAttribute('data-shipping-control'));if(!control||!note.id){return;}var ids=(control.getAttribute('aria-describedby')||'').split(/\\s+/).filter(Boolean);if(ids.indexOf(note.id)===-1){ids.push(note.id);control.setAttribute('aria-describedby',ids.join(' '));}});}$(syncPickupDescriptions);$(document.body).on('updated_checkout updated_wc_div',syncPickupDescriptions);})(jQuery);"
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets', 20 );
