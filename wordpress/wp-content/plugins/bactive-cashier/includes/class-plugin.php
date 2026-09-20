<?php
namespace BActive\Cashier;
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Order_Lock;
use BActive\PayMongo\Webhook;
use BActive\PayMongo\Reconciler;
use Automattic\WooCommerce\Checkout\Helpers\ReserveStock;
defined('ABSPATH') || exit;

final class Plugin {
    const CAP = 'bactive_use_cashier';
    const VIA = 'bactive_cashier';
    const CASH = 'bactive_in_store_cash';
    const DIGITAL = 'bactive_paymongo';
    private static bool $cash_effects = false;

    public static function table(): string { global $wpdb; return $wpdb->prefix . 'bactive_cashier_sales'; }
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        dbDelta("CREATE TABLE $table (
            sale_key char(36) NOT NULL,
            owner bigint(20) unsigned NOT NULL,
            active_owner bigint(20) unsigned DEFAULT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            fingerprint char(64) NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'building',
            created_at datetime NOT NULL,
            PRIMARY KEY  (sale_key),
            UNIQUE KEY active_owner (active_owner),
            UNIQUE KEY order_id (order_id)
        ) {$wpdb->get_charset_collate()};");
        add_role('bactive_sales_associate', 'B Active Sales Associate', array('read' => true, self::CAP => true));
        $admin = get_role('administrator');
        if ($admin) { $admin->add_cap(self::CAP); }
        add_option('bactive_cashier_enabled', 'no', '', false);
        update_option('bactive_cashier_version', VERSION, false);
    }
    public static function boot(): void {
        require_once __DIR__ . '/class-stock.php';
        Stock::boot();
        add_filter('woocommerce_payment_complete_order_status', static fn($status,$id,$order) => self::is_sale($order) ? 'processing' : $status, 100, 3);
        add_action('admin_post_bactive_cashier_resolve', array(self::class, 'manager_resolve'));
        add_filter('woocommerce_payment_gateways', static function ($gateways) { $gateways[] = Cash_Gateway::class; return $gateways; });
        add_action('rest_api_init', array(self::class, 'routes'));
        add_action('template_redirect', array(self::class, 'page'), 0);
        add_action('admin_menu', array(self::class, 'admin_menu'));
        add_action('admin_post_bactive_cashier_settings', array(self::class, 'settings'));
        add_filter('woocommerce_order_hold_stock_minutes', static fn($minutes, $order) => self::is_sale($order) ? 52560000 : $minutes, 100, 2);
        add_filter('woocommerce_cancel_unpaid_order', static fn($cancel, $order) => self::is_sale($order) ? false : $cancel, 100, 2);
        // Woo handles valid order-pay POSTs on wp, before template_redirect.
        add_action('woocommerce_before_pay_action', static function ($order) {
            if (self::is_sale($order)) wp_die('Use the private cashier to manage this in-store sale.', 'In-store sale', array('response'=>403));
        }, 0);
        add_action('template_redirect', static function () {
            if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
                $order=wc_get_order(absint(get_query_var('order-pay')));
                if (self::is_sale($order)) wp_die('Ask the Sales Associate to open the secure payment link for this in-store sale.', 'In-store sale', array('response'=>403));
            }
        }, 1);
        foreach (array('customer_processing_order','customer_completed_order','customer_on_hold_order','customer_invoice') as $email) {
            add_filter('woocommerce_email_enabled_' . $email, static fn($enabled, $order) => self::is_sale($order) ? false : $enabled, 100, 2);
        }
        add_action('woocommerce_order_status_changed', array(self::class, 'status_changed'), 100, 4);
        add_action('bactive_cashier_email', array(self::class, 'email_job'));
        add_action('woocommerce_admin_order_data_after_order_details', array(self::class, 'order_details'));
    }
    public static function is_sale($order): bool { return $order instanceof \WC_Order && $order->get_created_via() === self::VIA; }
    public static function ready(): bool {
        return class_exists(Gateway::class) && class_exists(Order_Lock::class) && class_exists(Webhook::class)
            && get_option('woocommerce_manage_stock') === 'yes' && get_woocommerce_currency() === 'PHP'
            && (int) get_option('woocommerce_schema_version', 0) >= 430;
    }
    private static function enabled(): bool { return get_option('bactive_cashier_enabled') === 'yes' && self::ready(); }
    public static function permission(\WP_REST_Request $r) {
        if (!is_user_logged_in() || !current_user_can(self::CAP)) { return new \WP_Error('cashier_forbidden', 'Sign in with your own cashier account.', array('status'=>403)); }
        if (!wp_verify_nonce($r->get_header('X-WP-Nonce'), 'wp_rest')) { return new \WP_Error('cashier_nonce', 'Your session expired. Sign in again to resume this sale.', array('status'=>403)); }
        if (!self::ready()) { return new \WP_Error('cashier_setup', 'Checkout is unavailable. Ask your manager to check the payment and stock setup.', array('status'=>503)); }
        return true;
    }
    public static function routes(): void {
        foreach (array('products'=>array('GET','products'), 'sales'=>array('POST','create'), 'sales/(?P<key>[a-f0-9-]{36})'=>array('GET','read')) as $route=>$def) {
            register_rest_route('bactive-cashier/v1', '/' . $route, array('methods'=>$def[0], 'callback'=>array(self::class,$def[1]), 'permission_callback'=>array(self::class,'permission')));
        }
        foreach (array('cash','digital','handover','email','cancel') as $action) {
            register_rest_route('bactive-cashier/v1', '/sales/(?P<key>[a-f0-9-]{36})/' . $action, array('methods'=>'POST','callback'=>static fn($r)=>self::mutate($r,$action),'permission_callback'=>array(self::class,'permission')));
        }
        add_filter('rest_post_dispatch', static function ($response, $server, $request) {
            if (str_starts_with($request->get_route(), '/bactive-cashier/')) {
                $response->header('Cache-Control', 'private, no-store, max-age=0');
                $response->header('X-Robots-Tag', 'noindex, nofollow');
            }
            return $response;
        }, 10, 3);
    }
    private static function error(string $message, int $status=409): \WP_Error { return new \WP_Error('cashier_error', $message, array('status'=>$status)); }
    private static function money($input): int {
        if (!is_scalar($input) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string)$input)) { throw new \RuntimeException('Enter a valid peso amount with at most two decimal places.'); }
        $parts=explode('.',(string)$input); return ((int)$parts[0]*100)+(int)str_pad($parts[1]??'',2,'0');
    }
    private static function decimal(int $minor): string { return sprintf('%d.%02d', intdiv($minor,100),$minor%100); }
    private static function product(int $id): \WC_Product {
        $p=wc_get_product($id);
        if (!$p || !in_array($p->get_type(),array('simple','variation'),true) || !$p->is_purchasable() || !$p->is_in_stock()
            || $p->is_virtual() || $p->is_downloadable() || !$p->managing_stock() || $p->backorders_allowed() || $p->get_stock_quantity()===null || (int)$p->get_stock_quantity()<1
            || ($p->is_type('variation') && array_filter($p->get_attributes(),static fn($v)=>$v===''))) {
            throw new \RuntimeException('An item is unavailable or its stock/variation needs a manager check.');
        }
        return $p;
    }
    public static function products(\WP_REST_Request $r) {
        $search=sanitize_text_field((string)$r->get_param('search'));
        $results=array(); $stock=new ReserveStock();
        $ids=wc_get_products(array('status'=>'publish','type'=>array('simple','variable'),'limit'=>-1,'return'=>'ids'));
        foreach ($ids as $id) {
            $parent=wc_get_product($id);
            $children=$parent->is_type('variable')?$parent->get_children():array($id);
            foreach ($children as $child) {
                try { $p=self::product((int)$child); } catch (\Throwable $e) { continue; }
                $name=$p->get_name();
                if ($search!=='' && stripos($name.' '.$p->get_sku(),$search)===false) { continue; }
                $available=max(0,(int)$p->get_stock_quantity()-(int)$stock->get_reserved_stock($p));
                if ($available<1) { continue; }
                $results[]=array('id'=>$p->get_id(),'name'=>$name,'sku'=>$p->get_sku(),'image'=>wp_get_attachment_image_url($p->get_image_id(),'woocommerce_thumbnail')?:'',
                    'price'=>wc_format_decimal(wc_get_price_including_tax($p),2),'stock'=>$available,
                    'attributes'=>$p->is_type('variation')?wc_get_formatted_variation($p,true,false,true):'');
                if (count($results)>=100) { return array('products'=>$results,'has_more'=>true); }
            }
        }
        return array('products'=>$results,'has_more'=>false);
    }
    private static function row(string $key): ?object { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE sale_key=%s',$key)); }
    private static function own(string $key) {
        $row=self::row($key);
        if (!$row || ((int)$row->owner!==get_current_user_id() && !current_user_can('manage_woocommerce'))) { throw new \RuntimeException('Sale not found for this account.'); }
        return $row;
    }
    public static function create(\WP_REST_Request $r) {
        global $wpdb;
        if (!self::enabled()) { return self::error('New sales are paused. Existing sales can still be resumed.',503); }
        $body=$r->get_json_params();
        if (!is_array($body)) { return self::error('Invalid sale.',400); }
        $key=$body['key']??'';
        if (!is_string($key)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$key)) { return self::error('Invalid sale reference.',400); }
        $items=$body['items']??null;
        if (!is_array($items)||count($items)<1||count($items)>50) { return self::error('Select between 1 and 50 items.',400); }
        $email=$body['email']??'';
        if (!is_string($email)||strlen($email)>254||($email!==''&&!is_email($email))) { return self::error('Check the customer email address.',400); }
        $normalized=array();
        foreach ($items as $item) {
            if (!is_array($item)||!is_int($item['id']??null)||!is_int($item['quantity']??null)||$item['id']<1||$item['quantity']<1||$item['quantity']>100) { return self::error('Invalid product or quantity.',400); }
            $normalized[$item['id']]=($normalized[$item['id']]??0)+$item['quantity'];
            if ($normalized[$item['id']]>100) { return self::error('Maximum quantity is 100 per item.',400); }
        }
        ksort($normalized);$fingerprint=hash('sha256',wp_json_encode(array($normalized,strtolower($email))));
        $existing=self::row($key);
        if ($existing) {
            if ((int)$existing->owner!==get_current_user_id()||!hash_equals($existing->fingerprint,$fingerprint)) { return self::error('This sale reference belongs to a different basket. Resume the existing sale.'); }
            return self::read($r);
        }
        // Claim before creating a Woo order. A crashed build remains visible for manager review, never silently recreated.
        $insert=$wpdb->insert(self::table(),array('sale_key'=>$key,'owner'=>get_current_user_id(),'active_owner'=>get_current_user_id(),'fingerprint'=>$fingerprint,'state'=>'building','created_at'=>current_time('mysql',true)));
        if ($insert!==1) {
            $active=$wpdb->get_var($wpdb->prepare('SELECT sale_key FROM '.self::table().' WHERE active_owner=%d',get_current_user_id()));
            return new \WP_Error('cashier_active_sale','Resume your current sale before starting another.',array('status'=>409,'key'=>$active));
        }
        $order=null;
        if (!Order_Lock::acquire_checkout('cashier-create-'.$key)) return self::error('Sale creation is in progress. Resume shortly.');
        try {
            $products=array();foreach ($normalized as $id=>$quantity) { $products[$id]=self::product((int)$id); }
            $order=wc_create_order(array('status'=>'pending','created_via'=>self::VIA,'customer_id'=>0));
            if (is_wp_error($order)) { throw new \RuntimeException('Order creation failed. Ask your manager to review this sale reference.'); }
            if ($wpdb->update(self::table(),array('order_id'=>$order->get_id()),array('sale_key'=>$key))!==1) { throw new \RuntimeException('The sale record could not be verified. Ask your manager.'); }
            $order->set_currency('PHP');
            $order->update_meta_data('_bactive_cashier_key',$key);
            $order->update_meta_data('_bactive_cashier_associate',get_current_user_id());
            $order->update_meta_data('_bactive_cashier_store','Davao City');
            $order->set_billing_email($email);
            $base=wc_get_base_location();
            foreach (array('billing','shipping') as $address) {
                $order->{'set_'.$address.'_country'}($base['country']);$order->{'set_'.$address.'_state'}($base['state']);
                $order->{'set_'.$address.'_city'}(get_option('woocommerce_store_city'));
                $order->{'set_'.$address.'_postcode'}(get_option('woocommerce_store_postcode'));
            }
            foreach ($normalized as $id=>$quantity) { $order->add_product($products[$id],$quantity); }
            $pickup=new \WC_Order_Item_Shipping();$pickup->set_method_id('local_pickup');$pickup->set_method_title('In-Store Pickup');$pickup->set_total('0');$order->add_item($pickup);
            $order->calculate_totals(true);$order->save();
            $order->update_meta_data('_bactive_cashier_basket_hash',self::basket_hash($order));$order->save();
            if (self::money($order->get_total())<1) { throw new \RuntimeException('The sale total must be greater than zero.'); }
            self::reserve($order);
            if (!Order_Lock::renew_checkout() || $wpdb->update(self::table(),array('state'=>'ready'),array('sale_key'=>$key,'state'=>'building','active_owner'=>get_current_user_id()))!==1) { throw new \RuntimeException('The stock reservation needs a manager check.'); }
            return self::view($order,self::row($key));
        } catch (\Throwable $e) {
            // No payment can start before ready. Retain partial orders and the claim for inspection.
            if ($order instanceof \WC_Order) {
                $order->update_meta_data('_bactive_cashier_review','Sale creation or stock reservation did not finish.');$order->save();
            }
            $wpdb->update(self::table(),array('state'=>'review'),array('sale_key'=>$key));
            return self::error('The sale could not be prepared. No payment was started. Resume it and ask your manager to review stock.');
        } finally { Order_Lock::release_checkout(); }
    }
    private static function reserve(\WC_Order $order): void {
        global $wpdb;
        (new ReserveStock())->reserve_stock_for_order($order,52560000);
        // A sentinel, not a renewable lease: a worker outage cannot make a still-payable sale available again.
        $ok=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->wc_reserved_stock} SET expires='9999-12-31 23:59:59' WHERE order_id=%d",$order->get_id()));
        if ($ok===false || !self::reserved($order)) { throw new \RuntimeException('Stock hold could not be verified.'); }
    }
    private static function reserved(\WC_Order $order): bool {
        global $wpdb;
        $expected=array();foreach ($order->get_items() as $item) { $p=$item->get_product();if (!$p) return false;$id=$p->get_stock_managed_by_id();$expected[$id]=($expected[$id]??0)+(int)$item->get_quantity(); }
        $rows=$wpdb->get_results($wpdb->prepare("SELECT product_id,stock_quantity,expires FROM {$wpdb->wc_reserved_stock} WHERE order_id=%d",$order->get_id()));
        if (count($rows)!==count($expected)) return false;
        foreach ($rows as $row) { if (($expected[(int)$row->product_id]??-1)!==(int)$row->stock_quantity||$row->expires!=='9999-12-31 23:59:59') return false; }
        return true;
    }
    public static function read(\WP_REST_Request $r) {
        try {
            $row=self::own((string)$r['key']);
            $order=$row->order_id?wc_get_order((int)$row->order_id):null;
            if (!$order) { return self::error('This sale needs a manager review. No new payment should be collected.'); }
            return self::view($order,$row);
        } catch (\Throwable $e) { return self::error('Sale not found for this account.',404); }
    }
    public static function stock_done(\WC_Order $order): bool {
        if (!$order->get_data_store()->get_stock_reduced($order->get_id())) return false;
        foreach ($order->get_items() as $item) {
            if ((int)$item->get_meta('_reduced_stock',true)!==(int)$item->get_quantity()) return false;
        }
        return true;
    }
    private static function basket_hash(\WC_Order $order): string {
        $items=array();foreach ($order->get_items() as $item) $items[]=array($item->get_product_id(),$item->get_variation_id(),$item->get_quantity(),$item->get_total(),$item->get_total_tax());
        return hash('sha256',wp_json_encode(array($order->get_currency(),$order->get_total(),$order->get_shipping_total(),$items)));
    }
    private static function intact(\WC_Order $order): bool {
        $stored=(string)$order->get_meta('_bactive_cashier_basket_hash');
        return $stored!==''&&hash_equals($stored,self::basket_hash($order));
    }
    private static function paid(\WC_Order $order): bool {
        if (!self::intact($order)||!$order->is_paid()||!$order->get_date_paid()||!self::stock_done($order)||$order->get_meta('_bactive_cashier_review')) return false;
        if ($order->get_payment_method()===self::CASH) { return $order->get_meta('_bactive_cashier_cash_state')==='done'; }
        if ($order->get_payment_method()!==self::DIGITAL || !Gateway::has_provider_payment_evidence($order)
            || Gateway::has_inconsistent_provider_payment_state($order)||Webhook::has_pending_reviews($order->get_id())) return false;
        foreach (array('_bactive_paymongo_settlement_pending','_bactive_paymongo_review_required',Reconciler::UNRESOLVED_META) as $meta) { if ($order->get_meta($meta)) return false; }
        return true;
    }
    private static function view(\WC_Order $order, object $row): array {
        $paid=self::paid($order);$digital=$order->get_meta('_bactive_cashier_digital_started')==='yes';
        $review=$row->state!=='ready'||$order->get_meta('_bactive_cashier_review')||Gateway::has_inconsistent_provider_payment_state($order)||Webhook::has_pending_reviews($order->get_id());
        foreach (array('_bactive_paymongo_review_required',Reconciler::UNRESOLVED_META) as $meta) { $review=$review||$order->get_meta($meta); }
        if ($order->is_paid()&&!$paid) $review=true;
        $status=$review?'review':($paid?($order->get_status()==='completed'&&$order->get_meta('_bactive_cashier_invoice')&&$order->get_meta('_bactive_cashier_handed_by')?'completed':'paid'):($order->get_status()==='cancelled'?'cancelled':($digital?'pending':'unpaid')));
        $items=array();foreach ($order->get_items() as $item) $items[]=array('name'=>$item->get_name(),'quantity'=>$item->get_quantity(),'total'=>wc_format_decimal((float)$item->get_total()+(float)$item->get_total_tax(),2));
        return array('key'=>$row->sale_key,'number'=>$order->get_order_number(),'status'=>$status,'items'=>$items,'total'=>wc_format_decimal($order->get_total(),2),
            'method'=>$order->get_payment_method()===self::CASH?'cash':$order->get_payment_method(),'received'=>(string)$order->get_meta('_bactive_cashier_received'),
            'change'=>(string)$order->get_meta('_bactive_cashier_change'),'payment_url'=>$status==='pending'?self::checkout_url($order):null,
            'invoice'=>(string)$order->get_meta('_bactive_cashier_invoice'),'email'=>$order->get_billing_email(),'email_state'=>(string)$order->get_meta('_bactive_cashier_email_state'),
            'can_cancel'=>!$digital&&!$order->is_paid()&&!$order->get_meta('_bactive_cashier_cash_state')&&$order->has_status('pending'),
            'message'=>$status==='review'?'Ask your manager. Do not collect another payment or hand over goods.':($status==='pending'?'Waiting for server payment confirmation. Do not collect again.':''));
    }
    private static function checkout_url(\WC_Order $order): ?string {
        $url=(string)$order->get_meta('_bactive_cashier_checkout_url');
        $parts=wp_parse_url($url);
        return is_array($parts)&&($parts['scheme']??'')==='https'&&in_array(strtolower($parts['host']??''),array('checkout.paymongo.com','pm.link'),true)&&!isset($parts['user'])?$url:null;
    }
    public static function mutate(\WP_REST_Request $r,string $action) {
        global $wpdb;
        $order=null;$locked=false;
        try {
            $row=self::own((string)$r['key']);$id=(int)$row->order_id;
            if (!$id||!Order_Lock::acquire($id)) return self::error('This sale is being updated. Wait a moment, then resume it.');
            $locked=true;$order=wc_get_order($id);
            if (!$order) throw new \RuntimeException('Sale not found.');
            $body=$r->get_json_params();if (!is_array($body)) $body=array();
            if ($action==='cancel') {
                if (!self::view($order,$row)['can_cancel']) throw new \RuntimeException('This sale cannot be cancelled here. Ask your manager to resolve the payment.');
                $order->update_meta_data('_bactive_cashier_cancel_verified','yes');$order->save();
                $order->update_status('cancelled','Cashier cancelled before payment.');
                Stock::release_verified_cancel($order);
                $wpdb->update(self::table(),array('active_owner'=>null,'state'=>'ready'),array('sale_key'=>$row->sale_key));
            } elseif ($action==='cash'||$action==='digital') {
                if (self::paid($order)) return self::view($order,$row);
                if ($row->state!=='ready'||$order->get_meta('_bactive_cashier_review')) throw new \RuntimeException('Manager review is required. Do not collect payment.');
                if (!self::intact($order)||!$order->has_status('pending')||!self::reserved($order)) throw new \RuntimeException('Stock or payment state changed. Ask your manager before collecting payment.');
                if ($action==='cash') {
                    if ($order->get_meta('_bactive_cashier_digital_started')||Gateway::has_protected_payment_state($order)) throw new \RuntimeException('A digital payment was started. Do not collect cash; ask your manager.');
                    if ($order->get_meta('_bactive_cashier_cash_state')) throw new \RuntimeException('Cash recording needs review. Do not collect cash again.');
                    $received=self::money($body['received']??'');$total=self::money($order->get_total());
                    if ($received<$total) throw new \RuntimeException('Cash received is less than the total.');
                    $order->set_payment_method(self::CASH);$order->set_payment_method_title('Cash received in store');
                    $order->update_meta_data('_bactive_cashier_received',self::decimal($received));$order->update_meta_data('_bactive_cashier_change',self::decimal($received-$total));
                    $order->update_meta_data('_bactive_cashier_cash_state','armed');$order->save();
                    if (!Order_Lock::renew($id)) throw new \RuntimeException('Sale lock was lost. Ask your manager.');
                    self::$cash_effects=true;
                    try { $order->payment_complete(); } finally { self::$cash_effects=false; }
                    $order=wc_get_order($id);
                    if (!$order->is_paid()||!self::stock_done($order)) throw new \RuntimeException('Cash was recorded but stock needs manager verification. Do not repeat this payment.');
                    $order->update_meta_data('_bactive_cashier_cash_state','done');$order->save();
                    self::queue_email($id);
                } else {
                    if ($order->get_meta('_bactive_cashier_cash_state')||$order->get_payment_method()===self::CASH) throw new \RuntimeException('Cash was already recorded. Ask your manager.');
                    if ($order->get_meta('_bactive_cashier_digital_started')==='yes') return self::view($order,$row);
                    $gateway=WC()->payment_gateways()->payment_gateways()[self::DIGITAL]??null;
                    if (!$gateway instanceof Gateway) throw new \RuntimeException('Digital payments are unavailable.');
                    $order->set_payment_method($gateway);$order->update_meta_data('_bactive_cashier_digital_started','yes');$order->save();
                    // Existing gateway owns sessions, provider idempotency and callbacks. Never manufacture paid status here.
                    $result=$gateway->process_payment($id);
                    // The gateway releases its reentrant lock. Reacquire and reload before our metadata write.
                    if (!Order_Lock::acquire($id)) { $locked=false;return self::error('Payment is being updated. Resume this sale; do not collect again.'); }
                    $order=wc_get_order($id);
                    if (($result['result']??'')==='success'&&!self::paid($order)) {
                        $order->update_meta_data('_bactive_cashier_checkout_url',(string)($result['redirect']??''));$order->save();
                        if (!self::checkout_url($order)) throw new \RuntimeException('Payment link requires manager review. Do not start another payment.');
                    } elseif (!self::paid($order)) {
                        throw new \RuntimeException('Digital payment could not be confirmed. Ask your manager; do not collect again.');
                    }
                }
            } elseif ($action==='handover') {
                if (!self::paid($order)) throw new \RuntimeException('Payment and inventory must be confirmed before handover.');
                $invoice=$body['invoice']??'';
                if (!is_string($invoice)||!preg_match('/^[A-Za-z0-9][A-Za-z0-9 .\/-]{0,63}$/D',$invoice)) throw new \RuntimeException('Enter the serial number from the registered handwritten invoice.');
                $old=(string)$order->get_meta('_bactive_cashier_invoice');
                if ($old!==''&&$old!==$invoice) throw new \RuntimeException('This sale already has an invoice reference. Ask your manager to correct it.');
                $order->update_meta_data('_bactive_cashier_invoice',$invoice);$order->update_meta_data('_bactive_cashier_handed_by',get_current_user_id());$order->save();
                if (!$order->has_status('completed')) $order->update_status('completed','Cashier confirmed registered handwritten invoice and goods handover.');
                $wpdb->update(self::table(),array('active_owner'=>null),array('sale_key'=>$row->sale_key));
            } elseif ($action==='email') {
                if (!self::paid($order)) throw new \RuntimeException('Email confirmation is available after payment is verified.');
                self::send_email($order,true);$order=wc_get_order($id);
            }
            return self::view($order,self::row($row->sale_key));
        } catch (\Throwable $e) {
            if ($order instanceof \WC_Order && Order_Lock::held_by_request($order->get_id()) && Order_Lock::renew($order->get_id()) && $action==='cash' && $order->get_meta('_bactive_cashier_cash_state')) {
                // Unknown effects are deliberately not replayed. Existing provider reconciliation still runs.
                $order->update_meta_data('_bactive_cashier_review','Payment action needs manager verification.');$order->save();
            }
            return self::error($e->getMessage());
        } finally { if ($locked&&$order instanceof \WC_Order) Order_Lock::release($order->get_id()); }
    }
    public static function status_changed($id,$old,$new,$order): void {
        if (!self::is_sale($order)) return;
        if (in_array($new,array('processing','completed'),true)) {
            self::queue_email((int)$id);
            if (!wp_next_scheduled('bactive_cashier_email',array((int)$id))) wp_schedule_single_event(time()+60,'bactive_cashier_email',array((int)$id));
        }
    }
    private static function queue_email(int $id): void {
        if (function_exists('as_enqueue_async_action')) as_enqueue_async_action('bactive_cashier_email',array($id),'bactive-cashier',true);
        elseif (!wp_next_scheduled('bactive_cashier_email',array($id))) wp_schedule_single_event(time()+10,'bactive_cashier_email',array($id));
    }
    public static function email_job($id): void {
        if (!self::ready()||!Order_Lock::acquire((int)$id)) return;
        try { $order=wc_get_order((int)$id);if (self::is_sale($order)&&self::paid($order)) self::send_email($order,false); }
        finally { Order_Lock::release((int)$id); }
    }
    private static function send_email(\WC_Order $order,bool $resend): void {
        if ($order->get_billing_email()==='') { $order->update_meta_data('_bactive_cashier_email_state','not_requested');$order->save();return; }
        $state=(string)$order->get_meta('_bactive_cashier_email_state');
        if (!$resend&&in_array($state,array('sending','accepted'),true)) return;
        $last=(int)$order->get_meta('_bactive_cashier_email_at');
        if ($resend&&time()-$last<60) throw new \RuntimeException('Wait one minute before resending the confirmation.');
        $order->update_meta_data('_bactive_cashier_email_state','sending');$order->update_meta_data('_bactive_cashier_email_at',time());$order->save();
        $lines=array('B Active — Payment confirmation','Order #'.$order->get_order_number(),'');
        foreach ($order->get_items() as $item) $lines[]=$item->get_name().' × '.$item->get_quantity().' — PHP '.wc_format_decimal((float)$item->get_total()+(float)$item->get_total_tax(),2);
        $lines[]='Total: PHP '.wc_format_decimal($order->get_total(),2);$lines[]='Payment: '.$order->get_payment_method_title();
        $lines[]='';$lines[]='This is an order/payment confirmation, not a BIR tax invoice. Your registered handwritten invoice is issued in store.';
        $ok=wp_mail($order->get_billing_email(),'B Active payment confirmation — Order #'.$order->get_order_number(),implode("\n",$lines),array('Content-Type: text/plain; charset=UTF-8'));
        // Accepted by mail transport is not proof of inbox delivery.
        $order->update_meta_data('_bactive_cashier_email_state',$ok?'accepted':'failed');$order->save();
    }
    public static function page(): void {
        if (!isset($_GET['bactive_cashier'])) return;
        if (!is_user_logged_in()) { auth_redirect();exit; }
        if (!current_user_can(self::CAP)) wp_die('This page requires a B Active cashier account.',403);
        nocache_headers();header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
        global $wpdb;$active=$wpdb->get_var($wpdb->prepare('SELECT sale_key FROM '.self::table().' WHERE active_owner=%d',get_current_user_id()));
        $user=wp_get_current_user();$base=plugin_dir_url(FILE).'assets/';
        $config=array('api'=>rest_url('bactive-cashier/v1/'),'nonce'=>wp_create_nonce('wp_rest'),'staff'=>array('id'=>$user->ID,'name'=>$user->display_name),'enabled'=>self::enabled(),'activeKey'=>$active?:'','training'=>wp_get_environment_type()!=='production','logoutUrl'=>html_entity_decode(wp_logout_url(home_url('/')),ENT_QUOTES,'UTF-8'));
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>B Active · In-store checkout</title><link rel="stylesheet" href="<?php echo esc_url($base.'cashier.css?ver='.VERSION); ?>"></head><body><div id="bactive-cashier"></div><script>window.BActiveCashier=<?php echo wp_json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;</script><script src="<?php echo esc_url($base.'qrcode.js?ver='.VERSION); ?>"></script><script src="<?php echo esc_url($base.'cashier.js?ver='.VERSION); ?>"></script></body></html><?php exit;
    }
    public static function admin_menu(): void {
        add_menu_page('B Active cashier','B Active cashier',self::CAP,'bactive-cashier',static function () { echo '<div class="wrap"><h1>B Active cashier</h1><p><a class="button button-primary" href="'.esc_url(add_query_arg('bactive_cashier','1',home_url('/'))).'">Open tablet checkout</a></p></div>'; },'dashicons-store',56);
        add_submenu_page('bactive-cashier','Cashier setup','Setup & daily reconciliation','manage_woocommerce','bactive-cashier-setup',array(self::class,'setup_page'));
    }
    public static function settings(): void {
        if (!current_user_can('manage_woocommerce')) wp_die('Forbidden',403);
        check_admin_referer('bactive_cashier_settings');
        update_option('bactive_cashier_enabled',isset($_POST['enabled'])&&self::ready()?'yes':'no',false);
        wp_safe_redirect(admin_url('admin.php?page=bactive-cashier-setup'));exit;
    }
    /** Manager-only recovery releases an unpaid claim only after authoritative payment closure. */
    public static function manager_resolve(): void {
        global $wpdb;
        if (!current_user_can('manage_woocommerce') || !current_user_can(self::CAP)) wp_die('Forbidden',403);
        check_admin_referer('bactive_cashier_resolve');
        $key=sanitize_text_field(wp_unslash($_POST['key']??''));
        $row=self::row($key);$locked=false;$creation=false;$order=null;$failure='';
        try {
            if (!$row || !self::ready()) throw new \RuntimeException('Sale or dependency unavailable.');
            $creation=Order_Lock::acquire_checkout('cashier-create-'.$key);
            if (!$creation) throw new \RuntimeException('Sale creation is still running. Retry later.');
            $row=self::row($key);
            if (!$row->order_id) {
                if (strtotime($row->created_at.' UTC')>time()-3600) throw new \RuntimeException('Wait one hour before releasing an interrupted order-creation claim. No payment can be collected for this claim.');
                $wpdb->update(self::table(),array('active_owner'=>null,'state'=>'abandoned'),array('sale_key'=>$key));
            } else {
                $id=(int)$row->order_id;$locked=Order_Lock::acquire($id);
                if (!$locked) throw new \RuntimeException('Order is being updated. Retry later.');
                $order=wc_get_order($id);
                if (!self::is_sale($order)||$order->is_paid()||$order->get_meta('_bactive_cashier_cash_state')) throw new \RuntimeException('Paid or cash-effect orders require manual reconciliation; this action cannot undo them.');
                if ($order->get_meta('_bactive_cashier_digital_started') || Gateway::has_protected_payment_state($order)) {
                    $gateway=WC()->payment_gateways()->payment_gateways()[self::DIGITAL]??null;
                    if (!$gateway instanceof Gateway||!$gateway->expire_all_for_order($order)) throw new \RuntimeException('PayMongo closure could not be verified. Keep this sale and its stock held.');
                    $order=wc_get_order($id);
                    if ($order->is_paid()||Gateway::has_provider_payment_evidence($order)||Gateway::has_outstanding_attempts($order)
                        ||Gateway::has_inconsistent_provider_payment_state($order)||Webhook::has_pending_reviews($id)
                        ||$order->get_meta(Reconciler::UNRESOLVED_META)||$order->get_meta('_bactive_paymongo_review_required')) throw new \RuntimeException('PayMongo payment/review state remains unresolved. Use the existing payment recovery process.');
                }
                if (!Order_Lock::renew($id)) throw new \RuntimeException('Order lock lost. Retry later.');
                $order->delete_meta_data('_bactive_cashier_review');$order->update_meta_data('_bactive_cashier_cancel_verified','yes');$order->save();
                $order->update_status('cancelled','Manager verified unpaid cancellation and released cashier claim.');
                Stock::release_verified_cancel($order);
                $wpdb->update(self::table(),array('active_owner'=>null,'state'=>'ready'),array('sale_key'=>$key));
            }
        } catch (\Throwable $e) {
            $failure=$e->getMessage();
        } finally {
            if ($locked&&$order instanceof \WC_Order) Order_Lock::release($order->get_id());
            if ($creation) Order_Lock::release_checkout();
        }
        if ($failure!=='') wp_die(esc_html($failure),'Cashier recovery',array('response'=>409,'back_link'=>true));
        wp_safe_redirect(admin_url('admin.php?page=bactive-cashier-setup'));exit;
    }
    public static function setup_page(): void {
        if (!current_user_can('manage_woocommerce')) return;
        ?><div class="wrap"><h1>B Active cashier setup</h1><p>Assign the B Active Sales Associate role to each approved staff account. Never share accounts.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="bactive_cashier_settings"><?php wp_nonce_field('bactive_cashier_settings'); ?><p><label><input type="checkbox" name="enabled" <?php checked(get_option('bactive_cashier_enabled'),'yes'); ?>> Enable new in-store sales after the release checks and staff training.</label></p><?php submit_button('Save cashier settings'); ?></form><h2>Daily reconciliation</h2><p>Compare these order totals with the cash collected and PayMongo records. Cash totals are net sales, excluding drawer float and payouts. PayMongo order totals are gross; reconcile fees and payouts in PayMongo.</p><?php
        $orders=wc_get_orders(array('created_via'=>self::VIA,'limit'=>100,'orderby'=>'date','order'=>'DESC'));
        echo '<table class="widefat"><thead><tr><th>Order</th><th>Associate</th><th>State</th><th>Total PHP</th><th>Payment</th><th>Invoice</th></tr></thead><tbody>';
        foreach ($orders as $order) { $staff=get_userdata((int)$order->get_meta('_bactive_cashier_associate'));echo '<tr><td><a href="'.esc_url($order->get_edit_order_url()).'">'.esc_html($order->get_order_number()).'</a></td><td>'.esc_html($staff?$staff->display_name:'Unknown').'</td><td>'.esc_html($order->get_status()).'</td><td>'.esc_html($order->get_total()).'</td><td>'.esc_html($order->get_payment_method_title()).'</td><td>'.esc_html($order->get_meta('_bactive_cashier_invoice')).'</td></tr>'; }
        echo '</tbody></table><p>Latest 100 in-store orders. Use WooCommerce orders for older records. Resolve pending/review sales before reconciling a shift.</p>';
        global $wpdb;
        $claims=$wpdb->get_results('SELECT * FROM '.self::table().' WHERE active_owner IS NOT NULL ORDER BY created_at');
        echo '<h2>Unfinished register sales</h2><p>Only use Cancel after confirming the customer is abandoning the sale. Digital cancellation independently verifies PayMongo closure. Cash already collected must be reconciled manually.</p>';
        foreach ($claims as $claim) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bactive_cashier_resolve"><input type="hidden" name="key" value="'.esc_attr($claim->sale_key).'">';
            wp_nonce_field('bactive_cashier_resolve');
            echo '<p>'.esc_html('Order '.($claim->order_id?:'not created').' · '.$claim->state.' · '.$claim->sale_key).' <button class="button">Verify unpaid cancellation and release sale</button></p></form>';
        }
        echo '</div>';
    }
    public static function order_details($order): void {
        if (!self::is_sale($order)) return;
        echo '<p><strong>In-store sale:</strong> '.esc_html($order->get_meta('_bactive_cashier_key')).'<br>Invoice: '.esc_html($order->get_meta('_bactive_cashier_invoice')).'<br>Cash received / change: '.esc_html($order->get_meta('_bactive_cashier_received')).' / '.esc_html($order->get_meta('_bactive_cashier_change')).'</p>';
    }
}
