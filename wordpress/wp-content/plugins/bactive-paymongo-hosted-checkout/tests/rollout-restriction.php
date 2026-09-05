<?php

// Included by run.php. All keys, orders and provider responses are synthetic.
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Order_Lock;
use BActive\PayMongo\Readiness;
use BActive\PayMongo\Reconciler;
use BActive\PayMongo\Secrets;
use BActive\PayMongo\Webhook;

function rollout_test_setup(array $settings = array(), bool $live = false): Gateway
{
    global $fake_options, $fake_orders, $fake_order_query_ids, $fake_order_query_handler,
        $fake_current_user_caps, $fake_remote_handler, $fake_before_order_save,
        $fake_clone_order_reads, $fake_persist_order_saves, $fake_hook_calls;
    Order_Lock::release_settings();
    Order_Lock::release_checkout();
    Order_Lock::release(42);
    $mode = $live ? 'live' : 'test';
    $key = 'sk_' . $mode . '_rollout_fixture_123456789';
    $secret = 'whsk_rollout_fixture_123456789';
    $fake_options = array(
        'woocommerce_bactive_paymongo_settings' => array_merge(array(
            'enabled' => 'yes',
            'test_mode' => $live ? 'no' : 'yes',
            $mode . '_secret_key' => Secrets::encrypt($key),
        ), $settings),
        'bactive_paymongo_draining' => 'no',
        Reconciler::CONFIG_GENERATION_OPTION => 9,
        'bactive_paymongo_' . $mode . '_webhook_secret' => Secrets::encrypt($secret),
        'bactive_paymongo_' . $mode . '_webhook_secret_binding' => array(
            'webhook_id' => 'hook_rollout_fixture_123',
            'secret_fingerprint' => Secrets::fingerprint($secret),
            'livemode' => $live,
        ),
        'bactive_paymongo_readiness_' . $mode => array(
            'verified_at' => time(),
            'key_fingerprint' => Secrets::fingerprint($key),
            'webhook_secret_fingerprint' => Secrets::fingerprint($secret),
            'webhook_id' => 'hook_rollout_fixture_123',
            'endpoint_url' => Readiness::endpoint_url($live),
            'webhook_status' => 'enabled',
            'webhook_events' => array('checkout_session.payment.paid'),
            'livemode' => $live,
            'capabilities' => array('qrph', 'paymaya', 'shopee_pay', 'dob', 'dob_ubp'),
        ),
    );
    $fake_current_user_caps = array();
    $fake_orders = array(42 => new WC_Order());
    $fake_order_query_ids = array();
    $fake_order_query_handler = null;
    $fake_remote_handler = null;
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_hook_calls = array();
    return new Gateway(false);
}

foreach (array(false, true) as $rollout_live) {
    foreach (array(
        'missing' => array(),
        'enabled' => array('restricted_rollout' => 'yes'),
        'empty' => array('restricted_rollout' => ''),
        'boolean false' => array('restricted_rollout' => false),
        'boolean true' => array('restricted_rollout' => true),
        'numeric zero' => array('restricted_rollout' => 0),
        'unknown' => array('restricted_rollout' => 'public'),
        'array' => array('restricted_rollout' => array('no')),
    ) as $label => $settings) {
        $rollout_gateway = rollout_test_setup($settings, $rollout_live);
        $fake_current_user_caps = array('read', 'edit_posts');
        $rollout_calls = 0;
        $fake_remote_handler = static function () use (&$rollout_calls): WP_Error {
            ++$rollout_calls;
            return new WP_Error('unexpected_rollout_request', 'Unauthorized provider call');
        };
        $before_order = serialize($fake_orders[42]);
        $case = ($rollout_live ? 'live ' : 'test ') . $label;
        same(false, $rollout_gateway->is_available(), $case . ' denies ordinary customer availability');
        same('fail', $rollout_gateway->process_payment(42)['result'], $case . ' denies direct/order-pay issuance');
        same($before_order, serialize($fake_orders[42]), $case . ' leaves the order byte-identical');
        same(0, $rollout_calls, $case . ' performs zero provider calls');
    }
    foreach (array('manage_woocommerce', 'manage_options') as $capability) {
        $rollout_gateway = rollout_test_setup(array(), $rollout_live);
        $fake_current_user_caps = array($capability);
        same(true, $rollout_gateway->is_available(), $capability . ' may verify a ready restricted gateway');
        $fake_options['bactive_paymongo_draining'] = 'yes';
        same(false, $rollout_gateway->is_available(), $capability . ' cannot bypass the drain');
    }
    $rollout_gateway = rollout_test_setup(array('restricted_rollout' => 'no'), $rollout_live);
    same(true, $rollout_gateway->is_available(), 'explicit unrestricted mode permits an anonymous ready gateway');
    unset($fake_options['bactive_paymongo_readiness_' . ($rollout_live ? 'live' : 'test')]);
    same(false, $rollout_gateway->is_available(), 'unrestricted issuance still requires verified readiness');
    $rollout_gateway = rollout_test_setup(array('restricted_rollout' => 'no', 'enabled' => 'no'), $rollout_live);
    same(false, $rollout_gateway->is_available(), 'unrestricted mode never bypasses disabled setting');
}

foreach (array(false, true) as $rollout_live) {
    foreach (array('guest', 'manager') as $role) {
        foreach (array('disabled', 'unready', 'draining') as $unavailable) {
            $rollout_gateway = rollout_test_setup(array('restricted_rollout' => 'no'), $rollout_live);
            $fake_current_user_caps = $role === 'manager' ? array('manage_woocommerce') : array();
            if ($unavailable === 'disabled') {
                $fake_options['woocommerce_bactive_paymongo_settings']['enabled'] = 'no';
                $rollout_gateway = new Gateway(false);
            } elseif ($unavailable === 'unready') {
                unset($fake_options['bactive_paymongo_readiness_' . ($rollout_live ? 'live' : 'test')]);
            } else {
                $fake_options['bactive_paymongo_draining'] = 'yes';
            }
            $rollout_calls = 0;
            $fake_remote_handler = static function () use (&$rollout_calls): WP_Error {
                ++$rollout_calls;
                return new WP_Error('unexpected_rollout_request', 'Unavailable provider call');
            };
            $before_order = serialize($fake_orders[42]);
            $case = ($rollout_live ? 'live ' : 'test ') . $role . ' ' . $unavailable;
            same('fail', $rollout_gateway->process_payment(42)['result'], $case . ' denies direct issuance');
            same(0, $rollout_calls, $case . ' performs zero provider calls');
            same($before_order, serialize($fake_orders[42]), $case . ' leaves the order unchanged');
        }
    }
}

// A cached public instance cannot survive a restriction change, even when a
// competing writer fails to advance the generation counter.
$fake_mutating_settings_getter = true;
$rollout_gateway = rollout_test_setup();
$fake_current_user_caps = array('manage_woocommerce');
same(true, $rollout_gateway->is_available(), 'real Woo default-mutating getter does not strand a manager with a missing rollout field');
check(!array_key_exists('restricted_rollout', $rollout_gateway->settings), 'rollout authorization leaves missing stored settings unchanged');
$fake_mutating_settings_getter = false;

$rollout_gateway = rollout_test_setup(array('restricted_rollout' => 'no'));
$fake_options['woocommerce_bactive_paymongo_settings']['restricted_rollout'] = 'yes';
same(false, $rollout_gateway->is_available(), 'stale public gateway detects exact restriction drift');
same('fail', $rollout_gateway->process_payment(42)['result'], 'stale direct call cannot use an old public setting');

// Ordinary settings writes drain and invalidate prior issuance in both
// directions. Leave readiness provisioning to the existing after-save tests.
foreach (array(array('yes', 'no'), array('no', 'yes')) as $transition) {
    rollout_test_setup(array('restricted_rollout' => $transition[0]));
    $old_rollout_settings = $fake_options['woocommerce_bactive_paymongo_settings'];
    $new_rollout_settings = $old_rollout_settings;
    $new_rollout_settings['restricted_rollout'] = $transition[1];
    $filtered_rollout = Gateway::filter_settings_update($new_rollout_settings, $old_rollout_settings);
    same($transition[1], $filtered_rollout['restricted_rollout'], 'empty drain accepts exact rollout transition');
    same(10, Reconciler::config_generation(), 'rollout transition invalidates in-flight issuance generation');
    check(Order_Lock::settings_write_active(), 'rollout transition keeps lease until persisted readback');
    same('yes', $fake_options['bactive_paymongo_draining'], 'rollout transition fences issuance before commit');
    Order_Lock::release_settings();
}

// Model a concurrent writer's committed rollout change during the exact
// provider POST. The old request must expire its session rather than expose
// a checkout URL under stale visibility rules.
foreach (array(array('yes', 'no'), array('no', 'yes')) as $transition) {
    $rollout_gateway = rollout_test_setup(array('restricted_rollout' => $transition[0]));
    $fake_current_user_caps = array('manage_woocommerce');
    $rollout_race_order = new WC_Order();
    $rollout_race_attempt = array(
        'generation' => 1, 'fingerprint' => hash('sha256', '42|12345|PHP|test'),
        'mode' => 'test', 'reference' => 'BA-42-1', 'correlation_id' => 'correlation-123',
        'idempotency_key' => 'bactive-checkout-test-42-1', 'created_at' => time(),
        'session_id' => '', 'checkout_url' => '',
    );
    $rollout_race_order->meta['_bactive_paymongo_attempts'] = array($rollout_race_attempt);
    $fake_orders = array(42 => clone $rollout_race_order);
    $fake_clone_order_reads = true;
    $fake_persist_order_saves = true;
    $rollout_race_calls = array();
    $fake_remote_handler = static function (string $url, array $args) use (&$rollout_race_calls, $transition): array {
        global $fake_options;
        $rollout_race_calls[] = $url;
        if (str_ends_with($url, '/v2/checkout_sessions')) {
            $fake_options['woocommerce_bactive_paymongo_settings']['restricted_rollout'] = $transition[1];
            $fake_options[Reconciler::CONFIG_GENERATION_OPTION] = 10;
            $attributes = array('checkout_url' => 'https://checkout.paymongo.com/rollout-fixture', 'livemode' => false);
        } else {
            $attributes = array('status' => 'expired', 'livemode' => false, 'payments' => array());
        }
        return array('response' => array('code' => 200), 'body' => json_encode(array('data' => array(
            'id' => 'cs_rollout_race_123', 'type' => 'checkout_session', 'attributes' => $attributes,
        ))));
    };
    $rollout_submit = new ReflectionMethod(Gateway::class, 'submit_attempt');
    $rollout_submit->setAccessible(true);
    check(Order_Lock::acquire(42), 'rollout race acquires the exact order fence');
    try {
        $rollout_race_result = $rollout_submit->invoke($rollout_gateway, $rollout_race_order, 12345, array($rollout_race_attempt), 0, false);
    } finally {
        Order_Lock::release(42);
        $fake_clone_order_reads = false;
        $fake_persist_order_saves = false;
    }
    same('fail', $rollout_race_result['result'] ?? '', 'rollout toggle during provider creation suppresses redirect');
    same(3, count($rollout_race_calls), 'rollout race performs create, expire and independent GET only');
    same(1, count(array_filter($rollout_race_calls, static fn(string $url): bool => str_ends_with($url, '/expire'))), 'rollout race expires the exact new session once');
    check(!empty($fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['expired_at']), 'rollout race records verified expiry before returning');
}

// Restricted issuance must not suppress anonymous provider settlement or
// scheduled recovery of an already-authorized attempt.
rollout_test_setup(array('restricted_rollout' => 'yes'));
$anonymous_recovery_order = $fake_orders[42];
$anonymous_recovery_attempt = Gateway::order_attempts($anonymous_recovery_order)[0];
same(
    'processed',
    Webhook::reconcile_checkout_session($anonymous_recovery_order, array('data' => session()), $anonymous_recovery_attempt, false),
    'anonymous authenticated provider readback still settles during restricted rollout'
);
same(true, $anonymous_recovery_order->paid, 'restricted rollout leaves anonymous recovery paid effect reachable');
same('pay_test_payment_123', $anonymous_recovery_order->transaction_id, 'anonymous recovery retains exact provider transaction');
$fake_current_user_caps = null;
$fake_remote_handler = null;
