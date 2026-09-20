<?php

namespace Bactive\Brevo;

defined('ABSPATH') || exit;

final class Config
{
    public const OPTION = 'bactive_brevo_settings';
    public const CRON_FRESHNESS_SECONDS = 600;

    /**
     * These are marketing capabilities, rather than raw database-stage values.
     * Keeping both cart delays behind one explicit switch prevents a partial
     * cart-reminder launch.
     */
    private const STAGE_KEYS = [
        'ba_welcome_ready|contact|welcome' => 'welcome',
        'ba_cart_reminder_ready|cart|2h' => 'cart',
        'ba_cart_reminder_ready|cart|24h' => 'cart',
        'ba_post_purchase_ready|order|care' => 'care',
        'ba_post_purchase_ready|order|review' => 'review',
        'ba_winback_ready|order|90d' => 'winback',
    ];

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
            // A deployment must explicitly choose every stage it is releasing.
            'enabled_stages' => [],
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

    /** @return list<string> */
    public static function enabled_stages(): array
    {
        $configured = self::get('enabled_stages', []);
        if (!is_array($configured)) return [];
        $allowed = array_values(array_unique(array_values(self::STAGE_KEYS)));
        $stages = [];
        foreach ($configured as $stage) {
            if (is_string($stage) && in_array($stage, $allowed, true)) $stages[] = $stage;
        }
        return array_values(array_unique($stages));
    }

    /** Return the configured capability for an exact event/entity/stage tuple. */
    public static function stage_key(string $event, string $stage, string $entity_kind): string
    {
        return self::STAGE_KEYS[$event . '|' . $entity_kind . '|' . $stage] ?? '';
    }

    public static function stage_enabled(string $event, string $stage, string $entity_kind): bool
    {
        $key = self::stage_key($event, $stage, $entity_kind);
        return $key !== '' && in_array($key, self::enabled_stages(), true);
    }

    /** The cutoff must be recorded and active before new marketing jobs exist. */
    public static function launch_active(): bool
    {
        $cutoff = (int) self::get('launch_cutoff');
        return $cutoff > 0 && $cutoff <= time();
    }

    /** Queueing requires a currently active release as well as the stage allowlist. */
    public static function enqueue_enabled(string $event, string $stage, string $entity_kind): bool
    {
        return self::launch_active() && self::stage_enabled($event, $stage, $entity_kind);
    }

    /**
     * Return a sanitized hold reason when a durable job is outside this launch.
     * This deliberately uses job creation time, never its due time, so old held
     * work cannot be released by changing an allowlist later.
     */
    public static function dispatch_blocker(array $job): string
    {
        $created = $job['created_at'] ?? null;
        if (!is_scalar($created) || !preg_match('/^\d+$/D', (string) $created)
            || (int) $created < (int) self::get('launch_cutoff') || !self::launch_active()) {
            return 'prelaunch_job';
        }
        return self::stage_enabled((string) ($job['event_name'] ?? ''), (string) ($job['stage'] ?? ''), (string) ($job['entity_kind'] ?? ''))
            ? '' : 'stage_disabled';
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

    /**
     * Report CLI-cron evidence without exposing protected configuration. A future
     * timestamp is treated as clock skew, rather than as proof that cron is ready.
     */
    public static function cron_status(?int $now = null): array
    {
        $now = $now ?? time();
        $evidence = get_option('bactive_brevo_cron_evidence', []);
        $count = is_array($evidence) ? max(0, (int) ($evidence['count'] ?? 0)) : 0;
        $last = is_array($evidence) ? max(0, (int) ($evidence['last'] ?? 0)) : 0;
        $state = 'fresh';
        if ($last < 1) {
            $state = 'not_recorded';
        } elseif ($last > $now) {
            $state = 'clock_skew';
        } elseif ($count < 2) {
            $state = 'insufficient_ticks';
        } elseif ($last < $now - self::CRON_FRESHNESS_SECONDS) {
            $state = 'stale';
        }
        return [
            'state' => $state,
            'fresh' => $state === 'fresh',
            'observed_ticks' => $count,
            'last_tick_at' => $last,
            'age_seconds' => $last > 0 && $last <= $now ? $now - $last : null,
            'freshness_window_seconds' => self::CRON_FRESHNESS_SECONDS,
        ];
    }

    public static function cron_ready(): bool
    {
        return self::cron_status()['fresh'];
    }

    /** Only the CLI runner can establish evidence, never a public HTTP hit. */
    public static function record_cli_tick(): void
    {
        if (!defined('WP_CLI') || !WP_CLI || !self::site_allowed()) return;
        $now = time();
        $old = get_option('bactive_brevo_cron_evidence', []);
        $last = (int) ($old['last'] ?? 0);
        $count = (int) ($old['count'] ?? 0);
        if ($last > $now || $last < $now - self::CRON_FRESHNESS_SECONDS) $count = 0;
        if ($last > $now - 30 && $last <= $now) return;
        update_option('bactive_brevo_cron_evidence', ['count' => min(2, $count + 1), 'last' => $now], false);
    }
}
