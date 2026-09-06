<?php

namespace Bactive\Brevo;

defined('ABSPATH') || exit;

final class Automations
{
    public const EVENTS = ['ba_welcome_ready', 'ba_cart_reminder_ready', 'ba_post_purchase_ready', 'ba_winback_ready'];
    private static bool $capturing = false;

    public static function register(): void
    {
        add_action('woocommerce_after_calculate_totals', [self::class, 'capture_cart'], 90);
        add_action('woocommerce_cart_emptied', [self::class, 'empty_cart']);
        add_action('woocommerce_checkout_order_processed', [self::class, 'order_submitted'], 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'order_submitted']);
        add_action('woocommerce_payment_complete', [self::class, 'observe_order']);
        add_action('woocommerce_order_status_changed', [self::class, 'observe_order'], 20, 4);
        add_action('woocommerce_order_refunded', [self::class, 'observe_order'], 20, 2);
        add_action('bactive_brevo_tick', [self::class, 'run_due']);
        add_action('action_scheduler_init', [self::class, 'schedule']);
        add_action('init', [self::class, 'schedule'], 30);
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('bactive-brevo run-due', static function (): void {
                Config::record_cli_tick();
                \WP_CLI::line(wp_json_encode(self::run_due()));
            });
            \WP_CLI::add_command('bactive-brevo status', static function (): void {
                \WP_CLI::line(wp_json_encode(['readiness' => Config::readiness(), 'storage' => Store::status()]));
            });
        }
    }

    public static function schedule(): void
    {
        if (!Config::enabled() || !function_exists('as_has_scheduled_action') || !function_exists('as_schedule_recurring_action')) return;
        if (!as_has_scheduled_action('bactive_brevo_tick', [], 'bactive-brevo')) {
            as_schedule_recurring_action(time() + 60, 60, 'bactive_brevo_tick', [], 'bactive-brevo', true);
        }
    }

    public static function public_url(string $url): string
    {
        $parts = wp_parse_url($url);
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== $host
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) return '';
        // Links are public catalogue/cart URLs. Strip keys, sessions and arbitrary query data.
        return 'https://' . $host . ($parts['path'] ?? '/');
    }

    public static function fingerprint(array $cart): string
    {
        $lines = [];
        foreach ($cart as $line) {
            if (!is_array($line)) continue;
            $variations = is_array($line['variation'] ?? null) ? $line['variation'] : [];
            $variations = array_map(static fn($v) => is_scalar($v) ? (string) $v : '', $variations);
            ksort($variations);
            $lines[] = [(int) ($line['product_id'] ?? 0), (int) ($line['variation_id'] ?? 0), (float) ($line['quantity'] ?? 0), $variations];
        }
        usort($lines, static fn($a, $b) => strcmp(wp_json_encode($a), wp_json_encode($b)));
        return hash('sha256', wp_json_encode($lines));
    }

    public static function items(array $raw): array
    {
        if (count($raw) > 30) return [];
        $items = [];
        foreach ($raw as $line) {
            if (!is_array($line)) return [];
            $id = (int) ($line['variation_id'] ?? 0) ?: (int) ($line['product_id'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            $product = wc_get_product($id);
            if (!$product || !$product->exists() || !$product->is_purchasable() || !$product->is_in_stock() || $qty <= 0 || $qty > 1000) return [];
            $url = self::public_url((string) $product->get_permalink());
            if ($url === '') return [];
            $image = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
            $items[] = [
                'product_id' => (int) ($line['product_id'] ?? $id), 'variation_id' => (int) ($line['variation_id'] ?? 0),
                'name' => mb_substr(wp_strip_all_tags($product->get_name()), 0, 200), 'quantity' => $qty,
                'url' => $url, 'image' => $image ? self::public_url((string) $image) : '',
                'price' => number_format((float) wc_get_price_to_display($product, ['qty' => $qty]), 2, '.', ''),
            ];
        }
        return $items;
    }

    public static function capture_cart(mixed $unused = null): void
    {
        if (self::$capturing || !Config::enabled() || !function_exists('WC') || !WC()->session || !WC()->cart) return;
        self::$capturing = true;
        try {
            $identity = Consent::current_identity();
            if (!$identity) return;
            $raw = WC()->cart->get_cart();
            if (!$raw) { self::empty_cart(); return; }
            $items = self::items($raw);
            if (!$items) return;
            $key = (string) WC()->session->get('bactive_brevo_cart', '');
            $old = preg_match('/^[a-f0-9]{64}$/D', $key) ? Store::cart($key) : null;
            if (!$old || $old['state'] !== 'active' || $old['email_hash'] !== $identity['email_hash']
                || $old['mode'] !== Config::mode() || $old['site'] !== rtrim(home_url(), '/')) {
                $key = bin2hex(random_bytes(32));
                WC()->session->set('bactive_brevo_cart', $key);
            }
            $session_key = (string) WC()->session->get_customer_id();
            if ($session_key === '' || strlen($session_key) > 100) return;
            $saved = Store::save_cart([
                'cart_key' => $key, 'email_hash' => $identity['email_hash'], 'session_key' => $session_key,
                'fingerprint' => self::fingerprint($raw), 'state' => 'active', 'items' => wp_json_encode($items),
                'total' => (float) WC()->cart->get_total('edit'), 'currency' => get_woocommerce_currency(),
                'mode' => Config::mode(), 'site' => rtrim(home_url(), '/'),
            ]);
            if (!$saved) return;
            Store::queue($identity['email_hash'], 'ba_cart_reminder_ready', '2h', 'cart', $key, time() + 2 * HOUR_IN_SECONDS, true);
            Store::queue($identity['email_hash'], 'ba_cart_reminder_ready', '24h', 'cart', $key, time() + DAY_IN_SECONDS, true);
        } finally {
            self::$capturing = false;
        }
    }

    public static function empty_cart(): void
    {
        if (!function_exists('WC') || !WC()->session) return;
        $key = (string) WC()->session->get('bactive_brevo_cart', '');
        if (preg_match('/^[a-f0-9]{64}$/D', $key)) Store::cancel_cart($key, 'empty_cart');
        WC()->session->__unset('bactive_brevo_cart');
    }

    public static function order_submitted(mixed $id, mixed $posted = null, mixed $order = null): void
    {
        if (!Config::site_allowed()) return;
        $order = is_object($id) ? $id : ($order ?: wc_get_order((int) $id));
        if (!$order) return;
        $identity = Consent::current_identity();
        if ($identity && hash_equals($identity['email_hash'], Store::email_hash((string) $order->get_billing_email()))) {
            // This proof can only originate from the browser that possessed the DOI return token.
            $order->update_meta_data('_bactive_brevo_marketing_proof', self::order_proof($order, $identity));
            $order->save_meta_data();
        }
        if (is_email($order->get_billing_email())) Store::cancel_carts_for_contact(Store::email_hash($order->get_billing_email()), 'order_submitted');
        if ($identity) Store::cancel_old_winback($identity['email_hash'], $order->get_id());
        // Cancel the bound session even when the billing email differs from its marketing identity.
        self::empty_cart();
        self::observe_order($order->get_id());
    }

    private static function order_proof(mixed $order, array $contact): string
    {
        return Store::hash(implode('|', ['order-consent', $order->get_id(), $contact['email_hash'],
            $contact['marker'], Config::mode(), rtrim(home_url(), '/')]));
    }

    public static function order_identity_matches(mixed $order, array $contact): bool
    {
        $proof = $order->get_meta('_bactive_brevo_marketing_proof', true);
        return is_string($proof) && preg_match('/^[a-f0-9]{64}$/D', $proof)
            && hash_equals($contact['email_hash'], Store::email_hash((string) $order->get_billing_email()))
            && hash_equals(self::order_proof($order, $contact), $proof);
    }

    private static function fresh_order(int $id): mixed
    {
        $order = wc_get_order($id);
        if ($order) {
            $order->get_data_store()->read($order);
            $order->read_meta_data(true);
        }
        return $order;
    }

    /** A newer submitted order or later completed purchase ends the old win-back cycle. */
    public static function newer_purchase_exists(mixed $order, string $email): bool
    {
        $completed = $order->get_date_completed();
        if (!$completed) return true;
        $base = ['billing_email' => $email, 'exclude' => [$order->get_id()], 'limit' => 1, 'return' => 'ids'];
        $submitted = wc_get_orders($base + ['status' => array_keys(wc_get_order_statuses()),
            'date_created' => '>=' . $completed->getTimestamp()]);
        if ($submitted) return true;
        return (bool) wc_get_orders($base + ['status' => ['completed'],
            'date_completed' => '>=' . $completed->getTimestamp()]);
    }

    public static function order_allowed(mixed $order): bool
    {
        if (!$order || !method_exists($order, 'get_date_created')) return false;
        $created = $order->get_date_created();
        return $created && (int) Config::get('launch_cutoff') > 0
            && $created->getTimestamp() >= (int) Config::get('launch_cutoff')
            && !in_array($order->get_status(), ['cancelled', 'refunded', 'failed', 'trash', 'checkout-draft'], true)
            && (float) $order->get_total_refunded() <= 0;
    }

    public static function observe_order(mixed $id, mixed ...$unused): void
    {
        if (!Config::enabled()) return;
        $order = self::fresh_order(is_object($id) ? (int) $id->get_id() : (int) $id);
        if (!$order) return;
        if (!self::order_allowed($order)) {
            if (self::payment_certainty($order) === 'unknown') Store::review_order($order->get_id(), 'payment_unknown');
            else Store::cancel_order($order->get_id(), 'order_ineligible');
            self::clear_order_repair($order);
            return;
        }
        $email = strtolower(trim((string) $order->get_billing_email()));
        $contact = Store::contact(Store::email_hash($email));
        if (!$contact || $contact['state'] !== 'confirmed' || !Config::recipient_allowed($email)
            || !self::order_identity_matches($order, $contact)) { self::clear_order_repair($order); return; }
        $paid = $order->get_date_paid();
        if (!$paid || !$order->is_paid()) { self::clear_order_repair($order); return; }
        // Persist the local repair signal before queue writes; retries only recreate missing rows.
        $order->update_meta_data('_bactive_brevo_queue_pending', 'yes');
        $order->save_meta_data();
        $queued = true;
        if ($paid && $order->is_paid()) {
            $queued = Store::queue($contact['email_hash'], 'ba_post_purchase_ready', 'care', 'order', (string) $order->get_id(), $paid->getTimestamp() + 2 * DAY_IN_SECONDS) && $queued;
        }
        $completed = $order->get_date_completed();
        if ($order->get_status() === 'completed' && $completed && $paid && $order->is_paid()) {
            $queued = Store::queue($contact['email_hash'], 'ba_post_purchase_ready', 'review', 'order', (string) $order->get_id(), $completed->getTimestamp() + 14 * DAY_IN_SECONDS) && $queued;
            if (!self::newer_purchase_exists($order, $email)) {
                Store::cancel_old_winback($contact['email_hash'], $order->get_id());
                $queued = Store::queue($contact['email_hash'], 'ba_winback_ready', '90d', 'order', (string) $order->get_id(), $completed->getTimestamp() + 90 * DAY_IN_SECONDS) && $queued;
            }
        }
        if ($queued) self::clear_order_repair($order);
    }

    private static function clear_order_repair(mixed $order): void
    {
        if ($order->meta_exists('_bactive_brevo_queue_pending')) {
            $order->delete_meta_data('_bactive_brevo_queue_pending');
            $order->save_meta_data();
        }
    }

    private static function cart_properties(array $job, array $contact): array|\WP_Error
    {
        $cart = Store::cart($job['entity_id']);
        if (!$cart || $cart['state'] !== 'active' || $cart['email_hash'] !== $contact['email_hash']
            || (int) $cart['expires_at'] < time() || $cart['mode'] !== Config::mode()
            || $cart['site'] !== rtrim(home_url(), '/')) return new \WP_Error('cart_ineligible', 'Cart no longer eligible.');
        $delay = $job['stage'] === '2h' ? 2 * HOUR_IN_SECONDS : DAY_IN_SECONDS;
        if ((int) $cart['updated_at'] + $delay > time()) return new \WP_Error('cart_active', 'Cart is still active.', ['retry_at' => (int) $cart['updated_at'] + $delay]);
        // Checkout submission is the stop signal, including COD and unpaid orders.
        $orders = wc_get_orders([
            'billing_email' => $contact['email'], 'date_created' => '>=' . (int) $cart['created_at'],
            'status' => array_keys(wc_get_order_statuses()), 'limit' => 1, 'return' => 'ids',
        ]);
        if ($orders) { Store::cancel_cart($cart['cart_key'], 'order_submitted'); return new \WP_Error('order_submitted', 'Checkout already submitted.'); }
        if (!class_exists('WC_Session_Handler')) return new \WP_Error('cart_session_unavailable', 'Cart session unavailable.');
        $handler = new \WC_Session_Handler();
        $session = $handler->get_session($cart['session_key'], false);
        $raw = is_array($session) ? maybe_unserialize($session['cart'] ?? []) : [];
        if (!is_array($raw) || !$raw || !hash_equals($cart['fingerprint'], self::fingerprint($raw))) {
            return new \WP_Error('cart_changed_or_expired', 'Cart changed or expired.');
        }
        $items = self::items($raw);
        if (!$items) return new \WP_Error('cart_items_unavailable', 'Cart products unavailable.');
        return ['items' => $items, 'currency' => $cart['currency'], 'cart_url' => self::public_url(wc_get_cart_url())];
    }

    private static function order_properties(array $job, array $contact): array|\WP_Error
    {
        $order = self::fresh_order((int) $job['entity_id']);
        if ($order && self::payment_certainty($order) === 'unknown') {
            return new \WP_Error('payment_unknown', 'Payment settlement needs independent verification.');
        }
        if (!self::order_allowed($order) || !self::order_identity_matches($order, $contact)) {
            return new \WP_Error('order_ineligible', 'Order is no longer eligible.');
        }
        $paid = $order->get_date_paid();
        if (!$paid || !$order->is_paid()) return new \WP_Error('order_unpaid', 'Payment is not recorded.');
        if ($job['stage'] === 'care' && $paid->getTimestamp() + 2 * DAY_IN_SECONDS > time()) {
            return new \WP_Error('order_not_due', 'Order follow-up is not due.', ['retry_at' => $paid->getTimestamp() + 2 * DAY_IN_SECONDS]);
        }
        if (in_array($job['stage'], ['review', '90d'], true)) {
            $completed = $order->get_date_completed();
            if ($order->get_status() !== 'completed' || !$completed) return new \WP_Error('order_not_completed', 'Order is not complete.');
            $due = $completed->getTimestamp() + ($job['stage'] === 'review' ? 14 : 90) * DAY_IN_SECONDS;
            if ($due > time()) return new \WP_Error('order_not_due', 'Follow-up is not due.', ['retry_at' => $due]);
            if ($job['stage'] === '90d') {
                if (self::newer_purchase_exists($order, $contact['email'])) return new \WP_Error('new_purchase_cycle', 'A newer submitted order exists.');
            }
        }
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            $url = self::public_url($product->get_permalink());
            if ($url === '') continue;
            $items[] = ['name' => mb_substr(wp_strip_all_tags($item->get_name()), 0, 200), 'url' => $url, 'product_id' => $item->get_product_id()];
            if (count($items) >= 20) break;
        }
        return ['order_id' => (string) $order->get_id(), 'order_status' => $order->get_status(),
            'paid_at' => gmdate('c', $paid->getTimestamp()), 'items' => $items,
            'shop_url' => self::public_url(wc_get_page_permalink('shop'))];
    }

    public static function properties(array $job, array $contact): array|\WP_Error
    {
        if (($job['mode'] ?? '') !== Config::mode() || ($job['site'] ?? '') !== rtrim(home_url(), '/')) {
            return new \WP_Error('event_environment_changed', 'The queued event belongs to another environment.');
        }
        if (!in_array($job['event_name'], self::EVENTS, true)) return new \WP_Error('unknown_event', 'Unknown event.');
        $allowed = [
            'ba_welcome_ready' => ['contact:welcome'], 'ba_cart_reminder_ready' => ['cart:2h', 'cart:24h'],
            'ba_post_purchase_ready' => ['order:care', 'order:review'], 'ba_winback_ready' => ['order:90d'],
        ];
        if (!in_array($job['entity_kind'] . ':' . $job['stage'], $allowed[$job['event_name']], true)) return new \WP_Error('invalid_stage', 'Invalid marketing stage.');
        if ($job['entity_kind'] === 'cart') $data = self::cart_properties($job, $contact);
        elseif ($job['entity_kind'] === 'order') $data = self::order_properties($job, $contact);
        elseif ($job['entity_kind'] === 'contact' && $job['event_name'] === 'ba_welcome_ready') {
            if (!class_exists(Coupon::class) || !method_exists(Coupon::class, 'ready') || !Coupon::ready()) {
                return new \WP_Error('welcome_offer_unavailable', 'The first-order offer is not ready.', ['retry_at' => time() + 900]);
            }
            $data = ['coupon_code' => (string) Config::get('coupon_code', 'BACTIVE5'), 'shop_url' => self::public_url(wc_get_page_permalink('shop'))];
        } else return new \WP_Error('unknown_entity', 'Unknown event entity.');
        if (is_wp_error($data)) return $data;
        return ['delivery_key' => $job['delivery_key'], 'stage' => $job['stage'], 'source' => 'bactiveph', 'mode' => Config::mode()] + $data;
    }

    /** No payment lifecycle mutation, private Reconciler call, or inference from a cancelled/COD status. */
    public static function payment_certainty(mixed $order): string
    {
        foreach (['paymongo_payment_intent_id', 'paymongo_client_key'] as $key) {
            if ($order->meta_exists($key)) return 'unknown';
        }
        foreach ($order->get_meta_data() as $meta) {
            $data = $meta->get_data();
            if (is_string($data['key'] ?? null) && str_starts_with($data['key'], '_bactive_paymongo_')) {
                // Even well-formed attempts are not a complete settlement/recovery predicate.
                return 'unknown';
            }
        }
        if (str_contains(strtolower((string) $order->get_payment_method()), 'paymongo')) return 'unknown';
        if (class_exists('BActive\\PayMongo\\Gateway') && method_exists('BActive\\PayMongo\\Gateway', 'has_protected_payment_state')) {
            try {
                if (\BActive\PayMongo\Gateway::has_protected_payment_state($order)) return 'unknown';
            } catch (\Throwable $exception) {
                return 'unknown';
            }
        }
        return 'ordinary';
    }

    public static function run_due(): array
    {
        Store::cleanup();
        $ready = Config::readiness();
        if (!$ready['ready']) return ['processed' => 0, 'blockers' => $ready['blockers']];
        $start = microtime(true);
        Store::repair_cart_jobs();
        $repair_ids = wc_get_orders(['limit' => 5, 'return' => 'ids',
            'date_created' => '>=' . (int) Config::get('launch_cutoff'),
            'meta_query' => [['key' => '_bactive_brevo_queue_pending', 'value' => 'yes']]]);
        foreach ($repair_ids as $repair_id) {
            try { self::observe_order((int) $repair_id); }
            catch (\Throwable $exception) { update_option('bactive_brevo_storage_error', ['code' => 'order_queue_repair_failed', 'at' => time()], false); }
        }
        $processed = 0;
        foreach (Store::due() as $job) {
            if (microtime(true) - $start > 20 || !Config::readiness()['ready']) break;
            if (!Store::claim((int) $job['id'])) continue;
            ++$processed;
            try {
                self::process($job);
            } catch (\Throwable $exception) {
                // Do not log provider payloads, emails, stack traces, or secret-bearing errors.
                Store::finish((int) $job['id'], 'review_required', 'worker_exception');
            }
        }
        return ['processed' => $processed, 'blockers' => []];
    }

    private static function process(array $job): void
    {
        $id = (int) $job['id'];
        $contact = Store::contact($job['email_hash']);
        if (!$contact || $contact['state'] !== 'confirmed' || !Config::recipient_allowed($contact['email'])) {
            Store::finish($id, 'suppressed', 'consent_missing'); return;
        }
        $contact = Consent::check_live($contact);
        if (is_wp_error($contact)) {
            $code = $contact->get_error_code();
            $retry = in_array($code, ['provider_unavailable', 'provider_invalid_response', 'provider_rate_limited'], true);
            Store::finish($id, $retry ? 'pending' : 'suppressed', $code, $retry ? time() + 900 : 0); return;
        }
        $properties = self::properties($job, $contact);
        if (is_wp_error($properties)) {
            $data = $properties->get_error_data();
            $retry_at = is_array($data) ? (int) ($data['retry_at'] ?? 0) : 0;
            $state = $properties->get_error_code() === 'payment_unknown' ? 'review_required' : ($retry_at ? 'pending' : 'suppressed');
            Store::finish($id, $state, $properties->get_error_code(), $retry_at); return;
        }
        $date = gmdate('Ymd');
        if (!Store::reserve('event-daily|' . $date, Config::limit('daily_event_cap', 200), time() + DAY_IN_SECONDS)
            || !Store::reserve('contact-daily|' . $contact['email_hash'] . '|' . $date, Config::limit('per_contact_daily_cap', 3), time() + DAY_IN_SECONDS)) {
            Store::finish($id, 'pending', 'capacity_deferred', strtotime('tomorrow UTC') + 300); return;
        }
        $last = Store::contact($contact['email_hash']);
        $fresh_job = Store::delivery($job['delivery_key']);
        if (!$fresh_job || $fresh_job['state'] !== 'sending') return;
        if (!$last || $last['state'] !== 'confirmed' || !Config::readiness()['ready']) {
            Store::finish($id, 'suppressed', 'consent_or_configuration_changed'); return;
        }
        // Capture cancellation/refund/cart changes after initial evaluation and before dispatch.
        $properties = self::properties($fresh_job, $last);
        if (is_wp_error($properties)) {
            $retry_at = (int) (($properties->get_error_data()['retry_at'] ?? 0));
            Store::finish($id, $properties->get_error_code() === 'payment_unknown' ? 'review_required' : ($retry_at ? 'pending' : 'suppressed'), $properties->get_error_code(), $retry_at);
            return;
        }
        $fresh_job = Store::delivery($job['delivery_key']);
        $last = Store::contact($contact['email_hash']);
        if (!$fresh_job || $fresh_job['state'] !== 'sending' || !$last || $last['state'] !== 'confirmed') return;
        $result = Api::event($last, $job['event_name'], $properties);
        if (!is_wp_error($result)) { Store::finish($id, 'accepted'); return; }
        $code = $result->get_error_code();
        if ($code === 'provider_ambiguous') Store::finish($id, 'review_required', $code);
        elseif ($code === 'provider_rate_limited') Store::finish($id, 'pending', $code, time() + 900);
        else Store::finish($id, 'failed', $code);
    }
}
