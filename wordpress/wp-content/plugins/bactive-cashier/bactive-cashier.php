<?php
/**
 * Plugin Name: B Active Cashier
 * Description: Restricted in-store checkout using WooCommerce stock and the B Active PayMongo gateway.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, bactive-paymongo-hosted-checkout
 */
namespace BActive\Cashier;
defined('ABSPATH') || exit;
const VERSION = '1.0.0';
const FILE = __FILE__;
require_once __DIR__ . '/includes/class-plugin.php';
register_activation_hook(__FILE__, array(Plugin::class, 'install'));
add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', FILE, true);
    }
});
add_action('plugins_loaded', static function () {
    if (class_exists('WooCommerce')) {
        require_once __DIR__ . '/includes/class-cash-gateway.php';
        Plugin::boot();
    }
}, 30);
