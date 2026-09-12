<?php
/**
 * Plugin Name: Bactive Brevo Marketing
 * Description: Consent-bound marketing events for B Active. Does not change WordPress email transport.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Text Domain: bactive-brevo
 */

namespace Bactive\Brevo;

defined('ABSPATH') || exit;

define('BACTIVE_BREVO_PLUGIN_FILE', __FILE__);
define('BACTIVE_BREVO_VERSION', '1.0.0');

foreach (['config', 'store', 'api', 'consent', 'automations', 'webhooks', 'frontend', 'coupon', 'admin'] as $component) {
    $path = __DIR__ . '/includes/' . $component . '.php';
    if (is_file($path)) {
        require_once $path;
    }
}

register_activation_hook(__FILE__, static function (): void {
    Store::install();
    // Activation never enables marketing or creates provider objects.
    if (class_exists(Coupon::class) && method_exists(Coupon::class, 'install')) {
        Coupon::install();
    }
});

register_deactivation_hook(__FILE__, static function (): void {
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('bactive_brevo_tick', [], 'bactive-brevo');
    }
    Store::pause();
});

add_action('before_woocommerce_init', static function (): void {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    Store::maybe_upgrade();
    Consent::register();
    Webhooks::register();
    Automations::register();
    foreach ([Frontend::class, Coupon::class, Admin::class] as $component) {
        if (class_exists($component) && method_exists($component, 'register')) {
            $component::register();
        }
    }
});
