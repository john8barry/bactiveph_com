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

        $payments = $session_attributes['payments'] ?? null;
        if (!is_array($payments)) {
            return self::failure('payments_missing');
        }

        $qualifying = array();
        foreach ($payments as $payment) {
            if (!is_array($payment) || ($payment['type'] ?? 'payment') !== 'payment') {
                continue;
            }

            $payment_id = (string) ($payment['id'] ?? '');
            $attributes = $payment['attributes'] ?? null;
            if (!self::valid_id($payment_id, 'pay_') || !is_array($attributes) || ($attributes['status'] ?? '') !== 'paid') {
                continue;
            }
            if (($attributes['currency'] ?? '') !== 'PHP' || !is_int($attributes['amount'] ?? null)) {
                continue;
            }
            if (array_key_exists('livemode', $attributes)
                && (!is_bool($attributes['livemode']) || $attributes['livemode'] !== $context['live'])) {
                continue;
            }

            $method = self::payment_method($attributes['source'] ?? null);
            if ($method === null) {
                continue;
            }

            $qualifying[] = array(
                'payment_id' => $payment_id,
                'amount' => $attributes['amount'],
                'method' => $method['method'],
                'provider' => $method['provider'],
            );
        }

        if (count($qualifying) !== 1) {
            return self::failure('paid_payment_count_invalid');
        }

        $payment = $qualifying[0];
        if ($payment['amount'] !== $context['amount']) {
            return self::failure('amount_mismatch');
        }

        return array(
            'ok' => true,
            'code' => 'ok',
            'event_id' => $event_id,
            'session_id' => $session_id,
            'payment_id' => $payment['payment_id'],
            'method' => $payment['method'],
            'provider' => $payment['provider'],
            'amount' => $payment['amount'],
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
            if (str_contains($candidate, 'union')) {
                return 'ubp';
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
