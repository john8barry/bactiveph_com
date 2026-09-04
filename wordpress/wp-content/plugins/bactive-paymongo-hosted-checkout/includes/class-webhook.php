<?php

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

final class Webhook
{
    private const MAX_BODY_BYTES = 1048576;
    private const CLAIM_TTL = 120;

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
            self::quarantine('json_invalid', '', '', 0, '');
            self::respond(202, 'accepted_for_review');
        }
        $payload = Integrity::normalize_event($payload, $raw);
        if ($payload === null) {
            self::quarantine('event_shape_invalid', '', '', 0, '');
            self::respond(202, 'accepted_for_review');
        }

        $identity = self::event_identity($payload);
        if ($identity['event_id'] === '' || $identity['session_id'] === '') {
            self::quarantine('event_identity_invalid', $identity['event_id'], $identity['session_id'], 0, '');
            self::respond(202, 'accepted_for_review');
        }

        $event_claim = self::claim('event', $identity['event_id']);
        if ($event_claim === 'done') {
            self::respond(200, 'duplicate');
        }
        if ($event_claim !== 'claimed') {
            self::respond(503, 'retry_later');
        }

        $order = self::locate_order($payload);
        if (!$order instanceof \WC_Order) {
            self::finish_claim('event', $identity['event_id'], 'quarantined');
            self::quarantine('order_not_found', $identity['event_id'], $identity['session_id'], 0, '');
            self::respond(202, 'accepted_for_review');
        }

        $order_id = $order->get_id();
        if (!$live && !(new Gateway())->is_test_mode()) {
            self::finish_claim('event', $identity['event_id'], 'quarantined');
            self::quarantine('test_event_on_live_site', $identity['event_id'], $identity['session_id'], $order_id, '');
            self::respond(202, 'accepted_for_review');
        }
        $attempt = self::attempt_for_session($order, $identity['session_id'], $live);
        if ($attempt === null) {
            self::finish_claim('event', $identity['event_id'], 'quarantined');
            self::quarantine('session_not_authorized', $identity['event_id'], $identity['session_id'], $order_id, '');
            self::respond(202, 'accepted_for_review');
        }

        $amount = Integrity::amount_to_minor((string) $order->get_total());
        if ($amount === null || $order->get_currency() !== 'PHP') {
            self::finish_claim('event', $identity['event_id'], 'quarantined');
            self::quarantine('order_amount_invalid', $identity['event_id'], $identity['session_id'], $order_id, '');
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
            self::finish_claim('event', $identity['event_id'], 'quarantined');
            self::quarantine($validated['code'], $identity['event_id'], $identity['session_id'], $order_id, '');
            self::respond(202, 'accepted_for_review');
        }

        $payment_id = (string) $validated['payment_id'];
        $payment_claim = self::claim('payment', $payment_id);
        if ($payment_claim === 'done') {
            self::finish_claim('event', $identity['event_id'], 'processed');
            self::respond(200, 'duplicate');
        }
        if ($payment_claim !== 'claimed') {
            self::release_claim('event', $identity['event_id']);
            self::respond(503, 'retry_later');
        }
        if (!self::acquire_order_lock($order_id)) {
            self::release_claim('payment', $payment_id);
            self::release_claim('event', $identity['event_id']);
            self::respond(503, 'retry_later');
        }

        try {
            self::apply_payment($order, $validated, $attempt);
            self::finish_claim('payment', $payment_id, 'processed');
            self::finish_claim('event', $identity['event_id'], 'processed');
        } catch (\Throwable $error) {
            self::release_claim('payment', $payment_id);
            self::release_claim('event', $identity['event_id']);
            self::safe_log(
                'error',
                'payment_processing_failed',
                array(
                    'order_id' => $order_id,
                    'event_id' => $identity['event_id'],
                    'session_id' => $identity['session_id'],
                    'payment_id' => $payment_id,
                )
            );
            self::release_order_lock($order_id);
            self::respond(503, 'retry_later');
        }

        self::release_order_lock($order_id);
        self::respond(200, 'processed');
    }

    /** @param array<string,mixed> $validated @param array<string,mixed> $attempt */
    private static function apply_payment(\WC_Order $order, array $validated, array $attempt): void
    {
        $payment_id = (string) $validated['payment_id'];
        $existing_transaction = (string) $order->get_transaction_id();
        if ($order->is_paid()) {
            if ($existing_transaction === $payment_id) {
                return;
            }

            self::quarantine(
                'additional_paid_payment',
                (string) $validated['event_id'],
                (string) $validated['session_id'],
                $order->get_id(),
                $payment_id
            );
            return;
        }

        $order->update_meta_data('_bactive_paymongo_source_method', sanitize_key((string) $validated['method']));
        $order->update_meta_data('_bactive_paymongo_source_provider', sanitize_key((string) $validated['provider']));
        $order->update_meta_data('_bactive_paymongo_paid_event_id', sanitize_text_field((string) $validated['event_id']));
        $order->update_meta_data('_bactive_paymongo_paid_session_id', sanitize_text_field((string) $validated['session_id']));

        $attempts = Gateway::order_attempts($order);
        foreach ($attempts as &$stored_attempt) {
            if (($stored_attempt['session_id'] ?? '') === $validated['session_id']) {
                $stored_attempt['paid_event_id'] = $validated['event_id'];
                $stored_attempt['payment_id'] = $payment_id;
                $stored_attempt['paid_at'] = time();
            }
        }
        unset($stored_attempt);
        $order->update_meta_data('_bactive_paymongo_attempts', $attempts);

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

        if ($order->has_status(array('cancelled', 'refunded', 'failed'))) {
            $order->set_transaction_id($payment_id);
            $order->update_meta_data('_bactive_paymongo_review_required', 'paid_after_closed_order');
            $order->update_status('on-hold', $note . ' Manual reconciliation is required.', true);
            $order->save();
            self::quarantine(
                'paid_after_closed_order',
                (string) $validated['event_id'],
                (string) $validated['session_id'],
                $order->get_id(),
                $payment_id
            );
            return;
        }

        $order->add_order_note($note);
        $order->save();
        $order->payment_complete($payment_id);
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
            || $order->get_payment_method() !== GATEWAY_ID
            || $order->get_id() !== (int) $matches[1]) {
            return null;
        }
        return $order;
    }

    /** @return array<string,mixed>|null */
    private static function attempt_for_session(\WC_Order $order, string $session_id, bool $live): ?array
    {
        foreach (Gateway::order_attempts($order) as $attempt) {
            if (($attempt['session_id'] ?? '') === $session_id
                && ($attempt['mode'] ?? '') === ($live ? 'live' : 'test')) {
                return $attempt;
            }
        }
        return null;
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

    private static function claim(string $kind, string $id): string
    {
        $option = self::claim_option($kind, $id);
        $record = array('status' => 'processing', 'claimed_at' => time());
        if (add_option($option, $record, '', false)) {
            return 'claimed';
        }

        $existing = get_option($option, array());
        if (is_array($existing) && in_array($existing['status'] ?? '', array('processed', 'quarantined'), true)) {
            return 'done';
        }
        if (is_array($existing) && (time() - (int) ($existing['claimed_at'] ?? 0)) <= self::CLAIM_TTL) {
            return 'busy';
        }

        delete_option($option);
        return add_option($option, $record, '', false) ? 'claimed' : 'busy';
    }

    private static function finish_claim(string $kind, string $id, string $status): void
    {
        update_option(
            self::claim_option($kind, $id),
            array('status' => $status, 'claimed_at' => time()),
            false
        );
    }

    private static function release_claim(string $kind, string $id): void
    {
        delete_option(self::claim_option($kind, $id));
    }

    private static function claim_option(string $kind, string $id): string
    {
        return 'bactive_paymongo_' . $kind . '_' . hash('sha256', $id);
    }

    private static function acquire_order_lock(int $order_id): bool
    {
        $option = 'bactive_paymongo_webhook_order_lock_' . $order_id;
        $now = time();
        if (add_option($option, $now, '', false)) {
            return true;
        }
        $existing = (int) get_option($option, 0);
        if ($existing > 0 && ($now - $existing) > self::CLAIM_TTL) {
            delete_option($option);
            return add_option($option, $now, '', false);
        }
        return false;
    }

    private static function release_order_lock(int $order_id): void
    {
        delete_option('bactive_paymongo_webhook_order_lock_' . $order_id);
    }

    private static function quarantine(
        string $code,
        string $event_id,
        string $session_id,
        int $order_id,
        string $payment_id
    ): void {
        $identity = $event_id !== '' ? $event_id : hash('sha256', $code . '|' . $session_id . '|' . $order_id);
        $option = 'bactive_paymongo_quarantine_' . hash('sha256', $identity);
        $record = array(
            'code' => sanitize_key($code),
            'event_id' => sanitize_text_field($event_id),
            'session_id' => sanitize_text_field($session_id),
            'payment_id' => sanitize_text_field($payment_id),
            'order_id' => $order_id,
            'recorded_at' => time(),
        );
        if (add_option($option, $record, '', false)) {
            update_option('bactive_paymongo_review_count', (int) get_option('bactive_paymongo_review_count', 0) + 1, false);
        }

        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order instanceof \WC_Order) {
                $order->update_meta_data('_bactive_paymongo_review_required', sanitize_key($code));
                $order->add_order_note(
                    sprintf(
                        /* translators: %s: sanitized reconciliation reason */
                        __('PayMongo event quarantined for manual review: %s.', 'bactive-paymongo'),
                        sanitize_key($code)
                    )
                );
                $order->save();
            }
        }

        self::safe_log('error', $code, $record);
    }

    /** @param array<string,mixed> $context */
    private static function safe_log(string $level, string $code, array $context = array()): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $safe = array('code' => sanitize_key($code));
        foreach (array('order_id', 'event_id', 'session_id', 'payment_id') as $key) {
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
