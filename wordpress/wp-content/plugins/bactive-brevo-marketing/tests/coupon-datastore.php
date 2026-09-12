<?php
/**
 * Disposable real WooCommerce fixture entrypoint, for both CPT and HPOS.
 *
 * The runner must disable all mail/network delivery, define
 * BACTIVE_BREVO_TEST_FIXTURE=true, load WordPress/WooCommerce and this plugin,
 * and destroy the disposable database afterwards. Never deploy this file.
 *
 * bactive_brevo_coupon_datastore_checks($configure) returns named passing checks
 * and two order IDs. $configure(array $settings) must set the actual Config
 * storage. It is intentionally provided by the runner rather than guessed here.
 *
 * To test actual concurrent requests, call
 * bactive_brevo_coupon_datastore_claim($order_id) in separate WP-CLI processes
 * for each returned concurrency_orders ID. Only the smaller ID may be accepted;
 * the other returns bactive_first_order_reserved (or bactive_first_order_busy).
 * Repeat the winning ID to prove idempotence. No HTTP request is required.
 */

use Bactive\Brevo\Config;
use Bactive\Brevo\Coupon;

function bactive_brevo_coupon_fixture_only(): void
{
    if (!defined('BACTIVE_BREVO_TEST_FIXTURE') || BACTIVE_BREVO_TEST_FIXTURE !== true
        || !defined('WP_CLI') || WP_CLI !== true) {
        throw new RuntimeException('This test requires an explicitly disposable WP-CLI fixture.');
    }
}

function bactive_brevo_coupon_datastore_claim(int $order_id): string
{
    bactive_brevo_coupon_fixture_only();
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('_bactive_brevo_coupon_fixture', true) !== 'yes') {
        throw new RuntimeException('Refusing a non-fixture order.');
    }
    $result = Coupon::claim_order($order);
    return is_wp_error($result) ? $result->get_error_code() : 'accepted';
}

function bactive_brevo_coupon_datastore_checks(callable $configure): array
{
    bactive_brevo_coupon_fixture_only();
    global $wpdb;
    $checks = array();
    $assert = static function ($condition, string $name) use (&$checks): void {
        if (!$condition) {
            throw new RuntimeException('Coupon datastore failure: ' . $name);
        }
        $checks[] = $name;
    };
    $configure(array('enabled' => false, 'coupon_id' => 0, 'coupon_code' => 'BACTIVE5'));
    $assert(Coupon::install() === true, 'InnoDB claim storage installs without provisioning a coupon');
    $coupon_id = Coupon::provision();
    $assert(is_int($coupon_id) && $coupon_id > 0, 'explicit provisioning returns a coupon ID');
    $coupon = new WC_Coupon($coupon_id);
    $assert($coupon->get_status() === 'draft', 'provisioned coupon is a draft');
    $assert(Coupon::provision() === $coupon_id, 'draft coupon provisioning is idempotent');
    $assert($coupon->get_discount_type() === 'percent' && (float) $coupon->get_amount() === 5.0
        && $coupon->get_individual_use() && $coupon->get_usage_limit_per_user() === 1
        && $coupon->get_usage_limit() === 0 && !$coupon->get_free_shipping()
        && !$coupon->get_exclude_sale_items() && !$coupon->get_date_expires(), 'native coupon settings match the public offer');
    $coupon->set_status('publish');
    $coupon->save();
    $configure(array('enabled' => true, 'coupon_id' => $coupon_id, 'coupon_code' => 'BACTIVE5'));
    $assert(Config::enabled() && (int) Config::get('coupon_id', 0) === $coupon_id, 'runner configured the real plugin settings');
    Coupon::register();
    $assert(Coupon::ready(), 'read-only readiness verifies the enabled native coupon and complete guard');

    $suffix = bin2hex(random_bytes(5));
    $address = static fn(string $label): string => $label . '-' . $suffix . '@example.test';
    $product = new WC_Product_Simple();
    $product->set_name('Disposable coupon test item');
    $product->set_regular_price('1000');
    $product->set_price('1000');
    $product->set_tax_status('none');
    $product->set_status('private');
    $product->save();

    $make = static function (string $email, bool $discounted = true, string $status = 'pending', int $customer_id = 0) use ($product, $coupon_id): WC_Order {
        $order = new WC_Order();
        $order->set_billing_email($email);
        $order->set_customer_id($customer_id);
        $order->set_payment_method('cod');
        $order->set_status($status);
        $order->update_meta_data('_bactive_brevo_coupon_fixture', 'yes');
        $order->add_product($product, 1);
        if ($discounted) {
            // Model a coupon already copied from cart into an order immediately
            // before the authoritative guard. Calling apply_coupon here would
            // run the earlier eligibility filter and hide race-path coverage.
            $line = new WC_Order_Item_Coupon();
            $line->set_code((new WC_Coupon($coupon_id))->get_code());
            $line->set_discount(50);
            $order->add_item($line);
        }
        $order->calculate_totals(false);
        $order->save();
        return $order;
    };
    $rejected = static function ($result, string $reason): bool {
        return is_wp_error($result) && $result->get_error_code() === 'bactive_first_order_' . $reason;
    };

    // Native arithmetic/usage uses the real API, separately from the race fixtures.
    $native = $make($address('native'), false);
    $applied = $native->apply_coupon(new WC_Coupon($coupon_id));
    $native->calculate_totals(false);
    $native->save();
    $assert($applied === true && (float) $native->get_discount_total() === 50.0
        && (float) $native->get_total() === 950.0, 'WooCommerce computes exactly 5 percent of a product line');
    $native_usage = (new WC_Coupon($coupon_id))->get_usage_count();
    $assert(Coupon::claim_order($native) === true && Coupon::claim_order($native) === true, 'first guest order and original retry succeed');
    $assert((new WC_Coupon($coupon_id))->get_usage_count() === $native_usage, 'guard does not rewrite native usage counters');
    $later = $make($address('native'));
    $assert($rejected(Coupon::claim_order($later), 'reserved'), 'second guest pending order cannot claim the offer');
    $configure(array('enabled' => false, 'coupon_id' => $coupon_id, 'coupon_code' => 'BACTIVE5'));
    $assert($rejected(Coupon::claim_order($later), 'configuration'), 'disabled integration cannot bypass eligibility on a saved discounted order');
    $configure(array('enabled' => true, 'coupon_id' => $coupon_id, 'coupon_code' => 'BACTIVE5'));
    $coupon->set_status('draft');
    $coupon->save();
    $assert($rejected(Coupon::claim_order($later), 'configuration'), 'unpublishing the coupon cannot bypass eligibility on a saved discounted order');
    $coupon->set_status('publish');
    $coupon->save();

    $first_full_price = $make($address('fullprice'), false);
    $second_discounted = $make($address('fullprice'));
    $assert($rejected(Coupon::claim_order($second_discounted), 'reserved'), 'earlier full-price order reserves first-order eligibility');
    $assert(Coupon::claim_order($first_full_price) === true, 'full-price order is accepted without price changes');
    $assert((float) $first_full_price->get_total() === 1000.0, 'guard leaves full-price total unchanged');

    foreach (array('processing', 'completed', 'on-hold', 'refunded') as $status) {
        $make($address($status), false, $status);
        $assert($rejected(Coupon::claim_order($make($address($status))), 'used'), 'prior ' . $status . ' order disqualifies');
    }
    $native->set_date_paid(time());
    $native->set_status('completed');
    $native->save();
    Coupon::payment_complete($native->get_id());
    Coupon::payment_complete($native->get_id());
    $native->delete(true);
    $assert($rejected(Coupon::claim_order($later), 'used'), 'consumed claim survives duplicate callbacks and order deletion');

    $account_email = $address('account-login');
    $user_id = wp_insert_user(array(
        'user_login' => 'coupon_fixture_' . $suffix,
        'user_email' => $account_email,
        'user_pass' => wp_generate_password(40, true, true),
        'role' => 'customer',
    ));
    $assert(is_int($user_id) && $user_id > 0, 'synthetic customer identity created');
    $account_order = $make($address('account-billing'), true, 'pending', $user_id);
    $assert(Coupon::claim_order($account_order) === true, 'new account can reserve its first order');
    $assert($rejected(Coupon::claim_order($make($address('account-billing'))), 'reserved'), 'guest checkout cannot bypass account billing claim');
    $assert($rejected(Coupon::claim_order($make($address('account-changed'), true, 'pending', $user_id)), 'reserved'), 'changed billing email cannot bypass account identity');

    $paymongo = $make($address('payment'));
    $paymongo->set_payment_method('bactive_paymongo');
    $paymongo->save();
    $assert(Coupon::claim_order($paymongo) === true, 'pending hosted-payment order reserves without provider access');
    $paymongo->set_status('failed');
    $paymongo->save();
    $assert($rejected(Coupon::claim_order($paymongo), 'payment'), 'failed payment with no recovery adapter is ineligible');
    $replacement = $make($address('payment'));
    $assert($rejected(Coupon::claim_order($replacement), 'reserved'), 'unknown old payment blocks a replacement order');
    $clear_fixture_payment = static function ($state, $order) use ($paymongo) {
        return $order->get_id() === $paymongo->get_id() ? 'clear' : $state;
    };
    add_filter('bactive_brevo_coupon_payment_state', $clear_fixture_payment, PHP_INT_MAX, 2);
    $assert($rejected(Coupon::claim_order($replacement), 'reserved'), 'generic adapter cannot override protected modern payment history');
    $paymongo->set_payment_method('fixture_verified_gateway');
    $paymongo->save();
    $assert(Coupon::claim_order($replacement) === true, 'read-only verified no-evidence non-PayMongo state permits replacement');
    remove_filter('bactive_brevo_coupon_payment_state', $clear_fixture_payment, PHP_INT_MAX);

    $table = $wpdb->prefix . 'bactive_brevo_coupon_claims';
    $hashes = $wpdb->get_col("SELECT identity_hash FROM {$table}");
    $assert($wpdb->last_error === '' && $hashes && count(array_filter($hashes, static fn($hash) => preg_match('/^[a-f0-9]{64}$/D', $hash))) === count($hashes), 'real claim rows contain opaque identity hashes');
    $assert((string) $wpdb->get_var($wpdb->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table
    )) === 'InnoDB', 'real claim table uses transactional storage');

    // The runner executes these IDs in two separate CLI processes so GET_LOCK
    // and the unique-key/transaction behavior are tested on real connections.
    $race_first = $make($address('concurrent'));
    $race_second = $make($address('concurrent'));
    return array(
        'checks' => $checks,
        'coupon_id' => $coupon_id,
        'concurrency_orders' => array($race_first->get_id(), $race_second->get_id()),
        'concurrency_expected' => array('accepted', 'bactive_first_order_reserved'),
        'cleanup' => 'Destroy this disposable fixture database; preserve production claim storage on rollback.',
    );
}
