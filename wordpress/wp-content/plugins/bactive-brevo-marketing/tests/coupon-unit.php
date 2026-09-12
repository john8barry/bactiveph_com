<?php
/** Run: php tests/coupon-unit.php. No WordPress bootstrap or network access. */
namespace Bactive\Brevo {
    final class Config
    {
        public static array $settings = array();
        public static function enabled(): bool { return !empty(self::$settings['enabled']); }
        public static function get($key, $default = null) { return self::$settings[$key] ?? $default; }
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/not-a-wordpress-install/');
    define('ARRAY_A', 'ARRAY_A');
    define('DB_NAME', 'disposable_coupon_unit');

    final class WP_Error
    {
        public array $errors;
        public function __construct($code = '', $message = '') { $this->errors = $code ? array($code => $message) : array(); }
        public function get_error_code() { return array_key_first($this->errors); }
        public function get_error_message() { return reset($this->errors); }
        public function add($code, $message): void { $this->errors[$code] = $message; }
    }
    function is_wp_error($value): bool { return $value instanceof WP_Error; }
    function sanitize_email($value): string { return (string) filter_var($value, FILTER_SANITIZE_EMAIL); }
    function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
    function wc_format_coupon_code($code): string { return trim($code); }
    function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
    function add_option($key, $value, $unused = '', $autoload = null): bool {
        if (isset($GLOBALS['options'][$key])) { return false; }
        $GLOBALS['options'][$key] = $value;
        return true;
    }
    function get_userdata($id) { return $GLOBALS['users'][$id] ?? false; }
    function get_user_by($field, $value) {
        foreach ($GLOBALS['users'] as $user) {
            if (strtolower($user->user_email) === strtolower($value)) { return $user; }
        }
        return false;
    }
    function get_current_user_id(): int { return $GLOBALS['current_user']; }
    function current_user_can($capability): bool { return $GLOBALS['can_configure']; }
    function add_filter($name, $callback, $priority = 10, $args = 1): void { $GLOBALS['filters'][$name][] = $callback; }
    function add_action($name, $callback, $priority = 10, $args = 1): void { $GLOBALS['actions'][$name][] = $callback; }
    function has_filter($name, $callback) {
        return in_array($callback, array_merge($GLOBALS['filters'][$name] ?? array(), $GLOBALS['actions'][$name] ?? array()), true) ? 20 : false;
    }
    function apply_filters($name, $value, ...$args) {
        foreach ($GLOBALS['filters'][$name] ?? array() as $callback) { $value = $callback($value, ...$args); }
        return $value;
    }
    function wc_add_notice($message, $type): void { $GLOBALS['notices'][] = array($type, $message); }
    function WC() { return $GLOBALS['wc']; }
    function wc_get_order($id) { return $GLOBALS['orders'][$id] ?? false; }
    function get_posts($query): array {
        return array_keys(array_filter($GLOBALS['coupons'], static fn($coupon) => strtolower($coupon['props']['code']) === strtolower($query['title'])));
    }
    function wc_get_coupon_id_by_code($code): int {
        foreach ($GLOBALS['coupons'] as $id => $coupon) {
            if ($coupon['props']['status'] === 'publish' && strtolower($coupon['props']['code']) === strtolower($code)) { return $id; }
        }
        return 0;
    }
    function wc_get_order_statuses(): array {
        return array_fill_keys(array('wc-pending', 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-failed', 'wc-cancelled', 'wc-refunded'), '');
    }
    function wc_get_orders($query) {
        $GLOBALS['last_history_query'] = $query;
        if ($GLOBALS['history_error']) { $GLOBALS['wpdb']->last_error = 'fixture SQL failure'; }
        $orders = array_filter($GLOBALS['orders'], static function ($order) use ($query) {
            return !in_array($order->id, $query['exclude'], true)
                && (in_array(strtolower(trim($order->email)), $query['customer'], true)
                    || ($order->customer_id > 0 && in_array($order->customer_id, $query['customer'], true)));
        });
        return (object) array('orders' => array_values($orders), 'total' => count($orders));
    }

    final class WC_Coupon
    {
        public array $props = array();
        public array $meta = array();
        public int $id = 0;
        public function __construct($value = null) {
            foreach ($GLOBALS['coupons'] as $id => $data) {
                if ($value === $id || (is_string($value) && strtolower($data['props']['code']) === strtolower($value))) {
                    $this->id = $id; $this->props = $data['props']; $this->meta = $data['meta']; break;
                }
            }
        }
        public function get_id(): int { return $this->id; }
        public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
        public function meta_exists($key): bool { return array_key_exists($key, $this->meta); }
        public function get_meta_data(): array {
            $items = array();
            foreach ($this->meta as $key => $value) {
                $items[] = new class($key, $value) {
                    public function __construct(private $key, private $value) {}
                    public function get_data(): array { return array('key' => $this->key, 'value' => $this->value); }
                };
            }
            return $items;
        }
        public function update_meta_data($key, $value): void { $this->meta[$key] = $value; }
        public function set_props($props): void { $this->props = $props + $this->props; }
        public function save(): int {
            $this->id = $this->id ?: count($GLOBALS['coupons']) + 100;
            $GLOBALS['coupons'][$this->id] = array('props' => $this->props, 'meta' => $this->meta);
            $GLOBALS['coupon_saves']++;
            return $this->id;
        }
        public function __call($method, $args) {
            if (str_starts_with($method, 'get_')) { return $this->props[substr($method, 4)] ?? null; }
            throw new RuntimeException('Unexpected coupon mutation: ' . $method);
        }
    }
    final class WC_Order
    {
        public function __construct(
            public int $id, public string $email, public int $customer_id = 0,
            public string $status = 'pending', public array $coupons = array('BACTIVE5'),
            public string $gateway = 'cod', public $paid = null, public array $meta = array(),
            public string $transaction = ''
        ) {}
        public function get_id(): int { return $this->id; }
        public function get_billing_email(): string { return $this->email; }
        public function get_customer_id(): int { return $this->customer_id; }
        public function get_status(): string { return $this->status; }
        public function get_date_paid() { return $this->paid; }
        public function get_payment_method(): string { return $this->gateway; }
        public function get_transaction_id(): string { return $this->transaction; }
        public function get_coupon_codes(): array { return $this->coupons; }
        public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
        public function meta_exists($key): bool { return array_key_exists($key, $this->meta); }
        public function get_meta_data(): array {
            $items = array();
            foreach ($this->meta as $key => $value) {
                $items[] = new class($key, $value) {
                    public function __construct(private $key, private $value) {}
                    public function get_data(): array { return array('key' => $this->key, 'value' => $this->value); }
                };
            }
            return $items;
        }
        public function __call($method, $args) { throw new RuntimeException('Unexpected order/payment mutation: ' . $method); }
    }
    final class DiscountContext
    {
        public function __construct(private $object) {}
        public function get_object() { return $this->object; }
    }

    /**
     * SQL contract fake. It models exclusive claim ownership, transaction
     * rollback and a second connection contending while the first holds a lock.
     * Actual MySQL locking is covered by coupon-datastore.php in the WP fixture.
     */
    final class ClaimDatabase
    {
        public string $prefix = 'fixture_';
        public string $last_error = '';
        public array $claims = array();
        public array $statements = array();
        public bool $locked = false;
        public bool $force_busy = false;
        public bool $fail_read = false;
        public bool $fail_write = false;
        public bool $fail_commit = false;
        public string $engine = 'InnoDB';
        public array $indexes = array(array('Column_name' => 'identity_hash', 'Non_unique' => 0));
        public $interleave = null;
        private ?array $snapshot = null;
        public function prepare($sql, ...$args): array { return array('sql' => $sql, 'args' => $args); }
        public function get_var($query) {
            $sql = $query['sql'];
            $this->statements[] = $sql;
            if (str_contains($sql, 'SELECT ENGINE')) { return $this->engine; }
            if (str_contains($sql, 'GET_LOCK')) {
                if ($this->force_busy || $this->locked) { return '0'; }
                $this->locked = true; return '1';
            }
            if (str_contains($sql, 'RELEASE_LOCK')) { $this->locked = false; return '1'; }
            throw new RuntimeException('Unexpected SQL read');
        }
        public function get_results($query, $format): ?array {
            if ($this->fail_read) { $this->last_error = 'read failed'; return null; }
            if (is_string($query)) {
                $this->statements[] = $query;
                if (str_starts_with($query, 'SHOW INDEX')) { return $this->indexes; }
                if (str_ends_with($query, 'LIMIT 0')) { return array(); }
                throw new RuntimeException('Unexpected readiness SQL');
            }
            $column = str_contains($query['sql'], 'WHERE order_id IN') ? 'order_id' : 'identity_hash';
            $rows = array_values(array_filter($this->claims, static fn($row) => in_array($row[$column], $query['args'], true)));
            if ($this->interleave) { $fn = $this->interleave; $this->interleave = null; $fn(); }
            return $rows;
        }
        public function query($query) {
            if (is_string($query)) {
                if ($query === 'START TRANSACTION') { $this->snapshot = $this->claims; return 0; }
                if ($query === 'COMMIT') {
                    if ($this->fail_commit) { return false; }
                    $this->snapshot = null; return 0;
                }
                if ($query === 'ROLLBACK') { $this->claims = $this->snapshot ?? $this->claims; $this->snapshot = null; return 0; }
                throw new RuntimeException('Unexpected transaction');
            }
            if ($this->fail_write) { $this->last_error = 'write failed'; return false; }
            $this->statements[] = $query['sql'];
            if (str_starts_with($query['sql'], 'INSERT INTO')) {
                foreach (array_chunk($query['args'], 3) as [$hash, $id, $state]) {
                    $this->claims[$hash] = array('identity_hash' => $hash, 'order_id' => $id, 'state' => $state);
                }
                return 1;
            }
            if (str_starts_with($query['sql'], 'UPDATE')) {
                foreach ($this->claims as &$claim) {
                    if ($claim['order_id'] === $query['args'][0]) { $claim['state'] = 'consumed'; }
                }
                return 1;
            }
            throw new RuntimeException('Unexpected SQL mutation');
        }
    }

    require dirname(__DIR__) . '/includes/coupon.php';
    use Bactive\Brevo\Coupon;
    use Bactive\Brevo\Config;

    function reset_fixture(): void {
        $GLOBALS['wpdb'] = new ClaimDatabase();
        $GLOBALS['options'] = array('bactive_brevo_coupon_identity_key' => str_repeat('a', 64), 'woocommerce_enable_coupons' => 'yes');
        $GLOBALS['orders'] = $GLOBALS['users'] = $GLOBALS['coupons'] = $GLOBALS['filters'] = $GLOBALS['actions'] = $GLOBALS['notices'] = array();
        $GLOBALS['current_user'] = $GLOBALS['coupon_saves'] = 0;
        $GLOBALS['history_error'] = $GLOBALS['can_configure'] = false;
        $GLOBALS['wc'] = (object) array('customer' => new WC_Order(0, ''), 'cart' => null);
        Config::$settings = array('enabled' => true, 'coupon_id' => 7, 'coupon_code' => 'BACTIVE5');
        $props = array(
            'code' => 'bactive5', 'status' => 'publish', 'discount_type' => 'percent', 'amount' => '5',
            'individual_use' => true, 'usage_limit' => 0, 'usage_limit_per_user' => 1,
            'limit_usage_to_x_items' => null, 'free_shipping' => false, 'exclude_sale_items' => false,
            'product_ids' => array(), 'excluded_product_ids' => array(), 'product_categories' => array(),
            'excluded_product_categories' => array(), 'email_restrictions' => array(),
            'minimum_amount' => '', 'maximum_amount' => '', 'date_expires' => null,
        );
        $GLOBALS['coupons'][7] = array('props' => $props, 'meta' => array(Coupon::CAMPAIGN_META => Coupon::CAMPAIGN));
    }
    function order(int $id, string $email = 'first@example.test', array $overrides = array()): WC_Order {
        $order = new WC_Order($id, $email);
        foreach ($overrides as $key => $value) { $order->$key = $value; }
        $GLOBALS['orders'][$id] = $order;
        return $order;
    }
    function check($condition, string $message): void {
        if (!$condition) { throw new RuntimeException($message); }
        $GLOBALS['checks']++;
    }
    function error_is($result, string $reason): bool { return is_wp_error($result) && $result->get_error_code() === 'bactive_first_order_' . $reason; }
    $GLOBALS['checks'] = 0;

    reset_fixture();
    Config::$settings['enabled'] = false;
    $GLOBALS['wpdb']->fail_read = true;
    check(Coupon::claim_order(order(1, 'first@example.test', array('coupons' => array()))) === true && !$GLOBALS['wpdb']->statements, 'Disabled feature must not touch full-price checkout/storage.');
    check(error_is(Coupon::claim_order(order(2)), 'configuration'), 'Disabled integration must reject saved campaign-discounted orders.');
    Coupon::before_pay($GLOBALS['orders'][2]);
    check(count($GLOBALS['notices']) === 1, 'Disabled integration must block order-pay for a saved discounted order.');
    Config::$settings['enabled'] = true; Config::$settings['coupon_id'] = 0;
    check(Coupon::claim_order(order(1, 'first@example.test', array('coupons' => array()))) === true, 'Unconfigured campaign must not obstruct full-price checkout.');
    check(error_is(Coupon::claim_order(order(2)), 'configuration'), 'Cleared campaign binding cannot make a saved campaign discount payable.');

    reset_fixture();
    $first = order(1, '  First@EXAMPLE.TEST  ');
    check(Coupon::claim_order($first) === true, 'First guest order must qualify.');
    check(Coupon::claim_order($first) === true, 'Original order retry must be idempotent.');
    $second = order(2);
    check(error_is(Coupon::claim_order($second), 'reserved'), 'Same-email second pending order must fail.');
    check(count($GLOBALS['wpdb']->claims) === 1 && !str_contains(json_encode($GLOBALS['wpdb']->claims), '@'), 'Only keyed identity hashes belong in claims.');
    check($GLOBALS['last_history_query']['customer'] === array('first@example.test'), 'Normalized billing identity must drive HPOS query.');
    $first->status = 'completed'; $first->paid = 123;
    Coupon::status_changed(1, 'pending', 'completed', $first);
    Coupon::payment_complete(1); Coupon::payment_complete(1);
    check(array_values($GLOBALS['wpdb']->claims)[0]['state'] === 'consumed', 'Duplicate callbacks must leave one consumed claim.');
    unset($GLOBALS['orders'][1]);
    check(error_is(Coupon::claim_order($second), 'used'), 'Deleting a consumed order must not renew the offer.');
    check($GLOBALS['coupon_saves'] === 0, 'Redemption must never rewrite native coupon settings/counters.');

    foreach (array('processing', 'completed', 'on-hold', 'refunded') as $status) {
        reset_fixture(); order(1, 'first@example.test', array('status' => $status, 'coupons' => array()));
        add_filter('bactive_brevo_coupon_payment_state', static fn() => 'clear');
        check(error_is(Coupon::claim_order(order(2)), 'used'), 'Prior ' . $status . ' full-price order must disqualify.');
    }
    reset_fixture(); order(1, 'first@example.test', array('status' => 'cancelled', 'paid' => 123, 'coupons' => array()));
    check(error_is(Coupon::claim_order(order(2)), 'used'), 'A previously paid cancellation must disqualify.');

    reset_fixture();
    $GLOBALS['users'][10] = (object) array('ID' => 10, 'user_email' => 'login@example.test');
    $first = order(1, 'billing@example.test', array('customer_id' => 10));
    check(Coupon::claim_order($first) === true, 'Account first order must qualify.');
    check(error_is(Coupon::claim_order(order(2, 'billing@example.test')), 'reserved'), 'Guest cannot repeat account order using same billing email.');
    check(error_is(Coupon::claim_order(order(3, 'changed@example.test', array('customer_id' => 10))), 'reserved'), 'Account cannot bypass via changed billing email.');
    $first->status = 'completed'; Coupon::status_changed(1, 'pending', 'completed', $first);
    check(error_is(Coupon::claim_order(order(4, 'changed@example.test')), 'used'), 'New billing alias must stay linked for later guest checkout.');

    reset_fixture(); $first = order(1);
    check(Coupon::claim_order($first) === true, 'Guest initial claim succeeds.');
    $GLOBALS['users'][20] = (object) array('ID' => 20, 'user_email' => 'first@example.test');
    check(error_is(Coupon::claim_order(order(2, 'first@example.test', array('customer_id' => 20))), 'reserved'), 'Account creation cannot bypass guest claim.');

    reset_fixture(); $full_price = order(1, 'first@example.test', array('coupons' => array())); $discounted = order(2);
    check(error_is(Coupon::claim_order($discounted), 'reserved'), 'Concurrent full-price earlier order must win even when discounted request reaches guard first.');
    check(Coupon::claim_order($full_price) === true, 'Earlier full-price order proceeds.');
    check(array_values($GLOBALS['wpdb']->claims)[0]['order_id'] === 1, 'Both aliases converge on one first order.');

    reset_fixture(); $first = order(1); $second = order(2);
    $contender_result = null;
    $GLOBALS['wpdb']->interleave = static function () use ($second, &$contender_result) { $contender_result = Coupon::claim_order($second); };
    check(Coupon::claim_order($first) === true, 'First transaction survives competing request.');
    check(error_is($contender_result, 'busy'), 'Simulated second connection cannot enter held claim transaction.');
    check(!$GLOBALS['wpdb']->locked, 'Claim lock must be released.');
    check(error_is(Coupon::claim_order($second), 'reserved'), 'Contender remains ineligible after lock is released.');

    reset_fixture(); $first = order(1, 'first@example.test', array('gateway' => 'bactive_paymongo'));
    check(Coupon::claim_order($first) === true, 'New pending PayMongo order can reserve offer.');
    order(2, 'first@example.test', array('status' => 'completed', 'coupons' => array(), 'paid' => 123));
    check(Coupon::claim_order($first) === true, 'Later order must not invalidate original reserved order retry.');
    $first->status = 'failed';
    check(error_is(Coupon::claim_order($first), 'payment'), 'Unknown failed PayMongo recovery blocks same-order retry.');
    $GLOBALS['orders'] = array(1 => $first, 3 => new WC_Order(3, 'first@example.test'));
    check(error_is(Coupon::claim_order($GLOBALS['orders'][3]), 'reserved'), 'Unknown earlier PayMongo recovery must reserve identity.');
    add_filter('bactive_brevo_coupon_payment_state', static fn() => 'pending');
    check(error_is(Coupon::claim_order($GLOBALS['orders'][3]), 'reserved'), 'Outstanding provider session stays reserved.');
    $GLOBALS['filters']['bactive_brevo_coupon_payment_state'] = array(static fn() => 'clear');
    check(error_is(Coupon::claim_order($GLOBALS['orders'][3]), 'reserved'), 'An extension cannot clear protected modern gateway history.');
    $first->gateway = 'fixture_verified_gateway';
    check(Coupon::claim_order($GLOBALS['orders'][3]) === true, 'Explicit no-payment-evidence adapter permits a safe non-PayMongo retry.');
    $GLOBALS['orders'][3]->meta['_bactive_paymongo_review_required'] = 'review';
    check(error_is(Coupon::claim_order($GLOBALS['orders'][3]), 'payment'), 'Review hold cannot be overridden by clear adapter.');

    foreach (array(
        array('paymongo_client_key' => ''),
        array('paymongo_payment_intent_id' => ''),
        array('_bactive_paymongo_attempts' => 'malformed'),
        array('_bactive_paymongo_attempts' => array('malformed')),
        array('_bactive_paymongo_attempts' => array(array())),
        array('_bactive_paymongo_attempts' => array(array('mode' => 'live', 'session_id' => 'fixture'))),
    ) as $meta) {
        reset_fixture();
        $uncertain = order(1, 'first@example.test', array('meta' => $meta));
        check(error_is(Coupon::claim_order($uncertain), 'payment'), 'Legacy/malformed/unsupported payment evidence must block even COD pending order.');
    }

    foreach (array('fail_read', 'fail_write', 'fail_commit', 'force_busy') as $failure) {
        reset_fixture(); $GLOBALS['wpdb']->$failure = true;
        check(is_wp_error(Coupon::claim_order(order(1))), $failure . ' must fail closed.');
        check(!$GLOBALS['wpdb']->claims && !$GLOBALS['wpdb']->locked, $failure . ' must not leave partial claims or locks.');
    }
    reset_fixture(); $GLOBALS['history_error'] = true;
    check(error_is(Coupon::claim_order(order(1)), 'storage'), 'Order query SQL error must fail closed.');
    reset_fixture(); unset($GLOBALS['options']['bactive_brevo_coupon_identity_key']);
    check(error_is(Coupon::claim_order(order(1)), 'storage'), 'Missing identity key must never silently regenerate during checkout.');
    reset_fixture(); $GLOBALS['orders'][1] = new WC_Order(1, 'not-an-email');
    check(is_wp_error(Coupon::claim_order($GLOBALS['orders'][1])), 'Invalid billing email must fail closed.');

    reset_fixture(); $GLOBALS['coupons'][7]['props']['amount'] = 50;
    check(error_is(Coupon::claim_order(order(1)), 'configuration'), 'Modified percentage must fail closed for campaign redemption.');
    check(Coupon::claim_order(order(2, 'other@example.test', array('coupons' => array()))) === true, 'Malformed coupon must not obstruct unrelated full-price checkout.');
    reset_fixture(); $GLOBALS['coupons'][7]['meta'] = array();
    check(error_is(Coupon::claim_order(order(1)), 'configuration'), 'Missing campaign marker must fail closed.');
    $unrelated = new WC_Coupon(); $unrelated->id = 99;
    check(Coupon::validate_coupon(true, $unrelated, new DiscountContext(null)) === true, 'Other coupon IDs are untouched.');

    reset_fixture(); $first = order(1); $second = order(2);
    check(Coupon::claim_order($first) === true && error_is(Coupon::claim_order($second), 'reserved'), 'Rollback fixture contains a rejected second discounted order.');
    Config::$settings['enabled'] = false;
    check(error_is(Coupon::claim_order($first), 'configuration') && error_is(Coupon::claim_order($second), 'configuration'), 'Disabling integration keeps both original and rejected campaign orders blocked.');
    Config::$settings['enabled'] = true; $GLOBALS['coupons'][7]['props']['status'] = 'draft';
    Coupon::before_pay($second);
    check(count($GLOBALS['notices']) === 1, 'Unpublished campaign coupon blocks saved discounted order-pay.');
    check(Coupon::claim_order(order(3, 'other@example.test', array('coupons' => array()))) === true, 'Unpublished campaign does not obstruct unrelated full-price checkout.');

    reset_fixture(); order(1, 'first@example.test', array('status' => 'completed', 'paid' => 123));
    $pay = order(2); Coupon::before_pay($pay);
    check(count($GLOBALS['notices']) === 1, 'Order-pay failure must add notice rather than throw outside Woo exception handler.');
    reset_fixture(); $GLOBALS['can_configure'] = true; $GLOBALS['coupons'] = array();
    $id = Coupon::provision();
    check(is_int($id) && $GLOBALS['coupons'][$id]['props']['status'] === 'draft', 'Explicit provisioning only creates a draft coupon.');
    check($GLOBALS['coupons'][$id]['props']['amount'] === 5 && $GLOBALS['coupons'][$id]['props']['usage_limit_per_user'] === 1, 'Provisioned native coupon has 5 percent and one use per customer.');
    check(Coupon::provision() === $id && $GLOBALS['coupon_saves'] === 1, 'Provisioning is idempotent without editing an existing coupon.');
    $GLOBALS['can_configure'] = false;
    check(error_is(Coupon::provision(), 'permission'), 'Provision requires explicit operator permission.');
    reset_fixture(); Coupon::register();
    check($GLOBALS['coupon_saves'] === 0, 'Hook registration must not provision discounts.');
    check(isset($GLOBALS['actions']['woocommerce_store_api_checkout_order_processed']), 'Store API must have authoritative guard.');
    check(Coupon::ready() === true, 'Ready requires the enabled exact native coupon, registered guards and intact storage.');
    check(!$GLOBALS['wpdb']->claims && !$GLOBALS['wpdb']->locked && $GLOBALS['coupon_saves'] === 0, 'Readiness creates no claims, locks or coupons.');
    $GLOBALS['options']['woocommerce_enable_coupons'] = 'no';
    check(Coupon::ready() === false, 'WooCommerce coupons disabled must suppress advertised offers.');
    $GLOBALS['options']['woocommerce_enable_coupons'] = 'yes';
    $GLOBALS['wpdb']->engine = 'MyISAM';
    check(Coupon::ready() === false, 'Nontransactional claim storage must not be ready.');
    $GLOBALS['wpdb']->engine = 'InnoDB'; $GLOBALS['wpdb']->indexes = array();
    check(Coupon::ready() === false, 'Missing unique identity key must not be ready.');
    $GLOBALS['wpdb']->indexes = array(array('Column_name' => 'identity_hash', 'Non_unique' => 0));
    $GLOBALS['wpdb']->fail_read = true;
    check(Coupon::ready() === false, 'Readiness fails closed on query error.');
    $GLOBALS['wpdb']->fail_read = false;
    $GLOBALS['coupons'][7]['props']['status'] = 'draft';
    check(Coupon::ready() === false, 'A draft coupon must never be advertised as ready.');
    $GLOBALS['coupons'][7]['props']['status'] = 'publish';
    $GLOBALS['coupons'][7]['props']['usage_limit_per_user'] = 2;
    check(Coupon::ready() === false, 'Changed native restrictions must suppress advertised offers.');
    $GLOBALS['coupons'][7]['props']['usage_limit_per_user'] = 1;
    unset($GLOBALS['actions']['woocommerce_before_pay_action']);
    check(Coupon::ready() === false, 'A removed payment guard must suppress advertised offers.');

    echo 'PASS coupon-unit: ' . $GLOBALS['checks'] . " assertions; simulated locking only, real Woo/MySQL fixture remains required.\n";
}
