<?php
/** Pure metadata planner/CAS preflight regressions; no WordPress or database writes. */
define( 'BACTIVE_MIGRATION_LIBRARY', true );
require dirname( __DIR__ ) . '/tools/catalogue_settings_migration.php';
function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function rejects( $callback, $message ) { try { $callback(); } catch ( RuntimeException $e ) { return; } throw new RuntimeException( $message ); }
$row = array( 'kind' => 'post', 'id' => 117, 'key' => '_bactive_colour_settings', 'before' => array(), 'after' => array( base64_encode( 'reviewed-bytes' ) ) );
$before = fn( $r ) => $r['before']; $after = fn( $r ) => $r['after'];
check( 1 === count( bcm_preflight( array( $row ), $before, 'apply' ) ), 'Apply missing row' );
check( array() === bcm_preflight( array( $row ), $after, 'apply' ), 'Apply idempotence' );
check( 1 === count( bcm_preflight( array( $row ), $after, 'rollback' ) ), 'Rollback expected bytes' );
check( array() === bcm_preflight( array( $row ), $before, 'rollback' ), 'Rollback idempotence' );
$existing = $row; $existing['before'] = $existing['after'] = array( base64_encode( 'arbitrary-existing-override' ), base64_encode( 'duplicate-existing-row' ) );
check( array() === bcm_preflight( array( $existing ), $before, 'apply' ), 'Existing duplicates must remain unchanged' );
rejects( fn() => bcm_preflight( array( $row ), fn() => array( base64_encode( 'later-writer' ) ), 'apply' ), 'Later writer allowed' );
rejects( fn() => bcm_preflight( array( $row ), fn() => array( base64_encode( 'later-writer' ) ), 'rollback' ), 'Rollback later writer allowed' );
$unsafe = $row; $unsafe['before'] = array( base64_encode( 'old' ) );
rejects( fn() => bcm_preflight( array( $unsafe ), $before, 'apply' ), 'Existing override replacement allowed' );
$held = $row; $held['id'] = 56;
rejects( fn() => bcm_preflight( array( $held ), $before, 'apply' ), 'Held mutation allowed' );
$wrong = $row; $wrong['key'] = '_stock';
rejects( fn() => bcm_preflight( array( $wrong ), $before, 'apply' ), 'Commerce write allowed' );
rejects( fn() => bcm_preflight( array( $row, $row ), $before, 'apply' ), 'Duplicate rows allowed' );
$bad = $row; $bad['after'] = array( '**not-base64**' );
rejects( fn() => bcm_preflight( array( $bad ), $before, 'apply' ), 'Malformed bytes allowed' );
$read_ids = array(); $second = $row; $second['id'] = 154;
rejects( function () use ( $row, $second, &$read_ids ) { bcm_preflight( array( $row, $second ), function ( $r ) use ( &$read_ids ) { $read_ids[] = $r['id']; return 154 === $r['id'] ? array( base64_encode( 'later' ) ) : $r['before']; }, 'apply' ); }, 'All-row preflight allowed later conflict' );
check( array( 117, 154 ) === $read_ids, 'Every row must be preflighted before caller can mutate' );
echo "Catalogue migration: preflight/no-op/idempotence/conflict/held/commerce tests PASS\n";
