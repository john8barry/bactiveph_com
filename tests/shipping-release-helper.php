<?php
/** Standalone mutation-safety tests for the issue 77 private release helper. */
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

class TestWpError {}

class TestWpdb {
	public $posts = 'wp_posts';
	public $options = 'wp_options';
	private $snapshot;

	public function prepare( $query, $value ) {
		return $query . '|' . $value;
	}

	public function get_var( $query ) {
		return $GLOBALS['table_engine'];
	}

	public function query( $sql ) {
		if ( 'START TRANSACTION' === $sql ) {
			$this->snapshot = serialize( array( $GLOBALS['posts'], $GLOBALS['options'] ) );
			return true;
		}
		if ( 'ROLLBACK' === $sql ) {
			list( $GLOBALS['posts'], $GLOBALS['options'] ) = unserialize( $this->snapshot );
			$this->snapshot = null;
			return true;
		}
		if ( 'COMMIT' === $sql ) {
			$this->snapshot = null;
			return true;
		}
		return false;
	}
}

class TestShippingMethod {
	public $id = 'free_shipping';
	public $instance_id;
	public $enabled = 'yes';

	public function __construct( $instance_id ) {
		$this->instance_id = $instance_id;
	}
}

class TestZoneLocation {
	public $code;
	public $type;

	public function __construct( $code, $type ) {
		$this->code = $code;
		$this->type = $type;
	}
}

class TestShippingZone {
	private $id;

	public function __construct( $id ) {
		$this->id = $id;
	}

	public function get_shipping_methods( $enabled_only = false ) {
		if ( 0 === $this->id ) {
			return $GLOBALS['fallback_methods'];
		}
		return array( new TestShippingMethod( $GLOBALS['zone_instances'][ $this->id ] ) );
	}

	public function get_zone_locations() {
		return $GLOBALS['zone_locations'][ $this->id ];
	}
}

class WC_Shipping_Zone extends TestShippingZone {}

class WC_Shipping_Zones {
	public static function get_zones( $context = 'admin' ) {
		return array(
			1 => array( 'id' => 1, 'zone_name' => 'Davao City' ),
			2 => array( 'id' => 2, 'zone_name' => 'Mindanao (excl. Davao)' ),
			3 => array( 'id' => 3, 'zone_name' => 'Luzon & Visayas' ),
		);
	}

	public static function get_zone( $id ) {
		return new TestShippingZone( (int) $id );
	}
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}

function get_stylesheet() {
	return 'blocksy-child';
}

function current_user_can( $capability, $id = null ) {
	return true;
}

function get_post( $id ) {
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
		return null;
	}
	$row = $GLOBALS['posts'][ $id ];
	return (object) array(
		'ID' => $id,
		'post_status' => 'publish',
		'post_type' => 'page',
		'post_name' => $row['slug'],
		'post_content' => $row['content'],
	);
}

function wp_update_post( $post, $return_error = false ) {
	$id = (int) $post['ID'];
	if ( $id === ( $GLOBALS['fail_post'] ?? null ) ) {
		return new TestWpError();
	}
	$GLOBALS['posts'][ $id ]['content'] = $post['post_content'];
	return $id;
}

function wp_slash( $value ) {
	return $value;
}

function is_wp_error( $value ) {
	return $value instanceof TestWpError;
}

function clean_post_cache( $id ) {}

function update_option( $name, $value ) {
	if ( $name === ( $GLOBALS['fail_option'] ?? null ) ) {
		return false;
	}
	$GLOBALS['options'][ $name ] = $value;
	return true;
}

function wp_cache_delete( $key, $group = '' ) {}

function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}

function fresh_state() {
	$GLOBALS['posts'] = array(
		20 => array( 'slug' => 'shipping-returns', 'content' => 'Before OLD-20 after' ),
		21 => array( 'slug' => 'faq', 'content' => 'Before OLD-21 after' ),
		24 => array( 'slug' => 'terms', 'content' => 'Before OLD-24 after' ),
	);
	$GLOBALS['options'] = array(
		'home' => 'https://bactiveph.com',
		'woocommerce_free_shipping_13_settings' => array( 'title' => 'Free shipping', 'requires' => 'min_amount', 'min_amount' => '2000', 'ignore_discounts' => 'no' ),
		'woocommerce_free_shipping_16_settings' => array( 'title' => 'Free shipping', 'requires' => 'min_amount', 'min_amount' => '2000', 'ignore_discounts' => 'no' ),
		'woocommerce_free_shipping_18_settings' => array( 'title' => 'Free shipping', 'requires' => 'min_amount', 'min_amount' => '2000', 'ignore_discounts' => 'no' ),
	);
	$GLOBALS['zone_instances'] = array( 1 => 13, 2 => 16, 3 => 18 );
	$GLOBALS['zone_locations'] = array(
		1 => array( new TestZoneLocation( 'PH-11', 'state' ) ),
		2 => array( new TestZoneLocation( 'PH-12', 'state' ) ),
		3 => array( new TestZoneLocation( 'PH-01', 'state' ) ),
	);
	$GLOBALS['fallback_methods'] = array();
	$GLOBALS['fail_post'] = null;
	$GLOBALS['fail_option'] = null;
	$GLOBALS['table_engine'] = 'InnoDB';
	$GLOBALS['wpdb'] = new TestWpdb();
}

function build_manifest() {
	$manifest = array(
		'version' => 1,
		'issue' => 77,
		'minimum_php' => 5000,
		'targets' => array(
			'production' => array(
				'site' => 'https://bactiveph.com',
				'database' => 'waypmvhk_bactwp',
				'posts' => array(),
			),
		),
		'shipping_methods' => array(),
	);
	foreach ( array( 20, 21, 24 ) as $id ) {
		$before = $GLOBALS['posts'][ $id ]['content'];
		$after = str_replace( 'OLD-' . $id, 'NEW-' . $id, $before );
		$manifest['targets']['production']['posts'][] = array(
			'id' => $id,
			'slug' => $GLOBALS['posts'][ $id ]['slug'],
			'before_sha256' => hash( 'sha256', $before ),
			'after_sha256' => hash( 'sha256', $after ),
			'old' => 'OLD-' . $id,
			'new' => 'NEW-' . $id,
		);
	}
	$zone_names = array( 1 => 'Davao City', 2 => 'Mindanao (excl. Davao)', 3 => 'Luzon & Visayas' );
	foreach ( $GLOBALS['zone_instances'] as $zone_id => $instance_id ) {
		$option_name = 'woocommerce_free_shipping_' . $instance_id . '_settings';
		$before = $GLOBALS['options'][ $option_name ];
		$after = $before;
		$after['min_amount'] = '5000';
		$locations = array_map(
			function ( $location ) {
				return array( 'code' => $location->code, 'type' => $location->type );
			},
			$GLOBALS['zone_locations'][ $zone_id ]
		);
		$manifest['shipping_methods'][] = array(
			'zone_id' => $zone_id,
			'zone_name' => $zone_names[ $zone_id ],
			'locations_sha256' => hash( 'sha256', json_encode( $locations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
			'instance_id' => $instance_id,
			'option_name' => $option_name,
			'before_sha256' => hash( 'sha256', serialize( $before ) ),
			'after_sha256' => hash( 'sha256', serialize( $after ) ),
		);
	}
	return $manifest;
}

require dirname( __DIR__ ) . '/tools/apply_shipping_minimum.php';

fresh_state();
$manifest = build_manifest();
$GLOBALS['table_engine'] = 'MyISAM';
try {
	bactive_apply_shipping_minimum_manifest( $manifest, 'check' );
	throw new RuntimeException( 'Non-transactional tables were accepted' );
} catch ( RuntimeException $error ) {
	check( 'Required database table is not transactional' === $error->getMessage(), 'Table engine drift failed for the wrong reason' );
}

fresh_state();
$manifest = build_manifest();
$initial_state = serialize( array( $GLOBALS['posts'], $GLOBALS['options'] ) );
$check_result = bactive_apply_shipping_minimum_manifest( $manifest, 'check' );
check( true === $check_result['complete'] && array() === $check_result['changed'], 'Check mode did not remain read-only' );
check( $initial_state === serialize( array( $GLOBALS['posts'], $GLOBALS['options'] ) ), 'Check mode changed state' );

$apply_result = bactive_apply_shipping_minimum_manifest( $manifest, 'apply' );
check( true === $apply_result['complete'] && 6 === count( $apply_result['changed'] ), 'Apply did not change all six records' );
check( '5000' === $GLOBALS['options']['woocommerce_free_shipping_13_settings']['min_amount'], 'Apply did not update the shipping minimum' );
$rollback_result = bactive_apply_shipping_minimum_manifest( $manifest, 'rollback' );
check( true === $rollback_result['complete'] && 6 === count( $rollback_result['changed'] ), 'Rollback did not restore all six records' );
check( $initial_state === serialize( array( $GLOBALS['posts'], $GLOBALS['options'] ) ), 'Rollback did not restore the exact initial state' );

fresh_state();
$manifest = build_manifest();
$initial_state = serialize( array( $GLOBALS['posts'], $GLOBALS['options'] ) );
$GLOBALS['fail_option'] = 'woocommerce_free_shipping_16_settings';
$failure_result = bactive_apply_shipping_minimum_manifest( $manifest, 'apply' );
check( false === $failure_result['complete'] && true === $failure_result['rolled_back'], 'Partial failure was not reported as safely rolled back' );
check( array() === $failure_result['changed'], 'Partial failure reported committed changes' );
check( $initial_state === serialize( array( $GLOBALS['posts'], $GLOBALS['options'] ) ), 'Partial failure left mixed records' );

fresh_state();
$manifest = build_manifest();
$GLOBALS['zone_locations'][1][] = new TestZoneLocation( 'US', 'country' );
try {
	bactive_apply_shipping_minimum_manifest( $manifest, 'check' );
	throw new RuntimeException( 'International zone drift was accepted' );
} catch ( RuntimeException $error ) {
	check( 'Complimentary shipping method precondition changed' === $error->getMessage(), 'International zone drift failed for the wrong reason' );
}

fresh_state();
$manifest = build_manifest();
$GLOBALS['fallback_methods'][] = new TestShippingMethod( 99 );
try {
	bactive_apply_shipping_minimum_manifest( $manifest, 'check' );
	throw new RuntimeException( 'Fallback free shipping was accepted' );
} catch ( RuntimeException $error ) {
	check( 'Fallback or international complimentary shipping is enabled' === $error->getMessage(), 'Fallback free shipping failed for the wrong reason' );
}

fresh_state();
$manifest = build_manifest();
$GLOBALS['options']['woocommerce_free_shipping_13_settings']['ignore_discounts'] = 'yes';
try {
	bactive_apply_shipping_minimum_manifest( $manifest, 'check' );
	throw new RuntimeException( 'Changed discount eligibility was accepted' );
} catch ( RuntimeException $error ) {
	check( 'Complimentary shipping method precondition changed' === $error->getMessage(), 'Discount eligibility failed for the wrong reason' );
}

echo "Shipping release helper safety checks passed\n";
