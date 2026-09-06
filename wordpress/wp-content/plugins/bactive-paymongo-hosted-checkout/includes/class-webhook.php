<?php

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

/** Internal control-flow marker: retry the provider event without applying it. */
final class Quarantine_Retry_Exception extends \RuntimeException
{
}

final class Webhook
{
    private const MAX_BODY_BYTES = 1048576;
    private const CLAIM_TTL = 120;
    private const PAYMENT_EFFECTS_INCIDENT_CODE = 'payment_effects_ambiguous';
    private const PROCESSING_CODE_META = '_bactive_paymongo_processing_incident_code';
    private const PROCESSING_PAYMENT_META = '_bactive_paymongo_processing_incident_payment_id';
    private const PROCESSING_EVENT_META = '_bactive_paymongo_processing_incident_event_id';
    private const PROCESSING_SESSION_META = '_bactive_paymongo_processing_incident_session_id';
    private const PROCESSING_MODE_META = '_bactive_paymongo_processing_incident_mode';
    private const SETTLEMENT_MODE_META = '_bactive_paymongo_settlement_pending_mode';
    private const PAID_MODE_META = '_bactive_paymongo_paid_mode';
    private const UNEXPECTED_MODE_META = '_bactive_paymongo_unexpected_payment_mode';
    private const REVIEW_EFFECT_IDENTITY_META = '_bactive_paymongo_review_effect_identity';
    private const REVIEW_EFFECT_CODE_META = '_bactive_paymongo_review_effect_code';
    private const REVIEW_EFFECT_EVENT_META = '_bactive_paymongo_review_effect_event_id';
    private const REVIEW_EFFECT_SESSION_META = '_bactive_paymongo_review_effect_session_id';
    private const REVIEW_EFFECT_PAYMENT_META = '_bactive_paymongo_review_effect_payment_id';
    private const REVIEW_EFFECT_MODE_META = '_bactive_paymongo_review_effect_mode';
    private const REVIEW_MODE_META = '_bactive_paymongo_review_mode';
    private const REVIEW_RESOLUTION_OPTION_PREFIX = 'bactive_paymongo_review_resolution_';
    private const REVIEW_RESOLUTION_RECEIPT_PREFIX = 'bactive_paymongo_review_receipt_';
    private const EFFECTS_RESOLUTION_OPTION_PREFIX = 'bactive_paymongo_effects_resolution_';
    private const RESOLVED_PAYMENT_PENDING_META = '_bactive_paymongo_resolved_payment_pending';
    private const OPERATOR_DISPOSITION_META = '_bactive_paymongo_operator_disposition';
    private const OPERATOR_DISPOSITION_OPTION_PREFIX = 'bactive_paymongo_operator_disposition_';
    private const PENDING_REVIEWS_OPTION_PREFIX = 'bactive_paymongo_pending_reviews_';

    /**
     * Keep incidents reachable independently of the order's single active
     * review tuple. The order lease serializes this bounded per-order inbox.
     * @param array<string,mixed> $record
     * @return array<string,mixed>|null
     */
    public static function queue_review_incident(\WC_Order $order, string $kind, array $record): ?array
    {
        if (!Order_Lock::held_by_request($order->get_id())
            || !Order_Lock::renew($order->get_id())
            || !self::pending_review_record_valid($kind, $record, $order->get_id())) {
            return null;
        }
        $ledger = self::pending_review_ledger_option($kind, $record);
        $existing = Reconciler::read_incident_option($ledger, null);
        if ($existing !== null) {
            if (!is_array($existing)
                || !self::pending_review_record_valid($kind, $existing, $order->get_id())
                || self::pending_review_identity($kind, $existing) !== self::pending_review_identity($kind, $record)) {
                return null;
            }
            $record = $existing;
        }
        $option = self::PENDING_REVIEWS_OPTION_PREFIX . $order->get_id();
        $pending = Reconciler::read_incident_option($option, array());
        if (!is_array($pending)) {
            return null;
        }
        $identity = self::pending_review_identity($kind, $record);
        if (isset($pending[$identity])) {
            $item = $pending[$identity];
            $valid = is_array($item)
                && ($item['kind'] ?? '') === $kind
                && is_array($item['record'] ?? null)
                && self::pending_review_record_valid($kind, $item['record'], $order->get_id())
                && self::pending_review_identity($kind, $item['record']) === $identity;
            if ($valid) {
                Reconciler::set_draining(true);
                Reconciler::schedule_order($order->get_id());
            }
            return $valid ? $item['record'] : null;
        }
        if (count($pending) >= 100) {
            return null;
        }
        $pending[$identity] = array('kind' => $kind, 'record' => $record);
        update_option($option, $pending, false);
        if (Reconciler::read_incident_option($option, null) !== $pending) {
            Reconciler::record_global_drain_error(array('recorded_at' => time(),
                'code' => 'review_inbox_persist_failed', 'order_id' => $order->get_id(),
                'mode' => (string) ($record['mode'] ?? 'local')));
            return null;
        }
        Reconciler::set_draining(true);
        Reconciler::schedule_order($order->get_id());
        return $record;
    }

    public static function has_pending_reviews(int $order_id): bool
    {
        return Reconciler::read_incident_option(self::PENDING_REVIEWS_OPTION_PREFIX . $order_id, array()) !== array();
    }

    /** Remove inbox entries only after the exact incident is attached durably. */
    public static function acknowledge_attached_pending_reviews(\WC_Order $order): bool
    {
        if (!Order_Lock::renew($order->get_id())) {
            return false;
        }
        $fresh = self::fresh_order($order->get_id());
        $option = self::PENDING_REVIEWS_OPTION_PREFIX . $order->get_id();
        $pending = get_option($option, array());
        if (!$fresh instanceof \WC_Order || !is_array($pending)) {
            return false;
        }
        $remaining = $pending;
        foreach ($pending as $identity => $item) {
            $kind = is_array($item) ? (string) ($item['kind'] ?? '') : '';
            $record = is_array($item) ? ($item['record'] ?? null) : null;
            if (!is_array($record)
                || !self::pending_review_record_valid($kind, $record, $order->get_id())
                || self::pending_review_identity($kind, $record) !== $identity
                || get_option(self::pending_review_ledger_option($kind, $record), null) !== $record
                || (string) $fresh->get_meta('_bactive_paymongo_review_required', true) !== $record['code']
                || (string) $fresh->get_meta(Reconciler::UNRESOLVED_META, true) !== $record['code']
                || (string) $fresh->get_meta(self::REVIEW_MODE_META, true) !== $record['mode']
                || (int) $fresh->get_meta('_bactive_paymongo_review_incidents', true) < 1
                || (string) $fresh->get_meta(Reconciler::REQUIRED_META, true) !== 'yes'
                || (string) $fresh->get_meta(self::REVIEW_EFFECT_IDENTITY_META, true) !== '') {
                continue;
            }
            if ($kind === 'processing') {
                $matches = true;
                foreach (array(
                    self::PROCESSING_CODE_META => 'code',
                    self::PROCESSING_PAYMENT_META => 'payment_id',
                    self::PROCESSING_EVENT_META => 'event_id',
                    self::PROCESSING_SESSION_META => 'session_id',
                    self::PROCESSING_MODE_META => 'mode',
                ) as $meta => $key) {
                    if ((string) $fresh->get_meta($meta, true) !== $record[$key]) {
                        $matches = false;
                    }
                }
                if (!$matches || get_option(self::processing_review_option(
                    self::processing_identity_from_record($record), $record['code'], $record['mode']
                ), null) !== $record) {
                    continue;
                }
            } elseif ((string) $fresh->get_meta(self::PROCESSING_CODE_META, true) !== '') {
                continue;
            }
            unset($remaining[$identity]);
        }
        if ($remaining === $pending) {
            return true;
        }
        if (!Order_Lock::renew($order->get_id()) || get_option($option, array()) !== $pending) {
            return false;
        }
        if ($remaining === array()) {
            return Order_Lock::delete_option_if_exact($option, $pending);
        }
        update_option($option, $remaining, false);
        return get_option($option, null) === $remaining;
    }

    /** @return array<int,int>|\WP_Error */
    public static function pending_review_order_ids()
    {
        global $wpdb;
        if (defined('BACTIVE_PAYMONGO_TESTING')) {
            $names = array_keys((array) ($GLOBALS['fake_options'] ?? array()));
            $names = array_values(array_filter($names, static fn(string $name): bool => str_starts_with($name, self::PENDING_REVIEWS_OPTION_PREFIX)));
        } elseif (is_object($wpdb) && is_callable(array($wpdb, 'get_col'))) {
            $names = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 1001",
                $wpdb->esc_like(self::PENDING_REVIEWS_OPTION_PREFIX) . '%'
            ));
            if (!is_array($names) || $wpdb->last_error !== '') {
                return new \WP_Error('paymongo_pending_review_scan_failed', 'PayMongo incident discovery failed safely.');
            }
        } else {
            return new \WP_Error('paymongo_pending_review_scan_unavailable', 'PayMongo incident discovery is unavailable.');
        }
        if (count($names) > 1000) {
            return new \WP_Error('paymongo_pending_review_scan_limit', 'PayMongo incident discovery exceeded its bounded scan.');
        }
        $ids = array();
        foreach ($names as $name) {
            $suffix = substr($name, strlen(self::PENDING_REVIEWS_OPTION_PREFIX));
            if (!preg_match('/^[1-9][0-9]*$/D', $suffix)) {
                return new \WP_Error('paymongo_pending_review_identity_invalid', 'PayMongo incident index requires review.');
            }
            if (self::has_pending_reviews((int) $suffix)) {
                $ids[] = (int) $suffix;
            }
        }
        return $ids;
    }

    /** Promote or acknowledge one inbox entry without replaying business effects. */
    public static function promote_pending_review(\WC_Order $order): bool
    {
        $option = self::PENDING_REVIEWS_OPTION_PREFIX . $order->get_id();
        $pending = get_option($option, array());
        if ($pending === array()) {
            return true;
        }
        if (!is_array($pending) || !Order_Lock::renew($order->get_id())) {
            return false;
        }
        $identity = array_key_first($pending);
        $item = $pending[$identity];
        $kind = is_array($item) ? (string) ($item['kind'] ?? '') : '';
        $record = is_array($item) ? ($item['record'] ?? null) : null;
        if (!is_array($record)
            || !self::pending_review_record_valid($kind, $record, $order->get_id())
            || self::pending_review_identity($kind, $record) !== $identity) {
            return false;
        }
        $fields = array(
            '_bactive_paymongo_review_required' => $record['code'],
            Reconciler::UNRESOLVED_META => $record['code'],
            self::REVIEW_MODE_META => $record['mode'],
        );
        if ($kind === 'processing') {
            $fields += array(
                self::PROCESSING_CODE_META => $record['code'],
                self::PROCESSING_PAYMENT_META => $record['payment_id'],
                self::PROCESSING_EVENT_META => $record['event_id'],
                self::PROCESSING_SESSION_META => $record['session_id'],
                self::PROCESSING_MODE_META => $record['mode'],
            );
        }
        foreach ($fields as $key => $value) {
            $current = (string) $order->get_meta($key, true);
            if ($current !== '' && $current !== (string) $value) {
                return false;
            }
        }
        if ((string) $order->get_meta(self::REVIEW_EFFECT_IDENTITY_META, true) !== ''
            || ($kind === 'generic' && (string) $order->get_meta(self::PROCESSING_CODE_META, true) !== '')
            || self::review_resolution_recovery_pending($order)
            || self::operator_disposition_recovery_pending($order)
            || (string) $order->get_meta(self::RESOLVED_PAYMENT_PENDING_META, true) !== '') {
            return false;
        }
        $ledger = self::pending_review_ledger_option($kind, $record);
        add_option($ledger, $record, '', false);
        if (get_option($ledger, null) !== $record) {
            return false;
        }
        if ($kind === 'processing') {
            $review_option = self::processing_review_option(self::processing_identity_from_record($record), $record['code'], $record['mode']);
            add_option($review_option, $record, '', false);
            if (get_option($review_option, null) !== $record) {
                return false;
            }
        }
        foreach ($fields as $key => $value) {
            $order->update_meta_data($key, $value);
        }
        $order->update_meta_data('_bactive_paymongo_review_incidents', max(1, (int) $order->get_meta('_bactive_paymongo_review_incidents', true)));
        Reconciler::mark_required($order);
        self::save_with_status_effects_suppressed($order);
        $readback = self::fresh_order($order->get_id());
        if (!$readback instanceof \WC_Order) {
            return false;
        }
        foreach ($fields as $key => $value) {
            if ((string) $readback->get_meta($key, true) !== (string) $value) {
                return false;
            }
        }
        if (!Order_Lock::renew($order->get_id()) || get_option($option, null) !== $pending) {
            return false;
        }
        unset($pending[$identity]);
        if ($pending === array()) {
            if (!Order_Lock::delete_option_if_exact($option, array($identity => $item))) {
                return false;
            }
        } else {
            update_option($option, $pending, false);
        }
        return get_option($option, array()) === $pending && self::refresh_order($order);
    }

    /** @param array<string,mixed> $record */
    private static function pending_review_record_valid(string $kind, array $record, int $order_id): bool
    {
        if (!in_array($kind, array('generic', 'processing'), true)
            || ($record['order_id'] ?? null) !== $order_id || $order_id < 1
            || !is_string($record['code'] ?? null) || !preg_match('/^[a-z0-9_]{1,100}$/D', $record['code'])
            || !self::valid_mode((string) ($record['mode'] ?? ''))
            || !is_int($record['recorded_at'] ?? null) || $record['recorded_at'] < 1) {
            return false;
        }
        if ($kind === 'processing') {
            foreach (array('event_id', 'session_id', 'payment_id') as $key) {
                if (!is_string($record[$key] ?? null) || strlen($record[$key]) > 160) {
                    return false;
                }
            }
            return in_array($record['mode'], array('test', 'live'), true);
        }
        return true;
    }

    /** @param array<string,mixed> $record */
    private static function pending_review_identity(string $kind, array $record): string
    {
        unset($record['recorded_at']);
        ksort($record, SORT_STRING);
        return hash('sha256', $kind . '|' . serialize($record));
    }

    /** @param array<string,mixed> $record */
    private static function pending_review_ledger_option(string $kind, array $record): string
    {
        return $kind === 'generic'
            ? Reconciler::review_incident_option($record['order_id'], $record['code'], $record['mode'])
            : self::processing_incident_option(self::processing_identity_from_record($record), $record['mode']);
    }

    public static function handle(bool $live): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::respond(405, 'method_not_allowed');
        }

        $declared_length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($declared_length > self::MAX_BODY_BYTES) {
            self::respond(413, 'payload_too_large');
        }

        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if (!is_string($raw) || $raw === '' || strlen($raw) > self::MAX_BODY_BYTES) {
            self::respond(400, 'payload_invalid');
        }

        $secret = Secrets::webhook_secret($live);
        $signature = Integrity::verify_signature(
            $raw,
            self::signature_header(),
            $secret,
            $live,
            time(),
            300
        );
        if (!$signature['ok']) {
            self::safe_log('warning', $signature['code']);
            self::respond(401, 'signature_rejected');
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            if (!self::quarantine('json_invalid', '', '', 0, '', self::provider_mode($live))) {
                self::respond(503, 'retry_later');
            }
            self::respond(202, 'accepted_for_review');
        }
        $payload = Integrity::normalize_event($payload, $raw);
        if ($payload === null) {
            if (!self::quarantine('event_shape_invalid', '', '', 0, '', self::provider_mode($live))) {
                self::respond(503, 'retry_later');
            }
            self::respond(202, 'accepted_for_review');
        }

        $identity = self::event_identity($payload);
        if ($identity['event_id'] === '' || $identity['session_id'] === '') {
            if (!self::quarantine(
                'event_identity_invalid',
                $identity['event_id'],
                $identity['session_id'],
                0,
                '',
                self::provider_mode($live)
            )) {
                self::respond(503, 'retry_later');
            }
            self::respond(202, 'accepted_for_review');
        }

        $event_claim = self::claim('event', $identity['event_id'], self::provider_mode($live));
        if ($event_claim === 'done') {
            self::respond(200, 'duplicate');
        }
        if ($event_claim !== 'claimed') {
            self::respond(503, 'retry_later');
        }

        $order = self::locate_order($payload);
        if (!$order instanceof \WC_Order) {
            if (!self::finish_claimed_quarantine(
                'order_not_found',
                $identity['event_id'],
                $identity['session_id'],
                0,
                '',
                $live
            )) {
                self::respond(503, 'retry_later');
            }
            self::respond(202, 'accepted_for_review');
        }

        $order_id = $order->get_id();
        $attempt = self::attempt_for_session($order, $identity['session_id'], $live);
        if ($attempt === null) {
            if (!self::quarantine(
                'session_not_authorized',
                $identity['event_id'],
                $identity['session_id'],
                $order_id,
                '',
                self::provider_mode($live)
            )) {
                self::release_claim('event', $identity['event_id'], self::provider_mode($live));
                self::respond(503, 'retry_later');
            }
            self::finish_claim('event', $identity['event_id'], 'quarantined', self::provider_mode($live));
            self::respond(202, 'accepted_for_review');
        }

        if (!$live && !(new Gateway(false))->is_test_mode()) {
            if (!self::quarantine_retrieved_payment(
                $order,
                self::session_from_payload($payload),
                $identity['event_id'],
                $identity['session_id'],
                'test_event_on_live_site',
                $live
            )) {
                self::release_claim('event', $identity['event_id'], self::provider_mode($live));
                self::respond(503, 'retry_later');
            }
            self::finish_claim('event', $identity['event_id'], 'quarantined', self::provider_mode($live));
            self::respond(202, 'accepted_for_review');
        }

        $amount = Integrity::amount_to_minor((string) $order->get_total());
        if ($amount === null || $order->get_currency() !== 'PHP') {
            if (!self::quarantine_retrieved_payment(
                $order,
                self::session_from_payload($payload),
                $identity['event_id'],
                $identity['session_id'],
                'order_amount_invalid',
                $live
            )) {
                self::release_claim('event', $identity['event_id'], self::provider_mode($live));
                self::respond(503, 'retry_later');
            }
            self::finish_claim('event', $identity['event_id'], 'quarantined', self::provider_mode($live));
            self::respond(202, 'accepted_for_review');
        }

        $validated = Integrity::validate_paid_event(
            $payload,
            array(
                'live' => $live,
                'order_id' => $order_id,
                'amount' => $amount,
                'reference' => (string) ($attempt['reference'] ?? ''),
                'correlation' => (string) ($attempt['correlation_id'] ?? ''),
                'session_ids' => array($identity['session_id']),
            )
        );
        if (!$validated['ok']) {
            $recovered = self::recover_invalid_signed_event(
                $order,
                $attempt,
                $identity['event_id'],
                $identity['session_id'],
                $live
            );
            if ($recovered === 'retry') {
                self::respond(503, 'retry_later');
            }
            if ($recovered === 'quarantined') {
                self::respond(202, 'accepted_for_review');
            }
            self::respond(200, $recovered === 'duplicate' ? 'duplicate' : 'processed');
        }

        $result = self::process_claimed_payment($order, $validated, $live);
        if ($result === 'retry') {
            self::respond(503, 'retry_later');
        }
        self::respond(200, $result === 'duplicate' ? 'duplicate' : 'processed');
    }

    /**
     * Reconcile a Checkout Session retrieved with the mode-correct secret key.
     * This is intentionally not exposed as an HTTP route; it is the recovery
     * path for a webhook delivery that never arrived.
     *
     * @param array<string,mixed> $session_response
     * @param array<string,mixed> $attempt
     */
    public static function reconcile_checkout_session(
        \WC_Order $order,
        array $session_response,
        array $attempt,
        bool $live
    ): string {
        $session_id = (string) ($attempt['session_id'] ?? '');
        $status = Integrity::checkout_session_status($session_response, $session_id, $live);
        $payment_state = Integrity::checkout_session_payment_state($session_response, $session_id, $live);
        $session = $session_response['data'] ?? null;
        $attributes = is_array($session) ? ($session['attributes'] ?? null) : null;
        if ($status === null
            || $payment_state === null
            || !is_array($session)
            || !is_array($attributes)) {
            return 'invalid';
        }

        if ($payment_state['paid'] === array() && $payment_state['pending'] !== array()) {
            return 'payment_pending';
        }
        if ($payment_state['paid'] === array()) {
            return 'pending';
        }

        $raw = wp_json_encode($session, JSON_UNESCAPED_SLASHES);
        if (!is_string($raw)) {
            return 'invalid';
        }
        $event_id = 'evt_reconcile_' . substr(hash('sha256', $raw), 0, 48);
        $payload = array(
            'data' => array(
                'id' => $event_id,
                'type' => 'event',
                'attributes' => array(
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => $live,
                    'data' => $session,
                ),
            ),
        );

        if (!$live && !(new Gateway(false))->is_test_mode()) {
            $quarantined = self::quarantine_retrieved_payment(
                $order,
                $session,
                $event_id,
                $session_id,
                'test_event_on_live_site',
                $live
            );
            return $quarantined ? 'quarantined' : 'retry';
        }

        $amount = Integrity::amount_to_minor((string) $order->get_total());
        $validated = $amount === null ? array('ok' => false, 'code' => 'order_amount_invalid') : Integrity::validate_paid_event(
            $payload,
            array(
                'live' => $live,
                'order_id' => $order->get_id(),
                'amount' => $amount,
                'reference' => (string) ($attempt['reference'] ?? ''),
                'correlation' => (string) ($attempt['correlation_id'] ?? ''),
                'session_ids' => array((string) ($attempt['session_id'] ?? '')),
            )
        );
        if (!$validated['ok']) {
            $quarantined = self::quarantine_retrieved_payment(
                $order,
                $session,
                $event_id,
                $session_id,
                (string) $validated['code'],
                $live
            );
            return $quarantined ? 'quarantined' : 'retry';
        }

        $event_claim = self::claim('event', $event_id, self::provider_mode($live));
        if ($event_claim === 'done') {
            return 'duplicate';
        }
        if ($event_claim !== 'claimed') {
            return 'retry';
        }
        return self::process_claimed_payment($order, $validated, $live);
    }

    /**
     * Persist an operational review hold through the same at-most-once status
     * effect boundary used by paid-event quarantine. The caller owns the order
     * fence; an optional attempt snapshot is saved in the same durable phase.
     *
     * @param array<int,array<string,mixed>>|null $attempts
     */
    public static function hold_order_for_review(
        \WC_Order $order,
        string $code,
        string $session_id,
        ?array $attempts = null
    ): bool {
        $order_id = $order->get_id();
        $code = sanitize_key($code);
        $session_id = sanitize_text_field($session_id);
        if ($order_id < 1
            || $code === ''
            || !Order_Lock::held_by_request($order_id)
            || !Order_Lock::renew($order_id)) {
            return false;
        }

        $mode = self::review_mode_for_order($order, $session_id);
        $event_id = 'evt_local_' . substr(
            hash('sha256', $mode . '|' . $code . '|' . $session_id . '|' . $order_id),
            0,
            48
        );
        $quarantine = self::prepare_quarantine_record(
            $code,
            $event_id,
            $session_id,
            $order_id,
            '',
            $mode
        );
        if (empty($quarantine['durable'])) {
            self::record_quarantine_persistence_failure($quarantine['record']);
            Reconciler::schedule_order($order_id);
            return false;
        }

        if ($attempts !== null) {
            $attempts = array_values(array_filter($attempts, 'is_array'));
            $order->update_meta_data('_bactive_paymongo_attempts', $attempts);
        }
        $context = array(
            'event_id' => $event_id,
            'session_id' => $session_id,
            'payment_id' => '',
            'mode' => $mode,
        );
        try {
            $persisted = self::persist_review_hold(
                $order,
                $code,
                '',
                $session_id,
                sprintf(
                    /* translators: %s: sanitized reconciliation reason */
                    __('PayMongo order held for manual review: %s.', 'bactive-paymongo'),
                    $code
                ),
                $event_id,
                null,
                (bool) $quarantine['record_incident'],
                (bool) $quarantine['needs_annotation'],
                $quarantine['record']
            );
        } catch (\Throwable $error) {
            $persisted = false;
        }
        if ($persisted && $attempts !== null) {
            $readback = self::fresh_order($order_id);
            $persisted = $readback instanceof \WC_Order
                && Gateway::order_attempts($readback) === $attempts;
        }
        if (!$persisted || !self::finish_quarantine_record($quarantine)) {
            self::record_quarantine_retry_failure($order, $context, 'operational_review_persist_failed');
            return false;
        }

        self::clear_quarantine_retry_failure($order, $context);
        self::safe_log('error', $code, $quarantine['record']);
        return true;
    }

    /**
     * PayMongo documents its webhook body as abbreviated. When a correctly
     * signed delivery lacks fields required by the strict validator, retrieve
     * the authorized Checkout Session with the mode-correct secret and apply
     * only the independently verified resource. Never turn a transient GET or
     * not-yet-visible payment into a terminal quarantine.
     *
     * @param array<string,mixed> $attempt
     */
    private static function recover_invalid_signed_event(
        \WC_Order $order,
        array $attempt,
        string $event_id,
        string $session_id,
        bool $live
    ): string {
        $key = Secrets::api_key($live, new Gateway(false));
        if ($key === '') {
            self::release_claim('event', $event_id, self::provider_mode($live));
            Reconciler::schedule_order($order->get_id());
            return 'retry';
        }

        $response = (new Api_Client($key))->retrieve_checkout_session($session_id);
        if (is_wp_error($response)) {
            self::release_claim('event', $event_id, self::provider_mode($live));
            Reconciler::schedule_order($order->get_id());
            self::safe_log('error', 'signed_event_readback_failed', array(
                'order_id' => $order->get_id(),
                'event_id' => $event_id,
                'session_id' => $session_id,
            ));
            return 'retry';
        }

        $payment_state = Integrity::checkout_session_payment_state($response, $session_id, $live);
        $session = is_array($response) ? ($response['data'] ?? null) : null;
        if (!is_array($payment_state)
            || !is_array($session)
            || $payment_state['paid'] === array()) {
            self::release_claim('event', $event_id, self::provider_mode($live));
            Reconciler::schedule_order($order->get_id());
            return 'retry';
        }

        $amount = Integrity::amount_to_minor((string) $order->get_total());
        $validated = $amount === null
            ? array('ok' => false, 'code' => 'order_amount_invalid')
            : Integrity::validate_paid_event(
                array(
                    'data' => array(
                        'id' => $event_id,
                        'type' => 'event',
                        'attributes' => array(
                            'type' => 'checkout_session.payment.paid',
                            'livemode' => $live,
                            'data' => $session,
                        ),
                    ),
                ),
                array(
                    'live' => $live,
                    'order_id' => $order->get_id(),
                    'amount' => $amount,
                    'reference' => (string) ($attempt['reference'] ?? ''),
                    'correlation' => (string) ($attempt['correlation_id'] ?? ''),
                    'session_ids' => array($session_id),
                )
            );

        if (empty($validated['ok'])) {
            $quarantined = self::quarantine_retrieved_payment(
                $order,
                $session,
                $event_id,
                $session_id,
                (string) ($validated['code'] ?? 'provider_readback_invalid'),
                $live
            );
            if (!$quarantined) {
                self::release_claim('event', $event_id, self::provider_mode($live));
                return 'retry';
            }
            self::finish_claim('event', $event_id, 'quarantined', self::provider_mode($live));
            return 'quarantined';
        }

        return self::process_claimed_payment($order, $validated, $live);
    }

    public static function ambiguous_effects_action_available(\WC_Order $order): bool
    {
        if (self::effects_resolution_recovery_context($order) !== null) {
            return true;
        }
        $payment_id = (string) $order->get_meta('_bactive_paymongo_settlement_pending', true);
        $mode = self::payment_mode_for_order($order, $payment_id);
        $record = self::effects_record('payment', $payment_id, $mode);
        return self::payment_effects_incident_matches($order, $payment_id, $record);
    }

    /**
     * Resolve an at-most-once effects ambiguity only after a fresh provider
     * GET proves the exact payment. This deliberately does not replay any Woo
     * status, stock, email, or payment-complete hook; the operator must verify
     * those business effects before invoking the order action.
     */
    public static function resolve_ambiguous_effects(\WC_Order $order): bool
    {
        $order_id = $order->get_id();
        if (!Order_Lock::held_by_request($order_id)
            || !Order_Lock::renew($order_id)
            || !self::refresh_order($order)) {
            return false;
        }
        if (!self::acknowledge_attached_pending_reviews($order)) {
            return false;
        }

        $recovery = self::effects_resolution_recovery_context($order);
        $intent = is_array($recovery) ? $recovery['record'] : null;
        $payment_id = is_array($intent)
            ? (string) $intent['payment_id']
            : (string) $order->get_meta('_bactive_paymongo_settlement_pending', true);
        $mode = is_array($intent)
            ? (string) $intent['mode']
            : self::payment_mode_for_order($order, $payment_id);
        $effect = self::effects_record('payment', $payment_id, $mode);
        if (!is_array($intent)
            && !self::payment_effects_incident_matches($order, $payment_id, $effect)) {
            return false;
        }

        $attempts = array();
        foreach (Gateway::order_attempts($order) as $candidate) {
            if ((string) ($candidate['payment_id'] ?? '') === $payment_id
                && (string) ($candidate['mode'] ?? '') === $mode) {
                $attempts[] = $candidate;
            }
        }
        if (count($attempts) !== 1) {
            return false;
        }
        $attempt = $attempts[0];
        $mode = (string) ($attempt['mode'] ?? '');
        $live = $mode === 'live';
        if (!in_array($mode, array('test', 'live'), true)) {
            return false;
        }
        $key = Secrets::api_key($live, new Gateway(false));
        if ($key === '') {
            return false;
        }
        $session_id = (string) ($attempt['session_id'] ?? '');
        $response = (new Api_Client($key))->retrieve_checkout_session($session_id);
        if (is_wp_error($response) || !Order_Lock::renew($order_id)) {
            return false;
        }
        $session = is_array($response) ? ($response['data'] ?? null) : null;
        $amount = Integrity::amount_to_minor((string) $order->get_total());
        $event_id = (string) $order->get_meta('_bactive_paymongo_paid_event_id', true);
        if (!is_array($session) || $amount === null || $event_id === '') {
            return false;
        }
        $validated = Integrity::validate_paid_event(
            array(
                'data' => array(
                    'id' => $event_id,
                    'type' => 'event',
                    'attributes' => array(
                        'type' => 'checkout_session.payment.paid',
                        'livemode' => $live,
                        'data' => $session,
                    ),
                ),
            ),
            array(
                'live' => $live,
                'order_id' => $order_id,
                'amount' => $amount,
                'reference' => (string) ($attempt['reference'] ?? ''),
                'correlation' => (string) ($attempt['correlation_id'] ?? ''),
                'session_ids' => array($session_id),
            )
        );
        if (!empty($validated['ok'])) {
            $validated['mode'] = $mode;
        }
        if (empty($validated['ok'])
            || (string) ($validated['payment_id'] ?? '') !== $payment_id
            || !self::payment_facts_match($order, $validated, $payment_id)
            || self::payment_identity_conflicts($order, $validated, $payment_id)
            || !self::refresh_order($order)
            || !Order_Lock::renew($order_id)) {
            return false;
        }

        if (is_array($intent)) {
            $current_recovery = self::effects_resolution_recovery_context($order);
            if (!is_array($current_recovery) || $current_recovery['record'] !== $intent) {
                return false;
            }
        } else {
            $effect = self::effects_record('payment', $payment_id, $mode);
            if (!self::payment_effects_incident_matches($order, $payment_id, $effect)) {
                return false;
            }
            $intent = self::arm_effects_resolution($order, $payment_id, $mode, $effect);
            if (!is_array($intent)) {
                return false;
            }
        }

        return self::complete_effects_resolution($order, $intent);
    }

    /** @param array<string,mixed> $effect @return array<string,mixed>|null */
    private static function arm_effects_resolution(
        \WC_Order $order,
        string $payment_id,
        string $mode,
        array $effect
    ): ?array {
        if (!self::payment_effects_incident_matches($order, $payment_id, $effect)) {
            return null;
        }
        $processing_identity = self::processing_identity(
            $order->get_id(),
            self::PAYMENT_EFFECTS_INCIDENT_CODE,
            (string) ($effect['event_id'] ?? ''),
            (string) ($effect['session_id'] ?? ''),
            $payment_id
        );
        $incident = get_option(self::processing_incident_option($processing_identity, $mode), null);
        $review = get_option(
            self::processing_review_option($processing_identity, self::PAYMENT_EFFECTS_INCIDENT_CODE, $mode),
            null
        );
        if (!is_array($incident) || !is_array($review) || $review !== $incident) {
            return null;
        }
        $record = array(
            'status' => 'armed',
            'kind' => 'effects_resolution',
            'type' => 'operator_verified_no_reemit',
            'order_id' => $order->get_id(),
            'code' => self::PAYMENT_EFFECTS_INCIDENT_CODE,
            'payment_id' => $payment_id,
            'mode' => $mode,
            'event_id' => (string) ($effect['event_id'] ?? ''),
            'session_id' => (string) ($effect['session_id'] ?? ''),
            'from' => (string) ($effect['from'] ?? ''),
            'to' => (string) ($effect['to'] ?? ''),
            'effect_record' => $effect,
            'incident_record' => $incident,
            'resolved_by' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
            'recorded_at' => time(),
        );
        if (!self::effects_resolution_record_valid($record)
            || !self::effects_resolution_order_matches_record($order, $record)) {
            return null;
        }
        $option = self::effects_resolution_option($payment_id, $mode);
        $existing = get_option($option, null);
        if ($existing !== null) {
            return is_array($existing)
                && in_array((string) ($existing['status'] ?? ''), array('armed', 'processing'), true)
                && $existing === $record
                    ? $existing
                    : null;
        }
        if (!add_option($option, $record, '', false)) {
            return null;
        }
        $readback = get_option($option, null);
        return is_array($readback) && $readback === $record ? $record : null;
    }

    /** @param array<string,mixed> $record */
    private static function complete_effects_resolution(\WC_Order $order, array $record): bool
    {
        $record = self::begin_effects_resolution($record);
        if (!is_array($record) || !Order_Lock::renew((int) $record['order_id'])) {
            return false;
        }
        $current = self::fresh_order((int) $record['order_id']);
        $recovery = $current instanceof \WC_Order
            ? self::effects_resolution_recovery_context($current)
            : null;
        if (!is_array($recovery) || $recovery['record'] !== $record) {
            return false;
        }

        $effect = self::effects_record('payment', (string) $record['payment_id'], (string) $record['mode']);
        if (!self::effects_resolution_effect_matches($effect, $record)
            || !self::mark_effect_resolved_without_reemit(
                'payment',
                (string) $record['payment_id'],
                (string) $record['mode'],
                $effect
            )) {
            return false;
        }
        if (function_exists('do_action')) {
            do_action('bactive_paymongo_effects_resolution_checkpoint', 'effect_done', $record);
        }

        $current = self::fresh_order((int) $record['order_id']);
        if (!$current instanceof \WC_Order
            || !self::effects_resolution_order_matches_record($current, $record)
            || !Order_Lock::renew((int) $record['order_id'])) {
            return false;
        }
        if (!self::effects_resolution_final_state_matches($current, $record)) {
            self::apply_effects_resolution_target($current);
            try {
                self::save_with_status_effects_suppressed(
                    $current,
                    null,
                    static fn(\WC_Order $saving_order): bool => self::effects_resolution_final_state_matches(
                        $saving_order,
                        $record
                    )
                );
            } catch (\Throwable $error) {
                // The mode-bound external intent keeps every torn layout
                // recoverable and leaves checkout draining.
            }
        }
        if (function_exists('do_action')) {
            do_action('bactive_paymongo_effects_resolution_checkpoint', 'order_saved', $record);
        }

        $resolved = self::fresh_order((int) $record['order_id']);
        if (!$resolved instanceof \WC_Order
            || !self::effects_resolution_final_state_matches($resolved, $record)) {
            return false;
        }
        if (function_exists('do_action')) {
            do_action('bactive_paymongo_effects_resolution_checkpoint', 'order_verified', $record);
        }

        foreach (array(
            self::processing_incident_option(
                self::processing_identity_from_record((array) $record['incident_record']),
                (string) $record['mode']
            ),
            self::processing_review_option(
                self::processing_identity_from_record((array) $record['incident_record']),
                self::PAYMENT_EFFECTS_INCIDENT_CODE,
                (string) $record['mode']
            ),
        ) as $option) {
            $stored = get_option($option, null);
            if ($stored !== null) {
                if (!is_array($stored)
                    || $stored !== (array) $record['incident_record']) {
                    return false;
                }
                delete_option($option);
                if (get_option($option, null) !== null) {
                    return false;
                }
            }
        }
        if (function_exists('do_action')) {
            do_action('bactive_paymongo_effects_resolution_checkpoint', 'ledgers_deleted', $record);
        }
        if (!self::finish_effects_resolution($record)) {
            return false;
        }
        self::clear_matching_global_incident($record['incident_record'], array((string) $record['incident_record']['code']));

        Reconciler::schedule_order((int) $record['order_id']);
        try {
            $resolved->add_order_note(
                __('PayMongo effects ambiguity resolved after an authorized operator independently verified the provider payment and downstream fulfillment. No WooCommerce payment or status hooks were re-emitted.', 'bactive-paymongo')
            );
        } catch (\Throwable $error) {
            // The completed intent is the authoritative operator audit.
        }
        return true;
    }

    /** @param array<string,mixed> $record @return array<string,mixed>|null */
    private static function begin_effects_resolution(array $record): ?array
    {
        if (!self::effects_resolution_record_valid($record)
            || !in_array((string) ($record['status'] ?? ''), array('armed', 'processing'), true)) {
            return null;
        }
        $option = self::effects_resolution_option(
            (string) $record['payment_id'],
            (string) $record['mode']
        );
        $stored = get_option($option, null);
        if (!is_array($stored) || $stored !== $record) {
            return null;
        }
        if (($stored['status'] ?? '') === 'armed') {
            $stored['status'] = 'processing';
            $stored['started_at'] = time();
            update_option($option, $stored, false);
        }
        $readback = get_option($option, null);
        return is_array($readback)
            && $readback === $stored
            && self::effects_resolution_record_valid($readback)
            && ($readback['status'] ?? '') === 'processing'
                ? $readback
                : null;
    }

    /** @param array<string,mixed> $record */
    private static function finish_effects_resolution(array $record): bool
    {
        if (!self::effects_resolution_record_valid($record)
            || ($record['status'] ?? '') !== 'processing') {
            return false;
        }
        $option = self::effects_resolution_option(
            (string) $record['payment_id'],
            (string) $record['mode']
        );
        $stored = get_option($option, null);
        if (!is_array($stored) || $stored !== $record) {
            return false;
        }
        $record['status'] = 'done';
        $record['finished_at'] = time();
        update_option($option, $record, false);
        $readback = get_option($option, null);
        return is_array($readback)
            && $readback === $record
            && self::effects_resolution_record_valid($readback);
    }

    /** @return array{record:array<string,mixed>}|null */
    private static function effects_resolution_recovery_context(\WC_Order $order): ?array
    {
        $pairs = array();
        foreach (array(
            array((string) $order->get_transaction_id(), (string) $order->get_meta(self::PAID_MODE_META, true)),
            array(
                (string) $order->get_meta('_bactive_paymongo_settlement_pending', true),
                (string) $order->get_meta(self::SETTLEMENT_MODE_META, true),
            ),
            array(
                (string) $order->get_meta(self::PROCESSING_PAYMENT_META, true),
                (string) $order->get_meta(self::PROCESSING_MODE_META, true),
            ),
        ) as $pair) {
            if (preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', $pair[0])
                && in_array($pair[1], array('test', 'live'), true)) {
                $pairs[$pair[1] . '|' . $pair[0]] = array('payment_id' => $pair[0], 'mode' => $pair[1]);
            }
        }
        if (count($pairs) !== 1) {
            return null;
        }
        $pair = array_values($pairs)[0];
        $record = get_option(
            self::effects_resolution_option($pair['payment_id'], $pair['mode']),
            null
        );
        return is_array($record)
            && in_array((string) ($record['status'] ?? ''), array('armed', 'processing'), true)
            && self::effects_resolution_record_valid($record)
            && self::effects_resolution_order_matches_record($order, $record)
                ? array('record' => $record)
                : null;
    }

    /** @param array<string,mixed>|null $effect @param array<string,mixed> $record */
    private static function effects_resolution_effect_matches(?array $effect, array $record): bool
    {
        $expected = is_array($record['effect_record'] ?? null) ? $record['effect_record'] : array();
        if (!is_array($effect)) {
            return false;
        }
        foreach (array('kind', 'identity', 'mode', 'order_id', 'from', 'to', 'event_id', 'session_id', 'payment_id') as $key) {
            if (($effect[$key] ?? null) !== ($expected[$key] ?? null)) {
                return false;
            }
        }
        return $effect === $expected
            || ((string) ($effect['status'] ?? '') === 'done'
                && (string) ($effect['resolution'] ?? '') === 'operator_verified_no_reemit'
                && (int) ($effect['finished_at'] ?? 0) > 0
                && (int) ($effect['resolved_by'] ?? 0) > 0);
    }

    /** @param array<string,mixed> $record */
    private static function effects_resolution_record_valid(array $record): bool
    {
        $status = (string) ($record['status'] ?? '');
        $expected_keys = array(
            'status', 'kind', 'type', 'order_id', 'code', 'payment_id', 'mode',
            'event_id', 'session_id', 'from', 'to', 'effect_record',
            'incident_record', 'resolved_by', 'recorded_at',
        );
        if (in_array($status, array('processing', 'done'), true)) {
            $expected_keys[] = 'started_at';
        }
        if ($status === 'done') {
            $expected_keys[] = 'finished_at';
        }
        $actual_keys = array_keys($record);
        sort($actual_keys, SORT_STRING);
        sort($expected_keys, SORT_STRING);
        $effect = is_array($record['effect_record'] ?? null) ? $record['effect_record'] : null;
        $incident = is_array($record['incident_record'] ?? null) ? $record['incident_record'] : null;
        if ($actual_keys !== $expected_keys
            || !in_array($status, array('armed', 'processing', 'done'), true)
            || ($record['kind'] ?? '') !== 'effects_resolution'
            || ($record['type'] ?? '') !== 'operator_verified_no_reemit'
            || (string) ($record['code'] ?? '') !== self::PAYMENT_EFFECTS_INCIDENT_CODE
            || !is_int($record['order_id'] ?? null)
            || (int) $record['order_id'] < 1
            || !preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', (string) ($record['payment_id'] ?? ''))
            || !in_array((string) ($record['mode'] ?? ''), array('test', 'live'), true)
            || !preg_match('/^evt_[A-Za-z0-9_-]{3,128}$/D', (string) ($record['event_id'] ?? ''))
            || !preg_match('/^cs_[A-Za-z0-9_-]{3,128}$/D', (string) ($record['session_id'] ?? ''))
            || !is_array($effect)
            || !is_array($incident)
            || (string) ($effect['kind'] ?? '') !== 'payment'
            || (string) ($effect['identity'] ?? '') !== (string) $record['payment_id']
            || (string) ($effect['mode'] ?? '') !== (string) $record['mode']
            || (int) ($effect['order_id'] ?? 0) !== (int) $record['order_id']
            || (string) ($effect['event_id'] ?? '') !== (string) $record['event_id']
            || (string) ($effect['session_id'] ?? '') !== (string) $record['session_id']
            || (string) ($effect['payment_id'] ?? '') !== (string) $record['payment_id']
            || (string) ($effect['from'] ?? '') !== (string) $record['from']
            || (string) ($effect['to'] ?? '') !== (string) $record['to']
            || !in_array((string) ($effect['status'] ?? ''), array('processing', 'done'), true)
            || (string) ($incident['code'] ?? '') !== self::PAYMENT_EFFECTS_INCIDENT_CODE
            || (int) ($incident['order_id'] ?? 0) !== (int) $record['order_id']
            || (string) ($incident['payment_id'] ?? '') !== (string) $record['payment_id']
            || (string) ($incident['mode'] ?? '') !== (string) $record['mode']
            || (string) ($incident['event_id'] ?? '') !== (string) $record['event_id']
            || (string) ($incident['session_id'] ?? '') !== (string) $record['session_id']
            || !is_int($record['resolved_by'] ?? null)
            || (int) $record['resolved_by'] < 1
            || !is_int($record['recorded_at'] ?? null)
            || (int) $record['recorded_at'] < 1) {
            return false;
        }
        if (in_array($status, array('processing', 'done'), true)
            && (!is_int($record['started_at'] ?? null)
                || (int) $record['started_at'] < (int) $record['recorded_at'])) {
            return false;
        }
        return $status !== 'done'
            || (is_int($record['finished_at'] ?? null)
                && (int) $record['finished_at'] >= (int) $record['started_at']);
    }

    /** @param array<string,mixed> $record */
    private static function effects_resolution_order_matches_record(\WC_Order $order, array $record): bool
    {
        if (!self::effects_resolution_record_valid($record)
            || $order->get_id() !== (int) $record['order_id']
            || !$order->is_paid()
            || (string) $order->get_transaction_id() !== (string) $record['payment_id']
            || $order->get_date_paid('edit') === null
            || $order->get_status() !== (string) $record['to']
            || Gateway::has_outstanding_attempts($order)
            || (string) $order->get_meta('_bactive_paymongo_paid_event_id', true) !== (string) $record['event_id']
            || (string) $order->get_meta('_bactive_paymongo_paid_session_id', true) !== (string) $record['session_id']
            || (string) $order->get_meta(self::PAID_MODE_META, true) !== (string) $record['mode']
            || (string) $order->get_meta(Reconciler::REQUIRED_META, true) !== 'yes'
            || (string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true) !== ''
            || (string) $order->get_meta(self::UNEXPECTED_MODE_META, true) !== '') {
            return false;
        }
        $allowed = array(
            '_bactive_paymongo_settlement_pending' => (string) $record['payment_id'],
            self::SETTLEMENT_MODE_META => (string) $record['mode'],
            Reconciler::UNRESOLVED_META => (string) $record['code'],
            '_bactive_paymongo_review_required' => (string) $record['code'],
            '_bactive_paymongo_review_incidents' => '1',
            self::REVIEW_MODE_META => (string) $record['mode'],
            self::PROCESSING_CODE_META => (string) $record['code'],
            self::PROCESSING_PAYMENT_META => (string) $record['payment_id'],
            self::PROCESSING_EVENT_META => (string) $record['event_id'],
            self::PROCESSING_SESSION_META => (string) $record['session_id'],
            self::PROCESSING_MODE_META => (string) $record['mode'],
        );
        foreach ($allowed as $key => $before) {
            if (!in_array((string) $order->get_meta($key, true), array($before, ''), true)) {
                return false;
            }
        }
        $matches = 0;
        foreach (Gateway::order_attempts($order) as $attempt) {
            if ((string) ($attempt['session_id'] ?? '') === (string) $record['session_id']
                && (string) ($attempt['mode'] ?? '') === (string) $record['mode']
                && (string) ($attempt['payment_id'] ?? '') === (string) $record['payment_id']
                && (string) ($attempt['paid_event_id'] ?? '') === (string) $record['event_id']
                && (int) ($attempt['paid_at'] ?? 0) > 0) {
                ++$matches;
            }
        }
        if ($matches !== 1
            || !self::effects_resolution_effect_matches(
                self::effects_record('payment', (string) $record['payment_id'], (string) $record['mode']),
                $record
            )) {
            return false;
        }
        foreach (array(
            self::processing_incident_option(
                self::processing_identity_from_record((array) $record['incident_record']),
                (string) $record['mode']
            ),
            self::processing_review_option(
                self::processing_identity_from_record((array) $record['incident_record']),
                self::PAYMENT_EFFECTS_INCIDENT_CODE,
                (string) $record['mode']
            ),
        ) as $option) {
            $stored = get_option($option, null);
            if ($stored !== null && $stored !== (array) $record['incident_record']) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $record */
    private static function effects_resolution_final_state_matches(\WC_Order $order, array $record): bool
    {
        if (!self::effects_resolution_order_matches_record($order, $record)) {
            return false;
        }
        foreach (array(
            '_bactive_paymongo_settlement_pending', self::SETTLEMENT_MODE_META,
            Reconciler::UNRESOLVED_META, '_bactive_paymongo_review_required',
            '_bactive_paymongo_review_incidents', self::REVIEW_MODE_META,
            self::PROCESSING_CODE_META, self::PROCESSING_PAYMENT_META,
            self::PROCESSING_EVENT_META, self::PROCESSING_SESSION_META,
            self::PROCESSING_MODE_META,
        ) as $key) {
            if ((string) $order->get_meta($key, true) !== '') {
                return false;
            }
        }
        return true;
    }

    private static function apply_effects_resolution_target(\WC_Order $order): void
    {
        foreach (array(
            '_bactive_paymongo_settlement_pending', self::SETTLEMENT_MODE_META,
            Reconciler::UNRESOLVED_META, '_bactive_paymongo_review_required',
            '_bactive_paymongo_review_incidents', self::REVIEW_MODE_META,
            self::PROCESSING_CODE_META, self::PROCESSING_PAYMENT_META,
            self::PROCESSING_EVENT_META, self::PROCESSING_SESSION_META,
            self::PROCESSING_MODE_META,
        ) as $key) {
            $order->delete_meta_data($key);
        }
        $order->update_meta_data(Reconciler::REQUIRED_META, 'yes');
    }

    private static function effects_resolution_option(string $payment_id, string $mode): string
    {
        return self::EFFECTS_RESOLUTION_OPTION_PREFIX . $mode . '_'
            . hash('sha256', $mode . '|' . $payment_id);
    }

    /** @param array<string,mixed>|null $record */
    private static function payment_effects_incident_matches(
        \WC_Order $order,
        string $payment_id,
        ?array $record
    ): bool {
        $mode = self::payment_mode_for_order($order, $payment_id);
        if (!in_array($mode, array('test', 'live'), true)
            || !preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', $payment_id)
            || !is_array($record)
            || (string) ($record['mode'] ?? '') !== $mode
            || !in_array((string) ($record['status'] ?? ''), array('processing', 'done'), true)
            || (($record['status'] ?? '') === 'done'
                && ($record['resolution'] ?? '') !== 'operator_verified_no_reemit')
            || (int) ($record['order_id'] ?? 0) !== $order->get_id()
            || (string) ($record['identity'] ?? '') !== $payment_id
            || (string) ($record['payment_id'] ?? '') !== $payment_id
            || (string) ($record['to'] ?? '') !== $order->get_status()
            || !in_array((string) ($record['from'] ?? ''), array('pending', 'failed'), true)
            || !$order->is_paid()
            || (string) $order->get_transaction_id() !== $payment_id
            || $order->get_date_paid('edit') === null
            || Gateway::has_outstanding_attempts($order)
            || (string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true) !== ''
            || (string) $order->get_meta(self::UNEXPECTED_MODE_META, true) !== ''
            || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== $payment_id
            || (string) $order->get_meta(self::SETTLEMENT_MODE_META, true) !== $mode
            || (string) $order->get_meta(self::PAID_MODE_META, true) !== $mode
            || (string) $order->get_meta(Reconciler::UNRESOLVED_META, true)
                !== self::PAYMENT_EFFECTS_INCIDENT_CODE
            || (string) $order->get_meta('_bactive_paymongo_review_required', true)
                !== self::PAYMENT_EFFECTS_INCIDENT_CODE
            || (string) $order->get_meta(Reconciler::REQUIRED_META, true) !== 'yes'
            || (int) $order->get_meta('_bactive_paymongo_review_incidents', true) !== 1
            || (string) $order->get_meta(self::REVIEW_MODE_META, true) !== $mode
            || (string) $order->get_meta(self::PROCESSING_CODE_META, true)
                !== self::PAYMENT_EFFECTS_INCIDENT_CODE
            || (string) $order->get_meta(self::PROCESSING_PAYMENT_META, true) !== $payment_id
            || (string) $order->get_meta(self::PROCESSING_EVENT_META, true)
                !== (string) ($record['event_id'] ?? '')
            || (string) $order->get_meta(self::PROCESSING_SESSION_META, true)
                !== (string) ($record['session_id'] ?? '')
            || (string) $order->get_meta(self::PROCESSING_MODE_META, true) !== $mode) {
            return false;
        }

        $processing_identity = self::processing_identity(
            $order->get_id(),
            self::PAYMENT_EFFECTS_INCIDENT_CODE,
            (string) ($record['event_id'] ?? ''),
            (string) ($record['session_id'] ?? ''),
            $payment_id
        );
        $incident = get_option(self::processing_incident_option($processing_identity, $mode), null);
        $review = get_option(self::processing_review_option(
            $processing_identity,
            self::PAYMENT_EFFECTS_INCIDENT_CODE,
            $mode
        ), null);
        return is_array($incident)
            && (string) ($incident['code'] ?? '') === self::PAYMENT_EFFECTS_INCIDENT_CODE
            && (string) ($incident['mode'] ?? '') === $mode
            && (int) ($incident['order_id'] ?? 0) === $order->get_id()
            && (string) ($incident['payment_id'] ?? '') === $payment_id
            && (string) ($incident['event_id'] ?? '') === (string) ($record['event_id'] ?? '')
            && (string) ($incident['session_id'] ?? '') === (string) ($record['session_id'] ?? '')
            && is_array($review)
            && $review === $incident;
    }

    /** @param array<string,mixed>|null $record */
    private static function mark_effect_resolved_without_reemit(
        string $kind,
        string $identity,
        string $mode,
        ?array $record
    ): bool {
        if (!self::valid_mode($mode)
            || !is_array($record)
            || (string) ($record['mode'] ?? '') !== $mode) {
            return false;
        }
        if (($record['status'] ?? '') === 'done') {
            return ($record['resolution'] ?? '') === 'operator_verified_no_reemit';
        }
        if (!in_array((string) ($record['status'] ?? ''), array('armed', 'processing'), true)) {
            return false;
        }
        $record['status'] = 'done';
        $record['finished_at'] = time();
        $record['resolution'] = 'operator_verified_no_reemit';
        $record['resolved_by'] = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        update_option(self::effects_option($kind, $identity, $mode), $record, false);
        $readback = self::effects_record($kind, $identity, $mode);
        return is_array($readback)
            && ($readback['status'] ?? '') === 'done'
            && ($readback['resolution'] ?? '') === 'operator_verified_no_reemit'
            && (string) ($readback['identity'] ?? '') === $identity
            && (string) ($readback['mode'] ?? '') === $mode;
    }

    /**
     * Resolve one exact review incident. A review transition left armed or
     * processing is acknowledged without replay only after its durable
     * quarantine record and current on-hold order state agree exactly.
     */
    public static function resolve_review_for_operator(\WC_Order $order): bool
    {
        $order_id = $order->get_id();
        if (!Order_Lock::held_by_request($order_id)
            || !Order_Lock::renew($order_id)
            || !self::refresh_order($order)
            || !self::generic_review_resolution_state_is_eligible($order)) {
            return false;
        }
        if (!self::acknowledge_attached_pending_reviews($order)) {
            return false;
        }

        $resolution_recovery = self::review_resolution_recovery_context($order);
        if (is_array($resolution_recovery)) {
            return self::complete_review_resolution($order, $resolution_recovery['record']);
        }
        if (self::review_resolution_recovery_pending($order)) {
            // An active but incompatible record is a hard drain blocker, not
            // authority to start a second resolution over uncertain state.
            return false;
        }

        $code = sanitize_key((string) $order->get_meta(Reconciler::UNRESOLVED_META, true));
        if ($code === ''
            || $code === self::PAYMENT_EFFECTS_INCIDENT_CODE
            || (string) $order->get_meta('_bactive_paymongo_review_required', true) !== $code
            || (string) $order->get_meta(Reconciler::REQUIRED_META, true) !== 'yes'
            || (int) $order->get_meta('_bactive_paymongo_review_incidents', true) !== 1) {
            return false;
        }

        $identity = (string) $order->get_meta(self::REVIEW_EFFECT_IDENTITY_META, true);
        $effect_context = array(
            'code' => (string) $order->get_meta(self::REVIEW_EFFECT_CODE_META, true),
            'event_id' => (string) $order->get_meta(self::REVIEW_EFFECT_EVENT_META, true),
            'session_id' => (string) $order->get_meta(self::REVIEW_EFFECT_SESSION_META, true),
            'payment_id' => (string) $order->get_meta(self::REVIEW_EFFECT_PAYMENT_META, true),
            'mode' => (string) $order->get_meta(self::REVIEW_EFFECT_MODE_META, true),
        );
        $has_effect_context = $identity !== '' || array_filter(
            $effect_context,
            static fn($value): bool => (string) $value !== ''
        ) !== array();
        $review_mode = (string) $order->get_meta(self::REVIEW_MODE_META, true);
        if (!self::valid_mode($review_mode)
            || ($has_effect_context && (string) $effect_context['mode'] !== $review_mode)) {
            return false;
        }

        if ($has_effect_context) {
            $mode = (string) $effect_context['mode'];
            $record = self::effects_record('review', $identity, $mode);
            if ($identity === ''
                || $effect_context['event_id'] === ''
                || !self::valid_mode($mode)
                || !hash_equals($identity, $effect_context['event_id'])
                || $effect_context['code'] !== $code
                || !is_array($record)
                || (string) ($record['kind'] ?? '') !== 'review'
                || (string) ($record['identity'] ?? '') !== $identity
                || (int) ($record['order_id'] ?? 0) !== $order_id
                || (string) ($record['to'] ?? '') !== 'on-hold'
                || !$order->has_status('on-hold')
                || !in_array((string) ($record['status'] ?? ''), array('armed', 'processing', 'done'), true)) {
                return false;
            }
            foreach ($effect_context as $key => $value) {
                if ((string) ($record[$key] ?? '') !== $value) {
                    return false;
                }
            }

            $quarantine_option = self::quarantine_option($identity, $mode);
            $quarantine_record = get_option($quarantine_option, null);
            if (!is_array($quarantine_record)
                || !self::quarantine_record_matches(
                    $quarantine_record,
                    array(
                        'code' => $code,
                        'event_id' => $effect_context['event_id'],
                        'session_id' => $effect_context['session_id'],
                        'payment_id' => $effect_context['payment_id'],
                        'order_id' => $order_id,
                        'mode' => $mode,
                        'recorded_at' => (int) ($quarantine_record['recorded_at'] ?? 0),
                    ),
                    false
                )) {
                return false;
            }

            if (($record['status'] ?? '') !== 'done'
                && !self::mark_effect_resolved_without_reemit('review', $identity, $mode, $record)) {
                return false;
            }
            if (empty($quarantine_record['order_annotated'])) {
                $quarantine = array(
                    'option' => $quarantine_option,
                    'record' => $quarantine_record,
                    'needs_annotation' => true,
                    'durable' => true,
                );
                if (!self::finish_quarantine_record($quarantine)) {
                    return false;
                }
            }
        }

        if (!Order_Lock::renew($order_id)
            || !self::refresh_order($order)
            || !self::generic_review_resolution_state_is_eligible($order)) {
            return false;
        }
        if ((string) $order->get_meta(Reconciler::UNRESOLVED_META, true) !== $code
            || (string) $order->get_meta('_bactive_paymongo_review_required', true) !== $code
            || (int) $order->get_meta('_bactive_paymongo_review_incidents', true) !== 1) {
            return false;
        }

        $resolution_source = self::review_resolution_source($order, $code);
        if (!is_array($resolution_source)) {
            return false;
        }

        foreach (array(
            Reconciler::UNRESOLVED_META,
            '_bactive_paymongo_review_required',
            '_bactive_paymongo_review_incidents',
            self::REVIEW_EFFECT_IDENTITY_META,
            self::REVIEW_EFFECT_CODE_META,
            self::REVIEW_EFFECT_EVENT_META,
            self::REVIEW_EFFECT_SESSION_META,
            self::REVIEW_EFFECT_PAYMENT_META,
            self::REVIEW_EFFECT_MODE_META,
            self::REVIEW_MODE_META,
        ) as $key) {
            $order->delete_meta_data($key);
        }
        // This marker is the order-query index for the external intent. Keep it
        // until the intent is durably done so a zero-attempt review remains
        // recoverable even if its one-off scheduled action is lost.
        $order->update_meta_data(Reconciler::REQUIRED_META, 'yes');
        $resolved_evidence_fingerprint = '';
        $resolved_payment_pending = '';
        $order->delete_meta_data(self::RESOLVED_PAYMENT_PENDING_META);
        if (Gateway::has_provider_payment_evidence($order)) {
            $resolved_evidence_fingerprint = Gateway::provider_payment_evidence_fingerprint($order);
            $order->update_meta_data(
                '_bactive_paymongo_resolved_evidence_fingerprint',
                $resolved_evidence_fingerprint
            );
            // Only an exact paid-quarantine chain gets a second disposition
            // step. Keep that step drain-active so its mode-correct credential
            // cannot be rotated away between the two human actions.
            $order->update_meta_data(
                self::RESOLVED_PAYMENT_PENDING_META,
                $resolved_evidence_fingerprint
            );
            if (self::resolved_payment_context($order) !== null) {
                $resolved_payment_pending = $resolved_evidence_fingerprint;
            } else {
                $order->delete_meta_data(self::RESOLVED_PAYMENT_PENDING_META);
            }
        }
        $record = self::arm_review_resolution(
            $order,
            $resolution_source,
            (string) $order->get_meta('_bactive_paymongo_resolved_evidence_fingerprint', true),
            $resolved_payment_pending
        );
        return is_array($record) && self::complete_review_resolution($order, $record);
    }

    private static function generic_review_resolution_state_is_eligible(\WC_Order $order): bool
    {
        if (Gateway::has_outstanding_attempts($order)
            || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== ''
            || (string) $order->get_meta(self::SETTLEMENT_MODE_META, true) !== '') {
            return false;
        }
        foreach (array(
            self::PROCESSING_CODE_META,
            self::PROCESSING_PAYMENT_META,
            self::PROCESSING_EVENT_META,
            self::PROCESSING_SESSION_META,
            self::PROCESSING_MODE_META,
        ) as $key) {
            if ((string) $order->get_meta($key, true) !== '') {
                return false;
            }
        }
        return true;
    }

    public static function review_resolution_recovery_pending(\WC_Order $order): bool
    {
        foreach (self::review_resolution_candidate_records($order->get_id()) as $candidate) {
            $record = $candidate['record'];
            if (is_array($record)
                && in_array((string) ($record['status'] ?? ''), array('armed', 'processing'), true)
                && (int) ($record['order_id'] ?? 0) === $order->get_id()) {
                return true;
            }
        }
        return false;
    }

    public static function review_resolution_recovery_action_available(\WC_Order $order): bool
    {
        return self::review_resolution_recovery_context($order) !== null;
    }

    /** @return array<string,mixed>|null */
    private static function review_resolution_source(\WC_Order $order, string $code): ?array
    {
        $amount = Integrity::amount_to_minor((string) $order->get_total());
        $date_paid = $order->get_date_paid('edit');
        if ($amount === null
            || ($date_paid !== null
                && (!is_object($date_paid) || !is_callable(array($date_paid, 'getTimestamp'))))) {
            return null;
        }
        $paid_at = is_object($date_paid) ? (int) $date_paid->getTimestamp() : 0;
        $effect_mode = (string) $order->get_meta(self::REVIEW_EFFECT_MODE_META, true);
        $review_mode = (string) $order->get_meta(self::REVIEW_MODE_META, true);
        $effect_fields = array(
            (string) $order->get_meta(self::REVIEW_EFFECT_IDENTITY_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_CODE_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_EVENT_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_SESSION_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_PAYMENT_META, true),
        );
        $has_effect = array_filter(
            $effect_fields,
            static fn(string $value): bool => $value !== ''
        ) !== array();
        if (!self::valid_mode($review_mode)
            || ($effect_mode !== '' && !self::valid_mode($effect_mode))
            || ($has_effect && $effect_mode === '')
            || ($effect_mode !== '' && $effect_mode !== $review_mode)) {
            return null;
        }
        $mode = $review_mode;
        if (!self::valid_mode($mode)) {
            return null;
        }
        $source = array(
            'order_id' => $order->get_id(),
            'code' => sanitize_key($code),
            'effect_identity' => (string) $order->get_meta(self::REVIEW_EFFECT_IDENTITY_META, true),
            'effect_code' => (string) $order->get_meta(self::REVIEW_EFFECT_CODE_META, true),
            'event_id' => (string) $order->get_meta(self::REVIEW_EFFECT_EVENT_META, true),
            'session_id' => (string) $order->get_meta(self::REVIEW_EFFECT_SESSION_META, true),
            'payment_id' => (string) $order->get_meta(self::REVIEW_EFFECT_PAYMENT_META, true),
            'mode' => $mode,
            'amount' => $amount,
            'currency' => $order->get_currency(),
            'order_evidence_fingerprint' => Gateway::provider_payment_evidence_fingerprint($order),
            'prior_status' => $order->get_status(),
            'prior_is_paid' => $order->is_paid(),
            'prior_payment_method' => $order->get_payment_method(),
            'prior_payment_method_title' => $order->get_payment_method_title(),
            'prior_transaction_id' => (string) $order->get_transaction_id(),
            'prior_paid_at' => $paid_at,
            'prior_resolved_evidence_fingerprint' => (string) $order->get_meta(
                '_bactive_paymongo_resolved_evidence_fingerprint',
                true
            ),
            'prior_resolved_payment_pending' => (string) $order->get_meta(
                self::RESOLVED_PAYMENT_PENDING_META,
                true
            ),
        );
        return $source['order_id'] > 0 && $source['code'] !== '' ? $source : null;
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>|null
     */
    private static function arm_review_resolution(
        \WC_Order $target_order,
        array $source,
        string $target_fingerprint,
        string $target_pending
    ): ?array {
        $record = array_merge(
            array(
                'status' => 'armed',
                'kind' => 'review_resolution',
                'type' => 'operator_verified_no_reemit',
            ),
            $source,
            array(
                'target_resolved_evidence_fingerprint' => $target_fingerprint,
                'target_resolved_payment_pending' => $target_pending,
                'resolved_by' => function_exists('get_current_user_id')
                    ? (int) get_current_user_id()
                    : 0,
                'recorded_at' => time(),
            )
        );
        if (!self::review_resolution_record_valid($record)
            || !self::review_resolution_final_state_matches($target_order, $record)) {
            return null;
        }

        $option = self::review_resolution_option((int) $record['order_id'], (string) $record['mode']);
        $existing = get_option($option, null);
        if ($existing !== null) {
            if (!is_array($existing)
                || !self::review_resolution_record_valid($existing)
                || ($existing['status'] ?? '') !== 'done'
                || (int) ($existing['order_id'] ?? 0) !== (int) $record['order_id']
                || get_option(self::review_resolution_receipt_option($existing), null) !== $existing
                || !Order_Lock::delete_option_if_exact($option, $existing)
                || get_option($option, null) !== null) {
                return null;
            }
        }
        if (!add_option($option, $record, '', false)) {
            return null;
        }
        $readback = get_option($option, null);
        return is_array($readback) && $readback === $record ? $record : null;
    }

    /** @param array<string,mixed> $expected @return array<string,mixed>|null */
    private static function begin_review_resolution(array $expected): ?array
    {
        if (!self::review_resolution_record_valid($expected)
            || !in_array((string) $expected['status'], array('armed', 'processing'), true)) {
            return null;
        }
        $option = self::review_resolution_option(
            (int) $expected['order_id'],
            (string) $expected['mode']
        );
        $record = get_option($option, null);
        if (!is_array($record) || $record !== $expected) {
            return null;
        }
        if ($record['status'] === 'armed') {
            $record['status'] = 'processing';
            $record['started_at'] = time();
            update_option($option, $record, false);
        }
        $readback = get_option($option, null);
        return is_array($readback)
            && $readback === $record
            && self::review_resolution_record_valid($readback)
            && $readback['status'] === 'processing'
                ? $readback
                : null;
    }

    /** @param array<string,mixed> $record */
    private static function complete_review_resolution(\WC_Order $order, array $record): bool
    {
        if (!Order_Lock::held_by_request($order->get_id())
            || !Order_Lock::renew($order->get_id())) {
            return false;
        }
        $record = self::begin_review_resolution($record);
        if (!is_array($record) || !Order_Lock::renew((int) $record['order_id'])) {
            return false;
        }

        $current = self::fresh_order((int) $record['order_id']);
        $recovery = $current instanceof \WC_Order
            ? self::review_resolution_recovery_context($current)
            : null;
        if (!is_array($recovery) || $recovery['record'] !== $record) {
            return false;
        }

        if (!self::review_resolution_final_state_matches($current, $record)) {
            self::apply_review_resolution_target($current, $record);
            if (!self::review_resolution_final_state_matches($current, $record)
                || !Order_Lock::renew((int) $record['order_id'])) {
                return false;
            }
            try {
                self::save_with_status_effects_suppressed(
                    $current,
                    null,
                    static fn(\WC_Order $saving_order): bool => self::review_resolution_final_state_matches(
                        $saving_order,
                        $record
                    )
                );
            } catch (\Throwable $error) {
                // The external processing intent remains authoritative across
                // any row-level metadata tear.
            }
        }

        $resolved = self::fresh_order((int) $record['order_id']);
        if (!$resolved instanceof \WC_Order
            || !self::review_resolution_final_state_matches($resolved, $record)) {
            return false;
        }

        $review_option = Reconciler::review_incident_option(
            (int) $record['order_id'],
            (string) $record['code'],
            (string) $record['mode']
        );
        if (get_option($review_option, null) !== null) {
            delete_option($review_option);
            if (get_option($review_option, null) !== null) {
                return false;
            }
        }
        if (!self::finish_review_resolution($record)) {
            return false;
        }

        // REQUIRED_META deliberately survives until the external intent is
        // done. The normal reconciliation lane now owns its removal (or the
        // paid-disposition action consumes it), which keeps this order visible
        // to source-of-truth scans across scheduler loss and process crashes.
        Reconciler::schedule_order((int) $record['order_id']);

        try {
            $resolved->add_order_note(
                sprintf(
                    /* translators: %s: sanitized reconciliation reason */
                    __('PayMongo reconciliation review explicitly resolved by an authorized operator. Prior reason: %s. Payment facts were retained; order status and prior WooCommerce effects were not replayed.', 'bactive-paymongo'),
                    (string) $record['code']
                )
            );
        } catch (\Throwable $error) {
            // The completed intent is the authoritative resolution audit.
        }
        return true;
    }

    /** @param array<string,mixed> $record */
    private static function apply_review_resolution_target(\WC_Order $order, array $record): void
    {
        foreach (array(
            Reconciler::UNRESOLVED_META,
            '_bactive_paymongo_review_required',
            '_bactive_paymongo_review_incidents',
            self::REVIEW_EFFECT_IDENTITY_META,
            self::REVIEW_EFFECT_CODE_META,
            self::REVIEW_EFFECT_EVENT_META,
            self::REVIEW_EFFECT_SESSION_META,
            self::REVIEW_EFFECT_PAYMENT_META,
            self::REVIEW_EFFECT_MODE_META,
            self::REVIEW_MODE_META,
        ) as $key) {
            $order->delete_meta_data($key);
        }
        $order->update_meta_data(Reconciler::REQUIRED_META, 'yes');
        $target_fingerprint = (string) $record['target_resolved_evidence_fingerprint'];
        if ($target_fingerprint === '') {
            $order->delete_meta_data('_bactive_paymongo_resolved_evidence_fingerprint');
        } else {
            $order->update_meta_data(
                '_bactive_paymongo_resolved_evidence_fingerprint',
                $target_fingerprint
            );
        }
        $target_pending = (string) $record['target_resolved_payment_pending'];
        if ($target_pending === '') {
            $order->delete_meta_data(self::RESOLVED_PAYMENT_PENDING_META);
        } else {
            $order->update_meta_data(self::RESOLVED_PAYMENT_PENDING_META, $target_pending);
        }
    }

    /** @return array{record:array<string,mixed>}|null */
    private static function review_resolution_recovery_context(\WC_Order $order): ?array
    {
        $active = array();
        foreach (self::review_resolution_candidate_records($order->get_id()) as $candidate) {
            $record = $candidate['record'];
            if (!is_array($record)
                || !in_array((string) ($record['status'] ?? ''), array('armed', 'processing'), true)) {
                continue;
            }
            if ((string) ($record['mode'] ?? '') !== $candidate['mode']
                || !self::review_resolution_record_valid($record)
                || !self::review_resolution_order_matches_record($order, $record)) {
                return null;
            }
            $active[] = $record;
        }
        return count($active) === 1 ? array('record' => $active[0]) : null;
    }

    /** @param array<string,mixed> $record */
    private static function review_resolution_record_valid(array $record): bool
    {
        $base_keys = array(
            'status', 'kind', 'type', 'order_id', 'code', 'effect_identity',
            'effect_code', 'event_id', 'session_id', 'payment_id', 'mode', 'amount',
            'currency', 'order_evidence_fingerprint', 'prior_status',
            'prior_is_paid', 'prior_payment_method', 'prior_payment_method_title',
            'prior_transaction_id', 'prior_paid_at',
            'prior_resolved_evidence_fingerprint', 'prior_resolved_payment_pending',
            'target_resolved_evidence_fingerprint', 'target_resolved_payment_pending',
            'resolved_by', 'recorded_at',
        );
        $status = (string) ($record['status'] ?? '');
        $expected_keys = $base_keys;
        if (in_array($status, array('processing', 'done'), true)) {
            $expected_keys[] = 'started_at';
        }
        if ($status === 'done') {
            $expected_keys[] = 'finished_at';
        }
        $actual_keys = array_keys($record);
        sort($actual_keys, SORT_STRING);
        sort($expected_keys, SORT_STRING);
        if ($actual_keys !== $expected_keys
            || !in_array($status, array('armed', 'processing', 'done'), true)
            || ($record['kind'] ?? '') !== 'review_resolution'
            || ($record['type'] ?? '') !== 'operator_verified_no_reemit'
            || !is_int($record['order_id'] ?? null)
            || (int) $record['order_id'] < 1
            || !is_int($record['amount'] ?? null)
            || (int) $record['amount'] < 0
            || !is_string($record['currency'] ?? null)
            || preg_match('/^[A-Z]{3}$/D', (string) $record['currency']) !== 1
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($record['order_evidence_fingerprint'] ?? ''))
            || (string) ($record['code'] ?? '') === ''
            || sanitize_key((string) $record['code']) !== (string) $record['code']
            || !self::valid_mode((string) ($record['mode'] ?? ''))
            || !is_bool($record['prior_is_paid'] ?? null)
            || (string) ($record['prior_status'] ?? '') === ''
            || sanitize_key((string) $record['prior_status']) !== (string) $record['prior_status']
            || sanitize_key((string) ($record['prior_payment_method'] ?? '')) !== (string) ($record['prior_payment_method'] ?? '')
            || !is_string($record['prior_payment_method_title'] ?? null)
            || strlen((string) $record['prior_payment_method_title']) > 255
            || !is_string($record['prior_transaction_id'] ?? null)
            || strlen((string) $record['prior_transaction_id']) > 255
            || !is_int($record['prior_paid_at'] ?? null)
            || (int) $record['prior_paid_at'] < 0
            || !is_int($record['resolved_by'] ?? null)
            || (int) $record['resolved_by'] < 1
            || !is_int($record['recorded_at'] ?? null)
            || (int) $record['recorded_at'] < 1
            || (int) $record['recorded_at'] > (time() + 300)) {
            return false;
        }

        foreach (array(
            'prior_resolved_evidence_fingerprint',
            'prior_resolved_payment_pending',
            'target_resolved_evidence_fingerprint',
            'target_resolved_payment_pending',
        ) as $key) {
            $value = (string) ($record[$key] ?? '');
            if ($value !== '' && !preg_match('/^[a-f0-9]{64}$/D', $value)) {
                return false;
            }
        }
        if ((string) $record['target_resolved_payment_pending'] !== ''
            && (string) $record['target_resolved_payment_pending']
                !== (string) $record['target_resolved_evidence_fingerprint']) {
            return false;
        }

        $effect_values = array(
            (string) ($record['effect_identity'] ?? ''),
            (string) ($record['effect_code'] ?? ''),
            (string) ($record['event_id'] ?? ''),
            (string) ($record['session_id'] ?? ''),
            (string) ($record['payment_id'] ?? ''),
        );
        $has_effect = array_filter($effect_values, static fn(string $value): bool => $value !== '') !== array();
        if ($has_effect
            && ((string) $record['effect_identity'] === ''
                || (string) $record['event_id'] !== (string) $record['effect_identity']
                || (string) $record['effect_code'] !== (string) $record['code'])) {
            return false;
        }
        foreach ($effect_values as $value) {
            if (strlen($value) > 255 || sanitize_text_field($value) !== $value) {
                return false;
            }
        }

        if (in_array($status, array('processing', 'done'), true)
            && (!is_int($record['started_at'] ?? null)
                || (int) $record['started_at'] < (int) $record['recorded_at']
                || (int) $record['started_at'] > (time() + 300))) {
            return false;
        }
        return $status !== 'done'
            || (is_int($record['finished_at'] ?? null)
                && (int) $record['finished_at'] >= (int) $record['started_at']
                && (int) $record['finished_at'] <= (time() + 300));
    }

    /** @param array<string,mixed> $record */
    private static function review_resolution_order_matches_record(
        \WC_Order $order,
        array $record
    ): bool {
        $amount = Integrity::amount_to_minor((string) $order->get_total());
        $date_paid = $order->get_date_paid('edit');
        if (!self::review_resolution_record_valid($record)
            || !self::generic_review_resolution_state_is_eligible($order)
            || $order->get_id() !== (int) $record['order_id']
            || $amount !== (int) $record['amount']
            || $order->get_currency() !== (string) $record['currency']
            || $order->get_status() !== (string) $record['prior_status']
            || $order->is_paid() !== (bool) $record['prior_is_paid']
            || $order->get_payment_method() !== (string) $record['prior_payment_method']
            || $order->get_payment_method_title() !== (string) $record['prior_payment_method_title']
            || (string) $order->get_transaction_id() !== (string) $record['prior_transaction_id']
            || ($date_paid !== null
                && (!is_object($date_paid) || !is_callable(array($date_paid, 'getTimestamp'))))
            || (is_object($date_paid) ? (int) $date_paid->getTimestamp() : 0)
                !== (int) $record['prior_paid_at']
            || !hash_equals(
                (string) $record['order_evidence_fingerprint'],
                Gateway::provider_payment_evidence_fingerprint($order)
            )
            || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== ''
            || $order->get_meta(self::OPERATOR_DISPOSITION_META, true) !== '') {
            return false;
        }

        $either = static fn(string $actual, string $before, string $after): bool => in_array(
            $actual,
            array_values(array_unique(array($before, $after))),
            true
        );
        foreach (array(
            Reconciler::UNRESOLVED_META => array((string) $record['code'], ''),
            '_bactive_paymongo_review_required' => array((string) $record['code'], ''),
            Reconciler::REQUIRED_META => array('yes', 'yes'),
            self::REVIEW_EFFECT_IDENTITY_META => array((string) $record['effect_identity'], ''),
            self::REVIEW_EFFECT_CODE_META => array((string) $record['effect_code'], ''),
            self::REVIEW_EFFECT_EVENT_META => array((string) $record['event_id'], ''),
            self::REVIEW_EFFECT_SESSION_META => array((string) $record['session_id'], ''),
            self::REVIEW_EFFECT_PAYMENT_META => array((string) $record['payment_id'], ''),
            self::REVIEW_EFFECT_MODE_META => array((string) $record['mode'], ''),
            self::REVIEW_MODE_META => array((string) $record['mode'], ''),
            '_bactive_paymongo_resolved_evidence_fingerprint' => array(
                (string) $record['prior_resolved_evidence_fingerprint'],
                (string) $record['target_resolved_evidence_fingerprint'],
            ),
            self::RESOLVED_PAYMENT_PENDING_META => array(
                (string) $record['prior_resolved_payment_pending'],
                (string) $record['target_resolved_payment_pending'],
            ),
        ) as $key => $values) {
            if (!$either((string) $order->get_meta($key, true), $values[0], $values[1])) {
                return false;
            }
        }
        $incidents = $order->get_meta('_bactive_paymongo_review_incidents', true);
        if ($incidents !== '' && (int) $incidents !== 1) {
            return false;
        }

        if ((string) $record['effect_identity'] !== '') {
            $effect = self::effects_record(
                'review',
                (string) $record['effect_identity'],
                (string) $record['mode']
            );
            $quarantine = get_option(
                self::quarantine_option(
                    (string) $record['effect_identity'],
                    (string) $record['mode']
                ),
                null
            );
            if (!is_array($effect)
                || (string) ($effect['status'] ?? '') !== 'done'
                || (string) ($effect['kind'] ?? '') !== 'review'
                || (string) ($effect['identity'] ?? '') !== (string) $record['effect_identity']
                || (string) ($effect['mode'] ?? '') !== (string) $record['mode']
                || (int) ($effect['order_id'] ?? 0) !== (int) $record['order_id']
                || (string) ($effect['to'] ?? '') !== 'on-hold'
                || (string) ($effect['code'] ?? '') !== (string) $record['code']
                || (string) ($effect['event_id'] ?? '') !== (string) $record['event_id']
                || (string) ($effect['session_id'] ?? '') !== (string) $record['session_id']
                || (string) ($effect['payment_id'] ?? '') !== (string) $record['payment_id']
                || !is_array($quarantine)
                || !self::quarantine_record_matches(
                    $quarantine,
                    array(
                        'code' => (string) $record['code'],
                        'event_id' => (string) $record['event_id'],
                        'session_id' => (string) $record['session_id'],
                        'payment_id' => (string) $record['payment_id'],
                        'order_id' => (int) $record['order_id'],
                        'mode' => (string) $record['mode'],
                        'recorded_at' => (int) ($quarantine['recorded_at'] ?? 0),
                    ),
                    true
                )) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $record */
    private static function review_resolution_final_state_matches(
        \WC_Order $order,
        array $record
    ): bool {
        if (!self::review_resolution_order_matches_record($order, $record)) {
            return false;
        }
        foreach (array(
            Reconciler::UNRESOLVED_META,
            '_bactive_paymongo_review_required',
            '_bactive_paymongo_review_incidents',
            self::REVIEW_EFFECT_IDENTITY_META,
            self::REVIEW_EFFECT_CODE_META,
            self::REVIEW_EFFECT_EVENT_META,
            self::REVIEW_EFFECT_SESSION_META,
            self::REVIEW_EFFECT_PAYMENT_META,
            self::REVIEW_EFFECT_MODE_META,
            self::REVIEW_MODE_META,
        ) as $key) {
            if ((string) $order->get_meta($key, true) !== '') {
                return false;
            }
        }
        return (string) $order->get_meta(Reconciler::REQUIRED_META, true) === 'yes'
            && (string) $order->get_meta('_bactive_paymongo_resolved_evidence_fingerprint', true)
                === (string) $record['target_resolved_evidence_fingerprint']
            && (string) $order->get_meta(self::RESOLVED_PAYMENT_PENDING_META, true)
                === (string) $record['target_resolved_payment_pending'];
    }

    /** @param array<string,mixed> $record */
    private static function finish_review_resolution(array $record): bool
    {
        if (!self::review_resolution_record_valid($record)
            || ($record['status'] ?? '') !== 'processing') {
            return false;
        }
        $option = self::review_resolution_option((int) $record['order_id'], (string) $record['mode']);
        $stored = get_option($option, null);
        if (!is_array($stored) || $stored !== $record) {
            return false;
        }
        $record['status'] = 'done';
        $record['finished_at'] = time();
        $receipt_option = self::review_resolution_receipt_option($record);
        if (!add_option($receipt_option, $record, '', false)) {
            $receipt = get_option($receipt_option, null);
            if (!is_array($receipt)
                || !self::review_resolution_record_valid($receipt)
                || ($receipt['status'] ?? '') !== 'done'
                || self::review_resolution_intent_fingerprint($receipt)
                    !== self::review_resolution_intent_fingerprint($record)) {
                return false;
            }
            $record = $receipt;
        }
        if (get_option($receipt_option, null) !== $record) {
            return false;
        }
        update_option($option, $record, false);
        $readback = get_option($option, null);
        return is_array($readback)
            && $readback === $record
            && self::review_resolution_record_valid($readback)
            && $readback['status'] === 'done';
    }

    /** @param array<string,mixed> $record */
    private static function review_resolution_intent_fingerprint(array $record): string
    {
        unset($record['status'], $record['started_at'], $record['finished_at']);
        ksort($record, SORT_STRING);
        return hash('sha256', serialize($record));
    }

    private static function review_resolution_option(int $order_id, string $mode): string
    {
        return self::REVIEW_RESOLUTION_OPTION_PREFIX . $mode . '_'
            . hash('sha256', $mode . '|' . $order_id);
    }

    /** @param array<string,mixed> $record */
    private static function review_resolution_receipt_option(array $record): string
    {
        return self::REVIEW_RESOLUTION_RECEIPT_PREFIX
            . self::review_resolution_intent_fingerprint($record);
    }

    /** @return array<int,array{mode:string,record:mixed}> */
    private static function review_resolution_candidate_records(int $order_id): array
    {
        $records = array();
        foreach (array('test', 'live', 'local') as $mode) {
            $records[] = array(
                'mode' => $mode,
                'record' => get_option(self::review_resolution_option($order_id, $mode), null),
            );
        }
        return $records;
    }

    public static function resolved_payment_disposition_action_available(\WC_Order $order): bool
    {
        if (self::operator_disposition_recovery_context($order) !== null) {
            return true;
        }
        $context = self::resolved_payment_context($order);
        return is_array($context)
            && get_option(
                self::operator_disposition_option(
                    (string) $context['payment_id'],
                    (string) $context['mode']
                ),
                null
            ) === null;
    }

    /**
     * A durable external intent keeps a torn CPT/HPOS order write recoverable
     * even when the order-side pending marker was part of the half that saved.
     */
    public static function operator_disposition_recovery_pending(\WC_Order $order): bool
    {
        foreach (self::operator_disposition_candidate_contexts($order) as $candidate) {
            $record = get_option(self::operator_disposition_option(
                $candidate['payment_id'],
                $candidate['mode']
            ), null);
            if (is_array($record)
                && in_array((string) ($record['status'] ?? ''), array('armed', 'processing'), true)
                && (int) ($record['order_id'] ?? 0) === $order->get_id()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Record one exact provider-paid quarantine after a second, explicit human
     * decision. This path deliberately suppresses WooCommerce payment/status
     * hooks: the operator must independently verify or perform every business
     * effect before selecting the action.
     */
    public static function finalize_resolved_payment_for_operator(\WC_Order $order): bool
    {
        $order_id = $order->get_id();
        if (!current_user_can('manage_woocommerce')
            || !Order_Lock::held_by_request($order_id)
            || !Order_Lock::renew($order_id)) {
            return false;
        }

        $current = self::fresh_order($order_id);
        if (!$current instanceof \WC_Order) {
            return false;
        }

        $recovery = self::operator_disposition_recovery_context($current);
        $context = is_array($recovery) ? null : self::resolved_payment_context($current);
        $record = is_array($recovery) ? $recovery['record'] : null;
        if (!is_array($record) && !is_array($context)) {
            return false;
        }

        $provider_context = is_array($record) ? $record : $context;
        $live = (bool) $provider_context['live'];
        $gateway = new Gateway(false);
        if ($gateway->is_test_mode() !== !$live) {
            return false;
        }
        $key = Secrets::api_key($live, $gateway);
        if ($key === '') {
            return false;
        }

        $response = (new Api_Client($key))->retrieve_checkout_session(
            (string) $provider_context['session_id']
        );
        if (is_wp_error($response) || !Order_Lock::renew($order_id)) {
            return false;
        }

        // The provider round trip is a race boundary. Re-read every local fact
        // under the database fence and require byte-for-byte equivalent context
        // before the provider response can authorize a state change.
        $verified_order = self::fresh_order($order_id);
        if (!$verified_order instanceof \WC_Order) {
            return false;
        }
        if (is_array($record)) {
            $verified_recovery = self::operator_disposition_recovery_context($verified_order);
            if (!is_array($verified_recovery)
                || $verified_recovery['record'] !== $record) {
                return false;
            }
        } else {
            $verified_context = self::resolved_payment_context($verified_order);
            if (!is_array($verified_context) || $verified_context !== $context) {
                return false;
            }
        }

        if (!is_array($response)) {
            return false;
        }
        $validated = self::validate_operator_disposition_provider_response(
            $response,
            $provider_context,
            $order_id
        );
        if (!is_array($validated)
            || !self::payment_facts_match(
                $verified_order,
                $validated,
                (string) $provider_context['payment_id']
            )
            || self::payment_identity_conflicts(
                $verified_order,
                $validated,
                (string) $provider_context['payment_id']
            )) {
            return false;
        }

        if (!is_array($record)) {
            $default_status = $verified_order->needs_processing() ? 'processing' : 'completed';
            $next_status = sanitize_key((string) apply_filters(
                'woocommerce_payment_complete_order_status',
                $default_status,
                $order_id,
                $verified_order
            ));
            $paid_statuses = function_exists('wc_get_is_paid_statuses')
                ? array_map('sanitize_key', (array) wc_get_is_paid_statuses())
                : array('processing', 'completed');
            $gateway_title = sanitize_text_field(is_callable(array($gateway, 'get_title'))
                ? (string) $gateway->get_title()
                : (string) $gateway->title);
            if ($next_status === ''
                || !in_array($next_status, $paid_statuses, true)
                || $gateway_title === ''
                || strlen($gateway_title) > 255
                || !Order_Lock::renew($order_id)) {
                return false;
            }

            $final_order = self::fresh_order($order_id);
            $final_context = $final_order instanceof \WC_Order
                ? self::resolved_payment_context($final_order)
                : null;
            if (!is_array($final_context) || $final_context !== $context) {
                return false;
            }
            $record = self::arm_operator_disposition(
                $final_order,
                $final_context,
                $next_status,
                $gateway_title
            );
            if (!is_array($record)) {
                return false;
            }
        }

        $record = self::begin_operator_disposition($record);
        if (!is_array($record) || !Order_Lock::renew($order_id)) {
            return false;
        }

        $final_order = self::fresh_order($order_id);
        $active_recovery = $final_order instanceof \WC_Order
            ? self::operator_disposition_recovery_context($final_order)
            : null;
        if (!is_array($active_recovery) || $active_recovery['record'] !== $record) {
            return false;
        }

        $disposition = self::operator_disposition_audit($record);
        $transition = null;
        if (!self::operator_disposition_final_state_matches($final_order, $record)) {
            if (!self::operator_disposition_order_matches_record($final_order, $record)
                || !Order_Lock::renew($order_id)) {
                return false;
            }

            $final_order->set_payment_method(GATEWAY_ID);
            $final_order->set_payment_method_title((string) $record['gateway_title']);
            $final_order->set_transaction_id((string) $record['payment_id']);
            $final_order->set_date_paid((int) $record['paid_at']);
            $final_order->delete_meta_data('_bactive_paymongo_unexpected_payment_id');
            $final_order->delete_meta_data(self::UNEXPECTED_MODE_META);
            $final_order->delete_meta_data('_bactive_paymongo_resolved_evidence_fingerprint');
            $final_order->delete_meta_data(self::RESOLVED_PAYMENT_PENDING_META);
            $final_order->delete_meta_data(Reconciler::REQUIRED_META);
            $final_order->delete_meta_data('_bactive_paymongo_reconcile_poll_count');
            $final_order->update_meta_data(self::OPERATOR_DISPOSITION_META, $disposition);

            if ($final_order->get_status() === (string) $record['prior_status']) {
                $final_order->set_status((string) $record['target_status'], false);
                $transition = self::take_status_transition($final_order);
                if (!self::transition_matches(
                    $transition,
                    (string) $record['prior_status'],
                    (string) $record['target_status']
                )) {
                    return false;
                }
            } elseif ($final_order->get_status() !== (string) $record['target_status']) {
                return false;
            }

            try {
                self::save_with_status_effects_suppressed(
                    $final_order,
                    $transition,
                    static fn(\WC_Order $saving_order): bool => self::operator_disposition_final_state_matches(
                        $saving_order,
                        $record
                    )
                );
            } catch (\Throwable $error) {
                // CPT and HPOS persist core fields and custom metadata in
                // opposite orders. The external processing intent below is
                // deliberately retained until an exact readback converges.
            }
        }

        $paid = self::fresh_order($order_id);
        if (!$paid instanceof \WC_Order
            || !self::operator_disposition_final_state_matches($paid, $record)
            || !self::payment_facts_match($paid, $validated, (string) $record['payment_id'])
            || self::payment_identity_conflicts($paid, $validated, (string) $record['payment_id'])
            || Gateway::has_inconsistent_provider_payment_state($paid)
            || !self::finish_operator_disposition($record)) {
            return false;
        }

        try {
            $paid->add_order_note(
                __('An authorized operator recorded the independently verified PayMongo payment without replaying WooCommerce payment, status, stock, email, or fulfillment effects.', 'bactive-paymongo')
            );
        } catch (\Throwable $error) {
            // The order audit plus completed external intent are authoritative.
        }
        return true;
    }

    /**
     * @param array<string,mixed> $response
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private static function validate_operator_disposition_provider_response(
        array $response,
        array $context,
        int $order_id
    ): ?array {
        $session = $response['data'] ?? null;
        if (!is_array($session)) {
            return null;
        }
        $live = (bool) ($context['live'] ?? false);
        $validated = Integrity::validate_paid_event(
            array(
                'data' => array(
                    'id' => (string) ($context['event_id'] ?? ''),
                    'type' => 'event',
                    'attributes' => array(
                        'type' => 'checkout_session.payment.paid',
                        'livemode' => $live,
                        'data' => $session,
                    ),
                ),
            ),
            array(
                'live' => $live,
                'order_id' => $order_id,
                'amount' => (int) ($context['amount'] ?? -1),
                'reference' => (string) ($context['reference'] ?? ''),
                'correlation' => (string) ($context['correlation'] ?? ''),
                'session_ids' => array((string) ($context['session_id'] ?? '')),
            )
        );
        foreach (array('event_id', 'session_id', 'payment_id', 'method', 'provider') as $key) {
            if (empty($validated['ok'])
                || (string) ($validated[$key] ?? '') !== (string) ($context[$key] ?? '')) {
                return null;
            }
        }
        $mode = (string) ($context['mode'] ?? '');
        if (!in_array($mode, array('test', 'live'), true)
            || $live !== ($mode === 'live')) {
            return null;
        }
        $validated['mode'] = $mode;
        return (int) ($validated['amount'] ?? -1) === (int) ($context['amount'] ?? -2)
            ? $validated
            : null;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private static function arm_operator_disposition(
        \WC_Order $order,
        array $context,
        string $target_status,
        string $gateway_title
    ): ?array {
        $resolved_by = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $record = array(
            'status' => 'armed',
            'kind' => 'operator_disposition',
            'type' => 'paid_verified_no_reemit',
            'identity' => (string) $context['payment_id'],
            'payment_id' => (string) $context['payment_id'],
            'order_id' => $order->get_id(),
            'event_id' => (string) $context['event_id'],
            'session_id' => (string) $context['session_id'],
            'method' => (string) $context['method'],
            'provider' => (string) $context['provider'],
            'mode' => (string) $context['mode'],
            'live' => (bool) $context['live'],
            'amount' => (int) $context['amount'],
            'currency' => 'PHP',
            'reference' => (string) $context['reference'],
            'correlation' => (string) $context['correlation'],
            'paid_at' => (int) $context['paid_at'],
            'quarantine_code' => (string) $context['quarantine_code'],
            'resolved_evidence_fingerprint' => (string) $context['fingerprint'],
            'payment_evidence_fingerprint' => (string) $context['payment_evidence_fingerprint'],
            'target_status' => sanitize_key($target_status),
            'gateway_id' => GATEWAY_ID,
            'gateway_title' => sanitize_text_field($gateway_title),
            'prior_status' => $order->get_status(),
            'prior_payment_method' => $order->get_payment_method(),
            'prior_payment_method_title' => $order->get_payment_method_title(),
            'resolved_by' => $resolved_by,
            'recorded_at' => time(),
        );
        if (!self::operator_disposition_record_valid($record)) {
            return null;
        }

        $option = self::operator_disposition_option(
            (string) $record['payment_id'],
            (string) $record['mode']
        );
        if (!add_option($option, $record, '', false)) {
            return null;
        }
        $readback = get_option($option, null);
        return is_array($readback) && $readback === $record ? $record : null;
    }

    /** @param array<string,mixed> $expected @return array<string,mixed>|null */
    private static function begin_operator_disposition(array $expected): ?array
    {
        if (!self::operator_disposition_record_valid($expected)
            || !in_array((string) $expected['status'], array('armed', 'processing'), true)) {
            return null;
        }
        $option = self::operator_disposition_option(
            (string) $expected['payment_id'],
            (string) $expected['mode']
        );
        $record = get_option($option, null);
        if (!is_array($record) || $record !== $expected) {
            return null;
        }
        if ($record['status'] === 'armed') {
            $record['status'] = 'processing';
            $record['started_at'] = time();
            update_option($option, $record, false);
        }
        $readback = get_option($option, null);
        return is_array($readback)
            && $readback === $record
            && self::operator_disposition_record_valid($readback)
            && $readback['status'] === 'processing'
                ? $readback
                : null;
    }

    /** @param array<string,mixed> $expected */
    private static function finish_operator_disposition(array $expected): bool
    {
        if (!self::operator_disposition_record_valid($expected)
            || ($expected['status'] ?? '') !== 'processing') {
            return false;
        }
        $option = self::operator_disposition_option(
            (string) $expected['payment_id'],
            (string) $expected['mode']
        );
        $record = get_option($option, null);
        if (!is_array($record) || $record !== $expected) {
            return false;
        }
        $record['status'] = 'done';
        $record['finished_at'] = time();
        update_option($option, $record, false);
        $readback = get_option($option, null);
        return is_array($readback)
            && $readback === $record
            && self::operator_disposition_record_valid($readback)
            && $readback['status'] === 'done';
    }

    /** @return array{record:array<string,mixed>}|null */
    private static function operator_disposition_recovery_context(\WC_Order $order): ?array
    {
        $active = array();
        foreach (self::operator_disposition_candidate_contexts($order) as $candidate) {
            $record = get_option(self::operator_disposition_option(
                $candidate['payment_id'],
                $candidate['mode']
            ), null);
            if (!is_array($record)
                || !in_array((string) ($record['status'] ?? ''), array('armed', 'processing'), true)) {
                continue;
            }
            if (!self::operator_disposition_record_valid($record)
                || !self::operator_disposition_order_matches_record($order, $record)) {
                return null;
            }
            $active[] = $record;
        }
        return count($active) === 1 ? array('record' => $active[0]) : null;
    }

    /** @return array<int,array{payment_id:string,mode:string}> */
    private static function operator_disposition_candidate_contexts(\WC_Order $order): array
    {
        $candidates = array(
            array(
                'payment_id' => (string) $order->get_transaction_id(),
                'mode' => (string) $order->get_meta(self::PAID_MODE_META, true),
            ),
            array(
                'payment_id' => (string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true),
                'mode' => (string) $order->get_meta(self::UNEXPECTED_MODE_META, true),
            ),
        );
        $audit = $order->get_meta(self::OPERATOR_DISPOSITION_META, true);
        if (is_array($audit)) {
            $candidates[] = array(
                'payment_id' => (string) ($audit['payment_id'] ?? ''),
                'mode' => (string) ($audit['mode'] ?? ''),
            );
        }
        foreach (Gateway::order_attempts($order) as $attempt) {
            $mode = (string) ($attempt['mode'] ?? '');
            $candidates[] = array(
                'payment_id' => (string) ($attempt['payment_id'] ?? ''),
                'mode' => $mode,
            );
            foreach ((array) ($attempt['reconciliation_payment_ids'] ?? array()) as $payment_id) {
                if (is_string($payment_id)) {
                    $candidates[] = array('payment_id' => $payment_id, 'mode' => $mode);
                }
            }
        }
        $unique = array();
        foreach ($candidates as $candidate) {
            $payment_id = (string) ($candidate['payment_id'] ?? '');
            $mode = (string) ($candidate['mode'] ?? '');
            if (preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', $payment_id) !== 1
                || !in_array($mode, array('test', 'live'), true)) {
                continue;
            }
            $unique[$mode . '|' . $payment_id] = array(
                'payment_id' => $payment_id,
                'mode' => $mode,
            );
        }
        return array_values($unique);
    }

    /** @param array<string,mixed> $record */
    private static function operator_disposition_record_valid(array $record): bool
    {
        $base_keys = array(
            'status', 'kind', 'type', 'identity', 'payment_id', 'order_id',
            'event_id', 'session_id', 'method', 'provider', 'mode', 'live',
            'amount', 'currency', 'reference', 'correlation', 'paid_at',
            'quarantine_code', 'resolved_evidence_fingerprint',
            'payment_evidence_fingerprint', 'target_status', 'gateway_id',
            'gateway_title', 'prior_status', 'prior_payment_method',
            'prior_payment_method_title', 'resolved_by', 'recorded_at',
        );
        $status = (string) ($record['status'] ?? '');
        $expected_keys = $base_keys;
        if (in_array($status, array('processing', 'done'), true)) {
            $expected_keys[] = 'started_at';
        }
        if ($status === 'done') {
            $expected_keys[] = 'finished_at';
        }
        $actual_keys = array_keys($record);
        sort($actual_keys, SORT_STRING);
        sort($expected_keys, SORT_STRING);
        if ($actual_keys !== $expected_keys
            || !in_array($status, array('armed', 'processing', 'done'), true)
            || ($record['kind'] ?? '') !== 'operator_disposition'
            || ($record['type'] ?? '') !== 'paid_verified_no_reemit'
            || !is_int($record['order_id'] ?? null)
            || (int) $record['order_id'] < 1
            || !is_int($record['amount'] ?? null)
            || (int) $record['amount'] < 1
            || ($record['currency'] ?? '') !== 'PHP'
            || !is_bool($record['live'] ?? null)
            || !is_int($record['paid_at'] ?? null)
            || !is_int($record['resolved_by'] ?? null)
            || (int) $record['resolved_by'] < 1
            || !is_int($record['recorded_at'] ?? null)
            || (int) $record['recorded_at'] < 1
            || (int) $record['recorded_at'] > (time() + 300)
            || (int) $record['paid_at'] < 1
            || (int) $record['paid_at'] > ((int) $record['recorded_at'] + 300)
            || !preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', (string) ($record['payment_id'] ?? ''))
            || (string) ($record['identity'] ?? '') !== (string) $record['payment_id']
            || !preg_match('/^evt_[A-Za-z0-9_-]{3,128}$/D', (string) ($record['event_id'] ?? ''))
            || !preg_match('/^cs_[A-Za-z0-9_-]{3,128}$/D', (string) ($record['session_id'] ?? ''))
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($record['resolved_evidence_fingerprint'] ?? ''))
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($record['payment_evidence_fingerprint'] ?? ''))
            || (string) ($record['quarantine_code'] ?? '') === ''
            || sanitize_key((string) $record['quarantine_code']) !== (string) $record['quarantine_code']
            || (string) ($record['gateway_id'] ?? '') !== GATEWAY_ID
            || (string) ($record['prior_status'] ?? '') !== 'on-hold'
            || sanitize_key((string) ($record['prior_payment_method'] ?? '')) !== (string) ($record['prior_payment_method'] ?? '')
            || !is_string($record['gateway_title'] ?? null)
            || (string) $record['gateway_title'] === ''
            || strlen((string) $record['gateway_title']) > 255
            || sanitize_text_field((string) $record['gateway_title']) !== (string) $record['gateway_title']
            || !is_string($record['prior_payment_method_title'] ?? null)
            || strlen((string) $record['prior_payment_method_title']) > 255
            || !in_array((string) ($record['mode'] ?? ''), array('test', 'live'), true)
            || (bool) $record['live'] !== ((string) $record['mode'] === 'live')
            || !preg_match(
                '/^BA-' . preg_quote((string) $record['order_id'], '/') . '-[1-9][0-9]*$/D',
                (string) ($record['reference'] ?? '')
            )
            || !is_string($record['correlation'] ?? null)
            || (string) $record['correlation'] === ''
            || strlen((string) $record['correlation']) > 255) {
            return false;
        }

        $method = (string) ($record['method'] ?? '');
        $provider = (string) ($record['provider'] ?? '');
        $source_valid = in_array($method, array('qrph', 'paymaya', 'shopee_pay'), true)
            ? $provider === ''
            : ($method === 'dob' && in_array($provider, array('bpi', 'ubp'), true));
        $paid_statuses = function_exists('wc_get_is_paid_statuses')
            ? array_map('sanitize_key', (array) wc_get_is_paid_statuses())
            : array('processing', 'completed');
        if (!$source_valid
            || !in_array((string) ($record['target_status'] ?? ''), $paid_statuses, true)) {
            return false;
        }

        if (in_array($status, array('processing', 'done'), true)
            && (!is_int($record['started_at'] ?? null)
                || (int) $record['started_at'] < (int) $record['recorded_at']
                || (int) $record['started_at'] > (time() + 300))) {
            return false;
        }
        return $status !== 'done'
            || (is_int($record['finished_at'] ?? null)
                && (int) $record['finished_at'] >= (int) $record['started_at']
                && (int) $record['finished_at'] <= (time() + 300));
    }

    /** @param array<string,mixed> $record */
    private static function operator_disposition_order_matches_record(
        \WC_Order $order,
        array $record
    ): bool {
        $amount = Integrity::amount_to_minor((string) $order->get_total());
        $required = (string) $order->get_meta(Reconciler::REQUIRED_META, true);
        if (!self::operator_disposition_record_valid($record)
            || $order->get_id() !== (int) $record['order_id']
            || $order->get_currency() !== (string) $record['currency']
            || $amount !== (int) $record['amount']
            || !in_array($required, array('', 'yes'), true)
            || Gateway::has_outstanding_attempts($order)
            || !hash_equals(
                (string) $record['payment_evidence_fingerprint'],
                self::operator_payment_evidence_fingerprint($order)
            )
            || !hash_equals(
                (string) $record['resolved_evidence_fingerprint'],
                self::reconstructed_resolved_evidence_fingerprint($order, $record)
            )) {
            return false;
        }

        foreach (array(
            '_bactive_paymongo_settlement_pending',
            self::SETTLEMENT_MODE_META,
            Reconciler::UNRESOLVED_META,
            '_bactive_paymongo_review_required',
            '_bactive_paymongo_review_incidents',
            self::PROCESSING_CODE_META,
            self::PROCESSING_PAYMENT_META,
            self::PROCESSING_EVENT_META,
            self::PROCESSING_SESSION_META,
            self::PROCESSING_MODE_META,
            self::REVIEW_MODE_META,
            self::REVIEW_EFFECT_IDENTITY_META,
            self::REVIEW_EFFECT_CODE_META,
            self::REVIEW_EFFECT_EVENT_META,
            self::REVIEW_EFFECT_SESSION_META,
            self::REVIEW_EFFECT_PAYMENT_META,
            self::REVIEW_EFFECT_MODE_META,
        ) as $key) {
            if ((string) $order->get_meta($key, true) !== '') {
                return false;
            }
        }

        foreach (array(
            '_bactive_paymongo_paid_event_id' => 'event_id',
            '_bactive_paymongo_paid_session_id' => 'session_id',
            '_bactive_paymongo_source_method' => 'method',
            '_bactive_paymongo_source_provider' => 'provider',
            self::PAID_MODE_META => 'mode',
        ) as $meta_key => $record_key) {
            if (!is_callable(array($order, 'meta_exists'))
                || !$order->meta_exists($meta_key)
                || (string) $order->get_meta($meta_key, true) !== (string) $record[$record_key]) {
                return false;
            }
        }

        $matching_attempts = 0;
        foreach (Gateway::order_attempts($order) as $attempt) {
            $attempt_payment_id = (string) ($attempt['payment_id'] ?? '');
            $attempt_event_id = (string) ($attempt['paid_event_id'] ?? '');
            $attempt_paid_at = (int) ($attempt['paid_at'] ?? 0);
            if (($attempt_payment_id !== '' && $attempt_payment_id !== (string) $record['payment_id'])
                || ($attempt_event_id !== '' && $attempt_event_id !== (string) $record['event_id'])
                || ($attempt_paid_at > 0 && (string) ($attempt['session_id'] ?? '') !== (string) $record['session_id'])) {
                return false;
            }
            foreach ((array) ($attempt['reconciliation_payment_ids'] ?? array()) as $payment_id) {
                if (!is_string($payment_id) || $payment_id !== (string) $record['payment_id']) {
                    return false;
                }
            }
            if ((string) ($attempt['session_id'] ?? '') === (string) $record['session_id']
                && $attempt_payment_id === (string) $record['payment_id']
                && $attempt_event_id === (string) $record['event_id']
                && $attempt_paid_at === (int) $record['paid_at']
                && (string) ($attempt['mode'] ?? '') === (string) $record['mode']
                && (string) ($attempt['reference'] ?? '') === (string) $record['reference']
                && (string) ($attempt['correlation_id'] ?? '') === (string) $record['correlation']) {
                ++$matching_attempts;
            }
        }
        if ($matching_attempts !== 1) {
            return false;
        }

        $quarantine = get_option(
            self::quarantine_option((string) $record['event_id'], (string) $record['mode']),
            null
        );
        $effect = self::effects_record(
            'review',
            (string) $record['event_id'],
            (string) $record['mode']
        );
        if (!is_array($quarantine)
            || !self::quarantine_record_matches(
                $quarantine,
                array(
                    'code' => (string) $record['quarantine_code'],
                    'event_id' => (string) $record['event_id'],
                    'session_id' => (string) $record['session_id'],
                    'payment_id' => (string) $record['payment_id'],
                    'order_id' => (int) $record['order_id'],
                    'mode' => (string) $record['mode'],
                    'recorded_at' => (int) ($quarantine['recorded_at'] ?? 0),
                ),
                true
            )
            || !is_array($effect)
            || (string) ($effect['status'] ?? '') !== 'done'
            || (string) ($effect['kind'] ?? '') !== 'review'
            || (string) ($effect['identity'] ?? '') !== (string) $record['event_id']
            || (string) ($effect['mode'] ?? '') !== (string) $record['mode']
            || (int) ($effect['order_id'] ?? 0) !== (int) $record['order_id']
            || (string) ($effect['to'] ?? '') !== (string) $record['prior_status']
            || (string) ($effect['code'] ?? '') !== (string) $record['quarantine_code']
            || (string) ($effect['event_id'] ?? '') !== (string) $record['event_id']
            || (string) ($effect['session_id'] ?? '') !== (string) $record['session_id']
            || (string) ($effect['payment_id'] ?? '') !== (string) $record['payment_id']) {
            return false;
        }

        if (!in_array($order->get_status(), array($record['prior_status'], $record['target_status']), true)
            || ($order->get_status() === $record['prior_status'] && $order->is_paid())
            || ($order->get_status() === $record['target_status'] && !$order->is_paid())
            || !in_array($order->get_payment_method(), array($record['prior_payment_method'], GATEWAY_ID), true)
            || !in_array($order->get_payment_method_title(), array($record['prior_payment_method_title'], $record['gateway_title']), true)
            || !in_array((string) $order->get_transaction_id(), array('', $record['payment_id']), true)) {
            return false;
        }

        $date_paid = $order->get_date_paid('edit');
        if ($date_paid !== null
            && (!is_object($date_paid) || !is_callable(array($date_paid, 'getTimestamp')))) {
            return false;
        }
        $paid_at = is_object($date_paid) && is_callable(array($date_paid, 'getTimestamp'))
            ? (int) $date_paid->getTimestamp()
            : 0;
        if (!in_array($paid_at, array(0, (int) $record['paid_at']), true)
            || !in_array(
                (string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true),
                array('', (string) $record['payment_id']),
                true
            )
            || !in_array(
                (string) $order->get_meta(self::UNEXPECTED_MODE_META, true),
                array('', (string) $record['mode']),
                true
            )
            || !in_array(
                (string) $order->get_meta('_bactive_paymongo_resolved_evidence_fingerprint', true),
                array('', (string) $record['resolved_evidence_fingerprint']),
                true
            )
            || !in_array(
                (string) $order->get_meta(self::RESOLVED_PAYMENT_PENDING_META, true),
                array('', (string) $record['resolved_evidence_fingerprint']),
                true
            )) {
            return false;
        }

        $stored_audit = $order->get_meta(self::OPERATOR_DISPOSITION_META, true);
        return $stored_audit === ''
            || (is_array($stored_audit)
                && $stored_audit === self::operator_disposition_audit($record));
    }

    /** @param array<string,mixed> $record */
    private static function operator_disposition_final_state_matches(
        \WC_Order $order,
        array $record
    ): bool {
        $date_paid = $order->get_date_paid('edit');
        if ($date_paid !== null
            && (!is_object($date_paid) || !is_callable(array($date_paid, 'getTimestamp')))) {
            return false;
        }
        $paid_at = is_object($date_paid) && is_callable(array($date_paid, 'getTimestamp'))
            ? (int) $date_paid->getTimestamp()
            : 0;
        return self::operator_disposition_order_matches_record($order, $record)
            && $order->get_status() === (string) $record['target_status']
            && $order->is_paid()
            && $order->get_payment_method() === GATEWAY_ID
            && $order->get_payment_method_title() === (string) $record['gateway_title']
            && (string) $order->get_transaction_id() === (string) $record['payment_id']
            && $paid_at === (int) $record['paid_at']
            && (string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true) === ''
            && (string) $order->get_meta(self::UNEXPECTED_MODE_META, true) === ''
            && (string) $order->get_meta('_bactive_paymongo_resolved_evidence_fingerprint', true) === ''
            && (string) $order->get_meta(self::RESOLVED_PAYMENT_PENDING_META, true) === ''
            && (string) $order->get_meta(Reconciler::REQUIRED_META, true) === ''
            && (string) $order->get_meta('_bactive_paymongo_reconcile_poll_count', true) === ''
            && $order->get_meta(self::OPERATOR_DISPOSITION_META, true)
                === self::operator_disposition_audit($record);
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    private static function operator_disposition_audit(array $record): array
    {
        return array(
            'type' => 'paid_verified_no_reemit',
            'event_id' => (string) $record['event_id'],
            'session_id' => (string) $record['session_id'],
            'payment_id' => (string) $record['payment_id'],
            'method' => (string) $record['method'],
            'provider' => (string) $record['provider'],
            'mode' => (string) $record['mode'],
            'live' => (bool) $record['live'],
            'paid_at' => (int) $record['paid_at'],
            'prior_status' => (string) $record['prior_status'],
            'prior_payment_method' => (string) $record['prior_payment_method'],
            'prior_payment_method_title' => (string) $record['prior_payment_method_title'],
            'resolved_evidence_fingerprint' => (string) $record['resolved_evidence_fingerprint'],
            'resolved_by' => (int) $record['resolved_by'],
            'recorded_at' => (int) $record['recorded_at'],
        );
    }

    private static function operator_payment_evidence_fingerprint(\WC_Order $order): string
    {
        $data = array(
            'order_id' => $order->get_id(),
            'amount' => Integrity::amount_to_minor((string) $order->get_total()),
            'currency' => $order->get_currency(),
            'attempts' => Gateway::order_attempts($order),
        );
        foreach (array(
            '_bactive_paymongo_paid_event_id',
            '_bactive_paymongo_paid_session_id',
            '_bactive_paymongo_source_method',
            '_bactive_paymongo_source_provider',
            self::PAID_MODE_META,
        ) as $key) {
            $data[$key] = $order->get_meta($key, true);
        }
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($data, JSON_UNESCAPED_SLASHES)
            : json_encode($data, JSON_UNESCAPED_SLASHES);
        return hash('sha256', is_string($encoded) ? $encoded : serialize($data));
    }

    /** @param array<string,mixed> $record */
    private static function reconstructed_resolved_evidence_fingerprint(
        \WC_Order $order,
        array $record
    ): string {
        // Gateway::provider_payment_evidence_fingerprint() intentionally also
        // covers fields consumed by finalization. Rebuild its exact pre-write
        // representation from the immutable intent so both torn layouts can
        // still prove which resolved state authorized the operation.
        $data = array(
            'order_id' => $order->get_id(),
            'payment_method' => (string) $record['prior_payment_method'],
            'status' => (string) $record['prior_status'],
            'is_paid' => false,
            'transaction_id' => '',
            'date_paid' => 0,
            'attempts' => Gateway::order_attempts($order),
            '_bactive_paymongo_settlement_pending' => '',
            '_bactive_paymongo_unexpected_payment_id' => (string) $record['payment_id'],
            '_bactive_paymongo_paid_event_id' => $order->get_meta('_bactive_paymongo_paid_event_id', true),
            '_bactive_paymongo_paid_session_id' => $order->get_meta('_bactive_paymongo_paid_session_id', true),
            '_bactive_paymongo_source_method' => $order->get_meta('_bactive_paymongo_source_method', true),
            '_bactive_paymongo_source_provider' => $order->get_meta('_bactive_paymongo_source_provider', true),
            self::PAID_MODE_META => $order->get_meta(self::PAID_MODE_META, true),
            self::UNEXPECTED_MODE_META => (string) $record['mode'],
            self::SETTLEMENT_MODE_META => '',
            self::PROCESSING_MODE_META => '',
            self::PROCESSING_CODE_META => '',
            self::PROCESSING_PAYMENT_META => '',
            self::PROCESSING_EVENT_META => '',
            self::PROCESSING_SESSION_META => '',
        );
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($data, JSON_UNESCAPED_SLASHES)
            : json_encode($data, JSON_UNESCAPED_SLASHES);
        return hash('sha256', is_string($encoded) ? $encoded : serialize($data));
    }

    private static function operator_disposition_option(string $payment_id, string $mode): string
    {
        return self::OPERATOR_DISPOSITION_OPTION_PREFIX . $mode . '_'
            . hash('sha256', $mode . '|' . $payment_id);
    }

    /**
     * @return array{amount:int,event_id:string,session_id:string,payment_id:string,method:string,provider:string,mode:string,live:bool,reference:string,correlation:string,paid_at:int,quarantine_code:string,fingerprint:string,payment_evidence_fingerprint:string}|null
     */
    private static function resolved_payment_context(\WC_Order $order): ?array
    {
        $amount = Integrity::amount_to_minor((string) $order->get_total());
        $fingerprint = (string) $order->get_meta('_bactive_paymongo_resolved_evidence_fingerprint', true);
        if ($order->get_id() < 1
            || !$order->has_status('on-hold')
            || $order->is_paid()
            || (string) $order->get_transaction_id() !== ''
            || $order->get_date_paid('edit') !== null
            || $order->get_currency() !== 'PHP'
            || $amount === null
            || Gateway::has_outstanding_attempts($order)
            || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)
            || !hash_equals($fingerprint, Gateway::provider_payment_evidence_fingerprint($order))
            || (string) $order->get_meta(self::RESOLVED_PAYMENT_PENDING_META, true) !== $fingerprint
            || $order->get_meta(self::OPERATOR_DISPOSITION_META, true) !== '') {
            return null;
        }

        foreach (array(
            '_bactive_paymongo_settlement_pending',
            self::SETTLEMENT_MODE_META,
            Reconciler::UNRESOLVED_META,
            '_bactive_paymongo_review_required',
            '_bactive_paymongo_review_incidents',
            self::PROCESSING_CODE_META,
            self::PROCESSING_PAYMENT_META,
            self::PROCESSING_EVENT_META,
            self::PROCESSING_SESSION_META,
            self::PROCESSING_MODE_META,
            self::REVIEW_MODE_META,
            self::REVIEW_EFFECT_IDENTITY_META,
            self::REVIEW_EFFECT_CODE_META,
            self::REVIEW_EFFECT_EVENT_META,
            self::REVIEW_EFFECT_SESSION_META,
            self::REVIEW_EFFECT_PAYMENT_META,
            self::REVIEW_EFFECT_MODE_META,
        ) as $key) {
            if ((string) $order->get_meta($key, true) !== '') {
                return null;
            }
        }
        if (!in_array(
            (string) $order->get_meta(Reconciler::REQUIRED_META, true),
            array('', 'yes'),
            true
        )) {
            return null;
        }

        foreach (array(
            '_bactive_paymongo_unexpected_payment_id',
            '_bactive_paymongo_paid_event_id',
            '_bactive_paymongo_paid_session_id',
            '_bactive_paymongo_source_method',
            '_bactive_paymongo_source_provider',
            self::PAID_MODE_META,
            self::UNEXPECTED_MODE_META,
        ) as $key) {
            if (!is_callable(array($order, 'meta_exists')) || !$order->meta_exists($key)) {
                return null;
            }
        }

        $payment_id = (string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true);
        $event_id = (string) $order->get_meta('_bactive_paymongo_paid_event_id', true);
        $session_id = (string) $order->get_meta('_bactive_paymongo_paid_session_id', true);
        $method = (string) $order->get_meta('_bactive_paymongo_source_method', true);
        $provider = (string) $order->get_meta('_bactive_paymongo_source_provider', true);
        $source_valid = in_array($method, array('qrph', 'paymaya', 'shopee_pay'), true)
            ? $provider === ''
            : ($method === 'dob' && in_array($provider, array('bpi', 'ubp'), true));
        if (!preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', $payment_id)
            || !preg_match('/^evt_[A-Za-z0-9_-]{3,128}$/D', $event_id)
            || !preg_match('/^cs_[A-Za-z0-9_-]{3,128}$/D', $session_id)
            || !$source_valid) {
            return null;
        }

        $matching_attempt = null;
        $matching_count = 0;
        foreach (Gateway::order_attempts($order) as $attempt) {
            $attempt_payment_id = (string) ($attempt['payment_id'] ?? '');
            $attempt_event_id = (string) ($attempt['paid_event_id'] ?? '');
            $attempt_paid_at = (int) ($attempt['paid_at'] ?? 0);
            if (($attempt_payment_id !== '' && !hash_equals($payment_id, $attempt_payment_id))
                || ($attempt_event_id !== '' && !hash_equals($event_id, $attempt_event_id))
                || ($attempt_paid_at > 0 && (string) ($attempt['session_id'] ?? '') !== $session_id)) {
                return null;
            }
            foreach ((array) ($attempt['reconciliation_payment_ids'] ?? array()) as $reconciled_id) {
                if (!is_string($reconciled_id)
                    || !preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', $reconciled_id)
                    || !hash_equals($payment_id, $reconciled_id)) {
                    return null;
                }
            }
            if ((string) ($attempt['session_id'] ?? '') !== $session_id
                || $attempt_payment_id !== $payment_id
                || $attempt_event_id !== $event_id
                || $attempt_paid_at < 1) {
                continue;
            }
            ++$matching_count;
            $matching_attempt = $attempt;
        }
        if ($matching_count !== 1 || !is_array($matching_attempt)) {
            return null;
        }

        $mode = (string) ($matching_attempt['mode'] ?? '');
        $reference = (string) ($matching_attempt['reference'] ?? '');
        $correlation = (string) ($matching_attempt['correlation_id'] ?? '');
        $paid_at = (int) ($matching_attempt['paid_at'] ?? 0);
        if (!in_array($mode, array('test', 'live'), true)
            || !preg_match('/^BA-' . preg_quote((string) $order->get_id(), '/') . '-[1-9][0-9]*$/D', $reference)
            || $correlation === ''
            || strlen($correlation) > 255
            || $paid_at < 1
            || $paid_at > (time() + 300)) {
            return null;
        }

        if ((string) $order->get_meta(self::PAID_MODE_META, true) !== $mode
            || (string) $order->get_meta(self::UNEXPECTED_MODE_META, true) !== $mode) {
            return null;
        }

        $quarantine = get_option(self::quarantine_option($event_id, $mode), null);
        $quarantine_code = is_array($quarantine) ? sanitize_key((string) ($quarantine['code'] ?? '')) : '';
        if ($quarantine_code === ''
            || !is_array($quarantine)
            || !self::quarantine_record_matches(
                $quarantine,
                array(
                    'code' => $quarantine_code,
                    'event_id' => $event_id,
                    'session_id' => $session_id,
                    'payment_id' => $payment_id,
                    'order_id' => $order->get_id(),
                    'mode' => $mode,
                    'recorded_at' => (int) ($quarantine['recorded_at'] ?? 0),
                ),
                true
            )) {
            return null;
        }

        $effect = self::effects_record('review', $event_id, $mode);
        if (!is_array($effect)
            || (string) ($effect['status'] ?? '') !== 'done'
            || (string) ($effect['kind'] ?? '') !== 'review'
            || (string) ($effect['identity'] ?? '') !== $event_id
            || (string) ($effect['mode'] ?? '') !== $mode
            || (int) ($effect['order_id'] ?? 0) !== $order->get_id()
            || (string) ($effect['to'] ?? '') !== 'on-hold'
            || (string) ($effect['code'] ?? '') !== $quarantine_code
            || (string) ($effect['event_id'] ?? '') !== $event_id
            || (string) ($effect['session_id'] ?? '') !== $session_id
            || (string) ($effect['payment_id'] ?? '') !== $payment_id) {
            return null;
        }

        return array(
            'amount' => $amount,
            'event_id' => $event_id,
            'session_id' => $session_id,
            'payment_id' => $payment_id,
            'method' => $method,
            'provider' => $provider,
            'mode' => $mode,
            'live' => $mode === 'live',
            'reference' => $reference,
            'correlation' => $correlation,
            'paid_at' => $paid_at,
            'quarantine_code' => $quarantine_code,
            'fingerprint' => $fingerprint,
            'payment_evidence_fingerprint' => self::operator_payment_evidence_fingerprint($order),
        );
    }

    /** @param array<string,mixed> $validated */
    private static function process_claimed_payment(\WC_Order $order, array $validated, bool $live): string
    {
        $mode = self::provider_mode($live);
        $validated['mode'] = $mode;
        $payment_id = (string) $validated['payment_id'];
        $mode = (string) ($validated['mode'] ?? '');
        if (!in_array($mode, array('test', 'live'), true)
            || $mode !== self::provider_mode($live)) {
            throw new \RuntimeException('PayMongo payment mode was not verified.');
        }
        $event_id = (string) $validated['event_id'];
        $session_id = (string) $validated['session_id'];
        $payment_claim = self::claim('payment', $payment_id, $mode);
        if ($payment_claim === 'done') {
            self::finish_claim('event', $event_id, 'processed', $mode);
            return 'duplicate';
        }
        if ($payment_claim !== 'claimed') {
            self::release_claim('event', $event_id, $mode);
            return 'retry';
        }
        $held_by_request = Order_Lock::held_by_request($order->get_id());
        if (!self::acquire_order_lock($order->get_id())) {
            self::release_claim('payment', $payment_id, $mode);
            self::release_claim('event', $event_id, $mode);
            return 'retry';
        }
        if (!Order_Lock::renew($order->get_id())) {
            self::release_claim('payment', $payment_id, $mode);
            self::release_claim('event', $event_id, $mode);
            self::safe_log('critical', 'payment_order_lock_lost', array('order_id' => $order->get_id()));
            return 'retry';
        }

        try {
            self::apply_payment($order, $validated, $live);
            self::finish_claim('payment', $payment_id, 'processed', $mode);
            self::finish_claim('event', $event_id, 'processed', $mode);
        } catch (\Throwable $error) {
            if ($error instanceof Quarantine_Retry_Exception) {
                $alarm = Reconciler::read_incident_option('bactive_paymongo_disable_drain_error', null);
                Reconciler::record_global_drain_error(is_array($alarm) ? $alarm : array(
                    'recorded_at' => time(), 'code' => 'quarantine_recovery_pending',
                    'order_id' => $order->get_id(), 'event_id' => $event_id,
                    'payment_id' => $payment_id, 'mode' => $mode));
                Reconciler::schedule_order($order->get_id());
            } elseif (Order_Lock::held_by_request($order->get_id())) {
                self::ensure_settlement_recovery($order, $validated);
            } else {
                self::safe_log('critical', 'settlement_recovery_skipped_lock_lost', array('order_id' => $order->get_id()));
            }
            self::release_claim('payment', $payment_id, $mode);
            self::release_claim('event', $event_id, $mode);
            self::safe_log(
                'error',
                'payment_processing_failed',
                array(
                    'order_id' => $order->get_id(),
                    'event_id' => $event_id,
                    'session_id' => $session_id,
                    'payment_id' => $payment_id,
                )
            );
            return 'retry';
        } finally {
            if (!$held_by_request) {
                self::release_order_lock($order->get_id());
            }
        }

        return 'processed';
    }

    /** @param array<string,mixed> $validated */
    private static function apply_payment(\WC_Order $order, array $validated, bool $live): void
    {
        $payment_id = (string) $validated['payment_id'];
        $mode = (string) ($validated['mode'] ?? '');
        if (!in_array($mode, array('test', 'live'), true)
            || $mode !== self::provider_mode($live)) {
            throw new \RuntimeException('PayMongo payment mode was not verified.');
        }

        if (!Order_Lock::held_by_request($order->get_id())
            || !Order_Lock::renew($order->get_id())) {
            throw new \RuntimeException('Payment order lock was not held.');
        }

        // The order may have changed while the event was being validated. Read
        // it again only after acquiring the order lock, before any transition.
        if (!self::refresh_order($order)) {
            throw new \RuntimeException('Unable to refresh the order before payment application.');
        }

        $current_amount = Integrity::amount_to_minor((string) $order->get_total());
        $attempt = self::attempt_for_session($order, (string) $validated['session_id'], $live);
        if ($attempt === null) {
            self::hold_unexpected_payment(
                $order,
                $validated,
                $payment_id,
                'payment_attempt_ambiguous',
                true
            );
            return;
        }
        // Check the complete stored identity before even the changed-total
        // quarantine path records facts. A new delivery cannot supply the
        // missing mode for an older, partially written payment identity.
        if (self::payment_identity_conflicts($order, $validated, $payment_id)) {
            self::hold_unexpected_payment(
                $order,
                $validated,
                $payment_id,
                'payment_identity_collision',
                true
            );
            return;
        }
        if ($current_amount === null
            || $current_amount !== (int) $validated['amount']
            || $order->get_currency() !== 'PHP'
        ) {
            self::record_payment_facts($order, $validated, $payment_id);
            self::hold_unexpected_payment(
                $order,
                $validated,
                $payment_id,
                'order_changed_before_payment'
            );
            return;
        }

        $existing_transaction = (string) $order->get_transaction_id();
        $closed = $order->has_status(array('cancelled', 'refunded', 'failed'));
        $disposition = Integrity::paid_event_disposition(
            $order->get_payment_method(),
            GATEWAY_ID,
            $order->is_paid(),
            $order->needs_payment(),
            $closed,
            $existing_transaction,
            $payment_id,
            !empty($attempt['expired_at'])
        );
        if ($disposition === 'duplicate') {
            if ((string) $order->get_meta('_bactive_paymongo_settlement_pending', true) === $payment_id
                && (string) $order->get_meta(self::SETTLEMENT_MODE_META, true) === $mode) {
                self::recover_or_close_deferred_settlement($order, $validated, $payment_id);
            }
            return;
        }

        self::record_payment_facts($order, $validated, $payment_id);

        if ($disposition === 'quarantine') {
            $code = self::unexpected_payment_code($order, $attempt);
            self::hold_unexpected_payment($order, $validated, $payment_id, $code);
            return;
        }

        $method = (string) $validated['method'];
        if ((string) $validated['provider'] !== '') {
            $method .= ':' . (string) $validated['provider'];
        }
        $note = sprintf(
            /* translators: 1: payment ID, 2: payment method */
            __('Payment confirmed by signed PayMongo webhook. Payment ID: %1$s; method: %2$s.', 'bactive-paymongo'),
            sanitize_text_field($payment_id),
            sanitize_text_field($method)
        );

        // Phase 1: persist and independently read back the provider facts and
        // recovery marker while the order is still unpaid. No payment/status
        // hook is allowed to run before this durable recovery point exists.
        if (!Order_Lock::renew($order->get_id())) {
            throw new \RuntimeException('Payment order lock was lost before settlement persistence.');
        }
        $order->update_meta_data('_bactive_paymongo_settlement_pending', sanitize_text_field($payment_id));
        $order->update_meta_data(self::SETTLEMENT_MODE_META, $mode);
        Reconciler::mark_required($order);
        self::save_with_status_effects_suppressed($order);
        $durable = self::fresh_order($order->get_id());
        if (!$durable instanceof \WC_Order
            || !self::settlement_state_matches($durable, $validated, $payment_id, false)) {
            self::record_processing_incident($order, $validated, 'settlement_marker_readback_failed');
            throw new \RuntimeException('PayMongo settlement marker was not independently verified.');
        }

        // Phase 2: arm an at-most-once effect record, persist the paid state
        // with WC_Order::$status_transition suppressed, and read it back. Woo
        // status, stock, email, and payment-complete hooks are replayed only
        // after the database proves the exact paid state is durable.
        $default_status = $durable->needs_processing() ? 'processing' : 'completed';
        $next_status = sanitize_key((string) apply_filters(
            'woocommerce_payment_complete_order_status',
            $default_status,
            $durable->get_id(),
            $durable
        ));
        $paid_statuses = function_exists('wc_get_is_paid_statuses')
            ? array_map('sanitize_key', (array) wc_get_is_paid_statuses())
            : array('processing', 'completed');
        if ($next_status === '' || !in_array($next_status, $paid_statuses, true)) {
            self::record_processing_incident($durable, $validated, 'payment_target_status_invalid');
            throw new \RuntimeException('WooCommerce returned an invalid paid status.');
        }

        $from_status = $durable->get_status();
        $durable->set_transaction_id($payment_id);
        if (!$durable->get_date_paid('edit')) {
            $durable->set_date_paid(time());
        }
        $durable->set_status($next_status, false);
        $transition = self::take_status_transition($durable);
        if (!self::transition_matches($transition, $from_status, $next_status)
            || self::arm_effects(
                'payment',
                $payment_id,
                $mode,
                $durable->get_id(),
                $transition,
                array(
                    'event_id' => (string) $validated['event_id'],
                    'session_id' => (string) $validated['session_id'],
                    'payment_id' => $payment_id,
                )
            ) !== 'armed') {
            self::record_processing_incident($durable, $validated, 'payment_effects_arm_failed');
            throw new \RuntimeException('WooCommerce payment effects could not be armed safely.');
        }

        try {
            self::save_with_status_effects_suppressed($durable, $transition);
        } catch (\Throwable $error) {
            self::record_processing_incident($durable, $validated, 'payment_state_persistence_ambiguous');
            throw $error;
        }
        $paid = self::fresh_order($durable->get_id());
        if (!$paid instanceof \WC_Order
            || $paid->get_status() !== $next_status
            || !self::settlement_state_matches($paid, $validated, $payment_id, true)) {
            self::record_processing_incident($durable, $validated, 'payment_state_readback_failed');
            throw new \RuntimeException('WooCommerce payment completion was not independently verified.');
        }

        try {
            self::emit_payment_effects($paid, $payment_id, $mode, $transition, $note);
        } catch (\Throwable $error) {
            self::record_processing_incident($paid, $validated, 'payment_effects_ambiguous');
            throw $error;
        }

        self::close_settlement_marker($paid, $validated, $payment_id);
    }

    /** @param array<string,mixed> $validated */
    private static function record_payment_facts(\WC_Order $order, array $validated, string $payment_id): void
    {
        $attempts = Gateway::order_attempts($order);
        $matching_indexes = array();
        foreach ($attempts as $index => $stored_attempt) {
            if ((string) ($stored_attempt['session_id'] ?? '') === (string) ($validated['session_id'] ?? '')
                && (string) ($stored_attempt['mode'] ?? '') === (string) ($validated['mode'] ?? '')) {
                $matching_indexes[] = $index;
            }
        }
        if (count($matching_indexes) !== 1) {
            throw new \RuntimeException('PayMongo payment attempt identity was ambiguous.');
        }
        $attempt_index = (int) $matching_indexes[0];
        $canonical_event_id = (string) $order->get_meta('_bactive_paymongo_paid_event_id', true);
        if ($canonical_event_id === '') {
            if ((string) ($attempts[$attempt_index]['paid_event_id'] ?? '') !== '') {
                $canonical_event_id = (string) $attempts[$attempt_index]['paid_event_id'];
            }
        }
        if ($canonical_event_id === '') {
            $canonical_event_id = (string) $validated['event_id'];
        }
        foreach (array(
            '_bactive_paymongo_source_method' => sanitize_key((string) $validated['method']),
            '_bactive_paymongo_source_provider' => sanitize_key((string) $validated['provider']),
            '_bactive_paymongo_paid_event_id' => sanitize_text_field($canonical_event_id),
            '_bactive_paymongo_paid_session_id' => sanitize_text_field((string) $validated['session_id']),
            self::PAID_MODE_META => sanitize_key((string) ($validated['mode'] ?? '')),
        ) as $key => $value) {
            // Preserve the first durably observed event for same-payment retry.
            // Identity conflicts are rejected before this method is reached.
            if (!$order->meta_exists($key)) {
                $order->update_meta_data($key, $value);
            }
        }

        if (empty($attempts[$attempt_index]['paid_event_id'])) {
            $attempts[$attempt_index]['paid_event_id'] = $canonical_event_id;
        }
        if (empty($attempts[$attempt_index]['payment_id'])) {
            $attempts[$attempt_index]['payment_id'] = $payment_id;
        }
        if (empty($attempts[$attempt_index]['paid_at'])) {
            $attempts[$attempt_index]['paid_at'] = time();
        }
        $order->update_meta_data('_bactive_paymongo_attempts', $attempts);
    }

    /** @param array<string,mixed> $validated */
    private static function payment_identity_conflicts(
        \WC_Order $order,
        array $validated,
        string $payment_id
    ): bool {
        $known_payment_ids = array(
            (string) $order->get_transaction_id(),
            (string) $order->get_meta('_bactive_paymongo_settlement_pending', true),
            (string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true),
        );
        $same_payment_proven = false;
        $validated_mode = (string) ($validated['mode'] ?? '');
        if (!in_array($validated_mode, array('test', 'live'), true)) {
            return true;
        }
        $paid_facts = array(
            (string) $order->get_meta('_bactive_paymongo_paid_event_id', true),
            (string) $order->get_meta('_bactive_paymongo_paid_session_id', true),
            (string) $order->get_meta('_bactive_paymongo_source_method', true),
        );
        $paid_mode = (string) $order->get_meta(self::PAID_MODE_META, true);
        $has_paid_identity = array_filter($paid_facts, static fn(string $value): bool => $value !== '') !== array()
            || (string) $order->get_meta('_bactive_paymongo_source_provider', true) !== ''
            || preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', (string) $order->get_transaction_id());
        if (($has_paid_identity || $paid_mode !== '')
            && ($paid_mode === '' || in_array('', $paid_facts, true))) {
            return true;
        }
        foreach (array(
            '_bactive_paymongo_settlement_pending' => self::SETTLEMENT_MODE_META,
            '_bactive_paymongo_unexpected_payment_id' => self::UNEXPECTED_MODE_META,
        ) as $id_meta => $mode_meta) {
            if (((string) $order->get_meta($id_meta, true) === '')
                !== ((string) $order->get_meta($mode_meta, true) === '')) {
                return true;
            }
        }
        foreach (array(
            self::PAID_MODE_META,
            self::SETTLEMENT_MODE_META,
            self::UNEXPECTED_MODE_META,
        ) as $mode_meta) {
            if ($order->meta_exists($mode_meta)) {
                $known_mode = (string) $order->get_meta($mode_meta, true);
                if (!in_array($known_mode, array('test', 'live'), true)
                    || !hash_equals($validated_mode, $known_mode)) {
                    return true;
                }
            }
        }
        $attempts = Gateway::order_attempts($order);
        foreach ($attempts as $attempt) {
            if (((string) ($attempt['payment_id'] ?? '') !== ''
                    || (string) ($attempt['paid_event_id'] ?? '') !== ''
                    || (array) ($attempt['reconciliation_payment_ids'] ?? array()) !== array())
                && (string) ($attempt['mode'] ?? '') !== $validated_mode) {
                return true;
            }
            $known_payment_ids[] = (string) ($attempt['payment_id'] ?? '');
            foreach ((array) ($attempt['reconciliation_payment_ids'] ?? array()) as $reconciled_id) {
                if (is_string($reconciled_id)) {
                    $known_payment_ids[] = $reconciled_id;
                }
            }
        }
        foreach ($known_payment_ids as $known_payment_id) {
            if ($known_payment_id === '') {
                continue;
            }
            if (!hash_equals($payment_id, $known_payment_id)) {
                return true;
            }
            $same_payment_proven = true;
        }

        $expected_facts = array(
            '_bactive_paymongo_paid_session_id' => sanitize_text_field((string) ($validated['session_id'] ?? '')),
            '_bactive_paymongo_source_method' => sanitize_key((string) ($validated['method'] ?? '')),
            '_bactive_paymongo_source_provider' => sanitize_key((string) ($validated['provider'] ?? '')),
            self::PAID_MODE_META => sanitize_key((string) ($validated['mode'] ?? '')),
        );
        foreach ($expected_facts as $key => $expected) {
            if ($order->meta_exists($key)
                && (string) $order->get_meta($key, true) !== $expected) {
                return true;
            }
        }
        if ($order->meta_exists('_bactive_paymongo_paid_event_id')
            && (string) $order->get_meta('_bactive_paymongo_paid_event_id', true) !== (string) ($validated['event_id'] ?? '')
            && !$same_payment_proven) {
            return true;
        }

        foreach ($attempts as $attempt) {
            if ((int) ($attempt['paid_at'] ?? 0) < 1) {
                continue;
            }
            if ((string) ($attempt['mode'] ?? '') !== $validated_mode) {
                return true;
            }
            if ((string) ($attempt['session_id'] ?? '') !== (string) ($validated['session_id'] ?? '')) {
                return true;
            }
            $attempt_event = (string) ($attempt['paid_event_id'] ?? '');
            if ($attempt_event !== ''
                && $attempt_event !== (string) ($validated['event_id'] ?? '')
                && !$same_payment_proven) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $validated */
    private static function settlement_state_matches(
        \WC_Order $order,
        array $validated,
        string $payment_id,
        bool $must_be_paid
    ): bool {
        $amount = Integrity::amount_to_minor((string) $order->get_total());
        $stored_event_id = (string) $order->get_meta('_bactive_paymongo_paid_event_id', true);
        if ($amount === null
            || $amount !== (int) ($validated['amount'] ?? -1)
            || $order->get_currency() !== 'PHP'
            || $order->get_payment_method() !== GATEWAY_ID
            || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== $payment_id
            || (string) $order->get_meta(self::SETTLEMENT_MODE_META, true) !== (string) ($validated['mode'] ?? '')
            || (string) $order->get_meta(Reconciler::REQUIRED_META, true) !== 'yes'
            || (string) $order->get_meta('_bactive_paymongo_source_method', true) !== sanitize_key((string) ($validated['method'] ?? ''))
            || (string) $order->get_meta('_bactive_paymongo_source_provider', true) !== sanitize_key((string) ($validated['provider'] ?? ''))
            || $stored_event_id === ''
            || (string) $order->get_meta('_bactive_paymongo_paid_session_id', true) !== sanitize_text_field((string) ($validated['session_id'] ?? ''))
            || (string) $order->get_meta(self::PAID_MODE_META, true) !== (string) ($validated['mode'] ?? '')) {
            return false;
        }

        $attempt_matches = false;
        foreach (Gateway::order_attempts($order) as $attempt) {
            if ((string) ($attempt['session_id'] ?? '') === (string) ($validated['session_id'] ?? '')
                && (string) ($attempt['mode'] ?? '') === (string) ($validated['mode'] ?? '')
                && (string) ($attempt['paid_event_id'] ?? '') === $stored_event_id
                && (string) ($attempt['payment_id'] ?? '') === $payment_id
                && (string) ($attempt['mode'] ?? '') === (string) ($validated['mode'] ?? '')
                && (int) ($attempt['paid_at'] ?? 0) > 0) {
                $attempt_matches = true;
                break;
            }
        }
        if (!$attempt_matches) {
            return false;
        }

        if (!$must_be_paid) {
            return !$order->is_paid() && $order->needs_payment();
        }
        return $order->is_paid()
            && (string) $order->get_transaction_id() === $payment_id
            && $order->get_date_paid('edit') !== null;
    }

    private static function fresh_order(int $order_id): ?\WC_Order
    {
        $fresh = wc_get_order($order_id);
        if (!$fresh instanceof \WC_Order
            || $fresh->get_id() !== $order_id
            || !self::refresh_order($fresh)) {
            return null;
        }
        return $fresh;
    }

    /**
     * @param array<string,mixed>|null $expected_transition
     * @param callable(\WC_Order):bool|null $state_assertion
     */
    private static function save_with_status_effects_suppressed(
        \WC_Order $order,
        ?array $expected_transition = null,
        ?callable $state_assertion = null
    ): void {
        $late_transition = null;
        $suppress = static function ($saving_order) use (
            $order,
            &$late_transition,
            $state_assertion
        ): void {
            if ($saving_order !== $order) {
                return;
            }
            $candidate = self::take_status_transition($order);
            if (is_array($candidate)) {
                $late_transition = $candidate;
            }
            if ($state_assertion !== null && !$state_assertion($order)) {
                throw new \RuntimeException('WooCommerce changed an armed order state during persistence.');
            }
        };

        add_action('woocommerce_before_order_object_save', $suppress, PHP_INT_MAX, 1);
        // WC_Order::save() invokes parent::save(), including its after-save
        // hook, before it calls status_transition(). A last-priority after-save
        // fence is therefore required to catch a transition introduced by an
        // extension after the database write but before Woo emits its effects.
        add_action('woocommerce_after_order_object_save', $suppress, PHP_INT_MAX, 1);
        try {
            $order->save();
        } finally {
            remove_action('woocommerce_before_order_object_save', $suppress, PHP_INT_MAX);
            remove_action('woocommerce_after_order_object_save', $suppress, PHP_INT_MAX);
            $candidate = self::take_status_transition($order);
            if (is_array($candidate)) {
                $late_transition = $candidate;
            }
        }

        if ($late_transition !== null) {
            if ($expected_transition === null) {
                throw new \RuntimeException('WooCommerce introduced an unarmed order transition during persistence.');
            }
            if (!self::same_transition($expected_transition, $late_transition)) {
                throw new \RuntimeException('WooCommerce changed the armed order transition during persistence.');
            }
        }
    }

    /** @return array<string,mixed>|null */
    private static function take_status_transition(\WC_Order $order): ?array
    {
        $class = new \ReflectionObject($order);
        while ($class) {
            if ($class->hasProperty('status_transition')) {
                $property = $class->getProperty('status_transition');
                $property->setAccessible(true);
                $value = $property->getValue($order);
                $property->setValue($order, false);
                if ($value === false || $value === null) {
                    return null;
                }
                if (!is_array($value)) {
                    throw new \RuntimeException('WooCommerce exposed an invalid order transition.');
                }
                return $value;
            }
            $class = $class->getParentClass();
        }
        throw new \RuntimeException('WooCommerce order transition controls are unavailable.');
    }

    /** @param array<string,mixed>|null $transition */
    private static function transition_matches(?array $transition, string $from, string $to): bool
    {
        return is_array($transition)
            && (string) ($transition['from'] ?? '') === $from
            && (string) ($transition['to'] ?? '') === $to;
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private static function same_transition(array $left, array $right): bool
    {
        return (string) ($left['from'] ?? '') === (string) ($right['from'] ?? '')
            && (string) ($left['to'] ?? '') === (string) ($right['to'] ?? '')
            && ($left['note'] ?? '') === ($right['note'] ?? '')
            && (bool) ($left['manual'] ?? false) === (bool) ($right['manual'] ?? false);
    }

    /** @param array<string,mixed> $transition @param array<string,mixed> $context */
    private static function arm_effects(
        string $kind,
        string $identity,
        string $mode,
        int $order_id,
        array $transition,
        array $context = array()
    ): string {
        if (!self::valid_mode($mode)) {
            return 'invalid';
        }
        $option = self::effects_option($kind, $identity, $mode);
        $record = array(
            'status' => 'armed',
            'kind' => sanitize_key($kind),
            'identity' => sanitize_text_field($identity),
            'mode' => $mode,
            'order_id' => $order_id,
            'from' => sanitize_key((string) ($transition['from'] ?? '')),
            'to' => sanitize_key((string) ($transition['to'] ?? '')),
            'note' => is_string($transition['note'] ?? null) ? sanitize_text_field($transition['note']) : false,
            'manual' => (bool) ($transition['manual'] ?? false),
            'recorded_at' => time(),
        );
        foreach (array('code', 'event_id', 'session_id', 'payment_id') as $key) {
            if (array_key_exists($key, $context)) {
                $record[$key] = $key === 'code'
                    ? sanitize_key((string) $context[$key])
                    : sanitize_text_field((string) $context[$key]);
            }
        }
        if (add_option($option, $record, '', false)) {
            return 'armed';
        }

        $existing = get_option($option, array());
        if (!is_array($existing)
            || (string) ($existing['kind'] ?? '') !== $record['kind']
            || (string) ($existing['identity'] ?? '') !== $record['identity']
            || (string) ($existing['mode'] ?? '') !== $mode
            || (int) ($existing['order_id'] ?? 0) !== $order_id
            || (string) ($existing['from'] ?? '') !== $record['from']
            || (string) ($existing['to'] ?? '') !== $record['to']) {
            return 'invalid';
        }
        foreach (array('code', 'event_id', 'session_id', 'payment_id') as $key) {
            if ((string) ($existing[$key] ?? '') !== (string) ($record[$key] ?? '')) {
                return 'invalid';
            }
        }
        return in_array($existing['status'] ?? '', array('armed', 'processing', 'done'), true)
            ? (string) $existing['status']
            : 'invalid';
    }

    /** @return array<string,mixed>|null */
    private static function effects_record(string $kind, string $identity, string $mode): ?array
    {
        if (!self::valid_mode($mode)) {
            return null;
        }
        $record = get_option(self::effects_option($kind, $identity, $mode), null);
        return is_array($record) && (string) ($record['mode'] ?? '') === $mode
            ? $record
            : null;
    }

    /** @param array<string,mixed> $expected */
    private static function begin_effects(
        string $kind,
        string $identity,
        string $mode,
        array $expected
    ): bool
    {
        $record = self::effects_record($kind, $identity, $mode);
        if (!is_array($record)
            || ($record['status'] ?? '') !== 'armed'
            || (string) ($record['kind'] ?? '') !== (string) ($expected['kind'] ?? '')
            || (string) ($record['identity'] ?? '') !== (string) ($expected['identity'] ?? '')
            || (string) ($record['mode'] ?? '') !== $mode
            || (string) ($expected['mode'] ?? '') !== $mode
            || (int) ($record['order_id'] ?? 0) !== (int) ($expected['order_id'] ?? 0)
            || (string) ($record['from'] ?? '') !== (string) ($expected['from'] ?? '')
            || (string) ($record['to'] ?? '') !== (string) ($expected['to'] ?? '')) {
            return false;
        }
        foreach (array('code', 'event_id', 'session_id', 'payment_id') as $key) {
            if ((string) ($record[$key] ?? '') !== (string) ($expected[$key] ?? '')) {
                return false;
            }
        }
        $record['status'] = 'processing';
        $record['started_at'] = time();
        update_option(self::effects_option($kind, $identity, $mode), $record, false);
        $readback = self::effects_record($kind, $identity, $mode);
        return is_array($readback) && ($readback['status'] ?? '') === 'processing';
    }

    private static function finish_effects(string $kind, string $identity, string $mode): bool
    {
        $record = self::effects_record($kind, $identity, $mode);
        if (!is_array($record) || ($record['status'] ?? '') !== 'processing') {
            return false;
        }
        $record['status'] = 'done';
        $record['finished_at'] = time();
        update_option(self::effects_option($kind, $identity, $mode), $record, false);
        $readback = self::effects_record($kind, $identity, $mode);
        return is_array($readback) && ($readback['status'] ?? '') === 'done';
    }

    private static function effects_option(string $kind, string $identity, string $mode): string
    {
        return 'bactive_paymongo_effects_' . $mode . '_' . sanitize_key($kind) . '_'
            . hash('sha256', $mode . '|' . $identity);
    }

    /** @param array<string,mixed> $transition */
    private static function replay_status_transition(\WC_Order $order, array $transition): void
    {
        $from = sanitize_key((string) ($transition['from'] ?? ''));
        $to = sanitize_key((string) ($transition['to'] ?? ''));
        if (!self::transition_matches($transition, $from, $to) || $to === '') {
            throw new \RuntimeException('WooCommerce status transition is invalid.');
        }

        // WC_Order::status_transition() catches Exception from extensions and
        // exposes no success signal. Invoking it would let a hook run partly,
        // throw, and still let our at-most-once record be marked done. Emit the
        // same WooCommerce 10.8 action sequence directly so every failure can
        // leave the durable effect record in the operator-only processing
        // state. The transition itself was already persisted and read back.
        do_action('woocommerce_order_status_' . $to, $order->get_id(), $order, $transition);

        $transition_note = $transition['note'] ?? false;
        if ($transition_note !== false) {
            if ($from !== '' && !in_array($from, array('draft', 'auto-draft', 'new', 'checkout-draft'), true)) {
                $generated_note = sprintf(
                    /* translators: 1: old order status; 2: new order status */
                    __('Order status changed from %1$s to %2$s.', 'woocommerce'),
                    function_exists('wc_get_order_status_name') ? wc_get_order_status_name($from) : $from,
                    function_exists('wc_get_order_status_name') ? wc_get_order_status_name($to) : $to
                );
                $order->add_order_note(
                    trim((string) $transition_note . ' ' . $generated_note),
                    false,
                    (bool) ($transition['manual'] ?? false)
                );
            } elseif ($from === '') {
                $generated_note = sprintf(
                    /* translators: %s: new order status */
                    __('Order status set to %s.', 'woocommerce'),
                    function_exists('wc_get_order_status_name') ? wc_get_order_status_name($to) : $to
                );
                $order->add_order_note(
                    trim((string) $transition_note . ' ' . $generated_note),
                    false,
                    (bool) ($transition['manual'] ?? false)
                );
            }
        }

        if ($from === '') {
            return;
        }

        do_action('woocommerce_order_status_' . $from . '_to_' . $to, $order->get_id(), $order);
        do_action('woocommerce_order_status_changed', $order->get_id(), $from, $to, $order);

        $valid_for_payment = (array) apply_filters(
            'woocommerce_valid_order_statuses_for_payment',
            array('pending', 'failed'),
            $order
        );
        $paid_statuses = function_exists('wc_get_is_paid_statuses')
            ? (array) wc_get_is_paid_statuses()
            : array('processing', 'completed');
        if (in_array($from, $valid_for_payment, true) && in_array($to, $paid_statuses, true)) {
            do_action('woocommerce_order_payment_status_changed', $order->get_id(), $order);
        }
    }

    /** @param array<string,mixed> $transition */
    private static function emit_payment_effects(
        \WC_Order $order,
        string $payment_id,
        string $mode,
        array $transition,
        string $note
    ): void {
        $record = self::effects_record('payment', $payment_id, $mode);
        if (!is_array($record)
            || !self::transition_matches($transition, (string) ($record['from'] ?? ''), (string) ($record['to'] ?? ''))
            || !self::begin_effects('payment', $payment_id, $mode, $record)) {
            throw new \RuntimeException('WooCommerce payment effects are not safely armed.');
        }

        do_action('woocommerce_pre_payment_complete', $order->get_id(), $payment_id);
        if (function_exists('WC') && is_object(WC()) && WC()->session) {
            WC()->session->set('order_awaiting_payment', false);
        }
        $order->add_order_note($note);
        self::replay_status_transition($order, $transition);
        do_action('woocommerce_payment_complete', $order->get_id(), $payment_id);
        if (!self::finish_effects('payment', $payment_id, $mode)) {
            throw new \RuntimeException('WooCommerce payment effects completion was not persisted.');
        }
    }

    /** @param array<string,mixed> $validated */
    private static function recover_or_close_deferred_settlement(
        \WC_Order $order,
        array $validated,
        string $payment_id
    ): void {
        if (!self::settlement_state_matches($order, $validated, $payment_id, true)) {
            self::record_processing_incident($order, $validated, 'duplicate_settlement_state_invalid');
            throw new \RuntimeException('Deferred PayMongo settlement state is invalid.');
        }
        $mode = (string) ($validated['mode'] ?? '');
        $record = self::effects_record('payment', $payment_id, $mode);
        if (!is_array($record)) {
            self::record_processing_incident($order, $validated, 'payment_effects_record_missing');
            throw new \RuntimeException('Deferred WooCommerce payment effects are untracked.');
        }
        if ($order->get_status() !== (string) ($record['to'] ?? '')) {
            self::record_processing_incident($order, $validated, 'payment_effects_status_mismatch');
            throw new \RuntimeException('Deferred WooCommerce payment status does not match its armed effects.');
        }
        if (($record['status'] ?? '') === 'processing') {
            self::record_processing_incident($order, $validated, 'payment_effects_ambiguous');
            throw new \RuntimeException('Deferred WooCommerce payment effects are ambiguous.');
        }
        if (($record['status'] ?? '') === 'armed') {
            $transition = array(
                'from' => (string) ($record['from'] ?? ''),
                'to' => (string) ($record['to'] ?? ''),
                'note' => $record['note'] ?? false,
                'manual' => (bool) ($record['manual'] ?? false),
            );
            self::emit_payment_effects(
                $order,
                $payment_id,
                $mode,
                $transition,
                __('Payment confirmed by PayMongo after a deferred settlement recovery.', 'bactive-paymongo')
            );
        } elseif (($record['status'] ?? '') !== 'done') {
            self::record_processing_incident($order, $validated, 'payment_effects_record_invalid');
            throw new \RuntimeException('Deferred WooCommerce payment effects record is invalid.');
        }
        self::close_settlement_marker($order, $validated, $payment_id);
    }

    /** @param array<string,mixed> $validated */
    private static function close_settlement_marker(
        \WC_Order $order,
        array $validated,
        string $payment_id
    ): void {
        if (!Order_Lock::renew($order->get_id())) {
            self::record_processing_incident($order, $validated, 'settlement_closeout_lock_lost');
            throw new \RuntimeException('Payment order lock was lost before settlement closeout.');
        }
        $order->delete_meta_data('_bactive_paymongo_settlement_pending');
        $order->delete_meta_data(self::SETTLEMENT_MODE_META);
        self::save_with_status_effects_suppressed($order);
        $closed = self::fresh_order($order->get_id());
        if (!$closed instanceof \WC_Order
            || (string) $closed->get_meta('_bactive_paymongo_settlement_pending', true) !== ''
            || (string) $closed->get_meta(self::SETTLEMENT_MODE_META, true) !== ''
            || !$closed->is_paid()
            || (string) $closed->get_transaction_id() !== $payment_id
            || !self::payment_facts_match($closed, $validated, $payment_id)) {
            self::record_processing_incident($order, $validated, 'settlement_closeout_readback_failed');
            throw new \RuntimeException('PayMongo settlement closeout was not independently verified.');
        }
    }

    /** @param array<string,mixed> $validated */
    private static function payment_facts_match(\WC_Order $order, array $validated, string $payment_id): bool
    {
        $stored_event_id = (string) $order->get_meta('_bactive_paymongo_paid_event_id', true);
        if ((string) $order->get_meta('_bactive_paymongo_source_method', true) !== sanitize_key((string) ($validated['method'] ?? ''))
            || (string) $order->get_meta('_bactive_paymongo_source_provider', true) !== sanitize_key((string) ($validated['provider'] ?? ''))
            || $stored_event_id === ''
            || (string) $order->get_meta('_bactive_paymongo_paid_session_id', true) !== sanitize_text_field((string) ($validated['session_id'] ?? ''))
            || (string) $order->get_meta(self::PAID_MODE_META, true) !== (string) ($validated['mode'] ?? '')) {
            return false;
        }
        $matches = 0;
        foreach (Gateway::order_attempts($order) as $attempt) {
            if ((string) ($attempt['session_id'] ?? '') === (string) ($validated['session_id'] ?? '')
                && (string) ($attempt['paid_event_id'] ?? '') === $stored_event_id
                && (string) ($attempt['payment_id'] ?? '') === $payment_id
                && (string) ($attempt['mode'] ?? '') === (string) ($validated['mode'] ?? '')
                && (int) ($attempt['paid_at'] ?? 0) > 0) {
                ++$matches;
            }
        }
        return $matches === 1;
    }

    /** @param array<string,mixed> $validated */
    private static function record_processing_incident(
        \WC_Order $order,
        array $validated,
        string $code
    ): void {
        $payment_id = sanitize_text_field((string) ($validated['payment_id'] ?? ''));
        $mode = sanitize_key((string) ($validated['mode'] ?? 'invalid'));
        $record = array(
            'code' => sanitize_key($code),
            'order_id' => $order->get_id(),
            'event_id' => sanitize_text_field((string) ($validated['event_id'] ?? '')),
            'session_id' => sanitize_text_field((string) ($validated['session_id'] ?? '')),
            'payment_id' => $payment_id,
            'mode' => $mode,
            'recorded_at' => time(),
        );
        $queued = self::queue_review_incident($order, 'processing', $record);
        if ($queued === null) {
            Reconciler::record_global_drain_error($record);
            Reconciler::schedule_order($order->get_id());
            self::safe_log('critical', 'processing_incident_queue_failed', $record);
            return;
        }
        $record = $queued;
        $identity = self::processing_identity_from_record($record);
        $incident_option = self::processing_incident_option($identity, $mode);
        if (!add_option($incident_option, $record, '', false)) {
            $existing = get_option($incident_option, null);
            $same_identity = is_array($existing);
            foreach (array('code', 'order_id', 'event_id', 'session_id', 'payment_id', 'mode') as $key) {
                if (($existing[$key] ?? null) !== ($record[$key] ?? null)) {
                    $same_identity = false;
                    break;
                }
            }
            if (!$same_identity || (int) ($existing['recorded_at'] ?? 0) < 1) {
                Reconciler::set_draining(true);
                self::safe_log('critical', 'processing_incident_identity_collision', $record);
                return;
            }
            // The first immutable timestamp is part of the incident audit and
            // must survive retries in later requests.
            $record = $existing;
        }
        if (get_option($incident_option, null) !== $record) {
            Reconciler::set_draining(true);
            self::safe_log('critical', 'processing_incident_readback_failed', $record);
            return;
        }
        Reconciler::record_global_drain_error($record);
        Reconciler::schedule_order($order->get_id());
        try {
            if (Order_Lock::held_by_request($order->get_id())
                && Order_Lock::renew($order->get_id())) {
                $fresh = self::fresh_order($order->get_id());
                if ($fresh instanceof \WC_Order) {
                    $sanitized_code = sanitize_key($code);
                    $already_linked = (string) $fresh->get_meta(self::PROCESSING_CODE_META, true) === $sanitized_code
                        && (string) $fresh->get_meta(self::PROCESSING_PAYMENT_META, true) === $record['payment_id']
                        && (string) $fresh->get_meta(self::PROCESSING_EVENT_META, true) === $record['event_id']
                        && (string) $fresh->get_meta(self::PROCESSING_SESSION_META, true) === $record['session_id']
                        && (string) $fresh->get_meta(self::PROCESSING_MODE_META, true) === $mode
                        && (string) $fresh->get_meta(self::REVIEW_MODE_META, true) === $mode
                        && (string) $fresh->get_meta('_bactive_paymongo_review_required', true) === $sanitized_code
                        && (string) $fresh->get_meta(Reconciler::UNRESOLVED_META, true) === $sanitized_code
                        && (int) $fresh->get_meta('_bactive_paymongo_review_incidents', true) > 0;
                    $active_review_values = array(
                        (string) $fresh->get_meta('_bactive_paymongo_review_required', true),
                        (string) $fresh->get_meta(Reconciler::UNRESOLVED_META, true),
                        (string) $fresh->get_meta(self::REVIEW_MODE_META, true),
                        (string) $fresh->get_meta(self::PROCESSING_CODE_META, true),
                        (string) $fresh->get_meta(self::PROCESSING_PAYMENT_META, true),
                        (string) $fresh->get_meta(self::PROCESSING_EVENT_META, true),
                        (string) $fresh->get_meta(self::PROCESSING_SESSION_META, true),
                        (string) $fresh->get_meta(self::PROCESSING_MODE_META, true),
                        (string) $fresh->get_meta(self::REVIEW_EFFECT_IDENTITY_META, true),
                        (string) $fresh->get_meta('_bactive_paymongo_review_incidents', true),
                    );
                    if (!$already_linked
                        && array_filter(
                            $active_review_values,
                            static fn(string $value): bool => $value !== ''
                        ) !== array()) {
                        // Keep the first attached tuple resolvable. The new
                        // exact incident remains durable in its mode-scoped
                        // option and can be attached after the first closes.
                        return;
                    }
                    $review_option = self::processing_review_option($identity, $code, $mode);
                    $new_incident = add_option($review_option, $record, '', false);
                    $stored_review = get_option($review_option, null);
                    $incident_exists = is_array($stored_review) && $stored_review === $record;
                    if (!$incident_exists) {
                        return;
                    }
                    $fresh->update_meta_data('_bactive_paymongo_review_required', $sanitized_code);
                    $fresh->update_meta_data(Reconciler::UNRESOLVED_META, $sanitized_code);
                    $fresh->update_meta_data(self::PROCESSING_CODE_META, sanitize_key($code));
                    $fresh->update_meta_data(self::PROCESSING_PAYMENT_META, $record['payment_id']);
                    $fresh->update_meta_data(self::PROCESSING_EVENT_META, $record['event_id']);
                    $fresh->update_meta_data(self::PROCESSING_SESSION_META, $record['session_id']);
                    $fresh->update_meta_data(self::PROCESSING_MODE_META, $mode);
                    $fresh->update_meta_data(self::REVIEW_MODE_META, $mode);
                    Reconciler::mark_required($fresh);
                    if ($incident_exists && ($new_incident || !$already_linked)) {
                        Reconciler::record_review_incident($fresh, $new_incident, !$already_linked);
                    }
                    self::save_with_status_effects_suppressed($fresh);
                    self::acknowledge_attached_pending_reviews($fresh);
                    $review = self::fresh_order($fresh->get_id());
                    if (!$review instanceof \WC_Order
                        || (string) $review->get_meta(Reconciler::UNRESOLVED_META, true) !== sanitize_key($code)
                        || (string) $review->get_meta('_bactive_paymongo_review_required', true) !== sanitize_key($code)
                        || (string) $review->get_meta(self::PROCESSING_CODE_META, true) !== sanitize_key($code)
                        || (string) $review->get_meta(self::PROCESSING_PAYMENT_META, true) !== $record['payment_id']
                        || (string) $review->get_meta(self::PROCESSING_EVENT_META, true) !== $record['event_id']
                        || (string) $review->get_meta(self::PROCESSING_SESSION_META, true) !== $record['session_id']
                        || (string) $review->get_meta(self::PROCESSING_MODE_META, true) !== $mode
                        || (string) $review->get_meta(self::REVIEW_MODE_META, true) !== $mode) {
                        update_option(
                            'bactive_paymongo_reconcile_diagnostic_' . $order->get_id(),
                            array('recorded_at' => time(), 'code' => 'processing_review_marker_readback_failed'),
                            false
                        );
                    }
                }
            }
        } catch (\Throwable $marker_error) {
            update_option(
                'bactive_paymongo_reconcile_diagnostic_' . $order->get_id(),
                array('recorded_at' => time(), 'code' => 'processing_review_marker_persist_failed'),
                false
            );
        }
        self::safe_log('critical', $code, $record);
    }

    /** @return array{option:string,record:array<string,mixed>,needs_annotation:bool,record_incident:bool,durable:bool} */
    private static function prepare_quarantine_record(
        string $code,
        string $event_id,
        string $session_id,
        int $order_id,
        string $payment_id,
        string $mode = 'local'
    ): array {
        if (!self::valid_mode($mode)) {
            $mode = 'invalid';
        }
        $identity = $event_id !== '' ? $event_id : hash('sha256', $code . '|' . $session_id . '|' . $order_id);
        $option = self::quarantine_option($identity, $mode);
        $record = array(
            'code' => sanitize_key($code),
            'event_id' => sanitize_text_field($event_id),
            'session_id' => sanitize_text_field($session_id),
            'payment_id' => sanitize_text_field($payment_id),
            'order_id' => $order_id,
            'mode' => $mode,
            'recorded_at' => time(),
        );
        if (!self::valid_mode($mode)) {
            return array(
                'option' => $option,
                'record' => $record,
                'needs_annotation' => true,
                'record_incident' => false,
                'durable' => false,
            );
        }
        if (add_option($option, $record, '', false)) {
            $new_incident = true;
        } else {
            $new_incident = false;
            $existing = get_option($option, null);
            if (!is_array($existing) || !self::quarantine_record_matches($existing, $record, false)) {
                return array(
                    'option' => $option,
                    'record' => $record,
                    'needs_annotation' => true,
                    'record_incident' => false,
                    'durable' => false,
                );
            }
            $record = $existing;
        }
        $readback = get_option($option, null);
        $durable = is_array($readback) && self::quarantine_record_matches($readback, $record, false);
        return array(
            'option' => $option,
            'record' => $record,
            'needs_annotation' => $new_incident || empty($record['order_annotated']),
            'record_incident' => $new_incident,
            'durable' => $durable,
        );
    }

    /** @param array{option:string,record:array<string,mixed>,needs_annotation:bool,record_incident?:bool,durable:bool} $quarantine */
    private static function finish_quarantine_record(array $quarantine): bool
    {
        if (empty($quarantine['durable'])) {
            return false;
        }
        $record = $quarantine['record'];
        $record['order_annotated'] = true;
        update_option($quarantine['option'], $record, false);
        $readback = get_option($quarantine['option'], null);
        $verified = is_array($readback) && self::quarantine_record_matches($readback, $record, true);
        if ($verified) {
            self::clear_matching_global_incident($record, array(
                'quarantine_record_persist_failed', 'quarantine_persist_failed',
            ));
        }
        return $verified;
    }

    /** @param array<string,mixed> $actual @param array<string,mixed> $expected */
    private static function quarantine_record_matches(array $actual, array $expected, bool $must_be_annotated): bool
    {
        foreach (array('code', 'event_id', 'session_id', 'payment_id', 'mode') as $key) {
            if ((string) ($actual[$key] ?? '') !== (string) ($expected[$key] ?? '')) {
                return false;
            }
        }
        return (int) ($actual['order_id'] ?? -1) === (int) ($expected['order_id'] ?? -2)
            && (int) ($actual['recorded_at'] ?? 0) > 0
            && (!$must_be_annotated || !empty($actual['order_annotated']));
    }

    private static function quarantine_option(string $identity, string $mode): string
    {
        return 'bactive_paymongo_quarantine_' . $mode . '_'
            . hash('sha256', $mode . '|' . $identity);
    }

    private static function processing_incident_option(string $identity, string $mode): string
    {
        return 'bactive_paymongo_processing_incident_' . $mode . '_'
            . hash('sha256', $mode . '|' . $identity);
    }

    private static function processing_identity(
        int $order_id,
        string $code,
        string $event_id,
        string $session_id,
        string $payment_id
    ): string {
        return implode('|', array(
            (string) $order_id,
            sanitize_key($code),
            sanitize_text_field($event_id),
            sanitize_text_field($session_id),
            sanitize_text_field($payment_id),
        ));
    }

    /** @param array<string,mixed> $record */
    private static function processing_identity_from_record(array $record): string
    {
        return self::processing_identity(
            (int) ($record['order_id'] ?? 0),
            (string) ($record['code'] ?? ''),
            (string) ($record['event_id'] ?? ''),
            (string) ($record['session_id'] ?? ''),
            (string) ($record['payment_id'] ?? '')
        );
    }

    private static function processing_review_option(
        string $identity,
        string $code,
        string $mode
    ): string {
        return 'bactive_paymongo_processing_review_' . $mode . '_'
            . hash('sha256', $mode . '|' . $identity . '|' . sanitize_key($code));
    }

    private static function quarantine_retry_option(string $identity, string $mode): string
    {
        return 'bactive_paymongo_quarantine_retry_' . $mode . '_'
            . hash('sha256', $mode . '|' . $identity);
    }

    /** @param array<string,mixed> $record */
    private static function record_quarantine_persistence_failure(array $record): void
    {
        Reconciler::record_global_drain_error(
            array(
                'recorded_at' => time(),
                'code' => 'quarantine_record_persist_failed',
                'order_id' => (int) ($record['order_id'] ?? 0),
                'event_id' => sanitize_text_field((string) ($record['event_id'] ?? '')),
                'session_id' => sanitize_text_field((string) ($record['session_id'] ?? '')),
                'payment_id' => sanitize_text_field((string) ($record['payment_id'] ?? '')),
                'mode' => sanitize_key((string) ($record['mode'] ?? 'invalid')),
            )
        );
        self::safe_log('critical', 'quarantine_record_persist_failed', $record);
    }

    /** @param array<string,mixed> $validated */
    private static function record_quarantine_retry_failure(
        \WC_Order $order,
        array $validated,
        string $code
    ): void {
        $record = array(
            'recorded_at' => time(),
            'code' => sanitize_key($code),
            'order_id' => $order->get_id(),
            'event_id' => sanitize_text_field((string) ($validated['event_id'] ?? '')),
            'session_id' => sanitize_text_field((string) ($validated['session_id'] ?? '')),
            'payment_id' => sanitize_text_field((string) ($validated['payment_id'] ?? '')),
            'mode' => sanitize_key((string) ($validated['mode'] ?? 'invalid')),
        );
        $identity = implode('|', array(
            $record['event_id'],
            $record['session_id'],
            $record['payment_id'],
            (string) $record['order_id'],
        ));
        update_option(self::quarantine_retry_option($identity, $record['mode']), $record, false);
        Reconciler::record_global_drain_error($record);
        Reconciler::schedule_order($order->get_id());
        self::safe_log('critical', $code, $record);
    }

    /** @param array<string,mixed> $validated */
    private static function clear_quarantine_retry_failure(\WC_Order $order, array $validated): void
    {
        $expected = array(
            'order_id' => $order->get_id(),
            'event_id' => sanitize_text_field((string) ($validated['event_id'] ?? '')),
            'session_id' => sanitize_text_field((string) ($validated['session_id'] ?? '')),
            'payment_id' => sanitize_text_field((string) ($validated['payment_id'] ?? '')),
            'mode' => sanitize_key((string) ($validated['mode'] ?? 'invalid')),
        );
        $identity = implode('|', array(
            $expected['event_id'],
            $expected['session_id'],
            $expected['payment_id'],
            (string) $expected['order_id'],
        ));
        $option = self::quarantine_retry_option($identity, $expected['mode']);
        $record = get_option($option, null);
        if (is_array($record)
            && (int) ($record['order_id'] ?? 0) === $expected['order_id']
            && (string) ($record['event_id'] ?? '') === $expected['event_id']
            && (string) ($record['session_id'] ?? '') === $expected['session_id']
            && (string) ($record['payment_id'] ?? '') === $expected['payment_id']
            && (string) ($record['mode'] ?? '') === $expected['mode']) {
            if (Order_Lock::delete_option_if_exact($option, $record)) {
                self::clear_matching_global_incident($record, array((string) ($record['code'] ?? '')));
            }
        }
    }

    /** Clear only the failure whose exact identity has just been recovered. */
    private static function clear_matching_global_incident(array $expected, array $codes): bool
    {
        $option = 'bactive_paymongo_disable_drain_error';
        $record = get_option($option, null);
        if ($record === null) {
            return true;
        }
        if (!is_array($record) || !in_array((string) ($record['code'] ?? ''), $codes, true)) {
            return false;
        }
        foreach (array('order_id', 'event_id', 'session_id', 'payment_id', 'mode') as $key) {
            if (!array_key_exists($key, $record) || !array_key_exists($key, $expected)
                || $record[$key] !== $expected[$key]) {
                return false;
            }
        }
        return Order_Lock::delete_option_if_exact($option, $record);
    }

    /** @param array<string,mixed>|null $validated */
    private static function persist_review_hold(
        \WC_Order $order,
        string $code,
        string $payment_id,
        string $session_id,
        string $note,
        string $effect_identity,
        ?array $validated,
        bool $record_global_review_incident,
        bool $ensure_order_review_incident,
        array $quarantine_record
    ): bool {
        if (!Order_Lock::held_by_request($order->get_id())
            || !Order_Lock::renew($order->get_id())) {
            return false;
        }

        $effect_identity = $effect_identity !== ''
            ? sanitize_text_field($effect_identity)
            : hash('sha256', $code . '|' . $session_id . '|' . $order->get_id());
        $mode = (string) ($quarantine_record['mode'] ?? '');
        if (!self::valid_mode($mode)) {
            return false;
        }
        if ($payment_id !== ''
            && (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) === $payment_id
            && (string) $order->get_meta(self::SETTLEMENT_MODE_META, true) !== $mode) {
            return false;
        }
        $effect_context = array(
            'code' => sanitize_key($code),
            'event_id' => sanitize_text_field((string) ($quarantine_record['event_id'] ?? '')),
            'session_id' => sanitize_text_field((string) ($quarantine_record['session_id'] ?? $session_id)),
            'payment_id' => sanitize_text_field((string) ($quarantine_record['payment_id'] ?? $payment_id)),
            'mode' => $mode,
        );
        $already_linked = (string) $order->get_meta(self::REVIEW_EFFECT_IDENTITY_META, true) === $effect_identity
            && (string) $order->get_meta(self::REVIEW_EFFECT_CODE_META, true) === $effect_context['code']
            && (string) $order->get_meta(self::REVIEW_EFFECT_EVENT_META, true) === $effect_context['event_id']
            && (string) $order->get_meta(self::REVIEW_EFFECT_SESSION_META, true) === $effect_context['session_id']
            && (string) $order->get_meta(self::REVIEW_EFFECT_PAYMENT_META, true) === $effect_context['payment_id']
            && (string) $order->get_meta(self::REVIEW_EFFECT_MODE_META, true) === $mode
            && (string) $order->get_meta(self::REVIEW_MODE_META, true) === $mode
            && (string) $order->get_meta('_bactive_paymongo_review_required', true) === $effect_context['code']
            && (string) $order->get_meta(Reconciler::UNRESOLVED_META, true) === $effect_context['code']
            && (int) $order->get_meta('_bactive_paymongo_review_incidents', true) > 0;
        $active_review_values = array(
            (string) $order->get_meta('_bactive_paymongo_review_required', true),
            (string) $order->get_meta(Reconciler::UNRESOLVED_META, true),
            (string) $order->get_meta(self::REVIEW_MODE_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_IDENTITY_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_CODE_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_EVENT_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_SESSION_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_PAYMENT_META, true),
            (string) $order->get_meta(self::REVIEW_EFFECT_MODE_META, true),
            (string) $order->get_meta('_bactive_paymongo_review_incidents', true),
        );
        if (!$already_linked
            && array_filter($active_review_values, static fn(string $value): bool => $value !== '') !== array()) {
            // Preserve the already attached incident byte-for-byte. The new
            // mode-scoped quarantine stays durable and its provider event is
            // retried after the operator resolves the first incident.
            if ($record_global_review_incident) {
                Reconciler::record_review_incident($order, true, false);
            }
            Reconciler::set_draining(true);
            Reconciler::schedule_order($order->get_id());
            return false;
        }
        $order->update_meta_data('_bactive_paymongo_review_required', $effect_context['code']);
        $order->update_meta_data(Reconciler::UNRESOLVED_META, $effect_context['code']);
        $order->update_meta_data(self::REVIEW_EFFECT_IDENTITY_META, $effect_identity);
        $order->update_meta_data(self::REVIEW_EFFECT_CODE_META, $effect_context['code']);
        $order->update_meta_data(self::REVIEW_EFFECT_EVENT_META, $effect_context['event_id']);
        $order->update_meta_data(self::REVIEW_EFFECT_SESSION_META, $effect_context['session_id']);
        $order->update_meta_data(self::REVIEW_EFFECT_PAYMENT_META, $effect_context['payment_id']);
        $order->update_meta_data(self::REVIEW_EFFECT_MODE_META, $mode);
        $order->update_meta_data(self::REVIEW_MODE_META, $mode);
        if ($payment_id !== '') {
            $order->update_meta_data('_bactive_paymongo_unexpected_payment_id', sanitize_text_field($payment_id));
            $order->update_meta_data(self::UNEXPECTED_MODE_META, $mode);
            if ((string) $order->get_meta('_bactive_paymongo_settlement_pending', true) === $payment_id
                && (string) $order->get_meta(self::SETTLEMENT_MODE_META, true) === $mode) {
                $order->delete_meta_data('_bactive_paymongo_settlement_pending');
                $order->delete_meta_data(self::SETTLEMENT_MODE_META);
            }
        }
        Reconciler::mark_required($order);
        if ($ensure_order_review_incident && ($record_global_review_incident || !$already_linked)) {
            Reconciler::record_review_incident(
                $order,
                $record_global_review_incident,
                !$already_linked
            );
        }
        self::save_with_status_effects_suppressed($order);

        $durable = self::fresh_order($order->get_id());
        if (!$durable instanceof \WC_Order
            || !self::review_state_matches(
                $durable,
                $code,
                $payment_id,
                $session_id,
                $validated,
                $effect_identity,
                $effect_context
            )) {
            return false;
        }

        if ($durable->has_status('on-hold')) {
            $effect = self::effects_record('review', $effect_identity, $mode);
            if (is_array($effect) && ($effect['status'] ?? '') === 'processing') {
                return false;
            }
            if (is_array($effect) && ($effect['status'] ?? '') === 'armed') {
                $transition = array(
                    'from' => (string) ($effect['from'] ?? ''),
                    'to' => (string) ($effect['to'] ?? ''),
                    'note' => $effect['note'] ?? false,
                    'manual' => (bool) ($effect['manual'] ?? false),
                );
                return self::emit_review_effects($durable, $effect_identity, $mode, $transition);
            }
            if ($record_global_review_incident && $note !== '') {
                $durable->add_order_note($note);
            }
            return !is_array($effect) || ($effect['status'] ?? '') === 'done';
        }

        $from_status = $durable->get_status();
        $durable->set_status('on-hold', $note, false);
        $transition = self::take_status_transition($durable);
        if (!self::transition_matches($transition, $from_status, 'on-hold')
            || self::arm_effects(
                'review',
                $effect_identity,
                $mode,
                $durable->get_id(),
                $transition,
                $effect_context
            ) !== 'armed') {
            return false;
        }
        self::save_with_status_effects_suppressed($durable, $transition);
        $held = self::fresh_order($durable->get_id());
        if (!$held instanceof \WC_Order
            || !$held->has_status('on-hold')
            || !self::review_state_matches(
                $held,
                $code,
                $payment_id,
                $session_id,
                $validated,
                $effect_identity,
                $effect_context
            )) {
            return false;
        }
        return self::emit_review_effects($held, $effect_identity, $mode, $transition);
    }

    /** @param array<string,mixed>|null $validated */
    private static function review_state_matches(
        \WC_Order $order,
        string $code,
        string $payment_id,
        string $session_id,
        ?array $validated,
        string $effect_identity,
        array $effect_context
    ): bool {
        if ((string) $order->get_meta('_bactive_paymongo_review_required', true) !== sanitize_key($code)
            || (string) $order->get_meta(Reconciler::UNRESOLVED_META, true) !== sanitize_key($code)
            || (string) $order->get_meta(Reconciler::REQUIRED_META, true) !== 'yes'
            || (string) $order->get_meta(self::REVIEW_EFFECT_IDENTITY_META, true) !== $effect_identity
            || (string) $order->get_meta(self::REVIEW_EFFECT_CODE_META, true) !== (string) $effect_context['code']
            || (string) $order->get_meta(self::REVIEW_EFFECT_EVENT_META, true) !== (string) $effect_context['event_id']
            || (string) $order->get_meta(self::REVIEW_EFFECT_SESSION_META, true) !== (string) $effect_context['session_id']
            || (string) $order->get_meta(self::REVIEW_EFFECT_PAYMENT_META, true) !== (string) $effect_context['payment_id']
            || (string) $order->get_meta(self::REVIEW_EFFECT_MODE_META, true) !== (string) $effect_context['mode']
            || (string) $order->get_meta(self::REVIEW_MODE_META, true) !== (string) $effect_context['mode']
            || ($payment_id !== ''
                && ((string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true) !== sanitize_text_field($payment_id)
                    || (string) $order->get_meta(self::UNEXPECTED_MODE_META, true) !== (string) $effect_context['mode']
                    || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== ''
                    || (string) $order->get_meta(self::SETTLEMENT_MODE_META, true) !== ''))) {
            return false;
        }
        if (is_array($validated) && !self::payment_facts_match($order, $validated, $payment_id)) {
            return false;
        }
        if ($session_id !== '' && $payment_id !== '') {
            $matches = 0;
            foreach (Gateway::order_attempts($order) as $attempt) {
                if ((string) ($attempt['session_id'] ?? '') !== $session_id
                    || (string) ($attempt['mode'] ?? '') !== (string) $effect_context['mode']) {
                    continue;
                }
                if ((string) ($attempt['payment_id'] ?? '') === $payment_id
                    || in_array($payment_id, (array) ($attempt['reconciliation_payment_ids'] ?? array()), true)) {
                    ++$matches;
                }
            }
            return $matches === 1;
        }
        return true;
    }

    /** @param array<string,mixed> $transition */
    private static function emit_review_effects(
        \WC_Order $order,
        string $identity,
        string $mode,
        array $transition
    ): bool {
        $record = self::effects_record('review', $identity, $mode);
        if (!is_array($record)
            || !self::transition_matches($transition, (string) ($record['from'] ?? ''), (string) ($record['to'] ?? ''))
            || !self::begin_effects('review', $identity, $mode, $record)) {
            return false;
        }
        try {
            self::replay_status_transition($order, $transition);
        } catch (\Throwable $error) {
            return false;
        }
        return self::finish_effects('review', $identity, $mode);
    }

    /** @param array<string,mixed> $validated */
    private static function ensure_settlement_recovery(\WC_Order $order, array $validated): void
    {
        $recovered = false;
        try {
            if (!Order_Lock::held_by_request($order->get_id())
                || !Order_Lock::renew($order->get_id())) {
                self::record_processing_incident($order, $validated, 'settlement_recovery_lock_lost');
                return;
            }
            $fresh = self::fresh_order($order->get_id());
            if ($fresh instanceof \WC_Order) {
                $order = $fresh;
            }
            $payment_id = sanitize_text_field((string) ($validated['payment_id'] ?? ''));
            if ($payment_id === '') {
                self::record_processing_incident($order, $validated, 'settlement_recovery_identity_missing');
                return;
            }
            if (self::payment_identity_conflicts($order, $validated, $payment_id)) {
                // A failed attempt to quarantine a second payment must never
                // turn into authority to overwrite the first settlement while
                // the generic retry wrapper establishes recovery markers.
                self::record_processing_incident($order, $validated, 'settlement_recovery_identity_collision');
                return;
            }
            self::record_payment_facts($order, $validated, $payment_id);
            $order->update_meta_data('_bactive_paymongo_settlement_pending', $payment_id);
            $order->update_meta_data(
                self::SETTLEMENT_MODE_META,
                sanitize_key((string) ($validated['mode'] ?? ''))
            );
            Reconciler::mark_required($order);
            if (!Order_Lock::renew($order->get_id())) {
                self::record_processing_incident($order, $validated, 'settlement_recovery_lock_lost');
                return;
            }
            self::save_with_status_effects_suppressed($order);
            $readback = self::fresh_order($order->get_id());
            $recovered = $readback instanceof \WC_Order
                && (string) $readback->get_meta('_bactive_paymongo_settlement_pending', true) === $payment_id
                && (string) $readback->get_meta(self::SETTLEMENT_MODE_META, true)
                    === (string) ($validated['mode'] ?? '')
                && self::payment_facts_match($readback, $validated, $payment_id);
        } catch (\Throwable $recovery_error) {
            $recovered = false;
        }
        if (!$recovered) {
            self::record_processing_incident($order, $validated, 'settlement_recovery_persist_failed');
        }
    }

    /** @param array<string,mixed> $attempt */
    private static function unexpected_payment_code(\WC_Order $order, array $attempt): string
    {
        if ($order->get_payment_method() !== GATEWAY_ID) {
            return 'paid_after_payment_method_changed';
        }
        if (!empty($attempt['expired_at'])) {
            return 'paid_after_session_expired';
        }
        if ($order->has_status(array('cancelled', 'refunded', 'failed'))) {
            return 'paid_after_closed_order';
        }
        if ($order->is_paid()) {
            return 'additional_paid_payment';
        }
        return 'paid_after_order_stopped_payment';
    }

    /** @param array<string,mixed> $validated */
    private static function hold_unexpected_payment(
        \WC_Order $order,
        array $validated,
        string $payment_id,
        string $code,
        bool $preserve_existing_facts = false
    ): void {
        if (!Order_Lock::renew($order->get_id())) {
            self::record_quarantine_retry_failure($order, $validated, 'payment_quarantine_lock_lost');
            throw new Quarantine_Retry_Exception('Payment order lock was lost before quarantine.');
        }
        $note = sprintf(
            /* translators: 1: payment ID, 2: reconciliation code */
            __('Unexpected signed PayMongo payment received. Payment ID: %1$s; reason: %2$s. Fulfillment is paused for reconciliation.', 'bactive-paymongo'),
            sanitize_text_field($payment_id),
            sanitize_key($code)
        );

        $quarantine = self::prepare_quarantine_record(
            $code,
            (string) $validated['event_id'],
            (string) $validated['session_id'],
            $order->get_id(),
            $payment_id,
            (string) ($validated['mode'] ?? 'invalid')
        );
        if (empty($quarantine['durable'])) {
            self::record_quarantine_retry_failure($order, $validated, 'payment_quarantine_record_persist_failed');
            throw new Quarantine_Retry_Exception('Payment quarantine record was not persisted.');
        }
        if (!self::persist_review_hold(
            $order,
            $code,
            $preserve_existing_facts ? '' : $payment_id,
            $preserve_existing_facts ? '' : (string) $validated['session_id'],
            $note,
            (string) $validated['event_id'],
            $preserve_existing_facts ? null : $validated,
            (bool) $quarantine['record_incident'],
            (bool) $quarantine['needs_annotation'],
            $quarantine['record']
        )) {
            self::record_quarantine_retry_failure($order, $validated, 'payment_quarantine_persist_failed');
            throw new Quarantine_Retry_Exception('Payment quarantine was not independently verified.');
        }
        if (!self::finish_quarantine_record($quarantine)) {
            self::record_quarantine_retry_failure($order, $validated, 'payment_quarantine_record_failed');
            throw new Quarantine_Retry_Exception('Payment quarantine record was not independently verified.');
        }
        self::clear_quarantine_retry_failure($order, $validated);
        self::safe_log('error', $code, $quarantine['record']);
    }

    /** @param array<string,mixed> $session */
    private static function quarantine_retrieved_payment(
        \WC_Order $order,
        array $session,
        string $event_id,
        string $session_id,
        string $validation_code,
        bool $live
    ): bool {
        $order_id = $order->get_id();
        $code = 'reconciliation_' . sanitize_key($validation_code);
        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            self::quarantine($code, $event_id, $session_id, $order_id, '', self::provider_mode($live));
            return false;
        }
        try {
            if (!self::refresh_order($order) || !Order_Lock::renew($order_id)) {
                self::quarantine($code, $event_id, $session_id, $order_id, '', self::provider_mode($live));
                return false;
            }
            $payment_ids = array();
            $attributes = is_array($session['attributes'] ?? null) ? $session['attributes'] : array();
            foreach ((array) ($attributes['payments'] ?? array()) as $payment) {
                $payment_attributes = is_array($payment) ? ($payment['attributes'] ?? null) : null;
                $payment_id = is_array($payment) ? (string) ($payment['id'] ?? '') : '';
                if (is_array($payment_attributes)
                    && ($payment_attributes['status'] ?? '') === 'paid'
                    && preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', $payment_id)) {
                    $payment_ids[] = $payment_id;
                }
            }

            $mode = self::provider_mode($live);
            $payment_ids = array_values(array_unique($payment_ids));
            $payment_id = count($payment_ids) === 1 ? (string) $payment_ids[0] : '';
            $attempts = Gateway::order_attempts($order);
            $matching_indexes = array();
            foreach ($attempts as $index => $attempt) {
                if ((string) ($attempt['session_id'] ?? '') === $session_id
                    && (string) ($attempt['mode'] ?? '') === $mode) {
                    $matching_indexes[] = $index;
                }
            }
            $attempt_is_unique = count($matching_indexes) === 1 && $payment_id !== '';
            if ($attempt_is_unique) {
                $attempt_index = (int) $matching_indexes[0];
                $attempts[$attempt_index]['paid_at'] = time();
                $attempts[$attempt_index]['reconciliation_payment_ids'] = $payment_ids;
            }
            $linked_payment_id = $attempt_is_unique ? $payment_id : '';
            $linked_session_id = $attempt_is_unique ? $session_id : '';
            if (!Order_Lock::renew($order_id)) {
                self::quarantine(
                    $code,
                    $event_id,
                    $session_id,
                    $order_id,
                    $payment_id,
                    $mode
                );
                return false;
            }
            if ($attempt_is_unique) {
                $order->update_meta_data('_bactive_paymongo_attempts', $attempts);
            }
            $quarantine = self::prepare_quarantine_record(
                $code,
                $event_id,
                $session_id,
                $order_id,
                $payment_id,
                $mode
            );
            if (empty($quarantine['durable'])) {
                self::record_quarantine_retry_failure($order, array(
                    'event_id' => $event_id,
                    'session_id' => $session_id,
                    'payment_id' => $payment_id,
                    'mode' => $mode,
                ), 'retrieved_payment_quarantine_record_persist_failed');
                return false;
            }
            try {
                $persisted = self::persist_review_hold(
                    $order,
                    $code,
                    $linked_payment_id,
                    $linked_session_id,
                    __('Authenticated PayMongo reconciliation found a paid session that failed strict validation. Fulfillment is paused.', 'bactive-paymongo'),
                    $event_id,
                    null,
                    (bool) $quarantine['record_incident'],
                    (bool) $quarantine['needs_annotation'],
                    $quarantine['record']
                );
            } catch (\Throwable $error) {
                $persisted = false;
            }
            if (!$persisted) {
                self::record_quarantine_retry_failure($order, array(
                    'event_id' => $event_id,
                    'session_id' => $session_id,
                    'payment_id' => $payment_id,
                    'mode' => $mode,
                ), 'retrieved_payment_quarantine_persist_failed');
                return false;
            }
            if (!self::finish_quarantine_record($quarantine)) {
                self::record_quarantine_retry_failure($order, array(
                    'event_id' => $event_id,
                    'session_id' => $session_id,
                    'payment_id' => $payment_id,
                    'mode' => $mode,
                ), 'retrieved_payment_quarantine_record_failed');
                return false;
            }
            self::clear_quarantine_retry_failure($order, array(
                'event_id' => $event_id,
                'session_id' => $session_id,
                'payment_id' => $payment_id,
                'mode' => $mode,
            ));
            self::safe_log('error', $code, $quarantine['record']);
            return true;
        } finally {
            if (!$held_by_request) {
                Order_Lock::release($order_id);
            }
        }
    }

    /** @return array<string,mixed> */
    private static function session_from_payload(array $payload): array
    {
        $event = is_array($payload['data'] ?? null) ? $payload['data'] : array();
        $attributes = is_array($event['attributes'] ?? null) ? $event['attributes'] : array();
        return is_array($attributes['data'] ?? null) ? $attributes['data'] : array();
    }

    private static function refresh_order(\WC_Order $order): bool
    {
        try {
            $data_store = $order->get_data_store();
            if (!is_object($data_store) || !is_callable(array($data_store, 'read'))) {
                return false;
            }
            $data_store->read($order);
            return $order->get_id() > 0;
        } catch (\Throwable $error) {
            return false;
        }
    }

    /** @return array{event_id:string,session_id:string} */
    private static function event_identity(array $payload): array
    {
        $event = is_array($payload['data'] ?? null) ? $payload['data'] : array();
        $attributes = is_array($event['attributes'] ?? null) ? $event['attributes'] : array();
        $session = is_array($attributes['data'] ?? null) ? $attributes['data'] : array();

        return array(
            'event_id' => sanitize_text_field((string) ($event['id'] ?? '')),
            'session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
        );
    }

    private static function locate_order(array $payload): ?\WC_Order
    {
        $event = is_array($payload['data'] ?? null) ? $payload['data'] : array();
        $attributes = is_array($event['attributes'] ?? null) ? $event['attributes'] : array();
        $session = is_array($attributes['data'] ?? null) ? $attributes['data'] : array();
        $session_attributes = is_array($session['attributes'] ?? null) ? $session['attributes'] : array();
        $reference = (string) ($session_attributes['reference_number'] ?? '');
        if (!preg_match('/^BA-([1-9][0-9]*)-([1-9][0-9]*)$/D', $reference, $matches)) {
            return null;
        }

        $order = wc_get_order((int) $matches[1]);
        if (!$order instanceof \WC_Order
            || $order->get_id() !== (int) $matches[1]) {
            return null;
        }
        return $order;
    }

    /** @return array<string,mixed>|null */
    private static function attempt_for_session(\WC_Order $order, string $session_id, bool $live): ?array
    {
        $matches = array();
        foreach (Gateway::order_attempts($order) as $attempt) {
            if (($attempt['session_id'] ?? '') === $session_id
                && ($attempt['mode'] ?? '') === ($live ? 'live' : 'test')) {
                $matches[] = $attempt;
            }
        }
        return count($matches) === 1 ? $matches[0] : null;
    }

    private static function signature_header(): string
    {
        if (isset($_SERVER['HTTP_PAYMONGO_SIGNATURE'])) {
            return trim(wp_unslash((string) $_SERVER['HTTP_PAYMONGO_SIGNATURE']));
        }
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strtolower((string) $key) === 'paymongo-signature') {
                    return trim((string) $value);
                }
            }
        }
        return '';
    }

    private static function provider_mode(bool $live): string
    {
        return $live ? 'live' : 'test';
    }

    private static function valid_mode(string $mode): bool
    {
        return in_array($mode, array('test', 'live', 'local'), true);
    }

    private static function review_mode_for_order(\WC_Order $order, string $session_id): string
    {
        $modes = array();
        foreach (Gateway::order_attempts($order) as $attempt) {
            if ($session_id !== '' && (string) ($attempt['session_id'] ?? '') !== $session_id) {
                continue;
            }
            $mode = (string) ($attempt['mode'] ?? '');
            if (in_array($mode, array('test', 'live'), true)) {
                $modes[$mode] = true;
            }
        }
        $modes = array_keys($modes);
        return count($modes) === 1 ? (string) $modes[0] : 'local';
    }

    private static function payment_mode_for_order(\WC_Order $order, string $payment_id): string
    {
        if (!preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', $payment_id)) {
            return '';
        }
        $modes = array();
        foreach (array(
            array('_bactive_paymongo_settlement_pending', self::SETTLEMENT_MODE_META),
            array('_bactive_paymongo_unexpected_payment_id', self::UNEXPECTED_MODE_META),
            array('transaction', self::PAID_MODE_META),
        ) as $source) {
            $known_id = $source[0] === 'transaction'
                ? (string) $order->get_transaction_id()
                : (string) $order->get_meta($source[0], true);
            if ($known_id !== $payment_id) {
                continue;
            }
            $mode = (string) $order->get_meta($source[1], true);
            if (!in_array($mode, array('test', 'live'), true)) {
                return '';
            }
            $modes[$mode] = true;
        }
        foreach (Gateway::order_attempts($order) as $attempt) {
            $matches = (string) ($attempt['payment_id'] ?? '') === $payment_id
                || in_array($payment_id, (array) ($attempt['reconciliation_payment_ids'] ?? array()), true);
            if (!$matches) {
                continue;
            }
            $mode = (string) ($attempt['mode'] ?? '');
            if (!in_array($mode, array('test', 'live'), true)) {
                return '';
            }
            $modes[$mode] = true;
        }
        $modes = array_keys($modes);
        return count($modes) === 1 ? (string) $modes[0] : '';
    }

    private static function claim(string $kind, string $id, string $mode): string
    {
        if (!self::valid_mode($mode)) {
            return 'invalid';
        }
        $kind = sanitize_key($kind);
        $id = sanitize_text_field($id);
        $option = self::claim_option($kind, $id, $mode);
        $record = array(
            'status' => 'processing',
            'claimed_at' => time(),
            'kind' => $kind,
            'identity' => $id,
            'mode' => $mode,
        );
        if (add_option($option, $record, '', false)) {
            return 'claimed';
        }

        $existing = get_option($option, array());
        if (!is_array($existing)
            || (string) ($existing['kind'] ?? '') !== $kind
            || (string) ($existing['identity'] ?? '') !== $id
            || (string) ($existing['mode'] ?? '') !== $mode) {
            return 'invalid';
        }
        if (in_array($existing['status'] ?? '', array('processed', 'quarantined'), true)) {
            return 'done';
        }
        if ((time() - (int) ($existing['claimed_at'] ?? 0)) <= self::CLAIM_TTL) {
            return 'busy';
        }

        delete_option($option);
        return add_option($option, $record, '', false) ? 'claimed' : 'busy';
    }

    private static function finish_claim(string $kind, string $id, string $status, string $mode): void
    {
        $option = self::claim_option($kind, $id, $mode);
        $existing = get_option($option, null);
        if (!is_array($existing)
            || (string) ($existing['status'] ?? '') !== 'processing'
            || (string) ($existing['kind'] ?? '') !== sanitize_key($kind)
            || (string) ($existing['identity'] ?? '') !== sanitize_text_field($id)
            || (string) ($existing['mode'] ?? '') !== $mode
            || !in_array($status, array('processed', 'quarantined'), true)) {
            return;
        }
        $existing['status'] = $status;
        $existing['claimed_at'] = time();
        update_option(
            $option,
            $existing,
            false
        );
    }

    private static function release_claim(string $kind, string $id, string $mode): void
    {
        $option = self::claim_option($kind, $id, $mode);
        $existing = get_option($option, null);
        if (is_array($existing)
            && (string) ($existing['status'] ?? '') === 'processing'
            && (string) ($existing['kind'] ?? '') === sanitize_key($kind)
            && (string) ($existing['identity'] ?? '') === sanitize_text_field($id)
            && (string) ($existing['mode'] ?? '') === $mode) {
            delete_option($option);
        }
    }

    private static function claim_option(string $kind, string $id, string $mode): string
    {
        return 'bactive_paymongo_' . $mode . '_' . sanitize_key($kind) . '_'
            . hash('sha256', $mode . '|' . $id);
    }

    private static function finish_claimed_quarantine(
        string $code,
        string $event_id,
        string $session_id,
        int $order_id,
        string $payment_id,
        bool $live
    ): bool {
        $mode = self::provider_mode($live);
        if (!self::quarantine($code, $event_id, $session_id, $order_id, $payment_id, $mode)) {
            self::release_claim('event', $event_id, $mode);
            return false;
        }
        self::finish_claim('event', $event_id, 'quarantined', $mode);
        return true;
    }

    private static function acquire_order_lock(int $order_id): bool
    {
        return Order_Lock::acquire($order_id);
    }

    private static function release_order_lock(int $order_id): void
    {
        Order_Lock::release($order_id);
    }

    private static function quarantine(
        string $code,
        string $event_id,
        string $session_id,
        int $order_id,
        string $payment_id,
        string $mode = 'local'
    ): bool {
        $quarantine = self::prepare_quarantine_record(
            $code,
            $event_id,
            $session_id,
            $order_id,
            $payment_id,
            $mode
        );
        $record = $quarantine['record'];
        if (empty($quarantine['durable'])) {
            self::record_quarantine_persistence_failure($record);
            if ($order_id > 0) {
                Reconciler::schedule_order($order_id);
            }
            return false;
        }

        if ($order_id > 0) {
            $held_by_request = Order_Lock::held_by_request($order_id);
            if (!Order_Lock::acquire($order_id)) {
                Reconciler::schedule_order($order_id);
                self::safe_log('error', $code, $record);
                return false;
            }
            try {
                $order = wc_get_order($order_id);
                if (!$order instanceof \WC_Order
                    || !self::refresh_order($order)
                    || !Order_Lock::renew($order_id)) {
                    Reconciler::schedule_order($order_id);
                    self::safe_log('error', $code, $record);
                    return false;
                }
                try {
                    $persisted = self::persist_review_hold(
                        $order,
                        $code,
                        $payment_id,
                        $session_id,
                        sprintf(
                            /* translators: %s: sanitized reconciliation reason */
                            __('PayMongo event quarantined for manual review: %s.', 'bactive-paymongo'),
                            sanitize_key($code)
                        ),
                        $event_id,
                        null,
                        (bool) $quarantine['record_incident'],
                        (bool) $quarantine['needs_annotation'],
                        $quarantine['record']
                    );
                } catch (\Throwable $error) {
                    $persisted = false;
                }
                if (!$persisted || !self::finish_quarantine_record($quarantine)) {
                    Reconciler::schedule_order($order_id);
                    Reconciler::record_global_drain_error(
                        array(
                            'recorded_at' => time(),
                            'code' => 'quarantine_persist_failed',
                            'order_id' => $order_id,
                            'event_id' => sanitize_text_field($event_id),
                            'session_id' => sanitize_text_field($session_id),
                            'payment_id' => sanitize_text_field($payment_id),
                            'mode' => sanitize_key($mode),
                        )
                    );
                    self::safe_log('critical', 'quarantine_persist_failed', $record);
                    return false;
                }
            } finally {
                if (!$held_by_request) {
                    Order_Lock::release($order_id);
                }
            }
        }

        if ($order_id === 0 && !self::finish_quarantine_record($quarantine)) {
            self::record_quarantine_persistence_failure($record);
            return false;
        }
        self::safe_log('error', $code, $record);
        return true;
    }

    /** @param array<string,mixed> $context */
    private static function safe_log(string $level, string $code, array $context = array()): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $safe = array('code' => sanitize_key($code));
        foreach (array('order_id', 'event_id', 'session_id', 'payment_id', 'mode') as $key) {
            if (isset($context[$key])) {
                $safe[$key] = sanitize_text_field((string) $context[$key]);
            }
        }
        wc_get_logger()->log($level, wp_json_encode($safe), array('source' => 'bactive-paymongo'));
    }

    private static function respond(int $status, string $code): void
    {
        wp_send_json(array('received' => $status >= 200 && $status <= 209, 'code' => $code), $status);
        exit;
    }
}
