<?php

namespace Bactive\Brevo;

defined('ABSPATH') || exit;

final class Consent
{
    public const IDENTITY_COOKIE = '__Host-bactive_brevo_identity';
    public const PENDING_COOKIE = '__Host-bactive_brevo_confirmation';
    public const SOURCES = ['footer', 'homepage', 'checkout'];

    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'handle_confirmation'], 1);
        add_action('rest_api_init', static function (): void {
            register_rest_route('bactive-brevo/v1', '/status', [
                'methods' => 'GET', 'permission_callback' => '__return_true',
                'callback' => static function (): \WP_REST_Response {
                    $response = new \WP_REST_Response(self::status());
                    $response->header('Cache-Control', 'private, no-store');
                    return $response;
                },
            ]);
        });
    }

    /** Explicit consent is a separate boolean; merely supplying an email is never opt-in. */
    public static function subscribe(string $email, string $source, string $captcha_token, bool $consent = false): array|\WP_Error
    {
        $email = strtolower(trim($email));
        if (!$consent || !in_array($source, self::SOURCES, true) || !is_email($email) || strlen($email) > 254) {
            return new \WP_Error('invalid_signup', 'Enter a valid email and choose to receive B Active emails.');
        }
        if (!Config::recipient_allowed($email) || !Config::readiness(false)['ready']) {
            return new \WP_Error('signup_unavailable', 'Newsletter signup is temporarily unavailable. Please try again later.');
        }
        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
        $hour = gmdate('YmdH');
        if (!Store::reserve('signup-ip|' . $ip . '|' . $hour, 20, time() + HOUR_IN_SECONDS)
            || !Store::reserve('signup-email|' . $email . '|' . $hour, 5, time() + HOUR_IN_SECONDS)) {
            return new \WP_Error('signup_rate_limited', 'Please wait before trying again.');
        }
        if (!Api::captcha($captcha_token)) return new \WP_Error('captcha_failed', 'Please complete the security check and try again.');
        $generic = ['state' => 'pending', 'message' => 'If confirmation is needed, check your inbox to confirm your signup.'];
        $old = Store::contact(Store::email_hash($email));
        if ($old && ($old['state'] === 'confirmed' || $old['state'] === 'review_required'
            || ($old['state'] === 'pending' && (int) $old['pending_until'] > time())
            || in_array($old['reason'], ['hard_bounce', 'spam', 'complaint'], true))) {
            $identity = self::current_identity();
            return $identity && hash_equals($identity['email_hash'], $old['email_hash'])
                ? ['state' => 'confirmed', 'message' => 'You are already subscribed.'] : $generic;
        }
        if (!Store::reserve('signup-daily|' . gmdate('Ymd'), Config::limit('daily_signup_cap', 100), time() + DAY_IN_SECONDS)) {
            return new \WP_Error('signup_capacity', 'Newsletter signup is temporarily unavailable. Please try again later.');
        }
        $return_token = bin2hex(random_bytes(32));
        $marker = bin2hex(random_bytes(32));
        if (!Store::pending($email, $source, $return_token, $marker)) return $generic;
        $result = Api::request_doi($email, $marker, $source, $return_token);
        if (is_wp_error($result)) {
            $ambiguous = $result->get_error_code() === 'provider_ambiguous';
            Store::update_contact(Store::email_hash($email), [
                'state' => $ambiguous ? 'review_required' : 'failed',
                'reason' => $ambiguous ? 'doi_ambiguous' : $result->get_error_code(),
            ]);
            return new \WP_Error('signup_unconfirmed', 'The email service has not confirmed this signup. Please try again later.');
        }
        // Neither the return token nor a usable identity is exposed to the signup caller.
        return $generic;
    }

    public static function provider_eligible(array $data, string $email): bool
    {
        if (!is_int($data['id'] ?? null) || $data['id'] < 1 || !is_string($data['email'] ?? null)
            || strtolower(trim($data['email'])) !== strtolower(trim($email))
            || ($data['emailBlacklisted'] ?? null) !== false || !is_array($data['listIds'] ?? null)) return false;
        foreach ($data['listIds'] as $id) if (!is_int($id) || $id < 1) return false;
        return in_array((int) Config::get('confirmed_list_id'), $data['listIds'], true);
    }

    /** Mandatory immediately before each event, even if a browser identity was recently checked. */
    public static function check_live(array $contact): array|\WP_Error
    {
        if ($contact['state'] !== 'confirmed' || !Config::recipient_allowed($contact['email'])) {
            return new \WP_Error('consent_missing', 'Marketing consent is required.');
        }
        $result = Api::contact($contact['email']);
        if (is_wp_error($result)) return $result;
        $data = $result['data'];
        if (!self::provider_eligible($data, $contact['email'])) {
            Store::suppress($contact['email_hash'], 'provider_consent_missing');
            return new \WP_Error('consent_revoked', 'Marketing consent is no longer active.');
        }
        if ((int) $contact['provider_id'] !== (int) $data['id']) {
            return new \WP_Error('identity_changed', 'Subscriber identity needs review.');
        }
        Store::update_contact($contact['email_hash'], ['verified_at' => time()]);
        $fresh = Store::contact($contact['email_hash']);
        return $fresh && $fresh['state'] === 'confirmed' ? $fresh : new \WP_Error('consent_revoked', 'Marketing consent is no longer active.');
    }

    public static function confirm_from_token(string $token): array|\WP_Error
    {
        global $wpdb;
        $pending = Store::contact_for_token($token);
        if (!$pending || $pending['state'] !== 'pending' || (int) $pending['pending_until'] < time()
            || !Config::recipient_allowed($pending['email'])) {
            return new \WP_Error('confirmation_invalid', 'This confirmation link is no longer available.');
        }
        $result = Api::contact($pending['email']);
        if (is_wp_error($result)) return $result;
        $data = $result['data'];
        $marker = $data['attributes']['BA_DOI_TOKEN'] ?? '';
        if (!self::provider_eligible($data, $pending['email']) || !is_string($marker)
            || !hash_equals($pending['marker'], $marker)) {
            return new \WP_Error('confirmation_pending', 'Your email confirmation is still being verified.');
        }
        $identity_token = bin2hex(random_bytes(32));
        if (!Store::ready() || $wpdb->query('START TRANSACTION') === false) {
            return new \WP_Error('confirmation_storage', 'Your confirmation could not be completed. Please try the link again.');
        }
        if (!Store::confirm($pending, (int) $data['id'], $identity_token)
            || !Store::queue($pending['email_hash'], 'ba_welcome_ready', 'welcome', 'contact', 'welcome', time())
            || $wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('confirmation_changed', 'Your confirmation could not be completed.');
        }
        self::cookie(self::IDENTITY_COOKIE, $identity_token, time() + 30 * DAY_IN_SECONDS);
        self::cookie(self::PENDING_COOKIE, '', time() - HOUR_IN_SECONDS);
        $contact = Store::contact($pending['email_hash']);
        // Only this confirmed browser may bind its existing Woo cart.
        if (class_exists(Automations::class)) Automations::capture_cart();
        return ['state' => 'confirmed', 'message' => 'Your subscription is confirmed.'];
    }

    private static function cookie(string $name, string $value, int $expires): void
    {
        if (headers_sent()) return;
        setcookie($name, $value, ['expires' => $expires, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        if ($value === '') unset($_COOKIE[$name]);
        else $_COOKIE[$name] = $value;
    }

    public static function handle_confirmation(): void
    {
        if (!Config::enabled()) return;
        $from_url = isset($_GET['ba_brevo_confirm']) && is_string($_GET['ba_brevo_confirm']);
        $token = $from_url ? wp_unslash($_GET['ba_brevo_confirm']) : ($_COOKIE[self::PENDING_COOKIE] ?? '');
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) return;
        $pending = Store::contact_for_token($token);
        if (!$pending || (int) $pending['pending_until'] < time()) {
            self::cookie(self::PENDING_COOKIE, '', time() - 1);
            if (!$from_url) return;
            $state = 'expired';
        } else {
            self::cookie(self::PENDING_COOKIE, $token, min((int) $pending['pending_until'], time() + HOUR_IN_SECONDS));
            $allowed = Store::once('confirm-read|' . Store::hash($token) . '|' . (int) (time() / 30), time() + 60);
            $result = $allowed ? self::confirm_from_token($token) : new \WP_Error('confirmation_pending', 'Confirmation pending.');
            $state = is_wp_error($result) ? 'pending' : 'confirmed';
        }
        if (!$from_url) return;
        nocache_headers();
        header('Referrer-Policy: no-referrer');
        $url = remove_query_arg(['ba_brevo_confirm', 'ba_newsletter', 'ba_signup'], Config::redirect_url());
        wp_safe_redirect(add_query_arg('ba_signup', $state, $url), 303);
        exit;
    }

    public static function current_identity(): ?array
    {
        if (!Config::enabled()) return null;
        $token = $_COOKIE[self::IDENTITY_COOKIE] ?? '';
        $contact = is_string($token) ? Store::contact_for_token($token, true) : null;
        if (!$contact || (int) $contact['session_until'] < time()) return null;
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            // A WordPress login does not prove email ownership. It must also match the DOI cookie.
            if (!is_email((string) $user->user_email)
                || !hash_equals($contact['email_hash'], Store::email_hash((string) $user->user_email))) return null;
        }
        if (!$contact || $contact['state'] !== 'confirmed' || !Config::recipient_allowed($contact['email'])) return null;
        if ((int) $contact['verified_at'] < time() - 300) {
            $contact = self::check_live($contact);
            if (is_wp_error($contact)) return null;
        }
        return $contact;
    }

    public static function status(): array
    {
        return ['state' => self::current_identity() ? 'confirmed' : 'pending'];
    }
}
