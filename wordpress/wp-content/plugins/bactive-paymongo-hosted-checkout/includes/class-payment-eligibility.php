<?php

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

/**
 * Read-only marketing eligibility for persisted WooCommerce and PayMongo state.
 *
 * This class never calls PayMongo, schedules recovery, updates an order, or
 * changes WooCommerce payment state. Consumers must treat UNCERTAIN as a hard
 * stop and wait for the existing payment-recovery workflow to finish.
 */
final class Payment_Eligibility
{
    public const SETTLED = 'settled';
    public const UNPAID = 'unpaid';
    public const UNCERTAIN = 'uncertain';

    private const ATTEMPTS_META = '_bactive_paymongo_attempts';

    /** These controls prove that a recovery or operator decision is still relevant. */
    private const RECOVERY_META = array(
        Reconciler::REQUIRED_META,
        Reconciler::UNRESOLVED_META,
        '_bactive_paymongo_review_required',
        '_bactive_paymongo_review_incidents',
        '_bactive_paymongo_review_mode',
        '_bactive_paymongo_settlement_pending',
        '_bactive_paymongo_settlement_pending_mode',
        '_bactive_paymongo_unexpected_payment_id',
        '_bactive_paymongo_unexpected_payment_mode',
        '_bactive_paymongo_processing_incident_code',
        '_bactive_paymongo_processing_incident_payment_id',
        '_bactive_paymongo_processing_incident_event_id',
        '_bactive_paymongo_processing_incident_session_id',
        '_bactive_paymongo_processing_incident_mode',
        '_bactive_paymongo_review_effect_identity',
        '_bactive_paymongo_review_effect_code',
        '_bactive_paymongo_review_effect_event_id',
        '_bactive_paymongo_review_effect_session_id',
        '_bactive_paymongo_review_effect_payment_id',
        '_bactive_paymongo_review_effect_mode',
        '_bactive_paymongo_resolved_evidence_fingerprint',
        '_bactive_paymongo_resolved_payment_pending',
        '_bactive_paymongo_operator_disposition',
        '_bactive_paymongo_reconcile_poll_count',
    );

    /**
     * Return SETTLED only for a fully persisted, internally consistent payment.
     * UNPAID is safe to wait on; UNCERTAIN needs the existing recovery/review
     * controls and must never trigger a post-purchase message.
     */
    public static function classify(\WC_Order $order): string
    {
        try {
            if ($order->get_id() < 1) {
                return self::UNCERTAIN;
            }
            $metadata = self::metadata($order);
            if ($metadata === null || self::has_refund_or_dispute($order, $metadata)) {
                return self::UNCERTAIN;
            }

            if (!self::has_paymongo_evidence($order, $metadata)) {
                return self::ordinary_state($order);
            }
            if (!self::valid_paymongo_metadata($metadata)
                || !self::valid_attempts($metadata)
                || self::has_recovery_signal($metadata)
                || Webhook::has_pending_reviews($order->get_id())
                || Gateway::has_inconsistent_provider_payment_state($order)) {
                return self::UNCERTAIN;
            }
            if (self::has_pending_provider_request($metadata)) {
                return self::UNCERTAIN;
            }
            if (Gateway::has_outstanding_attempts($order)) {
                return self::UNPAID;
            }
            if (!$order->is_paid()) {
                return self::UNPAID;
            }

            return self::settlement_matches($order) ? self::SETTLED : self::UNCERTAIN;
        } catch (\Throwable $exception) {
            return self::UNCERTAIN;
        }
    }

    /** @return array<string,mixed>|null */
    private static function metadata(\WC_Order $order): ?array
    {
        if (!method_exists($order, 'get_meta_data')) {
            return null;
        }
        $metadata = $order->get_meta_data();
        if (!is_array($metadata)) {
            return null;
        }
        $values = array();
        foreach ($metadata as $meta) {
            if (!is_object($meta) || !method_exists($meta, 'get_data')) {
                return null;
            }
            $data = $meta->get_data();
            $key = is_array($data) ? ($data['key'] ?? null) : null;
            if (!is_string($key) || $key === '') {
                return null;
            }
            if (str_starts_with($key, '_bactive_paymongo_')
                && array_key_exists($key, $values)) {
                return null;
            }
            $values[$key] = $data['value'] ?? null;
        }
        return $values;
    }

    /** @param array<string,mixed> $metadata */
    private static function has_paymongo_evidence(\WC_Order $order, array $metadata): bool
    {
        if ($order->get_payment_method() === GATEWAY_ID
            || preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', (string) $order->get_transaction_id())) {
            return true;
        }
        foreach (array_keys($metadata) as $key) {
            if (str_starts_with($key, '_bactive_paymongo_') || str_starts_with($key, 'paymongo_')) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $metadata */
    private static function has_refund_or_dispute(\WC_Order $order, array $metadata): bool
    {
        if ($order->get_status() === 'refunded' || !method_exists($order, 'get_refunds')
            || !method_exists($order, 'get_total_refunded')) {
            return true;
        }
        $refunds = $order->get_refunds();
        if (!is_array($refunds) || $refunds !== array()) {
            return true;
        }
        $total_refunded = $order->get_total_refunded();
        if (!is_numeric($total_refunded) || !is_finite((float) $total_refunded)
            || (float) $total_refunded !== 0.0) {
            return true;
        }
        foreach (array_keys($metadata) as $key) {
            if (str_starts_with($key, '_bactive_paymongo_')
                && preg_match('/(?:refund|dispute|chargeback|reversal)/i', $key)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $metadata */
    private static function valid_attempts(array $metadata): bool
    {
        if (!array_key_exists(self::ATTEMPTS_META, $metadata)
            || !is_array($metadata[self::ATTEMPTS_META])
            || !array_is_list($metadata[self::ATTEMPTS_META])
            || count($metadata[self::ATTEMPTS_META]) < 1
            || count($metadata[self::ATTEMPTS_META]) > 10) {
            return false;
        }
        foreach ($metadata[self::ATTEMPTS_META] as $attempt) {
            if (!self::valid_attempt($attempt)) {
                return false;
            }
        }
        return true;
    }

    /** @param mixed $attempt */
    private static function valid_attempt(mixed $attempt): bool
    {
        if (!is_array($attempt)
            || !is_int($attempt['generation'] ?? null)
            || $attempt['generation'] < 1
            || !is_string($attempt['fingerprint'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $attempt['fingerprint'])
            || !is_string($attempt['mode'] ?? null)
            || !in_array($attempt['mode'], array('test', 'live'), true)
            || !is_string($attempt['reference'] ?? null)
            || !preg_match('/^BA-[1-9][0-9]*-[1-9][0-9]*$/D', $attempt['reference'])
            || !is_string($attempt['correlation_id'] ?? null)
            || !preg_match('/^[a-f0-9]{48}$/D', $attempt['correlation_id'])
            || !is_string($attempt['idempotency_key'] ?? null)
            || !preg_match('/^bactive-checkout-[A-Za-z0-9_-]{1,180}$/D', $attempt['idempotency_key'])
            || !self::positive_timestamp($attempt['created_at'] ?? null)
            || !is_string($attempt['session_id'] ?? null)
            || !self::valid_provider_id((string) $attempt['session_id'], 'cs_')) {
            return false;
        }
        foreach (array('request_started_at', 'request_rejected_at', 'request_aborted_at', 'authorized_at', 'expired_at', 'paid_at', 'persistence_failed_at') as $key) {
            if (array_key_exists($key, $attempt) && !self::positive_timestamp($attempt[$key])) {
                return false;
            }
        }
        if (array_key_exists('config_generation', $attempt)
            && (!is_int($attempt['config_generation']) || $attempt['config_generation'] < 0)) {
            return false;
        }
        if (array_key_exists('request_pending', $attempt) && !is_bool($attempt['request_pending'])) {
            return false;
        }
        if (array_key_exists('checkout_url', $attempt)
            && (!is_string($attempt['checkout_url']) || strlen($attempt['checkout_url']) > 2048)) {
            return false;
        }
        foreach (array('payment_id' => 'pay_', 'paid_event_id' => 'evt_') as $key => $prefix) {
            if (array_key_exists($key, $attempt)
                && (!is_string($attempt[$key]) || !self::valid_provider_id($attempt[$key], $prefix))) {
                return false;
            }
        }
        if (array_key_exists('reconciliation_payment_ids', $attempt)) {
            if (!is_array($attempt['reconciliation_payment_ids'])
                || !array_is_list($attempt['reconciliation_payment_ids'])
                || count($attempt['reconciliation_payment_ids']) > 10) {
                return false;
            }
            foreach ($attempt['reconciliation_payment_ids'] as $payment_id) {
                if (!is_string($payment_id) || !self::valid_provider_id($payment_id, 'pay_')) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @param array<string,mixed> $metadata */
    private static function valid_paymongo_metadata(array $metadata): bool
    {
        foreach ($metadata as $key => $value) {
            if (str_starts_with($key, '_bactive_paymongo_')
                && $key !== self::ATTEMPTS_META
                && !is_scalar($value)
                && $value !== null) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $metadata */
    private static function has_recovery_signal(array $metadata): bool
    {
        foreach (self::RECOVERY_META as $key) {
            if (array_key_exists($key, $metadata)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $metadata */
    private static function has_pending_provider_request(array $metadata): bool
    {
        foreach ($metadata[self::ATTEMPTS_META] as $attempt) {
            if (($attempt['request_pending'] ?? false) === true) {
                return true;
            }
        }
        return false;
    }

    private static function ordinary_state(\WC_Order $order): string
    {
        $paid_at = $order->get_date_paid('edit');
        if ($order->is_paid()) {
            return self::valid_timestamp($paid_at) ? self::SETTLED : self::UNCERTAIN;
        }
        return $paid_at === null ? self::UNPAID : self::UNCERTAIN;
    }

    private static function settlement_matches(\WC_Order $order): bool
    {
        return $order->get_payment_method() === GATEWAY_ID
            && preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', (string) $order->get_transaction_id())
            && self::valid_timestamp($order->get_date_paid('edit'));
    }

    private static function valid_timestamp(mixed $date): bool
    {
        return is_object($date) && method_exists($date, 'getTimestamp')
            && is_int($date->getTimestamp()) && $date->getTimestamp() > 0;
    }

    private static function positive_timestamp(mixed $value): bool
    {
        return is_int($value) && $value > 0;
    }

    private static function valid_provider_id(string $value, string $prefix): bool
    {
        return $value === '' || preg_match('/^' . preg_quote($prefix, '/') . '[A-Za-z0-9_-]{3,128}$/D', $value) === 1;
    }
}
