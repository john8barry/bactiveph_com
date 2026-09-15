<?php
/** Run: php tests/catalogue-editor.php. No WordPress, network or database writes. */
define( 'ABSPATH', __DIR__ );
class WP_Error { public function __construct( public $code, public $message ) {} public function get_error_message() { return $this->message; } }
function __( $s, $domain = '' ) { return $s; }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function bactive_catalogue_product_stamp( $p ) { return str_repeat( 'a', 64 ); }
function bactive_catalogue_product_colours( $p ) { return array( 'pa_colour:83' => array( 'key' => 'pa_colour:83', 'taxonomy' => 'pa_colour', 'term_id' => 83, 'slug' => 'lavender', 'name' => 'Lavender' ) ); }
function bactive_catalogue_product_settings( $p ) { return $GLOBALS['previous']; }
function bactive_catalogue_attachment( $id ) { return 750 === $id ? array( 'id' => 750 ) : null; }
function bactive_catalogue_review_fingerprint( ...$args ) { return str_repeat( 'b', 64 ); }
function bactive_catalogue_held( $id ) { return $GLOBALS['held']; }
function current_user_can( ...$args ) { return $GLOBALS['can']; }
function wp_is_post_revision( $id ) { return false; }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce && in_array( $action, array( 'bactive_catalogue_185', 'bactive_colour_term_pa_colour_83', 'bactive_colour_term_pa_colour_0' ), true ); }
function get_taxonomy( $taxonomy ) { return (object) array( 'cap' => (object) array( 'edit_terms' => 'manage_product_terms' ) ); }
function current_filter() { return 'edited_term'; }
function bactive_catalogue_invalidate_colour_term( $id, $taxonomy ) { $GLOBALS['term_invalidated'] = array( $id, $taxonomy ); }
function update_term_meta( $id, $key, $value ) { $GLOBALS['term_writes'][] = array( $id, $key, $value ); }
function sanitize_text_field( $s ) { return $s; }
function wp_unslash( $x ) { return $x; }
function wc_get_product( $id ) { return $GLOBALS['product']; }
function bactive_catalogue_forget( $id ) {}
function bactive_catalogue_invalidate( $id ) { $GLOBALS['invalidated']++; }
class WC_Admin_Meta_Boxes { public static function add_error( $message ) { $GLOBALS['errors'][] = $message; } }
class Product { public $writes = array(); public function get_id() { return 185; } public function is_type( $x ) { return 'variable' === $x; } public function update_meta_data( $key, $value ) { $this->writes[$key] = $value; } }
require __DIR__ . '/../wordpress/wp-content/themes/blocksy-child/inc/catalogue-editor.php';
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$product = new Product(); $held = false; $can = true; $invalidated = 0; $errors = array();
$previous = array( 'schema_version' => 1, 'colours' => array() );
$input = array( 'stamp' => str_repeat( 'a', 64 ), 'layout' => 'auto', 'colours' => array( 'pa_colour:83' => array( 'mode' => 'custom', 'hex' => '#AaBBcc', 'preview_image_id' => '750' ) ) );
$result = bactive_catalogue_editor_validate( $product, $input );
check( '' === $result['settings']['colours']['pa_colour:83']['review'], 'Normal save must not approve' );
$confirmed = $input; $confirmed['colours']['pa_colour:83']['confirm'] = '1';
$result = bactive_catalogue_editor_validate( $product, $confirmed );
check( str_repeat( 'b', 64 ) === $result['settings']['colours']['pa_colour:83']['review'], 'Explicit review must bind server fingerprint' );
$previous = $result['settings'];
check( str_repeat( 'b', 64 ) === bactive_catalogue_editor_validate( $product, $input )['settings']['colours']['pa_colour:83']['review'], 'Unchanged valid review preserved' );
$changed = $input; $changed['colours']['pa_colour:83']['hex'] = '#000000';
check( '' === bactive_catalogue_editor_validate( $product, $changed )['settings']['colours']['pa_colour:83']['review'], 'Shade change invalidates review' );
$previous['colours']['pa_colour:83']['review'] = str_repeat( 'c', 64 );
check( '' === bactive_catalogue_editor_validate( $product, $input )['settings']['colours']['pa_colour:83']['review'], 'Mapping change invalidates review' );
$held = true;
check( '' === bactive_catalogue_editor_validate( $product, $confirmed )['settings']['colours']['pa_colour:83']['review'], 'Held product cannot approve' ); $held = false;
foreach ( array( 'mode' => 'bad', 'hex' => '<script>', 'preview_image_id' => '751' ) as $field => $value ) { $bad = $input; $bad['colours']['pa_colour:83'][$field] = $value; check( is_wp_error( bactive_catalogue_editor_validate( $product, $bad ) ), 'Reject ' . $field ); }
foreach ( array( array(), false, '-1', '1.2', '9999999999999999999999999' ) as $id ) { $bad = $input; $bad['colours']['pa_colour:83']['preview_image_id'] = $id; check( is_wp_error( bactive_catalogue_editor_validate( $product, $bad ) ), 'Reject malformed attachment' ); }
$bad = $input; $bad['colours']['pa_colour:999'] = $bad['colours']['pa_colour:83']; check( is_wp_error( bactive_catalogue_editor_validate( $product, $bad ) ), 'Reject foreign term' );
$bad = $input; $bad['colours'] = array(); check( is_wp_error( bactive_catalogue_editor_validate( $product, $bad ) ), 'Reject truncated form' );
$_POST = array( 'bactive_catalogue_nonce' => 'valid', 'bactive_catalogue' => $input );
$can = false; bactive_catalogue_editor_save( $product ); check( ! $product->writes, 'Permission failure must not write' ); $can = true;
$_POST['bactive_catalogue_nonce'] = 'invalid'; bactive_catalogue_editor_save( $product ); check( ! $product->writes, 'Nonce failure must not write' );
$_POST['bactive_catalogue_nonce'] = 'valid'; $_POST['bactive_catalogue']['stamp'] = str_repeat( 'c', 64 ); bactive_catalogue_editor_save( $product ); check( ! $product->writes && count( $errors ) === 1, 'Stale editor rejects settings' );
$_POST['bactive_catalogue'] = $input; bactive_catalogue_editor_save( $product );
check( array_keys( $product->writes ) === array( '_bactive_colour_settings', '_bactive_layout_mode' ), 'Only owned metadata writes' );
check( 1 === $invalidated, 'Invalidate on successful save only' );
$_POST = array( 'bactive_colour_term_nonce' => 'valid', 'bactive_colour_hex' => '#AbCdEf' ); $term_writes = array();
$can = false; bactive_catalogue_term_save( 83, 83, 'pa_colour' ); check( ! $term_writes, 'Term permissions required' ); $can = true;
$_POST['bactive_colour_term_nonce'] = 'invalid'; bactive_catalogue_term_save( 83, 83, 'pa_colour' ); check( ! $term_writes, 'Term nonce required' );
$_POST['bactive_colour_term_nonce'] = 'valid'; bactive_catalogue_term_save( 83, 83, 'category' ); check( ! $term_writes, 'Other taxonomies untouched' );
$_POST['bactive_colour_hex'] = array(); bactive_catalogue_term_save( 83, 83, 'pa_colour' ); check( ! $term_writes, 'Malformed term hex rejected' );
$_POST['bactive_colour_hex'] = '#AbCdEf'; bactive_catalogue_term_save( 83, 83, 'pa_colour' ); check( $term_writes === array( array( 83, '_bactive_colour_hex', '#abcdef' ) ), 'Scoped global shade write' );
check( $term_invalidated === array( 83, 'pa_colour' ), 'Invalidate products inheriting global colour' );
echo "Catalogue editor auth, stale form, validation, review and scoped-write checks PASS\n";
