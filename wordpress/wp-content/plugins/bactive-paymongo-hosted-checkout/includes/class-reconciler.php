<?php

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

/**
 * Durable recovery for Checkout Sessions whose webhook delivery was missed.
 *
 * Order metadata is the source of truth. Per-order Action Scheduler jobs give
 * prompt retries, while a rotating WP-Cron scan backfills jobs after scheduler
 * loss without relying on a lossy shared tracking option.
 */
final class Reconciler
{
    public const CRON_HOOK = 'bactive_paymongo_reconcile_sessions';
    public const ORDER_HOOK = 'bactive_paymongo_reconcile_order';
    public const REQUIRED_META = '_bactive_paymongo_reconcile_required';
    public const UNRESOLVED_META = '_bactive_paymongo_settlement_unresolved';
    public const CONFIG_GENERATION_OPTION = 'bactive_paymongo_config_generation';

    private const CURSOR_OPTION = 'bactive_paymongo_reconcile_scan_page';
    private const ACTION_GROUP = 'bactive-paymongo';
    private const BATCH_SIZE = 50;
    private const DRAIN_BATCH_SIZE = 100;
    private const DRAIN_MAX_BATCHES = 100;
    private static string $deactivation_fingerprint = '';

    /** @return array<int,array<string,mixed>> */
    private static function source_meta_query(): array
    {
        // Separate OR/EXISTS clauses create one HPOS metadata join per key.
        // A single key-IN clause preserves discovery without multiplying rows.
        return array(array(
            'key' => array(
                self::REQUIRED_META,
                self::UNRESOLVED_META,
                '_bactive_paymongo_review_required',
                '_bactive_paymongo_settlement_pending',
                '_bactive_paymongo_settlement_pending_mode',
                '_bactive_paymongo_resolved_payment_pending',
                '_bactive_paymongo_unexpected_payment_id',
                '_bactive_paymongo_unexpected_payment_mode',
                '_bactive_paymongo_paid_event_id',
                '_bactive_paymongo_paid_session_id',
                '_bactive_paymongo_source_method',
                '_bactive_paymongo_source_provider',
                '_bactive_paymongo_paid_mode',
                '_bactive_paymongo_processing_incident_mode',
                '_bactive_paymongo_review_effect_mode',
                '_bactive_paymongo_review_mode',
                '_bactive_paymongo_attempts',
            ),
            'compare_key' => 'IN',
            'compare' => 'EXISTS',
        ));
    }

    /**
     * WooCommerce's CPT datastore ignores top-level meta_query arguments.
     * Translate only our internal scan marker at its supported WP_Query hook.
     *
     * @param array<string,mixed> $wp_args
     * @param array<string,mixed> $query_vars
     * @return array<string,mixed>
     */
    public static function filter_cpt_source_query(array $wp_args, array $query_vars): array
    {
        if (($query_vars['bactive_paymongo_source_scan'] ?? false) !== true) {
            return $wp_args;
        }
        $source_query = self::source_meta_query();
        $wp_args['meta_query'] = empty($wp_args['meta_query'])
            ? $source_query
            : array('relation' => 'AND', $wp_args['meta_query'], $source_query);
        return $wp_args;
    }

    /** @param array<string,array<string,int|string>> $schedules */
    public static function cron_schedules(array $schedules): array
    {
        $schedules['bactive_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every five minutes (B Active PayMongo)', 'bactive-paymongo'),
        );
        return $schedules;
    }

    public static function ensure_scheduled(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'bactive_five_minutes', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::ORDER_HOOK);
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('', array(), self::ACTION_GROUP);
        }
    }

    /**
     * Mark the caller's already-locked order for durable reconciliation. The
     * caller must persist the order after this method returns.
     */
    public static function mark_required(\WC_Order $order): void
    {
        $order->update_meta_data(self::REQUIRED_META, 'yes');
        self::schedule_order($order->get_id());
    }

    public static function schedule_order(int $order_id): void
    {
        if ($order_id < 1) {
            return;
        }

        $args = array($order_id);
        if (function_exists('as_has_scheduled_action')
            && function_exists('as_schedule_single_action')) {
            if (as_has_scheduled_action(self::ORDER_HOOK, $args, self::ACTION_GROUP)) {
                return;
            }
            // Action Scheduler's database uniqueness covers the shared hook
            // and group, not these order arguments. Keep the exact-order
            // precheck above; order locks make concurrent duplicate jobs safe.
            $action_id = as_schedule_single_action(
                time() + 300,
                self::ORDER_HOOK,
                $args,
                self::ACTION_GROUP,
                false
            );
            if ($action_id > 0) {
                return;
            }
            // A failed enqueue must retain the per-order WP-Cron fallback.
        }

        if (!wp_next_scheduled(self::ORDER_HOOK, $args)) {
            wp_schedule_event(time() + 300, 'bactive_five_minutes', self::ORDER_HOOK, $args);
        }
    }

    public static function run_order(int $order_id): void
    {
        self::reconcile_order($order_id);

        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            self::schedule_next_order($order_id, 900);
            return;
        }
        if (Webhook::has_pending_reviews($order_id)
            || Gateway::has_outstanding_attempts($order)
            || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== ''
            || (string) $order->get_meta(self::UNRESOLVED_META, true) !== ''
            || (string) $order->get_meta('_bactive_paymongo_resolved_payment_pending', true) !== ''
            || Webhook::review_resolution_recovery_pending($order)
            || Webhook::operator_disposition_recovery_pending($order)
            || Gateway::has_inconsistent_provider_payment_state($order)) {
            $poll_count = max(0, (int) $order->get_meta('_bactive_paymongo_reconcile_poll_count', true));
            $delay = min(3600, 300 * (2 ** min(4, $poll_count)));
            self::schedule_next_order($order_id, $delay);
        }
    }

    /**
     * Rotate through every order that has ever held one of our attempts. This
     * is a source-of-truth backfill, not the primary per-order work queue.
     */
    public static function run(): void
    {
        $page = max(1, (int) get_option(self::CURSOR_OPTION, 1));
        $order_ids = self::source_order_ids(self::BATCH_SIZE, $page);
        if (is_wp_error($order_ids)) {
            update_option('bactive_paymongo_reconcile_scan_failed', time(), false);
            return;
        }

        if ($order_ids === array()) {
            update_option(self::CURSOR_OPTION, 1, false);
            return;
        }

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order instanceof \WC_Order) {
                // A transient read miss is not authority to forget an order or
                // cancel its recovery schedule.
                self::schedule_order($order_id);
                continue;
            }
            if (self::order_requires_reconciliation($order)) {
                self::schedule_order($order_id);
            } else {
                self::unschedule_order($order_id);
            }
        }

        delete_option('bactive_paymongo_reconcile_scan_failed');
        update_option(
            self::CURSOR_OPTION,
            count($order_ids) < self::BATCH_SIZE ? 1 : $page + 1,
            false
        );
    }

    public static function reconcile_order(int $order_id): bool
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            self::schedule_order($order_id);
            return false;
        }

        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            self::schedule_order($order_id);
            return false;
        }

        try {
            if (!self::refresh_order($order)) {
                self::record_external_failure($order_id, 'reconciliation_order_read_failed');
                self::schedule_order($order_id);
                return false;
            }

            Webhook::acknowledge_attached_pending_reviews($order);
            Webhook::promote_pending_review($order);
            if (!self::refresh_order($order)) {
                self::schedule_order($order_id);
                return false;
            }

            $attempts = Gateway::order_attempts($order);
            $settlement_pending = (string) $order->get_meta('_bactive_paymongo_settlement_pending', true);
            $settlement_mode = (string) $order->get_meta('_bactive_paymongo_settlement_pending_mode', true);
            if (($settlement_pending === '') !== ($settlement_mode === '')
                || ($settlement_mode !== '' && !in_array($settlement_mode, array('test', 'live'), true))) {
                self::flag_failure($order, 'settlement_mode_inconsistent', 'local');
                self::schedule_order($order_id);
                return false;
            }
            foreach ($attempts as $index => $attempt) {
                $session_id = (string) ($attempt['session_id'] ?? '');
                $mode = (string) ($attempt['mode'] ?? '');
                $review_mode = in_array($mode, array('test', 'live'), true) ? $mode : 'local';
                $is_settlement_retry = $settlement_pending !== ''
                    && hash_equals($settlement_pending, (string) ($attempt['payment_id'] ?? ''))
                    && hash_equals($settlement_mode, $mode);
                if ($session_id === '' && !empty($attempt['request_pending'])) {
                    $started_at = (int) ($attempt['request_started_at'] ?? $attempt['created_at'] ?? 0);
                    if ($started_at < 1) {
                        self::flag_failure($order, 'checkout_creation_time_invalid', $review_mode);
                        break;
                    }
                    if ((time() - $started_at) >= 82800) {
                        if (!Order_Lock::renew($order_id)) {
                            self::record_external_failure($order_id, 'reconciliation_order_lock_lost');
                            self::schedule_order($order_id);
                            return false;
                        }
                        $attempts[$index]['request_pending'] = false;
                        $attempts[$index]['request_ambiguous_at'] = time();
                        $order->update_meta_data('_bactive_paymongo_attempts', $attempts);
                        self::flag_failure($order, 'checkout_creation_ambiguous', $review_mode);
                        break;
                    }
                    continue;
                }
                if ($session_id === ''
                    || (!empty($attempt['expired_at']) && !$is_settlement_retry)
                    || ((!empty($attempt['paid_at']) || !empty($attempt['payment_id'])) && !$is_settlement_retry)) {
                    continue;
                }

                if (!Order_Lock::renew($order_id)) {
                    self::record_external_failure($order_id, 'reconciliation_order_lock_lost');
                    self::schedule_order($order_id);
                    return false;
                }

                if (!in_array($mode, array('test', 'live'), true)) {
                    self::flag_failure($order, 'reconciliation_mode_invalid', 'local');
                    continue;
                }
                $live = $mode === 'live';
                $key = Secrets::api_key($live, new Gateway(false));
                if ($key === '') {
                    self::record_external_failure($order_id, 'reconciliation_key_missing');
                    continue;
                }

                $response = (new Api_Client($key))->retrieve_checkout_session($session_id);
                if (!Order_Lock::renew($order_id)) {
                    self::record_external_failure($order_id, 'reconciliation_order_lock_lost');
                    self::schedule_order($order_id);
                    return false;
                }
                if (is_wp_error($response)) {
                    self::record_external_failure($order_id, 'reconciliation_api_failed');
                    continue;
                }

                $status = Integrity::checkout_session_status($response, $session_id, $live);
                if ($status === null) {
                    self::flag_failure($order, 'reconciliation_readback_invalid', $mode);
                    continue;
                }

                // Inspect authenticated payment facts before treating an
                // expired session as harmless; paid and expired are distinct
                // fields in the Checkout Session resource.
                $result = Webhook::reconcile_checkout_session($order, $response, $attempt, $live);
                if (!Order_Lock::held_by_request($order_id)
                    || !Order_Lock::renew($order_id)) {
                    self::record_external_failure($order_id, 'reconciliation_order_lock_lost');
                    self::schedule_order($order_id);
                    return false;
                }
                if ($result === 'invalid') {
                    self::flag_failure($order, 'reconciliation_settlement_invalid', $mode);
                    break;
                }
                if ($result === 'retry') {
                    self::record_external_failure($order_id, 'reconciliation_settlement_retry');
                    break;
                }
                if ($result === 'payment_pending') {
                    // Session expiry is not terminal while a Payment is pending
                    // or its Intent is still processing. Leave the attempt unexpired and in the durable
                    // retry queue so a later paid/failed readback is observed.
                    break;
                }
                if ($result !== 'pending') {
                    // Payment processing refreshes and saves the order. Never
                    // overwrite it with the earlier attempt snapshot.
                    break;
                }

                if ($status === 'expired') {
                    if (!Order_Lock::renew($order_id)) {
                        self::record_external_failure($order_id, 'reconciliation_order_lock_lost');
                        self::schedule_order($order_id);
                        return false;
                    }
                    $attempts[$index]['expired_at'] = time();
                    $order->update_meta_data('_bactive_paymongo_attempts', $attempts);
                    $order->save();
                    continue;
                }

                $created_at = (int) ($attempt['created_at'] ?? 0);
                if ($created_at < 1) {
                    self::flag_failure($order, 'reconciliation_created_at_invalid', $mode);
                    break;
                }
                if ((time() - $created_at) >= 82800) {
                    // Age-based expiry affects every outstanding attempt. An
                    // existing order-level hold requires provider readback and
                    // review, not another automatic expiry request. Keep reading
                    // other attempts, and retain the scheduled recovery job.
                    if ((string) $order->get_meta(self::UNRESOLVED_META, true) !== ''
                        || (string) $order->get_meta('_bactive_paymongo_review_required', true) !== ''
                        || Webhook::has_pending_reviews($order_id)) {
                        continue;
                    }
                    if (!(new Gateway(false))->expire_all_for_order($order)) {
                        if (!Order_Lock::held_by_request($order_id)
                            || !Order_Lock::renew($order_id)) {
                            self::record_external_failure($order_id, 'reconciliation_order_lock_lost');
                            self::schedule_order($order_id);
                            return false;
                        }
                        self::flag_failure($order, 'reconciliation_abandoned_expiry_failed', $mode);
                    }
                    break;
                }
            }

            if (!self::refresh_order($order)) {
                self::record_external_failure($order_id, 'reconciliation_final_read_failed');
                self::schedule_order($order_id);
                return false;
            }
            if (!Order_Lock::renew($order_id)) {
                self::record_external_failure($order_id, 'reconciliation_order_lock_lost');
                self::schedule_order($order_id);
                return false;
            }

            // Torn operator actions intentionally permit exact before/after
            // field values while an external processing intent remains
            // durable. Do not replace either recoverable state with a generic
            // inconsistency review.
            if (!Webhook::review_resolution_recovery_pending($order)
                && !Webhook::operator_disposition_recovery_pending($order)
                && Gateway::has_inconsistent_provider_payment_state($order)
                && (string) $order->get_meta(self::UNRESOLVED_META, true) === '') {
                self::flag_failure($order, 'provider_payment_state_inconsistent');
                $readback = wc_get_order($order_id);
                if (!$readback instanceof \WC_Order
                    || !self::refresh_order($readback)
                    || (string) $readback->get_meta(self::UNRESOLVED_META, true)
                        !== 'provider_payment_state_inconsistent'
                    || (string) $readback->get_meta('_bactive_paymongo_review_required', true)
                        !== 'provider_payment_state_inconsistent') {
                    self::record_external_failure($order_id, 'provider_payment_state_review_persist_failed');
                    self::schedule_order($order_id);
                    return false;
                }
                $order = $readback;
            }

            if (self::order_requires_reconciliation_without_marker($order)) {
                self::mark_required($order);
                if (Gateway::has_outstanding_attempts($order)
                    || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== '') {
                    $order->update_meta_data(
                        '_bactive_paymongo_reconcile_poll_count',
                        max(0, (int) $order->get_meta('_bactive_paymongo_reconcile_poll_count', true)) + 1
                    );
                }
                $order->save();
            } else {
                $order->delete_meta_data(self::REQUIRED_META);
                $order->delete_meta_data('_bactive_paymongo_reconcile_poll_count');
                $order->save();
                self::unschedule_order($order_id);
            }
            return true;
        } finally {
            if (!$held_by_request) {
                Order_Lock::release($order_id);
            }
        }
    }

    /** @return true|\WP_Error */
    public static function expire_all_tracked(Gateway $gateway, ?callable $heartbeat = null)
    {
        // Always consume page 1. Successful reconciliation removes the source
        // marker, so offset pagination over this shrinking set can skip the
        // former first row of page 2. Re-reading page 1 is stable and also
        // catches a session committed by an in-flight checkout during drain.
        $seen = array();
        for ($batch = 0; $batch <= self::DRAIN_MAX_BATCHES; ++$batch) {
            if ($heartbeat !== null && !$heartbeat()) {
                return new \WP_Error(
                    'paymongo_settings_lock_lost',
                    'PayMongo settings ownership was lost during the drain.'
                );
            }
            $order_ids = self::source_order_ids(self::DRAIN_BATCH_SIZE, 1, false);
            if (is_wp_error($order_ids)) {
                return $order_ids;
            }
            if ($order_ids === array()) {
                return true;
            }
            if ($batch === self::DRAIN_MAX_BATCHES) {
                return new \WP_Error(
                    'paymongo_drain_scan_limit',
                    'PayMongo drain exceeded its bounded source scan and remains closed.'
                );
            }

            $failed = array();
            foreach ($order_ids as $order_id) {
                if ($heartbeat !== null && !$heartbeat()) {
                    return new \WP_Error(
                        'paymongo_settings_lock_lost',
                        'PayMongo settings ownership was lost during the drain.'
                    );
                }
                if (isset($seen[$order_id])) {
                    $failed[] = $order_id;
                    continue;
                }
                $seen[$order_id] = true;

                if (!self::reconcile_order($order_id)) {
                    $failed[] = $order_id;
                    continue;
                }
                $order = wc_get_order($order_id);
                if (!$order instanceof \WC_Order || !self::refresh_order($order)) {
                    $failed[] = $order_id;
                    continue;
                }

                if (Gateway::has_outstanding_attempts($order) && !$gateway->expire_all_for_order($order)) {
                    $failed[] = $order_id;
                    self::flag_failure_for_order($order_id, 'rollback_session_expiry_unverified');
                    continue;
                }
                if (!self::finalize_drained_order($order_id)) {
                    $failed[] = $order_id;
                }
            }

            if ($failed !== array()) {
                return new \WP_Error(
                    'paymongo_active_sessions_remain',
                    sprintf('%d PayMongo order(s) still require reconciliation.', count(array_unique($failed)))
                );
            }
        }

        return new \WP_Error('paymongo_drain_incomplete', 'PayMongo drain did not reach an empty source scan.');
    }

    /**
     * Deactivation is allowed only after a source-of-truth scan and exact
     * provider drain. Hiding the gateway never disables webhook recovery.
     */
    public static function guard_deactivation(): void
    {
        $fingerprint = hash('sha256', 'deactivate|' . PLUGIN_FILE . '|' . self::config_generation());
        if (!Order_Lock::acquire_settings($fingerprint)) {
            wp_die(
                esc_html__('Another PayMongo settings or lifecycle operation is active. Deactivation was not started.', 'bactive-paymongo'),
                esc_html__('PayMongo deactivation blocked', 'bactive-paymongo'),
                array('response' => 409, 'back_link' => true)
            );
        }
        self::set_draining(true);
        $result = class_exists(Gateway::class)
            ? self::expire_all_tracked(
                new Gateway(false),
                static fn(): bool => Order_Lock::renew_settings($fingerprint)
            )
            : new \WP_Error('paymongo_gateway_unavailable', 'The PayMongo gateway could not be loaded for safe deactivation.');
        if (is_wp_error($result) || self::has_tracked_orders()
            || self::has_unresolved_external_incidents()
            || !Order_Lock::renew_settings($fingerprint)) {
            Order_Lock::release_settings();
            wp_die(
                esc_html__('PayMongo still has active or unresolved Checkout Sessions. The gateway is hidden, but this plugin and both webhooks must remain active until every item is reconciled.', 'bactive-paymongo'),
                esc_html__('PayMongo deactivation blocked', 'bactive-paymongo'),
                array('response' => 409, 'back_link' => true)
            );
        }
        self::$deactivation_fingerprint = $fingerprint;
        // Keep recovery scheduled and the lease held until WordPress actually
        // commits the active-plugin list. Shutdown releases an aborted write.
    }

    public static function guard_plugin_list_update($option, $old_value, $value): void
    {
        if ($option !== 'active_plugins' || !is_array($old_value)) {
            return;
        }
        $plugin = plugin_basename(PLUGIN_FILE);
        if (!in_array($plugin, $old_value, true) || (is_array($value) && in_array($plugin, $value, true))) {
            return;
        }
        if (!is_array($value)) {
            throw new \Error('PayMongo cannot be deactivated by a malformed active-plugin list.');
        }
        self::verify_deactivation_commit();
    }

    public static function guard_network_plugin_list_update($value, $old_value)
    {
        $plugin = plugin_basename(PLUGIN_FILE);
        if (is_array($old_value) && isset($old_value[$plugin])
            && (!is_array($value) || !isset($value[$plugin]))) {
            self::verify_deactivation_commit();
        }
        return $value;
    }

    private static function verify_deactivation_commit(): void
    {
        if (self::$deactivation_fingerprint === ''
            || !Order_Lock::renew_settings(self::$deactivation_fingerprint)
            || self::has_tracked_orders() || self::has_unresolved_external_incidents()) {
            self::set_draining(true);
            throw new \Error('PayMongo deactivation lost its verified drain or settings lease before storage.');
        }
    }

    public static function after_plugin_list_update($option, $value = null): void
    {
        if (!in_array($option, array('active_plugins', 'active_sitewide_plugins'), true)
            || self::$deactivation_fingerprint === '') {
            return;
        }
        $stored = $option === 'active_plugins' ? get_option($option, null) : get_site_option($option, null);
        $plugin = plugin_basename(PLUGIN_FILE);
        if (is_array($stored)
            && ($option === 'active_plugins' ? !in_array($plugin, $stored, true) : !isset($stored[$plugin]))
            && Order_Lock::renew_settings(self::$deactivation_fingerprint)) {
            self::unschedule();
        }
        self::$deactivation_fingerprint = '';
        Order_Lock::release_settings();
    }

    public static function has_tracked_orders(): bool
    {
        $order_ids = self::source_order_ids(1, 1, false);
        return is_wp_error($order_ids) || $order_ids !== array();
    }

    public static function has_review_state(): bool
    {
        $pending_ids = Webhook::pending_review_order_ids();
        if (is_wp_error($pending_ids) || $pending_ids !== array()) {
            return true;
        }
        if (get_option('bactive_paymongo_disable_drain_error', false) !== false) {
            return true;
        }
        for ($page = 1; $page <= self::DRAIN_MAX_BATCHES; ++$page) {
            $order_ids = self::source_order_ids(self::BATCH_SIZE, $page, false);
            if (is_wp_error($order_ids)) {
                return true;
            }
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order instanceof \WC_Order || !self::refresh_order($order)) {
                    return true;
                }
                if (is_callable(array($order, 'read_meta_data'))) {
                    $order->read_meta_data(true);
                }
                if ((string) $order->get_meta(self::UNRESOLVED_META, true) !== ''
                    || (string) $order->get_meta('_bactive_paymongo_review_required', true) !== ''
                    || (string) $order->get_meta('_bactive_paymongo_processing_incident_code', true) !== ''
                    || Webhook::review_resolution_recovery_pending($order)
                    || Webhook::ambiguous_effects_action_available($order)) {
                    return true;
                }
            }
            if (count($order_ids) < self::BATCH_SIZE) {
                return false;
            }
        }
        return true;
    }

    /** Independent ledgers must be drained even when an order write failed. */
    public static function has_unresolved_external_incidents(): bool
    {
        global $wpdb;
        $global_incident = self::read_incident_option('bactive_paymongo_disable_drain_error', null);
        if ($global_incident !== null
            && (!is_array($global_incident) || ($global_incident['owner'] ?? '') !== 'settings')) {
            return true;
        }
        $prefixes = array(
            'bactive_paymongo_processing_incident_',
            'bactive_paymongo_processing_review_',
            'bactive_paymongo_quarantine_',
            'bactive_paymongo_effects_',
            'bactive_paymongo_operator_disposition_',
            'bactive_paymongo_review_resolution_',
            'bactive_paymongo_review_test_',
            'bactive_paymongo_review_live_',
            'bactive_paymongo_review_local_',
            'bactive_paymongo_pending_reviews_',
        );
        if (defined('BACTIVE_PAYMONGO_TESTING')) {
            $names = array_keys((array) ($GLOBALS['fake_options'] ?? array()));
            $names = array_values(array_filter($names, static function (string $name) use ($prefixes): bool {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        return true;
                    }
                }
                return false;
            }));
        } elseif (is_object($wpdb) && is_callable(array($wpdb, 'get_col'))) {
            $clauses = array_fill(0, count($prefixes), 'option_name LIKE %s');
            $patterns = array_map(static fn(string $prefix): string => $wpdb->esc_like($prefix) . '%', $prefixes);
            $names = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE (" . implode(' OR ', $clauses) . ') ORDER BY option_name LIMIT 10001',
                $patterns
            ));
            if (!is_array($names) || $wpdb->last_error !== '') {
                return true;
            }
        } else {
            return true;
        }
        if (count($names) > 10000) {
            return true;
        }
        foreach ($names as $name) {
            $record = self::read_incident_option($name, null);
            if (!is_array($record)) {
                return true;
            }
            if (str_starts_with($name, 'bactive_paymongo_pending_reviews_')) {
                if ($record !== array()) {
                    return true;
                }
                continue;
            }
            if (str_starts_with($name, 'bactive_paymongo_quarantine_')
                && !str_starts_with($name, 'bactive_paymongo_quarantine_retry_')) {
                if (($record['order_annotated'] ?? null) !== true) {
                    return true;
                }
                continue;
            }
            if (str_starts_with($name, 'bactive_paymongo_effects_')
                || str_starts_with($name, 'bactive_paymongo_operator_disposition_')
                || str_starts_with($name, 'bactive_paymongo_review_resolution_')) {
                if (($record['status'] ?? '') !== 'done') {
                    return true;
                }
                continue;
            }
            return true;
        }
        return false;
    }

    /** A settings drain can clear only its own exact summary, never an event failure. */
    public static function clear_settings_drain_error(): bool
    {
        $option = 'bactive_paymongo_disable_drain_error';
        $record = get_option($option, null);
        if ($record === null) {
            return true;
        }
        return is_array($record) && ($record['owner'] ?? '') === 'settings'
            && !self::has_tracked_orders() && !self::has_unresolved_external_incidents()
            && Order_Lock::renew_current_settings()
            && Order_Lock::delete_option_if_exact($option, $record);
    }

    public static function is_draining(): bool
    {
        // This is an issuance fence. A request-local option cache must not hide
        // a newer webhook hold, and missing/malformed/read-error states close it.
        return self::read_drain_value() !== 'no';
    }

    public static function set_draining(bool $draining): void
    {
        if (!$draining) {
            throw new \LogicException('PayMongo reopening requires a verified drain fence.');
        }
        $previous = self::read_drain_value();
        // Always write through to the database: cached `yes` may conceal the
        // settings writer's newer verification token. Closing invalidates it.
        if (!self::write_drain_value('yes')) {
            throw new \RuntimeException('PayMongo drain fence could not be persisted.');
        }
        if ($previous === 'no' && function_exists('do_action')) {
            do_action('bactive_paymongo_availability_changed');
        }
    }

    /** Publish the alarm before closing; an unrecorded failure cannot be reopened by settings. */
    public static function record_global_drain_error(array $record): void
    {
        $option = 'bactive_paymongo_disable_drain_error';
        try {
            update_option($option, $record, false);
            $recorded = self::read_incident_option($option, null) === $record;
        } catch (\Throwable $error) {
            $recorded = false;
        }
        if (!$recorded) {
            if (!self::write_drain_value('unrecorded-incident')) {
                throw new \RuntimeException('PayMongo incident fence could not be persisted.');
            }
            // This exceptional close can bypass ordinary drain publication.
            // Purge claims even if a concurrent writer changed the prior value.
            if (function_exists('do_action')) {
                do_action('bactive_paymongo_availability_changed');
            }
            return;
        }
        self::set_draining(true);
    }

    /** Start before readiness and review scans; later closures invalidate this token. */
    public static function begin_reopen_verification(bool $explicit_recovery = false): string
    {
        if (!Order_Lock::renew_current_settings()) {
            return '';
        }
        try {
            $fence = 'verifying:' . bin2hex(random_bytes(16));
        } catch (\Throwable $error) {
            return '';
        }
        $previous = self::read_drain_value();
        if ($previous === 'no' || ($explicit_recovery && $previous === 'yes')) {
            if (!self::write_drain_value($fence, $previous)) {
                return '';
            }
            if ($previous === 'no' && function_exists('do_action')) {
                do_action('bactive_paymongo_availability_changed');
            }
            return $fence;
        }
        // Initial setup may insert only an actually absent option, never
        // overwrite a concurrent closure or an unreadable existing value.
        return $explicit_recovery && $previous === null && self::write_drain_value($fence, null, true)
            && self::read_drain_value() === $fence ? $fence : '';
    }

    /** The only reopening lane; a concurrent incident wins over the final CAS. */
    public static function reopen_after_verification(string $fence): bool
    {
        try {
            if (!preg_match('/^verifying:[a-f0-9]{32}$/D', $fence)
                || self::has_review_state()
                || self::has_unresolved_external_incidents()
                || !Order_Lock::renew_current_settings()
                || !self::write_drain_value('no', $fence)) {
                return false;
            }
        } catch (\Throwable $error) {
            return false;
        }
        if (function_exists('do_action')) {
            do_action('bactive_paymongo_availability_changed');
        }
        return true;
    }

    private static function read_drain_value(): string|\WP_Error|null
    {
        $value = self::read_incident_option('bactive_paymongo_draining', null, true);
        return is_string($value) || is_wp_error($value) || $value === null
            ? $value : new \WP_Error('paymongo_drain_shape_invalid', 'PayMongo drain value is invalid.');
    }

    /** Read recovery evidence from its source of truth, bypassing cached absent/done values. */
    public static function read_incident_option(string $option, $default = null, bool $raw = false)
    {
        global $wpdb;
        if (is_object($wpdb) && isset($wpdb->options)
            && is_callable(array($wpdb, 'prepare')) && is_callable(array($wpdb, 'get_var'))) {
            $value = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option
            ));
            if ($wpdb->last_error !== '') {
                return new \WP_Error('paymongo_incident_read_failed', 'PayMongo incident read failed safely.');
            }
            if ($value === null) {
                return $default;
            }
            return !$raw && is_string($value) && is_serialized($value)
                ? unserialize($value, array('allowed_classes' => false)) : $value;
        }
        if (defined('BACTIVE_PAYMONGO_TESTING') && BACTIVE_PAYMONGO_TESTING === true) {
            return get_option($option, $default);
        }
        return new \WP_Error('paymongo_incident_read_unavailable', 'PayMongo incident storage is unavailable.');
    }

    /** Null expectation closes unconditionally; reopening requires exact atomic replacement. */
    private static function write_drain_value(string $value, ?string $expected = null, bool $insert_only = false): bool
    {
        global $wpdb;
        $option = 'bactive_paymongo_draining';
        if (is_object($wpdb) && isset($wpdb->options)
            && is_callable(array($wpdb, 'prepare')) && is_callable(array($wpdb, 'query'))) {
            $query = $insert_only
                ? $wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $option, $value)
                : ($expected === null
                ? $wpdb->prepare(
                    "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')"
                        . " ON DUPLICATE KEY UPDATE option_value = CASE WHEN option_value = 'unrecorded-incident'"
                        . " THEN option_value ELSE VALUES(option_value) END, autoload = 'no'",
                    $option,
                    $value
                )
                : $wpdb->prepare(
                    "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
                    $value,
                    $option,
                    $expected
                ));
            $affected = $wpdb->query($query);
            if ($affected === false || $wpdb->last_error !== ''
                || (($expected !== null || $insert_only) && $affected !== 1)) {
                return false;
            }
            wp_cache_delete($option, 'options');
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
            return true;
        }
        // Only single-process synthetic fixtures may emulate an atomic write.
        if (!defined('BACTIVE_PAYMONGO_TESTING') || BACTIVE_PAYMONGO_TESTING !== true
            || ($expected !== null && self::read_drain_value() !== $expected)) {
            return false;
        }
        if ($expected === null && self::read_drain_value() === 'unrecorded-incident') {
            return true;
        }
        if ($insert_only) {
            return add_option($option, $value, '', false) && self::read_drain_value() === $value;
        }
        update_option($option, $value, false);
        return self::read_drain_value() === $value;
    }

    public static function config_generation(): int
    {
        return max(0, (int) get_option(self::CONFIG_GENERATION_OPTION, 0));
    }

    public static function bump_config_generation(): int
    {
        $next = self::config_generation() + 1;
        update_option(self::CONFIG_GENERATION_OPTION, $next, false);
        return $next;
    }

    /** @param array<string,string> $actions */
    public static function order_actions(array $actions, $order): array
    {
        if ($order instanceof \WC_Order && Webhook::ambiguous_effects_action_available($order)) {
            $actions['bactive_paymongo_resolve_effects_ambiguity'] = __(
                'Acknowledge verified PayMongo effects recovery',
                'bactive-paymongo'
            );
        }
        if ($order instanceof \WC_Order && Webhook::resolved_payment_disposition_action_available($order)) {
            $actions['bactive_paymongo_finalize_resolved_payment'] = __(
                'Record verified PayMongo payment (no effects)',
                'bactive-paymongo'
            );
        }
        if ($order instanceof \WC_Order
            && ((string) $order->get_meta(self::UNRESOLVED_META, true) !== ''
                || Webhook::review_resolution_recovery_action_available($order))) {
            $actions['bactive_paymongo_resolve_review'] = __(
                'Resolve PayMongo reconciliation review',
                'bactive-paymongo'
            );
        }
        return $actions;
    }

    /**
     * Human-only recovery for a crash during at-most-once WooCommerce effects.
     * The operator must first verify fulfillment, stock, and notifications;
     * Webhook performs a fresh provider GET and never re-emits those effects.
     */
    public static function resolve_effects_ambiguity($order): void
    {
        if (!$order instanceof \WC_Order || !current_user_can('manage_woocommerce')) {
            return;
        }
        $order_id = $order->get_id();
        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            return;
        }
        try {
            if (!self::refresh_order($order)
                || !Webhook::ambiguous_effects_action_available($order)
                || !Webhook::resolve_ambiguous_effects($order)) {
                self::record_external_failure($order_id, 'effects_resolution_verification_failed');
                self::schedule_order($order_id);
                return;
            }
            self::unschedule_order($order_id);
        } finally {
            if (!$held_by_request) {
                Order_Lock::release($order_id);
            }
        }
    }

    /**
     * Explicit human acknowledgement after provider/payment/refund facts were
     * reconciled. This never changes payment or fulfillment status itself.
     */
    public static function resolve_review($order): void
    {
        if (!$order instanceof \WC_Order || !current_user_can('manage_woocommerce')) {
            return;
        }

        $order_id = $order->get_id();
        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            return;
        }

        try {
            if (!self::refresh_order($order) || !Webhook::resolve_review_for_operator($order)) {
                self::record_external_failure($order_id, 'review_resolution_verification_failed');
                self::schedule_order($order_id);
                return;
            }
            self::unschedule_order($order_id);
        } finally {
            if (!$held_by_request) {
                Order_Lock::release($order_id);
            }
        }
    }

    /**
     * Complete the payment fields for one explicitly resolved paid quarantine.
     * The operator must already have verified or handled stock, customer mail,
     * fulfillment, and downstream integrations because no WooCommerce payment
     * or status effect is emitted by this action.
     */
    public static function finalize_resolved_payment($order): void
    {
        if (!$order instanceof \WC_Order || !current_user_can('manage_woocommerce')) {
            return;
        }

        $order_id = $order->get_id();
        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            return;
        }

        try {
            try {
                $finalized = self::refresh_order($order)
                    && Webhook::resolved_payment_disposition_action_available($order)
                    && Webhook::finalize_resolved_payment_for_operator($order);
            } catch (\Throwable $error) {
                $finalized = false;
            }
            if (!$finalized) {
                self::record_external_failure($order_id, 'resolved_payment_disposition_failed');
                self::schedule_order($order_id);
                return;
            }

            delete_option('bactive_paymongo_reconcile_diagnostic_' . $order_id);
            self::unschedule_order($order_id);
        } finally {
            if (!$held_by_request) {
                Order_Lock::release($order_id);
            }
        }
    }

    public static function record_review_incident(
        \WC_Order $order,
        bool $increment_global = true,
        bool $increment_order = true
    ): void
    {
        if ($increment_order) {
            $order->update_meta_data(
                '_bactive_paymongo_review_incidents',
                max(0, (int) $order->get_meta('_bactive_paymongo_review_incidents', true)) + 1
            );
        }
        // Global arithmetic cannot be made crash-safe across independent
        // order writers. Admin visibility is derived from durable order and
        // incident state instead of a mutable shared counter.
    }

    private static function order_requires_reconciliation(\WC_Order $order): bool
    {
        return (string) $order->get_meta(self::REQUIRED_META, true) === 'yes'
            || self::order_requires_reconciliation_without_marker($order);
    }

    private static function order_requires_reconciliation_without_marker(\WC_Order $order): bool
    {
        return Webhook::has_pending_reviews($order->get_id())
            || Gateway::has_outstanding_attempts($order)
            || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== ''
            || (string) $order->get_meta(self::UNRESOLVED_META, true) !== ''
            || (string) $order->get_meta('_bactive_paymongo_resolved_payment_pending', true) !== ''
            || Webhook::review_resolution_recovery_pending($order)
            || Webhook::operator_disposition_recovery_pending($order)
            || Gateway::has_inconsistent_provider_payment_state($order);
    }

    /** @return array<int,int>|\WP_Error */
    private static function source_order_ids(int $limit, int $page, bool $include_history = true)
    {
        $pending_ids = Webhook::pending_review_order_ids();
        if (is_wp_error($pending_ids)) {
            return $pending_ids;
        }
        $query = static function (int $query_limit, int $query_page) {
            global $wpdb;
            try {
                $args = array(
                    'limit' => $query_limit,
                    'page' => $query_page,
                    'return' => 'ids',
                    'orderby' => 'ID',
                    'order' => 'ASC',
                );
                if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)
                    && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                    $args['meta_query'] = self::source_meta_query();
                } else {
                    $args['bactive_paymongo_source_scan'] = true;
                }
                // Woo can return [] after a database failure. Never interpret
                // a failed scan as proof that no payment needs recovery.
                $wpdb->last_error = '';
                $ids = wc_get_orders($args);
                if ((string) ($wpdb->last_error ?? '') !== '') {
                    return new \WP_Error('paymongo_order_scan_failed', 'WooCommerce PayMongo order scan failed safely.');
                }
            } catch (\Throwable $error) {
                return new \WP_Error('paymongo_order_scan_failed', 'WooCommerce PayMongo order scan failed safely.');
            }
            if (!is_array($ids)) {
                return new \WP_Error('paymongo_order_scan_shape', 'WooCommerce returned an unexpected PayMongo order scan response.');
            }
            return array_values(array_unique(array_filter(array_map('absint', $ids))));
        };

        if ($include_history) {
            $ids = $query($limit, $page);
            return is_wp_error($ids) || $page !== 1
                ? $ids : array_values(array_unique(array_merge($ids, $pending_ids)));
        }

        // Provider evidence is intentionally retained as audit history. Query
        // it to recover torn writes, but post-filter coherent settled orders
        // so a drain can reach an empty active set without deleting evidence.
        $candidate_limit = max(self::BATCH_SIZE, $limit);
        $required = $pending_ids;
        $needed = max(1, $limit * $page);
        for ($candidate_page = 1; $candidate_page <= self::DRAIN_MAX_BATCHES; ++$candidate_page) {
            $candidate_ids = $query($candidate_limit, $candidate_page);
            if (is_wp_error($candidate_ids)) {
                return $candidate_ids;
            }
            foreach ($candidate_ids as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order instanceof \WC_Order || !self::refresh_order($order)) {
                    // An unreadable candidate may still be an active payment.
                    // Surface it to the caller, which will fail the drain.
                    $required[] = $order_id;
                    continue;
                }
                if (self::order_requires_reconciliation($order)) {
                    $required[] = $order_id;
                }
            }
            $required = array_values(array_unique($required));
            if (count($required) >= $needed || count($candidate_ids) < $candidate_limit) {
                $offset = max(0, ($page - 1) * $limit);
                return array_slice($required, $offset, $limit);
            }
        }

        return new \WP_Error(
            'paymongo_order_scan_limit',
            'PayMongo active-order discovery exceeded its bounded history scan.'
        );
    }

    private static function unschedule_order(int $order_id): void
    {
        $args = array($order_id);
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::ORDER_HOOK, $args, self::ACTION_GROUP);
        }
        wp_clear_scheduled_hook(self::ORDER_HOOK, $args);
    }

    private static function schedule_next_order(int $order_id, int $delay): void
    {
        $args = array($order_id);
        $delay = max(300, min(3600, $delay));
        if (function_exists('as_schedule_single_action')) {
            $action_id = as_schedule_single_action(
                time() + $delay,
                self::ORDER_HOOK,
                $args,
                self::ACTION_GROUP,
                false
            );
            if ($action_id > 0) {
                return;
            }
        }
        wp_schedule_single_event(time() + $delay, self::ORDER_HOOK, $args);
    }

    private static function refresh_order(\WC_Order $order): bool
    {
        try {
            $store = $order->get_data_store();
            if (!is_object($store) || !is_callable(array($store, 'read'))) {
                return false;
            }
            $store->read($order);
            return true;
        } catch (\Throwable $error) {
            return false;
        }
    }

    private static function flag_failure(
        \WC_Order $order,
        string $code,
        string $mode = 'local'
    ): void
    {
        if (!Order_Lock::renew($order->get_id())) {
            self::record_external_failure($order->get_id(), 'reconciliation_order_lock_lost');
            self::schedule_order($order->get_id());
            return;
        }
        $code = sanitize_key($code);
        $mode = in_array($mode, array('test', 'live', 'local'), true) ? $mode : 'local';
        $identity = self::review_incident_option($order->get_id(), $code, $mode);
        $already_linked = (string) $order->get_meta('_bactive_paymongo_review_required', true) === $code
            && (string) $order->get_meta(self::UNRESOLVED_META, true) === $code
            && (string) $order->get_meta('_bactive_paymongo_review_mode', true) === $mode
            && (int) $order->get_meta('_bactive_paymongo_review_incidents', true) > 0;
        $claim = array(
            'recorded_at' => time(),
            'order_id' => $order->get_id(),
            'code' => $code,
            'mode' => $mode,
        );
        $claim = Webhook::queue_review_incident($order, 'generic', $claim);
        if ($claim === null) {
            self::record_global_drain_error(array('recorded_at' => time(),
                'code' => 'review_inbox_persist_failed', 'order_id' => $order->get_id(), 'mode' => $mode));
            self::schedule_order($order->get_id());
            return;
        }
        $new_incident = Order_Lock::insert_option($identity, $claim);
        $stored_claim = get_option($identity, null);
        $incident_exists = is_array($stored_claim)
            && (int) ($stored_claim['order_id'] ?? 0) === $order->get_id()
            && (string) ($stored_claim['code'] ?? '') === $code
            && (string) ($stored_claim['mode'] ?? '') === $mode
            && (int) ($stored_claim['recorded_at'] ?? 0) > 0;
        if (!$incident_exists) {
            self::set_draining(true);
            self::schedule_order($order->get_id());
            return;
        }
        $active_review_values = array(
            (string) $order->get_meta('_bactive_paymongo_review_required', true),
            (string) $order->get_meta(self::UNRESOLVED_META, true),
            (string) $order->get_meta('_bactive_paymongo_review_mode', true),
            (string) $order->get_meta('_bactive_paymongo_review_effect_identity', true),
            (string) $order->get_meta('_bactive_paymongo_processing_incident_code', true),
            (string) $order->get_meta('_bactive_paymongo_review_incidents', true),
        );
        if (!$already_linked
            && array_filter($active_review_values, static fn(string $value): bool => $value !== '') !== array()) {
            self::set_draining(true);
            self::schedule_order($order->get_id());
            return;
        }
        $order->update_meta_data('_bactive_paymongo_review_required', $code);
        $order->update_meta_data(self::UNRESOLVED_META, $code);
        $order->update_meta_data('_bactive_paymongo_review_mode', $mode);
        self::mark_required($order);
        if ($new_incident) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: sanitized reconciliation reason */
                    __('PayMongo scheduled reconciliation requires review: %s.', 'bactive-paymongo'),
                    $code
                )
            );
        }
        if ($incident_exists && ($new_incident || !$already_linked)) {
            self::record_review_incident($order, $new_incident, !$already_linked);
        }
        $order->save();
        Webhook::acknowledge_attached_pending_reviews($order);
    }

    public static function review_incident_option(
        int $order_id,
        string $code,
        string $mode
    ): string {
        return 'bactive_paymongo_review_' . $mode . '_'
            . hash('sha256', $mode . '|' . $order_id . '|' . sanitize_key($code));
    }

    private static function record_external_failure(int $order_id, string $code): void
    {
        update_option(
            'bactive_paymongo_reconcile_diagnostic_' . $order_id,
            array('recorded_at' => time(), 'code' => sanitize_key($code)),
            false
        );
    }

    private static function flag_failure_for_order(int $order_id, string $code): void
    {
        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            self::record_external_failure($order_id, $code . '_lock_busy');
            self::schedule_order($order_id);
            return;
        }
        try {
            $order = wc_get_order($order_id);
            if (!$order instanceof \WC_Order || !self::refresh_order($order)) {
                self::record_external_failure($order_id, $code . '_read_failed');
                return;
            }
            self::flag_failure($order, $code);
        } finally {
            if (!$held_by_request) {
                Order_Lock::release($order_id);
            }
        }
    }

    private static function finalize_drained_order(int $order_id): bool
    {
        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            self::record_external_failure($order_id, 'rollback_final_lock_busy');
            return false;
        }
        try {
            $order = wc_get_order($order_id);
            if (!$order instanceof \WC_Order || !self::refresh_order($order)) {
                self::record_external_failure($order_id, 'rollback_final_read_failed');
                return false;
            }
            if (Webhook::has_pending_reviews($order_id)
                || Gateway::has_outstanding_attempts($order)
                || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== ''
                || (string) $order->get_meta(self::UNRESOLVED_META, true) !== ''
                || (string) $order->get_meta('_bactive_paymongo_resolved_payment_pending', true) !== ''
                || Webhook::review_resolution_recovery_pending($order)
                || Webhook::operator_disposition_recovery_pending($order)
                || Gateway::has_inconsistent_provider_payment_state($order)) {
                if (Webhook::review_resolution_recovery_pending($order)) {
                    self::record_external_failure($order_id, 'rollback_review_resolution_pending');
                    return false;
                }
                if ((string) $order->get_meta('_bactive_paymongo_resolved_payment_pending', true) !== ''
                    || Webhook::operator_disposition_recovery_pending($order)) {
                    self::record_external_failure($order_id, 'rollback_resolved_payment_pending');
                    return false;
                }
                self::flag_failure($order, 'rollback_settlement_unresolved');
                return false;
            }
            if (!Order_Lock::renew($order_id)) {
                self::record_external_failure($order_id, 'rollback_final_lock_lost');
                return false;
            }
            $order->delete_meta_data(self::REQUIRED_META);
            $order->save();
            self::unschedule_order($order_id);
            return true;
        } finally {
            if (!$held_by_request) {
                Order_Lock::release($order_id);
            }
        }
    }
}
