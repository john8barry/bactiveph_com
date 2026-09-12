<?php

namespace Bactive\Brevo;

defined('ABSPATH') || exit;

final class Config
{
    public const OPTION = 'bactive_brevo_settings';

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'test_mode' => true,
            'test_recipients' => [],
            'confirmed_list_id' => 0,
            'doi_template_id' => 0,
            'doi_redirect_url' => '',
            'turnstile_site_key' => '',
            'launch_cutoff' => 0,
            'automations_verified' => false,
            'daily_event_cap' => 100,
            'daily_signup_cap' => 50,
            'per_contact_daily_cap' => 2,
            'coupon_id' => 0,
            'coupon_code' => 'BACTIVE5',
        ];
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        $settings = get_option(self::OPTION, []);
        $defaults = self::defaults();
        return is_array($settings) && array_key_exists($name, $settings)
            ? $settings[$name] : ($defaults[$name] ?? $default);
    }

    public static function flag(string $name): bool
    {
        return in_array(self::get($name), [true, 1, '1'], true);
    }

    public static function secret(string $name): string
    {
        $map = [
            'api_key' => 'BACTIVE_BREVO_API_KEY',
            'webhook_token' => 'BACTIVE_BREVO_WEBHOOK_TOKEN',
            'turnstile_secret' => 'BACTIVE_BREVO_TURNSTILE_SECRET',
        ];
        $key = $map[strtolower($name)] ?? (in_array($name, $map, true) ? $name : '');
        if (!$key) {
            return '';
        }
        $value = defined($key) ? constant($key) : getenv($key);
        return is_string($value) ? trim($value) : '';
    }

    public static function site_allowed(): bool
    {
        $home = rtrim((string) home_url(), '/');
        $site = rtrim((string) site_url(), '/');
        if ($home !== $site) {
            return false;
        }
        if ($home === 'https://bactiveph.com') {
            return true;
        }
        return $home === 'https://staging.bactiveph.com'
            && self::flag('test_mode') && self::test_recipients() !== [];
    }

    public static function enabled(): bool
    {
        return self::flag('enabled') && self::site_allowed();
    }

    public static function test_recipients(): array
    {
        $values = self::get('test_recipients', []);
        if (!is_array($values)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map(
            static fn($v) => is_string($v) && is_email($v) ? strtolower(trim($v)) : '',
            array_slice($values, 0, 20)
        ))));
    }

    public static function recipient_allowed(string $email): bool
    {
        return self::enabled() && is_email($email)
            && (!self::flag('test_mode') || in_array(strtolower(trim($email)), self::test_recipients(), true));
    }

    public static function mode(): string
    {
        return self::flag('test_mode') ? 'test' : 'live';
    }

    public static function limit(string $key, int $max): int
    {
        return max(1, min($max, (int) self::get($key)));
    }

    public static function redirect_url(): string
    {
        $url = (string) self::get('doi_redirect_url');
        if ($url === '') {
            return home_url('/?ba_signup=confirmed');
        }
        $parts = wp_parse_url($url);
        $home = wp_parse_url(home_url());
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') !== ($home['host'] ?? '')
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return '';
        }
        return $url;
    }

    /** Sanitized, nonsecret operational information for the admin UI. */
    public static function readiness(bool $for_events = true): array
    {
        $blockers = [];
        if (!self::flag('enabled')) $blockers[] = 'disabled';
        if (!self::site_allowed()) $blockers[] = 'site_mismatch';
        if (self::secret('api_key') === '') $blockers[] = 'api_key_missing';
        if (strlen(self::secret('webhook_token')) < 32) $blockers[] = 'webhook_token_missing';
        if (self::secret('turnstile_secret') === '') $blockers[] = 'turnstile_secret_missing';
        if (self::get('turnstile_site_key') === '') $blockers[] = 'turnstile_site_key_missing';
        if ((int) self::get('confirmed_list_id') < 1) $blockers[] = 'confirmed_list_missing';
        if ((int) self::get('doi_template_id') < 1) $blockers[] = 'doi_template_missing';
        if (self::redirect_url() === '') $blockers[] = 'invalid_redirect';
        if ((int) self::get('launch_cutoff') < 1 || (int) self::get('launch_cutoff') > time()) $blockers[] = 'launch_cutoff_missing';
        if (self::flag('test_mode') && !self::test_recipients()) $blockers[] = 'test_recipients_missing';
        if (!function_exists('wc_get_order')) $blockers[] = 'woocommerce_missing';
        if (!Store::ready()) $blockers[] = 'storage_unavailable';
        if ($for_events) {
            if (!self::flag('automations_verified')) $blockers[] = 'automations_unverified';
            if (!function_exists('as_schedule_recurring_action')) $blockers[] = 'action_scheduler_missing';
            if (!self::cron_ready()) $blockers[] = 'real_cron_unverified';
        }
        return ['ready' => $blockers === [], 'mode' => self::mode(), 'blockers' => $blockers];
    }

    public static function cron_ready(): bool
    {
        $evidence = get_option('bactive_brevo_cron_evidence', []);
        return is_array($evidence) && (int) ($evidence['count'] ?? 0) >= 2
            && (int) ($evidence['last'] ?? 0) >= time() - 600;
    }

    /** Only the CLI runner can establish evidence, never a public HTTP hit. */
    public static function record_cli_tick(): void
    {
        if (!defined('WP_CLI') || !WP_CLI || !self::site_allowed()) return;
        $old = get_option('bactive_brevo_cron_evidence', []);
        $last = (int) ($old['last'] ?? 0);
        $count = (int) ($old['count'] ?? 0);
        if ($last < time() - 600) $count = 0;
        if ($last > time() - 30) return;
        update_option('bactive_brevo_cron_evidence', ['count' => min(2, $count + 1), 'last' => time()], false);
    }
}
