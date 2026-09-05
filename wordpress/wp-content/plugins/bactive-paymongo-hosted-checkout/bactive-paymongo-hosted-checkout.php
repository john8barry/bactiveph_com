<?php
/**
 * Plugin Name: B Active PayMongo Hosted Checkout
 * Description: Production-hardened PayMongo Hosted Checkout for B Active WooCommerce orders.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: B Active
 * License: GPL-2.0-or-later
 * Text Domain: bactive-paymongo
 */

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

const VERSION = '1.0.0';
const GATEWAY_ID = 'bactive_paymongo';
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/includes/class-integrity.php';
require_once __DIR__ . '/includes/class-secrets.php';
require_once __DIR__ . '/includes/class-api-client.php';
require_once __DIR__ . '/includes/class-order-lock.php';
require_once __DIR__ . '/includes/class-readiness.php';
require_once __DIR__ . '/includes/class-webhook.php';
require_once __DIR__ . '/includes/class-reconciler.php';

add_action('bactive_paymongo_availability_changed', __NAMESPACE__ . '\\purge_public_payment_cache');
add_filter('cron_schedules', array(Reconciler::class, 'cron_schedules'));
register_activation_hook(PLUGIN_FILE, array(Reconciler::class, 'ensure_scheduled'));
register_deactivation_hook(PLUGIN_FILE, array(Reconciler::class, 'guard_deactivation'));
add_action('update_option', array(Reconciler::class, 'guard_plugin_list_update'), PHP_INT_MAX, 3);
add_filter('pre_update_site_option_active_sitewide_plugins', array(Reconciler::class, 'guard_network_plugin_list_update'), PHP_INT_MAX, 2);
add_action('updated_option', array(Reconciler::class, 'after_plugin_list_update'), PHP_INT_MAX, 2);
add_action('update_site_option', array(Reconciler::class, 'after_plugin_list_update'), PHP_INT_MAX, 2);

add_action('before_woocommerce_init', static function (): void {
    if (!class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'custom_order_tables',
        PLUGIN_FILE,
        true
    );
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'cart_checkout_blocks',
        PLUGIN_FILE,
        false
    );
});

add_action('plugins_loaded', __NAMESPACE__ . '\bootstrap', 20);

function bootstrap(): void
{
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', __NAMESPACE__ . '\woocommerce_required_notice');
        return;
    }

    require_once __DIR__ . '/includes/class-gateway.php';

    add_filter('woocommerce_payment_gateways', static function (array $gateways): array {
        $gateways[] = Gateway::class;
        return $gateways;
    });
    add_filter('woocommerce_available_payment_gateways', static function (array $gateways): array {
        foreach (array(
            'bacs',
            'paymongo',
            'paymongo_hcp',
            'paymongo_card',
            'paymongo_card_installment',
            'paymongo_gcash',
            'paymongo_grab_pay',
            'paymongo_paymaya',
            'paymongo_maya',
            'paymongo_shopee_pay',
            'paymongo_shopeepay',
            'paymongo_qrph',
            'paymongo_atome',
            'paymongo_bpi',
            'paymongo_unionbank',
            'paymongo_ubp',
            'paymongo_billease',
        ) as $legacy_id) {
            unset($gateways[$legacy_id]);
        }

        // Never let WooCommerce's order-pay endpoint switch an existing
        // PayMongo order to COD without rebuilding cart totals and the COD fee.
        if (function_exists('is_wc_endpoint_url')
            && is_wc_endpoint_url('order-pay')
            && function_exists('get_query_var')) {
            $order_id = absint(get_query_var('order-pay'));
            $order = $order_id > 0 ? wc_get_order($order_id) : null;
            if ($order instanceof \WC_Order && Gateway::has_protected_payment_state($order)) {
                foreach (array_keys($gateways) as $gateway_id) {
                    if ($gateway_id !== GATEWAY_ID) {
                        unset($gateways[$gateway_id]);
                    }
                }
            }
        }
        return $gateways;
    }, PHP_INT_MAX);

    // One persistent instance retains lock ownership from a before-save guard
    // through the matching data-store write and classic-checkout lifecycle.
    $lifecycle_gateway = new Gateway(false);
    // Run after ordinary order-mutating hooks so the database fence validates
    // the final object that WooCommerce is about to hand to its data store.
    add_action('woocommerce_before_order_object_save', array($lifecycle_gateway, 'handle_order_before_save'), PHP_INT_MAX, 2);
    add_action('woocommerce_after_order_object_save', array($lifecycle_gateway, 'handle_order_after_save'), PHP_INT_MAX, 2);
    add_action('woocommerce_before_checkout_process', array($lifecycle_gateway, 'acquire_checkout_submission_lock'), PHP_INT_MIN, 0);
    add_action('woocommerce_after_checkout_validation', array($lifecycle_gateway, 'guard_checkout_submission'), PHP_INT_MAX, 2);
    add_action('woocommerce_checkout_create_order', array($lifecycle_gateway, 'handle_checkout_create_order'), 1, 2);
    add_action('woocommerce_checkout_order_created', array($lifecycle_gateway, 'finalize_checkout_lock'), PHP_INT_MAX, 1);
    add_action('woocommerce_checkout_order_exception', array($lifecycle_gateway, 'release_checkout_lock'), PHP_INT_MAX, 1);
    add_action('woocommerce_checkout_order_exception', array($lifecycle_gateway, 'release_checkout_submission_lock'), PHP_INT_MAX, 0);
    add_action('shutdown', array($lifecycle_gateway, 'release_request_locks'), PHP_INT_MAX);
    add_action('shutdown', array(Order_Lock::class, 'release_settings'), PHP_INT_MAX);

    // wc_create_refund fires this before the refund child is persisted,
    // payment/refund APIs are called, stock is restored, or parent status is
    // changed. Version 1.0.0 records provider refunds as private notes only.
    add_action('woocommerce_create_refund', array($lifecycle_gateway, 'guard_refund_creation'), 1, 2);

    add_filter(
        'pre_update_option_woocommerce_' . GATEWAY_ID . '_settings',
        array(Gateway::class, 'filter_settings_update'),
        10,
        2
    );
    add_action(
        'update_option_woocommerce_' . GATEWAY_ID . '_settings',
        array(Gateway::class, 'after_settings_update'),
        10,
        2
    );
    add_action('update_option', array(Gateway::class, 'guard_settings_update_commit'), PHP_INT_MIN, 3);
    add_action(
        'add_option_woocommerce_' . GATEWAY_ID . '_settings',
        array(Gateway::class, 'after_settings_add'),
        10,
        2
    );
    add_action('add_option', array(Gateway::class, 'guard_settings_add'), PHP_INT_MIN, 2);
    add_action('delete_option', array(Gateway::class, 'guard_settings_delete'), PHP_INT_MIN, 1);
    add_action('add_option', array(Secrets::class, 'guard_webhook_secret_write'), PHP_INT_MIN, 2);
    add_action('update_option', array(Secrets::class, 'guard_webhook_secret_update'), PHP_INT_MIN, 3);
    add_action('delete_option', array(Secrets::class, 'guard_webhook_secret_delete'), PHP_INT_MIN, 1);
    add_action(
        'delete_option_woocommerce_' . GATEWAY_ID . '_settings',
        array(Gateway::class, 'after_settings_delete'),
        PHP_INT_MAX,
        1
    );

    add_filter('woocommerce_pre_delete_order', array($lifecycle_gateway, 'guard_order_deletion'), 1, 3);
    add_action('woocommerce_before_delete_order', array($lifecycle_gateway, 'block_unsafe_delete_action'), 1, 2);
    add_action('woocommerce_before_trash_order', array($lifecycle_gateway, 'block_unsafe_delete_action'), 1, 2);
    add_action('woocommerce_delete_order', array($lifecycle_gateway, 'release_delete_lock'), PHP_INT_MAX, 1);
    add_action('woocommerce_trash_order', array($lifecycle_gateway, 'release_delete_lock'), PHP_INT_MAX, 1);

    add_filter('woocommerce_order_actions', array(Reconciler::class, 'order_actions'), 10, 2);
    add_action('woocommerce_order_action_bactive_paymongo_resolve_review', array(Reconciler::class, 'resolve_review'));
    add_action(
        'woocommerce_order_action_bactive_paymongo_finalize_resolved_payment',
        array(Reconciler::class, 'finalize_resolved_payment')
    );
    add_action(
        'woocommerce_order_action_bactive_paymongo_resolve_effects_ambiguity',
        array(Reconciler::class, 'resolve_effects_ambiguity')
    );

    add_filter('auto_update_plugin', static function ($update, $item) {
        $plugin = is_object($item) ? (string) ($item->plugin ?? '') : '';
        return $plugin === plugin_basename(PLUGIN_FILE) ? false : $update;
    }, 10, 2);

    add_action('woocommerce_api_bactive_paymongo_test', static function (): void {
        Webhook::handle(false);
    });
    add_action('woocommerce_api_bactive_paymongo_live', static function (): void {
        Webhook::handle(true);
    });
    add_action('woocommerce_api_bactive_paymongo_cancel', static function (): void {
        (new Gateway(false))->handle_cancel_request();
    });
    add_action(Reconciler::CRON_HOOK, array(Reconciler::class, 'run'));
    add_action(Reconciler::ORDER_HOOK, array(Reconciler::class, 'run_order'), 10, 1);
    Reconciler::ensure_scheduled();

    add_action('wp', __NAMESPACE__ . '\replace_legacy_checkout_reassurance');
    add_action('admin_notices', __NAMESPACE__ . '\review_required_notice');
}

add_action('init', static function (): void {
    $route = isset($_GET['wc-api']) ? sanitize_key(wp_unslash((string) $_GET['wc-api'])) : '';
    if (!in_array($route, array(
        'bactive_paymongo_test',
        'bactive_paymongo_live',
        'bactive_paymongo_cancel',
    ), true)) {
        return;
    }
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    if (!defined('LSCACHE_NO_CACHE')) {
        define('LSCACHE_NO_CACHE', true);
    }
    if (function_exists('nocache_headers')) {
        nocache_headers();
    }
    do_action('litespeed_control_set_nocache', 'PayMongo callback');
}, 0);

function replace_legacy_checkout_reassurance(): void
{
    remove_action('woocommerce_review_order_after_submit', 'bactive_checkout_reassurance', 10);
    add_action('woocommerce_review_order_after_submit', __NAMESPACE__ . '\checkout_reassurance', 10);
}

function checkout_reassurance(): void
{
    $parts = array();
    if (function_exists('WC') && WC() && is_callable(array(WC(), 'payment_gateways'))) {
        try {
            $available = WC()->payment_gateways()->get_available_payment_gateways();
        } catch (\Throwable $error) {
            $available = array();
        }
        if (isset($available[GATEWAY_ID])) {
            $parts[] = __('PayMongo: QRPh, Maya, ShopeePay, BPI Direct Debit & UBP Direct Debit', 'bactive-paymongo');
        }
        if (isset($available['cod'])) {
            $parts[] = __('COD', 'bactive-paymongo');
        }
    }
    $parts[] = __('7-day size-exchange guarantee', 'bactive-paymongo');

    echo '<div style="text-align:center; font-size:13px; margin-top:20px; color:#2B2A28;">'
        . esc_html(__('Secure checkout', 'bactive-paymongo') . ' · ' . implode(' · ', $parts))
        . '</div>';
}

/**
 * Payment claims must not survive an enable/disable/readiness transition in a
 * full-page or persistent object cache. Host/CDN purges are still verified at
 * cutover because an origin hook cannot prove an external edge accepted it.
 */
function purge_public_payment_cache(): void
{
    try {
        do_action('litespeed_purge_all');
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    } catch (\Throwable $error) {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log(
                'warning',
                wp_json_encode(array('code' => 'payment_cache_purge_failed')),
                array('source' => 'bactive-paymongo')
            );
        }
    }
    do_action('bactive_paymongo_after_public_cache_purge');
}

function woocommerce_required_notice(): void
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-error"><p>'
        . esc_html__('B Active PayMongo requires WooCommerce to be active.', 'bactive-paymongo')
        . '</p></div>';
}

function review_required_notice(): void
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    if (!Reconciler::has_review_state()) {
        return;
    }

    echo '<div class="notice notice-error"><p>'
        . esc_html__('PayMongo has payment state requiring manual review. Open WooCommerce order notes before fulfillment; checkout remains closed while a drain alarm is active.', 'bactive-paymongo')
        . '</p></div>';
}
