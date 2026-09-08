<?php

// Included by run.php after rollout-restriction.php; fixtures are synthetic.
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Integrity;
use BActive\PayMongo\Order_Lock;
use BActive\PayMongo\Readiness;
use BActive\PayMongo\Reconciler;
use BActive\PayMongo\Webhook;

$methods_gateway = rollout_test_setup();
same(Integrity::CHECKOUT_METHODS, $methods_gateway->issuance_methods(), 'missing methods setting preserves legacy five-method issuance');
$fake_mutating_settings_getter = true;
$methods_gateway = rollout_test_setup();
$fake_current_user_caps = array('manage_woocommerce');
same(true, $methods_gateway->is_available(), 'methods migration tolerates real Woo default-mutating getters');
check(!array_key_exists('issuance_methods', $methods_gateway->settings), 'method reads do not mutate missing stored settings');
$fake_mutating_settings_getter = false;

foreach (array(array(), '', null, false, 'qrph', array('card'), array('qrph', 'card'), array('QRPH'), array(array('qrph')), array('method' => 'qrph')) as $invalid_methods) {
    $methods_gateway = rollout_test_setup(array('issuance_methods' => $invalid_methods));
    $fake_current_user_caps = array('manage_woocommerce');
    $method_requests = 0;
    $fake_remote_handler = static function () use (&$method_requests): WP_Error {
        ++$method_requests;
        return new WP_Error('unexpected_methods_request', 'Invalid selection must not contact provider.');
    };
    $method_order_before = serialize($fake_orders[42]);
    same(array(), $methods_gateway->issuance_methods(), 'empty or malformed selected methods fail closed');
    same(false, $methods_gateway->is_available(), 'invalid selection hides gateway even for manager');
    same('fail', $methods_gateway->process_payment(42)['result'], 'invalid selection blocks direct issuance');
    same(false, Readiness::is_ready($methods_gateway, false), 'invalid selection cannot use cached readiness');
    same('paymongo_methods_empty', Readiness::verify_and_provision($methods_gateway, false)->get_error_code(), 'invalid selection cannot provision callbacks');
    same($method_order_before, serialize($fake_orders[42]), 'invalid selection leaves order unchanged');
    same(0, $method_requests, 'invalid selection never calls provider');
}

$methods_gateway = rollout_test_setup(array('issuance_methods' => array('shopee_pay', 'qrph', 'qrph', 'paymaya')), true);
same(array('qrph', 'paymaya', 'shopee_pay'), $methods_gateway->issuance_methods(), 'selected methods canonicalized and deduplicated');
same(array(), $methods_gateway->validate_issuance_methods_field('issuance_methods', null), 'unselected Woo multiselect persists explicit empty selection');
same(array(), $methods_gateway->validate_issuance_methods_field('issuance_methods', array('qrph', 'card')), 'malformed Woo submission does not silently retain a permitted subset');
check(str_contains($methods_gateway->description, 'QRPh, Maya, ShopeePay'), 'checkout description names selected methods');
check(!str_contains($methods_gateway->description, 'BPI') && !str_contains($methods_gateway->description, 'UBP'), 'checkout description omits deferred bank methods');
$method_payload = new ReflectionMethod(Gateway::class, 'checkout_payload');
$method_payload->setAccessible(true);
$method_payload_value = $method_payload->invoke($methods_gateway, $fake_orders[42], 12345, array_merge(Gateway::order_attempts($fake_orders[42])[0], array('generation' => 1)));
same(array('qrph', 'paymaya', 'shopee_pay'), $method_payload_value['data']['attributes']['payment_method_types'], 'provider payload uses only selected methods');

// A valid bank capability is unnecessary until selected, both for cached and
// independently refreshed live readiness. Selecting an unavailable rail closes it.
$fake_current_user_caps = array('manage_woocommerce');
$fake_options['bactive_paymongo_readiness_live']['capabilities'] = array('qrph', 'paymaya', 'shopee_pay');
same(true, $methods_gateway->is_available(), 'cached live readiness permits verified subset without banks');
$method_capabilities = array('qrph', 'paymaya', 'shopee_pay');
$method_requests = 0;
$fake_remote_handler = static function (string $url) use (&$method_capabilities, &$method_requests): array {
    ++$method_requests;
    if (str_ends_with($url, '/v1/merchants/capabilities/payment_methods')) {
        $data = array('attributes' => array('payment_methods' => $method_capabilities));
    } else {
        $data = array(array('id' => 'hook_rollout_fixture_123', 'attributes' => array(
            'url' => Readiness::endpoint_url(true), 'status' => 'enabled', 'livemode' => true,
            'events' => array('checkout_session.payment.paid'),
            'secret_key' => 'whsk_rollout_fixture_123456789',
        )));
    }
    return array('response' => array('code' => 200), 'body' => json_encode(array('data' => $data)));
};
same(true, Readiness::is_ready($methods_gateway, true, true), 'fresh live readiness accepts selected subset');
same(true, Readiness::verify_and_provision($methods_gateway, true), 'settings provisioning accepts selected subset');
$fake_options['woocommerce_bactive_paymongo_settings']['issuance_methods'][] = 'dob';
$methods_gateway_with_bank = new Gateway(false);
same(false, Readiness::is_ready($methods_gateway_with_bank, true), 'cached capabilities reject newly selected unavailable bank');
same(false, Readiness::is_ready($methods_gateway_with_bank, true, true), 'fresh capabilities reject newly selected unavailable bank');
same('paymongo_methods_inactive', Readiness::verify_and_provision($methods_gateway_with_bank, true)->get_error_code(), 'settings verification rejects unavailable selected bank');
same(false, $methods_gateway->is_available(), 'stale gateway detects methods drift without generation change');
same('fail', $methods_gateway->process_payment(42)['result'], 'stale gateway cannot issue after methods drift');

// Every selected method has its own live capability requirement and alias.
foreach (array('qrph' => 'qr_ph', 'paymaya' => 'maya', 'shopee_pay' => 'shopeepay', 'dob' => 'bpi', 'dob_ubp' => 'unionbank') as $method => $alias) {
    $methods_gateway = rollout_test_setup(array('restricted_rollout' => 'no', 'issuance_methods' => array($method)), true);
    $fake_options['bactive_paymongo_readiness_live']['capabilities'] = array($alias);
    same(true, $methods_gateway->is_available(), 'selected ' . $method . ' accepts its capability alias');
    $fake_options['bactive_paymongo_readiness_live']['capabilities'] = array('card');
    same(false, $methods_gateway->is_available(), 'selected ' . $method . ' requires its own capability');
}

foreach (array(array('qrph'), array('qrph', 'paymaya'), array()) as $new_methods) {
    rollout_test_setup(array('issuance_methods' => array('qrph', 'shopee_pay')));
    $old_methods_settings = $fake_options['woocommerce_bactive_paymongo_settings'];
    $new_methods_settings = $old_methods_settings;
    $new_methods_settings['issuance_methods'] = $new_methods;
    $filtered_methods = Gateway::filter_settings_update($new_methods_settings, $old_methods_settings);
    same($new_methods, $filtered_methods['issuance_methods'], 'empty drain accepts exact method selection');
    same(10, Reconciler::config_generation(), 'method change invalidates in-flight issuance generation');
    same(true, Reconciler::is_draining(), 'method change fences issuance before commit');
    check(Order_Lock::settings_write_active(), 'method change retains settings lease until readback');
    Order_Lock::release_settings();
}

// A prior QRPh payment still settles when new issuance selects only a bank,
// including anonymous callbacks and duplicate delivery. Selection is not an
// integrity allowlist for historical payments.
rollout_test_setup(array('issuance_methods' => array('dob_ubp')));
$historical_method_order = $fake_orders[42];
$historical_method_attempt = Gateway::order_attempts($historical_method_order)[0];
same('processed', Webhook::reconcile_checkout_session($historical_method_order, array('data' => session()), $historical_method_attempt, false), 'unselected historical method still reconciles');
same(true, $historical_method_order->paid, 'historical payment retains paid effect');
same('pay_test_payment_123', $historical_method_order->transaction_id, 'historical method retains exact transaction');
$historical_paid_snapshot = serialize($historical_method_order);
Webhook::reconcile_checkout_session($historical_method_order, array('data' => session()), $historical_method_attempt, false);
same($historical_paid_snapshot, serialize($historical_method_order), 'duplicate historical method settlement has no second order effect');

// Public sandbox must deny even a forged direct call with opt-out configured.
$methods_gateway = rollout_test_setup(array('restricted_rollout' => 'no', 'issuance_methods' => array('qrph')));
$method_requests = 0;
$fake_remote_handler = static function () use (&$method_requests): WP_Error { ++$method_requests; return new WP_Error('unexpected_public_test'); };
same('fail', $methods_gateway->process_payment(42)['result'], 'public sandbox direct call denied despite rollout opt-out');
same(0, $method_requests, 'public sandbox performs no provider calls');
$fake_current_user_caps = array('manage_woocommerce');
same(true, $methods_gateway->is_available(), 'private manager sandbox remains available');
$fake_current_user_caps = null;
$fake_remote_handler = null;

foreach (array(
    array(array(), false, false),
    array(array('restricted_rollout' => 'no'), false, false),
    array(array('restricted_rollout' => 'yes'), true, false),
    array(array('enabled' => 'no', 'restricted_rollout' => 'no'), true, false),
    array(array('restricted_rollout' => 'no', 'issuance_methods' => array()), true, false),
    array(array('restricted_rollout' => 'no', 'issuance_methods' => array('qrph')), true, true),
) as [$bank_settings, $bank_live, $hide_bank]) {
    rollout_test_setup($bank_settings, $bank_live);
    same($hide_bank, Gateway::public_live_issuance_configured(), 'manual bank transfer hidden only after configured public live issuance');
}
$fake_current_user_caps = null;
$fake_remote_handler = null;
