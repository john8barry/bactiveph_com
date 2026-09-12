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
	$url .= $chart ? '#' . $chart . '-size-chart' : '#sizing-help';
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
		<p>This chart is for the Court Skort only. Sizes are shown using the chart's numeric labels.</p>
		<p>Shopping with S, M, L or XL? <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact us to confirm your matching Court Skort size</a>.</p>
		<div class="bactive-size-table-wrap" role="region" aria-label="Court Skort measurements; scroll to see all sizes" tabindex="0">
			<table class="bactive-size-table">
				<caption>Court Skort measurements in centimeters (cm)</caption>
				<thead>
					<tr><th scope="col">Size</th><th scope="col">4</th><th scope="col">6</th><th scope="col">8</th><th scope="col">10</th><th scope="col">12</th><th scope="col">14</th></tr>
				</thead>
				<tbody>
					<tr><th scope="row">Length (cm)</th><td>35</td><td>36</td><td>37</td><td>38</td><td>39</td><td>40</td></tr>
					<tr><th scope="row">Waist (cm)</th><td>64</td><td>68</td><td>72</td><td>76</td><td>80</td><td>84</td></tr>
					<tr><th scope="row">Inner Hip (cm)</th><td>72</td><td>76</td><td>80</td><td>84</td><td>88</td><td>92</td></tr>
					<tr><th scope="row">Inner Leg Opening (cm)</th><td>40</td><td>42</td><td>44</td><td>46</td><td>48</td><td>50</td></tr>
					<tr><th scope="row">Inner Length (cm)</th><td>8.5</td><td>8.8</td><td>9.1</td><td>9.4</td><td>9.7</td><td>10.0</td></tr>
				</tbody>
			</table>
		</div>
		<p class="bactive-size-scroll-hint">Scroll the table horizontally to see all sizes.</p>
		<p>Please allow 1–2 cm difference due to manual measurement. If you are between sizes, we recommend sizing up for a more comfortable fit.</p>
		<h3>How to measure</h3>
		<dl class="bactive-size-measurements">
			<dt>Length</dt><dd>Measure from the top of the waistband to the hem.</dd>
			<dt>Waist</dt><dd>Measure around the narrowest part of your waist.</dd>
			<dt>Inner Hip</dt><dd>Measure around the fullest part of your hips (below the waistband).</dd>
			<dt>Inner Leg Opening</dt><dd>Measure across the leg opening of the built-in shorts.</dd>
			<dt>Inner Length</dt><dd>Measure the length of the inner shorts (from crotch to hem).</dd>
		</dl>
		<?php elseif ( 'bubble-dress' === $chart ) : ?>
		<h2<?php echo $heading_attribute; ?>>Bubble Dress size chart</h2>
		<p>This chart is for the Bubble Dress only.</p>
		<div class="bactive-size-table-wrap" role="region" aria-label="Bubble Dress measurements; scroll to see all measurements" tabindex="0">
			<table class="bactive-size-table bactive-size-table-bubble">
				<caption>Bubble Dress measurements in centimeters (cm)</caption>
				<thead>
					<tr><th scope="col">Size</th><th scope="col">Coat Length (cm)</th><th scope="col">Bust (cm)</th><th scope="col">Waist (cm)</th><th scope="col">Hip (cm)</th><th scope="col">Slack Bottom (cm)</th></tr>
				</thead>
				<tbody>
					<tr><th scope="row">S</th><td>74</td><td>68</td><td>56</td><td>80</td><td>41</td></tr>
					<tr><th scope="row">M</th><td>76</td><td>72</td><td>60</td><td>84</td><td>43</td></tr>
					<tr><th scope="row">L</th><td>78</td><td>76</td><td>64</td><td>88</td><td>45</td></tr>
					<tr><th scope="row">XL</th><td>80</td><td>80</td><td>68</td><td>92</td><td>47</td></tr>
					<tr><th scope="row">XXL</th><td>84</td><td>84</td><td>72</td><td>98</td><td>49</td></tr>
				</tbody>
			</table>
		</div>
		<p class="bactive-size-scroll-hint">Scroll the table horizontally to see all measurements.</p>
		<p>Please allow 1–2 cm difference due to manual measurement. If you are between sizes, we recommend sizing up for a more comfortable fit.</p>
		<h3>How to measure</h3>
		<dl class="bactive-size-measurements">
			<dt>Coat Length</dt><dd>Total length from top of shoulder to bottom hem of outer skirt.</dd>
			<dt>Bust</dt><dd>Measure around the fullest part of your bust.</dd>
			<dt>Waist</dt><dd>Measure around the narrowest part of your waist.</dd>
			<dt>Hip</dt><dd>Measure around the fullest part of your hips.</dd>
			<dt>Slack Bottom</dt><dd>This is the flat half-width of the leg opening of the built-in inner shorts. Double to get full thigh opening circumference. This dimension tells how loose/tight the inner shorts fit around your thighs.</dd>
		</dl>
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

	return bactive_get_size_guide_content( 'court-skort-size-chart', 'court-skort' )
		. bactive_get_size_guide_content( 'bubble-dress-size-chart', 'bubble-dress' )
		. '<section id="sizing-help" class="bactive-size-guide-content"><h2>Other styles</h2><p>For other skorts, dresses, tops and styles, <a href="' . esc_url( home_url( '/contact/' ) ) . '">contact us for the right size guide</a>. The charts above apply only to the Court Skort and Bubble Dress, respectively.</p></section>';
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
