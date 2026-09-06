<?php

namespace Bactive\Brevo;

defined('ABSPATH') || exit;

final class Api
{
    /** No arbitrary URL or transport override is accepted from callers. */
    private static function request(string $method, string $path, ?array $body = null): array|\WP_Error
    {
        if (!Config::enabled() || Config::secret('api_key') === '') {
            return new \WP_Error('provider_disabled', 'Email service is unavailable.');
        }
        if (!preg_match('~^/(?:contacts(?:/doubleOptinConfirmation|/[^/?]+)?|events)$~D', $path)) {
            return new \WP_Error('invalid_provider_path', 'Invalid service request.');
        }
        $args = [
            'method' => $method, 'timeout' => 15, 'redirection' => 0,
            'sslverify' => true, 'limit_response_size' => 131072,
            'headers' => ['api-key' => Config::secret('api_key'), 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ];
        if ($body !== null) $args['body'] = wp_json_encode($body);
        $response = wp_remote_request('https://api.brevo.com/v3' . $path, $args);
        return self::classify_response($response, $method !== 'GET');
    }

    /** A POST timeout/5xx may have taken effect. Never turn it into an automatic retry. */
    public static function classify_response(mixed $response, bool $mutation): array|\WP_Error
    {
        if (is_wp_error($response)) {
            return new \WP_Error($mutation ? 'provider_ambiguous' : 'provider_unavailable', 'Email service did not confirm the request.');
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            $raw = (string) wp_remote_retrieve_body($response);
            $data = $raw === '' ? [] : json_decode($raw, true);
            if (!is_array($data)) {
                return new \WP_Error($mutation ? 'provider_ambiguous' : 'provider_invalid_response', 'Email service returned an invalid response.');
            }
            return ['http_status' => $code, 'data' => $data];
        }
        if ($code === 429) return new \WP_Error('provider_rate_limited', 'Email service is busy.');
        if ($code >= 400 && $code < 500) return new \WP_Error('provider_rejected', 'Email service rejected the request.', ['http_status' => $code]);
        return new \WP_Error($mutation ? 'provider_ambiguous' : 'provider_unavailable', 'Email service did not confirm the request.');
    }

    public static function contact(string $email): array|\WP_Error
    {
        if (!Config::recipient_allowed($email)) return new \WP_Error('recipient_not_allowed', 'Recipient is not eligible.');
        return self::request('GET', '/contacts/' . rawurlencode(strtolower(trim($email))));
    }

    public static function request_doi(string $email, string $marker, string $source, string $return_token): array|\WP_Error
    {
        if (!Config::recipient_allowed($email) || !Config::readiness(false)['ready']) {
            return new \WP_Error('signup_unavailable', 'Signup is temporarily unavailable.');
        }
        return self::request('POST', '/contacts/doubleOptinConfirmation', [
            'email' => $email,
            'attributes' => ['BA_DOI_TOKEN' => $marker, 'BA_CONSENT_SOURCE' => $source],
            'includeListIds' => [(int) Config::get('confirmed_list_id')],
            'templateId' => (int) Config::get('doi_template_id'),
            'redirectionUrl' => add_query_arg('ba_brevo_confirm', $return_token, Config::redirect_url()),
        ]);
    }

    public static function event(array $contact, string $event, array $properties): array|\WP_Error
    {
        if (($properties['mode'] ?? '') !== Config::mode()) {
            return new \WP_Error('event_environment_changed', 'The event belongs to another environment.');
        }
        if (!Config::recipient_allowed((string) ($contact['email'] ?? '')) || (int) ($contact['provider_id'] ?? 0) < 1) {
            return new \WP_Error('recipient_not_allowed', 'Recipient is not eligible.');
        }
        if (!in_array($event, Automations::EVENTS, true)) return new \WP_Error('invalid_event', 'Unknown marketing event.');
        $encoded = wp_json_encode($properties);
        if (!is_string($encoded) || strlen($encoded) > 24000) return new \WP_Error('payload_too_large', 'Marketing event is too large.');
        return self::request('POST', '/events', [
            'event_name' => $event,
            'event_date' => gmdate('Y-m-d\TH:i:s\Z'),
            // Using an existing numeric contact prevents the API from creating an unconsented contact.
            'identifiers' => ['contact_id' => (int) $contact['provider_id']],
            'event_properties' => $properties,
        ]);
    }

    public static function captcha(string $token): bool
    {
        if (strlen($token) < 10 || strlen($token) > 2048 || Config::secret('turnstile_secret') === '') return false;
        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'timeout' => 10, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 8192,
            'body' => ['secret' => Config::secret('turnstile_secret'), 'response' => $token],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($data) && ($data['success'] ?? null) === true
            && ($data['hostname'] ?? '') === wp_parse_url(home_url(), PHP_URL_HOST)
            && ($data['action'] ?? '') === 'newsletter';
    }
}
