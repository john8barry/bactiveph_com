<?php

// Included by run.php after the shared WooCommerce and WordPress test doubles.
// These assertions exercise the public, read-only contract used by Brevo.

use BActive\PayMongo\Payment_Eligibility;

/** @return array<string,mixed> */
function marketing_eligibility_attempt(int $now, string $mode = 'test'): array
{
    return array(
        'generation' => 1,
        'fingerprint' => hash('sha256', 'marketing-eligibility-fixture'),
        'mode' => $mode,
        'reference' => 'BA-42-1',
        'correlation_id' => str_repeat('a', 48),
        'idempotency_key' => 'bactive-checkout-marketing-fixture-' . $mode . '-42-1',
        'created_at' => $now - 120,
        'config_generation' => 1,
        'request_started_at' => $now - 90,
        'session_id' => 'cs_marketing_session_123',
        'checkout_url' => 'https://checkout.paymongo.com/fixture',
        'authorized_at' => $now - 60,
        'request_pending' => false,
    );
}

function marketing_eligibility_settled_order(int $now): WC_Order
{
    $order = new WC_Order();
    $attempt = marketing_eligibility_attempt($now);
    $attempt['payment_id'] = 'pay_marketing_payment_123';
    $attempt['paid_event_id'] = 'evt_marketing_event_123';
    $attempt['paid_at'] = $now - 30;
    $order->status = 'processing';
    $order->paid = true;
    $order->date_paid = new DateTimeImmutable('@' . ($now - 30));
    $order->transaction_id = 'pay_marketing_payment_123';
    $order->meta = array(
        '_bactive_paymongo_attempts' => array($attempt),
        '_bactive_paymongo_paid_event_id' => 'evt_marketing_event_123',
        '_bactive_paymongo_paid_session_id' => 'cs_marketing_session_123',
        '_bactive_paymongo_source_method' => 'qrph',
        '_bactive_paymongo_source_provider' => '',
        '_bactive_paymongo_paid_mode' => 'test',
    );
    return $order;
}

$fake_options = array();
$eligibility_now = time();
$settled_marketing_order = marketing_eligibility_settled_order($eligibility_now);
$settled_before = serialize(array(
    $settled_marketing_order->status,
    $settled_marketing_order->paid,
    $settled_marketing_order->transaction_id,
    $settled_marketing_order->date_paid,
    $settled_marketing_order->meta,
));
same(Payment_Eligibility::SETTLED, Payment_Eligibility::classify($settled_marketing_order), 'complete matching PayMongo evidence is settled');
same($settled_before, serialize(array(
    $settled_marketing_order->status,
    $settled_marketing_order->paid,
    $settled_marketing_order->transaction_id,
    $settled_marketing_order->date_paid,
    $settled_marketing_order->meta,
)), 'payment eligibility inspection makes no order mutation');

$missing_attempt_order = new WC_Order();
$missing_attempt_order->meta = array();
same(Payment_Eligibility::UNCERTAIN, Payment_Eligibility::classify($missing_attempt_order), 'PayMongo order without persisted attempts fails closed');

$unpaid_marketing_order = new WC_Order();
$unpaid_attempt = marketing_eligibility_attempt($eligibility_now);
$unpaid_attempt['expired_at'] = $eligibility_now - 1;
$unpaid_marketing_order->meta = array('_bactive_paymongo_attempts' => array($unpaid_attempt));
same(Payment_Eligibility::UNPAID, Payment_Eligibility::classify($unpaid_marketing_order), 'verified terminal PayMongo attempt remains unpaid');

$pending_request_order = new WC_Order();
$pending_attempt = marketing_eligibility_attempt($eligibility_now);
$pending_attempt['request_pending'] = true;
$pending_request_order->meta = array('_bactive_paymongo_attempts' => array($pending_attempt));
same(Payment_Eligibility::UNCERTAIN, Payment_Eligibility::classify($pending_request_order), 'request with an uncertain provider outcome is held for recovery');

$malformed_order = marketing_eligibility_settled_order($eligibility_now);
$malformed_order->meta['_bactive_paymongo_attempts'] = 'not-an-attempt-list';
same(Payment_Eligibility::UNCERTAIN, Payment_Eligibility::classify($malformed_order), 'malformed persisted PayMongo attempts fail closed');

$mode_mismatch_order = marketing_eligibility_settled_order($eligibility_now);
$mode_mismatch_order->meta['_bactive_paymongo_attempts'][0]['mode'] = 'live';
same(Payment_Eligibility::UNCERTAIN, Payment_Eligibility::classify($mode_mismatch_order), 'payment mode mismatch fails closed');

$recovery_order = marketing_eligibility_settled_order($eligibility_now);
$recovery_order->meta[\BActive\PayMongo\Reconciler::REQUIRED_META] = 'yes';
same(Payment_Eligibility::UNCERTAIN, Payment_Eligibility::classify($recovery_order), 'reconciliation marker withholds post-purchase messages');

$refund_order = marketing_eligibility_settled_order($eligibility_now);
$refund_order->refunds = array(new stdClass());
$refund_order->total_refunded = 1.0;
same(Payment_Eligibility::UNCERTAIN, Payment_Eligibility::classify($refund_order), 'refund state withholds post-purchase messages');

$dispute_order = marketing_eligibility_settled_order($eligibility_now);
$dispute_order->meta['_bactive_paymongo_dispute_id'] = 'dp_marketing_dispute_123';
same(Payment_Eligibility::UNCERTAIN, Payment_Eligibility::classify($dispute_order), 'dispute evidence withholds post-purchase messages');

$ordinary_paid_order = new WC_Order();
$ordinary_paid_order->payment_method = 'cod';
$ordinary_paid_order->meta = array();
$ordinary_paid_order->paid = true;
$ordinary_paid_order->date_paid = new DateTimeImmutable('@' . $eligibility_now);
same(Payment_Eligibility::SETTLED, Payment_Eligibility::classify($ordinary_paid_order), 'paid non-PayMongo order has an ordinary settlement record');

$ordinary_unpaid_order = new WC_Order();
$ordinary_unpaid_order->payment_method = 'cod';
$ordinary_unpaid_order->meta = array();
same(Payment_Eligibility::UNPAID, Payment_Eligibility::classify($ordinary_unpaid_order), 'unpaid non-PayMongo order remains unpaid');

require_once dirname(__DIR__, 2) . '/bactive-brevo-marketing/includes/automations.php';
same('settled', \Bactive\Brevo\Automations::payment_certainty($settled_marketing_order), 'Brevo receives the public settled state');
same('unknown', \Bactive\Brevo\Automations::payment_certainty($mode_mismatch_order), 'Brevo holds an uncertain payment state');
