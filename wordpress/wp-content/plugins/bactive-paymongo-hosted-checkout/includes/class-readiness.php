<?php

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

final class Readiness
{
    private const CACHE_SECONDS = 900;

    /** @return true|\WP_Error */
    public static function verify_and_provision(Gateway $gateway, bool $live)
    {
        $key = Secrets::api_key($live, $gateway);
        $prefix = $live ? 'sk_live_' : 'sk_test_';
        if (!str_starts_with($key, $prefix)) {
            return new \WP_Error('paymongo_key_missing', 'The active PayMongo secret key is missing or has the wrong mode.');
        }

        $client = new Api_Client($key);
        $capabilities = $client->capabilities();
        if (is_wp_error($capabilities)) {
            self::clear($live);
            return $capabilities;
        }
        if ($live && !self::has_required_capabilities($capabilities['methods'])) {
            self::clear($live);
            return new \WP_Error(
                'paymongo_methods_inactive',
                'PayMongo did not confirm every required payment method as active.'
            );
        }

        $webhook = self::find_webhook($client, $live);
        if (is_wp_error($webhook)) {
            self::clear($live);
            return $webhook;
        }

        if ($webhook === null) {
            $idempotency_key = self::webhook_idempotency_key($live);
            $created = $client->create_webhook(self::endpoint_url($live), $idempotency_key);
            if (is_wp_error($created)) {
                self::clear($live);
                return $created;
            }
            $webhook = self::parse_webhook($created['data'] ?? null, $live);
            if ($webhook === null) {
                self::clear($live);
                return new \WP_Error('paymongo_webhook_create_shape', 'PayMongo returned an unexpected webhook response.');
            }
            if (!self::store_webhook_secret_if_present($webhook, $live)) {
                self::clear($live);
                return new \WP_Error(
                    'paymongo_webhook_secret_missing',
                    'The PayMongo webhook was created, but its signing secret could not be secured.'
                );
            }

            $webhook = self::find_webhook($client, $live);
            if (is_wp_error($webhook) || $webhook === null) {
                self::clear($live);
                return is_wp_error($webhook)
                    ? $webhook
                    : new \WP_Error('paymongo_webhook_readback_failed', 'The PayMongo webhook could not be verified after creation.');
            }
        }

        if (!self::store_webhook_secret_if_present($webhook, $live) || Secrets::webhook_secret($live) === '') {
            self::clear($live);
            return new \WP_Error(
                'paymongo_webhook_secret_missing',
                'The PayMongo webhook exists, but its signing secret could not be secured.'
            );
        }

        if (!self::store_state($live, $key, $capabilities['methods'], $webhook)) {
            self::clear($live);
            return new \WP_Error('paymongo_readiness_persist_failed', 'PayMongo readiness could not be persisted and read back exactly.');
        }
        return true;
    }

    public static function is_ready(Gateway $gateway, bool $live, bool $force_refresh = false): bool
    {
        $key = Secrets::api_key($live, $gateway);
        $state = get_option(self::state_option($live), array());
        if ($key === '' || !is_array($state) || Secrets::webhook_secret($live) === '') {
            return false;
        }
        if (!hash_equals((string) ($state['key_fingerprint'] ?? ''), Secrets::fingerprint($key))) {
            return false;
        }

        $verified_at = (int) ($state['verified_at'] ?? 0);
        if (!$force_refresh && $verified_at > 0 && (time() - $verified_at) <= self::CACHE_SECONDS) {
            return self::state_is_valid($state, $live);
        }

        return self::refresh($gateway, $live);
    }

    public static function endpoint_url(bool $live): string
    {
        $route = $live ? 'bactive_paymongo_live' : 'bactive_paymongo_test';
        return add_query_arg('wc-api', $route, home_url('/'));
    }

    /** @return array<string,mixed> */
    public static function state(bool $live): array
    {
        $state = get_option(self::state_option($live), array());
        return is_array($state) ? $state : array();
    }

    private static function refresh(Gateway $gateway, bool $live): bool
    {
        $key = Secrets::api_key($live, $gateway);
        $prefix = $live ? 'sk_live_' : 'sk_test_';
        if (!str_starts_with($key, $prefix)) {
            self::clear($live);
            return false;
        }

        $client = new Api_Client($key);
        $capabilities = $client->capabilities();
        if (is_wp_error($capabilities)
            || ($live && !self::has_required_capabilities($capabilities['methods']))) {
            self::clear($live);
            return false;
        }

        $webhook = self::find_webhook($client, $live);
        if (is_wp_error($webhook) || $webhook === null) {
            self::clear($live);
            return false;
        }
        if (!self::store_webhook_secret_if_present($webhook, $live) || Secrets::webhook_secret($live) === '') {
            self::clear($live);
            return false;
        }

        return self::store_state($live, $key, $capabilities['methods'], $webhook);
    }

    /** @return array<string,mixed>|null|\WP_Error */
    private static function find_webhook(Api_Client $client, bool $live)
    {
        $response = $client->list_webhooks(self::endpoint_url($live));
        if (is_wp_error($response)) {
            return $response;
        }
        if (!isset($response['data']) || !is_array($response['data'])) {
            return new \WP_Error('paymongo_webhooks_shape', 'PayMongo returned an unexpected webhooks response.');
        }

        $matches = array();
        foreach ($response['data'] as $candidate) {
            $parsed = self::parse_webhook($candidate, $live);
            if ($parsed !== null && hash_equals(self::endpoint_url($live), $parsed['url'])) {
                $matches[] = $parsed;
            }
        }

        if (count($matches) > 1) {
            return new \WP_Error('paymongo_webhook_duplicate', 'Multiple PayMongo webhooks target this mode-specific URL.');
        }
        if ($matches === array()) {
            return null;
        }

        $webhook = $matches[0];
        if ($webhook['status'] !== 'enabled'
            || $webhook['events'] !== array('checkout_session.payment.paid')) {
            return new \WP_Error('paymongo_webhook_not_ready', 'The PayMongo webhook is disabled or missing the required event.');
        }

        return $webhook;
    }

    /** @return array<string,mixed>|null */
    private static function parse_webhook($candidate, bool $live): ?array
    {
        if (!is_array($candidate) || !is_array($candidate['attributes'] ?? null)) {
            return null;
        }

        $attributes = $candidate['attributes'];
        $id = (string) ($candidate['id'] ?? '');
        $url = (string) ($attributes['url'] ?? '');
        $status = (string) ($attributes['status'] ?? '');
        $events = $attributes['events'] ?? null;
        if (!preg_match('/^hook_[A-Za-z0-9_-]+$/D', $id)
            || !is_bool($attributes['livemode'] ?? null)
            || $attributes['livemode'] !== $live
            || !is_array($events)
            || !wp_http_validate_url($url)
            || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        return array(
            'id' => $id,
            'url' => $url,
            'status' => $status,
            'events' => array_values(array_filter($events, 'is_string')),
            'secret_key' => is_string($attributes['secret_key'] ?? null) ? $attributes['secret_key'] : '',
        );
    }

    private static function store_webhook_secret_if_present(array $webhook, bool $live): bool
    {
        if ($webhook['secret_key'] === '') {
            $secret = Secrets::webhook_secret($live);
            return $secret !== '' && self::secret_is_bound_to_webhook($webhook['id'], $secret, $live);
        }
        if (!Secrets::store_webhook_secret($live, $webhook['secret_key'])
            || !hash_equals($webhook['secret_key'], Secrets::webhook_secret($live))) {
            return false;
        }
        return self::store_secret_binding($webhook['id'], $webhook['secret_key'], $live);
    }

    /** @param array<int,string> $methods */
    private static function has_required_capabilities(array $methods): bool
    {
        $methods = array_map('strtolower', $methods);
        $required_groups = array(
            array('qrph', 'qr_ph'),
            array('paymaya', 'maya'),
            array('shopee_pay', 'shopeepay'),
            array('dob', 'dob_bpi', 'bpi'),
            array('dob_ubp', 'ubp', 'unionbank'),
        );

        foreach ($required_groups as $group) {
            if (array_intersect($group, $methods) === array()) {
                return false;
            }
        }
        return true;
    }

    /** @param array<int,string> $methods */
    private static function store_state(bool $live, string $key, array $methods, array $webhook): bool
    {
        $previous = get_option(self::state_option($live), array());
        $webhook_secret = Secrets::webhook_secret($live);
        if ($webhook_secret === ''
            || !self::secret_is_bound_to_webhook($webhook['id'], $webhook_secret, $live)) {
            return false;
        }
        $next = array(
            'verified_at' => time(),
            'key_fingerprint' => Secrets::fingerprint($key),
            'webhook_secret_fingerprint' => Secrets::fingerprint($webhook_secret),
            'capabilities' => array_values($methods),
            'webhook_id' => $webhook['id'],
            'endpoint_url' => $webhook['url'],
            'webhook_status' => $webhook['status'],
            'webhook_events' => $webhook['events'],
            'livemode' => $live,
        );
        update_option(
            self::state_option($live),
            $next,
            false
        );
        $readback = get_option(self::state_option($live), array());
        if (!is_array($readback) || $readback !== $next) {
            return false;
        }
        if ((!is_array($previous)
                || !self::state_is_valid($previous, $live)
                || ($previous['key_fingerprint'] ?? '') !== $next['key_fingerprint']
                || ($previous['webhook_id'] ?? '') !== $next['webhook_id'])
            && function_exists('do_action')) {
            do_action('bactive_paymongo_availability_changed');
        }
        return true;
    }

    private static function state_is_valid(array $state, bool $live): bool
    {
        $secret = Secrets::webhook_secret($live);
        return ($state['livemode'] ?? null) === $live
            && ($state['webhook_status'] ?? '') === 'enabled'
            && ($state['webhook_events'] ?? null) === array('checkout_session.payment.paid')
            && hash_equals(self::endpoint_url($live), (string) ($state['endpoint_url'] ?? ''))
            && $secret !== ''
            && hash_equals(
                (string) ($state['webhook_secret_fingerprint'] ?? ''),
                Secrets::fingerprint($secret)
            )
            && self::secret_is_bound_to_webhook((string) ($state['webhook_id'] ?? ''), $secret, $live)
            && is_array($state['capabilities'] ?? null)
            && (!$live || self::has_required_capabilities($state['capabilities']));
    }

    private static function store_secret_binding(string $webhook_id, string $secret, bool $live): bool
    {
        if (!preg_match('/^hook_[A-Za-z0-9_-]+$/D', $webhook_id) || $secret === '') {
            return false;
        }
        $record = array(
            'webhook_id' => $webhook_id,
            'secret_fingerprint' => Secrets::fingerprint($secret),
            'livemode' => $live,
            'recorded_at' => time(),
        );
        update_option(self::binding_option($live), $record, false);
        $readback = get_option(self::binding_option($live), array());
        return is_array($readback)
            && (string) ($readback['webhook_id'] ?? '') === $record['webhook_id']
            && (string) ($readback['secret_fingerprint'] ?? '') === $record['secret_fingerprint']
            && ($readback['livemode'] ?? null) === $live
            && (int) ($readback['recorded_at'] ?? 0) === $record['recorded_at'];
    }

    private static function secret_is_bound_to_webhook(string $webhook_id, string $secret, bool $live): bool
    {
        $binding = get_option(self::binding_option($live), array());
        return preg_match('/^hook_[A-Za-z0-9_-]+$/D', $webhook_id) === 1
            && $secret !== ''
            && is_array($binding)
            && ($binding['livemode'] ?? null) === $live
            && hash_equals($webhook_id, (string) ($binding['webhook_id'] ?? ''))
            && hash_equals(
                Secrets::fingerprint($secret),
                (string) ($binding['secret_fingerprint'] ?? '')
            );
    }

    private static function binding_option(bool $live): string
    {
        return 'bactive_paymongo_' . ($live ? 'live' : 'test') . '_webhook_secret_binding';
    }

    private static function webhook_idempotency_key(bool $live): string
    {
        $installation_id = (string) get_option('bactive_paymongo_installation_id', '');
        if ($installation_id === '') {
            $installation_id = wp_generate_uuid4();
            add_option('bactive_paymongo_installation_id', $installation_id, '', false);
            $installation_id = (string) get_option('bactive_paymongo_installation_id', $installation_id);
        }

        return 'bactive-webhook-' . ($live ? 'live-' : 'test-') . $installation_id;
    }

    private static function clear(bool $live): void
    {
        $option = self::state_option($live);
        $had_state = get_option($option, false) !== false;
        delete_option($option);
        if ($had_state && function_exists('do_action')) {
            do_action('bactive_paymongo_availability_changed');
        }
    }

    private static function state_option(bool $live): string
    {
        return $live ? 'bactive_paymongo_readiness_live' : 'bactive_paymongo_readiness_test';
    }
}
