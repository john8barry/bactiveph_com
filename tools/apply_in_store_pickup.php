<?php
/** Private WP-CLI include for issue 128. Never expose this file as an HTTP endpoint. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

function bactive_pickup_target( $manifest ) {
	$home = untrailingslashit( get_option( 'home' ) );
	foreach ( $manifest['targets'] ?? array() as $name => $target ) {
		if ( $home === untrailingslashit( $target['site'] ?? '' ) && DB_NAME === ( $target['database'] ?? null ) ) {
			return $name;
		}
	}
	throw new RuntimeException( 'Site or database identity mismatch' );
}

function bactive_pickup_state( $manifest, $reverse ) {
	global $wpdb;
	$engine = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
			$wpdb->options
		)
	);
	if ( 'InnoDB' !== $engine ) {
		throw new RuntimeException( 'Options table is not transactional' );
	}

	$item = $manifest['shipping_method'] ?? array();
	$found = array();
	foreach ( WC_Shipping_Zones::get_zones( 'admin' ) as $zone_row ) {
		$zone = WC_Shipping_Zones::get_zone( $zone_row['zone_id'] );
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( 'local_pickup' === $method->id ) {
				$found[] = array(
					'zone_id' => (int) $zone_row['zone_id'],
					'zone_name' => $zone_row['zone_name'],
					'instance_id' => (int) $method->instance_id,
					'enabled' => $method->enabled,
				);
			}
		}
	}
	foreach ( ( new WC_Shipping_Zone( 0 ) )->get_shipping_methods( true ) as $method ) {
		if ( 'local_pickup' === $method->id ) {
			throw new RuntimeException( 'Unexpected fallback pickup method' );
		}
	}

	if ( 1 !== count( $found ) || $found[0] !== array(
		'zone_id' => (int) ( $item['zone_id'] ?? 0 ),
		'zone_name' => $item['zone_name'] ?? '',
		'instance_id' => (int) ( $item['instance_id'] ?? 0 ),
		'enabled' => 'yes',
	) ) {
		throw new RuntimeException( 'Pickup method identity or enabled state changed' );
	}

	$option_name = $item['option_name'] ?? '';
	if ( 'woocommerce_local_pickup_' . (int) $item['instance_id'] . '_settings' !== $option_name ) {
		throw new RuntimeException( 'Pickup option identity changed' );
	}
	$settings = get_option( $option_name, null );
	$from_hash = $reverse ? 'after_sha256' : 'before_sha256';
	$to_hash = $reverse ? 'before_sha256' : 'after_sha256';
	$from_title = $item[ $reverse ? 'after_title' : 'before_title' ] ?? '';
	$to_title = $item[ $reverse ? 'before_title' : 'after_title' ] ?? '';
	if ( ! is_array( $settings ) || $from_title !== ( $settings['title'] ?? null )
		|| ! hash_equals( $item[ $from_hash ] ?? '', hash( 'sha256', serialize( $settings ) ) ) ) {
		throw new RuntimeException( 'Pickup settings precondition changed' );
	}
	$prepared = $settings;
	$prepared['title'] = $to_title;
	if ( ! hash_equals( $item[ $to_hash ] ?? '', hash( 'sha256', serialize( $prepared ) ) ) ) {
		throw new RuntimeException( 'Pickup settings result hash mismatch' );
	}
	return array( 'option_name' => $option_name, 'before' => $settings, 'after' => $prepared );
}

function bactive_apply_in_store_pickup( $manifest, $mode = 'check' ) {
	if ( 1 !== ( $manifest['version'] ?? null ) || 128 !== ( $manifest['issue'] ?? null )
		|| 'In-Store Pickup' !== ( $manifest['label'] ?? null ) || 'blocksy-child' !== get_stylesheet()
		|| ! current_user_can( 'manage_options' ) || ! in_array( $mode, array( 'check', 'apply', 'rollback' ), true ) ) {
		throw new RuntimeException( 'Manifest, capability, theme or mode mismatch' );
	}
	$environment = bactive_pickup_target( $manifest );
	$reverse = 'rollback' === $mode;
	$state = bactive_pickup_state( $manifest, $reverse );
	$result = array( 'environment' => $environment, 'mode' => $mode, 'complete' => false, 'changed' => array() );
	if ( 'check' === $mode ) {
		$result['complete'] = true;
		return $result;
	}

	global $wpdb;
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		throw new RuntimeException( 'Database transaction could not start' );
	}
	try {
		if ( ! update_option( $state['option_name'], $state['after'] )
			|| get_option( $state['option_name'], null ) !== $state['after'] ) {
			throw new RuntimeException( 'Pickup settings write or readback failed' );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'Database transaction commit was not confirmed' );
		}
		$result['changed'][] = array( 'kind' => 'shipping_method', 'instance_id' => 14 );
		$result['complete'] = true;
		return $result;
	} catch ( Throwable $error ) {
		$rolled_back = false !== $wpdb->query( 'ROLLBACK' );
		wp_cache_delete( $state['option_name'], 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$restored = $rolled_back && get_option( $state['option_name'], null ) === $state['before'];
		$result['rolled_back'] = $restored;
		$result['error'] = $restored
			? 'Write failed; the transaction was rolled back and the original settings were verified'
			: 'Write outcome is uncertain; stop and perform exact destination recovery';
		return $result;
	}
}
