<?php
/** Synthetic PayMongo transport for the network-isolated fixture ONLY. */
if (!defined('DB_NAME') || DB_NAME !== 'cashier_fixture'
    || !defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'local') {
    return;
}
define('BACTIVE_PAYMONGO_LIVE_SECRET_KEY', 'sk_live_synthetic_cashier_fixture_only');
// Model the HTTPS webhook address without changing the local browser origin.
// Readiness still validates the full webhook contract through synthetic HTTP.
add_filter('home_url', static function ($url) {
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) as $frame) {
        if (($frame['class'] ?? '') === 'BActive\\PayMongo\\Readiness'
            && ($frame['function'] ?? '') === 'endpoint_url') {
            return preg_replace('~^http:~', 'https:', $url);
        }
    }
    return $url;
});
add_filter('pre_http_request', static function ($pre, $args, $url) {
    if (wp_parse_url($url, PHP_URL_HOST) !== 'api.paymongo.com') {
        return $pre;
    }
    $path = wp_parse_url($url, PHP_URL_PATH);
    $method = $args['method'] ?? 'GET';
    $fault = get_option('bactive_cashier_fixture_provider_fault', '');
    if ($fault === 'timeout' || ($fault === 'expire_timeout' && str_ends_with($path, '/expire'))) {
        return new WP_Error('fixture_provider_timeout', 'Synthetic transport timeout.');
    }
    if ($path === '/v1/merchants/capabilities/payment_methods') {
        $body = array('qrph', 'paymaya', 'shopee_pay', 'grab_pay');
    } elseif ($path === '/v1/webhooks') {
        $hook = array('id' => 'hook_cashier_synthetic', 'type' => 'webhook', 'attributes' => array(
            'url' => \BActive\PayMongo\Readiness::endpoint_url(true), 'status' => 'enabled',
            'events' => array('checkout_session.payment.paid'), 'livemode' => true,
            'secret_key' => 'whsk_synthetic_cashier_fixture_only',
        ));
        $body = array('data' => $method === 'POST' ? $hook : array($hook));
    } elseif ($path === '/v2/checkout_sessions' && $method === 'POST') {
        $payload = json_decode($args['body'] ?? '{}', true);
        $attrs = $payload['data']['attributes'];
        $id = 'cs_cashier_synthetic_' . substr(hash('sha256', $args['headers']['Idempotency-Key']), 0, 24);
        $body = get_option('bactive_cashier_fixture_session_' . $id, null);
        if (!is_array($body)) {
            $attrs['status'] = 'active';
            $attrs['livemode'] = true;
            $attrs['checkout_url'] = 'https://checkout.paymongo.com/' . $id;
            $attrs['payments'] = array();
            $body = array('data' => array('id' => $id, 'type' => 'checkout_session', 'attributes' => $attrs));
            update_option('bactive_cashier_fixture_session_' . $id, $body, false);
        }
    } elseif (preg_match('~^/v1/checkout_sessions/(cs_[A-Za-z0-9_-]+)(/expire)?$~D', $path, $match)) {
        $id = $match[1];
        $body = get_option('bactive_cashier_fixture_session_' . $id, null);
        if (!is_array($body)) {
            return new WP_Error('fixture_session_missing', 'Unknown synthetic session.');
        }
        if (!empty($match[2])) {
            $body['data']['attributes']['status'] = 'expired';
            update_option('bactive_cashier_fixture_session_' . $id, $body, false);
        }
    } else {
        return new WP_Error('fixture_unhandled_provider_request', 'Synthetic transport has no handler for this path.');
    }
    return array('headers' => array(), 'body' => wp_json_encode($body),
        'response' => array('code' => 200, 'message' => 'Synthetic fixture'), 'cookies' => array());
}, PHP_INT_MAX, 3);

/** Set provider readback to paid; real gateway reconciliation performs settlement. */
function bactive_cashier_fixture_paid(int $order_id, string $method = 'qrph', bool $reconcile = true): void {
    $order = wc_get_order($order_id);
    $attempts = \BActive\PayMongo\Gateway::order_attempts($order);
    $attempt = end($attempts);
    $id = $attempt['session_id'] ?? '';
    $body = get_option('bactive_cashier_fixture_session_' . $id, null);
    if (!is_array($body)) {
        throw new RuntimeException('Synthetic session missing.');
    }
    $body['data']['attributes']['payments'] = array(array(
        'id' => 'pay_cashier_synthetic_' . $order_id, 'type' => 'payment', 'attributes' => array(
            'amount' => (int) round((float) $order->get_total() * 100), 'currency' => 'PHP',
            'status' => 'paid', 'livemode' => true, 'source' => array('type' => $method),
        ),
    ));
    update_option('bactive_cashier_fixture_session_' . $id, $body, false);
    if ($reconcile) { \BActive\PayMongo\Reconciler::run_order($order_id); }
}
