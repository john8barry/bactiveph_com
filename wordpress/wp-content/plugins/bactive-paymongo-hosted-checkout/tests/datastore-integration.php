<?php
/**
 * Run with wp eval-file against a disposable, empty WordPress installation.
 * Never run against staging or production: the exact fixture identity is required.
 */

use Automattic\WooCommerce\Utilities\OrderUtil;
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Readiness;
use BActive\PayMongo\Reconciler;
use BActive\PayMongo\Secrets;

if (PHP_SAPI !== 'cli'
    || !defined('WP_CLI') || !WP_CLI
    || DB_NAME !== 'bactive_payment_integration'
    || get_option('home') !== 'https://bactive-payment-integration.invalid') {
    throw new RuntimeException('Disposable integration fixture identity required.');
}

$expected_hpos = getenv('BACTIVE_INTEGRATION_STORE') === 'hpos';
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($label);
    }
};
$assert(OrderUtil::custom_orders_table_usage_is_enabled() === $expected_hpos, 'Wrong authoritative datastore.');
$assert(get_option('woocommerce_bactive_paymongo_settings', false) === false, 'Fixture settings must start absent.');
wp_set_current_user(1);
$assert(current_user_can('manage_woocommerce'), 'Fixture manager unavailable.');

// No customer mail or external HTTP is permitted from this synthetic fixture.
add_filter('pre_wp_mail', static fn() => true);
$hook = null;
$webhook_creates = 0;
add_filter('pre_http_request', static function ($pre, array $args, string $url) use (&$hook, &$webhook_creates) {
    $path = wp_parse_url($url, PHP_URL_PATH);
    if (wp_parse_url($url, PHP_URL_HOST) !== 'api.paymongo.com') {
        return new WP_Error('fixture_external_http_blocked');
    }
    if ($path === '/v1/merchants/capabilities/payment_methods') {
        $body = array('qrph', 'paymaya', 'shopee_pay', 'dob', 'dob_ubp');
    } elseif ($path === '/v1/webhooks' && ($args['method'] ?? 'GET') === 'POST') {
        ++$webhook_creates;
        $hook = array('id' => 'hook_disposable_integration', 'type' => 'webhook', 'attributes' => array(
            'url' => Readiness::endpoint_url(false),
            'status' => 'enabled',
            'events' => array('checkout_session.payment.paid'),
            'livemode' => false,
            'secret_key' => 'whsk_disposable_integration_fixture_only',
        ));
        $body = array('data' => $hook);
    } elseif ($path === '/v1/webhooks') {
        $body = array('data' => $hook ? array($hook) : array());
    } else {
        return new WP_Error('fixture_unexpected_provider_request');
    }
    return array('headers' => array(), 'body' => wp_json_encode($body), 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array());
}, PHP_INT_MAX, 3);

$max_metadata_joins = 0;
add_filter('woocommerce_orders_table_query_clauses', static function (array $clauses) use (&$max_metadata_joins, $assert): array {
    $joins = substr_count(strtoupper($clauses['join'] ?? ''), 'JOIN');
    $max_metadata_joins = max($max_metadata_joins, $joins);
    // Fail before executing the previous combinatorial query.
    $assert($joins <= 1, 'HPOS payment discovery must not multiply metadata joins.');
    return $clauses;
}, PHP_INT_MAX);
$unsupported_notices = array();
add_action('doing_it_wrong_run', static function ($function, $message) use (&$unsupported_notices): void {
    if (str_contains((string) $message, 'not supported on the current order datastore')) {
        $unsupported_notices[] = $function;
    }
}, 10, 2);

// Existing ordinary orders must not become candidates or stall a first save.
for ($i = 0; $i < 8; ++$i) {
    $order = wc_create_order(array('created_via' => 'disposable-paymongo-integration'));
    for ($j = 0; $j < 25; ++$j) {
        $order->update_meta_data('unrelated_fixture_' . $j, 'unrelated');
    }
    $order->save();
}
$scan = new ReflectionMethod(Reconciler::class, 'source_order_ids');
$scan->setAccessible(true);
$assert($scan->invoke(null, 50, 1) === array(), 'Ordinary orders leaked into payment discovery.');
$started = microtime(true);
$gateway = new Gateway(false);
$fixture_key = 'sk_test_disposable_integration_fixture_only';
$fields = array('enabled' => '1', 'test_mode' => '1', 'restricted_rollout' => '1',
    'title' => $gateway->title, 'description' => $gateway->description,
    'test_secret_key' => $fixture_key, 'live_secret_key' => '');
$post = array();
foreach ($fields as $name => $value) {
    $post[$gateway->get_field_key($name)] = $value;
}
$gateway->set_post_data($post);
$gateway->process_admin_options();
$elapsed = microtime(true) - $started;
$gateway = new Gateway(false);
$assert($elapsed < 10, 'First settings save exceeded the bounded fixture deadline.');
$assert($gateway->enabled === 'yes' && !Reconciler::is_draining(), 'First settings save did not become ready.');
$assert(hash_equals($fixture_key, Secrets::api_key(false, $gateway)), 'Encrypted key readback failed.');
$assert(Readiness::is_ready($gateway, false), 'Webhook readiness did not survive the settings save.');
$assert($webhook_creates === 1, 'First settings save provisioned a duplicate webhook.');
$assert($gateway->get_option('restricted_rollout') === 'yes', 'First save lost manager-only issuance.');
require __DIR__ . '/settings-drain-datastore.php';
Reconciler::run();
$assert(wp_next_scheduled(Reconciler::CRON_HOOK) !== false, 'Recurring recovery schedule missing.');
$assert(get_option('bactive_paymongo_reconcile_scan_failed', false) === false, 'Empty recovery scan failed.');

// Use WooCommerce's real Action Scheduler store: its database uniqueness rule
// can cover a shared hook/group even when the order arguments differ.
$assert(function_exists('as_has_scheduled_action') && function_exists('as_schedule_single_action'),
    'Real Action Scheduler APIs must be available.');
$assert(get_class(ActionScheduler::store()) === ActionScheduler_DBStore::class,
    'Recovery scheduling must exercise the deployed Action Scheduler database store.');
$scheduled_orders = array();
for ($i = 0; $i < 3; ++$i) {
    $scheduled = wc_create_order(array('created_via' => 'disposable-paymongo-integration'));
    $scheduled_orders[] = $scheduled->get_id();
    Reconciler::schedule_order($scheduled->get_id());
    $assert(as_has_scheduled_action(Reconciler::ORDER_HOOK, array($scheduled->get_id()), 'bactive-paymongo'),
        'A pending recovery action for another order suppressed this order.');
}
foreach ($scheduled_orders as $scheduled_id) {
    Reconciler::schedule_order($scheduled_id);
    $actions = as_get_scheduled_actions(array('hook' => Reconciler::ORDER_HOOK,
        'args' => array($scheduled_id), 'group' => 'bactive-paymongo',
        'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => 10), 'ids');
    $assert(count($actions) === 1, 'Sequential scheduling duplicated the same order.');
}
$assert(count(as_get_scheduled_actions(array('hook' => Reconciler::ORDER_HOOK,
    'group' => 'bactive-paymongo', 'status' => ActionScheduler_Store::STATUS_PENDING,
    'per_page' => 10), 'ids')) === 3, 'Recovery did not retain one action per order.');
foreach ($scheduled_orders as $scheduled_id) {
    as_unschedule_all_actions(Reconciler::ORDER_HOOK, array($scheduled_id), 'bactive-paymongo');
}

// A zero action ID is a failed enqueue, not evidence that recovery is queued.
$failed_enqueue = static fn() => 0;
add_filter('pre_as_schedule_single_action', $failed_enqueue);
try {
    $fallback_order = wc_create_order(array('created_via' => 'disposable-paymongo-integration'));
    $fallback_args = array($fallback_order->get_id());
    Reconciler::schedule_order($fallback_order->get_id());
    $fallback_time = wp_next_scheduled(Reconciler::ORDER_HOOK, $fallback_args);
    $assert(is_int($fallback_time) && $fallback_time >= time() + 290,
        'Failed initial enqueue lost per-order WP-Cron recovery.');
    Reconciler::schedule_order($fallback_order->get_id());
    $assert(wp_next_scheduled(Reconciler::ORDER_HOOK, $fallback_args) === $fallback_time,
        'Repeated enqueue failure replaced the existing fallback.');
    wp_clear_scheduled_hook(Reconciler::ORDER_HOOK, $fallback_args);
    $next_order = new ReflectionMethod(Reconciler::class, 'schedule_next_order');
    $next_order->setAccessible(true);
    $next_order->invoke(null, $fallback_order->get_id(), 900);
    $fallback_time = wp_next_scheduled(Reconciler::ORDER_HOOK, $fallback_args);
    $assert(is_int($fallback_time) && $fallback_time >= time() + 890,
        'Failed retry enqueue lost its bounded WP-Cron delay.');
    $assert(!as_has_scheduled_action(Reconciler::ORDER_HOOK, $fallback_args, 'bactive-paymongo'),
        'Failed enqueue fixture unexpectedly stored an Action Scheduler job.');
    wp_clear_scheduled_hook(Reconciler::ORDER_HOOK, $fallback_args);
} finally {
    remove_filter('pre_as_schedule_single_action', $failed_enqueue);
}

$meta = new ReflectionMethod(Reconciler::class, 'source_meta_query');
$meta->setAccessible(true);
$keys = $meta->invoke(null)[0]['key'];
$assert(count($keys) === 17, 'Payment discovery key coverage changed.');
$expected = array();
foreach ($keys as $key) {
    $order = wc_create_order(array('created_via' => 'disposable-paymongo-integration'));
    $order->update_meta_data($key, '');
    $order->save();
    $expected[] = $order->get_id();
    $found = $scan->invoke(null, 50, 1);
    $assert(is_array($found) && in_array($order->get_id(), $found, true), 'An empty-valued payment marker was omitted.');
}
$order = wc_create_order(array('created_via' => 'disposable-paymongo-integration'));
foreach ($keys as $key) {
    $order->update_meta_data($key, '');
}
$order->save();
$expected[] = $order->get_id();
for ($i = 0; $i < 52; ++$i) {
    $order = wc_create_order(array('created_via' => 'disposable-paymongo-integration'));
    $order->update_meta_data('_bactive_paymongo_attempts', array());
    $order->save();
    $expected[] = $order->get_id();
}
$pages = array($scan->invoke(null, 50, 1), $scan->invoke(null, 50, 2), $scan->invoke(null, 50, 3));
$assert(count($pages[0]) === 50 && count($pages[1]) === 20 && $pages[2] === array(), 'Recovery pagination boundaries changed.');
$actual = array_merge(...$pages);
sort($expected);
$assert($actual === $expected, 'Recovery pagination omitted or duplicated payment orders.');
$assert($unsupported_notices === array(), 'CPT received unsupported metadata query arguments.');
$assert(!$expected_hpos || $max_metadata_joins === 1, 'HPOS fixture did not exercise the real single-join query.');
// Exercise a real database failure, with output suppressed only in this fixture.
global $wpdb;
$previous_suppression = $wpdb->suppress_errors(true);
$broken_sql = static fn() => 'SELECT * FROM bactive_disposable_missing_table';
$failure_hook = $expected_hpos ? 'woocommerce_orders_table_query_sql' : 'posts_request';
add_filter($failure_hook, $broken_sql, PHP_INT_MAX);
try {
    $assert(is_wp_error($scan->invoke(null, 50, 1)), 'A database failure was mistaken for an empty recovery queue.');
} finally {
    remove_filter($failure_hook, $broken_sql, PHP_INT_MAX);
    $wpdb->suppress_errors($previous_suppression);
}
require __DIR__ . '/abandoned-session-datastore.php';
echo wp_json_encode(array('datastore' => $expected_hpos ? 'hpos' : 'cpt', 'checks' => $checks,
    'action_scheduler_store' => get_class(ActionScheduler::store()),
    'first_settings_save_seconds' => round($elapsed, 3), 'payment_candidates' => count($actual),
    'max_hpos_metadata_joins' => $max_metadata_joins, 'unsupported_query_notices' => count($unsupported_notices))) . "\n";
