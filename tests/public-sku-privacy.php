<?php
/** Standalone output-boundary regressions using WordPress's real hook/parser classes. */
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', __DIR__ . '/../wordpress/' );
define( 'WPINC', 'wp-includes' );
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/class-wp-http-response.php';
require ABSPATH . WPINC . '/class-wp-list-util.php';
require ABSPATH . WPINC . '/functions.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-attribute-token.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-span.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-text-replacement.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-decoder.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-tag-processor.php';
$admin = false; $ajax = false; $cron = false;
function is_admin() { return $GLOBALS['admin']; }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function wp_doing_cron() { return $GLOBALS['cron']; }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain = '' ) { return $text; }
function check_sku( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    ++$GLOBALS['checks'];
}
class SkuTestRequest {
    public function __construct( private $route, private $params = array() ) {}
    public function get_route() { return $this->route; }
    public function has_param( $key ) { return array_key_exists( $key, $this->params ); }
    public function get_param( $key ) { return $this->params[$key] ?? null; }
}
$checks = 0;
require __DIR__ . '/../wp-content/themes/blocksy-child/inc/public-sku-privacy.php';
check_sku( apply_filters( 'wc_product_sku_enabled', true ), 'Management schema registration lost SKU support' );
do_action( 'wp' );
check_sku( ! apply_filters( 'wc_product_sku_enabled', true ), 'Public product SKU flag' );
$admin = true;
check_sku( apply_filters( 'wc_product_sku_enabled', true ), 'Backend product editor changed' );
$ajax = true;
$_REQUEST['action'] = 'woocommerce_load_variations';
check_sku( apply_filters( 'wc_product_sku_enabled', true ), 'Authorized admin variation editor changed' );
add_action( 'wp_ajax_nopriv_public_product_search', '__return_null' );
$_REQUEST['action'] = 'public_product_search';
check_sku( ! apply_filters( 'wc_product_sku_enabled', true ), 'Manager viewing public AJAX bypassed privacy' );
$ajax = false;
$manage = new SkuTestRequest( '/wc/v3/products' );
$store = new SkuTestRequest( '/wc/store/v1/products' );
apply_filters( 'rest_request_before_callbacks', null, array(), $manage );
check_sku( apply_filters( 'wc_product_sku_enabled', true ), 'Backend REST changed' );
apply_filters( 'rest_request_before_callbacks', null, array(), $store );
check_sku( ! apply_filters( 'wc_product_sku_enabled', true ), 'Privileged Store API bypassed privacy' );
$payload = array( 'id'=>12, 'sku'=>'SECRET', 'variations'=>array( array('id'=>13, 'SKU'=>'CHILD') ),
    'meta_data'=>array( array('key'=>'_sku','value'=>'SECRET'), (object) array('key'=>'sku','value'=>'CHILD'), array('key'=>'colour','value'=>'Sage') ) );
$response = new WP_HTTP_Response( $payload, 206, array('X-Test'=>'preserved') );
$response = apply_filters( 'rest_request_after_callbacks', $response, array(), $store );
check_sku( ! str_contains( json_encode( $response->get_data() ), 'SECRET' ) && ! str_contains( json_encode( $response->get_data() ), 'CHILD' ), 'Nested API metadata leak' );
check_sku( $response->get_data()['meta_data'] === array( array('key'=>'colour','value'=>'Sage') ), 'Metadata shape/content changed' );
check_sku( $response->get_status() === 206 && $response->get_headers()['X-Test'] === 'preserved', 'Response status/headers changed' );
check_sku( apply_filters( 'wc_product_sku_enabled', true ), 'Nested management context not restored' );
apply_filters( 'rest_request_after_callbacks', new WP_HTTP_Response( array() ), array(), $manage );
check_sku( empty( $GLOBALS['bactive_sku_request_routes'] ), 'Request stack leaked' );
check_sku( apply_filters( 'rest_post_dispatch', new WP_HTTP_Response($payload), null, $manage )->get_data() === $payload, 'Management response scrubbed' );
$denied = apply_filters( 'rest_pre_dispatch', null, null, new SkuTestRequest('/wc/store/v1/products', array('sku'=>'SECRET')) );
check_sku( $denied instanceof WP_Error && $denied->get_error_data()['status'] === 400 && ! str_contains($denied->get_error_message(),'SECRET'), 'SKU inference query allowed or reflected' );
check_sku( null === apply_filters('rest_pre_dispatch',null,null,new SkuTestRequest('/wc/v3/products',array('sku'=>'SECRET'))), 'Management SKU query blocked' );
foreach (array('/WC/STORE/V1/products','/wC/sToRe/v1/products') as $route) {
    $request=new SkuTestRequest($route,array('sku'=>'SECRET'));
    check_sku(apply_filters('rest_pre_dispatch',null,null,$request) instanceof WP_Error,'Mixed-case query bypass');
    check_sku(!isset(apply_filters('rest_post_dispatch',new WP_HTTP_Response($payload),null,$request)->get_data()['sku']),'Mixed-case response bypass');
    apply_filters('woocommerce_hydration_dispatch_request',null,$request,'',array());
    check_sku(!apply_filters('wc_product_sku_enabled',true),'Mixed-case hydrated search bypass');
    apply_filters('woocommerce_hydration_request_after_callbacks',new WP_HTTP_Response($payload),array(),$request);
}
apply_filters( 'woocommerce_hydration_dispatch_request', null, $store, '', array() );
check_sku( ! apply_filters('wc_product_sku_enabled',true), 'Hydrated SKU search enabled' );
$hydrated = apply_filters( 'woocommerce_hydration_request_after_callbacks', new WP_HTTP_Response($payload), array(), $store );
check_sku( ! isset($hydrated->get_data()['sku']) && empty($GLOBALS['bactive_sku_request_routes']), 'Hydration leak/context leak' );
$batch = array('responses'=>array(array('body'=>$payload)));
check_sku( ! str_contains(json_encode(apply_filters('rest_post_dispatch',new WP_HTTP_Response($batch),null,new SkuTestRequest('/wc/store/v1/batch'))->get_data()),'SECRET'), 'Batch response leak' );
foreach ( array('woocommerce_available_variation','woocommerce_structured_data_product','woocommerce_structured_data_order') as $hook ) {
    $out=apply_filters($hook,$payload);
    check_sku(!isset($out['sku']) && $out['id']===12, $hook.' SKU or fallback leak');
}
check_sku( apply_filters('render_block_woocommerce/product-sku','<div>SECRET</div>') === '', 'SKU block leak' );
$html='<a class="remove" href="/?remove_item=abc" data-product_sku="SECRET" data-product_id="12" aria-label="Remove dress">×</a>';
$out=apply_filters('woocommerce_cart_item_remove_link',$html);
check_sku( !str_contains($out,'sku') && str_contains($out,'data-product_id="12"') && str_contains($out,'aria-label="Remove dress"'), 'Cart attribute stripping damaged markup' );
$args=apply_filters('woocommerce_loop_add_to_cart_args',array('attributes'=>array('data-product_sku'=>'SECRET','data-product_id'=>12)));
check_sku($args['attributes']===array('data-product_id'=>12),'Loop attributes');
$cron=true;
foreach ( array('woocommerce_email_order_items_args','woocommerce_email_fulfillment_items_args') as $hook ) {
    foreach (array(false,true) as $plain) {
        $args=array('sent_to_admin'=>false,'show_sku'=>true,'plain_text'=>$plain,'items'=>array(12));
        $out=apply_filters($hook,$args);
        check_sku(!$out['show_sku'] && $out['items']===$args['items'] && $out['plain_text']===$plain,'Customer email in privileged/background context');
    }
    $args=array('sent_to_admin'=>true,'show_sku'=>true);
    check_sku(apply_filters($hook,$args)===$args,'Internal email changed');
}
class Tt4b_Pixel_Class { public function print_script() {} }
$pixel=new Tt4b_Pixel_Class();
add_action('wp_head',array($pixel,'print_script'));
add_action('wp_head','__return_null',20);
bactive_sku_disable_unsafe_pixel();
check_sku(false===has_action('wp_head',array($pixel,'print_script')) && 20===has_action('wp_head','__return_null'),'Pixel guard missed emitter or removed unrelated callback');
check_sku(false===has_filter('woocommerce_product_get_sku') && false===has_filter('woocommerce_product_variation_get_sku'),'Global SKU getters must remain untouched');
echo "Public SKU privacy: {$checks} checks passed\n";
