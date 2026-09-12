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
		$option_name = $item['option_name'] ?? '';
		$settings = get_option( $option_name, null );
		$from_hash = $reverse ? 'after_sha256' : 'before_sha256';
		$to_hash = $reverse ? 'before_sha256' : 'after_sha256';
		$from_amount = $reverse ? '5000' : '2000';
		$to_amount = $reverse ? '2000' : '5000';
		if ( ! $method || 'yes' !== $method['enabled'] || (int) $item['zone_id'] !== $method['zone_id']
			|| $item['zone_name'] !== $method['zone_name']
			|| 'woocommerce_free_shipping_' . $instance_id . '_settings' !== $option_name
			|| ! is_array( $settings ) || 'min_amount' !== ( $settings['requires'] ?? null )
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

	foreach ( $posts as $post_state ) {
		$item = $post_state['item'];
		$saved = wp_update_post( wp_slash( array( 'ID' => $item['id'], 'post_content' => $post_state['after'] ) ), true );
		if ( is_wp_error( $saved ) ) {
			$result['error'] = 'Stopped; inspect the destination before any further write';
			return $result;
		}
		clean_post_cache( $item['id'] );
		if ( bactive_shipping_post_state( $item ) !== $post_state['after'] ) {
			$result['error'] = 'Stopped; inspect the destination before any further write';
			return $result;
		}
		$result['changed'][] = array( 'kind' => 'post', 'id' => $item['id'] );
	}

	foreach ( $methods as $method_state ) {
		$item = $method_state['item'];
		if ( ! update_option( $item['option_name'], $method_state['after'] )
			|| get_option( $item['option_name'], null ) !== $method_state['after'] ) {
			$result['error'] = 'Stopped; inspect the destination before any further write';
			return $result;
		}
		$result['changed'][] = array( 'kind' => 'shipping_method', 'instance_id' => (int) $item['instance_id'] );
	}

	$result['complete'] = true;
	return $result;
}
