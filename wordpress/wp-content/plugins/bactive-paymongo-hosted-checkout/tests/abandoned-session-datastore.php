<?php

// Included only by the identity-guarded disposable native datastore fixture.
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Order_Lock;
use BActive\PayMongo\Reconciler;

$expiry_order = wc_create_order(array('created_via' => 'disposable-paymongo-integration'));
$expiry_order->set_payment_method('bactive_paymongo');
$expiry_order->set_currency('PHP');
$expiry_order->set_total('123.45');
$expiry_attempt = array('session_id' => 'cs_fixture_expiry_native_123', 'mode' => 'test',
    'created_at' => time() - 90000, 'reference' => 'BA-fixture-expiry-native',
    'correlation_id' => 'fixture-native-expiry', 'generation' => 1,
    'config_generation' => Reconciler::config_generation());
$expiry_order->update_meta_data('_bactive_paymongo_attempts', array($expiry_attempt));
Reconciler::mark_required($expiry_order);
$expiry_order->save();
$expiry_id = $expiry_order->get_id();
$expiry_url = 'https://api.paymongo.com/v1/checkout_sessions/' . $expiry_attempt['session_id'];
$expiry_response = array('data' => array('id' => $expiry_attempt['session_id'],
    'type' => 'checkout_session', 'attributes' => array('status' => 'active',
        'livemode' => false, 'payments' => array())));
$expiry_calls = array();
$expiry_http = static function ($pre, array $args, string $url) use (&$expiry_calls, &$expiry_response, $expiry_url) {
    if ($url !== $expiry_url && $url !== $expiry_url . '/expire') {
        return $pre;
    }
    $expiry_calls[] = array($args['method'] ?? 'GET', $url);
    return array('headers' => array(), 'body' => wp_json_encode($expiry_response),
        'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array());
};
add_filter('pre_http_request', $expiry_http, PHP_INT_MAX, 3);
try {
    // Represent an existing historical hold through the native incident writer.
    $assert(Order_Lock::acquire($expiry_id), 'Native expiry fixture order lock unavailable.');
    $expiry_flag = new ReflectionMethod(Reconciler::class, 'flag_failure');
    $expiry_flag->invoke(null, $expiry_order, 'reconciliation_abandoned_expiry_failed', 'test');
    Order_Lock::release($expiry_id);
    $review_key = Reconciler::review_incident_option($expiry_id, 'reconciliation_abandoned_expiry_failed', 'test');
    $review_before = get_option($review_key);
    $effects_before = did_action('woocommerce_payment_complete');
    for ($poll = 0; $poll < 3; ++$poll) {
        Reconciler::run_order($expiry_id);
    }
    $expiry_order = wc_get_order($expiry_id);
    $assert($expiry_calls === array_fill(0, 3, array('GET', $expiry_url)), 'Native held recovery repeated an expiry POST.');
    $assert(Gateway::has_outstanding_attempts($expiry_order), 'Native held polling lost the outstanding attempt.');
    $assert(get_option($review_key) === $review_before, 'Native held polling replaced incident evidence.');
    $assert((int) $expiry_order->get_meta('_bactive_paymongo_review_incidents', true) === 1, 'Native held polling duplicated incidents.');
    $assert(as_has_scheduled_action(Reconciler::ORDER_HOOK, array($expiry_id), 'bactive-paymongo'), 'Native held polling lost its scheduled recovery.');

    // Captured structural shape with entirely synthetic IDs and no customer data.
    $expiry_response['data']['attributes']['status'] = 'expired';
    $expiry_response['data']['attributes']['payments'] = array(array('id' => 'pay_fixture_expiry_native_123',
        'type' => 'payment', 'attributes' => array('status' => 'failed', 'livemode' => false)));
    $expiry_response['data']['attributes']['payment_intent'] = array('id' => 'pi_fixture_expiry_native_123',
        'type' => 'payment_intent', 'attributes' => array('status' => 'processing', 'livemode' => false));
    $expiry_calls = array();
    Reconciler::run_order($expiry_id);
    $expiry_order = wc_get_order($expiry_id);
    $assert($expiry_calls === array(array('GET', $expiry_url)), 'Native processing Intent issued an automatic expiry request.');
    $assert(Gateway::has_outstanding_attempts($expiry_order), 'Native expired Session hid its processing Intent.');
    $assert(empty(Gateway::order_attempts($expiry_order)[0]['expired_at']), 'Native processing Intent was marked terminal.');

    unset($expiry_response['data']['attributes']['payment_intent']);
    Reconciler::run_order($expiry_id);
    $expiry_order = wc_get_order($expiry_id);
    $assert(!empty(Gateway::order_attempts($expiry_order)[0]['expired_at']), 'Native later terminal readback was not observed.');
    $assert($expiry_order->get_meta('_bactive_paymongo_review_required', true) === 'reconciliation_abandoned_expiry_failed',
        'Native terminal readback acknowledged an unresolved review.');
    $assert(!$expiry_order->is_paid() && $expiry_order->get_transaction_id() === '' && !$expiry_order->get_date_paid(),
        'Native unpaid recovery created payment facts.');
    $assert(did_action('woocommerce_payment_complete') === $effects_before, 'Native unpaid recovery emitted payment effects.');
} finally {
    remove_filter('pre_http_request', $expiry_http, PHP_INT_MAX);
    Order_Lock::release($expiry_id);
}
