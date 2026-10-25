<?php
/** Standalone mutation-safety checks for the issue 129 WP-CLI helper. */
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

class TestWpdb {
	public $options = 'wp_options';
	private $snapshot;

	public function prepare( $query, $value ) {
		return $query . '|' . $value;
	}

	public function get_var( $query ) {
		if ( false !== strpos( $query, 'information_schema.TABLES' ) ) {
			return $GLOBALS['table_engine'];
		}
		if ( false !== strpos( $query, 'SELECT COUNT(*)' ) ) {
			list( , $name ) = explode( '|', $query, 2 );
			return array_key_exists( $name, $GLOBALS['options'] ) ? 1 : 0;
		}
		return null;
	}

	public function query( $sql ) {
		if ( 'START TRANSACTION' === $sql ) {
			$this->snapshot = serialize( $GLOBALS['options'] );
			return true;
		}
		if ( 'COMMIT' === $sql ) {
			$this->snapshot = null;
			return true;
		}
		if ( 'ROLLBACK' === $sql ) {
			$GLOBALS['options'] = unserialize( $this->snapshot );
			$this->snapshot = null;
			return true;
		}
		return false;
	}
}

class WooCommerce {}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}
function get_stylesheet() { return 'blocksy-child'; }
function is_multisite() { return false; }
function current_user_can( $capability ) { return true; }
function update_option( $name, $value ) {
	if ( $name === ( $GLOBALS['fail_option'] ?? null ) ) {
		return false;
	}
	if ( array_key_exists( $name, $GLOBALS['options'] ) && $GLOBALS['options'][ $name ] === $value ) {
		return false;
	}
	$GLOBALS['options'][ $name ] = $value;
	return true;
}
function wp_cache_delete( $key, $group = '' ) {}

function fresh_state( $target = 'production' ) {
	$GLOBALS['options'] = array(
		'home' => 'production' === $target ? 'https://bactiveph.com' : 'https://staging.bactiveph.com',
		'woocommerce_store_address' => '',
		'woocommerce_store_address_2' => '',
		'woocommerce_store_city' => '',
		'woocommerce_store_postcode' => '',
		'woocommerce_default_country' => 'production' === $target ? 'PH:DAS' : 'PH:DVO',
		'woocommerce_email_footer_text' => '{site_title}<br />{store_address}',
	);
	$GLOBALS['table_engine'] = 'InnoDB';
	$GLOBALS['fail_option'] = null;
	$GLOBALS['wpdb'] = new TestWpdb();
}

require dirname( __DIR__ ) . '/tools/apply_store_address.php';

fresh_state();
$initial = serialize( $GLOBALS['options'] );
$check_result = bactive_apply_store_address( 'check' );
check( true === $check_result['complete'] && array() === $check_result['changed'], 'Check mode was not read-only' );
check( $initial === serialize( $GLOBALS['options'] ), 'Check mode changed an option' );

$apply_result = bactive_apply_store_address( 'apply' );
check( true === $apply_result['complete'] && 5 === count( $apply_result['changed'] ), 'Production apply did not change the five expected settings' );
check( 'PH:DAS' === $GLOBALS['options']['woocommerce_default_country'], 'Production state changed unexpectedly' );
check( false !== strpos( $GLOBALS['options']['woocommerce_email_footer_text'], 'Unit No. C07, Lombardy Bldg.' ), 'Email footer lacks the exact address' );
check( true === bactive_apply_store_address( 'verify' )['complete'], 'Verify mode rejected the applied values' );
$rollback_result = bactive_apply_store_address( 'rollback' );
check( true === $rollback_result['complete'] && $initial === serialize( $GLOBALS['options'] ), 'Rollback did not restore the exact production baseline' );

fresh_state();
$initial = serialize( $GLOBALS['options'] );
$GLOBALS['fail_option'] = 'woocommerce_store_city';
$failure_result = bactive_apply_store_address( 'apply' );
check( false === $failure_result['complete'] && true === $failure_result['rolled_back'], 'Partial write did not report a verified rollback' );
check( $initial === serialize( $GLOBALS['options'] ), 'Partial write left changed settings behind' );

fresh_state();
$GLOBALS['options']['woocommerce_store_city'] = 'Concurrent change';
try {
	bactive_apply_store_address( 'check' );
	throw new RuntimeException( 'Precondition drift was accepted' );
} catch ( RuntimeException $error ) {
	check( 'WooCommerce store-address precondition changed: woocommerce_store_city' === $error->getMessage(), 'Precondition drift failed for the wrong reason' );
}

fresh_state();
$GLOBALS['table_engine'] = 'MyISAM';
try {
	bactive_apply_store_address( 'check' );
	throw new RuntimeException( 'Non-transactional options table was accepted' );
} catch ( RuntimeException $error ) {
	check( 'Required options table is not transactional' === $error->getMessage(), 'Table engine drift failed for the wrong reason' );
}

echo "Store-address release helper safety checks passed\n";
