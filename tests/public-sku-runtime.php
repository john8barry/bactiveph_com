<?php
/** Private WP-CLI runtime verification. No saved products/orders or sent mail. */
if (!defined('WP_CLI') || !WP_CLI) {exit(1);}
if (!function_exists('bactive_sku_public_response')) {throw new RuntimeException('Candidate module must be loaded');}
$GLOBALS['sku_runtime_checks']=0;
function sku_runtime_check($ok,$label) {
    if(!$ok) {throw new RuntimeException($label);}
    ++$GLOBALS['sku_runtime_checks'];
}
function sku_runtime_has_key($value) {
    if(!is_array($value)) {return false;}
    foreach($value as $k=>$v) {
        if(is_string($k)&&in_array(strtolower($k),array('sku','_sku','product_sku','variation_sku'),true)) {return true;}
        if(sku_runtime_has_key($v)) {return true;}
    }
    return false;
}
global $wpdb;
$snapshot=function()use($wpdb){return hash('sha256',serialize(array(
    $wpdb->get_results("SELECT post_id,meta_value FROM {$wpdb->postmeta} WHERE meta_key='_sku' ORDER BY post_id",ARRAY_A),
    $wpdb->get_results("SELECT product_id,sku FROM {$wpdb->prefix}wc_product_meta_lookup ORDER BY product_id",ARRAY_A))));};
$before=$snapshot();$original_user=get_current_user_id();
$products=wc_get_products(array('limit'=>-1,'status'=>'publish'));
$sample=null;$variation_count=0;
$structured=new WC_Structured_Data();
foreach($products as $product) {
    if(!$sample && $product->get_sku()) {$sample=$product;}
    $structured->generate_product_data($product);
    if($product->is_type('variable')) {
        foreach($product->get_available_variations() as $variation) {
            sku_runtime_check(!isset($variation['sku'])&&isset($variation['variation_id']),'Variation payload SKU/identity failure');
            ++$variation_count;
        }
    }
}
sku_runtime_check(!sku_runtime_has_key($structured->get_data()),'Product structured data fallback leak');
sku_runtime_check((bool)$sample,'Missing representative SKU product');
$stored_sku=$sample->get_sku();
foreach(array(0,(int)get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'))[0]) as $user) {
    wp_set_current_user($user);
    foreach(array('/wc/store/v1/products','/WC/STORE/V1/products') as $route) {
        $request=new WP_REST_Request('GET',$route);$request->set_param('per_page',100);
        $response=rest_do_request($request);
        sku_runtime_check($response->get_status()===200&&!sku_runtime_has_key($response->get_data()),'Store API privacy failure');
        $request->set_param('sku',$stored_sku);$response=rest_do_request($request);
        sku_runtime_check($response->get_status()===400,'Public SKU lookup bypass');
    }
    $hydration=Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\Domain\Services\Hydration::class);
    $data=$hydration->get_rest_api_response_data('/wc/store/v1/products?per_page=100');
    sku_runtime_check(!empty($data['body'])&&!sku_runtime_has_key($data['body']),'Hydrated product payload failure');
}
// Current privileged request must retain its internal SKU filter and response.
$request=new WP_REST_Request('GET','/wc/v3/products');
$request->set_param('search',$stored_sku);$request->set_param('search_fields',array('sku'));
$response=rest_do_request($request);$data=$response->get_data();
sku_runtime_check($response->get_status()===200 && !empty($data) && $data[0]['sku']===$stored_sku,'Management SKU search/response regression');
sku_runtime_check($sample->get_sku()===$stored_sku && wc_get_product_id_by_sku($stored_sku)===$sample->get_id(),'Internal getter/lookup changed');

// Unsaved synthetic order exercises the installed HTML/plain email templates.
$order=new WC_Order();$order->set_date_created(time());$item=new WC_Order_Item_Product();$item->set_product($sample);$item->set_quantity(1);$item->set_subtotal(100);$item->set_total(100);$order->add_item($item);
$fulfillment=new class {public function get_items(){return array(array('item_id'=>0,'qty'=>1));}};
foreach(array(false,true) as $plain) {
    $html=wc_get_email_order_items($order,array('show_sku'=>true,'sent_to_admin'=>false,'plain_text'=>$plain));
    sku_runtime_check(!str_contains($html,$stored_sku),'Customer email leaked SKU');
    $internal=wc_get_email_order_items($order,array('show_sku'=>true,'sent_to_admin'=>true,'plain_text'=>$plain));
    sku_runtime_check(str_contains($internal,$stored_sku),'Internal email SKU missing');
    $html=wc_get_email_fulfillment_items($order,$fulfillment,array('show_sku'=>true,'sent_to_admin'=>false,'plain_text'=>$plain));
    sku_runtime_check($html!==''&&!str_contains($html,$stored_sku),'Customer fulfillment email leaked SKU');
}
$structured->generate_order_data($order,false,false);
sku_runtime_check(!sku_runtime_has_key($structured->get_data()),'Order structured data leak');
sku_runtime_check($order->get_id()===0,'Synthetic order unexpectedly persisted');
wp_set_current_user($original_user);
sku_runtime_check($snapshot()===$before,'Stored SKU or lookup changed');
sku_runtime_check(empty($GLOBALS['bactive_sku_request_routes']),'REST context leaked');
echo wp_json_encode(array('site'=>home_url(),'woocommerce'=>WC_VERSION,'checks'=>$GLOBALS['sku_runtime_checks'],'products'=>count($products),
    'variations'=>$variation_count,'sku_snapshot_sha256'=>$before,'orders_saved'=>0,'emails_sent'=>0))."\n";
