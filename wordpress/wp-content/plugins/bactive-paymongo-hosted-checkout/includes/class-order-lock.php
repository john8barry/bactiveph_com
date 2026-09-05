<?php

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

/**
 * A shared, request-reentrant lock for every mutation of one payment order.
 */
final class Order_Lock
{
    private const PREFIX = 'bactive_paymongo_order_lock_';
    private const CHECKOUT_PREFIX = 'bactive_paymongo_checkout_lock_';
    private const SETTINGS_OPTION = 'bactive_paymongo_settings_write_lock';
    private const TTL = 900;

    /** @var array<int,string> */
    private static array $held = array();

    private static string $checkout_option = '';
    private static string $checkout_token = '';

    private static string $settings_token = '';
    private static string $settings_fingerprint = '';

    public static function acquire(int $order_id): bool
    {
        if ($order_id < 1) {
            return false;
        }
        if (isset(self::$held[$order_id])) {
            return true;
        }

        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Throwable $error) {
            return false;
        }

        $option = self::option_name($order_id);
        $record = array('token' => $token, 'acquired_at' => time());
        if (!add_option($option, $record, '', false)) {
            $observed = self::read_database_record($option);
            $existing = is_array($observed) ? $observed['record'] : null;
            $acquired_at = is_array($existing) ? (int) ($existing['acquired_at'] ?? 0) : 0;
            if ($acquired_at < 1 || (time() - $acquired_at) <= self::TTL) {
                return false;
            }

            // Replace only the exact stale value that was observed. An
            // unconditional delete lets two contenders erase one another's
            // newly acquired lock and both enter the critical section.
            if (!self::compare_and_swap($option, (string) $observed['raw'], $record)) {
                return false;
            }
        }

        self::$held[$order_id] = $token;
        return true;
    }

    public static function held_by_request(int $order_id): bool
    {
        return isset(self::$held[$order_id]);
    }

    /**
     * Extend a long-running critical section without changing its fencing
     * token. Callers must stop mutating the order if renewal fails.
     */
    public static function renew(int $order_id): bool
    {
        $token = self::$held[$order_id] ?? '';
        if ($token === '') {
            return false;
        }

        $option = self::option_name($order_id);
        $observed = self::read_database_record($option);
        if (!is_array($observed)
            || !is_array($observed['record'])
            || !is_string($observed['record']['token'] ?? null)
            || !hash_equals($token, $observed['record']['token'])) {
            // The database fence is authoritative. Never leave a stale token
            // in request memory after ownership has been lost.
            unset(self::$held[$order_id]);
            return false;
        }

        $renewed = self::compare_and_swap(
            $option,
            (string) $observed['raw'],
            array('token' => $token, 'acquired_at' => time())
        );
        if (!$renewed) {
            unset(self::$held[$order_id]);
        }
        return $renewed;
    }

    public static function release(int $order_id): void
    {
        $token = self::$held[$order_id] ?? '';
        if ($token === '') {
            return;
        }

        $option = self::option_name($order_id);
        $observed = self::read_database_record($option);
        if (is_array($observed)
            && is_array($observed['record'])
            && is_string($observed['record']['token'] ?? null)
            && hash_equals($token, $observed['record']['token'])) {
            self::compare_and_delete($option, (string) $observed['raw']);
        }
        unset(self::$held[$order_id]);
    }

    /**
     * Serialize first-time PayMongo order creation for one Woo session without
     * persisting the raw session/customer identifier. Reentrant calls in the
     * same PHP request retain the original database fencing token.
     */
    public static function acquire_checkout(string $identity): bool
    {
        $identity = trim($identity);
        if ($identity === '') {
            return false;
        }

        $option = self::checkout_option_name($identity);
        if (self::$checkout_option === $option && self::$checkout_token !== '') {
            return true;
        }
        if (self::$checkout_option !== '' || self::$checkout_token !== '') {
            return false;
        }

        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Throwable $error) {
            return false;
        }

        $record = array('token' => $token, 'acquired_at' => time());
        if (!add_option($option, $record, '', false)) {
            $observed = self::read_database_record($option);
            $existing = is_array($observed) ? $observed['record'] : null;
            $acquired_at = is_array($existing) ? (int) ($existing['acquired_at'] ?? 0) : 0;
            if ($acquired_at < 1 || (time() - $acquired_at) <= self::TTL) {
                return false;
            }
            if (!self::compare_and_swap($option, (string) $observed['raw'], $record)) {
                return false;
            }
        }

        self::$checkout_option = $option;
        self::$checkout_token = $token;
        return true;
    }

    public static function checkout_held_by_request(): bool
    {
        return self::$checkout_option !== '' && self::$checkout_token !== '';
    }

    public static function renew_checkout(): bool
    {
        if (!self::checkout_held_by_request()) {
            return false;
        }

        $observed = self::read_database_record(self::$checkout_option);
        if (!is_array($observed)
            || !is_array($observed['record'])
            || !is_string($observed['record']['token'] ?? null)
            || !hash_equals(self::$checkout_token, $observed['record']['token'])) {
            self::$checkout_option = '';
            self::$checkout_token = '';
            return false;
        }

        $renewed = self::compare_and_swap(
            self::$checkout_option,
            (string) $observed['raw'],
            array('token' => self::$checkout_token, 'acquired_at' => time())
        );
        if (!$renewed) {
            self::$checkout_option = '';
            self::$checkout_token = '';
        }
        return $renewed;
    }

    public static function release_checkout(): void
    {
        if (!self::checkout_held_by_request()) {
            return;
        }

        $observed = self::read_database_record(self::$checkout_option);
        if (is_array($observed)
            && is_array($observed['record'])
            && is_string($observed['record']['token'] ?? null)
            && hash_equals(self::$checkout_token, $observed['record']['token'])) {
            self::compare_and_delete(self::$checkout_option, (string) $observed['raw']);
        }
        self::$checkout_option = '';
        self::$checkout_token = '';
    }

    /**
     * Serialize the complete gateway-settings mutation, including provider
     * readiness verification. The intended settings fingerprint is part of the
     * database fence so only the writer whose exact value reached storage may
     * reopen checkout.
     */
    public static function acquire_settings(string $fingerprint): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            return false;
        }
        if (self::$settings_token !== '') {
            return hash_equals(self::$settings_fingerprint, $fingerprint)
                && self::settings_held_for($fingerprint);
        }

        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Throwable $error) {
            return false;
        }

        $record = array(
            'token' => $token,
            'acquired_at' => time(),
            'fingerprint' => $fingerprint,
        );
        if (!add_option(self::SETTINGS_OPTION, $record, '', false)) {
            $observed = self::read_database_record(self::SETTINGS_OPTION);
            $existing = is_array($observed) ? $observed['record'] : null;
            $acquired_at = is_array($existing) ? (int) ($existing['acquired_at'] ?? 0) : 0;
            if ($acquired_at < 1 || (time() - $acquired_at) <= self::TTL) {
                return false;
            }
            if (!self::compare_and_swap(self::SETTINGS_OPTION, (string) $observed['raw'], $record)) {
                return false;
            }
        }

        self::$settings_token = $token;
        self::$settings_fingerprint = $fingerprint;
        return true;
    }

    public static function settings_held_for(string $fingerprint): bool
    {
        if (self::$settings_token === ''
            || self::$settings_fingerprint === ''
            || !hash_equals(self::$settings_fingerprint, $fingerprint)) {
            return false;
        }

        $observed = self::read_database_record(self::SETTINGS_OPTION);
        return is_array($observed)
            && is_array($observed['record'])
            && is_string($observed['record']['token'] ?? null)
            && is_string($observed['record']['fingerprint'] ?? null)
            && hash_equals(self::$settings_token, $observed['record']['token'])
            && hash_equals($fingerprint, $observed['record']['fingerprint']);
    }

    public static function renew_settings(string $fingerprint): bool
    {
        if (!self::settings_held_for($fingerprint)) {
            self::$settings_token = '';
            self::$settings_fingerprint = '';
            return false;
        }

        $observed = self::read_database_record(self::SETTINGS_OPTION);
        $renewed = is_array($observed) && self::compare_and_swap(
            self::SETTINGS_OPTION,
            (string) $observed['raw'],
            array(
                'token' => self::$settings_token,
                'acquired_at' => time(),
                'fingerprint' => $fingerprint,
            )
        );
        if (!$renewed) {
            self::$settings_token = '';
            self::$settings_fingerprint = '';
        }
        return $renewed;
    }

    public static function retarget_settings(string $current_fingerprint, string $next_fingerprint): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $next_fingerprint)
            || !self::settings_held_for($current_fingerprint)) {
            return false;
        }
        $observed = self::read_database_record(self::SETTINGS_OPTION);
        $retargeted = is_array($observed) && self::compare_and_swap(
            self::SETTINGS_OPTION,
            (string) $observed['raw'],
            array(
                'token' => self::$settings_token,
                'acquired_at' => time(),
                'fingerprint' => $next_fingerprint,
            )
        );
        if (!$retargeted) {
            self::$settings_token = '';
            self::$settings_fingerprint = '';
            return false;
        }
        self::$settings_fingerprint = $next_fingerprint;
        return true;
    }

    /**
     * Treat malformed or current records as active. A well-formed expired
     * record may be replaced by the next settings writer, but checkout never
     * removes it or assumes ownership.
     */
    public static function settings_write_active(): bool
    {
        $observed = self::read_database_record(self::SETTINGS_OPTION);
        if (!is_array($observed)) {
            return false;
        }
        $record = $observed['record'];
        if (!is_array($record)
            || !is_string($record['token'] ?? null)
            || !is_string($record['fingerprint'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $record['fingerprint'])) {
            return true;
        }
        $acquired_at = (int) ($record['acquired_at'] ?? 0);
        return $acquired_at < 1 || (time() - $acquired_at) <= self::TTL;
    }

    public static function release_settings(): void
    {
        if (self::$settings_token === '') {
            return;
        }
        $observed = self::read_database_record(self::SETTINGS_OPTION);
        if (is_array($observed)
            && is_array($observed['record'])
            && is_string($observed['record']['token'] ?? null)
            && hash_equals(self::$settings_token, $observed['record']['token'])) {
            self::compare_and_delete(self::SETTINGS_OPTION, (string) $observed['raw']);
        }
        self::$settings_token = '';
        self::$settings_fingerprint = '';
    }

    /** @param mixed $expected */
    public static function delete_option_if_exact(string $option, $expected): bool
    {
        $observed = self::read_database_record($option);
        if (!is_array($observed) || $observed['record'] !== $expected) {
            return false;
        }
        return self::compare_and_delete($option, (string) $observed['raw']);
    }

    public static function settings_held_by_request(): bool
    {
        return self::$settings_token !== '' && self::$settings_fingerprint !== '';
    }

    public static function renew_current_settings(): bool
    {
        return self::settings_held_by_request() && self::renew_settings(self::$settings_fingerprint);
    }

    private static function option_name(int $order_id): string
    {
        return self::PREFIX . $order_id;
    }

    private static function checkout_option_name(string $identity): string
    {
        return self::CHECKOUT_PREFIX . hash_hmac('sha256', $identity, wp_salt('auth'));
    }

    /** @return array{raw:string,record:mixed}|null */
    private static function read_database_record(string $option): ?array
    {
        global $wpdb;

        if (!is_object($wpdb)
            || !isset($wpdb->options)
            || !is_callable(array($wpdb, 'prepare'))
            || !is_callable(array($wpdb, 'get_var'))) {
            $record = get_option($option, null);
            return $record === null
                ? null
                : array('raw' => function_exists('maybe_serialize') ? maybe_serialize($record) : serialize($record), 'record' => $record);
        }

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option
            )
        );
        if (!is_string($raw)) {
            return null;
        }

        $record = is_serialized($raw)
            ? unserialize($raw, array('allowed_classes' => false))
            : $raw;
        return array('raw' => $raw, 'record' => $record);
    }

    /** @param array<string,mixed> $replacement */
    private static function compare_and_swap(string $option, string $observed_raw, array $replacement): bool
    {
        global $wpdb;

        $replacement_raw = function_exists('maybe_serialize') ? maybe_serialize($replacement) : serialize($replacement);
        // MySQL reports zero affected rows when a same-second renewal writes
        // bytes identical to the value we just fenced. Exact equality is still
        // a successful ownership check and does not need a database update.
        if (hash_equals($observed_raw, $replacement_raw)) {
            return true;
        }

        if (!is_object($wpdb)
            || !isset($wpdb->options)
            || !is_callable(array($wpdb, 'prepare'))
            || !is_callable(array($wpdb, 'query'))) {
            // The database CAS is required for stale takeover. The test-only
            // fallback may renew an uncontested lock but never steals one.
            $existing = get_option($option, null);
            if (!is_array($existing)
                || !is_string($existing['token'] ?? null)
                || !hash_equals((string) ($replacement['token'] ?? ''), $existing['token'])) {
                return false;
            }
            update_option($option, $replacement, false);
            return true;
        }

        $affected = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                $replacement_raw,
                $option,
                $observed_raw
            )
        );
        if ($affected !== 1) {
            return false;
        }

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($option, 'options');
        }
        return true;
    }

    private static function compare_and_delete(string $option, string $observed_raw): bool
    {
        global $wpdb;

        if (!is_object($wpdb)
            || !isset($wpdb->options)
            || !is_callable(array($wpdb, 'prepare'))
            || !is_callable(array($wpdb, 'query'))) {
            $existing = get_option($option, null);
            $token = is_array($existing) ? (string) ($existing['token'] ?? '') : '';
            $observed = is_serialized($observed_raw)
                ? unserialize($observed_raw, array('allowed_classes' => false))
                : $observed_raw;
            if (!is_array($observed)
                || !is_string($observed['token'] ?? null)
                || !hash_equals((string) $observed['token'], $token)) {
                return false;
            }
            return delete_option($option);
        }

        $affected = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
                $option,
                $observed_raw
            )
        );
        if ($affected !== 1) {
            return false;
        }

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($option, 'options');
        }
        return true;
    }
}
