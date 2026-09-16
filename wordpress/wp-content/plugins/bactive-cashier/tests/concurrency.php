<?php
if (!defined('WP_CLI') || !WP_CLI || DB_NAME!=='cashier_fixture' || home_url()!=='http://localhost:8097') throw new RuntimeException('Isolated fixture required.');
use BActive\Cashier\Plugin;
function race_user(): int {
    $id=wp_insert_user(array('user_login'=>'race-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(24),'role'=>'bactive_sales_associate'));
    if(is_wp_error($id)) throw new RuntimeException('Fixture user failed.');
    return $id;
}
if(($args[0]??'')==='prepare') {
    $p=new WC_Product_Simple();$p->set_name('Synthetic last-unit race');$p->set_regular_price('100');$p->set_status('publish');
    $p->set_manage_stock(true);$p->set_stock_quantity(1);$p->set_backorders('no');$id=$p->save();
    $case=array('product'=>$id,'users'=>array(race_user(),race_user()),'keys'=>array(wp_generate_uuid4(),wp_generate_uuid4()));
    $case_id=wp_generate_uuid4();update_option('bactive_cashier_race_'.$case_id,$case,false);
    echo wp_json_encode(array('case'=>$case_id))."\n";
} elseif(($args[0]??'')==='create') {
    global $wpdb;
    $case=get_option('bactive_cashier_race_'.$args[1]);$actor=(int)$args[2];$same=($args[3]??'')==='same';
    wp_set_current_user($actor===2?0:$case['users'][$same?0:$actor]);
    // Rendezvous makes independent PHP processes start creation together.
    update_option('bactive_cashier_race_ready_'.$args[1].'_'.$actor,'yes',false);
    $until=microtime(true)+20;
    $count=($args[3]??'')==='triple'?3:2;
    do {
        $ready=true;
        for($i=0;$i<$count;$i++) {
            $ready=$ready&&$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'bactive_cashier_race_ready_'.$args[1].'_'.$i))==='yes';
        }
        if(!$ready) usleep(20000);
    } while(!$ready && microtime(true)<$until);
    if(!$ready) throw new RuntimeException('Concurrent worker rendezvous timed out.');
    if($actor===2) {
        $order=wc_create_order(array('status'=>'pending','created_via'=>'checkout'));
        $order->add_product(wc_get_product($case['product']),1);$order->calculate_totals();
        try { wc_reserve_stock_for_order($order); $status=200; }
        catch(Throwable $e) { $status=409; }
        echo wp_json_encode(array('status'=>$status,'source'=>'ordinary_woocommerce'))."\n";
        return;
    }
    $key=$case['keys'][$same?0:$actor];
    $r=new WP_REST_Request('POST','/bactive-cashier/v1/sales');$r->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
    $r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode(array('key'=>$key,'items'=>array(array('id'=>$case['product'],'quantity'=>1)))));
    $response=rest_do_request($r);
    echo wp_json_encode(array('status'=>$response->get_status(),'body'=>$response->get_data()))."\n";
} elseif(($args[0]??'')==='inspect') {
    $case=get_option('bactive_cashier_race_'.$args[1]);global $wpdb;
    $rows=$wpdb->get_results($wpdb->prepare('SELECT order_id,state FROM '.Plugin::table().' WHERE sale_key IN (%s,%s)',...$case['keys']));
    echo wp_json_encode(array('rows'=>$rows,'physical'=>wc_get_product($case['product'])->get_stock_quantity(),'held'=>wc_get_held_stock_quantity(wc_get_product($case['product']))))."\n";
} else throw new RuntimeException('Unknown scenario');
