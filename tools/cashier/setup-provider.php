<?php
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'cashier_fixture'
    || home_url() !== 'http://localhost:8097' || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Isolated cashier fixture required.');
}
wp_set_current_user(1);
$gateway = new \BActive\PayMongo\Gateway(false);
$fields = array('enabled' => '1',
    'title' => 'Synthetic payment (training only) ' . time(),
    'issuance_methods' => array('qrph', 'paymaya', 'shopee_pay', 'grab_pay'),
    'test_secret_key' => '', 'live_secret_key' => 'sk_live_synthetic_cashier_fixture_only');
$post = array();
foreach ($fields as $name => $value) {
    $post[$gateway->get_field_key($name)] = $value;
}
$gateway->set_post_data($post);
ob_start();
$gateway->process_admin_options();
$notice = ob_get_clean();
$gateway = new \BActive\PayMongo\Gateway(false);
if (!$gateway->is_available()) {
    throw new RuntimeException('Synthetic gateway is unavailable: ' . strip_tags($notice));
}
echo "Synthetic gateway ready; all provider calls are intercepted locally.\n";
