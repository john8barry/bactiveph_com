<?php
/** Mutation-safety tests for the issue 128 private release helper. */
if ( PHP_SAPI !== 'cli' || defined( 'ABSPATH' ) ) {
	exit( 1 );
}

define( 'WP_CLI', true );
define( 'DB_NAME', 'waypmvhk_bactwp' );

function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function untrailingslashit( $value ) { return rtrim( $value, '/\\' ); }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function get_stylesheet() { return 'blocksy-child'; }
function current_user_can( $capability ) { return true; }
function wp_cache_delete( $key, $group = '' ) {}
function update_option( $name, $value ) {
	if ( $GLOBALS['fail_update'] ) { return false; }
	$GLOBALS['options'][ $name ] = $value;
	return true;
}

class TestWpdb {
	public $options = 'wp_options';
	private $snapshot;
	public function prepare( $query, $value ) { return $query . '|' . $value; }
	public function get_var( $query ) { return $GLOBALS['table_engine']; }
	public function query( $sql ) {
		if ( 'START TRANSACTION' === $sql ) { $this->snapshot = $GLOBALS['options']; return true; }
		if ( 'ROLLBACK' === $sql ) { $GLOBALS['options'] = $this->snapshot; return true; }
		if ( 'COMMIT' === $sql ) { $this->snapshot = null; return true; }
		return false;
	}
}
class TestPickupMethod {
	public $id = 'local_pickup';
	public $instance_id = 14;
	public $enabled = 'yes';
}
class TestShippingZone {
	private $id;
	public function __construct( $id ) { $this->id = (int) $id; }
	public function get_shipping_methods( $enabled_only = false ) {
		return 1 === $this->id ? $GLOBALS['pickup_methods'] : $GLOBALS['fallback_methods'];
	}
}
class WC_Shipping_Zone extends TestShippingZone {}
class WC_Shipping_Zones {
	public static function get_zones( $context = 'admin' ) {
		return array( 1 => array( 'zone_id' => 1, 'zone_name' => $GLOBALS['zone_name'] ) );
	}
	public static function get_zone( $id ) { return new TestShippingZone( $id ); }
}

function fresh_state() {
	$GLOBALS['options'] = array(
		'home' => 'https://bactiveph.com',
		'woocommerce_local_pickup_14_settings' => array( 'title' => 'Local Pickup (Davao City)', 'tax_status' => 'none', 'cost' => '' ),
	);
	$GLOBALS['pickup_methods'] = array( new TestPickupMethod() );
	$GLOBALS['fallback_methods'] = array();
	$GLOBALS['zone_name'] = 'Davao City';
	$GLOBALS['table_engine'] = 'InnoDB';
	$GLOBALS['fail_update'] = false;
	$GLOBALS['wpdb'] = new TestWpdb();
}

$manifest = json_decode( file_get_contents( dirname( __DIR__ ) . '/content/in-store-pickup.json' ), true );
require dirname( __DIR__ ) . '/tools/apply_in_store_pickup.php';

fresh_state();
$initial = serialize( $GLOBALS['options'] );
$checked = bactive_apply_in_store_pickup( $manifest, 'check' );
check( $checked['complete'] && array() === $checked['changed'], 'Check mode did not remain read-only' );
check( $initial === serialize( $GLOBALS['options'] ), 'Check mode changed settings' );
$applied = bactive_apply_in_store_pickup( $manifest, 'apply' );
check( $applied['complete'] && 'In-Store Pickup' === $GLOBALS['options']['woocommerce_local_pickup_14_settings']['title'], 'Apply did not update only the title' );
check( 'none' === $GLOBALS['options']['woocommerce_local_pickup_14_settings']['tax_status'] && '' === $GLOBALS['options']['woocommerce_local_pickup_14_settings']['cost'], 'Apply changed pickup behavior' );
$rolled_back = bactive_apply_in_store_pickup( $manifest, 'rollback' );
check( $rolled_back['complete'] && $initial === serialize( $GLOBALS['options'] ), 'Rollback did not restore exact settings' );

fresh_state();
$GLOBALS['table_engine'] = 'MyISAM';
try { bactive_apply_in_store_pickup( $manifest, 'check' ); throw new RuntimeException( 'Non-transactional options were accepted' ); }
catch ( RuntimeException $error ) { check( 'Options table is not transactional' === $error->getMessage(), 'Table drift failed for the wrong reason' ); }

fresh_state();
$GLOBALS['zone_name'] = 'Changed zone';
try { bactive_apply_in_store_pickup( $manifest, 'check' ); throw new RuntimeException( 'Zone drift was accepted' ); }
catch ( RuntimeException $error ) { check( 'Pickup method identity or enabled state changed' === $error->getMessage(), 'Zone drift failed for the wrong reason' ); }

fresh_state();
$GLOBALS['fallback_methods'] = array( new TestPickupMethod() );
try { bactive_apply_in_store_pickup( $manifest, 'check' ); throw new RuntimeException( 'Fallback pickup was accepted' ); }
catch ( RuntimeException $error ) { check( 'Unexpected fallback pickup method' === $error->getMessage(), 'Fallback pickup failed for the wrong reason' ); }

fresh_state();
$GLOBALS['fail_update'] = true;
$failed = bactive_apply_in_store_pickup( $manifest, 'apply' );
check( ! $failed['complete'] && $failed['rolled_back'], 'Failed write did not verify rollback' );
check( 'Local Pickup (Davao City)' === $GLOBALS['options']['woocommerce_local_pickup_14_settings']['title'], 'Failed write changed settings' );

echo "In-store pickup release helper safety checks passed\n";
