<?php
/**
 * Private WP-CLI include for issue 129. Never expose this file as an HTTP endpoint.
 *
 * Run with `wp eval-file` and an authorized WordPress administrator. The helper
 * accepts check, apply, verify, and rollback modes, but refuses any site, database,
 * theme, user, table-engine, or option-value drift.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

function bactive_store_address_manifest() {
	$address = 'Unit No. C07, Lombardy Bldg., Palmetto Place, Purok 16, Gem Village, Ma-a, Talomo District, 8000 City of Davao, Davao del Sur, Philippines';

	return array(
		'version' => 1,
		'issue' => 129,
		'exact_address' => $address,
		'targets' => array(
			'staging' => array(
				'site' => 'https://staging.bactiveph.com',
				'database' => 'waypmvhk_stg',
				'before' => array(
					'woocommerce_store_address' => '',
					'woocommerce_store_address_2' => '',
					'woocommerce_store_city' => '',
					'woocommerce_store_postcode' => '',
					'woocommerce_default_country' => 'PH:DVO',
					'woocommerce_email_footer_text' => '{site_title}<br />{store_address}',
				),
			),
			'production' => array(
				'site' => 'https://bactiveph.com',
				'database' => 'waypmvhk_bactwp',
				'before' => array(
					'woocommerce_store_address' => '',
					'woocommerce_store_address_2' => '',
					'woocommerce_store_city' => '',
					'woocommerce_store_postcode' => '',
					'woocommerce_default_country' => 'PH:DAS',
					'woocommerce_email_footer_text' => '{site_title}<br />{store_address}',
				),
			),
		),
		'after' => array(
			'woocommerce_store_address' => 'Unit No. C07, Lombardy Bldg., Palmetto Place',
			'woocommerce_store_address_2' => 'Purok 16, Gem Village, Ma-a, Talomo District',
			'woocommerce_store_city' => 'City of Davao',
			'woocommerce_store_postcode' => '8000',
			'woocommerce_default_country' => 'PH:DAS',
			'woocommerce_email_footer_text' => '{site_title}<br />' . $address,
		),
	);
}

function bactive_store_address_target( $manifest ) {
	foreach ( $manifest['targets'] as $name => $target ) {
		if ( get_option( 'home' ) === $target['site'] && DB_NAME === $target['database'] ) {
			return array( 'name' => $name, 'config' => $target );
		}
	}
	throw new RuntimeException( 'Site or database identity mismatch' );
}

function bactive_store_address_option_state( $name ) {
	global $wpdb;
	$exists = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $name )
	);
	return array(
		'present' => 1 === $exists,
		'value' => get_option( $name, null ),
	);
}

function bactive_store_address_assert_values( $expected ) {
	foreach ( $expected as $name => $value ) {
		$state = bactive_store_address_option_state( $name );
		if ( ! $state['present'] || $state['value'] !== $value ) {
			throw new RuntimeException( 'WooCommerce store-address precondition changed: ' . $name );
		}
	}
}

function bactive_store_address_clear_cache( $names ) {
	foreach ( $names as $name ) {
		wp_cache_delete( $name, 'options' );
	}
	wp_cache_delete( 'alloptions', 'options' );
}

function bactive_apply_store_address( $mode = 'check' ) {
	$manifest = bactive_store_address_manifest();
	if ( 1 !== $manifest['version'] || 129 !== $manifest['issue']
		|| ! in_array( $mode, array( 'check', 'apply', 'verify', 'rollback' ), true )
		|| is_multisite() || 'blocksy-child' !== get_stylesheet() || ! class_exists( 'WooCommerce' )
		|| ! current_user_can( 'manage_options' ) ) {
		throw new RuntimeException( 'Manifest, capability, WooCommerce, theme, or mode mismatch' );
	}

	$target = bactive_store_address_target( $manifest );
	global $wpdb;
	$engine = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
			$wpdb->options
		)
	);
	if ( 'InnoDB' !== $engine ) {
		throw new RuntimeException( 'Required options table is not transactional' );
	}

	$before = $target['config']['before'];
	$after = $manifest['after'];
	if ( 'check' === $mode ) {
		bactive_store_address_assert_values( $before );
		return array( 'environment' => $target['name'], 'mode' => $mode, 'complete' => true, 'changed' => array() );
	}
	if ( 'verify' === $mode ) {
		bactive_store_address_assert_values( $after );
		return array( 'environment' => $target['name'], 'mode' => $mode, 'complete' => true, 'changed' => array() );
	}

	$from = 'rollback' === $mode ? $after : $before;
	$to = 'rollback' === $mode ? $before : $after;
	bactive_store_address_assert_values( $from );
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		throw new RuntimeException( 'Database transaction could not start' );
	}

	$result = array( 'environment' => $target['name'], 'mode' => $mode, 'complete' => false, 'changed' => array() );
	try {
		foreach ( $to as $name => $value ) {
			if ( $from[ $name ] === $value ) {
				if ( bactive_store_address_option_state( $name )['value'] !== $value ) {
					throw new RuntimeException( 'Unchanged store-address value failed readback: ' . $name );
				}
				continue;
			}
			if ( ! update_option( $name, $value ) || bactive_store_address_option_state( $name )['value'] !== $value ) {
				throw new RuntimeException( 'Store-address write or readback failed: ' . $name );
			}
			$result['changed'][] = $name;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'Database transaction commit was not confirmed' );
		}
		bactive_store_address_clear_cache( array_keys( $to ) );
		bactive_store_address_assert_values( $to );
		$result['complete'] = true;
		return $result;
	} catch ( Throwable $error ) {
		$rolled_back = false !== $wpdb->query( 'ROLLBACK' );
		bactive_store_address_clear_cache( array_keys( $to ) );
		$restored = $rolled_back;
		try {
			bactive_store_address_assert_values( $from );
		} catch ( Throwable $verification_error ) {
			$restored = false;
		}
		$result['changed'] = array();
		$result['rolled_back'] = $restored;
		$result['error'] = $restored
			? 'Write failed; the transaction was rolled back and the original state was verified'
			: 'Write outcome is uncertain; stop and perform exact destination recovery';
		return $result;
	}
}
