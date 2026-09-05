<?php

// Included by run.php after the shared WooCommerce/WordPress test doubles.
// Exercise boundaries introduced by the final mode and recovery review.

use BActive\PayMongo\Gateway;
use BActive\PayMongo\Order_Lock;
use BActive\PayMongo\Reconciler;
use BActive\PayMongo\Secrets;
use BActive\PayMongo\Webhook;

$identity_conflict = new ReflectionMethod(Webhook::class, 'payment_identity_conflicts');
$identity_conflict->setAccessible(true);
$boundary_payment = array(
    'payment_id' => 'pay_boundary_same_123',
    'event_id' => 'evt_boundary_same_123',
    'session_id' => 'cs_boundary_same_123',
    'method' => 'qrph', 'provider' => '', 'amount' => 12345, 'mode' => 'live',
);
foreach (array(
    'paid event without mode' => array('_bactive_paymongo_paid_event_id' => 'evt_boundary_same_123'),
    'paid session without mode' => array('_bactive_paymongo_paid_session_id' => 'cs_boundary_same_123'),
    'paid method without mode' => array('_bactive_paymongo_source_method' => 'qrph'),
    'paid mode without identity' => array('_bactive_paymongo_paid_mode' => 'live'),
    'settlement ID without mode' => array('_bactive_paymongo_settlement_pending' => 'pay_boundary_same_123'),
    'settlement mode without ID' => array('_bactive_paymongo_settlement_pending_mode' => 'live'),
    'unexpected ID without mode' => array('_bactive_paymongo_unexpected_payment_id' => 'pay_boundary_same_123'),
    'unexpected mode without ID' => array('_bactive_paymongo_unexpected_payment_mode' => 'live'),
) as $label => $partial_meta) {
    $partial_order = new WC_Order();
    $partial_order->meta = $partial_meta;
    $before = serialize($partial_order->meta);
    check($identity_conflict->invoke(null, $partial_order, $boundary_payment, $boundary_payment['payment_id']), $label . ' cannot be repaired by a new live delivery');
    same($before, serialize($partial_order->meta), $label . ' retains original evidence bytes');
}
$partial_transaction = new WC_Order();
$partial_transaction->transaction_id = $boundary_payment['payment_id'];
check($identity_conflict->invoke(null, $partial_transaction, $boundary_payment, $boundary_payment['payment_id']), 'transaction ID without paid facts/mode is an identity conflict');

$fake_options = array();
$fake_order_query_ids = array();
$fake_hook_calls = array();
$fake_before_order_save = null;
$fake_persist_order_filter = null;
$fake_option_update_handler = null;
$fake_option_read_missing = array();
$fake_option_add_failures = array();
$fake_option_update_swallow = array();
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$inbox_order = new WC_Order();
$inbox_order->meta = array();
$fake_orders = array(42 => clone $inbox_order);
$first_review = array('order_id' => 42, 'code' => 'first_incident', 'mode' => 'test', 'recorded_at' => time());
$second_review = array('order_id' => 42, 'code' => 'second_incident', 'mode' => 'live', 'recorded_at' => time());
check(Order_Lock::acquire(42), 'incident inbox regression acquires the order fence');
try {
    same($first_review, Webhook::queue_review_incident($inbox_order, 'generic', $first_review), 'first incident is durably indexed before attachment');
    $fake_options['bactive_paymongo_draining'] = 'no';
    same($first_review, Webhook::queue_review_incident($inbox_order, 'generic', $first_review), 'retry recovers the same incident after an inbox-write crash');
    same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'existing inbox retry reasserts the drain');
    check(Webhook::promote_pending_review($inbox_order), 'first queued incident becomes the active review');
    same($second_review, Webhook::queue_review_incident($inbox_order, 'generic', $second_review), 'second mode incident remains durably indexed');
    same(false, Webhook::promote_pending_review($inbox_order), 'second mode incident cannot replace the first active tuple');
    same('test', $fake_orders[42]->meta['_bactive_paymongo_review_mode'] ?? '', 'first active review keeps its original mode');
    same(array(42), Webhook::pending_review_order_ids(), 'external inbox discovers order independently of order metadata');
    check(Webhook::resolve_review_for_operator($inbox_order), 'operator can resolve the first generic incident');
    check(Webhook::has_pending_reviews(42), 'resolving the first incident retains the second inbox entry');
    check(Webhook::promote_pending_review($inbox_order), 'second incident is promoted without another provider delivery');
    same('live', $fake_orders[42]->meta['_bactive_paymongo_review_mode'] ?? '', 'promoted incident retains its own mode');
    check(Webhook::resolve_review_for_operator($inbox_order), 'operator can resolve the second mode incident');
    same(2, count(test_options_with_prefix($fake_options, 'bactive_paymongo_review_receipt_')), 'both completed incident receipts remain in immutable history');

    $third_review = array('order_id' => 42, 'code' => 'third_incident', 'mode' => 'test', 'recorded_at' => time());
    check(Webhook::queue_review_incident($inbox_order, 'generic', $third_review) !== null, 'a later same-mode incident is indexed');
    check(Webhook::promote_pending_review($inbox_order), 'later same-mode incident is promoted');
    check(Webhook::resolve_review_for_operator($inbox_order), 'same-mode active resolution pointer can advance after its receipt is retained');
    same(3, count(test_options_with_prefix($fake_options, 'bactive_paymongo_review_receipt_')), 'later same-mode resolution does not erase the first receipt');
    same(array(), Webhook::pending_review_order_ids(), 'fully attached incidents leave no orphan inbox index');
    same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'incident promotion and resolution emit no payment completion');
    same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'incident promotion and resolution emit no fulfillment transition');
} finally {
    Order_Lock::release(42);
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}

// A settings drain must not erase a failed signed event whose durable write
// never completed, including an event which cannot yet be linked to an order.
$fake_orders = array();
$fake_options = array();

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $path): string {
        return basename(dirname($path)) . '/' . basename($path);
    }
}
if (!function_exists('get_site_option')) {
    function get_site_option(string $option, $default = false) {
        return get_option($option, $default);
    }
}
$network_plugin = plugin_basename(\BActive\PayMongo\PLUGIN_FILE);
$fake_options['active_sitewide_plugins'] = array($network_plugin => time());
Reconciler::guard_deactivation();
check(Order_Lock::settings_write_active(), 'deactivation keeps its settings fence until plugin-list storage');
same(array(), Reconciler::guard_network_plugin_list_update(array(), array($network_plugin => time())), 'network plugin-list removal revalidates the owned drain');
$fake_options['active_sitewide_plugins'] = array();
Reconciler::after_plugin_list_update('active_sitewide_plugins', array());
same(false, Order_Lock::settings_write_active(), 'network post-storage readback releases the settings fence');
$event_failure = array('recorded_at' => time(), 'code' => 'quarantine_record_persist_failed', 'order_id' => 0,
    'event_id' => 'evt_missing_order_123', 'session_id' => 'cs_missing_order_123', 'payment_id' => '', 'mode' => 'live');
$fake_options['bactive_paymongo_disable_drain_error'] = $event_failure;
check(Reconciler::has_unresolved_external_incidents(), 'orderless failure independently blocks settings rotation and deactivation');
$settings_guard_fingerprint = hash('sha256', 'settings-drain-regression');
check(Order_Lock::acquire_settings($settings_guard_fingerprint), 'global incident regression acquires settings lease');
try {
    same(false, Reconciler::clear_settings_drain_error(), 'settings drain cannot clear an orderless event failure');
    same($event_failure, $fake_options['bactive_paymongo_disable_drain_error'] ?? null, 'orderless failure remains byte-identical');
    $fake_options['bactive_paymongo_disable_drain_error'] = array('recorded_at' => time(), 'code' => 'paymongo_active_sessions_remain', 'owner' => 'settings');
    check(Reconciler::clear_settings_drain_error(), 'verified empty drain can clear its own exact summary');
} finally {
    Order_Lock::release_settings();
}

foreach (array('test', 'live') as $mode) {
    $option = 'bactive_paymongo_' . $mode . '_webhook_secret';
    foreach (array('whsk_untrusted_direct_value', Secrets::encrypt('whsk_untrusted_direct_value')) as $candidate) {
        $rejected = false;
        try {
            Secrets::guard_webhook_secret_write($option, $candidate);
        } catch (Error $error) {
            $rejected = true;
        }
        check($rejected, $mode . ' secret rejects an unarmed direct value before SQL');
    }
    $delete_rejected = false;
    try {
        Secrets::guard_webhook_secret_delete($option);
    } catch (Error $error) {
        $delete_rejected = true;
    }
    check($delete_rejected, $mode . ' secret cannot be directly deleted');
}

$fake_options = array();
$fake_orders = array();
$fake_option_update_handler = static function (string $option, $value) {
    if (str_ends_with($option, '_webhook_secret')) {
        Secrets::guard_webhook_secret_update($option, get_option($option, null), $value);
    }
    return null;
};
check(Secrets::store_webhook_secret(false, 'whsk_verified_sandbox_boundary'), 'verified provisioning stores its exact encrypted sandbox secret under a lease');
same('whsk_verified_sandbox_boundary', Secrets::webhook_secret(false), 'sandbox secret has exact independent decrypted readback');
same('', Secrets::webhook_secret(true), 'sandbox secret provisioning leaves live namespace empty');
$fake_option_update_handler = null;

$blocked_secret_order = new WC_Order();
$fake_orders = array(42 => $blocked_secret_order);
$fake_order_query_ids = array(42);
same(false, Secrets::store_webhook_secret(false, 'whsk_changed_while_session_active'), 'secret rotation fails closed while an order has an outstanding session');
same('whsk_verified_sandbox_boundary', Secrets::webhook_secret(false), 'blocked secret rotation retains the old signing secret');
$fake_orders = array();
$fake_order_query_ids = array();
$fake_options = array();

// The single-process test CAS must model whole-value equality, including
// tokenless operational records, rather than accidentally requiring a lock.
$fixture_record = array('order_id' => 42, 'code' => 'fixture-incident', 'mode' => 'test');
$fake_options['bactive_paymongo_fixture_cas'] = $fixture_record;
check(Order_Lock::delete_option_if_exact('bactive_paymongo_fixture_cas', $fixture_record), 'test CAS deletes an exact tokenless incident record');
same(null, get_option('bactive_paymongo_fixture_cas', null), 'exact tokenless deletion is read back');
$stale_lock = array('token' => 'same-fixture-token', 'acquired_at' => 1);
$renewed_lock = array('token' => 'same-fixture-token', 'acquired_at' => 2);
$fake_options['bactive_paymongo_fixture_cas'] = $renewed_lock;
$fixture_delete = new ReflectionMethod(Order_Lock::class, 'compare_and_delete');
$fixture_delete->setAccessible(true);
same(false, $fixture_delete->invoke(null, 'bactive_paymongo_fixture_cas', serialize($stale_lock)), 'test CAS rejects changed bytes even when the lock token matches');
same($renewed_lock, get_option('bactive_paymongo_fixture_cas', null), 'test CAS preserves the renewed record');
check(Order_Lock::delete_option_if_exact('bactive_paymongo_fixture_cas', $renewed_lock), 'test CAS permits deletion after fresh exact readback');
