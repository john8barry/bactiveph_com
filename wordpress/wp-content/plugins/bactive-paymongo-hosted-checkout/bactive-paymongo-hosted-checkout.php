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
require_once __DIR__ . '/includes/class-readiness.php';
require_once __DIR__ . '/includes/class-webhook.php';

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
        foreach (array('bacs', 'paymongo', 'paymongo_gcash', 'paymongo_paymaya', 'paymongo_hcp') as $legacy_id) {
            unset($gateways[$legacy_id]);
        }
        return $gateways;
    }, PHP_INT_MAX);

    add_action('woocommerce_api_bactive_paymongo_test', static function (): void {
        Webhook::handle(false);
    });
    add_action('woocommerce_api_bactive_paymongo_live', static function (): void {
        Webhook::handle(true);
    });

    add_action('admin_notices', __NAMESPACE__ . '\review_required_notice');
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

    $count = (int) get_option('bactive_paymongo_review_count', 0);
    if ($count < 1) {
        return;
    }

    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        esc_html(
            sprintf(
                /* translators: %d: number of quarantined payment events */
                _n(
                    '%d PayMongo payment event requires manual review. Open WooCommerce order notes before fulfillment.',
                    '%d PayMongo payment events require manual review. Open WooCommerce order notes before fulfillment.',
                    $count,
                    'bactive-paymongo'
                ),
                $count
            )
        )
    );
}
