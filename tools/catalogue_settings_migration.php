<?php
/** CLI only. Load after WordPress. No defaults-option activation or commerce writes.
 * BACTIVE_MIGRATION_LIBRARY=1 loads functions for tests without dispatch.
 * wp eval-file tools/catalogue_settings_migration.php dry-run MANIFEST SHA
 * wp eval-file tools/catalogue_settings_migration.php apply PLAN SHA
 * wp eval-file tools/catalogue_settings_migration.php rollback PLAN SHA
 * stdout is JSON; the operator persists and hashes dry-run output privately.
 */
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }

function bcm_assert( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function bcm_json( $value ) { return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ); }
function bcm_target() {
    $site = untrailingslashit( get_option( 'siteurl' ) );
    bcm_assert( untrailingslashit( get_option( 'home' ) ) === $site, 'Home/site mismatch' );
    $parts = wp_parse_url( $site );
    $local = 'local' === wp_get_environment_type() && in_array( $parts['host'] ?? '', array( '127.0.0.1', 'localhost', '[::1]' ), true )
        && in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) && defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON
        && defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL;
    bcm_assert( 'https://bactiveph.com' === $site || $local, 'Target rejected' );
    foreach ( array( 'user', 'pass', 'query', 'fragment', 'path' ) as $key ) { bcm_assert( empty( $parts[ $key ] ), 'Target components rejected' ); }
    return $site;
}
function bcm_input( $path, $sha ) {
    bcm_assert( is_string( $path ) && ! is_link( $path ) && is_file( $path ) && filesize( $path ) < 10000000, 'Invalid input file' );
    $bytes = file_get_contents( $path );
    bcm_assert( is_string( $sha ) && preg_match( '/\A[a-f0-9]{64}\z/', $sha ) && hash_equals( $sha, hash( 'sha256', $bytes ) ), 'Input hash mismatch' );
    return json_decode( $bytes, true, 64, JSON_THROW_ON_ERROR );
}
function bcm_raw( $kind, $id, $key, $lock = false ) {
    global $wpdb;
    bcm_assert( in_array( $kind, array( 'post', 'term' ), true ), 'Invalid metadata kind' );
    $table = 'post' === $kind ? $wpdb->postmeta : $wpdb->termmeta;
    $column = 'post' === $kind ? 'post_id' : 'term_id';
    $rows = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM $table WHERE $column=%d AND meta_key=%s ORDER BY meta_id" . ( $lock ? ' FOR UPDATE' : '' ), $id, $key ) );
    bcm_assert( '' === $wpdb->last_error, 'Metadata read failed' );
    return array_map( 'base64_encode', $rows );
}
function bcm_context() {
    $ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
    $rows = array();
    foreach ( $ids as $id ) {
        bactive_catalogue_forget( $id ); clean_post_cache( $id );
        $p = wc_get_product( $id ); $attributes = array(); foreach ( $p->get_attributes() as $key => $attribute ) { $attributes[ $key ] = $attribute->get_data(); } ksort( $attributes ); $variations = bactive_catalogue_variations( $p ); $files = array();
        foreach ( $variations as $v ) { if ( $v['image_id'] ) { $files[ $v['image_id'] ] = bactive_catalogue_attachment( $v['image_id'] )['sha256'] ?? null; } }
        ksort( $files );
        $rows[] = array( 'id' => (int) $id, 'attributes' => $attributes, 'colours' => bactive_catalogue_product_colours( $p ), 'variations' => $variations, 'files' => $files );
    }
    return array( 'registry' => bactive_catalog_visuals_registry(), 'products' => $rows );
}
function bcm_plan( $manifest, $manifest_sha ) {
    bcm_assert( 1 === ( $manifest['schema_version'] ?? null ) && is_array( $manifest['variations'] ?? null ) && is_array( $manifest['attachments'] ?? null ), 'Invalid reviewed manifest' );
    $site = bcm_target(); $context = bcm_context(); $rows = array(); $terms = array(); $references = array(); $attachment_hashes = array();
    foreach ( $manifest['attachments'] as $a ) { $attachment_hashes[ $a['attachment_id'] ] = $a['sha256']; }
    foreach ( $manifest['variations'] ?? array() as $v ) { $references[ $v['variation_id'] ] = $v; }
    foreach ( $context['products'] as $identity ) {
        $id = $identity['id']; $p = wc_get_product( $id );
        $before = bcm_raw( 'post', $id, '_bactive_colour_settings' ); $after = $before;
        $legacy = bactive_catalog_visuals_entry( $context['registry'], $id );
        if ( ! bactive_catalogue_held( $id ) && true === ( $legacy['reviewed'] ?? false ) ) {
            $settings = array( 'schema_version' => 1, 'colours' => array() );
            foreach ( $identity['colours'] as $key => $colour ) {
                $shade = $legacy['palette'][ 'attribute_' . $colour['taxonomy'] ][ $colour['slug'] ] ?? null;
                $hex = true === ( $shade['approved'] ?? false ) && $colour['term_id'] === ( $shade['term_id'] ?? null ) ? bactive_catalogue_hex( $shade['hex'] ?? '' ) : '';
                $terms[ $colour['term_id'] ][] = $hex; // Omission prevents cross-garment inheritance.
                $entry = array( 'mode' => $hex ? 'custom' : 'none', 'hex' => $hex, 'preview_image_id' => 0, 'review' => '' );
                $images = array(); $current_ids = array(); $reviewed_ids = array();
                foreach ( $identity['variations'] as $candidate ) { if ( 'publish' === $candidate['status'] ) { $current_ids[] = $candidate['id']; } }
                foreach ( $references as $vid => $ref ) { if ( ( $ref['product_id'] ?? null ) === $id ) { $reviewed_ids[] = $vid; } }
                sort( $current_ids ); sort( $reviewed_ids ); $valid = $current_ids === $reviewed_ids;
                foreach ( $identity['variations'] as $v ) {
                    if ( 'publish' !== $v['status'] ) { continue; }
                    $slug = $v['attributes'][ $colour['taxonomy'] ] ?? '';
                    if ( '' === $slug ) { $valid = false; }
                    if ( $slug !== $colour['slug'] ) { continue; }
                    $ref = $references[ $v['id'] ] ?? array(); $image = $v['image_id'];
                    if ( ! $image || ( $ref['product_id'] ?? null ) !== $id || ( $ref['term_id'] ?? null ) !== $colour['term_id']
                        || ( $ref['term_slug'] ?? null ) !== $slug || ( $ref['source_attachment_id'] ?? null ) !== $image
                        || ( $ref['reference_sha256'] ?? null ) !== ( $identity['files'][ $image ] ?? null )
                        || ( $attachment_hashes[ $image ] ?? null ) !== ( $ref['reference_sha256'] ?? null )
                        || sanitize_title( (string) ( $ref['size'] ?? '' ) ) !== ( $v['attributes']['pa_size'] ?? $v['attributes']['size'] ?? '' )
                        || empty( $ref['reference_sha256'] ) ) { $valid = false; }
                    $images[ $image ] = true;
                }
                if ( $valid && 1 === count( $images ) ) {
                    $image = (int) array_key_first( $images ); $review = bactive_catalogue_review_fingerprint( $p, $colour, $image );
                    if ( $review ) { $entry['preview_image_id'] = $image; $entry['review'] = $review; }
                }
                $settings['colours'][ $key ] = $entry;
            }
            // Any existing override, including malformed/duplicate rows, is preserved byte-for-byte.
            if ( ! $before ) { $after = array( base64_encode( maybe_serialize( $settings ) ) ); }
        }
        $rows[] = array( 'kind' => 'post', 'id' => $id, 'key' => '_bactive_colour_settings', 'before' => $before, 'after' => $after );
    }
    ksort( $terms );
    foreach ( $terms as $id => $shades ) {
        $before = bcm_raw( 'term', $id, '_bactive_colour_hex' ); $after = $before; $unique = array_unique( $shades );
        if ( ! $before && 1 === count( $unique ) && reset( $unique ) ) { $after = array( base64_encode( reset( $unique ) ) ); }
        $rows[] = array( 'kind' => 'term', 'id' => (int) $id, 'key' => '_bactive_colour_hex', 'before' => $before, 'after' => $after );
    }
    return array( 'schema_version' => 1, 'operation' => 'bactive-colour-metadata-migration', 'site' => $site, 'manifest_sha256' => $manifest_sha,
        'context' => $context, 'context_sha256' => hash( 'sha256', bcm_json( $context ) ), 'rows' => $rows, 'defaults_option_activation' => false );
}
/** Pure preflight used by both transaction driver and tests. */
function bcm_preflight( $rows, $read, $direction ) {
    bcm_assert( in_array( $direction, array( 'apply', 'rollback' ), true ), 'Invalid direction' );
    $todo = array(); $seen = array();
    foreach ( $rows as $row ) {
        bcm_assert( in_array( $row['kind'] ?? null, array( 'post', 'term' ), true ) && is_int( $row['id'] ?? null ) && $row['id'] > 0, 'Invalid row' );
        bcm_assert( $row['key'] === ( 'post' === $row['kind'] ? '_bactive_colour_settings' : '_bactive_colour_hex' ), 'Invalid metadata key' );
        $key = $row['kind'] . ':' . $row['id']; bcm_assert( ! isset( $seen[ $key ] ), 'Duplicate row' ); $seen[ $key ] = true;
        foreach ( array( 'before', 'after' ) as $state ) { bcm_assert( is_array( $row[ $state ] ), 'Invalid state' ); foreach ( $row[ $state ] as $v ) { bcm_assert( is_string( $v ) && false !== base64_decode( $v, true ), 'Invalid bytes' ); } }
        // This migration only inserts missing metadata; it never overwrites an existing override.
        bcm_assert( $row['before'] === $row['after'] || ( array() === $row['before'] && 1 === count( $row['after'] ) ), 'Unsafe replacement' );
        if ( 'post' === $row['kind'] && in_array( $row['id'], array( 56, 160, 211, 238, 148, 347 ), true ) ) { bcm_assert( $row['before'] === $row['after'], 'Held product mutation' ); }
        $from = $row[ 'apply' === $direction ? 'before' : 'after' ]; $to = $row[ 'apply' === $direction ? 'after' : 'before' ]; $current = $read( $row );
        bcm_assert( $current === $from || $current === $to, 'Later metadata writer detected' );
        if ( $current !== $to ) { $todo[] = array( 'row' => $row, 'from' => $from, 'to' => $to ); }
    }
    return $todo;
}
function bcm_execute( $plan, $direction ) {
    global $wpdb;
    bcm_assert( 1 === ( $plan['schema_version'] ?? null ) && 'bactive-colour-metadata-migration' === ( $plan['operation'] ?? null ) && false === ( $plan['defaults_option_activation'] ?? null ), 'Invalid plan' );
    bcm_assert( bcm_target() === $plan['site'], 'Target changed' );
    bcm_assert( hash_equals( $plan['context_sha256'], hash( 'sha256', bcm_json( bcm_context() ) ) ), 'Catalogue/registry/image identity changed' );
    foreach ( array( $wpdb->postmeta, $wpdb->termmeta ) as $table ) {
        $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
        bcm_assert( 'InnoDB' === $engine, 'Transactional tables required' );
    }
    bcm_assert( false !== $wpdb->query( 'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE' ) && false !== $wpdb->query( 'START TRANSACTION' ), 'Transaction failed' );
    try {
        $todo = bcm_preflight( $plan['rows'], function ( $r ) { return bcm_raw( $r['kind'], $r['id'], $r['key'], true ); }, $direction );
        foreach ( $todo as $item ) {
            $r = $item['row']; $table = 'post' === $r['kind'] ? $wpdb->postmeta : $wpdb->termmeta; $column = 'post' === $r['kind'] ? 'post_id' : 'term_id';
            bcm_assert( bcm_raw( $r['kind'], $r['id'], $r['key'], true ) === $item['from'], 'CAS precondition failed' );
            if ( $item['to'] ) {
                $n = $wpdb->insert( $table, array( $column => $r['id'], 'meta_key' => $r['key'], 'meta_value' => base64_decode( $item['to'][0], true ) ), array( '%d', '%s', '%s' ) );
            } else {
                $n = $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE $column=%d AND meta_key=%s AND BINARY meta_value=BINARY %s", $r['id'], $r['key'], base64_decode( $item['from'][0], true ) ) );
            }
            bcm_assert( 1 === $n && bcm_raw( $r['kind'], $r['id'], $r['key'], true ) === $item['to'], 'CAS write failed' );
        }
        bcm_assert( false !== $wpdb->query( 'COMMIT' ), 'Commit uncertain; inspect plan states before retry' );
    } catch ( Throwable $e ) { $wpdb->query( 'ROLLBACK' ); throw $e; }
    foreach ( $todo as $item ) { wp_cache_delete( $item['row']['id'], $item['row']['kind'] . '_meta' ); }
    return array( 'status' => 'PASS', 'direction' => $direction, 'changed_rows' => count( $todo ), 'defaults_option_activated' => false );
}
if ( ! defined( 'BACTIVE_MIGRATION_LIBRARY' ) ) {
    try {
        bcm_assert( defined( 'ABSPATH' ) && function_exists( 'bactive_catalogue_product_colours' ), 'Load WordPress and catalogue model first' );
        $cli = $args ?? array(); bcm_assert( 3 === count( $cli ), 'Expected dry-run|apply|rollback PATH SHA256' );
        $input = bcm_input( $cli[1], $cli[2] );
        $result = 'dry-run' === $cli[0] ? bcm_plan( $input, $cli[2] ) : bcm_execute( $input, $cli[0] );
        echo bcm_json( $result ) . "\n";
    } catch ( Throwable $e ) { fwrite( STDERR, 'Migration refused: ' . $e->getMessage() . "\n" ); exit( 1 ); }
}
