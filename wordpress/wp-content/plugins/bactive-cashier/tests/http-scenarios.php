<?php
/** CLI half of signed HTTP provider and manager-recovery regression. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'cashier_fixture'
    || home_url() !== 'http://localhost:8097' || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Isolated cashier fixture required.');
}
function scenario_api(string $method, string $route, array $body = array()): WP_REST_Response {
    $r = new WP_REST_Request($method, '/bactive-cashier/v1/' . $route);
    $r->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $r->set_header('Content-Type', 'application/json'); $r->set_body(wp_json_encode($body));
    return rest_do_request($r);
}
$mode = $args[0] ?? '';
if ($mode === 'prepare') {
    $method = $args[1] ?? 'qrph';
    $name = 'http-' . wp_generate_uuid4();
    $owner = wp_insert_user(array('user_login'=>$name,'user_pass'=>wp_generate_password(28),'role'=>'bactive_sales_associate'));
    wp_set_current_user($owner);
    $p = new WC_Product_Simple(); $p->set_name('Synthetic HTTP canary'); $p->set_regular_price('100.00');
    $p->set_status('publish'); $p->set_manage_stock(true); $p->set_stock_quantity(3); $p->set_backorders('no'); $product=$p->save();
    $key=wp_generate_uuid4();
    $r=scenario_api('POST','sales',array('key'=>$key,'items'=>array(array('id'=>$product,'quantity'=>1))));
    if($r->get_status()!==200) throw new RuntimeException('Create scenario failed: '.wp_json_encode($r->get_data()));
    if ($method !== 'order-pay') {
        $r=scenario_api('POST','sales/'.$key.'/digital');
        if($r->get_status()!==200) throw new RuntimeException('Digital scenario failed: '.wp_json_encode($r->get_data()));
    }
    global $wpdb;
    $order_id=(int)$wpdb->get_var($wpdb->prepare('SELECT order_id FROM '.\BActive\Cashier\Plugin::table().' WHERE sale_key=%s',$key));
    $case=array('key'=>$key,'order_id'=>$order_id,'owner'=>$owner,'product'=>$product);
    if (in_array($method, array('order-pay', 'order-pay-digital'), true)) {
        wp_set_current_user(0);
        // The HTTP request sends no cookies, so its Woo guest session must also be absent.
        if (WC()->session && WC()->session->has_session()) throw new RuntimeException('Unexpected guest session in nonce fixture.');
        $case['order_pay'] = array('order_key'=>wc_get_order($order_id)->get_order_key(),
            'nonce'=>wp_create_nonce('woocommerce-pay'));
    } elseif ($method!=='cancel') {
        bactive_cashier_fixture_paid($order_id,$method,false);
        $attempts=\BActive\PayMongo\Gateway::order_attempts(wc_get_order($order_id));$attempt=end($attempts);
        $session=get_option('bactive_cashier_fixture_session_'.$attempt['session_id']);
        $case['event']=array('data'=>array('id'=>'evt_cashier_synthetic_'.$order_id,'type'=>'event','attributes'=>array(
            'type'=>'checkout_session.payment.paid','livemode'=>true,'data'=>$session['data'],
        )));
    }
    update_option('bactive_cashier_http_case_'.$key,$case,false);
    echo wp_json_encode($case)."\n";
} elseif ($mode==='inspect') {
    $case=get_option('bactive_cashier_http_case_'.$args[1]);
    wp_set_current_user($case['owner']);
    $r=scenario_api('GET','sales/'.$case['key']);
    global $wpdb;
    $active=$wpdb->get_var($wpdb->prepare('SELECT active_owner FROM '.\BActive\Cashier\Plugin::table().' WHERE sale_key=%s',$case['key']));
    $order=wc_get_order($case['order_id']);
    $attempts=\BActive\PayMongo\Gateway::order_attempts($order);
    $result=array('payment_method'=>$order->get_payment_method(),'order_status'=>$order->get_status(),
        'attempt_count'=>count($attempts),'attempt_hash'=>hash('sha256',wp_json_encode($attempts)),
        'sale'=>$r->get_data(),'qty'=>wc_get_product($case['product'])->get_stock_quantity(),
        'held'=>wc_get_held_stock_quantity(wc_get_product($case['product'])),'active_owner'=>$active);
    echo wp_json_encode($result)."\n";
} elseif ($mode==='manager') {
    wp_set_current_user(1);
    $_POST['key']=$args[1];
    $_REQUEST['_wpnonce']=wp_create_nonce('bactive_cashier_resolve');
    \BActive\Cashier\Plugin::manager_resolve();
} else { throw new RuntimeException('Unknown fixture scenario.'); }
