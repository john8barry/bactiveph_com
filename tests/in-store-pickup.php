<?php
/** Standalone regression checks for issue 128. */
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 1 );
}

function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

define( 'ABSPATH', __DIR__ . '/' );
function add_filter( ...$args ) {}
function add_action( ...$args ) {}
function absint( $value ) { return abs( (int) $value ); }
function sanitize_title( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_html_class( $value ) { return sanitize_title( $value ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }

require dirname( __DIR__ ) . '/wordpress/wp-content/mu-plugins/bactive-in-store-pickup.php';

class TestRate {
	private $method_id;
	private $id;
	private $instance_id;
	public function __construct( $method_id, $id, $instance_id ) { $this->method_id = $method_id; $this->id = $id; $this->instance_id = $instance_id; }
	public function get_method_id() { return $this->method_id; }
	public function get_id() { return $this->id; }
	public function get_instance_id() { return $this->instance_id; }
}
class TestShippingItem {
	private $method_id;
	private $name;
	private $instance_id;
	public function __construct( $method_id, $name, $instance_id ) { $this->method_id = $method_id; $this->name = $name; $this->instance_id = $instance_id; }
	public function get_method_id() { return $this->method_id; }
	public function get_name() { return $this->name; }
	public function get_instance_id() { return $this->instance_id; }
}
class TestOrder {
	private $items;
	public function __construct( $items ) { $this->items = $items; }
	public function get_shipping_methods() { return $this->items; }
}

use function BactivePH\InStorePickup\normalize_order_shipping_method;
use function BactivePH\InStorePickup\normalize_rate_label;
use function BactivePH\InStorePickup\render_pickup_note;

$pickup = new TestRate( 'local_pickup', 'local_pickup:14', 14 );
$other_pickup = new TestRate( 'local_pickup', 'local_pickup:27', 27 );
$delivery = new TestRate( 'flat_rate', 'flat_rate:12', 12 );
check( 'In-Store Pickup' === normalize_rate_label( 'Local Pickup (Davao City)', $pickup ), 'Pickup rate was not normalized' );
check( 'Pickup at another store' === normalize_rate_label( 'Pickup at another store', $other_pickup ), 'Another pickup instance was changed' );
check( 'Davao City Delivery: ₱80' === normalize_rate_label( 'Davao City Delivery: ₱80', $delivery ), 'Delivery rate label changed' );

ob_start();
render_pickup_note( $pickup, 0 );
$note = preg_replace( '/\s+/', ' ', trim( ob_get_clean() ) );
check( str_contains( $note, 'Collect your order from our Davao City store. This option does not include delivery.' ), 'Pickup note copy changed' );
check( str_contains( $note, 'data-shipping-control="shipping_method_0_local_pickup14"' ), 'Pickup note lost its control association' );
ob_start(); render_pickup_note( $delivery, 0 ); $delivery_note = ob_get_clean();
check( '' === $delivery_note, 'Delivery rate received a pickup note' );
ob_start(); render_pickup_note( $other_pickup, 0 ); $other_note = ob_get_clean();
check( '' === $other_note, 'Another pickup instance received the Davao note' );

$historical = new TestOrder( array( new TestShippingItem( 'local_pickup', 'Local Pickup (Davao City)', 14 ) ) );
check( 'In-Store Pickup' === normalize_order_shipping_method( 'Local Pickup (Davao City)', $historical ), 'Historical pickup display was not normalized' );
$historical_cashier = new TestOrder( array( new TestShippingItem( 'local_pickup', 'In-store pickup — Davao City', 0 ) ) );
check( 'In-Store Pickup' === normalize_order_shipping_method( 'In-store pickup — Davao City', $historical_cashier ), 'Historical cashier display was not normalized' );
$mixed = new TestOrder( array(
	new TestShippingItem( 'local_pickup', 'Local Pickup (Davao City)', 14 ),
	new TestShippingItem( 'flat_rate', 'Davao City Delivery', 12 ),
) );
check( 'In-Store Pickup, Davao City Delivery' === normalize_order_shipping_method( 'Local Pickup (Davao City), Davao City Delivery', $mixed ), 'Mixed order labels changed incorrectly' );
$delivery_order = new TestOrder( array( new TestShippingItem( 'flat_rate', 'Davao City Delivery', 12 ) ) );
check( '<strong>Davao City Delivery</strong>' === normalize_order_shipping_method( '<strong>Davao City Delivery</strong>', $delivery_order ), 'Non-pickup formatting changed' );
$other_store = new TestOrder( array( new TestShippingItem( 'local_pickup', 'Pickup at another store', 27 ) ) );
check( 'Pickup at another store' === normalize_order_shipping_method( 'Pickup at another store', $other_store ), 'Another pickup order was changed' );

$repo = dirname( __DIR__ );
$manifest = json_decode( file_get_contents( $repo . '/content/in-store-pickup.json' ), true );
check( JSON_ERROR_NONE === json_last_error(), 'Pickup manifest is not valid JSON' );
check( 128 === $manifest['issue'] && 14 === $manifest['shipping_method']['instance_id'], 'Pickup manifest identity changed' );
check( array_keys( $manifest['targets'] ) === array( 'production', 'staging' ), 'Pickup target list changed' );
check( 'Local Pickup (Davao City)' === $manifest['shipping_method']['before_title'], 'Pickup preimage title changed' );
check( 'In-Store Pickup' === $manifest['shipping_method']['after_title'], 'Pickup result title changed' );

$cashier = file_get_contents( $repo . '/wordpress/wp-content/plugins/bactive-cashier/includes/class-plugin.php' );
$seed = file_get_contents( $repo . '/tools/cashier/seed.php' );
check( str_contains( $cashier, "set_method_title('In-Store Pickup')" ), 'Cashier order title is inconsistent' );
check( str_contains( $seed, "'title' => 'In-Store Pickup'" ), 'Cashier fixture title is inconsistent' );
foreach ( array( 'run_shipping_http.py', 'run_shipping_setup.py', 'run_shipping_wpcli.py', 'run_shipping_wpdb.py' ) as $fixture ) {
	$content = file_get_contents( $repo . '/' . $fixture );
	check( str_contains( $content, "'title' => 'In-Store Pickup'" ) || str_contains( $content, "['title'] = 'In-Store Pickup'" ), $fixture . ' title is inconsistent' );
	check( ! str_contains( $content, "'title' => 'Local Pickup'" ) && ! str_contains( $content, "['title'] = 'Local Pickup'" ), $fixture . ' retained the old title' );
}

echo "In-store pickup regression checks passed\n";
