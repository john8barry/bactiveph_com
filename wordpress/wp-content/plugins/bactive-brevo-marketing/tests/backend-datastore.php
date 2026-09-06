<?php
/** Disposable WordPress/WooCommerce database tests. Never include in a release. */

use Bactive\Brevo\Api;
use Bactive\Brevo\Automations;
use Bactive\Brevo\Config;
use Bactive\Brevo\Consent;
use Bactive\Brevo\Store;
use Bactive\Brevo\Webhooks;

function bactive_brevo_backend_integration_tests(): array
{
    if (!defined('BACTIVE_BREVO_TEST_FIXTURE') || BACTIVE_BREVO_TEST_FIXTURE !== true
        || !defined('WP_CLI') || WP_CLI !== true) {
        throw new RuntimeException('An explicitly disposable CLI fixture is required.');
    }
    global $wpdb;
    foreach (['BACTIVE_BREVO_API_KEY', 'BACTIVE_BREVO_WEBHOOK_TOKEN', 'BACTIVE_BREVO_TURNSTILE_SECRET'] as $constant) {
        if (defined($constant) || getenv($constant)) throw new RuntimeException('Fixture must not contain provider credentials.');
        define($constant, 'disposable-fixture-value-never-a-provider-secret');
    }
    $checks = [];
    $assert = static function (mixed $condition, string $name) use (&$checks): void {
        if (!$condition) throw new RuntimeException('Backend datastore failure: ' . $name);
        $checks[] = $name;
    };
    $error = static fn(mixed $value, string $code): bool => is_wp_error($value) && $value->get_error_code() === $code;
    $suffix = bin2hex(random_bytes(5));
    $email = 'backend-' . $suffix . '@example.test';
    $second = 'suppression-' . $suffix . '@example.test';
    $settings = Config::defaults() + [];
    $settings = array_merge($settings, [
        'enabled' => true, 'test_mode' => true, 'test_recipients' => [$email, $second],
        'confirmed_list_id' => 41, 'doi_template_id' => 42, 'turnstile_site_key' => 'fixture-public-key',
        'launch_cutoff' => time() - 120 * DAY_IN_SECONDS, 'automations_verified' => true,
        'daily_signup_cap' => 100, 'daily_event_cap' => 200, 'per_contact_daily_cap' => 3,
    ]);
    update_option(Config::OPTION, $settings, false);
    update_option('bactive_brevo_cron_evidence', ['count' => 2, 'last' => time()], false);
    $assert(Store::ready(), 'all four marketing tables use transactional InnoDB storage');
    $assert(Config::readiness()['ready'], 'fully mocked fixture satisfies event readiness');

    $http = ['doi' => [], 'events' => [], 'read' => 0, 'mode' => 'accepted', 'blocked' => false];
    $contacts = [];
    $mock = static function (mixed $prior, array $args, string $url) use (&$http, &$contacts): mixed {
        $reply = static fn(int $code, mixed $body): array => ['response' => ['code' => $code], 'body' => is_string($body) ? $body : wp_json_encode($body), 'headers' => []];
        if ($url === 'https://challenges.cloudflare.com/turnstile/v0/siteverify') {
            return $reply(200, ['success' => true, 'hostname' => 'bactiveph.com', 'action' => 'newsletter']);
        }
        if ($url === 'https://api.brevo.com/v3/contacts/doubleOptinConfirmation') {
            $data = json_decode($args['body'], true);
            $http['doi'][] = $data;
            // Deliberately model provider attributes/list membership changing BEFORE the DOI click.
            $contacts[$data['email']] = ['id' => 700 + count($http['doi']), 'email' => $data['email'],
                'emailBlacklisted' => false, 'listIds' => [41], 'attributes' => $data['attributes']];
            return $reply(201, []);
        }
        if (str_starts_with($url, 'https://api.brevo.com/v3/contacts/') && ($args['method'] ?? '') === 'GET') {
            ++$http['read'];
            $contact_email = rawurldecode(substr($url, strlen('https://api.brevo.com/v3/contacts/')));
            if (!isset($contacts[$contact_email])) return $reply(404, []);
            $data = $contacts[$contact_email];
            if ($http['blocked']) $data['emailBlacklisted'] = true;
            return $reply(200, $data);
        }
        if ($url === 'https://api.brevo.com/v3/events') {
            $http['events'][] = json_decode($args['body'], true);
            return $http['mode'] === 'ambiguous' ? new WP_Error('http_request_failed', 'Synthetic timeout') : $reply(204, '');
        }
        return new WP_Error('fixture_network_denied', 'Unexpected outbound request is denied.');
    };
    add_filter('pre_http_request', $mock, PHP_INT_MAX, 3);
    $old_errors = $wpdb->suppress_errors(true);
    try {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        unset($_COOKIE[Consent::IDENTITY_COOKIE], $_COOKIE[Consent::PENDING_COOKIE]);
        wp_set_current_user(0);
        $assert($error(Consent::subscribe($email, 'footer', 'fixture-captcha', false), 'invalid_signup'), 'unchecked consent cannot start DOI');
        $first = Consent::subscribe($email, 'footer', 'fixture-captcha', true);
        $assert(is_array($first) && $first['state'] === 'pending' && count($http['doi']) === 1, 'explicit signup starts exactly one native DOI request');
        $assert(!isset($first['token']) && !isset($_COOKIE[Consent::IDENTITY_COOKIE]) && Consent::current_identity() === null, 'signup response and early provider marker cannot bind browser identity');
        parse_str((string) wp_parse_url($http['doi'][0]['redirectionUrl'], PHP_URL_QUERY), $query);
        $return_token = $query['ba_brevo_confirm'];
        $assert($error(Consent::confirm_from_token(str_repeat('0', 64)), 'confirmation_invalid'), 'invented confirmation token cannot claim subscribed provider contact');
        Consent::subscribe($email, 'footer', 'fixture-captcha', true);
        $assert(count($http['doi']) === 1, 'duplicate pending signup does not resend DOI');

        $failed = false;
        $fail_welcome = static function (string $sql) use (&$failed): string {
            if (!$failed && str_starts_with($sql, 'INSERT INTO ' . Store::table('outbox'))) {
                $failed = true;
                return 'INSERT INTO bactive_fixture_missing_table (id) VALUES (1)';
            }
            return $sql;
        };
        add_filter('query', $fail_welcome);
        $result = Consent::confirm_from_token($return_token);
        remove_filter('query', $fail_welcome);
        $assert(is_wp_error($result) && $failed && Store::contact(Store::email_hash($email))['state'] === 'pending', 'failed welcome INSERT rolls back consent and preserves confirmation token');
        $confirmed = Consent::confirm_from_token($return_token);
        $assert(is_array($confirmed) && $confirmed['state'] === 'confirmed', 'authentic return token plus provider readback confirms subscription');
        $contact = Store::contact(Store::email_hash($email));
        $identity_token = $_COOKIE[Consent::IDENTITY_COOKIE] ?? '';
        $assert(strlen($identity_token) === 64 && Consent::current_identity()['email_hash'] === $contact['email_hash'], 'only confirmed browser receives usable identity');
        $assert($error(Consent::confirm_from_token($return_token), 'confirmation_invalid'), 'DOI return proof is one time');
        $assert((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Store::table('outbox') . " WHERE event_name='ba_welcome_ready'") === 1, 'confirmation creates one durable welcome');
        Consent::subscribe($email, 'footer', 'fixture-captcha', true);
        $assert(count($http['doi']) === 1, 'confirmed signup does not resend DOI or welcome');

        $user_id = wp_insert_user(['user_login' => 'unverified_' . $suffix, 'user_email' => $email,
            'user_pass' => wp_generate_password(32), 'role' => 'customer']);
        $assert(is_int($user_id) && $user_id > 0, 'fixture creates an account using subscribed email without mailbox verification');
        wp_set_current_user($user_id);
        unset($_COOKIE[Consent::IDENTITY_COOKIE]);
        $assert(Consent::current_identity() === null, 'account login with victim email alone cannot bind marketing identity');
        $_COOKIE[Consent::IDENTITY_COOKIE] = $identity_token;
        $assert(Consent::current_identity() !== null, 'matching account still requires valid confirmation cookie');
        wp_set_current_user(0);

        // Keep welcome outside the worker window: coupon fixtures independently validate the offer.
        $wpdb->update(Store::table('outbox'), ['due_at' => time() + DAY_IN_SECONDS], ['event_name' => 'ba_welcome_ready']);
        $assert(Store::queue($contact['email_hash'], 'ba_cart_reminder_ready', '2h', 'cart', 'fixture-dedupe', time()), 'local outbox accepts a valid queued event');
        Store::queue($contact['email_hash'], 'ba_cart_reminder_ready', '2h', 'cart', 'fixture-dedupe', time());
        $rows = $wpdb->get_results('SELECT * FROM ' . Store::table('outbox') . " WHERE entity_id='fixture-dedupe'", ARRAY_A);
        $assert(count($rows) === 1, 'SQL uniqueness deduplicates identical cart stage');
        $assert(Store::claim((int) $rows[0]['id']) && !Store::claim((int) $rows[0]['id']), 'only one worker can claim a due job');
        Store::cancel_cart('fixture-dedupe', 'order_submitted');
        $assert(Store::delivery($rows[0]['delivery_key'])['state'] === 'review_required', 'checkout invalidates an already claimed cart job');

        $make_order = static function (string $status = 'pending', int $created = 0) use ($email): WC_Order {
            $order = new WC_Order();
            $order->set_billing_email($email);
            $order->set_payment_method('cod');
            $order->set_status($status);
            $order->set_total(100);
            $order->set_date_created($created ?: time() - 4 * DAY_IN_SECONDS);
            $order->update_meta_data('_bactive_brevo_backend_fixture', 'yes');
            $order->save();
            return $order;
        };
        if (!WC()->cart || !WC()->session) wc_load_cart();
        $cod = new WC_Gateway_COD();
        $cod_order = $make_order();
        Automations::order_submitted($cod_order->get_id());
        $cod_result = $cod->process_payment($cod_order->get_id());
        $cod_fresh = wc_get_order($cod_order->get_id());
        $cod_fresh->get_data_store()->read($cod_fresh);
        $assert($cod_result['result'] === 'success' && $cod_fresh->get_status() === 'processing'
            && !$cod_fresh->get_date_paid('edit'), 'native nonzero COD submission is processing without recorded payment');
        $assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Store::table('outbox') . " WHERE entity_kind='order' AND entity_id=%s", (string) $cod_order->get_id())) === 0,
            'native COD submission cancels cart reminders without queuing unpaid purchase care');
        $unproven = $make_order('processing');
        $unproven->set_date_paid(time() - 3 * DAY_IN_SECONDS);
        $unproven->save();
        Automations::observe_order($unproven->get_id());
        $assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Store::table('outbox') . " WHERE entity_kind='order' AND entity_id=%s", (string) $unproven->get_id())) === 0, 'billing email alone cannot create purchase marketing jobs');
        $order = $make_order();
        Automations::order_submitted($order->get_id());
        $order = wc_get_order($order->get_id());
        $order->read_meta_data(true);
        $assert(Automations::order_identity_matches($order, $contact), 'checkout with confirmed cookie stores order-bound consent proof');
        $order->set_date_paid(time() - 3 * DAY_IN_SECONDS);
        $order->set_status('processing');
        $order->save();
        Automations::observe_order($order->get_id());
        $job = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Store::table('outbox') . " WHERE entity_kind='order' AND entity_id=%s AND stage='care'", (string) $order->get_id()), ARRAY_A);
        $assert(is_array($job), 'proven paid order queues care once');
        $http['mode'] = 'ambiguous';
        Automations::run_due();
        $assert(Store::delivery($job['delivery_key'])['state'] === 'review_required' && count($http['events']) === 1, 'ambiguous event POST is held for review');
        Automations::run_due();
        $assert(count($http['events']) === 1, 'ambiguous POST is never blindly retried');
        $payload = $http['events'][0];
        $assert($payload['identifiers'] === ['contact_id' => (int) $contact['provider_id']]
            && !isset($payload['event_properties']['email']) && !isset($payload['event_properties']['order_key']), 'events address existing contact ID without email, payment key, or billing data');

        $request = static function (array $body, bool $valid = true): WP_REST_Request {
            $request = new WP_REST_Request('POST', '/bactive-brevo/v1/webhook');
            $request->set_header('Authorization', 'Bearer ' . ($valid ? BACTIVE_BREVO_WEBHOOK_TOKEN : 'invalid'));
            $request->set_body(wp_json_encode($body));
            return $request;
        };
        $receipt = $request(['event' => 'event_accepted', 'email' => $email, 'delivery_key' => $job['delivery_key']]);
        $assert(Webhooks::handle($receipt) instanceof WP_REST_Response
            && Store::delivery($job['delivery_key'])['state'] === 'workflow_received', 'authenticated exact event receipt resolves workflow intake only');
        $assert(Webhooks::handle($receipt)->get_data()['duplicate'] === true, 'receipt replay is idempotently acknowledged');
        $assert($error(Webhooks::handle($request(['event' => 'unsubscribed', 'email' => $email, 'ts' => time()], false)), 'webhook_unauthorized'), 'invalid webhook authentication cannot suppress contact');
        $assert($error(Webhooks::handle($request(['event' => 'subscribe', 'email' => $email, 'ts' => time()])), 'webhook_unknown_event'), 'webhook cannot grant consent');

        $product = new WC_Product_Simple();
        $product->set_name('Disposable cart item');
        $product->set_status('publish');
        $product->set_regular_price('100');
        $product->set_price('100');
        $product->set_virtual(true);
        $product->save();
        WC()->cart->empty_cart();
        unset($_COOKIE[Consent::IDENTITY_COOKIE]);
        $cart_item_key = WC()->cart->add_to_cart($product->get_id(), 1);
        WC()->cart->calculate_totals();
        $assert($cart_item_key && (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Store::table('carts')) === 0, 'anonymous native Woo cart stores no marketing snapshot');
        $_COOKIE[Consent::IDENTITY_COOKIE] = $identity_token;
        WC()->session->set_customer_session_cookie(true);
        WC()->cart->calculate_totals();
        WC()->session->save_data();
        $cart_key = (string) WC()->session->get('bactive_brevo_cart');
        $cart_row = Store::cart($cart_key);
        $assert(is_array($cart_row) && $cart_row['email_hash'] === $contact['email_hash'], 'confirmed browser creates a minimal native cart snapshot');
        $cart_jobs = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Store::table('outbox') . " WHERE entity_kind='cart' AND entity_id=%s ORDER BY due_at", $cart_key), ARRAY_A);
        $assert(count($cart_jobs) === 2 && array_column($cart_jobs, 'stage') === ['2h', '24h'], 'native cart creates exactly two timed reminder stages');
        $wpdb->update(Store::table('carts'), ['created_at' => time() - 3 * HOUR_IN_SECONDS, 'updated_at' => time() - 2 * HOUR_IN_SECONDS - 1], ['cart_key' => $cart_key]);
        $props = Automations::properties($cart_jobs[0], $contact);
        $assert(is_array($props) && count($props['items']) === 1 && (float) $props['items'][0]['quantity'] === 1.0, 'due cart rereads actual stored Woo session and current products');
        WC()->cart->set_quantity($cart_item_key, 2, false);
        (new WC_Cart_Session(WC()->cart))->set_session();
        WC()->session->save_data();
        $assert($error(Automations::properties($cart_jobs[0], $contact), 'cart_changed_or_expired'), 'changed native session invalidates the old cart snapshot before sending');
        WC()->cart->calculate_totals();
        WC()->session->save_data();
        $wpdb->update(Store::table('outbox'), ['due_at' => time() - 1], ['id' => $cart_jobs[0]['id']]);
        $assert(Store::claim((int) $cart_jobs[0]['id']), 'native cart first stage can be claimed once');
        Store::finish((int) $cart_jobs[0]['id'], 'accepted');
        WC()->cart->calculate_totals();
        $assert(Store::delivery($cart_jobs[0]['delivery_key'])['state'] === 'accepted', 'new cart activity never requeues an already accepted first stage');
        $wpdb->update(Store::table('outbox'), ['due_at' => time() - 1], ['id' => $cart_jobs[1]['id']]);
        $assert(Store::claim((int) $cart_jobs[1]['id']), 'native cart second stage can be claimed independently');
        $cart_stop = $make_order('pending', time());
        Automations::order_submitted($cart_stop->get_id());
        $assert(Store::cart($cart_key)['state'] === 'cancelled'
            && Store::delivery($cart_jobs[1]['delivery_key'])['state'] === 'review_required', 'pending COD checkout cancels the native cart and a claimed second reminder');
        WC()->cart->empty_cart();

        // Invalidate an order in the narrow window after initial evaluation, before dispatch.
        $late = $make_order();
        Automations::order_submitted($late->get_id());
        $late = wc_get_order($late->get_id());
        $late->set_date_paid(time() - 3 * DAY_IN_SECONDS);
        $late->set_status('processing');
        $late->save();
        $cancelled = false;
        $daily_key = Store::hash('event-daily|' . gmdate('Ymd'));
        $cancel_after_check = static function (string $sql) use (&$cancelled, $daily_key, $late): string {
            if (!$cancelled && str_starts_with($sql, 'INSERT INTO ' . Store::table('controls')) && str_contains($sql, $daily_key)) {
                $cancelled = true;
                $fresh = wc_get_order($late->get_id());
                $fresh->set_status('cancelled');
                $fresh->save();
            }
            return $sql;
        };
        add_filter('query', $cancel_after_check);
        $before = count($http['events']);
        Automations::run_due();
        remove_filter('query', $cancel_after_check);
        $assert($cancelled && count($http['events']) === $before, 'order cancellation after first eligibility check prevents event dispatch');

        $old = $make_order('completed', time() - 100 * DAY_IN_SECONDS);
        $old->set_date_paid(time() - 99 * DAY_IN_SECONDS);
        $old->set_date_completed(time() - 98 * DAY_IN_SECONDS);
        $old->save();
        $assert(Automations::newer_purchase_exists($old, $email), 'newer pending or processing order suppresses old win-back cycle');
        $paymongo = $make_order();
        $paymongo->update_meta_data('_bactive_paymongo_attempts', 'malformed');
        $paymongo->save_meta_data();
        $assert(Automations::payment_certainty($paymongo) === 'unknown', 'malformed PayMongo state stays unknown even on COD');
        $paymongo->delete_meta_data('_bactive_paymongo_attempts');
        $paymongo->update_meta_data('paymongo_client_key', 'fixture-not-a-client-key');
        $assert(Automations::payment_certainty($paymongo) === 'unknown', 'legacy PayMongo evidence cannot be cleared by current payment method');

        $webhook_failed = false;
        $fault = static function (string $sql) use (&$webhook_failed): string {
            if (!$webhook_failed && str_starts_with($sql, 'UPDATE `' . Store::table('contacts') . '`')) {
                $webhook_failed = true;
                return 'UPDATE bactive_fixture_missing_table SET id=1';
            }
            return $sql;
        };
        $suppression = $request(['event' => 'unsubscribed', 'email' => $email, 'ts' => time(), 'id' => 'atomic-' . $suffix]);
        add_filter('query', $fault);
        $failed_response = Webhooks::handle($suppression);
        remove_filter('query', $fault);
        $assert($webhook_failed && $error($failed_response, 'webhook_storage_failed')
            && Store::contact($contact['email_hash'])['state'] === 'confirmed', 'failed webhook effect rolls back replay marker and contact mutation');
        $retried = Webhooks::handle($suppression);
        $assert($retried instanceof WP_REST_Response && empty($retried->get_data()['duplicate'])
            && Store::contact($contact['email_hash'])['state'] === 'suppressed', 'provider retry persists suppression after transient database failure');
        $assert(Consent::current_identity() === null, 'opt-out invalidates existing browser identity immediately');
        $assert((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Store::table('outbox') . " WHERE state IN ('pending','sending')") === 0, 'opt-out suppresses all outstanding jobs');

        Store::suppress_email($second, 'unsubscribed');
        $hash = Store::email_hash($second);
        $interleaved = false;
        $race = static function (string $sql) use (&$interleaved, $hash, $wpdb): string {
            if (!$interleaved && str_starts_with($sql, 'UPDATE `' . Store::table('contacts') . '`')
                && str_contains($sql, "`state` = 'pending'")) {
                $interleaved = true;
                $wpdb->update(Store::table('contacts'), ['reason' => 'hard_bounce'], ['email_hash' => $hash]);
            }
            return $sql;
        };
        add_filter('query', $race);
        $pending = Store::pending($second, 'footer', bin2hex(random_bytes(32)), bin2hex(random_bytes(32)));
        remove_filter('query', $race);
        $assert($interleaved && !$pending && Store::contact($hash)['reason'] === 'hard_bounce', 'same-second hard suppression wins over resubscribe CAS');

        $http['blocked'] = true;
        $fake_contact = $contact;
        $fake_contact['state'] = 'confirmed';
        $assert($error(Consent::check_live($fake_contact), 'consent_revoked'), 'provider blocklist readback always prevents event eligibility');
        $assert(count($http['events']) === $before, 'all fixture provider mutations remained HTTP mocks');
        return ['checks' => count($checks), 'passed' => $checks, 'provider_event_calls' => count($http['events']), 'network' => 'mocked_only'];
    } finally {
        remove_filter('pre_http_request', $mock, PHP_INT_MAX);
        $wpdb->suppress_errors($old_errors);
        wp_set_current_user(0);
        unset($_COOKIE[Consent::IDENTITY_COOKIE], $_COOKIE[Consent::PENDING_COOKIE]);
    }
}
