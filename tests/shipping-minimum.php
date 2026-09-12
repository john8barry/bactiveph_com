<?php
/** Standalone regression harness for the issue 77 release contract. */
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 1 );
}

function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$repo = dirname( __DIR__ );
$manifest = json_decode( file_get_contents( $repo . '/content/shipping-minimum-5000.json' ), true );
check( JSON_ERROR_NONE === json_last_error(), 'Shipping manifest is not valid JSON' );
check( 1 === $manifest['version'] && 77 === $manifest['issue'] && 5000 === $manifest['minimum_php'], 'Shipping manifest identity changed' );
check( array_keys( $manifest['targets'] ) === array( 'production', 'staging' ), 'Shipping manifest targets changed' );
check( 3 === count( $manifest['shipping_methods'] ), 'Shipping method count changed' );
check( array_column( $manifest['shipping_methods'], 'instance_id' ) === array( 13, 16, 18 ), 'Shipping method instances changed' );
foreach ( $manifest['targets'] as $target ) {
	check( 3 === count( $target['posts'] ), 'Shipping copy target count changed' );
	check( array_column( $target['posts'], 'id' ) === array( 20, 21, 24 ), 'Shipping copy target IDs changed' );
	foreach ( $target['posts'] as $post ) {
		check( substr_count( $post['new'], '₱5,000' ) >= 1, 'New copy lost its minimum' );
		check( str_contains( $post['new'], 'international destinations' ), 'New copy lost its international exclusion' );
		check( ! str_contains( $post['new'], '₱2,000' ) && ! str_contains( $post['new'], 'over ₱5,000' ), 'New copy retained an incorrect boundary' );
	}
}

$primary = file_get_contents( $repo . '/wordpress/wp-content/themes/blocksy-child/functions.php' );
$mirror = file_get_contents( $repo . '/wp-content/themes/blocksy-child/functions.php' );
check( $primary === $mirror, 'Theme source mirrors differ' );
check( 1 === substr_count( $primary, 'function bactive_complimentary_shipping_minimum()' ), 'Minimum helper changed' );
check( str_contains( $primary, 'return 5000;' ), 'Theme minimum is not ₱5,000' );
check( str_contains( $primary, "return 'PH' === strtoupper" ), 'Domestic destination gate is missing' );
check( str_contains( $primary, "add_filter( 'woocommerce_package_rates', 'bactive_restrict_complimentary_shipping_rates', 100, 2 )" ), 'International rate guard is missing' );
check( str_contains( $primary, 'It does not apply to international destinations.' ), 'Product tab lost its international exclusion' );
check( ! str_contains( $primary, 'Complimentary shipping on orders over ₱2,000.' ), 'Product tab retained the old minimum' );
check( ! str_contains( $primary, '$free_shipping_threshold = 2000;' ), 'Cart retained the old minimum' );

class TestCustomer {
	public $shipping;
	public $billing;
	public function __construct( $shipping, $billing = '' ) { $this->shipping = $shipping; $this->billing = $billing; }
	public function get_shipping_country() { return $this->shipping; }
	public function get_billing_country() { return $this->billing; }
}
class TestCart {
	public $subtotal;
	public $discount;
	public $discount_tax;
	public $including_tax;
	public function __construct( $subtotal, $discount = 0, $discount_tax = 0, $including_tax = false ) {
		$this->subtotal = $subtotal;
		$this->discount = $discount;
		$this->discount_tax = $discount_tax;
		$this->including_tax = $including_tax;
	}
	public function is_empty() { return false; }
	public function get_displayed_subtotal() { return $this->subtotal; }
	public function get_discount_total() { return $this->discount; }
	public function get_discount_tax() { return $this->discount_tax; }
	public function display_prices_including_tax() { return $this->including_tax; }
}
class TestWoo {
	public $customer;
	public $cart;
	public function __construct( $country, $subtotal, $discount = 0, $discount_tax = 0, $including_tax = false ) {
		$this->customer = new TestCustomer( $country );
		$this->cart = new TestCart( $subtotal, $discount, $discount_tax, $including_tax );
	}
}
function WC() { return $GLOBALS['woo']; }
function wc_get_base_location() { return array( 'country' => 'PH' ); }
function wc_get_price_decimals() { return 2; }
function add_filter( ...$args ) {}

class TestRate {
	private $method_id;
	public function __construct( $method_id ) { $this->method_id = $method_id; }
	public function get_method_id() { return $this->method_id; }
}

$helper_start = strpos( $primary, 'function bactive_complimentary_shipping_minimum()' );
$helper_end = strpos( $primary, 'function bactive_fabric_care_tab_content()', $helper_start );
check( false !== $helper_start && false !== $helper_end, 'Shipping helper section not found' );
eval( substr( $primary, $helper_start, $helper_end - $helper_start ) );
$progress_start = strpos( $primary, 'function bactive_free_shipping_progress_bar()' );
$progress_end = strpos( $primary, '// END PHASE 6 SNIPPETS', $progress_start );
check( false !== $progress_start && false !== $progress_end, 'Cart progress section not found' );
eval( substr( $primary, $progress_start, $progress_end - $progress_start ) );

$GLOBALS['woo'] = new TestWoo( 'PH', 1500 );
ob_start(); bactive_free_shipping_progress_bar(); $below = ob_get_clean();
check( str_contains( $below, '₱3,500.00' ) && str_contains( $below, 'complimentary shipping' ), 'Domestic progress amount is wrong' );
$GLOBALS['woo'] = new TestWoo( 'PH', 5000 );
ob_start(); bactive_free_shipping_progress_bar(); $qualified = ob_get_clean();
check( str_contains( $qualified, 'unlocked' ) && str_contains( $qualified, 'complimentary shipping' ), 'Domestic minimum does not unlock' );
$GLOBALS['woo'] = new TestWoo( 'PH', 5200, 300 );
ob_start(); bactive_free_shipping_progress_bar(); $discounted = ob_get_clean();
check( str_contains( $discounted, '₱100.00' ) && ! str_contains( $discounted, 'unlocked' ), 'Discounted cart claim disagrees with WooCommerce eligibility' );
$GLOBALS['woo'] = new TestWoo( 'PH', 5300, 200, 150, true );
ob_start(); bactive_free_shipping_progress_bar(); $discounted_tax = ob_get_clean();
check( str_contains( $discounted_tax, '₱50.00' ) && ! str_contains( $discounted_tax, 'unlocked' ), 'Tax-inclusive discount claim disagrees with WooCommerce eligibility' );
$GLOBALS['woo'] = new TestWoo( 'US', 7000 );
ob_start(); bactive_free_shipping_progress_bar(); $international = ob_get_clean();
check( '' === $international, 'International destination received a complimentary shipping claim' );
$rates = array( 'free:13' => new TestRate( 'free_shipping' ), 'flat:12' => new TestRate( 'flat_rate' ) );
$foreign_rates = bactive_restrict_complimentary_shipping_rates( $rates, array( 'destination' => array( 'country' => 'US', 'postcode' => '8000' ) ) );
check( ! isset( $foreign_rates['free:13'] ) && isset( $foreign_rates['flat:12'] ), 'International postcode collision retained free shipping' );
$domestic_rates = bactive_restrict_complimentary_shipping_rates( $rates, array( 'destination' => array( 'country' => 'PH', 'postcode' => '8000' ) ) );
check( isset( $domestic_rates['free:13'] ) && isset( $domestic_rates['flat:12'] ), 'Domestic rate guard removed a valid method' );
$GLOBALS['woo'] = new TestWoo( 'PH', 1000 );
ob_start(); bactive_shipping_returns_tab_content(); $tab = ob_get_clean();
check( str_contains( $tab, '₱5,000 or more' ) && str_contains( $tab, 'international destinations' ), 'Product tab copy is incomplete' );

echo "Shipping minimum regression checks passed\n";
