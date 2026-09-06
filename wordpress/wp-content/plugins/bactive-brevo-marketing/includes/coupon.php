<?php

namespace Bactive\Brevo;

defined('ABSPATH') || exit;

/**
 * Eligibility for the public first-order offer. WooCommerce remains the only
 * owner of discount arithmetic, coupon usage counts and payment state.
 *
 * Claims have no clock-based expiry: a payable order must never lose its claim
 * simply because a local reservation timer elapsed. Keep this table and its
 * identity key on rollback. Disable the coupon and retain this guard while any
 * saved campaign order remains payable; order-pay does not reapply its coupon.
 */
final class Coupon
{
    public const CAMPAIGN_META = '_bactive_brevo_campaign';
    public const CAMPAIGN = 'bactive-first-order-v1';
    private const KEY_OPTION = 'bactive_brevo_coupon_identity_key';
    private const TABLE_SUFFIX = 'bactive_brevo_coupon_claims';
    private const HISTORY_LIMIT = 500;
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        add_filter('woocommerce_coupon_is_valid', array(self::class, 'validate_coupon'), 20, 3);
        add_action('woocommerce_after_checkout_validation', array(self::class, 'validate_checkout'), 20, 2);
        add_action('woocommerce_checkout_order_processed', array(self::class, 'classic_checkout'), 20, 3);
        add_action('woocommerce_store_api_checkout_order_processed', array(self::class, 'guard_order'), 20, 1);
        add_action('woocommerce_before_pay_action', array(self::class, 'before_pay'), 20, 1);
        add_action('woocommerce_order_status_changed', array(self::class, 'status_changed'), 20, 4);
        add_action('woocommerce_payment_complete', array(self::class, 'payment_complete'), 20, 1);
    }

    /** Read-only sending gate: never advertise a coupon without its guard. */
    public static function ready(): bool
    {
        global $wpdb;
        try {
            if (!self::$registered || !self::campaign_enabled()
                || get_option('woocommerce_enable_coupons', 'no') !== 'yes') {
                return false;
            }
            foreach (array(
                'woocommerce_coupon_is_valid' => 'validate_coupon',
                'woocommerce_after_checkout_validation' => 'validate_checkout',
                'woocommerce_checkout_order_processed' => 'classic_checkout',
                'woocommerce_store_api_checkout_order_processed' => 'guard_order',
                'woocommerce_before_pay_action' => 'before_pay',
                'woocommerce_order_status_changed' => 'status_changed',
                'woocommerce_payment_complete' => 'payment_complete',
            ) as $hook => $method) {
                if (has_filter($hook, array(self::class, $method)) === false) {
                    return false;
                }
            }
            $id = (int) Config::get('coupon_id', 0);
            $coupon = new \WC_Coupon($id);
            if ((int) $coupon->get_id() !== $id || $coupon->get_status() !== 'publish'
                || !self::native_settings_valid($coupon)
                || (string) $coupon->get_meta(self::CAMPAIGN_META, true) !== self::CAMPAIGN
                || (int) wc_get_coupon_id_by_code($coupon->get_code()) !== $id) {
                return false;
            }
            self::identity_key();
            $table = self::table();
            $wpdb->last_error = '';
            $engine = $wpdb->get_var($wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $table
            ));
            if ($wpdb->last_error !== '' || strtolower((string) $engine) !== 'innodb') {
                return false;
            }
            $wpdb->last_error = '';
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'PRIMARY'", ARRAY_A);
            if ($wpdb->last_error !== '' || !is_array($indexes) || count($indexes) !== 1
                || ($indexes[0]['Column_name'] ?? '') !== 'identity_hash'
                || (int) ($indexes[0]['Non_unique'] ?? 1) !== 0) {
                return false;
            }
            $wpdb->last_error = '';
            $rows = $wpdb->get_results("SELECT identity_hash, order_id, state, updated_at FROM {$table} LIMIT 0", ARRAY_A);
            return $wpdb->last_error === '' && is_array($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Install storage only. Does not create, publish or enable a coupon. */
    public static function install()
    {
        global $wpdb;
        try {
            $table = self::table();
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $wpdb->last_error = '';
            dbDelta("CREATE TABLE {$table} (
                identity_hash char(64) NOT NULL,
                order_id bigint(20) unsigned NOT NULL,
                state varchar(16) NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (identity_hash),
                KEY order_id (order_id)
            ) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
            if ($wpdb->last_error !== '') {
                return self::error('storage');
            }
            $wpdb->last_error = '';
            $engine = $wpdb->get_var($wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $table
            ));
            if ($wpdb->last_error !== '' || strtolower((string) $engine) !== 'innodb') {
                return self::error('storage');
            }
            if (get_option(self::KEY_OPTION, false) === false) {
                // add_option is atomic; a concurrent installer may win safely.
                add_option(self::KEY_OPTION, bin2hex(random_bytes(32)), '', false);
            }
            self::identity_key();
            return true;
        } catch (\Throwable $e) {
            return self::error('storage');
        }
    }

    /**
     * Explicit operator operation. Returns a DRAFT coupon ID. The release owner
     * must set Config coupon_id, verify the guard, then publish separately.
     */
    public static function provision()
    {
        if (!(defined('WP_CLI') && WP_CLI) && !current_user_can('manage_woocommerce')) {
            return self::error('permission');
        }
        if (!class_exists('WC_Coupon')) {
            return self::error('configuration');
        }
        try {
            return self::with_lock(static function () {
            global $wpdb;
            $code = self::code((string) Config::get('coupon_code', 'BACTIVE5'));
            if ($code === '' || strlen($code) > 64 || !preg_match('/^[a-z0-9_-]+$/D', $code)) {
                return self::error('configuration');
            }
            // Woo's normal code lookup searches published coupons only. Include
            // drafts here so a repeated explicit provisioning call is idempotent.
            $wpdb->last_error = '';
            $ids = get_posts(array(
                'post_type' => 'shop_coupon',
                'post_status' => array('publish', 'draft', 'pending', 'private', 'future', 'trash'),
                'title' => $code,
                'posts_per_page' => 2,
                'fields' => 'ids',
                'suppress_filters' => true,
            ));
            if ($wpdb->last_error !== '' || !is_array($ids) || count($ids) > 1) {
                return self::error('configuration');
            }
            if ($ids) {
                $existing = new \WC_Coupon((int) $ids[0]);
                // Never adopt or overwrite an unrelated coupon sharing the name.
                if ((string) $existing->get_meta(self::CAMPAIGN_META, true) !== self::CAMPAIGN
                    || !self::native_settings_valid($existing)) {
                    return self::error('configuration');
                }
                return (int) $existing->get_id();
            }
            $coupon = new \WC_Coupon();
            $coupon->set_props(array(
                'code' => $code,
                'status' => 'draft',
                'discount_type' => 'percent',
                'amount' => 5,
                'individual_use' => true,
                'usage_limit' => 0,
                'usage_limit_per_user' => 1,
                'limit_usage_to_x_items' => null,
                'free_shipping' => false,
                'exclude_sale_items' => false,
                'product_ids' => array(),
                'excluded_product_ids' => array(),
                'product_categories' => array(),
                'excluded_product_categories' => array(),
                'email_restrictions' => array(),
                'minimum_amount' => '',
                'maximum_amount' => '',
                'date_expires' => null,
                'description' => '5% first-order offer; requires the B Active eligibility guard.',
            ));
            $coupon->update_meta_data(self::CAMPAIGN_META, self::CAMPAIGN);
            $id = (int) $coupon->save();
            return $id > 0 ? $id : self::error('storage');
            });
        } catch (\Throwable $e) {
            return self::error('storage');
        }
    }

    public static function validate_coupon($valid, $coupon, $discounts)
    {
        if (!$valid || !self::is_configured_coupon($coupon)) {
            return $valid;
        }
        if (!Config::enabled() || !self::native_settings_valid($coupon)
            || (string) $coupon->get_meta(self::CAMPAIGN_META, true) !== self::CAMPAIGN) {
            throw new \Exception(self::error('configuration')->get_error_message());
        }
        $object = is_object($discounts) && is_callable(array($discounts, 'get_object'))
            ? $discounts->get_object() : null;
        $order = $object instanceof \WC_Order ? $object : null;
        $customer = !$order && function_exists('WC') && WC() ? WC()->customer : null;
        $email = $order ? $order->get_billing_email()
            : ($customer ? $customer->get_billing_email() : '');
        // A cart preview may precede email entry. Checkout always revalidates.
        if (!$order && trim((string) $email) === '') {
            return $valid;
        }
        if ($order && ($order->get_date_paid() || self::has_payment_evidence($order))) {
            // WC_Order::apply_coupon() must not change an already issued payment.
            throw new \Exception(self::error('payment')->get_error_message());
        }
        $result = self::preview((string) $email, $order ? (int) $order->get_customer_id() : get_current_user_id(), $order);
        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }
        return $valid;
    }

    public static function validate_checkout($data, $errors): void
    {
        if (!self::campaign_enabled() || !function_exists('WC') || !WC() || !WC()->cart
            || !self::contains_campaign(WC()->cart->get_applied_coupons())) {
            return;
        }
        $result = self::preview((string) ($data['billing_email'] ?? ''), get_current_user_id(), null);
        if (is_wp_error($result)) {
            $errors->add($result->get_error_code(), $result->get_error_message());
        }
    }

    public static function classic_checkout($order_id, $data, $order): void
    {
        self::guard_order($order);
    }

    public static function guard_order($order): void
    {
        $result = self::claim_order($order);
        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }
    }

    public static function before_pay($order): void
    {
        // This Woo hook runs outside its exception handler. A notice prevents
        // process_payment() through Woo's subsequent error-count check.
        $result = self::claim_order($order);
        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
        }
    }

    /**
     * Serialize just the local claim decision, never the provider request.
     * All accepted first orders participate, including full-price orders.
     * No order, price, native coupon counter or payment metadata is written.
     */
    public static function claim_order($order)
    {
        if (!self::campaign_enabled()) {
            // Marketing can be disabled after a discounted order was saved.
            // Woo's order-pay path does not run coupon validation again, so
            // already-saved campaign orders must fail closed during rollback.
            if ($order instanceof \WC_Order && self::contains_campaign($order->get_coupon_codes())) {
                return self::error('configuration');
            }
            return true;
        }
        if (!$order instanceof \WC_Order || (int) $order->get_id() <= 0) {
            return self::error('identity');
        }
        $coupon = new \WC_Coupon((int) Config::get('coupon_id', 0));
        $discounted = self::contains_campaign($order->get_coupon_codes());
        if (!self::native_settings_valid($coupon)
            || (string) $coupon->get_meta(self::CAMPAIGN_META, true) !== self::CAMPAIGN) {
            return $discounted ? self::error('configuration') : true;
        }
        // A provisioned draft is not an enabled campaign.
        if ($coupon->get_status() !== 'publish') {
            return $discounted ? self::error('configuration') : true;
        }
        try {
            $identity = self::identity((string) $order->get_billing_email(), (int) $order->get_customer_id());
            return self::with_lock(static function () use ($identity, $order, $discounted) {
                $claims = self::read_claims($identity['hashes'], true);
                $history = self::history($identity['customers'], (int) $order->get_id());
                $decision = self::decide($claims, $history, $order);
                if (is_wp_error($decision)) {
                    return $decision;
                }
                // Propagate linked aliases when a safely closed reservation is
                // replaced. A guest/account switch cannot fork one claim into two.
                $hashes = array_values(array_unique(array_merge($identity['hashes'], array_column($claims, 'identity_hash'))));
                self::write_claims($hashes, $decision['order_id'], $decision['state']);
                if ($discounted && $decision['order_id'] !== (int) $order->get_id()) {
                    return self::error($decision['state'] === 'consumed' ? 'used' : 'reserved');
                }
                if ($discounted && $decision['state'] === 'unknown') {
                    return self::error('payment');
                }
                return true;
            });
        } catch (\Throwable $e) {
            return self::error('storage');
        }
    }

    private static function preview(string $email, int $account_id, $order)
    {
        try {
            $identity = self::identity($email, $account_id);
            $claims = self::read_claims($identity['hashes'], false);
            $history = self::history($identity['customers'], $order ? (int) $order->get_id() : 0);
            $decision = self::decide($claims, $history, $order);
            if (is_wp_error($decision)) {
                return $decision;
            }
            if ($decision['state'] === 'unknown') {
                return self::error('payment');
            }
            if ($decision['order_id'] && (!$order || $decision['order_id'] !== (int) $order->get_id())) {
                return self::error($decision['state'] === 'consumed' ? 'used' : 'reserved');
            }
            return true;
        } catch (\Throwable $e) {
            return self::error('storage');
        }
    }

    private static function identity(string $email, int $account_id): array
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || sanitize_email($normalized) !== $normalized || !is_email($normalized)) {
            throw new \RuntimeException('identity');
        }
        $emails = array($normalized);
        $ids = $account_id > 0 ? array($account_id) : array();
        $matched = get_user_by('email', $normalized);
        if ($matched) {
            $ids[] = (int) $matched->ID;
        }
        foreach (array_unique($ids) as $id) {
            $user = get_userdata($id);
            if (!$user || !is_email($user->user_email)) {
                throw new \RuntimeException('identity');
            }
            $emails[] = strtolower(trim($user->user_email));
        }
        $emails = array_values(array_unique($emails));
        $ids = array_values(array_unique($ids));
        $aliases = array_map(static fn($value) => 'email:' . $value, $emails);
        foreach ($ids as $id) {
            $aliases[] = 'account:' . $id;
        }
        $key = self::identity_key();
        $hashes = array_map(static fn($alias) => hash_hmac('sha256', $alias, $key), $aliases);
        sort($hashes, SORT_STRING);
        return array('hashes' => $hashes, 'customers' => array_merge($emails, $ids));
    }

    private static function identity_key(): string
    {
        $key = get_option(self::KEY_OPTION, '');
        if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new \RuntimeException('storage');
        }
        return $key;
    }

    private static function history(array $customers, int $exclude_id): array
    {
        global $wpdb;
        $wpdb->last_error = '';
        $result = wc_get_orders(array(
            'type' => 'shop_order',
            'customer' => $customers,
            'status' => array_values(array_unique(array_merge(array_keys(wc_get_order_statuses()), array('wc-checkout-draft', 'trash')))),
            'exclude' => $exclude_id ? array($exclude_id) : array(),
            'limit' => self::HISTORY_LIMIT,
            'paginate' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));
        if ($wpdb->last_error !== '' || !is_object($result) || !isset($result->orders, $result->total)
            || !is_array($result->orders) || (int) $result->total > self::HISTORY_LIMIT
            || (int) $result->total !== count($result->orders)) {
            throw new \RuntimeException('history');
        }
        foreach ($result->orders as $order) {
            if (!$order instanceof \WC_Order || (int) $order->get_id() <= 0) {
                throw new \RuntimeException('history');
            }
        }
        return $result->orders;
    }

    private static function decide(array $claims, array $history, $current)
    {
        $candidates = array();
        $current_id = $current ? (int) $current->get_id() : 0;
        $owns_existing_claim = false;
        foreach ($claims as $claim) {
            $id = (int) $claim['order_id'];
            if ($id <= 0 || !in_array($claim['state'], array('reserved', 'consumed', 'unknown'), true)) {
                return self::error('storage');
            }
            if ($claim['state'] === 'consumed') {
                $candidates[$id] = 'consumed';
                $owns_existing_claim = $owns_existing_claim || $id === $current_id;
                continue;
            }
            $owner = wc_get_order($id);
            $state = $owner instanceof \WC_Order ? self::order_state($owner) : 'unknown';
            if ($state !== 'clear' && $state !== 'draft') {
                $candidates[$id] = $state;
                $owns_existing_claim = $owns_existing_claim || $id === $current_id;
            } elseif ($state === 'draft') {
                // Existing claims are durable even if an operator changes status.
                $candidates[$id] = 'unknown';
            }
        }
        foreach ($history as $order) {
            $id = (int) $order->get_id();
            if ($owns_existing_claim && $id > $current_id) {
                // Later orders do not invalidate the original reserved order's
                // retry. Earlier history and separately linked claims still do.
                continue;
            }
            $state = self::order_state($order);
            if (!in_array($state, array('clear', 'draft'), true)
                && ($candidates[$id] ?? '') !== 'consumed') {
                $candidates[$id] = $state;
            }
        }
        if ($current) {
            $current_state = self::order_state($current);
            // The current failed order may be retried only after safe closure.
            $candidates[$current_id] = ($candidates[$current_id] ?? '') === 'consumed'
                ? 'consumed' : ($current_state === 'clear' ? 'reserved' : $current_state);
        }
        if (!$candidates) {
            return array('order_id' => 0, 'state' => 'reserved');
        }
        // Consumed history wins even if refunded or deleted. Uncertain payment
        // history cannot be displaced by a newer clean order.
        foreach (array('consumed', 'unknown', 'reserved') as $priority) {
            $matching = array_keys(array_filter($candidates, static fn($state) => $state === $priority));
            if ($matching) {
                return array('order_id' => (int) min($matching), 'state' => $priority);
            }
        }
        return self::error('payment');
    }

    private static function order_state($order): string
    {
        $status = $order->get_status();
        if ($order->get_date_paid() || in_array($status, array('processing', 'completed', 'on-hold', 'refunded'), true)) {
            return 'consumed';
        }
        foreach (array('_bactive_paymongo_settlement_pending', '_bactive_paymongo_review_required') as $key) {
            if ($order->get_meta($key, true)) {
                return 'unknown';
            }
        }
        if ($status === 'pending') {
            return self::payment_protection($order) === 'unknown' ? 'unknown' : 'reserved';
        }
        if (in_array($status, array('checkout-draft', 'draft', 'auto-draft'), true)) {
            return self::has_payment_evidence($order) ? 'unknown' : 'draft';
        }
        if (in_array($status, array('failed', 'cancelled', 'trash'), true)) {
            $protection = self::payment_protection($order);
            if ($protection !== 'no_payment_evidence'
                || (string) $order->get_payment_method() === 'bactive_paymongo') {
                // The supported predicate is a mutation-protection boundary,
                // not proof that a failed/cancelled session is terminal/unpaid.
                return 'unknown';
            }
            /**
             * Read-only adapter contract; never expires, refunds or reconciles.
             * Return clear only when every attempt is terminal and unpaid with
             * no pending settlement or review. Missing/invalid result is unknown.
             */
            $state = apply_filters('bactive_brevo_coupon_payment_state', 'unknown', $order);
            return in_array($state, array('clear', 'pending', 'paid'), true)
                ? array('clear' => 'clear', 'pending' => 'reserved', 'paid' => 'consumed')[$state]
                : 'unknown';
        }
        return 'unknown';
    }

    private static function has_payment_evidence($order): bool
    {
        return self::payment_protection($order) !== 'no_payment_evidence';
    }

    /**
     * Read-only PayMongo contract verified at 4a92967. A true helper result is
     * protection, never a paid classifier. No provider call or gateway instance
     * is created. Legacy or malformed data cannot be hidden by helper filtering.
     */
    private static function payment_protection($order): string
    {
        try {
            foreach (array('paymongo_payment_intent_id', 'paymongo_client_key') as $key) {
                if ($order->meta_exists($key)) {
                    return 'unknown';
                }
            }
            $attempts = $order->get_meta('_bactive_paymongo_attempts', true);
            if ($attempts !== '' && $attempts !== null) {
                if (!is_array($attempts)) {
                    return 'unknown';
                }
                foreach ($attempts as $attempt) {
                    if (!is_array($attempt) || !$attempt
                        || !isset($attempt['mode']) || !in_array($attempt['mode'], array('test', 'live'), true)) {
                        return 'unknown';
                    }
                }
            }
            $evidence = !empty($attempts) || (string) $order->get_transaction_id() !== '';
            foreach ($order->get_meta_data() as $meta) {
                $data = $meta->get_data();
                if (is_string($data['key'] ?? null) && str_starts_with($data['key'], '_bactive_paymongo_')
                    && $data['value'] !== '' && $data['value'] !== null && $data['value'] !== array()) {
                    $evidence = true;
                }
            }
            if (is_callable(array('BActive\\PayMongo\\Gateway', 'has_protected_payment_state'))) {
                $protected = \BActive\PayMongo\Gateway::has_protected_payment_state($order);
                if ($protected === true) {
                    return 'protected';
                }
                return $protected === false && !$evidence ? 'no_payment_evidence' : 'unknown';
            }
            // No provider evidence means a normal COD/new pre-payment order can
            // still claim. A selected gateway name alone is not an issued session.
            return $evidence ? 'unknown' : 'no_payment_evidence';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    private static function read_claims(array $hashes, bool $for_update): array
    {
        global $wpdb;
        $table = self::table();
        $placeholders = implode(',', array_fill(0, count($hashes), '%s'));
        $wpdb->last_error = '';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT identity_hash, order_id, state FROM {$table} WHERE identity_hash IN ({$placeholders})" . ($for_update ? ' FOR UPDATE' : ''),
            ...$hashes
        ), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($rows)) {
            throw new \RuntimeException('storage');
        }
        if ($rows && $for_update) {
            $owners = array_values(array_unique(array_map('intval', array_column($rows, 'order_id'))));
            $owner_placeholders = implode(',', array_fill(0, count($owners), '%d'));
            $wpdb->last_error = '';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT identity_hash, order_id, state FROM {$table} WHERE order_id IN ({$owner_placeholders}) FOR UPDATE",
                ...$owners
            ), ARRAY_A);
            if ($wpdb->last_error !== '' || !is_array($rows) || count($rows) > self::HISTORY_LIMIT) {
                throw new \RuntimeException('storage');
            }
        }
        return $rows;
    }

    private static function write_claims(array $hashes, int $order_id, string $state): void
    {
        global $wpdb;
        if ($order_id <= 0 || !$hashes || !in_array($state, array('reserved', 'consumed', 'unknown'), true)) {
            throw new \RuntimeException('storage');
        }
        $table = self::table();
        $values = array();
        $params = array();
        foreach ($hashes as $hash) {
            $values[] = '(%s,%d,%s,UTC_TIMESTAMP())';
            array_push($params, $hash, $order_id, $state);
        }
        $wpdb->last_error = '';
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (identity_hash,order_id,state,updated_at) VALUES " . implode(',', $values)
                . ' ON DUPLICATE KEY UPDATE order_id=VALUES(order_id), state=VALUES(state), updated_at=VALUES(updated_at)',
            ...$params
        ));
        if ($result === false || $wpdb->last_error !== '') {
            throw new \RuntimeException('storage');
        }
    }

    private static function with_lock(callable $operation)
    {
        global $wpdb;
        $table = self::table();
        $database = defined('DB_NAME') ? DB_NAME : $wpdb->prefix;
        $lock_name = 'bactive_coupon_' . substr(hash('sha256', $database . '|' . $table), 0, 40);
        $locked = false;
        $transaction = false;
        try {
            $wpdb->last_error = '';
            $locked = (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock_name)) === '1';
            if (!$locked || $wpdb->last_error !== '') {
                return self::error('busy');
            }
            if ($wpdb->query('START TRANSACTION') === false) {
                return self::error('storage');
            }
            $transaction = true;
            $result = $operation();
            // Negative eligibility still commits a reservation for the earlier
            // order. Exceptions, storage failures and unknown partial effects do not.
            if ($wpdb->query('COMMIT') === false) {
                return self::error('storage');
            }
            $transaction = false;
            return $result;
        } catch (\Throwable $e) {
            return self::error('storage');
        } finally {
            if ($transaction) {
                $wpdb->query('ROLLBACK');
            }
            if ($locked) {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            }
        }
    }

    public static function status_changed($order_id, $from, $to, $order): void
    {
        try {
            if ((int) Config::get('coupon_id', 0) <= 0 || !$order instanceof \WC_Order
                || self::order_state($order) !== 'consumed') {
                return;
            }
            self::consume((int) $order_id);
        } catch (\Throwable $e) {
            error_log('B Active first-order claim consumption requires review.');
        }
    }

    public static function payment_complete($order_id): void
    {
        try {
            if ((int) Config::get('coupon_id', 0) <= 0) {
                return;
            }
            $order = wc_get_order($order_id);
            if ($order instanceof \WC_Order && self::order_state($order) === 'consumed') {
                self::consume((int) $order_id);
            }
        } catch (\Throwable $e) {
            error_log('B Active first-order claim consumption requires review.');
        }
    }

    private static function consume(int $order_id): void
    {
        $result = self::with_lock(static function () use ($order_id) {
            global $wpdb;
            $table = self::table();
            $wpdb->last_error = '';
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET state='consumed', updated_at=UTC_TIMESTAMP() WHERE order_id=%d AND state <> 'consumed'",
                $order_id
            ));
            if ($updated === false || $wpdb->last_error !== '') {
                throw new \RuntimeException('storage');
            }
            return true;
        });
        if (is_wp_error($result)) {
            // Never throw from a payment callback or disturb settlement. History
            // is rechecked at redemption, and unresolved claims remain blocking.
            error_log('B Active first-order claim consumption requires review.');
        }
    }

    private static function table(): string
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table) || strlen($table) > 64) {
            throw new \RuntimeException('storage');
        }
        return $table;
    }

    private static function campaign_enabled(): bool
    {
        return Config::enabled() && (int) Config::get('coupon_id', 0) > 0 && class_exists('WC_Coupon');
    }

    private static function is_configured_coupon($coupon): bool
    {
        $id = (int) Config::get('coupon_id', 0);
        return $id > 0 && $coupon instanceof \WC_Coupon && (int) $coupon->get_id() === $id;
    }

    private static function contains_campaign(array $codes): bool
    {
        $wanted = self::code((string) Config::get('coupon_code', 'BACTIVE5'));
        foreach ($codes as $code) {
            if (self::code((string) $code) === $wanted) {
                return true;
            }
        }
        return false;
    }

    private static function native_settings_valid($coupon): bool
    {
        return $coupon instanceof \WC_Coupon
            && $coupon->get_discount_type() === 'percent'
            && (float) $coupon->get_amount() === 5.0
            && $coupon->get_individual_use()
            && (int) $coupon->get_usage_limit() === 0
            && (int) $coupon->get_usage_limit_per_user() === 1
            && !$coupon->get_limit_usage_to_x_items()
            && !$coupon->get_free_shipping()
            && !$coupon->get_exclude_sale_items()
            && !$coupon->get_product_ids()
            && !$coupon->get_excluded_product_ids()
            && !$coupon->get_product_categories()
            && !$coupon->get_excluded_product_categories()
            && !$coupon->get_email_restrictions()
            && (float) $coupon->get_minimum_amount() === 0.0
            && (float) $coupon->get_maximum_amount() === 0.0
            && !$coupon->get_date_expires()
            && self::code($coupon->get_code()) === self::code((string) Config::get('coupon_code', 'BACTIVE5'));
    }

    private static function code(string $code): string
    {
        return strtolower(trim(wc_format_coupon_code($code)));
    }

    private static function error(string $reason): \WP_Error
    {
        $messages = array(
            'used' => 'This offer is available on your first order only.',
            'reserved' => 'This offer is reserved for an earlier order. Continue that order or contact us for help.',
            'identity' => 'Enter a valid billing email address to use this offer.',
            'payment' => 'We cannot verify this first-order offer yet. Please contact us for help.',
            'busy' => 'Another checkout is being verified. Please try again in a moment.',
            'permission' => 'You do not have permission to configure this offer.',
            'configuration' => 'This offer is not available right now.',
            'storage' => 'We could not verify your order. Please try again or contact us for help.',
        );
        return new \WP_Error('bactive_first_order_' . $reason, $messages[$reason] ?? $messages['storage']);
    }
}
