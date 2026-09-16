<?php
namespace BActive\Cashier;

use Automattic\WooCommerce\Checkout\Helpers\ReserveStock;

defined('ABSPATH') || exit;

/** Keep a cashier hold effective until stock effects or cancellation are verified. */
final class Stock {
    const SENTINEL = '9999-12-31 23:59:59';

    public static function boot(): void {
        add_filter('woocommerce_query_for_reserved_stock', array(self::class, 'reservation_query'), 100, 3);
        foreach (array('woocommerce_payment_complete', 'woocommerce_order_status_cancelled', 'woocommerce_order_status_completed', 'woocommerce_order_status_processing', 'woocommerce_order_status_on-hold') as $hook) {
            remove_action($hook, 'wc_release_stock_for_order', 11);
            add_action($hook, array(self::class, 'maybe_release'), 11);
        }
        remove_action('woocommerce_checkout_order_exception', 'wc_release_stock_for_order', 10);
        add_action('woocommerce_checkout_order_exception', array(self::class, 'maybe_release'), 10);
        register_deactivation_hook(FILE, array(self::class, 'guard_deactivation'));
    }

    /**
     * Woo normally counts only pending orders. A payment status can be persisted
     * before stock hooks finish, so retain the existing query (and its row locks)
     * while extending only its status predicate. OR counts each hold once.
     */
    public static function reservation_query(string $query, $product_id, $exclude_order_id): string {
        $table = Plugin::table();
        $pattern = "/WHERE\s+((?:orders\.status|posts\.post_status)\s+IN\s*\(\s*'wc-checkout-draft'\s*,\s*'wc-pending'\s*\))/i";
        $replacement = "WHERE ($1 OR (stock_table.expires = '" . self::SENTINEL . "' AND EXISTS (SELECT 1 FROM {$table} cashier_hold WHERE cashier_hold.order_id = stock_table.order_id)))";
        $result = preg_replace($pattern, $replacement, $query, 1, $count);
        if ($count !== 1 || !is_string($result)) {
            // A Woo query-shape change must be reviewed before allowing stock sales.
            throw new \RuntimeException('The cashier inventory hold query needs a compatibility review.');
        }
        return $result;
    }

    public static function maybe_release($order): void {
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order);
        if (!$order) return;
        if (!Plugin::is_sale($order)) {
            wc_release_stock_for_order($order);
            return;
        }
        // Fresh storage, rather than the status-hook object's in-memory fields.
        $fresh = wc_get_order($order->get_id());
        if (!$fresh instanceof \WC_Order) return;
        $fresh->read_meta_data(true);
        if (Plugin::stock_done($fresh)) {
            (new ReserveStock())->release_stock_for_order($fresh);
        }
        // Cancellation status alone never proves that a PayMongo URL is unpayable.
        // The manager's verified cancellation path calls release_verified_cancel.
    }

    /** Caller must hold the order fence and verify every payment attempt closed. */
    public static function release_verified_cancel(\WC_Order $order): void {
        if (!Plugin::is_sale($order) || !$order->has_status('cancelled')
            || !\BActive\PayMongo\Order_Lock::held_by_request($order->get_id())) {
            throw new \RuntimeException('Verified cancellation requires the cashier order lock.');
        }
        (new ReserveStock())->release_stock_for_order($order);
    }

    public static function guard_deactivation(): void {
        global $wpdb;
        $table = Plugin::table();
        $active = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active_owner IS NOT NULL");
        $held = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->wc_reserved_stock} stock_table INNER JOIN {$table} cashier_hold ON cashier_hold.order_id = stock_table.order_id WHERE stock_table.expires=%s", self::SENTINEL));
        if ($active === null || $held === null || (int)$active > 0 || (int)$held > 0) {
            wp_die('Pause new cashier sales in Setup, then resolve all active sales and stock holds before deactivating B Active Cashier. Payment callbacks and inventory protection must remain running.', 'Cashier has unresolved sales', array('response' => 409));
        }
    }
}
