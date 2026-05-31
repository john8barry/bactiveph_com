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
}
add_action( 'wp_enqueue_scripts', 'blocksy_child_enqueue_styles' );

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

function bactive_shipping_returns_tab_content() {
	echo '<h2>Shipping & Returns</h2>';
	echo '<p><strong>Shipping</strong><br>We ship nationwide across the Philippines via J&T Express and Ninja Van. Complimentary shipping on orders over ₱2,000.</p>';
	echo '<p><strong>Returns & Exchanges</strong><br>We want you in the right size. If your fit isn\'t perfect, we accept size exchanges within 7 days of delivery for unworn items with tags attached and original packaging.</p>';
}

function bactive_fabric_care_tab_content() {
	echo '<h2>Fabric & Care</h2>';
	echo '<p><strong>CourtSoft™</strong><br>Our signature four-way-stretch knit: buttery-soft, squat-proof, sweat-wicking and built to hold its shape.</p>';
	echo '<p><strong>BreezeKnit™</strong><br>Lightweight and breathable for hot-court days — moves air, moves sweat, keeps you cool.</p>';
	echo '<p><strong>Care basics</strong><br>Machine wash cold on gentle with like colours. Skip the fabric softener (it coats performance fibres). Hang dry or tumble low. Don\'t bleach or iron print.</p>';
}

/**
 * Phase 4: Size Guide Modal Link
 */
add_action( 'woocommerce_single_product_summary', 'bactive_size_guide_link', 25 );
function bactive_size_guide_link() {
	echo '<a href="#" class="bactive-size-guide-link" aria-label="Open Size Guide">True to size (Asian fit) &rarr; Size Guide</a>';
}

/**
 * Phase 4: Size Guide Modal HTML (Output in footer)
 */
add_action( 'wp_footer', 'bactive_size_guide_modal' );
function bactive_size_guide_modal() {
	if ( ! is_product() ) return;
	?>
	<dialog id="bactive-size-modal" class="bactive-modal">
		<div class="bactive-modal-inner">
			<button class="bactive-modal-close" aria-label="Close modal">&times;</button>
			<h2>Find your fit</h2>
			<p>B Active is designed with an Asian fit and runs true to size. If you\'re between sizes, size up for a relaxed feel or stay true for a closer fit.</p>
			<h3>How to measure</h3>
			<p><strong>Bust</strong> — around the fullest part.<br><strong>Waist</strong> — the narrowest part of your torso.<br><strong>Hips</strong> — the fullest part.</p>
			<table class="bactive-size-table">
				<thead>
					<tr><th>Size</th><th>Bust (cm)</th><th>Waist (cm)</th><th>Hips (cm)</th></tr>
				</thead>
				<tbody>
					<tr><td>S</td><td>80–84</td><td>62–66</td><td>86–90</td></tr>
					<tr><td>M</td><td>85–89</td><td>67–71</td><td>91–95</td></tr>
					<tr><td>L</td><td>90–95</td><td>72–77</td><td>96–101</td></tr>
					<tr><td>XL</td><td>96–101</td><td>78–83</td><td>102–107</td></tr>
				</tbody>
			</table>
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
    if ( 'cod' === $chosen_gateway ) {
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
    echo '<div style="text-align:center; font-size:13px; margin-top:20px; color:#2B2A28;">Secure checkout &middot; GCash &middot; Maya &middot; Cards &middot; COD &middot; 7-day size-exchange guarantee</div>';
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

// Free Shipping Progress Bar
add_action( 'woocommerce_widget_shopping_cart_before_buttons', 'bactive_free_shipping_progress_bar', 5 );
function bactive_free_shipping_progress_bar() {
    if ( ! WC()->cart || WC()->cart->is_empty() ) return;
    
    $free_shipping_threshold = 2000;
    $cart_subtotal = WC()->cart->get_cart_contents_total();
    
    if ( $cart_subtotal < $free_shipping_threshold ) {
        $amount_left = $free_shipping_threshold - $cart_subtotal;
        echo '<div style="background:#FAF8F4; border:1px solid #E5E5E5; padding:10px; text-align:center; margin-bottom:15px; font-size:13px; color:#2B2A28;">You are just <strong>₱' . number_format($amount_left, 2) . '</strong> away from free shipping!</div>';
    } else {
        echo '<div style="background:#5E6E54; color:#FAF8F4; padding:10px; text-align:center; margin-bottom:15px; font-size:13px;">You have unlocked <strong>Free Shipping!</strong></div>';
    }
}
// END PHASE 6 SNIPPETS
