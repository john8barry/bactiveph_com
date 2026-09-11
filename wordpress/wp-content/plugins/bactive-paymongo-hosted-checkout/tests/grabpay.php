<?php

// Synthetic contract fixtures only; included by run.php after issuance-methods.php.
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Integrity;
use BActive\PayMongo\Readiness;
use BActive\PayMongo\Reconciler;
use BActive\PayMongo\Webhook;

$grab_gateway = rollout_test_setup();
same(array('qrph', 'paymaya', 'shopee_pay', 'dob', 'dob_ubp'), $grab_gateway->issuance_methods(), 'GrabPay support does not silently expand legacy issuance');
same($grab_gateway->issuance_methods(), $grab_gateway->form_fields['issuance_methods']['default'], 'Woo form defaults preserve original five methods');
check(!str_contains($grab_gateway->description, 'GrabPay'), 'default customer copy does not advertise GrabPay');
$grab_gateway = rollout_test_setup(array('issuance_methods' => array('grab_pay', 'qrph', 'grab_pay')), true);
same(array('qrph', 'grab_pay'), $grab_gateway->issuance_methods(), 'explicit GrabPay selection canonicalizes and deduplicates');
check(str_contains($grab_gateway->description, 'QRPh, GrabPay'), 'explicit selection generates GrabPay checkout label');
$grab_payload_method = new ReflectionMethod(Gateway::class, 'checkout_payload');
$grab_payload_method->setAccessible(true);
$grab_payload = $grab_payload_method->invoke($grab_gateway, $fake_orders[42], 12345, array_merge(Gateway::order_attempts($fake_orders[42])[0], array('generation' => 1)));
same(array('qrph', 'grab_pay'), $grab_payload['data']['attributes']['payment_method_types'], 'new session payload contains explicitly selected GrabPay identifier');
$fake_current_user_caps = array('manage_woocommerce');
same(false, $grab_gateway->is_available(), 'GrabPay selection denied when merchant capability absent');
$fake_options['bactive_paymongo_readiness_live']['capabilities'][] = 'grab_pay';
same(true, $grab_gateway->is_available(), 'GrabPay selection accepts exact verified live capability');
$grab_capabilities = array('qrph', 'grab_pay');
$fake_remote_handler = static function (string $url) use (&$grab_capabilities): array {
    $data = str_ends_with($url, '/v1/merchants/capabilities/payment_methods')
        ? array('attributes' => array('payment_methods' => $grab_capabilities))
        : array(array('id' => 'hook_rollout_fixture_123', 'attributes' => array(
            'url' => Readiness::endpoint_url(true), 'status' => 'enabled', 'livemode' => true,
            'events' => array('checkout_session.payment.paid'), 'secret_key' => 'whsk_rollout_fixture_123456789',
        )));
    return array('response' => array('code' => 200), 'body' => json_encode(array('data' => $data)));
};
same(true, Readiness::is_ready($grab_gateway, true, true), 'fresh provider capability read accepts GrabPay');
$grab_capabilities = array('qrph');
same(false, Readiness::is_ready($grab_gateway, true, true), 'fresh provider capability removal closes GrabPay issuance');
same(array(), $grab_gateway->validate_issuance_methods_field('issuance_methods', array('grabpay')), 'unverified GrabPay alias is not an issuance identifier');

// Adding a method must not weaken the common payment identity contract.
foreach (array('amount', 'currency', 'mode', 'order', 'unknown_alias') as $grab_bad_case) {
    $grab_event = current_event('grab_pay');
    $grab_session =& $grab_event['data']['data'];
    if ($grab_bad_case === 'amount') {
        $grab_session['attributes']['payments'][0]['attributes']['amount'] = 1;
    } elseif ($grab_bad_case === 'currency') {
        $grab_session['attributes']['payments'][0]['attributes']['currency'] = 'USD';
    } elseif ($grab_bad_case === 'mode') {
        $grab_session['attributes']['payments'][0]['attributes']['livemode'] = true;
    } elseif ($grab_bad_case === 'order') {
        $grab_session['attributes']['metadata']['order_id'] = '43';
    } else {
        $grab_session['attributes']['payments'][0]['attributes']['source']['type'] = 'grabpay';
    }
    unset($grab_session);
    $grab_normalized = Integrity::normalize_event($grab_event, (string) json_encode($grab_event));
    check(is_array($grab_normalized) && !Integrity::validate_paid_event($grab_normalized, context())['ok'], 'GrabPay rejects incorrect ' . $grab_bad_case);
}

// Exercise the signed delivery stages used by handle(), without an HTTP server.
// The existing harness cannot inject php://input or intercept handle()'s exit.
rollout_test_setup(array('issuance_methods' => array('grab_pay')));
$grab_signed_raw = (string) json_encode(current_event('grab_pay'));
$grab_signed_time = time();
$grab_signed_secret = 'whsk_grabpay_fixture_123456789';
$grab_signature = hash_hmac('sha256', $grab_signed_time . '.' . $grab_signed_raw, $grab_signed_secret);
$grab_signature_header = 't=' . $grab_signed_time . ',te=' . $grab_signature . ',li=';
check(Integrity::verify_signature($grab_signed_raw, $grab_signature_header, $grab_signed_secret, false, $grab_signed_time)['ok'], 'signed GrabPay raw payload verifies');
check(!Integrity::verify_signature($grab_signed_raw . ' ', $grab_signature_header, $grab_signed_secret, false, $grab_signed_time)['ok'], 'modified GrabPay raw payload fails signature');
$grab_signed_normalized = Integrity::normalize_event(json_decode($grab_signed_raw, true), $grab_signed_raw);
$grab_signed_validated = Integrity::validate_paid_event($grab_signed_normalized, context());
check($grab_signed_validated['ok'], 'signed GrabPay payload passes identity validation');
$grab_claim_method = new ReflectionMethod(Webhook::class, 'claim');
$grab_claim_method->setAccessible(true);
$grab_process_method = new ReflectionMethod(Webhook::class, 'process_claimed_payment');
$grab_process_method->setAccessible(true);
same('claimed', $grab_claim_method->invoke(null, 'event', $grab_signed_validated['event_id'], 'test'), 'first signed GrabPay event acquires claim');
same('processed', $grab_process_method->invoke(null, $fake_orders[42], $grab_signed_validated, false), 'signed GrabPay event performs settlement');
check($fake_orders[42]->paid, 'signed GrabPay event marks exact order paid');
$grab_signed_order_snapshot = serialize($fake_orders[42]);
$grab_signed_hook_snapshot = $fake_hook_calls;
same('done', $grab_claim_method->invoke(null, 'event', $grab_signed_validated['event_id'], 'test'), 'duplicate signed GrabPay event stops at completed claim');
same($grab_signed_order_snapshot, serialize($fake_orders[42]), 'duplicate signed GrabPay event leaves order unchanged');
same($grab_signed_hook_snapshot, $fake_hook_calls, 'duplicate signed GrabPay event emits no effects');

// Existing-session reconciliation is independent of the current issuance subset.
rollout_test_setup(array('issuance_methods' => array('qrph')));
$grab_order = $fake_orders[42];
$grab_attempt = Gateway::order_attempts($grab_order)[0];
same('processed', Webhook::reconcile_checkout_session($grab_order, array('data' => session('grab_pay')), $grab_attempt, false), 'historical GrabPay provider read settles after new issuance excludes it');
same('grab_pay', $grab_order->meta['_bactive_paymongo_source_method'] ?? '', 'settlement persists canonical GrabPay method');
same('pay_test_payment_123', $grab_order->transaction_id, 'GrabPay settlement retains exact transaction');
check($grab_order->paid, 'GrabPay settlement records paid order');
$grab_paid_snapshot = serialize($grab_order);
$grab_hook_snapshot = $fake_hook_calls;
Webhook::reconcile_checkout_session($grab_order, array('data' => session('grab_pay')), $grab_attempt, false);
same($grab_paid_snapshot, serialize($grab_order), 'duplicate GrabPay reconciliation leaves order unchanged');
same($grab_hook_snapshot, $fake_hook_calls, 'duplicate GrabPay reconciliation emits no second hooks');

// Exercise both durable source validators through the actual no-effects operator
// workflow using the already-established resolved-quarantine fixture.
$grab_resolved = clone $resolved_paid_snapshot;
$fake_options = $resolved_paid_options_snapshot;
$fake_current_user_caps = array('manage_woocommerce');
$grab_resolved->meta['_bactive_paymongo_source_method'] = 'grab_pay';
$grab_fingerprint = Gateway::provider_payment_evidence_fingerprint($grab_resolved);
$grab_resolved->meta['_bactive_paymongo_resolved_evidence_fingerprint'] = $grab_fingerprint;
$grab_resolved->meta['_bactive_paymongo_resolved_payment_pending'] = $grab_fingerprint;
check(Webhook::resolved_payment_disposition_action_available($grab_resolved), 'coherent resolved GrabPay evidence exposes authorized recovery action');
$grab_bad_resolved = clone $grab_resolved;
$grab_bad_resolved->meta['_bactive_paymongo_source_provider'] = 'bpi';
$grab_bad_fingerprint = Gateway::provider_payment_evidence_fingerprint($grab_bad_resolved);
$grab_bad_resolved->meta['_bactive_paymongo_resolved_evidence_fingerprint'] = $grab_bad_fingerprint;
$grab_bad_resolved->meta['_bactive_paymongo_resolved_payment_pending'] = $grab_bad_fingerprint;
check(!Webhook::resolved_payment_disposition_action_available($grab_bad_resolved), 'GrabPay recovery rejects inconsistent bank-provider evidence');
$grab_provider_session = session('grab_pay');
$grab_provider_session['attributes']['payments'][0]['id'] = 'pay_quarantine_repair_123';
$grab_provider_calls = 0;
$fake_remote_handler = static function () use ($grab_provider_session, &$grab_provider_calls): array {
    ++$grab_provider_calls;
    return array('response' => array('code' => 200), 'body' => json_encode(array('data' => $grab_provider_session)));
};
$fake_orders = array(42 => clone $grab_resolved);
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    Reconciler::finalize_resolved_payment(clone $grab_resolved);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_remote_handler = null;
}
same(1, $grab_provider_calls, 'GrabPay operator recovery performs one fresh provider read');
same('processing', $fake_orders[42]->status, 'GrabPay operator recovery records exact paid status');
same('pay_quarantine_repair_123', $fake_orders[42]->transaction_id, 'GrabPay operator recovery records exact transaction');
$grab_record = $fake_options[test_operator_disposition_option('pay_quarantine_repair_123')] ?? array();
same('done', $grab_record['status'] ?? '', 'GrabPay recovery completes durable disposition intent');
$grab_record_validator = new ReflectionMethod(Webhook::class, 'operator_disposition_record_valid');
$grab_record_validator->setAccessible(true);
same(true, $grab_record_validator->invoke(null, $grab_record), 'durable GrabPay disposition record validates');
$grab_record['provider'] = 'bpi';
same(false, $grab_record_validator->invoke(null, $grab_record), 'durable GrabPay disposition rejects bank provider');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'GrabPay operator recovery does not replay payment effects');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'GrabPay operator recovery does not replay status effects');
check(!Webhook::resolved_payment_disposition_action_available($fake_orders[42]), 'completed GrabPay recovery cannot replay');
// Return shared fake storage/hooks/locks to the ordinary fixture baseline.
rollout_test_setup();
$fake_scheduled = array();
$fake_current_user_caps = null;
$fake_remote_handler = null;
