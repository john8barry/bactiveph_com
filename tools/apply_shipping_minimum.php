<?php
/** Private WP-CLI include for issue 77. Never expose this file as an HTTP endpoint. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

function bactive_shipping_manifest_target( $manifest ) {
	$home = get_option( 'home' );
	foreach ( $manifest['targets'] ?? array() as $name => $target ) {
		if ( $home === ( $target['site'] ?? null ) && DB_NAME === ( $target['database'] ?? null ) ) {
			return array( 'name' => $name, 'config' => $target );
		}
	}
	throw new RuntimeException( 'Site or database identity mismatch' );
}

function bactive_shipping_assert_transactional_tables() {
	global $wpdb;
	foreach ( array( $wpdb->posts, $wpdb->options ) as $table ) {
		$engine = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$table
			)
		);
		if ( 'InnoDB' !== $engine ) {
			throw new RuntimeException( 'Required database table is not transactional' );
		}
	}
}

function bactive_shipping_post_state( $item ) {
	$post = get_post( $item['id'] ?? 0 );
	if ( ! $post || 'publish' !== $post->post_status || 'page' !== $post->post_type
		|| $post->post_name !== ( $item['slug'] ?? null ) || ! current_user_can( 'edit_post', $post->ID ) ) {
		throw new RuntimeException( 'Post identity or authorization changed' );
	}
	return $post->post_content;
}

function bactive_shipping_prepare_post( $item, $content, $reverse ) {
	$from_hash = $reverse ? 'after_sha256' : 'before_sha256';
	$to_hash = $reverse ? 'before_sha256' : 'after_sha256';
	$old = $item[ $reverse ? 'new' : 'old' ] ?? '';
	$new = $item[ $reverse ? 'old' : 'new' ] ?? '';
	if ( ! is_string( $content ) || ! hash_equals( $item[ $from_hash ] ?? '', hash( 'sha256', $content ) )
		|| ! is_string( $old ) || '' === $old || ! is_string( $new ) || 1 !== substr_count( $content, $old ) ) {
		throw new RuntimeException( 'Post content precondition changed' );
	}
	$prepared = str_replace( $old, $new, $content, $count );
	if ( 1 !== $count || ! hash_equals( $item[ $to_hash ] ?? '', hash( 'sha256', $prepared ) ) ) {
		throw new RuntimeException( 'Post result hash mismatch' );
	}
	return $prepared;
}

function bactive_shipping_method_states( $manifest, $reverse ) {
	$found = array();
	foreach ( WC_Shipping_Zones::get_zones( 'admin' ) as $zone_row ) {
		$zone = WC_Shipping_Zones::get_zone( $zone_row['id'] );
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( 'free_shipping' === $method->id ) {
				$found[ (int) $method->instance_id ] = array(
					'zone_id' => (int) $zone_row['id'],
					'zone_name' => $zone_row['zone_name'],
					'enabled' => $method->enabled,
				);
			}
		}
	}
	$fallback = new WC_Shipping_Zone( 0 );
	foreach ( $fallback->get_shipping_methods( true ) as $method ) {
		if ( 'free_shipping' === $method->id ) {
			throw new RuntimeException( 'Fallback or international complimentary shipping is enabled' );
		}
	}

	$expected_ids = array_map( 'intval', array_column( $manifest['shipping_methods'] ?? array(), 'instance_id' ) );
	sort( $expected_ids );
	$found_ids = array_keys( $found );
	sort( $found_ids );
	if ( $found_ids !== $expected_ids ) {
		throw new RuntimeException( 'Enabled complimentary shipping methods changed' );
	}

	$states = array();
	foreach ( $manifest['shipping_methods'] as $item ) {
		$instance_id = (int) $item['instance_id'];
		$method = $found[ $instance_id ] ?? null;
		$zone = WC_Shipping_Zones::get_zone( $item['zone_id'] );
		$locations = array_map(
			function ( $location ) {
				return array( 'code' => $location->code, 'type' => $location->type );
			},
			$zone->get_zone_locations()
		);
		usort(
			$locations,
			function ( $left, $right ) {
				return strcmp( $left['type'] . ':' . $left['code'], $right['type'] . ':' . $right['code'] );
			}
		);
		$option_name = $item['option_name'] ?? '';
		$settings = get_option( $option_name, null );
		$from_hash = $reverse ? 'after_sha256' : 'before_sha256';
		$to_hash = $reverse ? 'before_sha256' : 'after_sha256';
		$from_amount = $reverse ? '5000' : '2000';
		$to_amount = $reverse ? '2000' : '5000';
		if ( ! $method || 'yes' !== $method['enabled'] || (int) $item['zone_id'] !== $method['zone_id']
			|| $item['zone_name'] !== $method['zone_name']
			|| ! hash_equals( $item['locations_sha256'] ?? '', hash( 'sha256', wp_json_encode( $locations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) )
			|| 'woocommerce_free_shipping_' . $instance_id . '_settings' !== $option_name
			|| ! is_array( $settings ) || 'min_amount' !== ( $settings['requires'] ?? null )
			|| 'no' !== ( $settings['ignore_discounts'] ?? 'no' )
			|| $from_amount !== (string) ( $settings['min_amount'] ?? '' )
			|| ! hash_equals( $item[ $from_hash ] ?? '', hash( 'sha256', serialize( $settings ) ) ) ) {
			throw new RuntimeException( 'Complimentary shipping method precondition changed' );
		}
		$prepared = $settings;
		$prepared['min_amount'] = $to_amount;
		if ( ! hash_equals( $item[ $to_hash ] ?? '', hash( 'sha256', serialize( $prepared ) ) ) ) {
			throw new RuntimeException( 'Complimentary shipping method result hash mismatch' );
		}
		$states[] = array( 'item' => $item, 'before' => $settings, 'after' => $prepared );
	}
	return $states;
}

function bactive_apply_shipping_minimum_manifest( $manifest, $mode = 'check' ) {
	if ( 1 !== ( $manifest['version'] ?? null ) || 77 !== ( $manifest['issue'] ?? null )
		|| 5000 !== ( $manifest['minimum_php'] ?? null ) || 'blocksy-child' !== get_stylesheet()
		|| ! current_user_can( 'manage_options' ) || ! in_array( $mode, array( 'check', 'apply', 'rollback' ), true ) ) {
		throw new RuntimeException( 'Manifest, capability, theme or mode mismatch' );
	}

	$target = bactive_shipping_manifest_target( $manifest );
	bactive_shipping_assert_transactional_tables();
	$reverse = 'rollback' === $mode;
	$posts = array();
	foreach ( $target['config']['posts'] ?? array() as $item ) {
		$current = bactive_shipping_post_state( $item );
		$prepared = bactive_shipping_prepare_post( $item, $current, $reverse );
		if ( bactive_shipping_prepare_post( $item, $prepared, ! $reverse ) !== $current ) {
			throw new RuntimeException( 'Post rollback round trip failed' );
		}
		$posts[] = array( 'item' => $item, 'before' => $current, 'after' => $prepared );
	}
	$methods = bactive_shipping_method_states( $manifest, $reverse );

	$result = array( 'environment' => $target['name'], 'mode' => $mode, 'complete' => false, 'changed' => array() );
	if ( 'check' === $mode ) {
		$result['complete'] = true;
		return $result;
	}

	global $wpdb;
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		throw new RuntimeException( 'Database transaction could not start' );
	}

	try {
		foreach ( $posts as $post_state ) {
			$item = $post_state['item'];
			$saved = wp_update_post( wp_slash( array( 'ID' => $item['id'], 'post_content' => $post_state['after'] ) ), true );
			if ( is_wp_error( $saved ) ) {
				throw new RuntimeException( 'Post write failed' );
			}
			clean_post_cache( $item['id'] );
			if ( bactive_shipping_post_state( $item ) !== $post_state['after'] ) {
				throw new RuntimeException( 'Post write readback failed' );
			}
			$result['changed'][] = array( 'kind' => 'post', 'id' => $item['id'] );
		}

		foreach ( $methods as $method_state ) {
			$item = $method_state['item'];
			if ( ! update_option( $item['option_name'], $method_state['after'] )
				|| get_option( $item['option_name'], null ) !== $method_state['after'] ) {
				throw new RuntimeException( 'Shipping method write or readback failed' );
			}
			$result['changed'][] = array( 'kind' => 'shipping_method', 'instance_id' => (int) $item['instance_id'] );
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'Database transaction commit was not confirmed' );
		}
		$result['complete'] = true;
		return $result;
	} catch ( Throwable $error ) {
		$rolled_back = false !== $wpdb->query( 'ROLLBACK' );
		foreach ( $posts as $post_state ) {
			clean_post_cache( $post_state['item']['id'] );
		}
		foreach ( $methods as $method_state ) {
			wp_cache_delete( $method_state['item']['option_name'], 'options' );
		}
		wp_cache_delete( 'alloptions', 'options' );

		$restored = $rolled_back;
		foreach ( $posts as $post_state ) {
			$restored = $restored && bactive_shipping_post_state( $post_state['item'] ) === $post_state['before'];
		}
		foreach ( $methods as $method_state ) {
			$restored = $restored && get_option( $method_state['item']['option_name'], null ) === $method_state['before'];
		}
		$result['changed'] = array();
		$result['rolled_back'] = $restored;
		$result['error'] = $restored
			? 'Write failed; the transaction was rolled back and the original state was verified'
			: 'Write outcome is uncertain; stop and perform exact destination recovery';
		return $result;
	}
}
