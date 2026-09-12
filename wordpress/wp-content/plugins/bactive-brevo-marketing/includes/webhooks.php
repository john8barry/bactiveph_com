<?php

namespace Bactive\Brevo;

defined('ABSPATH') || exit;

final class Webhooks
{
    public const MAX_BODY = 16384;
    private const TYPES = [
        'unsubscribed' => 'unsubscribed', 'unsubscribe' => 'unsubscribed',
        'hard_bounce' => 'hard_bounce', 'hardBounce' => 'hard_bounce',
        'spam' => 'spam', 'complaint' => 'complaint', 'blocked' => 'blocked',
        'event_accepted' => 'event_accepted',
    ];

    public static function register(): void
    {
        add_action('rest_api_init', static function (): void {
            register_rest_route('bactive-brevo/v1', '/webhook', [
                'methods' => 'POST', 'permission_callback' => [self::class, 'authenticate'],
                'callback' => [self::class, 'handle'],
            ]);
        });
    }

    public static function authenticate(mixed $request): bool|\WP_Error
    {
        $secret = Config::secret('webhook_token');
        $header = (string) $request->get_header('authorization');
        $token = preg_match('/^Bearer ([^\s]+)$/D', $header, $match) ? $match[1] : (string) $request->get_header('x-bactive-brevo-token');
        if (!Config::site_allowed() || strlen($secret) < 32 || $token === '' || !hash_equals($secret, $token)) {
            return new \WP_Error('webhook_unauthorized', 'Unauthorized.', ['status' => 401]);
        }
        return true;
    }

    public static function envelope(mixed $request): array|\WP_Error
    {
        $raw = $request->get_body();
        if (!is_string($raw) || strlen($raw) > self::MAX_BODY) return new \WP_Error('webhook_too_large', 'Payload too large.', ['status' => 413]);
        $data = json_decode($raw, true, 12);
        if (!is_array($data) || array_is_list($data)) return new \WP_Error('webhook_invalid', 'Invalid event.', ['status' => 400]);
        $type = $data['event'] ?? '';
        $header_type = $request->get_header('x-bactive-brevo-event');
        if ($header_type === 'event_accepted') $type = 'event_accepted';
        if (!is_string($type) || !isset(self::TYPES[$type])) return new \WP_Error('webhook_unknown_event', 'Unsupported event.', ['status' => 400]);
        $type = self::TYPES[$type];
        $email = $data['email'] ?? $data['email_id'] ?? $data['contact']['email'] ?? '';
        if (!is_string($email) || !is_email($email) || strlen($email) > 254) return new \WP_Error('webhook_invalid_contact', 'Invalid contact.', ['status' => 400]);
        $email = strtolower(trim($email));
        if (Config::flag('test_mode') && !in_array($email, Config::test_recipients(), true)) return new \WP_Error('webhook_test_recipient', 'Recipient is not enabled for this environment.', ['status' => 403]);
        if ($type === 'event_accepted') {
            $key = self::delivery_key($data);
            if ($key === '') return new \WP_Error('webhook_invalid_receipt', 'Invalid event receipt.', ['status' => 400]);
            return ['type' => $type, 'email' => $email, 'delivery_key' => $key, 'replay_key' => 'receipt|' . $key];
        }
        $time = $data['ts_event'] ?? $data['ts_epoch'] ?? $data['ts'] ?? $data['timestamp'] ?? $data['date'] ?? null;
        if (is_numeric($time)) {
            $time = (int) $time;
            if ($time > 100000000000) $time = (int) floor($time / 1000);
        } elseif (is_string($time) && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $time)) {
            $time = strtotime($time);
        } else $time = 0;
        if (!$time || $time < time() - 7 * DAY_IN_SECONDS || $time > time() + 300) return new \WP_Error('webhook_stale', 'Event timestamp is outside the accepted window.', ['status' => 400]);
        $id = $data['id'] ?? $data['message-id'] ?? $data['messageId'] ?? '';
        if ((!is_string($id) && !is_int($id)) || strlen((string) $id) > 160) return new \WP_Error('webhook_invalid_id', 'Invalid event identifier.', ['status' => 400]);
        return ['type' => $type, 'email' => $email, 'replay_key' => implode('|', [$type, $email, $time, (string) $id])];
    }

    private static function delivery_key(array $data): string
    {
        $candidates = [
            $data['delivery_key'] ?? null,
            $data['event_properties']['delivery_key'] ?? null,
            $data['data']['delivery_key'] ?? null,
            $data['eventdata']['data']['delivery_key'] ?? null,
            $data['event_data']['event_properties']['delivery_key'] ?? null,
            $data['event']['event_properties']['delivery_key'] ?? null,
        ];
        foreach ($candidates as $key) {
            if (is_string($key) && preg_match('/^[a-f0-9]{64}$/D', $key)) return $key;
        }
        return '';
    }

    public static function handle(mixed $request): \WP_REST_Response|\WP_Error
    {
        global $wpdb;
        $auth = self::authenticate($request);
        if (is_wp_error($auth)) return $auth;
        $event = self::envelope($request);
        if (is_wp_error($event)) return $event;
        if ($event['type'] === 'event_accepted') {
            $job = Store::delivery($event['delivery_key']);
            if (!$job || !hash_equals($job['email_hash'], Store::email_hash($event['email']))
                || $job['site'] !== rtrim(home_url(), '/') || $job['mode'] !== Config::mode()
                || (int) $job['updated_at'] < time() - 7 * DAY_IN_SECONDS) {
                return new \WP_Error('webhook_receipt_mismatch', 'Receipt does not match this environment.', ['status' => 400]);
            }
        }
        if (!Store::ready() || $wpdb->query('START TRANSACTION') === false) return new \WP_Error('webhook_storage_failed', 'Event storage is unavailable.', ['status' => 503]);
        $fresh = Store::once('webhook|' . $event['replay_key'], time() + 8 * DAY_IN_SECONDS);
        if ($wpdb->last_error !== '') {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('webhook_storage_failed', 'Event storage is unavailable.', ['status' => 503]);
        }
        if (!$fresh) {
            if ($wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return new \WP_Error('webhook_storage_failed', 'Event storage is unavailable.', ['status' => 503]);
            }
            return new \WP_REST_Response(['accepted' => true, 'duplicate' => true]);
        }
        $stored = $event['type'] === 'event_accepted' ? Store::receipt($event['delivery_key']) : Store::suppress_email($event['email'], $event['type']);
        if (!$stored || $wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('webhook_storage_failed', 'Event storage is unavailable.', ['status' => 503]);
        }
        // A receipt establishes workflow intake, not inbox delivery. A webhook never grants consent.
        return new \WP_REST_Response(['accepted' => true]);
    }
}
