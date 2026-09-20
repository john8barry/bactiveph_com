<?php
/** Invoked only inside the disposable, network-isolated CLI fixture. */
if (!defined('BACTIVE_BREVO_TEST_FIXTURE') || BACTIVE_BREVO_TEST_FIXTURE !== true || !defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Explicit disposable CLI fixture required.');
}
if (getenv('BACTIVE_TEST_COUPON_ONLY') !== '1') require __DIR__ . '/backend-datastore.php';
require __DIR__ . '/coupon-datastore.php';
$backend = getenv('BACTIVE_TEST_COUPON_ONLY') === '1' ? ['status' => 'not_run_coupon_only'] : bactive_brevo_backend_integration_tests();
$coupon = bactive_brevo_coupon_datastore_checks(static function (array $settings): void {
    $current = get_option('bactive_brevo_settings', []);
    update_option('bactive_brevo_settings', array_merge(is_array($current) ? $current : [], $settings), false);
});
global $wp_version;
echo wp_json_encode([
    'wordpress' => $wp_version,
    'woocommerce' => WC_VERSION,
    'store' => get_option('woocommerce_custom_orders_table_enabled') === 'yes' ? 'hpos' : 'cpt',
    'backend' => $backend,
    'coupon' => $coupon,
]) . "\n";
