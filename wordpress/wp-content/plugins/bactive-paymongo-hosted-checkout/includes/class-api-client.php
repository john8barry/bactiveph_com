<?php

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

final class Api_Client
{
    private const API_BASE = 'https://api.paymongo.com';
    private const MAX_RESPONSE_BYTES = 1048576;

    private string $secret_key;

    public function __construct(string $secret_key)
    {
        $this->secret_key = $secret_key;
    }

    /** @return array<string,mixed>|\WP_Error */
    public function capabilities()
    {
        $response = $this->request('GET', '/v1/merchants/capabilities/payment_methods');
        if (is_wp_error($response)) {
            return $response;
        }

        $methods = $this->parse_capabilities($response);
        return $methods === null
            ? new \WP_Error('paymongo_capabilities_shape', 'PayMongo returned an unexpected capabilities response.')
            : array('methods' => $methods);
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_checkout_session(array $payload, string $idempotency_key)
    {
        return $this->request('POST', '/v2/checkout_sessions', $payload, $idempotency_key);
    }

    /** @return array<string,mixed>|\WP_Error */
    public function retrieve_checkout_session(string $session_id)
    {
        if (!preg_match('/^cs_[A-Za-z0-9_-]+$/D', $session_id)) {
            return new \WP_Error('paymongo_session_id_invalid', 'Invalid PayMongo checkout session ID.');
        }
        return $this->request('GET', '/v1/checkout_sessions/' . rawurlencode($session_id));
    }

    /** @return array<string,mixed>|\WP_Error */
    public function expire_checkout_session(string $session_id, string $idempotency_key)
    {
        if (!preg_match('/^cs_[A-Za-z0-9_-]+$/D', $session_id)) {
            return new \WP_Error('paymongo_session_id_invalid', 'Invalid PayMongo checkout session ID.');
        }
        return $this->request(
            'POST',
            '/v1/checkout_sessions/' . rawurlencode($session_id) . '/expire',
            array(),
            $idempotency_key
        );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function list_webhooks(string $url)
    {
        return $this->request(
            'GET',
            '/v1/webhooks?url=' . rawurlencode($url) . '&limit=100'
        );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_webhook(string $url, string $idempotency_key)
    {
        return $this->request(
            'POST',
            '/v1/webhooks',
            array(
                'data' => array(
                    'attributes' => array(
                        'url' => $url,
                        'events' => array('checkout_session.payment.paid'),
                    ),
                ),
            ),
            $idempotency_key
        );
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>|\WP_Error
     */
    private function request(string $method, string $path, ?array $body = null, string $idempotency_key = '')
    {
        $headers = array(
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($this->secret_key . ':'),
            'Content-Type' => 'application/json',
            'User-Agent' => 'BActive-PayMongo/' . VERSION,
        );
        if ($idempotency_key !== '') {
            $headers['Idempotency-Key'] = substr($idempotency_key, 0, 255);
        }

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'timeout' => 20,
        );
        if ($body !== null && $method !== 'GET') {
            $encoded = wp_json_encode($body, JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                return new \WP_Error('paymongo_json_encode', 'Unable to encode PayMongo request.');
            }
            $args['body'] = $encoded;
        }

        $response = wp_remote_request(self::API_BASE . $path, $args);
        if (is_wp_error($response)) {
            return new \WP_Error('paymongo_transport', 'PayMongo could not be reached.');
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        if (strlen($raw) > self::MAX_RESPONSE_BYTES) {
            return new \WP_Error('paymongo_response_oversized', 'PayMongo returned an oversized response.');
        }

        $decoded = $raw === '' ? array() : json_decode($raw, true);
        if ($raw !== '' && !is_array($decoded)) {
            return new \WP_Error('paymongo_response_invalid', 'PayMongo returned invalid JSON.');
        }
        if ($status < 200 || $status > 299) {
            $code = 'paymongo_http_' . ($status > 0 ? $status : 'unknown');
            if (is_array($decoded) && isset($decoded['errors'][0]['code'])) {
                $remote_code = sanitize_key((string) $decoded['errors'][0]['code']);
                if ($remote_code !== '') {
                    $code .= '_' . $remote_code;
                }
            }
            return new \WP_Error($code, 'PayMongo rejected the request.');
        }

        return $decoded;
    }

    /** @return array<int,string>|null */
    private function parse_capabilities(array $response): ?array
    {
        $candidate = null;
        if (isset($response['data']['attributes']['payment_methods']) && is_array($response['data']['attributes']['payment_methods'])) {
            $candidate = $response['data']['attributes']['payment_methods'];
        } elseif (isset($response['data']['attributes']['available_payment_methods']) && is_array($response['data']['attributes']['available_payment_methods'])) {
            $candidate = $response['data']['attributes']['available_payment_methods'];
        } elseif (isset($response['data']['attributes']['capabilities']) && is_array($response['data']['attributes']['capabilities'])) {
            $candidate = array();
            foreach ($response['data']['attributes']['capabilities'] as $method => $status) {
                if (($status === true || $status === 'active' || $status === 'enabled') && is_string($method)) {
                    $candidate[] = $method;
                }
            }
        } elseif (isset($response['data']) && is_array($response['data']) && array_is_list($response['data'])) {
            $candidate = $response['data'];
        } elseif (array_is_list($response)) {
            $candidate = $response;
        }

        if ($candidate === null) {
            return null;
        }

        $methods = array();
        foreach ($candidate as $method) {
            if (!is_string($method)) {
                return null;
            }
            $method = strtolower(trim($method));
            if ($method !== '') {
                $methods[] = $method;
            }
        }

        return array_values(array_unique($methods));
    }
}
