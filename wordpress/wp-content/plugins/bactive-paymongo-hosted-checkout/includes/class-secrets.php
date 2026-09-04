<?php

namespace BActive\PayMongo;

defined('ABSPATH') || defined('BACTIVE_PAYMONGO_TESTING') || exit;

final class Secrets
{
    private const PREFIX = 'enc:v1:';

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

        $gateway = $gateway ?? new Gateway();
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
        update_option($option, self::encrypt($secret), false);
        return self::webhook_secret($live) === $secret;
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
