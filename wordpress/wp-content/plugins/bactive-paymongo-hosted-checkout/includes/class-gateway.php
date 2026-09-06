<?php

namespace BActive\PayMongo;

defined('ABSPATH') || exit;

final class Gateway extends \WC_Payment_Gateway
{
    private const ATTEMPTS_META = '_bactive_paymongo_attempts';
    private const MAX_ATTEMPTS = 10;

    /** @var array<int,int> */
    private array $save_locks = array();

    /** @var array<int,bool> */
    private array $checkout_locks = array();

    /** @var array<int,array<string,mixed>> Indexed by spl_object_id before a new order has an ID. */
    private array $checkout_snapshots = array();

    /** @var array<int,bool> */
    private array $delete_locks = array();

    private int $loaded_config_generation = 0;
    private static string $settings_reopen_fence = '';
    private static string $settings_reopen_fingerprint = '';

    public function __construct(bool $register_hooks = true)
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
        $this->loaded_config_generation = Reconciler::config_generation();

        $this->title = (string) $this->get_option('title', __('Pay online securely', 'bactive-paymongo'));
        $this->description = (string) $this->get_option(
            'description',
            __('Choose QRPh, Maya, ShopeePay, BPI Direct Debit, or UBP Direct Debit on PayMongo.', 'bactive-paymongo')
        );
        $this->enabled = (string) $this->get_option('enabled', 'no');

        if ($register_hooks) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_filter('woocommerce_thankyou_order_received_text', array($this, 'pending_confirmation_text'), 10, 2);
        }
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
            'restricted_rollout' => array(
                'title' => __('Private verification', 'bactive-paymongo'),
                'type' => 'checkbox',
                'label' => __('Restrict PayMongo checkout to store managers', 'bactive-paymongo'),
                'description' => __('Keep enabled until sandbox tests and the approved live payment are verified. The store and Cash on Delivery remain public.', 'bactive-paymongo'),
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
                'default' => __('Choose QRPh, Maya, ShopeePay, BPI Direct Debit, or UBP Direct Debit on PayMongo.', 'bactive-paymongo'),
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
        $this->loaded_config_generation = Reconciler::config_generation();
        $this->enabled = (string) $this->get_option('enabled', 'no');

        // The option filter/action pair owns readiness verification and is the
        // only lane allowed to reopen checkout. Reopening here would race a
        // concurrent REST/AJAX settings writer after its lease was acquired.
        if ($this->enabled === 'yes' && Reconciler::is_draining()) {
            $this->add_error(__('PayMongo remains unavailable until its settings, webhook, and any prior sessions are verified.', 'bactive-paymongo'));
            $this->display_errors();
        }

        return $saved;
    }

    /**
     * Cover Woo's settings form, Payments AJAX toggle, REST controllers, and
     * direct option writes with the same controlled-drain boundary.
     *
     * @param mixed $value
     * @param mixed $old_value
     * @return mixed
     */
    public static function filter_settings_update($value, $old_value)
    {
        if (!is_array($value)) {
            // Never let a malformed direct option write erase the credentials
            // needed to reconcile already-issued sessions. Returning the exact
            // old value makes WordPress treat this as a no-op.
            update_option(
                'bactive_paymongo_settings_write_rejected',
                array('recorded_at' => time(), 'code' => 'settings_shape_invalid'),
                false
            );
            return $old_value;
        }
        $old_value = is_array($old_value) ? $old_value : array();

        $value['test_secret_key'] = self::normalize_secret_option(
            $value['test_secret_key'] ?? '',
            (string) ($old_value['test_secret_key'] ?? ''),
            'sk_test_'
        );
        $value['live_secret_key'] = self::normalize_secret_option(
            $value['live_secret_key'] ?? '',
            (string) ($old_value['live_secret_key'] ?? ''),
            'sk_live_'
        );

        if (self::settings_fingerprint($value) === self::settings_fingerprint($old_value)) {
            return $value;
        }

        $intended_fingerprint = self::settings_fingerprint($value);
        if (!Order_Lock::acquire_settings($intended_fingerprint)) {
            // Returning the exact stored value makes this competing update a
            // no-op. The active database lease already blocks checkout. Do not
            // write the shared drain flag here: the loser can run after the
            // winner's verified reopen but before its final lease release, and
            // there is no option-update hook for a no-op write to clear it.
            update_option(
                'bactive_paymongo_settings_write_rejected',
                array('recorded_at' => time(), 'code' => 'settings_write_contended'),
                false
            );
            return $old_value;
        }

        $old_enabled = ($old_value['enabled'] ?? 'no') === 'yes';
        $new_enabled = ($value['enabled'] ?? 'no') === 'yes';
        $mode_changed = ($old_value['test_mode'] ?? 'yes') !== ($value['test_mode'] ?? 'yes');
        $test_key_changed = ($old_value['test_secret_key'] ?? '') !== ($value['test_secret_key'] ?? '');
        $live_key_changed = ($old_value['live_secret_key'] ?? '') !== ($value['live_secret_key'] ?? '');
        $sensitive_change = $mode_changed || $test_key_changed || $live_key_changed;
        $rollout_changed = ($old_value['restricted_rollout'] ?? 'yes') !== ($value['restricted_rollout'] ?? 'yes');
        $availability_changed = $old_enabled !== $new_enabled || $rollout_changed;
        $needs_drain = $availability_changed || $sensitive_change;

        // Bind the original closure to this exact writer before any provider
        // work. A copy edit cannot acknowledge a gate that was already closed.
        self::$settings_reopen_fingerprint = $intended_fingerprint;
        self::$settings_reopen_fence = $new_enabled
            ? Reconciler::begin_reopen_verification($needs_drain)
            : '';
        if (self::$settings_reopen_fence === '') {
            Reconciler::set_draining(true);
        }

        // This monotonically invalidates every in-flight issuance, even if a
        // settings request finishes and reopens the draining flag before the
        // provider POST returns.
        if ($availability_changed || $sensitive_change) {
            Reconciler::bump_config_generation();
        }

        if (!$needs_drain) {
            return $value;
        }

        // Set this first: even if the synchronous drain times out, no new
        // checkout can issue a URL and the plugin/webhooks remain available to
        // finish background recovery.
        $result = Reconciler::expire_all_tracked(
            new self(false),
            static fn(): bool => Order_Lock::renew_settings($intended_fingerprint)
        );
        if (!is_wp_error($result) && !Reconciler::has_unresolved_external_incidents()
            && Reconciler::clear_settings_drain_error()
            && Order_Lock::renew_settings($intended_fingerprint)) {
            return $value;
        }

        if (!is_wp_error($result)) {
            $result = new \WP_Error('paymongo_external_incidents_remain', 'PayMongo event recovery is still pending.');
        }
        Reconciler::set_draining(true);

        Order_Lock::insert_option(
            'bactive_paymongo_disable_drain_error',
            array('recorded_at' => time(), 'code' => sanitize_key($result->get_error_code()), 'owner' => 'settings')
        );

        // Hiding the gateway is safe and remains allowed. A mode/key change is
        // rejected until the old-mode sessions have been drained with the old
        // credentials, so a sandbox payment can never settle a live order.
        if ($sensitive_change) {
            foreach (array('test_mode', 'test_secret_key', 'live_secret_key') as $key) {
                if (array_key_exists($key, $old_value)) {
                    $value[$key] = $old_value[$key];
                } else {
                    unset($value[$key]);
                }
            }
        }
        $final_fingerprint = self::settings_fingerprint($value);
        if (!hash_equals($intended_fingerprint, $final_fingerprint)
            && !Order_Lock::retarget_settings($intended_fingerprint, $final_fingerprint)) {
            return $old_value;
        }
        // A rejected sensitive-only mutation collapses to the exact stored
        // value, so WordPress returns before firing either update action. No
        // database write remains to guard; release this request's lease now
        // while retaining the drain flag and diagnostic.
        if (hash_equals($final_fingerprint, self::settings_fingerprint($old_value))) {
            Order_Lock::release_settings();
        }
        return $value;
    }

    /** @param mixed $candidate */
    private static function normalize_secret_option($candidate, string $existing, string $prefix): string
    {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return $existing;
        }
        if (str_starts_with($candidate, 'enc:v1:')) {
            $plaintext = Secrets::decrypt($candidate);
            if (str_starts_with($plaintext, $prefix)) {
                return $candidate;
            }
            update_option('bactive_paymongo_secret_update_error', time(), false);
            return $existing;
        }
        if (!str_starts_with($candidate, $prefix)
            || !preg_match('/^[A-Za-z0-9_]+$/D', $candidate)
            || strlen($candidate) < 20) {
            update_option('bactive_paymongo_secret_update_error', time(), false);
            return $existing;
        }

        try {
            $encrypted = Secrets::encrypt($candidate);
            delete_option('bactive_paymongo_secret_update_error');
            return $encrypted;
        } catch (\Throwable $error) {
            update_option('bactive_paymongo_secret_update_error', time(), false);
            return $existing;
        }
    }

    /** @param mixed $old_value @param mixed $value */
    public static function after_settings_update($old_value, $value): void
    {
        if (!is_array($value)) {
            // A later filter can still replace our safe value after the
            // gateway-specific pre-update filter ran. The credentials may be
            // gone at this point, so fail closed and retain a durable alarm.
            Reconciler::bump_config_generation();
            Reconciler::set_draining(true);
            update_option(
                'bactive_paymongo_settings_write_error',
                array('recorded_at' => time(), 'code' => 'settings_shape_invalid'),
                false
            );
            Order_Lock::release_settings();
            return;
        }

        $fingerprint = self::settings_fingerprint($value);
        try {
            $stored = get_option('woocommerce_' . GATEWAY_ID . '_settings', null);
            if (!Order_Lock::settings_held_for($fingerprint)
                || !is_array($stored)
                || !hash_equals($fingerprint, self::settings_fingerprint($stored))) {
                Reconciler::set_draining(true);
                update_option(
                    'bactive_paymongo_settings_write_error',
                    array('recorded_at' => time(), 'code' => 'settings_readback_mismatch'),
                    false
                );
                return;
            }

            if (($value['enabled'] ?? 'no') !== 'yes') {
                Reconciler::set_draining(true);
                delete_option('bactive_paymongo_settings_write_error');
                return;
            }

            $gateway = new self(false);
            $live = !$gateway->is_test_mode();
            $reopen_fence = hash_equals(self::$settings_reopen_fingerprint, $fingerprint)
                ? self::$settings_reopen_fence : '';
            try {
                $ready = $reopen_fence === ''
                    ? new \WP_Error('paymongo_reopen_fence_unavailable', 'PayMongo reopening could not be fenced.')
                    : Readiness::verify_and_provision($gateway, $live);
            } catch (\Throwable $error) {
                $ready = new \WP_Error('paymongo_setup_failed', 'PayMongo setup failed safely.');
            }

            $stored = get_option('woocommerce_' . GATEWAY_ID . '_settings', null);
            if (is_wp_error($ready)
                || !Order_Lock::renew_settings($fingerprint)
                || !is_array($stored)
                || !hash_equals($fingerprint, self::settings_fingerprint($stored))
                || get_option('bactive_paymongo_disable_drain_error', false) !== false
                || !Reconciler::reopen_after_verification($reopen_fence)) {
                Reconciler::set_draining(true);
                update_option(
                    'bactive_paymongo_settings_write_error',
                    array(
                        'recorded_at' => time(),
                        'code' => is_wp_error($ready)
                            ? sanitize_key($ready->get_error_code())
                            : 'settings_final_verification_failed',
                    ),
                    false
                );
                return;
            }

            delete_option('bactive_paymongo_settings_write_error');
            delete_option('bactive_paymongo_settings_write_rejected');
            // The drain CAS has succeeded. The active database lease still
            // blocks issuance until its exact token is released below.
        } finally {
            Order_Lock::release_settings();
            self::$settings_reopen_fence = '';
            self::$settings_reopen_fingerprint = '';
        }
    }

    /**
     * A later global pre_update_option filter can replace the normalized value
     * after our option-specific filter. Recheck the final value at WordPress's
     * last pre-SQL action and retain the old database credentials on mismatch.
     *
     * @param mixed $option
     * @param mixed $old_value
     * @param mixed $value
     * @throws \Error When the final write no longer matches this request's lease.
     */
    public static function guard_settings_update_commit($option, $old_value, $value): void
    {
        if ((string) $option !== 'woocommerce_' . GATEWAY_ID . '_settings') {
            return;
        }

        $fingerprint = is_array($value) ? self::settings_fingerprint($value) : '';
        if ($fingerprint !== '' && Order_Lock::settings_held_for($fingerprint)) {
            return;
        }

        Reconciler::set_draining(true);
        update_option(
            'bactive_paymongo_settings_write_rejected',
            array('recorded_at' => time(), 'code' => 'settings_commit_mismatch'),
            false
        );
        Order_Lock::release_settings();
        throw new \Error('PayMongo settings changed outside the guarded pre-update value.');
    }

    /** @param mixed $option @param mixed $value */
    public static function after_settings_add($option, $value): void
    {
        if ((string) $option !== 'woocommerce_' . GATEWAY_ID . '_settings') {
            Reconciler::set_draining(true);
            Order_Lock::release_settings();
            return;
        }
        self::after_settings_update(array(), $value);
    }

    /**
     * WordPress routes an update of a missing option through add_option(), but
     * a direct add otherwise bypasses every pre-update normalization and drain
     * control. Permit only the exact value already owned by this request's
     * settings lease; throw before WordPress inserts anything else.
     *
     * @param mixed $option
     * @param mixed $value
     * @throws \Error When the add did not originate in the guarded update lane.
     */
    public static function guard_settings_add($option, $value): void
    {
        if ((string) $option !== 'woocommerce_' . GATEWAY_ID . '_settings') {
            return;
        }

        $fingerprint = is_array($value) ? self::settings_fingerprint($value) : '';
        if ($fingerprint !== '' && Order_Lock::settings_held_for($fingerprint)) {
            return;
        }

        Reconciler::set_draining(true);
        update_option(
            'bactive_paymongo_settings_write_rejected',
            array('recorded_at' => time(), 'code' => 'settings_add_unguarded'),
            false
        );
        throw new \Error('PayMongo settings must be created through the guarded update path.');
    }

    /**
     * WordPress has no cancellable pre-delete option filter. Throwing before
     * its SQL delete is the only way to retain database-held credentials when
     * a session cannot first be drained with the old configuration.
     *
     * @param mixed $option
     * @throws \Error When deletion is contended or any tracked state remains.
     */
    public static function guard_settings_delete($option): void
    {
        if ((string) $option !== 'woocommerce_' . GATEWAY_ID . '_settings') {
            return;
        }

        $current = get_option('woocommerce_' . GATEWAY_ID . '_settings', null);
        $fingerprint = hash('sha256', 'delete|' . serialize($current));
        if (!Order_Lock::acquire_settings($fingerprint)) {
            update_option(
                'bactive_paymongo_settings_write_rejected',
                array('recorded_at' => time(), 'code' => 'settings_delete_contended'),
                false
            );
            throw new \Error('PayMongo settings deletion collided with another settings writer.');
        }

        Reconciler::set_draining(true);
        Reconciler::bump_config_generation();
        $result = Reconciler::expire_all_tracked(
            new self(false),
            static fn(): bool => Order_Lock::renew_settings($fingerprint)
        );
        if (is_wp_error($result) || Reconciler::has_tracked_orders()
            || Reconciler::has_unresolved_external_incidents()) {
            Order_Lock::insert_option(
                'bactive_paymongo_disable_drain_error',
                array(
                    'recorded_at' => time(),
                    'owner' => 'settings',
                    'code' => is_wp_error($result)
                        ? sanitize_key($result->get_error_code())
                        : 'settings_delete_active_orders',
                )
            );
            Order_Lock::release_settings();
            throw new \Error('PayMongo settings cannot be deleted while payment state remains active or unresolved.');
        }
        $fresh = get_option('woocommerce_' . GATEWAY_ID . '_settings', null);
        if (!Order_Lock::renew_settings($fingerprint) || $fresh !== $current) {
            update_option(
                'bactive_paymongo_settings_write_rejected',
                array('recorded_at' => time(), 'code' => 'settings_delete_lease_or_value_changed'),
                false
            );
            Order_Lock::release_settings();
            throw new \Error('PayMongo settings changed before the guarded delete reached storage.');
        }
        // Keep the exact settings lease until WordPress confirms the SQL
        // delete through after_settings_delete(). Checkout remains draining.
    }

    /** @param mixed $option */
    public static function after_settings_delete($option): void
    {
        if ((string) $option !== 'woocommerce_' . GATEWAY_ID . '_settings') {
            return;
        }
        Reconciler::set_draining(true);
        Order_Lock::release_settings();
    }

    /** @param array<string,mixed> $settings */
    private static function settings_fingerprint(array $settings): string
    {
        $canonicalize = static function ($value) use (&$canonicalize) {
            if (!is_array($value)) {
                return $value;
            }
            foreach ($value as $key => $item) {
                $value[$key] = $canonicalize($item);
            }
            ksort($value, SORT_STRING);
            return $value;
        };
        return hash('sha256', serialize($canonicalize($settings)));
    }

    public function is_available(): bool
    {
        if (!$this->rollout_allows_issuance()
            || Reconciler::is_draining()
            || Order_Lock::settings_write_active()
            || $this->loaded_config_generation !== Reconciler::config_generation()
            || !$this->loaded_payment_config_is_current()
            || !parent::is_available()
            || get_woocommerce_currency() !== 'PHP') {
            return false;
        }

        return Readiness::is_ready($this, !$this->is_test_mode());
    }

    /** Only an explicit opt-out opens issuance to the public; callbacks stay independent. */
    private function rollout_allows_issuance(): bool
    {
        // WC_Settings_API::get_option() fills missing defaults into settings.
        // Do not mutate this snapshot: currentness compares it with storage.
        return ($this->settings['restricted_rollout'] ?? 'yes') === 'no'
            || current_user_can('manage_woocommerce')
            || current_user_can('manage_options');
    }

    /**
     * Acquire before WC_Checkout::update_session() can dirty and later replace
     * the shared session row. This covers forged/unavailable payment methods as
     * well as PayMongo and COD submissions.
     *
     * @throws \Exception When another request owns this Woo session.
     */
    public function acquire_checkout_submission_lock(): void
    {
        $identity = $this->checkout_submission_identity();
        if ($identity !== '' && Order_Lock::acquire_checkout($identity)) {
            return;
        }
        throw new \Exception(__('This checkout is already being submitted. Wait for the first response, then reload before trying again.', 'bactive-paymongo'));
    }

    /** @param mixed $data @param mixed $errors */
    public function guard_checkout_submission($data, $errors): void
    {
        if (Order_Lock::checkout_held_by_request() && Order_Lock::renew_checkout()) {
            return;
        }

        $message = __('This checkout is already being submitted. Wait for the first response, then reload before trying again.', 'bactive-paymongo');
        if (is_object($errors) && is_callable(array($errors, 'add'))) {
            $errors->add('paymongo_checkout_busy', $message);
            return;
        }
        throw new \Exception($message);
    }

    public function release_checkout_submission_lock(): void
    {
        Order_Lock::release_checkout();
    }

    /** @return array{result:string,redirect?:string} */
    public function process_payment($order_id): array
    {
        $checkout_request_fenced = Order_Lock::checkout_held_by_request();
        try {
            // Recheck at the payment boundary, not only while rendering the
            // checkout list: stale forms, order-pay and direct calls must deny
            // before touching an order or issuing a provider request.
            if ($this->enabled !== 'yes' || !$this->rollout_allows_issuance()) {
                wc_add_notice(__('Online payments are not yet available. Please choose another payment method.', 'bactive-paymongo'), 'error');
                return array('result' => 'fail');
            }
            if (Order_Lock::checkout_held_by_request() && !Order_Lock::renew_checkout()) {
                wc_add_notice(__('Checkout lost its session fence. Reload before trying again.', 'bactive-paymongo'), 'error');
                return array('result' => 'fail');
            }
            if (Reconciler::is_draining() || Order_Lock::settings_write_active()) {
                wc_add_notice(__('PayMongo is temporarily unavailable while existing sessions are reconciled. Please choose Cash on Delivery or try again later.', 'bactive-paymongo'), 'error');
                return array('result' => 'fail');
            }
            if ($this->loaded_config_generation !== Reconciler::config_generation()
                || !$this->loaded_payment_config_is_current()) {
                wc_add_notice(__('PayMongo configuration changed. Reload checkout before trying again.', 'bactive-paymongo'), 'error');
                return array('result' => 'fail');
            }

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
                if (!self::refresh_order($order)) {
                    wc_add_notice(__('The order could not be verified before payment. Please reload checkout.', 'bactive-paymongo'), 'error');
                    return array('result' => 'fail');
                }
                if ($order->is_paid() || !$order->needs_payment()) {
                    if ($order->is_paid()
                        && !self::has_inconsistent_provider_payment_state($order)
                        && (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) === ''
                        && (string) $order->get_meta(Reconciler::UNRESOLVED_META, true) === ''
                        && (string) $order->get_meta('_bactive_paymongo_review_required', true) === '') {
                        return array('result' => 'success', 'redirect' => $order->get_checkout_order_received_url());
                    }
                    wc_add_notice(__('This order has unresolved PayMongo payment state. Do not pay again; contact support.', 'bactive-paymongo'), 'error');
                    return array('result' => 'fail');
                }
                if ($order->get_payment_method() !== $this->id
                    || $order->get_currency() !== 'PHP'
                    || Integrity::amount_to_minor((string) $order->get_total()) !== $amount
                    || !$this->checkout_state_is_payable($order)) {
                    wc_add_notice(__('The order changed before PayMongo checkout. Please reload and try again.', 'bactive-paymongo'), 'error');
                    return array('result' => 'fail');
                }
                if (Reconciler::is_draining() || Order_Lock::settings_write_active()) {
                    wc_add_notice(__('PayMongo is temporarily unavailable while existing sessions are reconciled. Please choose Cash on Delivery or try again later.', 'bactive-paymongo'), 'error');
                    return array('result' => 'fail');
                }
                if ($this->loaded_config_generation !== Reconciler::config_generation()
                    || !$this->loaded_payment_config_is_current()) {
                    wc_add_notice(__('PayMongo configuration changed. Reload checkout before trying again.', 'bactive-paymongo'), 'error');
                    return array('result' => 'fail');
                }
                $result = $this->create_or_reuse_session($order, $amount, $live);
                if (($result['result'] ?? '') === 'success'
                    && (Reconciler::is_draining() || Order_Lock::settings_write_active())) {
                    $fresh = wc_get_order((int) $order_id);
                    if ($fresh instanceof \WC_Order) {
                        $this->expire_all_for_order($fresh);
                    }
                    wc_add_notice(__('PayMongo entered maintenance before redirect. No payment link was issued; please choose Cash on Delivery or try again later.', 'bactive-paymongo'), 'error');
                    return array('result' => 'fail');
                }
                return $result;
            } catch (\Throwable $error) {
                $this->safe_log('error', 'checkout_exception', array('order_id' => (int) $order_id));
                wc_add_notice(__('Payment setup failed safely. No payment was confirmed; please try again.', 'bactive-paymongo'), 'error');
                return array('result' => 'fail');
            } finally {
                $this->release_order_lock((int) $order_id);
            }
        } finally {
            // WC has already persisted order_awaiting_payment before invoking
            // this method. Releasing here leaves any retry to the existing-
            // order guard instead of allowing two first-time order creations.
            if ($checkout_request_fenced && (int) $order_id > 0) {
                Order_Lock::release((int) $order_id);
            }
            Order_Lock::release_checkout();
        }
    }

    /**
     * Expire every unpaid Checkout Session before an order leaves this gateway
     * or otherwise stops accepting payment. Each attempt carries its own mode,
     * so sandbox and live credentials are never interchanged.
     *
     * @param mixed $order
     * @param mixed $data_store
     */
    public function handle_order_before_save($order, $data_store): void
    {
        if (!$order instanceof \WC_Order
            || $order->get_id() < 1
            || !$this->potentially_payment_relevant_save($order)) {
            return;
        }

        $held_by_request = Order_Lock::held_by_request($order->get_id());
        if ($held_by_request) {
            // The caller that owns the database fence is the authoritative
            // writer. Re-reading and copying the stored snapshot here would
            // erase this plugin's just-prepared request/payment facts before
            // WC_Order::save() can persist them.
            if (!Order_Lock::renew($order->get_id())) {
                $this->abort_order_save($order, 'The PayMongo order fence was lost before its authorized save.');
            }
            if (isset($this->checkout_locks[$order->get_id()])
                && !$this->checkout_state_is_payable($order)) {
                $this->abort_order_save($order, 'Checkout attempted to persist an order that was no longer safely payable.');
            }
            if (isset($this->save_locks[$order->get_id()])) {
                ++$this->save_locks[$order->get_id()];
            }
            return;
        }
        if (!Order_Lock::acquire($order->get_id())) {
            $this->safe_log('warning', 'payment_order_lock_busy', array('order_id' => $order->get_id()));
            $this->abort_order_save($order, 'A PayMongo order transition collided with payment processing.');
        }

        try {
            $stored = wc_get_order($order->get_id());
            if (!$stored instanceof \WC_Order || !self::refresh_order($stored)) {
                $this->abort_order_save($order, 'A PayMongo order transition could not read its stored payment state.');
            }
            $attempts = $this->attempts($stored);
            if (!self::has_protected_payment_state($stored)) {
                Order_Lock::release($order->get_id());
                return;
            }

            // A WC_Order loaded before a webhook may wait on this fence and
            // then try to persist stale status/payment fields after settlement.
            // HPOS writes its pending change set, so compare every protected
            // field with the fresh stored order before preserving our metadata.
            if ($this->protected_payment_state_change_is_unsafe($order, $stored)) {
                $this->abort_order_save($order, 'A stale order object attempted to overwrite protected PayMongo payment state.');
            }

            // Carry the authoritative attempt set into the imminent save so a
            // stale WC_Order object cannot erase a newly issued session.
            $this->copy_protected_payment_meta($stored, $order);
            if ($this->transition_requires_guard($order)
                && !$this->guard_order_transition($order, $attempts)) {
                $this->abort_order_save($order, 'A PayMongo order transition failed closed.');
            }

            // Keep a lock acquired here through the actual data-store write.
            $this->save_locks[$order->get_id()] = 1;
        } catch (\Throwable $error) {
            Order_Lock::release($order->get_id());
            throw $error;
        }
    }

    /** @param mixed $order @param mixed $data_store */
    public function handle_order_after_save($order, $data_store): void
    {
        if (!$order instanceof \WC_Order || !isset($this->save_locks[$order->get_id()])) {
            return;
        }
        --$this->save_locks[$order->get_id()];
        if ($this->save_locks[$order->get_id()] <= 0) {
            unset($this->save_locks[$order->get_id()]);
            Order_Lock::release($order->get_id());
        }
    }

    /**
     * Failures from this classic-checkout hook propagate to the checkout AJAX
     * response, unlike exceptions thrown inside WC_Order::save().
     *
     * @param mixed $order
     * @param mixed $data
     * @throws \Exception When the prior payment state cannot be made safe.
     */
    public function handle_checkout_create_order($order, $data): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        if (Order_Lock::checkout_held_by_request() && !Order_Lock::renew_checkout()) {
            throw new \Exception(__('Checkout lost its session fence. Reload before trying again.', 'bactive-paymongo'));
        }

        $this->guard_prior_awaiting_order($order->get_id());
        if ($order->get_id() < 1) {
            if (in_array($order->get_payment_method(), array($this->id, 'cod'), true)
                && !Order_Lock::checkout_held_by_request()) {
                throw new \Exception(__('Checkout did not hold its required session fence. Reload before trying again.', 'bactive-paymongo'));
            }
            if ($order->get_payment_method() === $this->id) {
                $snapshot = $this->checkout_order_snapshot($order);
                if ($snapshot === null) {
                    throw new \Exception(__('Checkout items could not be verified before saving. Reload before trying again.', 'bactive-paymongo'));
                }
                $this->checkout_snapshots[spl_object_id($order)] = $snapshot;
            }
            return;
        }

        $order_id = $order->get_id();
        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            throw new \Exception(__('Payment reconciliation is in progress. Please wait a moment and submit checkout again.', 'bactive-paymongo'));
        }

        try {
            $stored = wc_get_order($order_id);
            if (!$stored instanceof \WC_Order
                || !self::refresh_order($stored)
                || !$this->checkout_state_is_payable($stored)) {
                throw new \Exception(__('This order changed while payment was being checked. Reload checkout before trying again.', 'bactive-paymongo'));
            }

            $attempts = self::order_attempts($stored);
            if ($attempts !== array()) {
                $order->update_meta_data(self::ATTEMPTS_META, $attempts);
                if (!$this->guard_order_transition($order, $attempts)) {
                    throw new \Exception(__('The prior PayMongo session could not be closed safely. Do not pay again; contact support.', 'bactive-paymongo'));
                }
            }

            // Retain the current-order fence even when the stored attempt set
            // is empty. Otherwise another checkout request can create its first
            // PayMongo attempt between this read and Woo's eventual order save.
            if (!$held_by_request) {
                $this->checkout_locks[$order_id] = true;
            }
        } catch (\Throwable $error) {
            if (!$held_by_request) {
                Order_Lock::release($order_id);
            }
            if ($error instanceof \Exception) {
                throw $error;
            }
            throw new \Exception(__('Checkout could not verify the prior PayMongo session. Please try again.', 'bactive-paymongo'));
        }
    }

    /**
     * WooCommerce swallows exceptions thrown from WC_Order::save(). Verify the
     * exact stored checkout state before the checkout flow is allowed to call
     * the selected gateway's process_payment() method.
     *
     * @param mixed $order
     * @throws \Exception When the stored checkout does not match this request.
     */
    public function finalize_checkout_lock($order): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $object_id = spl_object_id($order);
        $expected_snapshot = $this->checkout_snapshots[$object_id] ?? null;
        if (!isset($this->checkout_locks[$order->get_id()]) && is_array($expected_snapshot)) {
            if ($order->get_id() < 1 || !Order_Lock::acquire($order->get_id())) {
                throw new \Exception(__('Checkout could not fence its newly stored order. Reload before trying again.', 'bactive-paymongo'));
            }
            $this->checkout_locks[$order->get_id()] = true;
        }
        if (!isset($this->checkout_locks[$order->get_id()])) {
            return;
        }

        $order_id = $order->get_id();
        if (!Order_Lock::renew($order_id)
            || (Order_Lock::checkout_held_by_request() && !Order_Lock::renew_checkout())) {
            throw new \Exception(__('Checkout lost its payment-order fence. Reload before trying again.', 'bactive-paymongo'));
        }
        $stored = wc_get_order($order_id);
        $incoming_amount = Integrity::amount_to_minor((string) $order->get_total());
        if (!$stored instanceof \WC_Order
            || !self::refresh_order($stored)) {
            throw new \Exception(__('Checkout payment details could not be read back. Reload before trying again.', 'bactive-paymongo'));
        }
        $stored_amount = Integrity::amount_to_minor((string) $stored->get_total());
        $stored_snapshot = is_array($expected_snapshot) ? $this->checkout_order_snapshot($stored) : null;
        if (!$this->checkout_state_is_payable($order)
            || !$this->checkout_state_is_payable($stored)
            || $incoming_amount === null
            || $stored_amount !== $incoming_amount
            || $stored->get_payment_method() !== $order->get_payment_method()
            || $stored->get_status() !== $order->get_status()
            || $stored->get_currency() !== $order->get_currency()
            || self::order_attempts($stored) !== self::order_attempts($order)
            || (is_array($expected_snapshot) && $stored_snapshot !== $expected_snapshot)
            || ($stored->get_payment_method() === 'cod' && !$this->cod_transition_is_valid($stored))) {
            throw new \Exception(__('Checkout payment details were not stored exactly. Reload before trying again.', 'bactive-paymongo'));
        }

        unset($this->checkout_snapshots[$object_id]);
        // Keep the order fence through WC()->session->save_data() and the
        // selected gateway's process_payment(). The PayMongo gateway releases
        // it in its outer finally; COD and checkout failures release at shutdown.
    }

    /** @param mixed $order */
    public function release_checkout_lock($order): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }
        unset($this->checkout_snapshots[spl_object_id($order)]);
        if (!isset($this->checkout_locks[$order->get_id()])) {
            return;
        }
        unset($this->checkout_locks[$order->get_id()]);
        Order_Lock::release($order->get_id());
    }

    public function release_request_locks(): void
    {
        $order_ids = array_unique(array_merge(
            array_keys($this->save_locks),
            array_keys($this->checkout_locks),
            array_keys($this->delete_locks)
        ));
        foreach ($order_ids as $order_id) {
            Order_Lock::release((int) $order_id);
        }
        $this->save_locks = array();
        $this->checkout_locks = array();
        $this->delete_locks = array();
        $this->checkout_snapshots = array();
        Order_Lock::release_checkout();
    }

    /**
     * Woo creates a new order when the cart hash changes. Drain the previous
     * session order first even when the new order has no ID yet, otherwise the
     * customer can keep both an old PayMongo URL and a new COD order payable.
     *
     * @throws \Exception When the prior order cannot be made unpayable.
     */
    private function guard_prior_awaiting_order(int $current_order_id): void
    {
        if (!function_exists('WC') || !WC() || !isset(WC()->session)) {
            return;
        }
        $prior_id = absint(WC()->session->get('order_awaiting_payment'));
        if ($prior_id < 1 || $prior_id === $current_order_id) {
            return;
        }

        $held_by_request = Order_Lock::held_by_request($prior_id);
        if (!Order_Lock::acquire($prior_id)) {
            throw new \Exception(__('The prior PayMongo order is still being reconciled. Wait a moment before creating another order.', 'bactive-paymongo'));
        }
        try {
            $prior = wc_get_order($prior_id);
            if (!$prior instanceof \WC_Order) {
                throw new \Exception(__('The prior PayMongo order could not be read. Do not submit a second order; reload and contact support.', 'bactive-paymongo'));
            }
            if (!self::refresh_order($prior)) {
                throw new \Exception(__('The prior PayMongo order could not be verified. Wait a moment before creating another order.', 'bactive-paymongo'));
            }
            if (!self::has_protected_payment_state($prior)) {
                return;
            }
            if (!$this->checkout_state_is_payable($prior)) {
                throw new \Exception(__('The prior PayMongo order may already be paid. Do not submit a second order; reload and contact support if needed.', 'bactive-paymongo'));
            }

            if (self::has_outstanding_attempts($prior)) {
                if (!$this->expire_all_for_order($prior)) {
                    throw new \Exception(__('The prior PayMongo session could not be closed safely. Do not submit a second order.', 'bactive-paymongo'));
                }
                // Require a fresh checkout submission after the destructive
                // provider drain so the customer sees recalculated methods.
                throw new \Exception(__('The previous PayMongo session was closed safely. Please submit checkout again.', 'bactive-paymongo'));
            }
        } finally {
            if (!$held_by_request) {
                Order_Lock::release($prior_id);
            }
        }
    }

    private function transition_requires_guard(\WC_Order $order): bool
    {
        $changes = $order->get_changes();
        $material_change = array_key_exists('total', $changes) || array_key_exists('currency', $changes);
        $leaving_gateway = $order->get_payment_method() !== $this->id;
        $cod_transition = $order->get_payment_method() === 'cod';
        $has_outstanding = self::has_outstanding_attempts($order);

        return $cod_transition
            || ($has_outstanding && ($leaving_gateway || !$order->needs_payment() || $material_change));
    }

    /** @param array<int,array<string,mixed>> $attempts */
    private function guard_order_transition(\WC_Order $order, array &$attempts): bool
    {
        if ($order->get_payment_method() === 'cod' && !$this->cod_transition_is_valid($order)) {
            $this->flag_review(
                $order,
                'cod_policy_invalid_after_paymongo',
                false,
                $this->review_mode_for_order($order)
            );
            return false;
        }

        if (!self::has_outstanding_attempts($order)) {
            return true;
        }

        $result = $this->expire_outstanding_attempts($order, $attempts, false);
        if (!$result['verified']) {
            if (!$result['lock_lost']) {
                $this->flag_review(
                    $order,
                    'session_expiry_unverified',
                    false,
                    $this->review_mode_for_order($order)
                );
            }
            return false;
        }
        return !self::has_outstanding_attempts($order);
    }

    /**
     * Prevent trash/permanent deletion from erasing the only correlation for
     * a still-payable provider session.
     *
     * @param mixed $check
     * @param mixed $order
     * @param mixed $force_delete
     * @return mixed
     */
    public function guard_order_deletion($check, $order, $force_delete)
    {
        if ($check !== null || !$order instanceof \WC_Order) {
            return $check;
        }

        $order_id = $order->get_id();
        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            return false;
        }
        $retain_lock = false;
        try {
            if (!self::refresh_order($order)) {
                return false;
            }
            if (!self::has_protected_payment_state($order)) {
                return null;
            }
            if ((string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== ''
                || (string) $order->get_meta(Reconciler::UNRESOLVED_META, true) !== ''
                || (string) $order->get_meta('_bactive_paymongo_review_required', true) !== ''
                || Webhook::review_resolution_recovery_pending($order)
                || Webhook::operator_disposition_recovery_pending($order)
                || self::has_inconsistent_provider_payment_state($order)) {
                return false;
            }

            if (self::has_outstanding_attempts($order)) {
                $this->expire_all_for_order($order);
                // Even a successful drain requires a second deliberate delete
                // after the operator can inspect whether a payment won the race.
                return false;
            }
            if (!$held_by_request) {
                $this->delete_locks[$order_id] = true;
                $retain_lock = true;
            }
            return null;
        } finally {
            if (!$held_by_request && !$retain_lock) {
                Order_Lock::release($order_id);
            }
        }
    }

    /** @param mixed $order */
    public function block_unsafe_delete_action($order_id, $order): void
    {
        $order_id = absint($order_id);
        if ($order_id < 1) {
            return;
        }
        $held_by_request = Order_Lock::held_by_request($order_id);
        if (!Order_Lock::acquire($order_id)) {
            throw new \RuntimeException('PayMongo order deletion blocked by concurrent payment processing.');
        }
        $retain_lock = false;
        try {
            $order = $order instanceof \WC_Order ? $order : wc_get_order($order_id);
            if (!$order instanceof \WC_Order || !self::refresh_order($order)) {
                throw new \RuntimeException('PayMongo order deletion blocked because payment state could not be verified.');
            }
            if (self::has_outstanding_attempts($order)
                || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== ''
                || (string) $order->get_meta(Reconciler::UNRESOLVED_META, true) !== ''
                || (string) $order->get_meta('_bactive_paymongo_review_required', true) !== ''
                || Webhook::review_resolution_recovery_pending($order)
                || Webhook::operator_disposition_recovery_pending($order)
                || self::has_inconsistent_provider_payment_state($order)) {
                throw new \RuntimeException('PayMongo order deletion blocked while payment reconciliation is unresolved.');
            }
            if (self::has_protected_payment_state($order)) {
                if (!$held_by_request) {
                    $this->delete_locks[$order_id] = true;
                    $retain_lock = true;
                }
            }
        } finally {
            if (!$held_by_request && !$retain_lock) {
                Order_Lock::release($order_id);
            }
        }
    }

    public function release_delete_lock($order_id): void
    {
        $order_id = absint($order_id);
        if ($order_id < 1 || !isset($this->delete_locks[$order_id])) {
            return;
        }
        unset($this->delete_locks[$order_id]);
        Order_Lock::release($order_id);
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

    /**
     * PayMongo's cancel URL does not expire a session. This signed callback
     * expires and reads back the exact session before restarting checkout, so
     * WooCommerce rebuilds COD eligibility and its fee from the cart.
     */
    public function handle_cancel_request(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            wp_die(esc_html__('Invalid PayMongo cancellation request.', 'bactive-paymongo'), '', array('response' => 405));
        }

        $order_id = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;
        $attempt_fingerprint = isset($_GET['attempt'])
            ? trim(wp_unslash((string) $_GET['attempt']))
            : '';
        $token = isset($_GET['token']) ? trim(wp_unslash((string) $_GET['token'])) : '';
        $order = $order_id > 0 ? wc_get_order($order_id) : null;
        $attempt = $order instanceof \WC_Order
            ? $this->attempt_by_fingerprint($order, $attempt_fingerprint)
            : null;
        if (!$order instanceof \WC_Order
            || $attempt === null
            || !preg_match('/^[a-f0-9]{64}$/D', $attempt_fingerprint)
            || !preg_match('/^[a-f0-9]{64}$/D', $token)
            || !hash_equals($this->cancel_token($order_id, $attempt), $token)) {
            wp_die(esc_html__('Invalid PayMongo cancellation request.', 'bactive-paymongo'), '', array('response' => 400));
        }

        if (!Order_Lock::acquire($order_id)) {
            wp_die(esc_html__('Payment reconciliation is in progress. Please refresh in a moment.', 'bactive-paymongo'), '', array('response' => 409));
        }

        $destination = wc_get_checkout_url();
        $notice = __('The PayMongo session was cancelled safely. You can now choose another payment method.', 'bactive-paymongo');
        $notice_type = 'notice';
        $fatal_message = '';
        $fatal_status = 409;
        try {
            if (!self::refresh_order($order)) {
                $fatal_message = __('The order could not be verified for cancellation.', 'bactive-paymongo');
            } else {
                $attempt = $this->attempt_by_fingerprint($order, $attempt_fingerprint);
            }
            if ($fatal_message === '' && ($attempt === null
                || !hash_equals($this->cancel_token($order_id, $attempt), $token))) {
                $fatal_message = __('Invalid PayMongo cancellation request.', 'bactive-paymongo');
                $fatal_status = 400;
            } elseif ($fatal_message !== '') {
                // Release the order fence in finally before emitting the HTTP
                // error response.
            } elseif (!empty($attempt['paid_at']) || !empty($attempt['payment_id']) || $order->is_paid()) {
                $destination = $order->get_checkout_order_received_url();
                $notice = __('This PayMongo order is already paid or reconciling; a second checkout was not opened.', 'bactive-paymongo');
                $notice_type = 'notice';
            } elseif (!empty($attempt['expired_at'])
                || !empty($attempt['request_rejected_at'])
                || !empty($attempt['request_aborted_at'])) {
                if (self::has_outstanding_attempts($order)) {
                    $destination = $order->get_checkout_order_received_url();
                    $notice = __('That older PayMongo session is already closed. A newer payment session remains active and was not changed.', 'bactive-paymongo');
                } else {
                    $destination = wc_get_checkout_url();
                    $notice = __('That PayMongo session was already closed safely. You can now return to checkout.', 'bactive-paymongo');
                }
                $notice_type = 'notice';
            } else {
                $destination = $order->get_checkout_order_received_url();
                $attempts = $this->attempts($order);
                // The signed URL authenticates exactly one immutable,
                // mode-bound request identity. Never let a replay from an
                // older browser tab or a duplicate generation drain another
                // sandbox/live session.
                $result = $this->expire_outstanding_attempts(
                    $order,
                    $attempts,
                    true,
                    $attempt_fingerprint
                );
                if ($result['lock_lost']) {
                    // Another request now owns the database fence. Do not read,
                    // mutate, or save this stale order object.
                    $fatal_message = __('Payment reconciliation took ownership of this order. Please refresh in a moment.', 'bactive-paymongo');
                    $fatal_status = 409;
                } elseif (!$result['verified']) {
                    $refreshed = self::refresh_order($order);
                    if ($refreshed && $order->is_paid()) {
                        $notice = __('Your PayMongo payment was confirmed. A second checkout was not opened.', 'bactive-paymongo');
                        $notice_type = 'notice';
                    } elseif ($refreshed) {
                        $this->preserve_or_flag_unverified_cancel(
                            $order,
                            $attempts,
                            $result,
                            is_array($attempt) ? (string) ($attempt['session_id'] ?? '') : ''
                        );
                        $notice = __('We could not safely close the prior payment session. The order is on hold; please contact support before paying again.', 'bactive-paymongo');
                        $notice_type = 'error';
                    } else {
                        update_option(
                            'bactive_paymongo_disable_drain_error',
                            array('recorded_at' => time(), 'code' => 'cancel_final_read_failed'),
                            false
                        );
                        $notice = __('The prior payment state could not be read safely. A second checkout was not opened; contact support.', 'bactive-paymongo');
                        $notice_type = 'error';
                    }
                    $destination = $order->get_checkout_order_received_url();
                } else {
                    $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
                    $order->save();
                    $destination = wc_get_checkout_url();
                }
            }
        } finally {
            Order_Lock::release($order_id);
        }

        if ($fatal_message !== '') {
            wp_die(esc_html($fatal_message), '', array('response' => $fatal_status));
        }

        wc_add_notice($notice, $notice_type);
        wp_safe_redirect($destination);
        exit;
    }

    /**
     * A paid readback may already have persisted a uniquely recoverable
     * quarantine. Never replace that evidence with a generic cancel error.
     *
     * @param array<int,array<string,mixed>> $attempts
     * @param array{verified:bool,settlement:bool,lock_lost:bool} $result
     */
    private function preserve_or_flag_unverified_cancel(
        \WC_Order $order,
        array $attempts,
        array $result,
        string $session_id
    ): bool {
        $existing_review = (string) $order->get_meta(Reconciler::UNRESOLVED_META, true);
        $review_required = (string) $order->get_meta('_bactive_paymongo_review_required', true);
        $durable_settlement_review = !empty($result['settlement'])
            && $existing_review !== ''
            && hash_equals($existing_review, $review_required)
            && (string) $order->get_meta(Reconciler::REQUIRED_META, true) === 'yes'
            && (int) $order->get_meta('_bactive_paymongo_review_incidents', true) > 0;
        if ($durable_settlement_review) {
            return true;
        }

        return Webhook::hold_order_for_review(
            $order,
            empty($result['settlement'])
                ? 'session_cancel_expiry_unverified'
                : 'paid_session_reconciliation_unverified',
            $session_id,
            empty($result['settlement']) ? array_slice($attempts, -self::MAX_ATTEMPTS) : null
        );
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

    public static function has_outstanding_attempts(\WC_Order $order): bool
    {
        foreach (self::order_attempts($order) as $attempt) {
            if ((!empty($attempt['session_id']) || !empty($attempt['request_pending']))
                && empty($attempt['request_rejected_at'])
                && empty($attempt['request_aborted_at'])
                && empty($attempt['expired_at'])
                && empty($attempt['paid_at'])
                && empty($attempt['payment_id'])) {
                return true;
            }
        }
        return false;
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
            if (($attempt['fingerprint'] ?? '') !== $fingerprint
                || ($attempt['mode'] ?? '') !== ($live ? 'live' : 'test')
                || (int) ($attempt['config_generation'] ?? -1) !== $this->loaded_config_generation) {
                continue;
            }
            if (!empty($attempt['request_rejected_at'])
                || !empty($attempt['request_aborted_at'])
                || !empty($attempt['expired_at'])
                || !empty($attempt['paid_at'])
                || !empty($attempt['payment_id'])) {
                continue;
            }

            $age = time() - (int) ($attempt['created_at'] ?? 0);
            if ($age > 82800) {
                if (!empty($attempt['session_id'])) {
                    continue;
                }
                wc_add_notice(
                    __('A previous PayMongo request could not be reconciled automatically. Please contact support before trying again.', 'bactive-paymongo'),
                    'error'
                );
                return array('result' => 'fail');
            }

            $checkout_url = (string) ($attempt['checkout_url'] ?? '');
            if ($checkout_url !== '' && $this->valid_checkout_url($checkout_url)) {
                return array('result' => 'success', 'redirect' => $checkout_url);
            }

            return $this->submit_attempt($order, $amount, $attempts, $index, $live);
        }

        if (!$this->expire_changed_attempts($order, $attempts)) {
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
                'bactive-checkout-%s-%s-%d-%d',
                substr(hash('sha256', home_url('/')), 0, 16),
                $live ? 'live' : 'test',
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
        if (!Order_Lock::renew($order->get_id())) {
            $this->safe_log('critical', 'checkout_request_order_lock_lost', array('order_id' => $order->get_id()));
            wc_add_notice(__('Payment setup changed in another request. Reload checkout before trying again.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }
        $config_generation = $this->loaded_config_generation;
        if (Order_Lock::settings_write_active()
            || $config_generation !== Reconciler::config_generation()
            || !$this->loaded_payment_config_is_current()) {
            wc_add_notice(__('PayMongo configuration changed before payment initialization. Reload checkout and try again.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }
        $attempts[$index]['config_generation'] = $config_generation;
        $attempts[$index]['request_pending'] = true;
        if (empty($attempts[$index]['request_started_at'])) {
            $attempts[$index]['request_started_at'] = time();
        }
        $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
        Reconciler::mark_required($order);
        $order->save();

        $persisted_request = wc_get_order($order->get_id());
        if (!$persisted_request instanceof \WC_Order
            || !self::refresh_order($persisted_request)
            || !$this->persisted_attempt_matches($persisted_request, $attempts[$index])) {
            $this->safe_log('critical', 'checkout_request_persistence_failed', array('order_id' => $order->get_id()));
            wc_add_notice(__('PayMongo checkout could not be recorded safely. No payment request was sent.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }
        if (Reconciler::is_draining() || Order_Lock::settings_write_active()) {
            $attempts[$index]['request_pending'] = false;
            $attempts[$index]['request_aborted_at'] = time();
            $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
            $order->save();
            wc_add_notice(__('PayMongo entered maintenance before the payment request. Please choose Cash on Delivery or try again later.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }
        if (Reconciler::config_generation() !== $config_generation
            || !$this->loaded_payment_config_is_current()) {
            $attempts[$index]['request_pending'] = false;
            $attempts[$index]['request_aborted_at'] = time();
            $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
            $order->save();
            wc_add_notice(__('PayMongo configuration changed before the payment request. Reload checkout and try again.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }

        $attempt = $attempts[$index];
        if (self::attempt_request_fingerprint($attempt) === '') {
            $attempts[$index]['request_pending'] = false;
            $attempts[$index]['request_aborted_at'] = time();
            $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
            $order->save();
            $this->safe_log('critical', 'checkout_attempt_identity_invalid', array(
                'order_id' => $order->get_id(),
                'mode' => $live ? 'live' : 'test',
            ));
            wc_add_notice(__('PayMongo checkout could not be authorized safely. Reload checkout and try again.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }
        $key = Secrets::api_key($live, $this);
        $client = new Api_Client($key);
        $payload = $this->checkout_payload($order, $amount, $attempt);
        $response = $client->create_checkout_session($payload, (string) $attempt['idempotency_key']);
        if (!Order_Lock::renew($order->get_id())) {
            // The persisted request_pending marker is the recovery source of
            // truth. A different owner now decides what the provider did.
            $this->safe_log('critical', 'checkout_response_order_lock_lost', array('order_id' => $order->get_id()));
            wc_add_notice(__('Payment initialization is being reconciled. Do not pay again; refresh in a moment.', 'bactive-paymongo'), 'error');
            return array('result' => 'fail');
        }

        if (is_wp_error($response)) {
            $this->safe_log('error', $response->get_error_code(), array('order_id' => $order->get_id()));
            if (preg_match('/^paymongo_http_(?:400|401|403|404|405|422)(?:_|$)/D', $response->get_error_code())) {
                $attempts[$index]['request_pending'] = false;
                $attempts[$index]['request_rejected_at'] = time();
                $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
                $order->save();
            }
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
        $attempts[$index]['request_pending'] = false;
        $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
        Reconciler::mark_required($order);
        $order->add_order_note(
            sprintf(
                /* translators: %s: PayMongo checkout session ID */
                __('PayMongo checkout session authorized: %s. Awaiting signed payment confirmation.', 'bactive-paymongo'),
                sanitize_text_field($session_id)
            )
        );
        $order->save();

        $persisted = wc_get_order($order->get_id());
        if (!$persisted instanceof \WC_Order
            || !self::refresh_order($persisted)
            || !$this->persisted_attempt_matches($persisted, $attempts[$index])) {
            $client->expire_checkout_session(
                $session_id,
                'bactive-expire-' . ($live ? 'live-' : 'test-')
                    . substr(hash('sha256', ($live ? 'live|' : 'test|') . $session_id), 0, 48)
            );
            // The expire mutation response is not independent evidence and an
            // expired Checkout Session may still contain a paid payment.
            $readback = $client->retrieve_checkout_session($session_id);
            $status = is_array($readback)
                ? Integrity::checkout_session_status($readback, $session_id, $live)
                : null;
            $payment_state = is_array($readback)
                ? Integrity::checkout_session_payment_state($readback, $session_id, $live)
                : null;
            $paid_ids = is_array($payment_state) ? $payment_state['paid'] : array();
            $pending_ids = is_array($payment_state) ? $payment_state['pending'] : array();
            $safe_expired = $status === 'expired'
                && is_array($payment_state)
                && $paid_ids === array()
                && $pending_ids === array();
            $correlation_persisted = $this->persist_failed_session_correlation(
                $order->get_id(),
                $attempts[$index],
                $session_id,
                $safe_expired,
                array_values(array_unique(array_merge($paid_ids, $pending_ids)))
            );
            if (!$safe_expired || !$correlation_persisted) {
                Reconciler::record_global_drain_error(
                    array(
                        'recorded_at' => time(),
                        'code' => 'checkout_persistence_failed',
                        'order_id' => $order->get_id(),
                        'session_id' => sanitize_text_field($session_id),
                        'mode' => $live ? 'live' : 'test',
                    )
                );
            }
            $this->safe_log('critical', 'checkout_persistence_failed', array('order_id' => $order->get_id()));
            wc_add_notice(
                $safe_expired && $correlation_persisted
                    ? __('PayMongo checkout could not be recorded safely. The session was independently verified expired and no payment link was issued.', 'bactive-paymongo')
                    : __('PayMongo checkout could not be recorded safely. The session requires reconciliation and checkout remains unavailable.', 'bactive-paymongo'),
                'error'
            );
            return array('result' => 'fail');
        }

        if (Reconciler::config_generation() !== $config_generation
            || Reconciler::is_draining()
            || Order_Lock::settings_write_active()
            || !$this->loaded_payment_config_is_current()) {
            $attempts = self::order_attempts($persisted);
            $result = $this->expire_outstanding_attempts($persisted, $attempts, true);
            $settlement_resolved = false;
            if ($result['settlement']
                && Order_Lock::held_by_request($persisted->get_id())
                && self::refresh_order($persisted)) {
                $settlement_resolved = !self::has_outstanding_attempts($persisted)
                    && (string) $persisted->get_meta('_bactive_paymongo_settlement_pending', true) === ''
                    && (string) $persisted->get_meta(Reconciler::UNRESOLVED_META, true) === '';
            }
            if (!$result['lock_lost'] && !$result['settlement']) {
                $persisted->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
                if (!$result['verified']) {
                    $this->flag_review(
                        $persisted,
                        'config_change_session_unresolved',
                        false,
                        $live ? 'live' : 'test'
                    );
                }
                $persisted->save();
            }
            $safe = $result['verified'] || $settlement_resolved;
            if (!$safe) {
                Reconciler::record_global_drain_error(
                    array('recorded_at' => time(), 'code' => 'config_change_session_unresolved')
                );
            }
            wc_add_notice(
                $safe
                    ? __('PayMongo configuration changed during payment initialization. No payment link was issued; the session was closed or its payment was reconciled.', 'bactive-paymongo')
                    : __('PayMongo configuration changed during payment initialization. The session requires reconciliation and checkout remains unavailable.', 'bactive-paymongo'),
                'error'
            );
            return array('result' => 'fail');
        }

        return array('result' => 'success', 'redirect' => $checkout_url);
    }

    /**
     * Preserve the provider identity after the normal authorized-attempt save
     * fails. This recovery record is what lets the scheduler resolve a paid or
     * still-active session without asking the customer to pay again.
     *
     * @param array<string,mixed> $expected
     * @param array<int,string> $payment_ids
     */
    private function persist_failed_session_correlation(
        int $order_id,
        array $expected,
        string $session_id,
        bool $safe_expired,
        array $payment_ids
    ): bool {
        if (!Order_Lock::held_by_request($order_id) || !Order_Lock::renew($order_id)) {
            return false;
        }

        $fresh = wc_get_order($order_id);
        if (!$fresh instanceof \WC_Order || !self::refresh_order($fresh)) {
            return false;
        }

        $recovered = $expected;
        $recovered['session_id'] = $session_id;
        $recovered['checkout_url'] = '';
        $recovered['request_pending'] = false;
        $recovered['authorized_at'] = (int) ($recovered['authorized_at'] ?? time());
        $recovered['persistence_failed_at'] = time();
        unset($recovered['request_rejected_at'], $recovered['request_aborted_at']);
        if ($safe_expired) {
            $recovered['expired_at'] = time();
            unset($recovered['reconciliation_payment_ids']);
        } else {
            unset($recovered['expired_at']);
            if ($payment_ids !== array()) {
                $recovered['reconciliation_payment_ids'] = array_slice(
                    array_values(array_filter($payment_ids, 'is_string')),
                    0,
                    10
                );
            }
        }

        $attempts = self::order_attempts($fresh);
        $expected_fingerprint = self::attempt_request_fingerprint($expected);
        if ($expected_fingerprint === '') {
            return false;
        }
        $matching_indexes = array();
        foreach ($attempts as $index => $attempt) {
            $candidate_fingerprint = self::attempt_request_fingerprint($attempt);
            if ($candidate_fingerprint !== ''
                && hash_equals($expected_fingerprint, $candidate_fingerprint)) {
                $matching_indexes[] = $index;
            }
        }
        if (count($matching_indexes) !== 1) {
            return false;
        }
        $attempts[(int) $matching_indexes[0]] = $recovered;
        $attempts = array_slice($attempts, -self::MAX_ATTEMPTS);

        $fresh->update_meta_data(self::ATTEMPTS_META, $attempts);
        Reconciler::mark_required($fresh);
        if (!$safe_expired) {
            $this->flag_review(
                $fresh,
                'checkout_persistence_failed',
                false,
                in_array((string) ($recovered['mode'] ?? ''), array('test', 'live'), true)
                    ? (string) $recovered['mode']
                    : 'local'
            );
        }
        if (!Order_Lock::renew($order_id)) {
            return false;
        }
        $fresh->save();

        $persisted = wc_get_order($order_id);
        if (!$persisted instanceof \WC_Order
            || !self::refresh_order($persisted)
            || !$this->persisted_attempt_matches($persisted, $recovered)) {
            return false;
        }
        $persisted_matches = array_values(array_filter(
            self::order_attempts($persisted),
            static function (array $attempt) use ($expected_fingerprint, $session_id): bool {
                $candidate_fingerprint = self::attempt_request_fingerprint($attempt);
                return $candidate_fingerprint !== ''
                    && hash_equals($expected_fingerprint, $candidate_fingerprint)
                    && (string) ($attempt['session_id'] ?? '') === $session_id;
            }
        ));
        if (count($persisted_matches) !== 1) {
            return false;
        }
        if ($safe_expired) {
            return !empty($persisted_matches[0]['expired_at']);
        }
        return (string) $persisted->get_meta(Reconciler::UNRESOLVED_META, true)
            === 'checkout_persistence_failed';
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
            'cancel_url' => add_query_arg(
                array(
                    'wc-api' => 'bactive_paymongo_cancel',
                    'order_id' => $order->get_id(),
                    'attempt' => self::attempt_request_fingerprint($attempt),
                    'token' => $this->cancel_token($order->get_id(), $attempt),
                ),
                home_url('/')
            ),
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

    private function expire_changed_attempts(\WC_Order $order, array &$attempts): bool
    {
        $result = $this->expire_outstanding_attempts($order, $attempts, true);
        if ($result['lock_lost']) {
            return false;
        }
        if (!$result['settlement']) {
            $order->save();
        }
        return $result['verified'];
    }

    /** @return array{verified:bool,settlement:bool,lock_lost:bool} */
    private function expire_outstanding_attempts(
        \WC_Order $order,
        array &$attempts,
        bool $settle_paid,
        ?string $only_attempt_fingerprint = null
    ): array
    {
        $all_verified = true;
        $changed = false;
        $lock_lost = false;

        $only_attempt_index = null;
        if ($only_attempt_fingerprint !== null) {
            if (!preg_match('/^[a-f0-9]{64}$/D', $only_attempt_fingerprint)) {
                return array('verified' => false, 'settlement' => false, 'lock_lost' => false);
            }
            $matching_indexes = array();
            foreach ($attempts as $index => $candidate) {
                $candidate_fingerprint = self::attempt_request_fingerprint($candidate);
                if ($candidate_fingerprint !== ''
                    && hash_equals($only_attempt_fingerprint, $candidate_fingerprint)) {
                    $matching_indexes[] = $index;
                }
            }
            if (count($matching_indexes) !== 1) {
                $this->safe_log('critical', 'session_expiry_attempt_identity_ambiguous', array(
                    'order_id' => $order->get_id(),
                ));
                return array('verified' => false, 'settlement' => false, 'lock_lost' => false);
            }
            $only_attempt_index = (int) $matching_indexes[0];
        }

        foreach ($attempts as $index => &$attempt) {
            if ($only_attempt_index !== null && $index !== $only_attempt_index) {
                continue;
            }
            if (empty($attempt['session_id'])) {
                if (!empty($attempt['request_pending'])
                    && empty($attempt['request_rejected_at'])
                    && empty($attempt['request_aborted_at'])
                    && empty($attempt['paid_at'])
                    && empty($attempt['payment_id'])) {
                    $all_verified = false;
                }
                continue;
            }
            if (!empty($attempt['expired_at'])
                || !empty($attempt['paid_at'])
                || !empty($attempt['payment_id'])) {
                continue;
            }

            if (!Order_Lock::renew($order->get_id())) {
                $all_verified = false;
                $lock_lost = true;
                $this->safe_log('critical', 'session_expiry_order_lock_lost', array('order_id' => $order->get_id()));
                break;
            }

            $session_id = (string) $attempt['session_id'];
            $mode = (string) ($attempt['mode'] ?? '');
            if (!in_array($mode, array('test', 'live'), true)) {
                $all_verified = false;
                $this->safe_log('error', 'session_expiry_mode_invalid', array(
                    'order_id' => $order->get_id(),
                    'session_id' => $session_id,
                    'mode' => $mode,
                ));
                continue;
            }

            $live = $mode === 'live';
            $key = Secrets::api_key($live, $this);
            if ($key === '') {
                $all_verified = false;
                $this->safe_log('error', 'session_expiry_key_missing', array(
                    'order_id' => $order->get_id(),
                    'session_id' => $session_id,
                    'mode' => $mode,
                ));
                continue;
            }

            $client = new Api_Client($key);
            $response = $client->expire_checkout_session(
                $session_id,
                'bactive-expire-' . $mode . '-'
                    . substr(hash('sha256', $mode . '|' . $session_id), 0, 48)
            );

            // Always retrieve independently after the mutation. The expire
            // response alone cannot close a pay-vs-cancel race.
            $readback = $client->retrieve_checkout_session($session_id);
            if (!Order_Lock::renew($order->get_id())) {
                $all_verified = false;
                $lock_lost = true;
                $this->safe_log('critical', 'session_expiry_order_lock_lost', array('order_id' => $order->get_id()));
                break;
            }

            $status = is_array($readback)
                ? Integrity::checkout_session_status($readback, $session_id, $live)
                : null;
            $payment_state = is_array($readback)
                ? Integrity::checkout_session_payment_state($readback, $session_id, $live)
                : null;
            if ($status === null || $payment_state === null) {
                $all_verified = false;
                $code = is_wp_error($response) ? $response->get_error_code() : 'session_expiry_readback_failed';
                $this->safe_log('error', $code, array('order_id' => $order->get_id()));
                continue;
            }

            $paid_ids = $payment_state['paid'];
            if ($paid_ids !== array()) {
                if (!Order_Lock::renew($order->get_id())) {
                    $this->safe_log('critical', 'session_expiry_order_lock_lost', array('order_id' => $order->get_id()));
                    return array('verified' => false, 'settlement' => false, 'lock_lost' => true);
                }
                if ($settle_paid) {
                    $result = Webhook::reconcile_checkout_session($order, $readback, $attempt, $live);
                    if (!Order_Lock::held_by_request($order->get_id())
                        || !Order_Lock::renew($order->get_id())) {
                        $this->safe_log('critical', 'session_expiry_order_lock_lost', array('order_id' => $order->get_id()));
                        return array('verified' => false, 'settlement' => false, 'lock_lost' => true);
                    }
                    if (!in_array($result, array('processed', 'duplicate', 'quarantined'), true)) {
                        $this->flag_review(
                            $order,
                            'paid_session_reconciliation_' . sanitize_key($result),
                            true,
                            $mode
                        );
                    }
                } else {
                    $attempt['paid_at'] = time();
                    $attempt['reconciliation_payment_ids'] = array_slice($paid_ids, 0, 10);
                    $order->update_meta_data(Reconciler::UNRESOLVED_META, 'paid_detected_during_session_expiry');
                    $order->update_meta_data('_bactive_paymongo_unexpected_payment_id', sanitize_text_field((string) $paid_ids[0]));
                    $order->update_meta_data('_bactive_paymongo_unexpected_payment_mode', $mode);
                    Reconciler::mark_required($order);
                    $this->flag_review($order, 'paid_detected_during_session_expiry', false, $mode);
                }
                return array('verified' => false, 'settlement' => true, 'lock_lost' => false);
            }

            if ($payment_state['pending'] !== array()
                || Integrity::checkout_session_has_processing_intent($readback)) {
                // Expiring a Checkout Session does not prove an already-
                // created pending Payment or processing Intent failed. Keep the attempt outstanding
                // so authenticated recovery can observe a later paid/failed
                // transition before COD or another session is permitted.
                $all_verified = false;
                $this->safe_log('warning', 'session_expiry_payment_pending', array('order_id' => $order->get_id()));
                continue;
            }

            if ($status !== 'expired') {
                $all_verified = false;
                $this->safe_log('error', 'session_expiry_readback_active', array('order_id' => $order->get_id()));
                continue;
            }

            $attempt['expired_at'] = time();
            $changed = true;
        }
        unset($attempt);

        if ($lock_lost) {
            return array('verified' => false, 'settlement' => false, 'lock_lost' => true);
        }
        if ($changed) {
            if (!Order_Lock::renew($order->get_id())) {
                $this->safe_log('critical', 'session_expiry_order_lock_lost', array('order_id' => $order->get_id()));
                return array('verified' => false, 'settlement' => false, 'lock_lost' => true);
            }
            $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
        }

        return array('verified' => $all_verified, 'settlement' => false, 'lock_lost' => false);
    }

    public function expire_all_for_order(\WC_Order $order): bool
    {
        if (!self::has_outstanding_attempts($order)) {
            return (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) === ''
                && (string) $order->get_meta(Reconciler::UNRESOLVED_META, true) === '';
        }

        $held_by_request = Order_Lock::held_by_request($order->get_id());
        if (!Order_Lock::acquire($order->get_id())) {
            return false;
        }

        try {
            $attempts = $this->attempts($order);
            $result = $this->expire_outstanding_attempts($order, $attempts, true);
            if ($result['lock_lost']) {
                return false;
            }
            if (!$result['settlement']) {
                $order->update_meta_data(self::ATTEMPTS_META, array_slice($attempts, -self::MAX_ATTEMPTS));
                $order->save();
            } else {
                self::refresh_order($order);
            }
            return !self::has_outstanding_attempts($order)
                && (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) === ''
                && (string) $order->get_meta(Reconciler::UNRESOLVED_META, true) === '';
        } finally {
            if (!$held_by_request) {
                Order_Lock::release($order->get_id());
            }
        }
    }

    private function cod_transition_is_valid(\WC_Order $order): bool
    {
        $product_total = 0;
        foreach ($order->get_items('line_item') as $item) {
            $minor = Integrity::amount_to_minor(wc_format_decimal($item->get_total(), 2));
            if ($minor === null) {
                return false;
            }
            $product_total += $minor;
        }

        $cod_fee = 0;
        foreach ($order->get_items('fee') as $item) {
            if (strcasecmp(trim((string) $item->get_name()), 'COD Fee') !== 0) {
                continue;
            }
            $minor = Integrity::amount_to_minor(wc_format_decimal($item->get_total(), 2));
            if ($minor === null) {
                return false;
            }
            $cod_fee += $minor;
        }

        return Integrity::cod_transition_is_valid($product_total, $cod_fee);
    }

    private function potentially_payment_relevant_save(\WC_Order $order): bool
    {
        if (in_array($order->get_payment_method(), array($this->id, 'cod'), true)) {
            return true;
        }
        $changes = $order->get_changes();
        foreach (array(
            'payment_method',
            'payment_method_title',
            'status',
            'total',
            'currency',
            'transaction_id',
            'date_paid',
            'date_paid_gmt',
        ) as $property) {
            if (array_key_exists($property, $changes)) {
                return true;
            }
        }

        // WC_Data::get_changes() excludes metadata. Deleted metadata is also
        // filtered out of get_meta_data(), but remains in WC_Data::$meta_data
        // with a null value until the data-store write. Inspect both views so
        // a stale object cannot silently delete a session/settlement marker.
        if (is_callable(array($order, 'get_meta_data'))) {
            foreach ((array) $order->get_meta_data() as $meta) {
                if ($this->meta_object_has_protected_key($meta)) {
                    return true;
                }
            }
        }

        try {
            $reflection = new \ReflectionObject($order);
            if ($reflection->hasProperty('meta_data')) {
                $property = $reflection->getProperty('meta_data');
                $property->setAccessible(true);
                foreach ((array) $property->getValue($order) as $meta) {
                    if ($this->meta_object_has_protected_key($meta)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $error) {
            // Core payment fields above remain guarded. If Woo changes the
            // metadata representation, current plugin metadata is still found
            // through get_meta_data(); unknown deleted shapes are not trusted.
        }

        return false;
    }

    /** @param mixed $meta */
    private function meta_object_has_protected_key($meta): bool
    {
        $key = '';
        if (is_object($meta) && is_callable(array($meta, 'get_data'))) {
            try {
                $data = $meta->get_data();
                $key = is_array($data) ? (string) ($data['key'] ?? '') : '';
            } catch (\Throwable $error) {
                $key = '';
            }
        }
        if ($key === '' && is_object($meta)) {
            try {
                $key = (string) ($meta->key ?? '');
            } catch (\Throwable $error) {
                $key = '';
            }
        }
        return str_starts_with($key, '_bactive_paymongo_');
    }

    private function checkout_state_is_payable(\WC_Order $order): bool
    {
        return !$order->is_paid()
            && $order->needs_payment()
            && (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) === ''
            && (string) $order->get_meta(Reconciler::UNRESOLVED_META, true) === ''
            && (string) $order->get_meta('_bactive_paymongo_review_required', true) === ''
            && !self::has_inconsistent_provider_payment_state($order);
    }

    private function checkout_submission_identity(): string
    {
        if (!function_exists('WC') || !WC() || !isset(WC()->session)
            || !is_callable(array(WC()->session, 'get_customer_id'))) {
            return '';
        }
        try {
            $session_id = trim((string) WC()->session->get_customer_id());
        } catch (\Throwable $error) {
            return '';
        }
        if ($session_id === '') {
            return '';
        }
        $site_id = function_exists('get_current_blog_id') ? (string) get_current_blog_id() : '1';
        return $site_id . '|' . $session_id;
    }

    /** @return array<string,mixed>|null */
    private function checkout_order_snapshot(\WC_Order $order): ?array
    {
        $amount = Integrity::amount_to_minor((string) $order->get_total());
        if ($amount === null) {
            return null;
        }

        $fields = array(
            'line_item' => array(
                'get_product_id', 'get_variation_id', 'get_quantity', 'get_subtotal',
                'get_total', 'get_subtotal_tax', 'get_total_tax', 'get_taxes',
            ),
            'fee' => array(
                'get_name', 'get_tax_class', 'get_tax_status', 'get_amount',
                'get_total', 'get_total_tax', 'get_taxes',
            ),
            'shipping' => array(
                'get_method_title', 'get_method_id', 'get_instance_id',
                'get_total', 'get_total_tax', 'get_taxes',
            ),
            'tax' => array(
                'get_rate_id', 'get_rate_code', 'get_label', 'get_compound',
                'get_tax_total', 'get_shipping_tax_total',
            ),
            'coupon' => array('get_code', 'get_discount', 'get_discount_tax'),
        );
        $item_fingerprints = array();
        foreach ($fields as $item_type => $getters) {
            try {
                $items = $order->get_items($item_type);
            } catch (\Throwable $error) {
                return null;
            }
            if (!is_array($items)) {
                return null;
            }
            $fingerprints = array();
            foreach ($items as $item) {
                if (!is_object($item)) {
                    return null;
                }
                $values = array();
                foreach ($getters as $getter) {
                    if (!is_callable(array($item, $getter))) {
                        return null;
                    }
                    try {
                        $values[$getter] = $this->canonical_checkout_value($item->{$getter}());
                    } catch (\Throwable $error) {
                        return null;
                    }
                }
                $encoded = wp_json_encode($values, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
                if (!is_string($encoded)) {
                    return null;
                }
                $fingerprints[] = hash('sha256', $encoded);
            }
            sort($fingerprints, SORT_STRING);
            $item_fingerprints[$item_type] = $fingerprints;
        }

        return array(
            'amount' => $amount,
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'status' => $order->get_status(),
            'items' => $item_fingerprints,
        );
    }

    /** @param mixed $value @return mixed */
    private function canonical_checkout_value($value)
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $entry) {
                $value[$key] = $this->canonical_checkout_value($entry);
            }
            return $value;
        }
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        throw new \UnexpectedValueException('Checkout item contained a non-canonical value.');
    }

    /**
     * Version 1.0.0 cannot verify PayMongo refund objects atomically with
     * WooCommerce refund creation. Stop before WC_Order_Refund::save() so a
     * manual/programmatic refund cannot leave child records or stock changes
     * after the parent-order payment fence rejects its status transition.
     *
     * @param mixed $refund
     * @param mixed $args
     * @throws \Exception For every order with PayMongo payment history.
     */
    public function guard_refund_creation($refund, $args): void
    {
        $order_id = is_array($args) ? absint($args['order_id'] ?? 0) : 0;
        if ($order_id < 1) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            throw new \Exception(__('The refund was stopped because the PayMongo order could not be read safely.', 'bactive-paymongo'));
        }
        if (!self::has_protected_payment_state($order)) {
            return;
        }

        throw new \Exception(__('WooCommerce refund records are disabled for PayMongo in this release. Issue and verify the refund in PayMongo, then add the exact refund facts as a private order note without changing payment status.', 'bactive-paymongo'));
    }

    /**
     * WC_Order::save() catches Exception and then still emits its queued status
     * transition. Clear that queue and throw Error so a blocked database write
     * cannot send fulfillment/cancellation emails or trigger stock hooks as if
     * the transition had succeeded.
     *
     * @throws \Error Always.
     */
    private function abort_order_save(\WC_Order $order, string $message): void
    {
        try {
            $reflection = new \ReflectionObject($order);
            if ($reflection->hasProperty('status_transition')) {
                $property = $reflection->getProperty('status_transition');
                $property->setAccessible(true);
                $property->setValue($order, false);
            }
        } catch (\Throwable $error) {
            // The Error below still escapes WC_Order::save() before its
            // status_transition() call on every supported WooCommerce build.
        }
        throw new \Error($message);
    }

    private function protected_payment_state_change_is_unsafe(\WC_Order $incoming, \WC_Order $stored): bool
    {
        $settlement_pending = (string) $stored->get_meta('_bactive_paymongo_settlement_pending', true) !== '';
        $unresolved = (string) $stored->get_meta(Reconciler::UNRESOLVED_META, true) !== '';
        $operator_action_pending = Webhook::review_resolution_recovery_pending($stored)
            || Webhook::operator_disposition_recovery_pending($stored);
        $core_payment_incomplete = !$stored->is_paid()
            || (string) $stored->get_transaction_id() === ''
            || $stored->get_date_paid('edit') === null;
        $review_pending = $settlement_pending
            || $unresolved
            || $operator_action_pending
            || (string) $stored->get_meta('_bactive_paymongo_review_required', true) !== ''
            || ($core_payment_incomplete && (
                (string) $stored->get_meta(Reconciler::REQUIRED_META, true) !== ''
                || self::has_provider_payment_evidence($stored)
            ));
        if (!$stored->is_paid()
            && $stored->get_transaction_id() === ''
            && $stored->get_date_paid('edit') === null
            && !$review_pending) {
            return false;
        }

        $changes = $incoming->get_changes();
        if (array_key_exists('payment_method', $changes)
            && $incoming->get_payment_method() !== $stored->get_payment_method()) {
            return true;
        }
        if (array_key_exists('payment_method_title', $changes)
            && (!is_callable(array($incoming, 'get_payment_method_title'))
                || !is_callable(array($stored, 'get_payment_method_title'))
                || $incoming->get_payment_method_title() !== $stored->get_payment_method_title())) {
            return true;
        }
        if (array_key_exists('currency', $changes)
            && $incoming->get_currency() !== $stored->get_currency()) {
            return true;
        }
        if (array_key_exists('total', $changes)) {
            $incoming_amount = Integrity::amount_to_minor((string) $incoming->get_total());
            $stored_amount = Integrity::amount_to_minor((string) $stored->get_total());
            if ($incoming_amount === null || $stored_amount === null || $incoming_amount !== $stored_amount) {
                return true;
            }
        }
        if (array_key_exists('transaction_id', $changes)
            && $incoming->get_transaction_id() !== $stored->get_transaction_id()) {
            return true;
        }
        if (array_key_exists('date_paid', $changes)
            || array_key_exists('date_paid_gmt', $changes)) {
            $incoming_paid_at = $this->order_paid_timestamp($incoming);
            $stored_paid_at = $this->order_paid_timestamp($stored);
            if ($incoming_paid_at === null || $stored_paid_at === null || $incoming_paid_at !== $stored_paid_at) {
                return true;
            }
        }
        if (array_key_exists('status', $changes)) {
            if (!is_callable(array($incoming, 'get_status'))
                || !is_callable(array($stored, 'get_status'))) {
                return true;
            }
            $incoming_status = $incoming->get_status();
            $stored_status = $stored->get_status();
            $transition = $this->queued_status_transition($incoming);
            if (!is_array($transition)
                || (string) ($transition['from'] ?? '') !== $stored_status
                || (string) ($transition['to'] ?? '') !== $incoming_status
                || $incoming_status === $stored_status) {
                // A transition queued from a different stored status is stale.
                // Even when its target now equals the webhook result, Woo would
                // replay payment/stock/email hooks after the no-op database save.
                return true;
            }

            // Allow normal fulfillment to advance a paid processing order to
            // another paid status, but never downgrade a completed order or
            // move protected settlement/review state at all.
            if ($review_pending
                || !$stored->is_paid()
                || !$incoming->is_paid()
                || $stored->has_status('completed')) {
                return true;
            }
        }

        return false;
    }

    public static function has_protected_payment_state(\WC_Order $order): bool
    {
        if (Webhook::has_pending_reviews($order->get_id())) {
            return true;
        }
        if (self::order_attempts($order) !== array()
            || $order->get_payment_method() === GATEWAY_ID
            || preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', (string) $order->get_transaction_id())
            || self::has_provider_payment_evidence($order)
            || Webhook::review_resolution_recovery_pending($order)
            || Webhook::operator_disposition_recovery_pending($order)) {
            return true;
        }

        foreach (array(
            Reconciler::REQUIRED_META,
            Reconciler::UNRESOLVED_META,
            '_bactive_paymongo_review_required',
            '_bactive_paymongo_review_incidents',
            '_bactive_paymongo_settlement_pending',
            '_bactive_paymongo_settlement_pending_mode',
            '_bactive_paymongo_unexpected_payment_id',
            '_bactive_paymongo_unexpected_payment_mode',
            '_bactive_paymongo_paid_event_id',
            '_bactive_paymongo_paid_session_id',
            '_bactive_paymongo_source_method',
            '_bactive_paymongo_source_provider',
            '_bactive_paymongo_paid_mode',
            '_bactive_paymongo_processing_incident_code',
            '_bactive_paymongo_processing_incident_payment_id',
            '_bactive_paymongo_processing_incident_event_id',
            '_bactive_paymongo_processing_incident_session_id',
            '_bactive_paymongo_processing_incident_mode',
            '_bactive_paymongo_review_effect_identity',
            '_bactive_paymongo_review_effect_code',
            '_bactive_paymongo_review_effect_event_id',
            '_bactive_paymongo_review_effect_session_id',
            '_bactive_paymongo_review_effect_payment_id',
            '_bactive_paymongo_review_effect_mode',
            '_bactive_paymongo_review_mode',
            '_bactive_paymongo_resolved_evidence_fingerprint',
            '_bactive_paymongo_resolved_payment_pending',
            '_bactive_paymongo_operator_disposition',
        ) as $key) {
            if ((string) $order->get_meta($key, true) !== '') {
                return true;
            }
        }
        return false;
    }

    public static function has_provider_payment_evidence(\WC_Order $order): bool
    {
        if (preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', (string) $order->get_transaction_id())
            || (string) $order->get_meta('_bactive_paymongo_settlement_pending', true) !== '') {
            return true;
        }
        foreach (array(
            '_bactive_paymongo_paid_event_id',
            '_bactive_paymongo_paid_session_id',
            '_bactive_paymongo_source_method',
            '_bactive_paymongo_source_provider',
            '_bactive_paymongo_unexpected_payment_id',
            '_bactive_paymongo_paid_mode',
            '_bactive_paymongo_unexpected_payment_mode',
            '_bactive_paymongo_settlement_pending_mode',
            '_bactive_paymongo_processing_incident_mode',
        ) as $key) {
            if ((is_callable(array($order, 'meta_exists')) && $order->meta_exists($key))
                || (string) $order->get_meta($key, true) !== '') {
                return true;
            }
        }
        foreach (self::order_attempts($order) as $attempt) {
            if ((string) ($attempt['payment_id'] ?? '') !== ''
                || (string) ($attempt['paid_event_id'] ?? '') !== ''
                || (int) ($attempt['paid_at'] ?? 0) > 0
                || (array) ($attempt['reconciliation_payment_ids'] ?? array()) !== array()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect a torn provider-paid state without re-queuing a fully settled,
     * internally coherent historical order.
     */
    public static function has_inconsistent_provider_payment_state(\WC_Order $order): bool
    {
        if (self::has_inconsistent_reconciliation_control_state($order)) {
            return true;
        }

        if (!self::has_provider_payment_evidence($order)) {
            return false;
        }

        $resolved_fingerprint = (string) $order->get_meta(
            '_bactive_paymongo_resolved_evidence_fingerprint',
            true
        );
        if (preg_match('/^[a-f0-9]{64}$/D', $resolved_fingerprint)
            && hash_equals($resolved_fingerprint, self::provider_payment_evidence_fingerprint($order))) {
            return false;
        }

        $transaction_id = (string) $order->get_transaction_id();
        $settlement_id = (string) $order->get_meta('_bactive_paymongo_settlement_pending', true);
        $settlement_mode = (string) $order->get_meta('_bactive_paymongo_settlement_pending_mode', true);
        $unexpected_id = (string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true);
        $unexpected_mode = (string) $order->get_meta('_bactive_paymongo_unexpected_payment_mode', true);
        $paid_mode = (string) $order->get_meta('_bactive_paymongo_paid_mode', true);
        $processing = array(
            (string) $order->get_meta('_bactive_paymongo_processing_incident_code', true),
            (string) $order->get_meta('_bactive_paymongo_processing_incident_payment_id', true),
            (string) $order->get_meta('_bactive_paymongo_processing_incident_event_id', true),
            (string) $order->get_meta('_bactive_paymongo_processing_incident_session_id', true),
            (string) $order->get_meta('_bactive_paymongo_processing_incident_mode', true),
        );
        if (($settlement_id === '') !== ($settlement_mode === '')
            || ($unexpected_id === '') !== ($unexpected_mode === '')
            || ($settlement_mode !== '' && !in_array($settlement_mode, array('test', 'live'), true))
            || ($unexpected_mode !== '' && !in_array($unexpected_mode, array('test', 'live'), true))
            || array_filter($processing, static fn(string $value): bool => $value !== '') !== array()) {
            return true;
        }
        if (!$order->is_paid()
            || !preg_match('/^pay_[A-Za-z0-9_-]{3,128}$/D', $transaction_id)
            || $order->get_date_paid('edit') === null
            || $unexpected_id !== ''
            || !in_array($paid_mode, array('test', 'live'), true)
            || ($settlement_mode !== '' && $settlement_mode !== $paid_mode)) {
            return true;
        }

        $event_id = (string) $order->get_meta('_bactive_paymongo_paid_event_id', true);
        $session_id = (string) $order->get_meta('_bactive_paymongo_paid_session_id', true);
        $method = (string) $order->get_meta('_bactive_paymongo_source_method', true);
        if ($event_id === '' || $session_id === '' || $method === '') {
            return true;
        }

        $matching_paid_attempts = 0;
        foreach (self::order_attempts($order) as $attempt) {
            $attempt_payment_id = (string) ($attempt['payment_id'] ?? '');
            $attempt_mode = (string) ($attempt['mode'] ?? '');
            if ($attempt_payment_id !== ''
                && (!hash_equals($transaction_id, $attempt_payment_id)
                    || !hash_equals($paid_mode, $attempt_mode))) {
                return true;
            }
            foreach ((array) ($attempt['reconciliation_payment_ids'] ?? array()) as $reconciled_id) {
                if (is_string($reconciled_id)
                    && $reconciled_id !== ''
                    && (!hash_equals($transaction_id, $reconciled_id)
                        || !hash_equals($paid_mode, $attempt_mode))) {
                    return true;
                }
            }
            if ((string) ($attempt['session_id'] ?? '') === $session_id
                && $attempt_payment_id === $transaction_id
                && (string) ($attempt['paid_event_id'] ?? '') === $event_id
                && $attempt_mode === $paid_mode
                && (int) ($attempt['paid_at'] ?? 0) > 0) {
                ++$matching_paid_attempts;
            }
        }
        return $matching_paid_attempts !== 1;
    }

    /**
     * Review and reconciliation controls are an independently durable tuple.
     * Detect every torn companion even when no provider payment ID has yet
     * been written, so source scans cannot silently drop the order.
     */
    private static function has_inconsistent_reconciliation_control_state(\WC_Order $order): bool
    {
        $review_code = (string) $order->get_meta('_bactive_paymongo_review_required', true);
        $unresolved_code = (string) $order->get_meta(Reconciler::UNRESOLVED_META, true);
        $review_mode = (string) $order->get_meta('_bactive_paymongo_review_mode', true);
        $review_incidents = (string) $order->get_meta('_bactive_paymongo_review_incidents', true);
        $review_values = array($review_code, $unresolved_code, $review_mode, $review_incidents);
        $review_active = array_filter(
            $review_values,
            static fn(string $value): bool => $value !== ''
        ) !== array();
        if ($review_active
            && ($review_code === ''
                || !hash_equals($review_code, $unresolved_code)
                || !in_array($review_mode, array('test', 'live', 'local'), true)
                || (int) $review_incidents < 1
                || (string) $order->get_meta(Reconciler::REQUIRED_META, true) !== 'yes')) {
            return true;
        }

        $effect_identity = (string) $order->get_meta('_bactive_paymongo_review_effect_identity', true);
        $effect_code = (string) $order->get_meta('_bactive_paymongo_review_effect_code', true);
        $effect_event = (string) $order->get_meta('_bactive_paymongo_review_effect_event_id', true);
        $effect_session = (string) $order->get_meta('_bactive_paymongo_review_effect_session_id', true);
        $effect_payment = (string) $order->get_meta('_bactive_paymongo_review_effect_payment_id', true);
        $effect_mode = (string) $order->get_meta('_bactive_paymongo_review_effect_mode', true);
        $effect_active = array_filter(
            array($effect_identity, $effect_code, $effect_event, $effect_session, $effect_payment, $effect_mode),
            static fn(string $value): bool => $value !== ''
        ) !== array();
        if ($effect_active
            && (!$review_active
                || $effect_identity === ''
                || $effect_code === ''
                || $effect_event === ''
                || $effect_mode === ''
                || !hash_equals($effect_identity, $effect_event)
                || !hash_equals($review_code, $effect_code)
                || !hash_equals($review_mode, $effect_mode))) {
            return true;
        }

        $processing_code = (string) $order->get_meta('_bactive_paymongo_processing_incident_code', true);
        $processing_payment = (string) $order->get_meta('_bactive_paymongo_processing_incident_payment_id', true);
        $processing_event = (string) $order->get_meta('_bactive_paymongo_processing_incident_event_id', true);
        $processing_session = (string) $order->get_meta('_bactive_paymongo_processing_incident_session_id', true);
        $processing_mode = (string) $order->get_meta('_bactive_paymongo_processing_incident_mode', true);
        $processing_active = array_filter(
            array($processing_code, $processing_payment, $processing_event, $processing_session, $processing_mode),
            static fn(string $value): bool => $value !== ''
        ) !== array();
        if ($processing_active
            && (!$review_active
                || $processing_code === ''
                || $processing_event === ''
                || $processing_session === ''
                || !in_array($processing_mode, array('test', 'live'), true)
                || !hash_equals($review_code, $processing_code)
                || !hash_equals($review_mode, $processing_mode))) {
            return true;
        }

        $settlement_id = (string) $order->get_meta('_bactive_paymongo_settlement_pending', true);
        $settlement_mode = (string) $order->get_meta('_bactive_paymongo_settlement_pending_mode', true);
        $unexpected_id = (string) $order->get_meta('_bactive_paymongo_unexpected_payment_id', true);
        $unexpected_mode = (string) $order->get_meta('_bactive_paymongo_unexpected_payment_mode', true);
        return ($settlement_id === '') !== ($settlement_mode === '')
            || ($unexpected_id === '') !== ($unexpected_mode === '')
            || ($settlement_mode !== '' && !in_array($settlement_mode, array('test', 'live'), true))
            || ($unexpected_mode !== '' && !in_array($unexpected_mode, array('test', 'live'), true));
    }

    public static function provider_payment_evidence_fingerprint(\WC_Order $order): string
    {
        $date_paid = $order->get_date_paid('edit');
        $paid_at = is_object($date_paid) && is_callable(array($date_paid, 'getTimestamp'))
            ? (int) $date_paid->getTimestamp()
            : 0;
        $data = array(
            'order_id' => $order->get_id(),
            'payment_method' => $order->get_payment_method(),
            'status' => $order->get_status(),
            'is_paid' => $order->is_paid(),
            'transaction_id' => (string) $order->get_transaction_id(),
            'date_paid' => $paid_at,
            'attempts' => self::order_attempts($order),
        );
        foreach (array(
            '_bactive_paymongo_settlement_pending',
            '_bactive_paymongo_unexpected_payment_id',
            '_bactive_paymongo_paid_event_id',
            '_bactive_paymongo_paid_session_id',
            '_bactive_paymongo_source_method',
            '_bactive_paymongo_source_provider',
            '_bactive_paymongo_paid_mode',
            '_bactive_paymongo_unexpected_payment_mode',
            '_bactive_paymongo_settlement_pending_mode',
            '_bactive_paymongo_processing_incident_mode',
            '_bactive_paymongo_processing_incident_code',
            '_bactive_paymongo_processing_incident_payment_id',
            '_bactive_paymongo_processing_incident_event_id',
            '_bactive_paymongo_processing_incident_session_id',
        ) as $key) {
            $data[$key] = $order->get_meta($key, true);
        }
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($data, JSON_UNESCAPED_SLASHES)
            : json_encode($data, JSON_UNESCAPED_SLASHES);
        return hash('sha256', is_string($encoded) ? $encoded : serialize($data));
    }

    /** @return array<string,mixed>|false|null */
    private function queued_status_transition(\WC_Order $order)
    {
        try {
            $reflection = new \ReflectionObject($order);
            if (!$reflection->hasProperty('status_transition')) {
                return null;
            }
            $property = $reflection->getProperty('status_transition');
            $property->setAccessible(true);
            $value = $property->getValue($order);
            return is_array($value) || $value === false ? $value : null;
        } catch (\Throwable $error) {
            return null;
        }
    }

    private function order_paid_timestamp(\WC_Order $order): ?int
    {
        if (!is_callable(array($order, 'get_date_paid'))) {
            return null;
        }
        $date = $order->get_date_paid();
        if ($date === null || $date === false) {
            return 0;
        }
        if (!is_object($date) || !is_callable(array($date, 'getTimestamp'))) {
            return null;
        }
        try {
            return (int) $date->getTimestamp();
        } catch (\Throwable $error) {
            return null;
        }
    }

    private function copy_protected_payment_meta(\WC_Order $stored, \WC_Order $incoming): void
    {
        foreach (array(
            self::ATTEMPTS_META,
            Reconciler::REQUIRED_META,
            Reconciler::UNRESOLVED_META,
            '_bactive_paymongo_review_required',
            '_bactive_paymongo_review_incidents',
            '_bactive_paymongo_settlement_pending',
            '_bactive_paymongo_settlement_pending_mode',
            '_bactive_paymongo_unexpected_payment_id',
            '_bactive_paymongo_unexpected_payment_mode',
            '_bactive_paymongo_paid_event_id',
            '_bactive_paymongo_paid_session_id',
            '_bactive_paymongo_source_method',
            '_bactive_paymongo_source_provider',
            '_bactive_paymongo_paid_mode',
            '_bactive_paymongo_processing_incident_code',
            '_bactive_paymongo_processing_incident_payment_id',
            '_bactive_paymongo_processing_incident_event_id',
            '_bactive_paymongo_processing_incident_session_id',
            '_bactive_paymongo_processing_incident_mode',
            '_bactive_paymongo_review_effect_identity',
            '_bactive_paymongo_review_effect_code',
            '_bactive_paymongo_review_effect_event_id',
            '_bactive_paymongo_review_effect_session_id',
            '_bactive_paymongo_review_effect_payment_id',
            '_bactive_paymongo_review_effect_mode',
            '_bactive_paymongo_review_mode',
            '_bactive_paymongo_resolved_evidence_fingerprint',
            '_bactive_paymongo_resolved_payment_pending',
            '_bactive_paymongo_operator_disposition',
        ) as $key) {
            $exists = is_callable(array($stored, 'meta_exists'))
                ? $stored->meta_exists($key)
                : $stored->get_meta($key, true) !== '';
            if ($exists) {
                $incoming->update_meta_data($key, $stored->get_meta($key, true));
            } else {
                $incoming->delete_meta_data($key);
            }
        }
    }

    private function loaded_payment_config_is_current(): bool
    {
        $current = get_option('woocommerce_' . GATEWAY_ID . '_settings', array());
        if (!is_array($current)) {
            return false;
        }
        foreach (array('enabled', 'test_mode', 'restricted_rollout', 'test_secret_key', 'live_secret_key') as $key) {
            if ((string) ($current[$key] ?? '') !== (string) ($this->settings[$key] ?? '')) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed>|null */
    private function attempt_by_fingerprint(\WC_Order $order, string $fingerprint): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            return null;
        }
        $matches = array();
        foreach ($this->attempts($order) as $attempt) {
            $candidate_fingerprint = self::attempt_request_fingerprint($attempt);
            if ($candidate_fingerprint !== '' && hash_equals($fingerprint, $candidate_fingerprint)) {
                $matches[] = $attempt;
            }
        }
        return count($matches) === 1 ? $matches[0] : null;
    }

    /** @param array<string,mixed> $attempt */
    private function cancel_token(int $order_id, array $attempt): string
    {
        $fingerprint = self::attempt_request_fingerprint($attempt);
        if ($order_id < 1 || $fingerprint === '') {
            return '';
        }
        $message = implode('|', array(
            (string) $order_id,
            $fingerprint,
        ));
        return hash_hmac('sha256', $message, wp_salt('auth'));
    }

    /**
     * Hash only immutable fields that exist before create_checkout_session.
     * The resulting identity is safe to embed in the provider cancel URL and
     * remains stable after the session ID and terminal markers are recorded.
     *
     * @param array<string,mixed> $attempt
     */
    private static function attempt_request_fingerprint(array $attempt): string
    {
        $identity = array(
            'generation' => (int) ($attempt['generation'] ?? 0),
            'mode' => (string) ($attempt['mode'] ?? ''),
            'fingerprint' => (string) ($attempt['fingerprint'] ?? ''),
            'reference' => (string) ($attempt['reference'] ?? ''),
            'correlation_id' => (string) ($attempt['correlation_id'] ?? ''),
            'idempotency_key' => (string) ($attempt['idempotency_key'] ?? ''),
            'created_at' => (int) ($attempt['created_at'] ?? 0),
            'config_generation' => (int) ($attempt['config_generation'] ?? -1),
            'request_started_at' => (int) ($attempt['request_started_at'] ?? 0),
        );
        if ($identity['generation'] < 1
            || !in_array($identity['mode'], array('test', 'live'), true)
            || !preg_match('/^[a-f0-9]{64}$/D', $identity['fingerprint'])
            || $identity['reference'] === ''
            || $identity['correlation_id'] === ''
            || $identity['idempotency_key'] === ''
            || $identity['created_at'] < 1
            || $identity['config_generation'] < 0
            || $identity['request_started_at'] < 1) {
            return '';
        }
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($identity, JSON_UNESCAPED_SLASHES)
            : json_encode($identity, JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? hash('sha256', $encoded) : '';
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

    private static function refresh_order(\WC_Order $order): bool
    {
        try {
            $data_store = $order->get_data_store();
            if (!is_object($data_store) || !is_callable(array($data_store, 'read'))) {
                return false;
            }
            $data_store->read($order);
            return $order->get_id() > 0;
        } catch (\Throwable $error) {
            return false;
        }
    }

    /** @param array<string,mixed> $expected */
    private function persisted_attempt_matches(\WC_Order $order, array $expected): bool
    {
        $expected_fingerprint = self::attempt_request_fingerprint($expected);
        if ($expected_fingerprint === '') {
            return false;
        }
        $matches = 0;
        foreach (self::order_attempts($order) as $attempt) {
            $candidate_fingerprint = self::attempt_request_fingerprint($attempt);
            if ($candidate_fingerprint !== ''
                && hash_equals($expected_fingerprint, $candidate_fingerprint)
                && $attempt === $expected) {
                ++$matches;
            }
        }
        return $matches === 1;
    }

    private function acquire_order_lock(int $order_id): bool
    {
        return Order_Lock::acquire($order_id);
    }

    private function release_order_lock(int $order_id): void
    {
        Order_Lock::release($order_id);
    }

    private function flag_review(
        \WC_Order $order,
        string $code,
        bool $save = true,
        string $mode = 'local'
    ): void
    {
        $code = sanitize_key($code);
        $mode = in_array($mode, array('test', 'live', 'local'), true) ? $mode : 'local';
        $already_linked = (string) $order->get_meta('_bactive_paymongo_review_required', true) === $code
            && (string) $order->get_meta(Reconciler::UNRESOLVED_META, true) === $code
            && (string) $order->get_meta('_bactive_paymongo_review_mode', true) === $mode
            && (int) $order->get_meta('_bactive_paymongo_review_incidents', true) > 0;
        $review_key = Reconciler::review_incident_option($order->get_id(), $code, $mode);
        $claim = array(
            'recorded_at' => time(),
            'order_id' => $order->get_id(),
            'code' => $code,
            'mode' => $mode,
        );
        $claim = Webhook::queue_review_incident($order, 'generic', $claim);
        if ($claim === null) {
            Reconciler::record_global_drain_error(array('recorded_at' => time(),
                'code' => 'review_inbox_persist_failed', 'order_id' => $order->get_id(), 'mode' => $mode));
            Reconciler::schedule_order($order->get_id());
            return;
        }
        $new_incident = Order_Lock::insert_option($review_key, $claim);
        $stored_claim = get_option($review_key, null);
        $incident_exists = is_array($stored_claim)
            && (int) ($stored_claim['order_id'] ?? 0) === $order->get_id()
            && (string) ($stored_claim['code'] ?? '') === $code
            && (string) ($stored_claim['mode'] ?? '') === $mode
            && (int) ($stored_claim['recorded_at'] ?? 0) > 0;
        if (!$incident_exists) {
            Reconciler::set_draining(true);
            Reconciler::schedule_order($order->get_id());
            return;
        }
        $active_review_values = array(
            (string) $order->get_meta('_bactive_paymongo_review_required', true),
            (string) $order->get_meta(Reconciler::UNRESOLVED_META, true),
            (string) $order->get_meta('_bactive_paymongo_review_mode', true),
            (string) $order->get_meta('_bactive_paymongo_review_effect_identity', true),
            (string) $order->get_meta('_bactive_paymongo_processing_incident_code', true),
            (string) $order->get_meta('_bactive_paymongo_review_incidents', true),
        );
        if (!$already_linked
            && array_filter($active_review_values, static fn(string $value): bool => $value !== '') !== array()) {
            Reconciler::set_draining(true);
            Reconciler::schedule_order($order->get_id());
            return;
        }
        $order->update_meta_data('_bactive_paymongo_review_required', $code);
        $order->update_meta_data(Reconciler::UNRESOLVED_META, $code);
        $order->update_meta_data('_bactive_paymongo_review_mode', $mode);
        Reconciler::mark_required($order);
        if ($new_incident) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: sanitized reconciliation reason */
                    __('PayMongo requires manual review: %s.', 'bactive-paymongo'),
                    sanitize_key($code)
                )
            );
        }
        if ($incident_exists && ($new_incident || !$already_linked)) {
            Reconciler::record_review_incident($order, $new_incident, !$already_linked);
        }
        if ($save) {
            $order->save();
            Webhook::acknowledge_attached_pending_reviews($order);
        }
    }

    private function review_mode_for_order(\WC_Order $order): string
    {
        $modes = array();
        foreach (self::order_attempts($order) as $attempt) {
            $mode = (string) ($attempt['mode'] ?? '');
            if (in_array($mode, array('test', 'live'), true)) {
                $modes[$mode] = true;
            }
        }
        $modes = array_keys($modes);
        return count($modes) === 1 ? (string) $modes[0] : 'local';
    }

    /** @param array<string,int|string> $context */
    private function safe_log(string $level, string $code, array $context = array()): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }
        $safe = array('code' => sanitize_key($code));
        foreach (array('order_id', 'event_id', 'session_id', 'payment_id', 'mode') as $key) {
            if (isset($context[$key])) {
                $safe[$key] = sanitize_text_field((string) $context[$key]);
            }
        }
        wc_get_logger()->log($level, wp_json_encode($safe), array('source' => 'bactive-paymongo'));
    }
}
