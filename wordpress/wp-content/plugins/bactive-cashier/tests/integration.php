<?php
/** Real Woo/HPOS integration; invoked only in tools/cashier/runtime.py fixture. */
use BActive\Cashier\Plugin;
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Order_Lock;

if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'cashier_fixture'
    || home_url() !== 'http://localhost:8097' || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Isolated cashier fixture required.');
}
$GLOBALS['checks'] = array();
function check($condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks[] = $label;
}
function associate(): int {
    $name = 'fixture-' . wp_generate_uuid4();
    $id = wp_insert_user(array('user_login' => $name, 'user_pass' => wp_generate_password(28),
        'user_email' => $name . '@example.invalid', 'role' => 'bactive_sales_associate'));
    if (is_wp_error($id)) { throw new RuntimeException('Synthetic user creation failed.'); }
    wp_set_current_user($id);
    return $id;
}
function item(int $stock = 5): int {
    $p = new WC_Product_Simple();
    $p->set_name('Synthetic integration item ' . wp_generate_uuid4());
    $p->set_status('publish'); $p->set_regular_price('650.00');
    $p->set_manage_stock(true); $p->set_stock_quantity($stock); $p->set_backorders('no');
    return $p->save();
}
function api(string $method, string $route, ?array $body = null, bool $nonce = true): WP_REST_Response {
    $r = new WP_REST_Request($method, '/bactive-cashier/v1/' . $route);
    if ($nonce) { $r->set_header('X-WP-Nonce', wp_create_nonce('wp_rest')); }
    if ($body !== null) { $r->set_header('Content-Type', 'application/json'); $r->set_body(wp_json_encode($body)); }
    return rest_do_request($r);
}
function sale(int $product, int $quantity = 1, string $email = ''): array {
    $key = wp_generate_uuid4();
    $body = array('key' => $key, 'items' => array(array('id' => $product, 'quantity' => $quantity)), 'email' => $email);
    $r = api('POST', 'sales', $body);
    check($r->get_status() === 200, 'Create reserved pending sale: ' . wp_json_encode($r->get_data()));
    return array($key, $body, $r->get_data());
}
function order_for(string $key): WC_Order {
    global $wpdb;
    $id = $wpdb->get_var($wpdb->prepare('SELECT order_id FROM ' . Plugin::table() . ' WHERE sale_key=%s', $key));
    return wc_get_order((int)$id);
}
function qty(int $id): int { return (int) wc_get_product($id)->get_stock_quantity(); }

check(Plugin::ready(), 'Woo and protected gateway available');
check(Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'Real HPOS datastore is authoritative');
wp_set_current_user(0);
check(api('GET', 'products')->get_status() === 403, 'Anonymous denied');
$a = associate();
check(!current_user_can('manage_woocommerce') && !current_user_can('edit_products'), 'Associate has no manager/product edit privileges');
check(api('GET', 'products', null, false)->get_status() === 403, 'Missing nonce denied');
$product = item();
[$key, $body, $view] = sale($product, 2, 'customer@example.invalid');
$order = order_for($key);
check($view['total'] === '1300.00' && $view['status'] === 'unpaid', 'Server price and unpaid state');
$shipping_items = $order->get_shipping_methods();
$shipping_item = reset($shipping_items);
check(count($shipping_items) === 1 && $shipping_item instanceof WC_Order_Item_Shipping
    && $shipping_item->get_method_id() === 'local_pickup' && $shipping_item->get_name() === 'In-Store Pickup',
    'Cashier order uses the in-store pickup label');
check(qty($product) === 5, 'Reservation does not decrement physical stock');
check((int) wc_get_held_stock_quantity(wc_get_product($product)) === 2, 'Pending sale visible to standard Woo stock availability');
global $wpdb;
$expiry = $wpdb->get_var($wpdb->prepare("SELECT expires FROM {$wpdb->wc_reserved_stock} WHERE order_id=%d", $order->get_id()));
check($expiry === '9999-12-31 23:59:59', 'Durable reservation has sentinel expiry');
check(apply_filters('woocommerce_cancel_unpaid_order', true, $order) === false, 'Woo timeout cannot cancel cashier order');
$again = api('POST', 'sales', $body);
check($again->get_status() === 200 && $again->get_data()['number'] === $view['number'], 'Same creation key returns same sale');
$changed = $body; $changed['items'][0]['quantity'] = 1;
check(api('POST', 'sales', $changed)->get_status() === 409, 'Changed basket with reused key rejected');
$new = $body; $new['key'] = wp_generate_uuid4();
check(api('POST', 'sales', $new)->get_status() === 409, 'Second active sale on same associate rejected');
$b = associate();
check(api('GET', 'sales/' . $key)->get_status() === 404, 'Other associate cannot read sale');
check(api('POST', 'sales/' . $key . '/cash', array('received' => '2000.00'))->get_status() >= 400, 'Other associate cannot pay sale');
wp_set_current_user($a);
check(api('POST', 'sales/' . $key . '/handover', array('invoice' => 'TEST-001'))->get_status() === 409, 'Unpaid goods handover denied');
check(api('POST', 'sales/' . $key . '/cash', array('received' => '1200.00'))->get_status() === 409, 'Underpayment rejected');
check(api('POST', 'sales/' . $key . '/cash', array('received' => '1300.001'))->get_status() === 409, 'Excess decimal precision rejected');
$paid = api('POST', 'sales/' . $key . '/cash', array('received' => '1500.00'));
check($paid->get_status() === 200 && $paid->get_data()['status'] === 'paid' && $paid->get_data()['change'] === '200.00', 'Cash records paid and correct change: ' . wp_json_encode($paid->get_data()));
check(qty($product) === 3 && (int) wc_get_held_stock_quantity(wc_get_product($product)) === 0, 'Cash reduces stock exactly once and releases reserve');
$repeated = api('POST', 'sales/' . $key . '/cash', array('received' => '1500.00'));
check($repeated->get_status() === 200 && qty($product) === 3, 'Cash retry does not repeat stock reduction');
check(api('POST', 'sales/' . $key . '/handover', array('invoice' => ''))->get_status() === 409, 'Invoice reference required before handover');
$done = api('POST', 'sales/' . $key . '/handover', array('invoice' => 'TRAIN-001'));
check($done->get_status() === 200 && $done->get_data()['status'] === 'completed', 'Paid handover completes sale');
check(api('POST', 'sales/' . $key . '/handover', array('invoice' => 'TRAIN-002'))->get_status() === 409, 'Invoice reference cannot be silently changed');
check(qty($product) === 3, 'Handover does not reduce stock twice');
$fail_mail=static fn()=>false;
add_filter('pre_wp_mail',$fail_mail,PHP_INT_MAX);
try { Plugin::email_job($order->get_id()); }
finally { remove_filter('pre_wp_mail',$fail_mail,PHP_INT_MAX); }
$email_order=wc_get_order($order->get_id());
check($email_order->get_meta('_bactive_cashier_email_state')==='failed' && qty($product)===3, 'Mail failure preserves paid sale and inventory');
$email_order->update_meta_data('_bactive_cashier_email_at',time()-61);$email_order->save();
$email_retry=api('POST','sales/'.$key.'/email',array());
check($email_retry->get_status()===200 && $email_retry->get_data()['email_state']==='accepted' && qty($product)===3, 'Mail retry changes only confirmation delivery state');
[$cancel_key] = sale($product);
check(api('POST', 'sales/' . $cancel_key . '/cancel', array())->get_data()['status'] === 'cancelled', 'Unpaid pre-provider sale cancels');
check(qty($product) === 3 && (int) wc_get_held_stock_quantity(wc_get_product($product)) === 0, 'Cancellation releases only reservation');

associate(); $fault_product=item(2); [$fault_key]=sale($fault_product);
$fault_order=order_for($fault_key);
$prevent_stock=static fn($can,$order) => $order->get_id()===$fault_order->get_id() ? false : $can;
add_filter('woocommerce_can_reduce_order_stock',$prevent_stock,100,2);
try { $fault=api('POST','sales/'.$fault_key.'/cash',array('received'=>'650.00')); }
finally { remove_filter('woocommerce_can_reduce_order_stock',$prevent_stock,100); }
check($fault->get_status()===409, 'Cash effect failure does not report successful payment');
check(api('GET','sales/'.$fault_key)->get_data()['status']==='review', 'Partial cash effects show manager review');
check(qty($fault_product)===2 && (int)wc_get_held_stock_quantity(wc_get_product($fault_product))===1, 'Partial cash effects retain effective stock hold');
check(api('POST','sales/'.$fault_key.'/cash',array('received'=>'650.00'))->get_status()===409, 'Partial cash effects cannot be replayed');

associate(); $changed_product=item(2); [$changed_key]=sale($changed_product);
$changed_order=order_for($changed_key);$changed_order->set_total('1.00');$changed_order->save();
check(api('POST','sales/'.$changed_key.'/cash',array('received'=>'650.00'))->get_status()===409, 'Altered server basket blocked before cash collection');
check(qty($changed_product)===2, 'Altered basket rejection leaves physical stock untouched');

// Inventory errors and cross-channel competition use fresh associates to retain failed records.
$last = item(1); associate(); [$last_key] = sale($last);
associate();
$r = api('POST', 'sales', array('key' => wp_generate_uuid4(), 'items' => array(array('id' => $last, 'quantity' => 1))));
check($r->get_status() >= 400, 'A second associate cannot reserve the last unit');
$online = wc_create_order(array('status' => 'pending', 'created_via' => 'checkout'));
$online->add_product(wc_get_product($last), 1); $online->calculate_totals();
$blocked = false;
try { wc_reserve_stock_for_order($online); } catch (Throwable $e) { $blocked = true; }
check($blocked, 'Standard online checkout respects cashier last-unit reservation');
foreach (array('untracked', 'backorder') as $label) {
    associate(); $ids = get_option('bactive_cashier_fixture_ids');
    $r = api('POST', 'sales', array('key' => wp_generate_uuid4(), 'items' => array(array('id' => (int)$ids[$label], 'quantity' => 1))));
    check($r->get_status() >= 400, $label . ' inventory refused');
}
associate();$virtual_id=item(2);$virtual=wc_get_product($virtual_id);$virtual->set_virtual(true);$virtual->save();
$virtual_response=api('POST','sales',array('key'=>wp_generate_uuid4(),'items'=>array(array('id'=>$virtual_id,'quantity'=>1))));
check($virtual_response->get_status()>=400, 'Virtual item cannot bypass physical goods handover');

// Crash after paid status persistence but before stock effects must retain the hold.
associate(); $crash_product = item(1); [$crash_key] = sale($crash_product);
$crash_order = order_for($crash_key);
$wpdb->update($wpdb->prefix . 'wc_orders', array('status' => 'wc-processing'), array('id' => $crash_order->get_id()));
check((int) wc_get_held_stock_quantity(wc_get_product($crash_product)) === 1, 'Paid-before-effects crash retains effective inventory hold');
\BActive\Cashier\Stock::maybe_release($crash_order->get_id());
check((int) wc_get_held_stock_quantity(wc_get_product($crash_product)) === 1, 'Incomplete stock effects cannot release reservation');
$deny_deactivation=false;
$die_handler=static fn()=>static function($message){ throw new RuntimeException(strip_tags($message)); };
add_filter('wp_die_handler',$die_handler,PHP_INT_MAX);
try { \BActive\Cashier\Stock::guard_deactivation(); }
catch(Throwable $e) { $deny_deactivation=str_contains($e->getMessage(),'resolve all active sales'); }
finally { remove_filter('wp_die_handler',$die_handler,PHP_INT_MAX); }
check($deny_deactivation, 'Plugin deactivation denied while cashier holds remain');
$competing = wc_create_order(array('status' => 'pending', 'created_via' => 'checkout'));
$competing->add_product(wc_get_product($crash_product), 1); $competing->calculate_totals();
$blocked = false;
try { wc_reserve_stock_for_order($competing); } catch (Throwable $e) { $blocked = true; }
check($blocked, 'Online checkout cannot buy crash-held last unit');
$wpdb->update($wpdb->prefix . 'wc_orders', array('status' => 'wc-cancelled'), array('id' => $crash_order->get_id()));
$crash_order = wc_get_order($crash_order->get_id());
$crash_order->get_data_store()->read($crash_order);
$guarded = false;
try { \BActive\Cashier\Stock::release_verified_cancel($crash_order); } catch (Throwable $e) { $guarded = true; }
check($guarded, 'Verified cancellation requires order fence');
Order_Lock::acquire($crash_order->get_id());
try { \BActive\Cashier\Stock::release_verified_cancel($crash_order); }
finally { Order_Lock::release($crash_order->get_id()); }
check((int) wc_get_held_stock_quantity(wc_get_product($crash_product)) === 0, 'Explicit verified cancellation releases sentinel hold');
$ordinary_product = item(2);
$ordinary = wc_create_order(array('status' => 'pending', 'created_via' => 'checkout'));
$ordinary->add_product(wc_get_product($ordinary_product), 1); $ordinary->calculate_totals();
wc_reserve_stock_for_order($ordinary);
check((int) wc_get_held_stock_quantity(wc_get_product($ordinary_product)) === 1, 'Ordinary Woo reservation still counted');
\BActive\Cashier\Stock::maybe_release($ordinary);
check((int) wc_get_held_stock_quantity(wc_get_product($ordinary_product)) === 0, 'Ordinary Woo reservation release unchanged');

// Real gateway creates the session; only its HTTP provider response is synthetic.
$digital_owner = associate(); $digital_product = item(4); [$digital_key] = sale($digital_product);
$digital = api('POST', 'sales/' . $digital_key . '/digital', array());
check($digital->get_status() === 200 && $digital->get_data()['status'] === 'pending' && str_starts_with($digital->get_data()['payment_url'] ?? '', 'https://checkout.paymongo.com/'), 'Real gateway issues pending session: ' . wp_json_encode($digital->get_data()));
$digital_order = order_for($digital_key);
check(count(Gateway::order_attempts($digital_order)) === 1, 'One persisted PayMongo attempt');
$retry = api('POST', 'sales/' . $digital_key . '/digital', array());
check($retry->get_status() === 200 && count(Gateway::order_attempts(order_for($digital_key))) === 1, 'Digital retry resumes same attempt');
check(api('POST', 'sales/' . $digital_key . '/cash', array('received' => '650'))->get_status() === 409, 'Digital sale cannot switch to cash');
check(api('POST', 'sales/' . $digital_key . '/cancel', array())->get_status() === 409, 'Associate cannot cancel active digital session');
check(qty($digital_product) === 4 && (int) wc_get_held_stock_quantity(wc_get_product($digital_product)) === 1, 'Awaiting digital keeps inventory unavailable');
wp_set_current_user(0); // Provider callbacks and cron have no authenticated cashier.
bactive_cashier_fixture_paid($digital_order->get_id());
wp_set_current_user($digital_owner);
$settled = api('GET', 'sales/' . $digital_key);
check($settled->get_status() === 200 && $settled->get_data()['status'] === 'paid', 'Gateway reconciliation confirms paid and stock: ' . wp_json_encode($settled->get_data()));
check(qty($digital_product) === 3, 'Verified digital reduces stock once');
bactive_cashier_fixture_paid($digital_order->get_id());
check(qty($digital_product) === 3, 'Duplicate provider readback does not reduce stock again');
$done = api('POST', 'sales/' . $digital_key . '/handover', array('invoice' => 'TRAIN-DIGITAL-001'));
check($done->get_status() === 200 && $done->get_data()['status'] === 'completed', 'Digital fulfillment preserves gateway protections');
$refund = wc_create_refund(array('order_id' => $digital_order->get_id(), 'amount' => '1.00', 'refund_payment' => false));
check(is_wp_error($refund), 'Woo refunds remain blocked for PayMongo history');

echo wp_json_encode(array('passed' => count($GLOBALS['checks']), 'checks' => $GLOBALS['checks']), JSON_PRETTY_PRINT) . "\n";
