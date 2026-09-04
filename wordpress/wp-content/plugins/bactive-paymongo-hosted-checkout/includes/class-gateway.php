<?php

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

final class Gateway extends \WC_Payment_Gateway
{
    private const ATTEMPTS_META = '_bactive_paymongo_attempts';
    private const MAX_ATTEMPTS = 10;

    public function __construct()
    {
        $this->id = GATEWAY_ID;
        $this->method_title = __('PayMongo Hosted Checkout', 'bactive-paymongo');
        $this->method_description = __(
            'Redirects customers to PayMongo and fulfills only after a verified checkout-session webhook.',
            'bactive-paymongo'
        );
        $this->has_fields = false;
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = (string) $this->get_option('title', __('Pay online securely', 'bactive-paymongo'));
        $this->description = (string) $this->get_option(
            'description',
            __('Choose QRPh, Maya, ShopeePay, BPI, or UnionBank on PayMongo.', 'bactive-paymongo')
        );
        $this->enabled = (string) $this->get_option('enabled', 'no');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_order_status_changed', array($this, 'handle_status_change'), 10, 4);
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'pending_confirmation_text'), 10, 2);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'bactive-paymongo'),
                'type' => 'checkbox',
                'label' => __('Enable PayMongo Hosted Checkout', 'bactive-paymongo'),
                'default' => 'no',
            ),
            'test_mode' => array(
                'title' => __('Sandbox mode', 'bactive-paymongo'),
                'type' => 'checkbox',
                'label' => __('Use PayMongo test credentials (no real money)', 'bactive-paymongo'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Checkout title', 'bactive-paymongo'),
                'type' => 'text',
                'default' => __('Pay online securely', 'bactive-paymongo'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Checkout description', 'bactive-paymongo'),
                'type' => 'textarea',
                'default' => __('Choose QRPh, Maya, ShopeePay, BPI, or UnionBank on PayMongo.', 'bactive-paymongo'),
            ),
            'test_secret_key' => array(
                'title' => __('Test secret key', 'bactive-paymongo'),
                'type' => 'bactive_secret',
                'description' => __('Starts with sk_test_. Leave blank to retain the configured key.', 'bactive-paymongo'),
            ),
            'live_secret_key' => array(
                'title' => __('Live secret key', 'bactive-paymongo'),
                'type' => 'bactive_secret',
                'description' => __('Starts with sk_live_. Leave blank to retain the configured key.', 'bactive-paymongo'),
            ),
        );
    }

    public function generate_bactive_secret_html($key, $data): string
    {
        $field_key = $this->get_field_key($key);
        $configured = Secrets::decrypt((string) $this->get_option($key, '')) !== '';
        $constant = $key === 'live_secret_key'
            ? 'BACTIVE_PAYMONGO_LIVE_SECRET_KEY'
            : 'BACTIVE_PAYMONGO_TEST_SECRET_KEY';
        $configured = $configured || (defined($constant) && trim((string) constant($constant)) !== '');
        $description = (string) ($data['description'] ?? '');

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html((string) ($data['title'] ?? '')); ?></label>
            </th>
            <td class="forminp">
                <input
                    class="input-text regular-input"
                    type="password"
                    autocomplete="new-password"
                    name="<?php echo esc_attr($field_key); ?>"
                    id="<?php echo esc_attr($field_key); ?>"
                    value=""
                    placeholder="<?php echo esc_attr($configured ? __('Configured — leave blank to retain', 'bactive-paymongo') : __('Not configured', 'bactive-paymongo')); ?>"
                    <?php disabled(defined($constant)); ?>
                />
                <?php if ($description !== ''): ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                <?php if (defined($constant)): ?>
                    <p class="description"><?php echo esc_html__('Managed by a server-side WordPress constant.', 'bactive-paymongo'); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    public function validate_test_secret_key_field($key, $value): string
    {
        return $this->validate_secret_field($key, $value, 'sk_test_');
    }

    public function validate_live_secret_key_field($key, $value): string
    {
        return $this->validate_secret_field($key, $value, 'sk_live_');
    }

    public function process_admin_options(): bool
    {
        $saved = parent::process_admin_options();
        $this->init_settings();
        $this->enabled = (string) $this->get_option('enabled', 'no');

        if ($this->enabled !== 'yes') {
            return $saved;
        }

        $live = !$this->is_test_mode();
        try {
            $ready = Readiness::verify_and_provision($this, $live);
        } catch (\Throwable $error) {
            $ready = new \WP_Error('paymongo_setup_failed', 'PayMongo setup failed safely.');
        }

        if (is_wp_error($ready)) {
            $this->add_error(
                __('PayMongo remains unavailable: ', 'bactive-paymongo')
                . sanitize_text_field($ready->get_error_message())
            );
            $this->display_errors();
        }

        return $saved;
    }

    public function is_available(): bool
    {
        if (!parent::is_available() || get_woocommerce_currency() !== 'PHP') {
            return false;
        }

        return Readiness::is_ready($this, !$this->is_test_mode());
    }

    /** @return array{result:string,redirect?:string} */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order || $order->get_payment_method() !== $this->id) {
            wc_add_notice(__('Unable to initialize this payment. Please try again.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }
        if ($order->get_currency() !== 'PHP') {
            wc_add_notice(__('PayMongo is available only for PHP orders.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }

        $amount = Integrity::amount_to_minor((string) $order->get_total());
        if ($amount === null || $amount < 1) {
            wc_add_notice(__('The order total is not valid for PayMongo.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }

        $live = !$this->is_test_mode();
        if (!Readiness::is_ready($this, $live)) {
            wc_add_notice(__('PayMongo is temporarily unavailable. Please choose Cash on Delivery or try again later.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }
        if (!$this->acquire_order_lock((int) $order_id)) {
            wc_add_notice(__('This payment is already being initialized. Please wait a moment and try again.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }

        try {
            return $this->create_or_reuse_session($order, $amount, $live);
        } catch (\Throwable $error) {
            $this->safe_log('error', 'checkout_exception', array('order_id' => (int) $order_id));
            wc_add_notice(__('Payment setup failed safely. No payment was confirmed; please try again.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        } finally {
            $this->release_order_lock((int) $order_id);
        }
    }

    public function handle_status_change($order_id, $old_status, $new_status, $order): void
    {
        if ($new_status !== 'cancelled'
            || !$order instanceof \WC_Order
            || $order->get_payment_method() !== $this->id) {
            return;
        }

        $live = !$this->is_test_mode_for_order($order);
        $key = Secrets::api_key($live, $this);
        if ($key === '') {
            $this->flag_review($order, 'session_expiry_key_missing');
            return;
        }

        $client = new Api_Client($key);
        $attempts = $this->attempts($order);
        foreach ($attempts as &$attempt) {
            $session_id = (string) ($attempt['session_id'] ?? '');
            if ($session_id === '' || !empty($attempt['expired_at'])) {
                continue;
            }
            $response = $client->expire_checkout_session(
                $session_id,
                'bactive-expire-' . substr(hash('sha256', $session_id), 0, 48)
            );
            if (is_wp_error($response)) {
                $this->flag_review($order, 'session_expiry_failed');
                continue;
            }
            $attempt['expired_at'] = time();
        }
        unset($attempt);

        $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
        $order->save();
    }

    public function pending_confirmation_text($text, $order): string
    {
        if ($order instanceof \WC_Order
            && $order->get_payment_method() === $this->id
            && !$order->is_paid()) {
            return __(
                'Your order was created and PayMongo confirmation is processing. Do not pay again. We will email you when payment is confirmed.',
                'bactive-paymongo'
            );
        }
        return (string) $text;
    }

    public function is_test_mode(): bool
    {
        return $this->get_option('test_mode', 'yes') === 'yes';
    }

    /** @return array<int,array<string,mixed>> */
    public static function order_attempts(\WC_Order $order): array
    {
        $attempts = $order->get_meta(self::ATTEMPTS_META, true);
        return is_array($attempts) ? array_values(array_filter($attempts, 'is_array')) : array();
    }

    /** @return array{result:string,redirect?:string} */
    private function create_or_reuse_session(\WC_Order $order, int $amount, bool $live): array
    {
        $fingerprint = hash('sha256', implode('|', array(
            (string) $order->get_id(),
            (string) $amount,
            $order->get_currency(),
            $live ? 'live' : 'test',
        )));
        $attempts = $this->attempts($order);

        for ($index = count($attempts) - 1; $index >= 0; --$index) {
            $attempt = $attempts[$index];
            if (($attempt['fingerprint'] ?? '') !== $fingerprint || ($attempt['mode'] ?? '') !== ($live ? 'live' : 'test')) {
                continue;
            }

            $checkout_url = (string) ($attempt['checkout_url'] ?? '');
            if ($checkout_url !== '' && $this->valid_checkout_url($checkout_url)) {
                return array('result' => 'success', 'redirect' => $checkout_url);
            }

            if ((time() - (int) ($attempt['created_at'] ?? 0)) > 82800) {
                wc_add_notice(
                    __('A previous PayMongo request could not be reconciled automatically. Please contact support before trying again.', 'bactive-paymongo'),
                    'error'
                );
                return array('result' => 'fail');
            }

            return $this->submit_attempt($order, $amount, $attempts, $index, $live);
        }

        if (!$this->expire_changed_attempts($order, $attempts, $live)) {
            wc_add_notice(
                __('A previous payment session could not be closed safely. Please contact support before trying again.', 'bactive-paymongo'),
                'error'
            );
            return array('result' => 'fail');
        }

        $generation = 1;
        foreach ($attempts as $attempt) {
            $generation = max($generation, ((int) ($attempt['generation'] ?? 0)) + 1);
        }
        $reference = 'BA-' . $order->get_id() . '-' . $generation;
        $attempts[] = array(
            'generation' => $generation,
            'fingerprint' => $fingerprint,
            'mode' => $live ? 'live' : 'test',
            'reference' => $reference,
            'correlation_id' => bin2hex(random_bytes(24)),
            'idempotency_key' => sprintf(
                'bactive-checkout-%s-%d-%d',
                substr(hash('sha256', home_url('/')), 0, 16),
                $order->get_id(),
                $generation
            ),
            'created_at' => time(),
            'session_id' => '',
            'checkout_url' => '',
        );
        $attempts = array_slice($attempts, -self::MAX_ATTEMPTS);
        $index = count($attempts) - 1;

        $order->update_meta_data(self::ATTEMPTS_META, $attempts);
        $order->save();

        return $this->submit_attempt($order, $amount, $attempts, $index, $live);
    }

    /** @return array{result:string,redirect?:string} */
    private function submit_attempt(\WC_Order $order, int $amount, array $attempts, int $index, bool $live): array
    {
        $attempt = $attempts[$index];
        $key = Secrets::api_key($live, $this);
        $client = new Api_Client($key);
        $payload = $this->checkout_payload($order, $amount, $attempt);
        $response = $client->create_checkout_session($payload, (string) $attempt['idempotency_key']);

        if (is_wp_error($response)) {
            $this->safe_log('error', $response->get_error_code(), array('order_id' => $order->get_id()));
            wc_add_notice(__('PayMongo could not initialize the payment. No payment was confirmed.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }

        $data = $response['data'] ?? null;
        $attributes = is_array($data) ? ($data['attributes'] ?? null) : null;
        $session_id = is_array($data) ? (string) ($data['id'] ?? '') : '';
        $checkout_url = is_array($attributes) ? (string) ($attributes['checkout_url'] ?? '') : '';
        if (!is_array($data)
            || !is_array($attributes)
            || ($data['type'] ?? 'checkout_session') !== 'checkout_session'
            || !preg_match('/^cs_[A-Za-z0-9_-]+$/D', $session_id)
            || !$this->valid_checkout_url($checkout_url)
            || !is_bool($attributes['livemode'] ?? null)
            || $attributes['livemode'] !== $live) {
            $this->safe_log('error', 'checkout_response_invalid', array('order_id' => $order->get_id()));
            wc_add_notice(__('PayMongo returned an invalid checkout session. No payment was confirmed.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }

        $attempts[$index]['session_id'] = $session_id;
        $attempts[$index]['checkout_url'] = $checkout_url;
        $attempts[$index]['authorized_at'] = time();
        $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
        $order->add_order_note(
            sprintf(
                /* translators: %s: PayMongo checkout session ID */
                __('PayMongo checkout session authorized: %s. Awaiting signed payment confirmation.', 'bactive-paymongo'),
                sanitize_text_field($session_id)
            )
        );
        $order->save();

        return array('result' => 'success', 'redirect' => $checkout_url);
    }

    /** @return array<string,mixed> */
    private function checkout_payload(\WC_Order $order, int $amount, array $attempt): array
    {
        $billing = array_filter(
            array(
                'name' => trim($order->get_formatted_billing_full_name()),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ),
            static fn($value): bool => is_string($value) && $value !== ''
        );

        $attributes = array(
            'line_items' => array(
                array(
                    'name' => 'B Active order #' . $order->get_order_number(),
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'quantity' => 1,
                ),
            ),
            'payment_method_types' => Integrity::CHECKOUT_METHODS,
            'success_url' => add_query_arg('paymongo_return', '1', $order->get_checkout_order_received_url()),
            'cancel_url' => $order->get_checkout_payment_url(),
            'reference_number' => (string) $attempt['reference'],
            'description' => 'B Active order #' . $order->get_order_number(),
            'send_email_receipt' => false,
            'pass_on_fees' => false,
            'show_description' => true,
            'show_line_items' => true,
            'metadata' => array(
                'integration' => 'bactive-paymongo',
                'integration_version' => VERSION,
                'order_id' => (string) $order->get_id(),
                'attempt' => (string) $attempt['generation'],
                'correlation_id' => (string) $attempt['correlation_id'],
                'site_id' => substr(hash('sha256', home_url('/')), 0, 32),
            ),
        );
        if ($billing !== array()) {
            $attributes['billing'] = $billing;
        }

        return array('data' => array('attributes' => $attributes));
    }

    private function expire_changed_attempts(\WC_Order $order, array &$attempts, bool $live): bool
    {
        if ($attempts === array()) {
            return true;
        }
        $client = new Api_Client(Secrets::api_key($live, $this));
        foreach ($attempts as &$attempt) {
            if (($attempt['mode'] ?? '') !== ($live ? 'live' : 'test')
                || empty($attempt['session_id'])
                || !empty($attempt['expired_at'])) {
                continue;
            }

            $session_id = (string) $attempt['session_id'];
            $response = $client->expire_checkout_session(
                $session_id,
                'bactive-expire-' . substr(hash('sha256', $session_id), 0, 48)
            );
            if (is_wp_error($response)) {
                $this->safe_log('error', $response->get_error_code(), array('order_id' => $order->get_id()));
                return false;
            }
            $attempt['expired_at'] = time();
        }
        unset($attempt);

        $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
        $order->save();
        return true;
    }

    /** @return array<int,array<string,mixed>> */
    private function attempts(\WC_Order $order): array
    {
        return self::order_attempts($order);
    }

    private function validate_secret_field(string $key, $value, string $prefix): string
    {
        $existing = (string) $this->get_option($key, '');
        $value = trim((string) $value);
        if ($value === '') {
            return $existing;
        }
        if (!str_starts_with($value, $prefix) || !preg_match('/^[A-Za-z0-9_]+$/D', $value) || strlen($value) < 20) {
            $this->add_error(__('The PayMongo secret key has the wrong format; the previous value was retained.', 'bactive-paymongo'));
            return $existing;
        }

        try {
            return Secrets::encrypt($value);
        } catch (\Throwable $error) {
            $this->add_error(__('The PayMongo secret key could not be protected; the previous value was retained.', 'bactive-paymongo'));
            return $existing;
        }
    }

    private function valid_checkout_url(string $url): bool
    {
        return wp_http_validate_url($url)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'checkout.paymongo.com';
    }

    private function acquire_order_lock(int $order_id): bool
    {
        $option = 'bactive_paymongo_order_lock_' . $order_id;
        $now = time();
        if (add_option($option, $now, '', false)) {
            return true;
        }
        $existing = (int) get_option($option, 0);
        if ($existing > 0 && ($now - $existing) > 60) {
            delete_option($option);
            return add_option($option, $now, '', false);
        }
        return false;
    }

    private function release_order_lock(int $order_id): void
    {
        delete_option('bactive_paymongo_order_lock_' . $order_id);
    }

    private function is_test_mode_for_order(\WC_Order $order): bool
    {
        $attempts = $this->attempts($order);
        $latest = end($attempts);
        return is_array($latest) ? (($latest['mode'] ?? 'test') === 'test') : $this->is_test_mode();
    }

    private function flag_review(\WC_Order $order, string $code): void
    {
        $order->update_meta_data('_bactive_paymongo_review_required', $code);
        $order->add_order_note(
            sprintf(
                /* translators: %s: sanitized reconciliation reason */
                __('PayMongo requires manual review: %s.', 'bactive-paymongo'),
                sanitize_key($code)
            )
        );
        $order->save();
        update_option('bactive_paymongo_review_count', (int) get_option('bactive_paymongo_review_count', 0) + 1, false);
    }

    /** @param array<string,int|string> $context */
    private function safe_log(string $level, string $code, array $context = array()): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }
        $safe = array('code' => sanitize_key($code));
        foreach (array('order_id', 'event_id', 'session_id', 'payment_id') as $key) {
            if (isset($context[$key])) {
                $safe[$key] = sanitize_text_field((string) $context[$key]);
            }
        }
        wc_get_logger()->log($level, wp_json_encode($safe), array('source' => 'bactive-paymongo'));
    }
}
