<?php
/**
 * Blocksy Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Blocksy Child
 */

/**
 * Enqueue scripts and styles.
 */
function blocksy_child_enqueue_styles() {
	// Enqueue parent style
	wp_enqueue_style(
		'blocksy-parent-style',
		get_template_directory_uri() . '/style.css'
	);
	
	// Enqueue child custom CSS
	wp_enqueue_style(
		'blocksy-child-custom',
		get_stylesheet_directory_uri() . '/assets/css/custom.css',
		array('blocksy-parent-style'),
		filemtime(get_stylesheet_directory() . '/assets/css/custom.css')
	);

	// Enqueue fonts
	wp_enqueue_style(
		'bactive-inter-font',
		get_stylesheet_directory_uri() . '/assets/css/inter-fonts.css'
	);
	wp_enqueue_style(
		'bactive-fraunces-font',
		get_stylesheet_directory_uri() . '/assets/css/fraunces-fonts.css'
	);

	// Enqueue child custom JS
	wp_enqueue_script(
		'blocksy-child-custom-js',
		get_stylesheet_directory_uri() . '/assets/js/custom.js',
		array(),
		filemtime(get_stylesheet_directory() . '/assets/js/custom.js'),
		true
	);

	if ( is_product() ) {
		wp_enqueue_script(
			'bactive-size-guide',
			get_stylesheet_directory_uri() . '/assets/js/size-guide.js',
			array(),
			filemtime( get_stylesheet_directory() . '/assets/js/size-guide.js' ),
			true
		);
	}

	if ( is_product() || is_page( 'size-guide' ) ) {
		wp_enqueue_style(
			'bactive-size-guide',
			get_stylesheet_directory_uri() . '/assets/css/size-guide.css',
			array( 'blocksy-child-custom' ),
			filemtime( get_stylesheet_directory() . '/assets/css/size-guide.css' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'blocksy_child_enqueue_styles' );

/** Load the logo-aligned display face after the existing theme styles. */
function bactive_enqueue_brand_typography() {
    $relative = '/assets/css/brand-typography.css';
    $path = get_stylesheet_directory() . $relative;
    if ( ! is_readable( $path ) ) {
        return;
    }
    wp_enqueue_style(
        'bactive-brand-typography',
        get_stylesheet_directory_uri() . $relative,
        array( 'blocksy-child-custom', 'bactive-inter-font' ),
        filemtime( $path )
    );
}
add_action( 'wp_enqueue_scripts', 'bactive_enqueue_brand_typography', 30 );


/**
 * Phase 4: Custom Product Tabs
 */
add_filter( 'woocommerce_product_tabs', 'bactive_custom_product_tabs', 98 );
function bactive_custom_product_tabs( $tabs ) {
	// Rename Description Tab
	if ( isset( $tabs['description'] ) ) {
		$tabs['description']['title'] = __( 'Description', 'bactive' );
	}

	// Add Features & Fit Tab
	$tabs['features_fit'] = array(
		'title' 	=> __( 'Features & Fit', 'bactive' ),
		'priority' 	=> 20,
		'callback' 	=> 'bactive_features_fit_tab_content'
	);

	// Add Shipping & Returns Tab
	$tabs['shipping_returns'] = array(
		'title' 	=> __( 'Shipping & Returns', 'bactive' ),
		'priority' 	=> 30,
		'callback' 	=> 'bactive_shipping_returns_tab_content'
	);

	// Add Fabric & Care Tab
	$tabs['fabric_care'] = array(
		'title' 	=> __( 'Fabric & Care', 'bactive' ),
		'priority' 	=> 40,
		'callback' 	=> 'bactive_fabric_care_tab_content'
	);

	// Remove standard Reviews/Additional Info for now to keep it clean (optional based on preference, keeping reviews is fine, removing additional info)
	unset( $tabs['additional_information'] );

	return $tabs;
}

function bactive_features_fit_tab_content() {
	global $post;
	echo '<h2>Features & Fit</h2>';
	echo apply_filters( 'the_excerpt', $post->post_excerpt );
}

function bactive_complimentary_shipping_minimum() {
	return 5000;
}

function bactive_is_domestic_shipping_destination() {
	$woocommerce = function_exists( 'WC' ) ? WC() : null;
	$country = '';

	if ( $woocommerce && $woocommerce->customer ) {
		$country = $woocommerce->customer->get_shipping_country();
		if ( ! $country ) {
			$country = $woocommerce->customer->get_billing_country();
		}
	}

	if ( ! $country && function_exists( 'wc_get_base_location' ) ) {
		$base_location = wc_get_base_location();
		$country = isset( $base_location['country'] ) ? $base_location['country'] : '';
	}

	return 'PH' === strtoupper( (string) $country );
}

function bactive_complimentary_shipping_cart_total( $cart ) {
	$total = $cart->get_displayed_subtotal() - $cart->get_discount_total();
	if ( $cart->display_prices_including_tax() ) {
		$total -= $cart->get_discount_tax();
	}

	return round( $total, wc_get_price_decimals() );
}

add_filter( 'woocommerce_package_rates', 'bactive_restrict_complimentary_shipping_rates', 100, 2 );
function bactive_restrict_complimentary_shipping_rates( $rates, $package ) {
	$country = strtoupper( (string) ( $package['destination']['country'] ?? '' ) );
	if ( 'PH' === $country ) {
		return $rates;
	}

	foreach ( $rates as $rate_id => $rate ) {
		$method_id = method_exists( $rate, 'get_method_id' ) ? $rate->get_method_id() : ( $rate->method_id ?? '' );
		if ( 'free_shipping' === $method_id ) {
			unset( $rates[ $rate_id ] );
		}
	}

	return $rates;
}

function bactive_shipping_returns_tab_content() {
	echo '<h2>Shipping & Returns</h2>';
	echo '<p><strong>Shipping</strong><br>We ship nationwide across the Philippines via J&T Express and LBC Express. Complimentary shipping is available on Philippine orders of ₱5,000 or more. It does not apply to international destinations.</p>';
	echo '<p><strong>Returns & Exchanges</strong><br>We want you in the right size. If your fit isn\'t perfect, we accept size exchanges within 7 days of delivery for unworn items with tags attached and original packaging.</p>';
}

function bactive_fabric_care_tab_content() {
	echo '<h2>Fabric & Care</h2>';
	echo '<p><strong>CourtSoft™</strong><br>Our signature four-way-stretch knit: buttery-soft, squat-proof, sweat-wicking and built to hold its shape.</p>';
	echo '<p><strong>BreezeKnit™</strong><br>Lightweight and breathable for hot-court days. It moves air, moves sweat, and keeps you cool.</p>';
	echo '<p><strong>Care basics</strong><br>Gentle hand wash only. Wash in cold water with like colours, then hang to dry. Do not machine wash, tumble dry, use fabric softener, bleach, or iron.</p>';
}

/**
 * Phase 4: Size Guide Modal Link
 */
add_action( 'woocommerce_single_product_summary', 'bactive_size_guide_link', 25 );
function bactive_size_guide_link() {
	$url = home_url( '/size-guide/' );
	$chart = bactive_get_product_size_chart();
	$url .= $chart ? '?chart=' . $chart . '#' . $chart . '-size-chart' : '#sizing-help';
	echo '<a href="' . esc_url( $url ) . '" class="bactive-size-guide-link" aria-haspopup="dialog" aria-controls="bactive-size-modal">Size Guide</a>';
}

/**
 * Approved charts belong to these exact products, not their categories.
 * Slugs are explicit identities; never infer sizing from a title or variation.
 */
function bactive_get_product_size_chart() {
	if ( ! is_product() ) {
		return '';
	}
	$charts = array(
		'the-court-skort' => 'court-skort',
		'the-bubble-dress' => 'bubble-dress',
	);
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	return $charts[ $slug ] ?? '';
}

/**
 * Render the canonical size-guide content for both the page and product dialog.
 */
function bactive_get_size_guide_content( $heading_id = '', $chart = '' ) {
	$heading_attribute = $heading_id ? ' id="' . esc_attr( $heading_id ) . '"' : '';

	ob_start();
	?>
	<div class="bactive-size-guide-content">
		<?php if ( 'court-skort' === $chart ) : ?>
		<h2<?php echo $heading_attribute; ?>>Court Skort size chart</h2>
		<figure class="bactive-size-illustration">
			<a href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/size-guides/court-skort-illustrated-20260911.jpg' ); ?>" target="_blank" rel="noopener" aria-label="Open the Court Skort illustrated size guide full size in a new tab" aria-describedby="court-skort-measurements-description">
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/size-guides/court-skort-illustrated-20260911.jpg' ); ?>" width="853" height="1280" loading="lazy" alt="Court Skort size chart with arrows showing waist, inner hip, skirt length and inner shorts length.">
			</a>
			<figcaption>Tap or click the illustration to open it full size in a new tab.</figcaption>
		</figure>
		<!-- Referenced text alternative, not a second visible chart. -->
		<div id="court-skort-measurements-description" hidden>Court Skort measurements in centimeters (cm). Sizes, in order: 4, 6, 8, 10, 12, 14. Length: 35, 36, 37, 38, 39, 40. Waist: 64, 68, 72, 76, 80, 84. Inner Hip: 72, 76, 80, 84, 88, 92. Inner Leg Opening: 40, 42, 44, 46, 48, 50. Inner Length: 8.5, 8.8, 9.1, 9.4, 9.7, 10.0. How to measure: Length: Measure from the top of the waistband to the hem. Waist: Measure around the narrowest part of your waist. Inner Hip: Measure around the fullest part of your hips (below the waistband). Inner Leg Opening: Measure across the leg opening of the built-in shorts. Inner Length: Measure the length of the inner shorts (from crotch to hem). Please allow 1–2 cm difference due to manual measurement. If you are between sizes, we recommend sizing up for a more comfortable fit.</div>
		<?php elseif ( 'bubble-dress' === $chart ) : ?>
		<h2<?php echo $heading_attribute; ?>>Bubble Dress size chart</h2>
		<figure class="bactive-size-illustration">
			<a href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/size-guides/bubble-dress-illustrated-20260911.jpg' ); ?>" target="_blank" rel="noopener" aria-label="Open the Bubble Dress illustrated size guide full size in a new tab" aria-describedby="bubble-dress-measurements-description">
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/size-guides/bubble-dress-illustrated-20260911.jpg' ); ?>" width="853" height="1280" loading="lazy" alt="Bubble Dress size chart with arrows showing coat length, bust, waist, hip and the inner shorts leg opening.">
			</a>
			<figcaption>Tap or click the illustration to open it full size in a new tab.</figcaption>
		</figure>
		<!-- Referenced text alternative, not a second visible chart. -->
		<div id="bubble-dress-measurements-description" hidden>Bubble Dress measurements in centimeters (cm). Each size lists Coat Length, Bust, Waist, Hip and Slack Bottom, in that order. S: 74, 68, 56, 80, 41. M: 76, 72, 60, 84, 43. L: 78, 76, 64, 88, 45. XL: 80, 80, 68, 92, 47. XXL: 84, 84, 72, 98, 49. How to measure: Coat Length: Total length from top of shoulder to bottom hem of outer skirt. Bust: Measure around the fullest part of your bust. Waist: Measure around the narrowest part of your waist. Hip: Measure around the fullest part of your hips. Slack Bottom: This is the flat half-width of the leg opening of the built-in inner shorts. Double to get full thigh opening circumference. This dimension tells how loose/tight the inner shorts fit around your thighs. Please allow 1–2 cm difference due to manual measurement. If you are between sizes, we recommend sizing up for a more comfortable fit.</div>
		<?php else : ?>
		<h2<?php echo $heading_attribute; ?>>Size guidance</h2>
		<p>Size charts vary by style. <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact us for help choosing your size</a>.</p>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}

add_filter( 'the_content', 'bactive_size_guide_page_content' );
function bactive_size_guide_page_content( $content ) {
	if ( is_admin() || ! is_page( 'size-guide' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	// Only exact, scalar chart keys may select a product guide. Never reflect input.
	$chart = isset( $_GET['chart'] ) && is_string( $_GET['chart'] ) ? $_GET['chart'] : '';
	if ( in_array( $chart, array( 'court-skort', 'bubble-dress' ), true ) ) {
		return bactive_get_size_guide_content( $chart . '-size-chart', $chart );
	}

	return '<section class="bactive-size-guide-content"><h2>Choose your product</h2>'
		. '<ul><li id="court-skort-size-chart"><a href="' . esc_url( home_url( '/size-guide/?chart=court-skort#court-skort-size-chart' ) ) . '">Court Skort visual size chart</a></li>'
		. '<li id="bubble-dress-size-chart"><a href="' . esc_url( home_url( '/size-guide/?chart=bubble-dress#bubble-dress-size-chart' ) ) . '">Bubble Dress visual size chart</a></li></ul></section>'
		. '<section id="sizing-help" class="bactive-size-guide-content"><h2>Other styles</h2><p>Size charts vary by style. For other skorts, dresses, tops and styles, <a href="' . esc_url( home_url( '/contact/' ) ) . '">contact us for the right size guide</a>.</p></section>';
}

/**
 * Phase 4: Size Guide Modal HTML (Output in footer)
 */
add_action( 'wp_footer', 'bactive_size_guide_modal' );
function bactive_size_guide_modal() {
	if ( ! is_product() ) return;
	?>
	<dialog id="bactive-size-modal" class="bactive-modal" aria-labelledby="bactive-size-modal-title">
		<div class="bactive-modal-inner">
			<button type="button" class="bactive-modal-close" aria-label="Close size guide">&times;</button>
			<?php echo bactive_get_size_guide_content( 'bactive-size-modal-title', bactive_get_product_size_chart() ); ?>
		</div>
	</dialog>
	<?php
}

/**
 * Phase 4: Sticky Add-to-Cart HTML
 */
add_action( 'woocommerce_after_single_product', 'bactive_sticky_add_to_cart' );
function bactive_sticky_add_to_cart() {
	global $product;
	if ( ! $product || ! $product->is_purchasable() ) return;
	?>
	<div id="bactive-sticky-cart" class="bactive-sticky-cart hidden">
		<div class="sticky-cart-inner">
			<div class="sticky-cart-info">
				<strong class="sticky-cart-title"><?php echo esc_html( $product->get_name() ); ?></strong>
				<span class="sticky-cart-price"><?php echo $product->get_price_html(); ?></span>
			</div>
			<div class="sticky-cart-action">
				<button class="button alt" id="sticky-cart-button">Add to Cart</button>
			</div>
		</div>
	</div>
	<?php
}

// BEGIN PHASE 6 SNIPPETS
// Minimal Address Fields
add_filter( 'woocommerce_checkout_fields' , 'bactive_custom_override_checkout_fields' );
function bactive_custom_override_checkout_fields( $fields ) {
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_2']);
    return $fields;
}

// COD Fee
add_action( 'woocommerce_cart_calculate_fees', 'bactive_add_cod_fee', 20, 1 );
function bactive_add_cod_fee( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    $chosen_gateway = WC()->session->get( 'chosen_payment_method' );
    if ( 'cod' === $chosen_gateway && $cart->get_cart_contents_total() <= 2500 ) {
        $fee = 50;
        $cart->add_fee( 'COD Fee', $fee, false, '' );
    }
}
// Force checkout update on payment method change to apply fee
add_action( 'wp_footer', 'bactive_checkout_update_script' );
function bactive_checkout_update_script() {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        echo '<script type="text/javascript">
            jQuery(document).ready(function($){
                $(document.body).on("change", "input[name=\"payment_method\"]", function() {
                    $("body").trigger("update_checkout");
                });
            });
        </script>';
    }
}

// Hide COD over 2500
add_filter( 'woocommerce_available_payment_gateways', 'bactive_hide_cod_over_cap' );
function bactive_hide_cod_over_cap( $available_gateways ) {
    if ( is_admin() ) return $available_gateways;
    if ( isset( $available_gateways['cod'] ) && WC()->cart ) {
        if ( WC()->cart->get_cart_contents_total() > 2500 ) {
            unset( $available_gateways['cod'] );
        }
    }
    return $available_gateways;
}

// Reassurance Row
add_action( 'woocommerce_review_order_after_submit', 'bactive_checkout_reassurance', 10 );
function bactive_checkout_reassurance() {
    echo '<div style="text-align:center; font-size:13px; margin-top:20px; color:#2B2A28;">Secure checkout &middot; Cash on Delivery available &middot; 7-day size-exchange guarantee</div>';
}

// Slide-out Cart Drawer Text
add_action( 'woocommerce_widget_shopping_cart_before_buttons', 'bactive_cart_drawer_text', 10 );
function bactive_cart_drawer_text() {
    echo '<div style="text-align:center; font-style:italic; margin-bottom:15px; color:#5E6E54;">Thank you for choosing quality.</div>';
}

// Rename Checkout Button
add_filter( 'woocommerce_order_button_text', 'bactive_custom_button_text' );
function bactive_custom_button_text() {
    return 'Checkout securely';
}

// Complimentary Shipping Progress Bar
add_action( 'woocommerce_widget_shopping_cart_before_buttons', 'bactive_free_shipping_progress_bar', 5 );
function bactive_free_shipping_progress_bar() {
    $woocommerce = function_exists( 'WC' ) ? WC() : null;
    if ( ! $woocommerce || ! $woocommerce->cart || $woocommerce->cart->is_empty() || ! bactive_is_domestic_shipping_destination() ) return;

    $free_shipping_threshold = bactive_complimentary_shipping_minimum();
    $cart_subtotal = bactive_complimentary_shipping_cart_total( $woocommerce->cart );
    
    if ( $cart_subtotal < $free_shipping_threshold ) {
        $amount_left = $free_shipping_threshold - $cart_subtotal;
        echo '<div style="background:#FAF8F4; border:1px solid #E5E5E5; padding:10px; text-align:center; margin-bottom:15px; font-size:13px; color:#2B2A28;">You are just <strong>₱' . number_format($amount_left, 2) . '</strong> away from complimentary shipping!</div>';
    } else {
        echo '<div style="background:#5E6E54; color:#FAF8F4; padding:10px; text-align:center; margin-bottom:15px; font-size:13px;">You have unlocked <strong>complimentary shipping!</strong></div>';
    }
}
// END PHASE 6 SNIPPETS

// Storefront punctuation policy and generated WooCommerce ranges.
require_once __DIR__ . '/inc/storefront-punctuation.php';

// Keep operational SKUs out of customer-facing output.
require_once __DIR__ . '/inc/public-sku-privacy.php';
