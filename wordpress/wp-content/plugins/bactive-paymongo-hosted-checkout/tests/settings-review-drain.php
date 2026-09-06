<?php

// Included after rollout-restriction.php. All state and provider replies are synthetic.
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Order_Lock;
use BActive\PayMongo\Readiness;
use BActive\PayMongo\Reconciler;
use BActive\PayMongo\Webhook;

$settings_review_prepare = static function (): void {
    global $fake_remote_handler, $fake_order_query_ids;
    rollout_test_setup(array('title' => 'Original title', 'description' => 'Original description'));
    $fake_order_query_ids = array(42);
    $fake_remote_handler = static function (string $url, array $args) {
        if (($args['method'] ?? 'GET') !== 'GET') {
            throw new RuntimeException('Settings copy edits must not mutate provider resources.');
        }
        if (str_contains($url, '/capabilities/payment_methods')) {
            $body = array('data' => array('attributes' => array('payment_methods' => array('qrph'))));
        } elseif (str_contains($url, '/v1/webhooks?')) {
            $body = array('data' => array(array('id' => 'hook_rollout_fixture_123', 'type' => 'webhook',
                'attributes' => array('url' => Readiness::endpoint_url(false), 'status' => 'enabled',
                    'events' => array('checkout_session.payment.paid'), 'livemode' => false,
                    'secret_key' => 'whsk_rollout_fixture_123456789'))));
        } else {
            throw new RuntimeException('Unexpected provider request in settings copy edit.');
        }
        return array('response' => array('code' => 200), 'body' => json_encode($body));
    };
};
$settings_review_save = static function (string $field, ?string $value = null): void {
    global $fake_options;
    $old = $fake_options['woocommerce_bactive_paymongo_settings'];
    $next = $old;
    $next[$field] = $value ?? 'Changed ' . $field;
    $next = Gateway::filter_settings_update($next, $old);
    Gateway::guard_settings_update_commit('woocommerce_bactive_paymongo_settings', $old, $next);
    $fake_options['woocommerce_bactive_paymongo_settings'] = $next;
    Gateway::after_settings_update($old, $next);
};
$settings_review_flag = new ReflectionMethod(Reconciler::class, 'flag_failure');
$settings_review_flag->setAccessible(true);
$settings_order_snapshot = static function (): string {
    global $fake_orders;
    $orders = array_map(static function (WC_Order $order): WC_Order {
        $copy = clone $order;
        $copy->read_count = 0; // Observation is allowed; persistence/effects are not.
        return $copy;
    }, $fake_orders);
    return hash('sha256', serialize($orders));
};

foreach (array('generic review', 'order-only review', 'orphan ledger', 'stale-open order review', 'stale-open orphan ledger',
    'lookup failure', 'readiness incident', 'delayed incident record') as $case) {
    $settings_review_prepare();
    if (in_array($case, array('generic review', 'order-only review', 'stale-open order review'), true)) {
        check(Order_Lock::acquire(42), $case . ' acquires its synthetic order lease');
        $settings_review_flag->invoke(null, $fake_orders[42], 'reconciliation_abandoned_expiry_failed', 'test');
        Order_Lock::release(42);
        if ($case !== 'generic review') {
            unset($fake_options[Reconciler::review_incident_option(42, 'reconciliation_abandoned_expiry_failed', 'test')]);
        }
    } elseif ($case === 'orphan ledger' || $case === 'stale-open orphan ledger') {
        $fake_options[Reconciler::review_incident_option(999, 'fixture_orphan_review', 'test')] = array(
            'recorded_at' => time(), 'order_id' => 999, 'code' => 'fixture_orphan_review', 'mode' => 'test');
        Reconciler::set_draining(true);
    } elseif ($case === 'lookup failure') {
        $fake_order_query_handler = static function (): array {
            throw new RuntimeException('Synthetic order discovery failure.');
        };
    } else {
        $ready_handler = $fake_remote_handler;
        $fake_remote_handler = static function (string $url, array $args) use ($ready_handler, $case) {
            global $fake_options;
            if ($case !== 'delayed incident record') {
                $fake_options[Reconciler::review_incident_option(999, 'fixture_readiness_review', 'test')] = array(
                    'recorded_at' => time(), 'order_id' => 999, 'code' => 'fixture_readiness_review', 'mode' => 'test');
            }
            Reconciler::set_draining(true);
            return $ready_handler($url, $args);
        };
    }
    if (str_starts_with($case, 'stale-open')) {
        $fake_options['bactive_paymongo_draining'] = 'no';
    }
    $before = $settings_order_snapshot();
    $was_open = !Reconciler::is_draining();
    $availability_before = (int) ($fake_hook_calls['bactive_paymongo_availability_changed'] ?? 0);
    $before_settings = $fake_options['woocommerce_bactive_paymongo_settings'];
    $settings_review_save($case === 'orphan ledger' ? 'description' : 'title');
    same(true, Reconciler::is_draining(), $case . ' cannot reopen through a copy edit');
    if ($was_open) {
        check(($fake_hook_calls['bactive_paymongo_availability_changed'] ?? 0) > $availability_before,
            $case . ' invalidates cached payment claims on the original closure');
    }
    same('yes', $fake_options['bactive_paymongo_draining'], $case . ' returns to an explicit closed flag');
    check(in_array($fake_options['bactive_paymongo_settings_write_error']['code'] ?? '',
        array('settings_final_verification_failed', 'paymongo_reopen_fence_unavailable'), true), $case . ' records failed reopening');
    same(false, Order_Lock::settings_write_active(), $case . ' releases only its settings lease');
    same(9, Reconciler::config_generation(), $case . ' does not change configuration generation for copy');
    same($before, $settings_order_snapshot(), $case . ' preserves every order and payment field');
    same($before_settings['test_secret_key'], $fake_options['woocommerce_bactive_paymongo_settings']['test_secret_key'], $case . ' retains the exact credential');
}

$settings_review_prepare();
$before = $settings_order_snapshot();
$settings_review_save('title');
same(false, Reconciler::is_draining(), 'ordinary clean title edit reopens without expiring an active session');
same($before, $settings_order_snapshot(), 'ordinary clean title edit retains the exact outstanding attempt');
same(false, Order_Lock::settings_write_active(), 'ordinary clean title edit releases its lease');

// Use the supported review action after terminal provider evidence is represented
// by the synthetic fixture. Preserve its completed resolution receipt on reopen.
$settings_review_prepare();
$fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['expired_at'] = time();
check(Order_Lock::acquire(42), 'resolved review fixture acquires order lease');
$settings_review_flag->invoke(null, $fake_orders[42], 'reconciliation_abandoned_expiry_failed', 'test');
check(Webhook::resolve_review_for_operator($fake_orders[42]), 'terminal generic review resolves through supported operator logic');
Order_Lock::release(42);
$before = $settings_order_snapshot();
$settings_review_save('title');
same(true, Reconciler::is_draining(), 'copy edit cannot acknowledge an older closure even after review resolution');
same($before, $settings_order_snapshot(), 'reopening never replays or changes resolved order effects');
$settings_review_save('enabled', 'no');
$settings_review_save('enabled', 'yes');
same(false, Reconciler::is_draining(), 'resolved review permits explicit disable-enable recovery after a complete drain');
same('done', $fake_options[test_review_resolution_option(42)]['status'] ?? '', 'reopening preserves the immutable review resolution receipt');

$settings_review_prepare();
same('', Reconciler::begin_reopen_verification(), 'reopen verification requires an owned settings lease');
unset($fake_options['bactive_paymongo_draining']);
same(true, Reconciler::is_draining(), 'absent drain option fails closed');
$fake_options['bactive_paymongo_draining'] = array('invalid');
same(true, Reconciler::is_draining(), 'malformed drain option fails closed');
Reconciler::set_draining(true);
check(Order_Lock::acquire_settings(hash('sha256', 'fixture-reopen-fence')), 'fence fixture acquires settings lease');
$fence = Reconciler::begin_reopen_verification(true);
check(str_starts_with($fence, 'verifying:'), 'verification obtains a unique closed token');
same(true, Reconciler::is_draining(), 'verification token always blocks issuance');
Reconciler::set_draining(true);
same(false, Reconciler::reopen_after_verification($fence), 'a later closure invalidates a clean-scan reopen token');
same(true, Reconciler::is_draining(), 'failed CAS retains the newer closure');
$fence = Reconciler::begin_reopen_verification(true);
Order_Lock::release_settings();
same(false, Reconciler::reopen_after_verification($fence), 'reopen requires the settings lease through final CAS');
Reconciler::set_draining(true);
$fake_remote_handler = null;
$fake_order_query_handler = null;

// Persistence failure must retain a stronger fence even when no incident row
// survives, rather than permitting a later explicit settings recovery.
foreach (array('swallowed alarm', 'throwing alarm', 'failed inbox and alarm') as $case) {
    $settings_review_prepare();
    $fake_orders[42]->meta['_bactive_paymongo_attempts'] = array();
    $availability_before = (int) ($fake_hook_calls['bactive_paymongo_availability_changed'] ?? 0);
    $fake_option_update_swallow = array('bactive_paymongo_disable_drain_error');
    if ($case === 'throwing alarm') {
        $fake_option_update_handler = static function (string $key): void {
            if ($key === 'bactive_paymongo_disable_drain_error') {
                throw new RuntimeException('Synthetic incident-write failure.');
            }
        };
    }
    if ($case === 'failed inbox and alarm') {
        $fake_option_update_swallow[] = 'bactive_paymongo_pending_reviews_42';
        check(Order_Lock::acquire(42), 'failed inbox fixture owns its order');
        $settings_review_flag->invoke(null, $fake_orders[42], 'fixture_failed_review', 'test');
        Order_Lock::release(42);
    } else {
        Reconciler::record_global_drain_error(array('recorded_at' => time(), 'code' => 'fixture_alarm_failure'));
    }
    check(($fake_hook_calls['bactive_paymongo_availability_changed'] ?? 0) > $availability_before,
        $case . ' invalidates cached claims when the missing-evidence latch closes issuance');
    $fake_option_update_handler = null;
    $fake_option_update_swallow = array();
    Reconciler::set_draining(true);
    $settings_review_save('enabled', 'no');
    $settings_review_save('enabled', 'yes');
    same('unrecorded-incident', $fake_options['bactive_paymongo_draining'], $case . ' cannot downgrade its missing-evidence latch');
    same(true, Reconciler::is_draining(), $case . ' cannot reopen through explicit settings changes');
}
