<?php

namespace BActive\PayMongo;

defined('ABSPATH') || defined('BACTIVE_PAYMONGO_TESTING') || exit;

final class Secrets
{
    private const PREFIX = 'enc:v1:';
    /** @var array{option:string,value:string}|null */
    private static ?array $write_intent = null;

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('OpenSSL is required to protect PayMongo credentials.');
        }

        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            self::encryption_key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'bactive-paymongo-v1'
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new \RuntimeException('Unable to protect PayMongo credentials.');
        }

        return self::PREFIX . base64_encode($nonce . $tag . $ciphertext);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        if (!str_starts_with($stored, self::PREFIX) || !function_exists('openssl_decrypt')) {
            return '';
        }

        $decoded = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($decoded === false || strlen($decoded) < 29) {
            return '';
        }

        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            self::encryption_key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'bactive-paymongo-v1'
        );

        return is_string($plaintext) ? $plaintext : '';
    }

    public static function api_key(bool $live, ?Gateway $gateway = null): string
    {
        $constant = $live ? 'BACTIVE_PAYMONGO_LIVE_SECRET_KEY' : 'BACTIVE_PAYMONGO_TEST_SECRET_KEY';
        if (defined($constant)) {
            return trim((string) constant($constant));
        }

        $gateway = $gateway ?? new Gateway(false);
        $field = $live ? 'live_secret_key' : 'test_secret_key';
        return self::decrypt((string) $gateway->get_option($field, ''));
    }

    public static function webhook_secret(bool $live): string
    {
        $constant = $live ? 'BACTIVE_PAYMONGO_LIVE_WEBHOOK_SECRET' : 'BACTIVE_PAYMONGO_TEST_WEBHOOK_SECRET';
        if (defined($constant)) {
            return trim((string) constant($constant));
        }

        $option = $live ? 'bactive_paymongo_live_webhook_secret' : 'bactive_paymongo_test_webhook_secret';
        return self::decrypt((string) get_option($option, ''));
    }

    public static function store_webhook_secret(bool $live, string $secret): bool
    {
        if (!str_starts_with($secret, 'whsk_')) {
            return false;
        }

        $option = $live ? 'bactive_paymongo_live_webhook_secret' : 'bactive_paymongo_test_webhook_secret';
        if (self::webhook_secret($live) === $secret) {
            return true;
        }
        $held = Order_Lock::settings_held_by_request();
        $fingerprint = hash('sha256', 'webhook-secret|' . $option . '|' . self::fingerprint($secret));
        if (($held && !Order_Lock::renew_current_settings())
            || (!$held && !Order_Lock::acquire_settings($fingerprint))) {
            return false;
        }
        try {
            Reconciler::set_draining(true);
            if (Reconciler::has_tracked_orders()
                || Reconciler::has_unresolved_external_incidents()
                || !Order_Lock::renew_current_settings()) {
                return false;
            }
            $encrypted = self::encrypt($secret);
            self::$write_intent = array('option' => $option, 'value' => $encrypted);
            update_option($option, $encrypted, false);
            return Order_Lock::renew_current_settings()
                && get_option($option, null) === $encrypted
                && self::webhook_secret($live) === $secret;
        } finally {
            self::$write_intent = null;
            if (!$held) {
                Order_Lock::release_settings();
            }
        }
    }

    /** Last pre-SQL gate for direct add/update and late filter substitution. */
    public static function guard_webhook_secret_write($option, $value): void
    {
        if (!in_array($option, array('bactive_paymongo_test_webhook_secret', 'bactive_paymongo_live_webhook_secret'), true)) {
            return;
        }
        if (self::$write_intent !== null
            && self::$write_intent['option'] === $option
            && is_string($value)
            && hash_equals(self::$write_intent['value'], $value)
            && Order_Lock::renew_current_settings()) {
            return;
        }
        Reconciler::set_draining(true);
        throw new \Error('PayMongo webhook secrets must be stored by verified webhook provisioning.');
    }

    public static function guard_webhook_secret_update($option, $old_value, $value): void
    {
        self::guard_webhook_secret_write($option, $value);
    }

    /** Signing secrets remain available for all outstanding deliveries. */
    public static function guard_webhook_secret_delete($option): void
    {
        if (!in_array($option, array('bactive_paymongo_test_webhook_secret', 'bactive_paymongo_live_webhook_secret'), true)) {
            return;
        }
        Reconciler::set_draining(true);
        throw new \Error('PayMongo signing secrets cannot be deleted directly; replace them through verified provisioning after a complete drain.');
    }

    public static function fingerprint(string $secret): string
    {
        return hash('sha256', $secret);
    }

    private static function encryption_key(): string
    {
        if (defined('BACTIVE_PAYMONGO_TEST_ENCRYPTION_KEY')) {
            return hash('sha256', (string) constant('BACTIVE_PAYMONGO_TEST_ENCRYPTION_KEY'), true);
        }

        $material = '';
        foreach (array('AUTH_KEY', 'SECURE_AUTH_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT') as $constant) {
            if (defined($constant)) {
                $material .= '|' . (string) constant($constant);
            }
        }
        if ($material === '' && function_exists('wp_salt')) {
            $material = (string) wp_salt('auth');
        }
        if ($material === '') {
            throw new \RuntimeException('WordPress authentication salts are required to protect PayMongo credentials.');
        }

        return hash('sha256', 'bactive-paymongo|' . $material, true);
    }
}
