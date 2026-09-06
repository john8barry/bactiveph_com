<?php

// Included only by the identity-guarded, isolated datastore fixture.
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Order_Lock;
use BActive\PayMongo\Reconciler;
use BActive\PayMongo\Webhook;

global $wpdb;
$drain_option = 'bactive_paymongo_draining';
$drain_fixture_write = static function (string $value) use ($wpdb, $drain_option): void {
    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", $value, $drain_option));
};
$save_fixture_copy = static function (): void {
    $settings = get_option('woocommerce_bactive_paymongo_settings');
    $settings['title'] .= ' revised';
    update_option('woocommerce_bactive_paymongo_settings', $settings, false);
};
$recover_fixture_settings = static function (): void {
    foreach (array('no', 'yes') as $enabled) {
        $settings = get_option('woocommerce_bactive_paymongo_settings');
        $settings['enabled'] = $enabled;
        update_option('woocommerce_bactive_paymongo_settings', $settings, false);
    }
};

// Native WP cache holds no while another request closes the underlying row.
wp_cache_set($drain_option, 'no', 'options');
$drain_fixture_write('yes');
$assert(Reconciler::is_draining(), 'Cached no concealed a database closure.');
$assert(Order_Lock::acquire_settings(hash('sha256', 'native-cache-fence')), 'Fixture settings lease unavailable.');
$fence = Reconciler::begin_reopen_verification(true);
$assert(str_starts_with($fence, 'verifying:'), 'Native CAS did not install a verification fence.');
wp_cache_set($drain_option, 'yes', 'options');
Reconciler::set_draining(true);
$assert($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $drain_option)) === 'yes',
    'Cached yes caused a concurrent closure to skip its database write.');
$assert(!Reconciler::reopen_after_verification($fence), 'Older verification token overwrote the newer closure.');
Order_Lock::release_settings();
$recover_fixture_settings();
$assert(!Reconciler::is_draining(), 'Clean explicit recovery failed.');

// Pause precisely after all final scans, just before the native CAS executes.
$late_close = false;
$close_before_cas = static function (string $sql) use (&$late_close): string {
    if (!$late_close && str_contains($sql, "SET option_value = 'no'") && str_contains($sql, "BINARY option_value")) {
        $late_close = true;
        Reconciler::set_draining(true);
    }
    return $sql;
};
add_filter('query', $close_before_cas, PHP_INT_MAX);
$save_fixture_copy();
remove_filter('query', $close_before_cas, PHP_INT_MAX);
$assert($late_close && Reconciler::is_draining(), 'Concurrent closure lost to the final native settings CAS.');
$assert(!Order_Lock::settings_write_active(), 'Failed native CAS leaked the settings lease.');
$recover_fixture_settings();

// Closure during readiness, with its durable diagnostic deliberately delayed.
$during_readiness = false;
$close_during_readiness = static function ($pre) use (&$during_readiness) {
    if (!$during_readiness) {
        $during_readiness = true;
        Reconciler::set_draining(true);
    }
    return $pre;
};
add_filter('pre_http_request', $close_during_readiness, PHP_INT_MAX);
$save_fixture_copy();
remove_filter('pre_http_request', $close_during_readiness, PHP_INT_MAX);
$assert($during_readiness && Reconciler::is_draining(), 'Readiness rearmed a fence after an incident closure.');
$save_fixture_copy();
$assert(Reconciler::is_draining(), 'A copy-only edit acknowledged a preexisting closure.');
$recover_fixture_settings();

// A ledger can change while this PHP process still caches its previous done value.
$ledger = 'bactive_paymongo_effects_test_payment_native_cache_fixture';
add_option($ledger, array('status' => 'done'), '', false);
get_option($ledger);
$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize(array('status' => 'processing')), $ledger));
$assert(Reconciler::has_unresolved_external_incidents(), 'Cached done ledger concealed processing effects.');
$save_fixture_copy();
$assert(Reconciler::is_draining(), 'Stale cached ledger reopened checkout.');
delete_option($ledger);
$recover_fixture_settings();

$alarm_option = 'bactive_paymongo_disable_drain_error';
get_option($alarm_option, false); // Cache its absence before another request publishes it.
$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
    $alarm_option, maybe_serialize(array('recorded_at' => time(), 'code' => 'fixture_cached_absent_alarm'))));
$assert(Reconciler::has_unresolved_external_incidents(), 'Cached missing global alarm concealed new incident evidence.');
$save_fixture_copy();
$assert(Reconciler::is_draining(), 'Cached missing global alarm allowed settings reopening.');
$wpdb->delete($wpdb->options, array('option_name' => $alarm_option));
wp_cache_delete($alarm_option, 'options');
wp_cache_delete('notoptions', 'options');
$recover_fixture_settings();

// Existing native Woo order metadata alone must be sufficient to keep the gate closed.
$review_order = wc_get_order($order->get_id());
$assert(Order_Lock::acquire($review_order->get_id()), 'Native review fixture could not acquire its order lock.');
$flag_review = new ReflectionMethod(Reconciler::class, 'flag_failure');
$flag_review->setAccessible(true);
$flag_review->invoke(null, $review_order, 'fixture_order_only', 'test');
Order_Lock::release($review_order->get_id());
$review_option = Reconciler::review_incident_option($review_order->get_id(), 'fixture_order_only', 'test');
$review_record = get_option($review_option);
delete_option($review_option); // Simulate a torn ledger; order evidence must suffice.
$save_fixture_copy();
$assert(Reconciler::is_draining(), 'Order-only native review was missed by the settings scan.');
add_option($review_option, $review_record, '', false);
$assert(Order_Lock::acquire($review_order->get_id()), 'Native review resolution lost its order lock.');
$assert(Webhook::resolve_review_for_operator($review_order), 'Native terminal review did not resolve through the supported action.');
Order_Lock::release($review_order->get_id());
$recover_fixture_settings();
$assert(!Reconciler::is_draining(), 'Resolved native review blocked explicit recovery.');

// SQL failures cannot be read as no hold, including a failed CAS after clean scans.
$suppressed = $wpdb->suppress_errors(true);
$break_drain_read = static fn(string $sql): string => str_contains($sql, 'SELECT option_value') && str_contains($sql, 'bactive_paymongo_draining')
    ? 'SELECT * FROM bactive_disposable_missing_table' : $sql;
add_filter('query', $break_drain_read, PHP_INT_MAX);
$assert(Reconciler::is_draining(), 'Drain database read failure opened issuance.');
$assert(Order_Lock::acquire_settings(hash('sha256', 'native-read-error')), 'Read-error fixture lease unavailable.');
$assert(Reconciler::begin_reopen_verification(true) === '', 'Database read error was treated as an absent option.');
Order_Lock::release_settings();
remove_filter('query', $break_drain_read, PHP_INT_MAX);
$break_cas = static fn(string $sql): string => str_contains($sql, "SET option_value = 'no'") && str_contains($sql, 'BINARY option_value')
    ? 'UPDATE bactive_disposable_missing_table SET missing = 1' : $sql;
add_filter('query', $break_cas, PHP_INT_MAX);
$save_fixture_copy();
remove_filter('query', $break_cas, PHP_INT_MAX);
$wpdb->suppress_errors($suppressed);
$assert(Reconciler::is_draining(), 'Failed native CAS opened issuance.');
$recover_fixture_settings();

$drain_fixture_write('s:2:"no";');
$assert(Reconciler::is_draining(), 'Serialized no was accepted as the raw open sentinel.');
$drain_fixture_write('no');
delete_option($drain_option);
$assert(Reconciler::is_draining(), 'Absent native drain value did not fail closed.');
$assert(Order_Lock::acquire_settings(hash('sha256', 'native-insert-race')), 'Insert-race fixture lease unavailable.');
$inserted_closure = false;
$close_before_insert = static function (string $sql) use (&$inserted_closure): string {
    if (!$inserted_closure && str_contains($sql, 'INSERT IGNORE') && str_contains($sql, 'bactive_paymongo_draining')) {
        $inserted_closure = true;
        Reconciler::set_draining(true);
    }
    return $sql;
};
add_filter('query', $close_before_insert, PHP_INT_MAX);
$assert(Reconciler::begin_reopen_verification(true) === '', 'Bootstrap overwrote a concurrent closure after observing absence.');
remove_filter('query', $close_before_insert, PHP_INT_MAX);
$assert($inserted_closure && Reconciler::is_draining(), 'Concurrent bootstrap closure did not survive insert-if-absent.');
Order_Lock::release_settings();
$recover_fixture_settings();

// Alarm write failure retains a stronger closure that all ordinary closes preserve.
$swallow_alarm = static fn($value, $old) => $old;
add_filter('pre_update_option_bactive_paymongo_disable_drain_error', $swallow_alarm, PHP_INT_MAX, 2);
Reconciler::record_global_drain_error(array('recorded_at' => time(), 'code' => 'fixture_alarm_write_failed'));
remove_filter('pre_update_option_bactive_paymongo_disable_drain_error', $swallow_alarm, PHP_INT_MAX);
Reconciler::set_draining(true);
$recover_fixture_settings();
$assert(Reconciler::is_draining(), 'Settings reopened an unrecorded incident.');
$assert($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $drain_option)) === 'unrecorded-incident',
    'Ordinary closure downgraded an unrecorded incident fence.');

// This reset is solely fixture cleanup, never an operational recovery procedure.
$drain_fixture_write('no');
wp_cache_delete($drain_option, 'options');
delete_option('bactive_paymongo_settings_write_error');
$assert(!Reconciler::is_draining(), 'Disposable fixture cleanup failed.');
