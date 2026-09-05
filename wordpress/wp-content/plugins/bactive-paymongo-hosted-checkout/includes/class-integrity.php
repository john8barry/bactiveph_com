<?php

namespace BActive\PayMongo;

defined('ABSPATH') || defined('BACTIVE_PAYMONGO_TESTING') || exit;

final class Integrity
{
    public const CHECKOUT_METHODS = array('qrph', 'paymaya', 'shopee_pay', 'dob', 'dob_ubp');

    private const ID_PATTERN = '/^[A-Za-z0-9_-]{3,128}$/D';

    /**
     * Normalize both the current Hosted Checkout envelope and the legacy
     * JSON:API event envelope into one strict internal representation.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public static function normalize_event(array $payload, string $raw_payload): ?array
    {
        $event = $payload['data'] ?? null;
        if (!is_array($event)) {
            return null;
        }

        $event_type = '';
        $livemode = null;
        $session = null;
        $event_id = (string) ($event['id'] ?? '');

        if (($event['type'] ?? '') === 'checkout_session.payment.paid') {
            if (isset($payload['event_type']) && $payload['event_type'] !== 'send.webhook') {
                return null;
            }
            if (isset($event['resource']) && $event['resource'] !== 'checkout_session') {
                return null;
            }
            $event_type = 'checkout_session.payment.paid';
            $livemode = $event['livemode'] ?? null;
            $session = $event['data'] ?? null;
        } else {
            $attributes = $event['attributes'] ?? null;
            if (!is_array($attributes) || ($attributes['type'] ?? '') !== 'checkout_session.payment.paid') {
                return null;
            }
            $event_type = 'checkout_session.payment.paid';
            $livemode = $attributes['livemode'] ?? null;
            $session = $attributes['data'] ?? null;
        }

        if (!is_bool($livemode) || !is_array($session)) {
            return null;
        }
        if (!self::valid_id($event_id, 'evt_')) {
            $event_id = 'evt_' . substr(hash('sha256', $raw_payload), 0, 48);
        }

        return array(
            'data' => array(
                'id' => $event_id,
                'type' => 'event',
                'attributes' => array(
                    'type' => $event_type,
                    'livemode' => $livemode,
                    'data' => $session,
                ),
            ),
        );
    }

    /**
     * Convert a non-negative decimal amount to integer centavos without floats.
     */
    public static function amount_to_minor(string $amount): ?int
    {
        $amount = trim($amount);
        if (!preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/D', $amount, $matches)) {
            return null;
        }

        $major = $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0');
        if (strlen($major) > 15) {
            return null;
        }

        $minor = ((int) $major * 100) + (int) $fraction;
        return $minor >= 0 ? $minor : null;
    }

    /**
     * Confirm an API response is the exact expired Checkout Session expected.
     *
     * @param array<string,mixed> $response
     */
    public static function checkout_session_is_expired(array $response, string $session_id, bool $live): bool
    {
        return self::checkout_session_status($response, $session_id, $live) === 'expired';
    }

    /** @param array<string,mixed> $response */
    public static function checkout_session_status(array $response, string $session_id, bool $live): ?string
    {
        $data = $response['data'] ?? null;
        $attributes = is_array($data) ? ($data['attributes'] ?? null) : null;
        if (!is_array($data)
            || !is_array($attributes)
            || ($data['type'] ?? '') !== 'checkout_session'
            || !self::valid_id($session_id, 'cs_')
            || !hash_equals($session_id, (string) ($data['id'] ?? ''))
            || !is_bool($attributes['livemode'] ?? null)) {
            return null;
        }

        $status = (string) ($attributes['status'] ?? '');
        if ($attributes['livemode'] !== $live || !in_array($status, array('active', 'expired'), true)) {
            return null;
        }

        return $status;
    }

    /**
     * Return the strictly identified paid payments on an authenticated
     * Checkout Session readback. A null result means the response is not safe
     * to use for a payment or expiration decision.
     *
     * @param array<string,mixed> $response
     * @return array<int,string>|null
     */
    public static function checkout_session_paid_payment_ids(
        array $response,
        string $session_id,
        bool $live
    ): ?array {
        $state = self::checkout_session_payment_state($response, $session_id, $live);
        return is_array($state) ? $state['paid'] : null;
    }

    /**
     * Validate every Payment identity, status, and mode before any caller can
     * classify a Checkout Session as safely unpaid.
     *
     * @param array<string,mixed> $response
     * @return array{paid:array<int,string>,pending:array<int,string>,failed:array<int,string>}|null
     */
    public static function checkout_session_payment_state(
        array $response,
        string $session_id,
        bool $live
    ): ?array {
        if (self::checkout_session_status($response, $session_id, $live) === null) {
            return null;
        }

        $data = $response['data'] ?? null;
        $attributes = is_array($data) ? ($data['attributes'] ?? null) : null;
        $payments = is_array($attributes) ? ($attributes['payments'] ?? null) : null;
        if (!is_array($payments) || !array_is_list($payments)) {
            return null;
        }

        $state = array('paid' => array(), 'pending' => array(), 'failed' => array());
        $seen = array();
        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                return null;
            }
            $payment_attributes = $payment['attributes'] ?? null;
            if (!is_array($payment_attributes)) {
                return null;
            }

            $payment_id = (string) ($payment['id'] ?? '');
            $status = $payment_attributes['status'] ?? null;
            if (($payment['type'] ?? '') !== 'payment'
                || !self::valid_id($payment_id, 'pay_')
                || isset($seen[$payment_id])
                || !is_string($status)
                || !in_array($status, array('pending', 'failed', 'paid'), true)
                || !is_bool($payment_attributes['livemode'] ?? null)
                || $payment_attributes['livemode'] !== $live) {
                return null;
            }
            $seen[$payment_id] = true;
            $state[$status][] = $payment_id;
        }

        return $state;
    }

    /**
     * Decide whether a verified paid event may change the WooCommerce order.
     */
    public static function paid_event_disposition(
        string $current_gateway,
        string $expected_gateway,
        bool $is_paid,
        bool $needs_payment,
        bool $closed_status,
        string $existing_transaction,
        string $incoming_transaction,
        bool $attempt_expired
    ): string {
        if ($current_gateway === $expected_gateway
            && $is_paid
            && $existing_transaction !== ''
            && hash_equals($incoming_transaction, $existing_transaction)) {
            return 'duplicate';
        }

        if ($current_gateway !== $expected_gateway
            || ($existing_transaction !== '' && !hash_equals($incoming_transaction, $existing_transaction))
            || $is_paid
            || !$needs_payment
            || $closed_status
            || $attempt_expired) {
            return 'quarantine';
        }

        return 'apply';
    }

    /**
     * Require both WooCommerce's return value and a persisted exact readback.
     */
    public static function payment_completion_verified(
        $completion_result,
        bool $is_paid,
        string $expected_transaction,
        string $actual_transaction
    ): bool {
        return $completion_result === true
            && $is_paid
            && $expected_transaction !== ''
            && hash_equals($expected_transaction, $actual_transaction);
    }

    /**
     * Preserve B Active's existing COD policy when restarting a PayMongo order.
     */
    public static function cod_transition_is_valid(int $product_total, int $cod_fee): bool
    {
        return $product_total >= 0
            && $product_total <= 250000
            && $cod_fee === 5000;
    }

    /**
     * Validate a PayMongo signature and reject stale/future replays.
     *
     * @return array{ok:bool,code:string,timestamp?:int}
     */
    public static function verify_signature(
        string $payload,
        string $header,
        string $secret,
        bool $live,
        ?int $now = null,
        int $tolerance = 300
    ): array {
        if ($header === '' || $secret === '') {
            return self::failure('signature_missing');
        }

        $parts = array();
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2 || isset($parts[$pair[0]])) {
                return self::failure('signature_malformed');
            }
            $parts[$pair[0]] = trim($pair[1]);
        }

        if (!isset($parts['t']) || !preg_match('/^[0-9]{10,13}$/D', $parts['t'])) {
            return self::failure('signature_timestamp_invalid');
        }

        $timestamp = (int) $parts['t'];
        $now = $now ?? time();
        if (abs($now - $timestamp) > $tolerance) {
            return self::failure('signature_timestamp_outside_tolerance');
        }

        $signature_key = $live ? 'li' : 'te';
        $provided = $parts[$signature_key] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/Di', $provided)) {
            return self::failure('signature_value_invalid');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        if (!hash_equals($expected, strtolower($provided))) {
            return self::failure('signature_mismatch');
        }

        return array('ok' => true, 'code' => 'ok', 'timestamp' => $timestamp);
    }

    /**
     * Validate the immutable payment facts in a checkout-session webhook.
     *
     * @param array<string,mixed> $payload
     * @param array{live:bool,order_id:int,amount:int,reference:string,correlation:string,session_ids:array<int,string>} $context
     * @return array{ok:bool,code:string,event_id?:string,session_id?:string,payment_id?:string,method?:string,provider?:string,amount?:int}
     */
    public static function validate_paid_event(array $payload, array $context): array
    {
        $event = $payload['data'] ?? null;
        $event_attributes = is_array($event) ? ($event['attributes'] ?? null) : null;
        if (!is_array($event) || !is_array($event_attributes)) {
            return self::failure('event_shape_invalid');
        }

        $event_id = (string) ($event['id'] ?? '');
        if (($event['type'] ?? '') !== 'event' || !self::valid_id($event_id, 'evt_')) {
            return self::failure('event_identity_invalid');
        }
        if (($event_attributes['type'] ?? '') !== 'checkout_session.payment.paid') {
            return self::failure('event_type_invalid');
        }
        if (!array_key_exists('livemode', $event_attributes)
            || !is_bool($event_attributes['livemode'])
            || $event_attributes['livemode'] !== $context['live']) {
            return self::failure('event_mode_mismatch');
        }

        $session = $event_attributes['data'] ?? null;
        $session_attributes = is_array($session) ? ($session['attributes'] ?? null) : null;
        if (!is_array($session) || !is_array($session_attributes) || ($session['type'] ?? '') !== 'checkout_session') {
            return self::failure('session_shape_invalid');
        }

        $session_id = (string) ($session['id'] ?? '');
        if (!self::valid_id($session_id, 'cs_') || !in_array($session_id, $context['session_ids'], true)) {
            return self::failure('session_not_authorized');
        }
        if (array_key_exists('livemode', $session_attributes)
            && (!is_bool($session_attributes['livemode']) || $session_attributes['livemode'] !== $context['live'])) {
            return self::failure('session_mode_mismatch');
        }
        if (!hash_equals($context['reference'], (string) ($session_attributes['reference_number'] ?? ''))) {
            return self::failure('reference_mismatch');
        }

        $metadata = $session_attributes['metadata'] ?? null;
        if (!is_array($metadata)
            || !hash_equals((string) $context['order_id'], (string) ($metadata['order_id'] ?? ''))
            || !hash_equals($context['correlation'], (string) ($metadata['correlation_id'] ?? ''))
            || ($metadata['integration'] ?? '') !== 'bactive-paymongo') {
            return self::failure('metadata_mismatch');
        }

        $payment_state = self::checkout_session_payment_state(
            array('data' => $session),
            $session_id,
            (bool) $context['live']
        );
        if ($payment_state === null) {
            return self::failure('payment_collection_invalid');
        }
        if ($payment_state['pending'] !== array()) {
            return self::failure('payment_pending_conflict');
        }
        if (count($payment_state['paid']) !== 1) {
            return self::failure('paid_payment_count_invalid');
        }

        $payment_id = $payment_state['paid'][0];
        $payment = null;
        foreach ((array) ($session_attributes['payments'] ?? array()) as $candidate) {
            if (is_array($candidate) && (string) ($candidate['id'] ?? '') === $payment_id) {
                $payment = $candidate;
                break;
            }
        }
        $attributes = is_array($payment) ? ($payment['attributes'] ?? null) : null;
        if (!is_array($attributes)) {
            return self::failure('payment_shape_invalid');
        }
        if (($attributes['currency'] ?? '') !== 'PHP' || !is_int($attributes['amount'] ?? null)) {
            return self::failure('payment_amount_invalid');
        }
        if (!is_bool($attributes['livemode'] ?? null)
            || $attributes['livemode'] !== $context['live']) {
            return self::failure('payment_mode_mismatch');
        }
        $method = self::payment_method($attributes['source'] ?? null);
        if ($method === null) {
            return self::failure('payment_method_invalid');
        }
        if ($attributes['amount'] !== $context['amount']) {
            return self::failure('amount_mismatch');
        }

        return array(
            'ok' => true,
            'code' => 'ok',
            'event_id' => $event_id,
            'session_id' => $session_id,
            'payment_id' => $payment_id,
            'method' => $method['method'],
            'provider' => $method['provider'],
            'amount' => $attributes['amount'],
        );
    }

    /** @return array{method:string,provider:string}|null */
    private static function payment_method($source): ?array
    {
        if (!is_array($source)) {
            return null;
        }

        $type = strtolower((string) ($source['type'] ?? ''));
        if ($type === 'qrph') {
            return array('method' => 'qrph', 'provider' => '');
        }
        if (in_array($type, array('paymaya', 'maya'), true)) {
            return array('method' => 'paymaya', 'provider' => '');
        }
        if (in_array($type, array('shopee_pay', 'shopeepay'), true)) {
            return array('method' => 'shopee_pay', 'provider' => '');
        }

        if (in_array($type, array('dob', 'dob_ubp'), true)) {
            $provider = self::provider_code($source);
            if ($type === 'dob_ubp') {
                $provider = $provider === '' ? 'ubp' : $provider;
                if ($provider !== 'ubp') {
                    return null;
                }
            }
            if (!in_array($provider, array('bpi', 'ubp'), true)) {
                return null;
            }
            return array('method' => 'dob', 'provider' => $provider);
        }

        return null;
    }

    private static function provider_code(array $source): string
    {
        $candidates = array(
            $source['bank_code'] ?? null,
            is_array($source['details'] ?? null) ? ($source['details']['bank_code'] ?? null) : null,
            is_string($source['provider'] ?? null) ? $source['provider'] : null,
        );

        if (is_array($source['provider'] ?? null)) {
            foreach (array('bank_code', 'code', 'name', 'id') as $key) {
                $candidates[] = $source['provider'][$key] ?? null;
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = strtolower((string) $candidate);
            if (in_array($candidate, array('bpi', 'ubp'), true)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function valid_id(string $id, string $prefix): bool
    {
        return str_starts_with($id, $prefix) && (bool) preg_match(self::ID_PATTERN, $id);
    }

    /** @return array{ok:false,code:string} */
    private static function failure(string $code): array
    {
        return array('ok' => false, 'code' => $code);
    }
}
