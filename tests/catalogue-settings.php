<?php
/** Boundary and cross-size mapping tests with temporary local image fixtures. */
define( 'ABSPATH', __DIR__ );
function add_action( ...$a ) {} function add_filter( ...$a ) {}
function wp_json_encode( $v ) { return json_encode( $v ); }
function is_wp_error( $v ) { return false; }
function get_option( $n, $d = array() ) { return $GLOBALS['options'][$n] ?? $d; }
function get_post_meta( $id, $key, $single ) { return ''; }
function get_term_meta( $id, $key, $single ) { return '#aabbcc'; }
function is_post_publicly_viewable( $id ) { return $GLOBALS['public'] ?? true; }
function post_password_required( $id ) { return $GLOBALS['password_required'] ?? false; }
function current_user_can( $cap, $id ) { return $GLOBALS['capabilities'][$cap] ?? false; }
function is_admin() { return $GLOBALS['admin'] ?? false; }
function doing_action( $action ) { return in_array( $action, $GLOBALS['actions'] ?? array(), true ); }
function wp_doing_ajax() { return $GLOBALS['ajax'] ?? false; }
class Catalogue_Test_Request {
    function __construct( public $method, public $route ) {}
    function get_method() { return $this->method; } function get_route() { return $this->route; }
}
function get_term( $id, $taxonomy ) { return (object) array( 'term_id' => $id, 'taxonomy' => $taxonomy, 'slug' => 'lavender', 'name' => 'Lavender' ); }
function wp_attachment_is_image( $id ) { return isset( $GLOBALS['files'][$id] ); }
function get_attached_file( $id ) { return $GLOBALS['files'][$id] ?? ''; }
function wp_get_upload_dir() { return array( 'basedir' => $GLOBALS['directory'], 'baseurl' => 'https://example.test/uploads' ); }
function wp_parse_url( ...$args ) { return parse_url( ...$args ); }
function wp_get_environment_type() { return 'production'; }
function wp_normalize_path( $p ) { return $p; }
function wp_getimagesize( $p ) { return @getimagesize( $p ); }
function wp_get_attachment_url( $id ) { return $GLOBALS['urls'][$id] ?? 'https://example.test/uploads/' . basename( $GLOBALS['files'][$id] ); }
function wc_get_product( $id ) { return $GLOBALS['variations'][$id] ?? false; }
class WC_Product_Attribute {
    function is_taxonomy() { return true; } function get_name() { return 'pa_colour'; } function get_options() { return array( 7 ); }
}
class WC_Product {
    public $id = 900; public $meta = array();
    function get_id() { return $this->id; } function get_name() { return 'Test garment'; }
    function get_attributes() { return array( new WC_Product_Attribute() ); }
    function get_meta( $key, $single = true ) { return $this->meta[$key] ?? ''; }
    function is_type( $type ) { return 'variable' === $type; }
    function get_children() { return array( 901, 902 ); }
}
class WC_Product_Variation extends WC_Product {
    public function __construct( public $image, public $size, public $colour = 'lavender' ) {}
    function get_parent_id() { return 900; } function get_status() { return 'publish'; }
    function get_attributes() { return array( 'pa_size' => $this->size, 'pa_colour' => $this->colour ); }
    function get_image_id( $context ) { if ( 'edit' !== $context ) { throw new Exception( 'Explicit images required' ); } return $this->image; }
}
require __DIR__ . '/../wordpress/wp-content/themes/blocksy-child/inc/catalogue-settings.php';
require __DIR__ . '/../wordpress/wp-content/themes/blocksy-child/inc/catalog-visuals.php';
function check( $v, $reason ) { if ( ! $v ) { throw new RuntimeException( $reason ); } }
$directory = sys_get_temp_dir() . '/bactive-model-' . bin2hex( random_bytes( 8 ) ); mkdir( $directory, 0700 ); $directory = realpath( $directory );
$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN0cAAAAASUVORK5CYII=' );
$files = array( 1 => $directory . '/one.png', 2 => $directory . '/two.png' );
file_put_contents( $files[1], $png ); file_put_contents( $files[2], $png . 'different-model-fixture' );
try {
    check( ! bactive_catalogue_feature( 'layout' ), 'Absent defaults must preserve staged deployment' );
    $options['bactive_catalogue_defaults'] = array( 'schema_version'=>1, 'version'=>'test', 'enabled'=>true, 'layout'=>true, 'selectors'=>'true' );
    check( bactive_catalogue_feature( 'layout' ) && ! bactive_catalogue_feature( 'selectors' ), 'Strict switches' );
    $product = new WC_Product(); $row = bactive_catalogue_product_colours( $product )['pa_colour:7'];
    check( '#aabbcc' === bactive_catalogue_effective_hex( $product, $row ), 'New product inherits colour term' );
    $options['bactive_catalog_visuals_release'] = array( 'schema_version'=>1, 'version'=>'legacy', 'products'=>array(900=>array('reviewed'=>true,'palette'=>array())) );
    check( '' === bactive_catalogue_effective_hex( $product, $row ), 'Unmigrated legacy omissions remain name only' );
    $product->meta['_bactive_colour_settings'] = array( 'schema_version'=>1, 'colours'=>array() );
    check( '#aabbcc' === bactive_catalogue_effective_hex( $product, $row ), 'New term on migrated product inherits its global shade despite old palette omission' );
    $product->meta['_bactive_colour_settings'] = array( 'schema_version'=>1, 'colours'=>array( 'pa_colour:7'=>array( 'mode'=>'custom','hex'=>'#112233','preview_image_id'=>1,'review'=>'' ) ) );
    check( '#112233' === bactive_catalogue_effective_hex( $product, $row ), 'Product shade overrides global default' );
    $variations = array( 901=>new WC_Product_Variation(1,'s'), 902=>new WC_Product_Variation(2,'l') );
    $review = bactive_catalogue_review_fingerprint( $product, $row, 1 );
    check( 64 === strlen( $review ), 'Different models for sizes are valid' );
    $product->meta['_bactive_colour_settings']['colours']['pa_colour:7']['review'] = $review;
    check( 1 === bactive_catalogue_preview( $product, $row )['id'], 'Representative image chosen explicitly' );
    $options['bactive_catalogue_defaults']['selectors'] = true;
    $variations[900] = $product;
    $config = bactive_catalog_visuals_config( null, 900 );
    check( 1 === $config['schemaVersion'] && 900 === $config['productId'] && 'test' === $config['version'], 'New variable product receives the gallery bridge config without a release allowlist' );
    check( isset( ( (array) $config['previews'] )['attribute_pa_colour']['lavender'] ), 'Automatic config preserves the approved representative alongside the native gallery' );
    check( 2 === bactive_catalogue_variation_image( 2, $variations[902] ), 'An explicit size photo always wins' );
    $variations[902]->image = 0; bactive_catalogue_forget(900);
    $product->meta['_bactive_colour_settings']['colours']['pa_colour:7']['review'] = bactive_catalogue_review_fingerprint( $product, $row, 1 );
    check( 1 === bactive_catalogue_variation_image( 2, $variations[902] ), 'View-only missing photo uses reviewed representative' );
    $admin = true;
    check( 2 === bactive_catalogue_variation_image( 2, $variations[902] ), 'Administrative getters preserve native image' );
    $actions = array( 'wp_ajax_nopriv_blocksy_get_product_view_for_variation' );
    check( 1 === bactive_catalogue_variation_image( 2, $variations[902] ), 'Public theme display AJAX keeps reviewed fallback' );
    $admin = false; $actions = array();
    bactive_catalogue_rest_before( null, null, new Catalogue_Test_Request( 'PUT', '/wc/v3/products/900/variations/902' ) );
    check( 2 === bactive_catalogue_variation_image( 2, $variations[902] ), 'REST gallery update cannot inherit display photo' );
    bactive_catalogue_rest_before( null, null, new Catalogue_Test_Request( 'GET', '/wc/v3/products/900' ) );
    check( 2 === bactive_catalogue_variation_image( 2, $variations[902] ), 'Nested reads cannot reopen a mutation context' );
    bactive_catalogue_rest_after( null ); bactive_catalogue_rest_after( null );
    check( 1 === bactive_catalogue_variation_image( 2, $variations[902] ), 'Read-only REST display retains reviewed fallback' );
    bactive_catalogue_rest_before( null, null, new Catalogue_Test_Request( 'POST', '/wc/store/v1/cart/add-item' ) );
    check( 1 === bactive_catalogue_variation_image( 2, $variations[902] ), 'Store API cart POST keeps the same display photo as GET' );
    bactive_catalogue_rest_after( null );
    bactive_catalogue_rest_before( null, null, new Catalogue_Test_Request( 'POST', '/wc/v3/products/batch' ) );
    check( 2 === bactive_catalogue_variation_image( 2, $variations[902] ), 'Administrative product batches preserve raw galleries' );
    bactive_catalogue_rest_after( null );
    bactive_catalogue_rest_before( null, null, new Catalogue_Test_Request( 'PUT', '/wc/v3/PRODUCTS/900/variations/902' ) );
    check( 2 === bactive_catalogue_variation_image( 2, $variations[902] ), 'Case-insensitive native REST routes remain protected' );
    bactive_catalogue_rest_after( null );
    $variations[902]->image = 2; bactive_catalogue_forget(900);
    $product->meta['_bactive_colour_settings']['colours']['pa_colour:7']['review'] = $review;
    $public = false;
    check( null === bactive_catalogue_preview( $product, $row ), 'Anonymous private preview refused' );
    $capabilities['read_post'] = true;
    check( 1 === bactive_catalogue_preview( $product, $row )['id'], 'Authorized private review preview remains usable' );
    $public = true; $capabilities = array(); $password_required = true;
    check( null === bactive_catalogue_preview( $product, $row ), 'Password-protected public preview refused' );
    $capabilities['read_post'] = true;
    check( null === bactive_catalogue_preview( $product, $row ), 'Read capability alone does not bypass a product password' );
    $capabilities['edit_post'] = true;
    check( 1 === bactive_catalogue_preview( $product, $row )['id'], 'Authorized editor can review protected product' );
    $password_required = false; $capabilities = array();
    $stamp = bactive_catalogue_product_stamp( $product );
    $editor_stamp = bactive_catalogue_editor_stamp( $product );
    $variations[902]->image = 1; bactive_catalogue_forget(900);
    check( null === bactive_catalogue_preview( $product, $row ), 'Changing one size photo invalidates review' );
    check( $stamp !== bactive_catalogue_product_stamp( $product ), 'Stale editor sees changed variation photos' );
    check( $editor_stamp === bactive_catalogue_editor_stamp( $product ), 'Woo variation AJAX does not create an owned-settings conflict' );
    $product->meta['_bactive_layout_mode'] = 'native';
    check( $editor_stamp !== bactive_catalogue_editor_stamp( $product ), 'Competing layout edit changes the owned-settings stamp' );
    unset($product->meta['_bactive_layout_mode']);
    $variations[902]->image = 2; bactive_catalogue_forget(900);
    check( $review === bactive_catalogue_review_fingerprint( $product, $row, 1 ), 'Restored mappings recover original identity' );
    file_put_contents( $files[2], $png . 'replacement' ); bactive_catalogue_forget(900);
    check( $stamp !== bactive_catalogue_product_stamp( $product ), 'Replaced attachment bytes invalidate editor stamp' );
    check( null === bactive_catalogue_preview( $product, $row ), 'Replaced attachment invalidates preview approval' );
    $variations[902]->colour = ''; bactive_catalogue_forget(900);
    check( '' === bactive_catalogue_review_fingerprint( $product, $row, 1 ), 'Wildcard colour is not a reviewed mapping' );
    $product->meta['_bactive_colour_settings']['colours']['pa_colour:7']['mode']='none';
    check( '' === bactive_catalogue_effective_hex( $product, $row ), 'Name only suppresses global default' );
    $product->id=56;
    check( '' === bactive_catalogue_review_fingerprint( $product, $row, 1 ) && '' === bactive_catalogue_effective_hex( $product, $row ), 'Held products cannot release through metadata' );
    $product->id=238; $product->meta['_bactive_colour_settings']['colours']['pa_colour:7']['mode']='inherit';
    check(!bactive_catalogue_held(238) && '#aabbcc'===bactive_catalogue_effective_hex($product,$row), 'Repaired Sculpt uses merchant global shades without the historical hold');
    foreach(array(56,160,211,148,347) as $held_id) { check(bactive_catalogue_held($held_id), 'Other protected products remain held'); }
    foreach ( array('http://example.test/uploads/one.png','https://example.test:8443/uploads/one.png','https://example.test/other/one.png','https://example.test/uploads/one.png?remote=1') as $bad ) {
        $urls[1]=$bad; check( null === bactive_catalogue_attachment(1), 'Reject unbound attachment URL' );
    }
    $urls=array(); $files[3]=$directory.'/link.png'; symlink($files[1],$files[3]);
    check( null === bactive_catalogue_attachment(3), 'Reject symlink attachment' );
    echo "Catalogue settings, inheritance, separate size models, review invalidation and URL boundaries PASS\n";
} finally { foreach( $files as $path ) { if(is_file($path)||is_link($path))unlink($path); } rmdir($directory); }
