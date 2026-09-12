<?php

declare(strict_types=1);

namespace BActive\PayMongo {
    // Install before loading runtime classes; use real time outside these tests.
    function time(): int
    {
        return $GLOBALS['abandoned_session_test_now'] ?? \time();
    }
}

namespace {
    use BActive\PayMongo\Gateway;
    use BActive\PayMongo\Integrity;
    use BActive\PayMongo\Order_Lock;
    use BActive\PayMongo\Reconciler;

    /** All orders, keys, responses and elapsed time in this suite are synthetic. */
    function abandoned_session_recovery_tests(): void
    {
        global $abandoned_session_test_now, $fake_orders, $fake_options,
            $fake_scheduled, $fake_remote_handler, $fake_hook_calls;

        $setup = static function (int $age): WC_Order {
            global $abandoned_session_test_now, $fake_orders, $fake_scheduled;
            rollout_test_setup();
            $abandoned_session_test_now = 2000000000;
            $fake_scheduled = array();
            $order = $fake_orders[42];
            $order->meta['_bactive_paymongo_attempts'][0]['created_at'] = $abandoned_session_test_now - $age;
            $order->meta['_bactive_paymongo_attempts'][0]['generation'] = 1;
            $order->meta['_bactive_paymongo_attempts'][0]['config_generation'] = 9;
            Reconciler::mark_required($order);
            return $order;
        };
        $unpaid = array('data' => session());
        $unpaid['data']['attributes']['payments'] = array();
        $expired = $unpaid;
        $expired['data']['attributes']['status'] = 'expired';
        $session_url = 'https://api.paymongo.com/v1/checkout_sessions/cs_test_session_123';
        $job_key = Reconciler::ORDER_HOOK . '|' . serialize(array(42));

        $no_payment_effects = static function (WC_Order $order, string $label): void {
            global $fake_options, $fake_hook_calls;
            same(false, $order->is_paid(), $label . ' remains unpaid');
            same('', $order->get_transaction_id(), $label . ' has no transaction');
            same(null, $order->get_date_paid(), $label . ' has no paid date');
            same(0, $order->payment_complete_calls, $label . ' does not invoke payment completion');
            same('123.45', $order->get_total(), $label . ' preserves the amount');
            same('PHP', $order->get_currency(), $label . ' preserves the currency');
            same(array(), test_options_with_prefix($fake_options, 'bactive_paymongo_effects_test_payment_'), $label . ' creates no payment effect claim');
            foreach (array('woocommerce_payment_complete', 'woocommerce_reduce_order_stock', 'woocommerce_order_status_processing', 'woocommerce_order_status_completed') as $hook) {
                same(0, $fake_hook_calls[$hook] ?? 0, $label . ' emits no ' . $hook);
            }
        };

        try {
            foreach (array(82799 => false, 82800 => true, 82801 => true) as $age => $should_expire) {
                $order = $setup($age);
                $calls = array();
                $fake_remote_handler = static function (string $url, array $args) use (&$calls, $unpaid, $expired, $session_url): array {
                    $method = $args['method'] ?? 'GET';
                    $calls[] = array($method, $url);
                    $body = count($calls) === 1 ? $unpaid : $expired;
                    return array('response' => array('code' => 200), 'body' => json_encode($body));
                };
                Reconciler::schedule_order(42);
                same($abandoned_session_test_now + 300, $fake_scheduled[$job_key]['time'] ?? null, 'abandoned age ' . $age . ' starts with a five-minute recovery job');
                Reconciler::run_order(42);
                $expected_calls = array(array('GET', $session_url));
                if ($should_expire) {
                    $expected_calls[] = array('POST', $session_url . '/expire');
                    $expected_calls[] = array('GET', $session_url);
                }
                same($expected_calls, $calls, 'abandoned age ' . $age . ' uses the exact expiry boundary and independent readback');
                same($should_expire ? $abandoned_session_test_now : 0, (int) (Gateway::order_attempts($order)[0]['expired_at'] ?? 0), 'abandoned age ' . $age . ' records verified expiry only');
                same(!$should_expire, Gateway::has_outstanding_attempts($order), 'abandoned age ' . $age . ' retains only outstanding work');
                same($should_expire ? '' : 'yes', (string) $order->get_meta(Reconciler::REQUIRED_META, true), 'abandoned age ' . $age . ' clears tracking only after expiry');
                same($should_expire ? null : $abandoned_session_test_now + 600, $fake_scheduled[$job_key]['time'] ?? null, 'abandoned age ' . $age . ' removes only terminal recovery jobs');
                same($should_expire ? '' : 1, $order->get_meta('_bactive_paymongo_reconcile_poll_count', true), 'abandoned age ' . $age . ' clears terminal poll count');
                same('pending', $order->get_status(), 'abandoned age ' . $age . ' leaves Woo payment pending');
                $no_payment_effects($order, 'abandoned age ' . $age);
            }

            // A successful expire response alone cannot make an attempt terminal.
            foreach (array('active', 'readback error') as $readback) {
                $order = $setup(82800);
                $calls = array();
                $fake_remote_handler = static function (string $url, array $args) use (&$calls, $unpaid, $expired, $readback) {
                    $calls[] = array($args['method'] ?? 'GET', $url);
                    if (count($calls) === 3 && $readback === 'readback error') {
                        return new WP_Error('synthetic_readback_error', 'Synthetic expiry readback failure');
                    }
                    return array('response' => array('code' => 200), 'body' => json_encode(count($calls) === 2 ? $expired : $unpaid));
                };
                Reconciler::run_order(42);
                same(array(array('GET', $session_url), array('POST', $session_url . '/expire'), array('GET', $session_url)), $calls, $readback . ' checks the independent expiry response');
                same(0, (int) (Gateway::order_attempts($order)[0]['expired_at'] ?? 0), $readback . ' does not invent terminal expiry');
                same(true, Gateway::has_outstanding_attempts($order), $readback . ' retains the existing payable session');
                same('reconciliation_abandoned_expiry_failed', $order->get_meta(Reconciler::UNRESOLVED_META, true), $readback . ' records the exact recovery incident');
                same($abandoned_session_test_now + 600, $fake_scheduled[$job_key]['time'] ?? null, $readback . ' retains a recovery job');
                $no_payment_effects($order, $readback);

                $review_before = $order->get_meta('_bactive_paymongo_review_required', true);
                $incidents_before = $order->get_meta('_bactive_paymongo_review_incidents', true);
                $review_ledger_before = test_options_with_prefix($fake_options, 'bactive_paymongo_review_test_');
                $calls = array();
                $fake_remote_handler = static function (string $url, array $args) use (&$calls, $unpaid): array {
                    $calls[] = array($args['method'] ?? 'GET', $url);
                    return array('response' => array('code' => 200), 'body' => json_encode($unpaid));
                };
                foreach (array(600, 1200, 2400) as $delay) {
                    $abandoned_session_test_now += $delay;
                    Reconciler::run_order(42);
                }
                same(array_fill(0, 3, array('GET', $session_url)), $calls, $readback . ' held attempt polls without repeating expiry POST');
                same($review_before, $order->get_meta('_bactive_paymongo_review_required', true), $readback . ' polling preserves review');
                same($incidents_before, $order->get_meta('_bactive_paymongo_review_incidents', true), $readback . ' polling adds no duplicate incident');
                same($review_ledger_before, test_options_with_prefix($fake_options, 'bactive_paymongo_review_test_'), $readback . ' preserves exact incident evidence');
                same(true, isset($fake_scheduled[$job_key]), $readback . ' held attempt retains traffic-independent scheduled recovery');
                $no_payment_effects($order, $readback . ' repeated polls');

                // Later authenticated expiry is observed without another POST.
                $calls = array();
                $fake_remote_handler = static function (string $url, array $args) use (&$calls, $expired): array {
                    $calls[] = array($args['method'] ?? 'GET', $url);
                    return array('response' => array('code' => 200), 'body' => json_encode($expired));
                };
                Reconciler::run_order(42);
                same(array(array('GET', $session_url)), $calls, $readback . ' held attempt still observes terminal readback');
                check(!empty(Gateway::order_attempts($order)[0]['expired_at']), $readback . ' records subsequently verified expiry');
                same($review_before, $order->get_meta('_bactive_paymongo_review_required', true), $readback . ' terminal readback does not acknowledge operator review');
                same(true, isset($fake_scheduled[$job_key]), $readback . ' unresolved review retains recovery');
                $no_payment_effects($order, $readback . ' later expiry');
            }

            $order = $setup(82801);
            $pending = array('data' => session('paymaya', '', 'pending'));
            $calls = array();
            $fake_remote_handler = static function (string $url, array $args) use (&$calls, $pending): array {
                $calls[] = array($args['method'] ?? 'GET', $url);
                return array('response' => array('code' => 200), 'body' => json_encode($pending));
            };
            Reconciler::run_order(42);
            same(array(array('GET', $session_url)), $calls, 'old pending Payment is inspected before the age expiry decision');
            same(0, (int) (Gateway::order_attempts($order)[0]['expired_at'] ?? 0), 'old pending Payment is never treated as safely expired');
            same(true, Gateway::has_outstanding_attempts($order), 'old pending Payment remains tracked');
            same($abandoned_session_test_now + 600, $fake_scheduled[$job_key]['time'] ?? null, 'old pending Payment has a next recovery job');
            $no_payment_effects($order, 'old pending Payment');

            // Sanitized shape captured from staging: active Session, processing
            // Payment Intent, failed Payment. All identities and amounts below
            // belong to the synthetic fixture, never to a merchant/customer.
            $processing = array('data' => session('paymaya', '', 'failed'));
            $processing['data']['attributes']['payment_intent'] = array(
                'id' => 'pi_fixture_processing_123', 'type' => 'payment_intent',
                'attributes' => array('status' => 'processing', 'livemode' => false,
                    'amount' => 12345, 'currency' => 'PHP'));
            foreach (array('active', 'expired') as $processing_status) {
                $order = $setup(90000);
                $processing['data']['attributes']['status'] = $processing_status;
                $calls = array();
                $fake_remote_handler = static function (string $url, array $args) use (&$calls, $processing): array {
                    $calls[] = array($args['method'] ?? 'GET', $url);
                    return array('response' => array('code' => 200), 'body' => json_encode($processing));
                };
                Reconciler::run_order(42);
                same(array(array('GET', $session_url)), $calls, $processing_status . ' processing Intent with failed Payment avoids automatic expiry');
                same(true, Gateway::has_outstanding_attempts($order), $processing_status . ' processing Intent remains outstanding');
                same(0, (int) (Gateway::order_attempts($order)[0]['expired_at'] ?? 0), $processing_status . ' processing Intent is not terminal expiry');
                same(true, isset($fake_scheduled[$job_key]), $processing_status . ' processing Intent retains recovery');
                $no_payment_effects($order, $processing_status . ' processing Intent');

                // The existing explicit expiry path also cannot infer terminal
                // safety from an expired Session around a processing Intent.
                $calls = array();
                same(false, (new Gateway(false))->expire_all_for_order($order), $processing_status . ' explicit expiry cannot prove a processing Intent terminal');
                same(array(array('POST', $session_url . '/expire'), array('GET', $session_url)), $calls, $processing_status . ' explicit expiry uses independent readback');
                same(true, Gateway::has_outstanding_attempts($order), $processing_status . ' explicit expiry preserves outstanding Intent');
            }
            $without_intent = $unpaid;
            same(false, Integrity::checkout_session_has_processing_intent($without_intent), 'absent optional Intent is not invented');
            $processing['data']['attributes']['payment_intent']['attributes']['status'] = 'succeeded';
            same(false, Integrity::checkout_session_has_processing_intent($processing), 'nonprocessing Intent does not masquerade as processing');

            // A held review still receives later paid provider facts. Existing
            // paid-event rules decide disposition; age must not issue a POST.
            $order = $setup(90000);
            $flag = new ReflectionMethod(Reconciler::class, 'flag_failure');
            check(Order_Lock::acquire(42), 'late paid held fixture acquires order lease');
            $flag->invoke(null, $order, 'reconciliation_abandoned_expiry_failed', 'test');
            Order_Lock::release(42);
            $paid = array('data' => session());
            $calls = array();
            $fake_remote_handler = static function (string $url, array $args) use (&$calls, $paid): array {
                $calls[] = array($args['method'] ?? 'GET', $url);
                return array('response' => array('code' => 200), 'body' => json_encode($paid));
            };
            Reconciler::run_order(42);
            same(array(array('GET', $session_url)), $calls, 'held attempt still reconciles later paid readback without expiry POST');
            same(true, $order->is_paid(), 'exact later paid facts retain the existing active-order settlement path');
            same('pay_test_payment_123', $order->get_transaction_id(), 'later paid readback retains exact transaction identity');
            same('reconciliation_abandoned_expiry_failed', $order->get_meta('_bactive_paymongo_review_required', true), 'paid recovery preserves the earlier review evidence');
            same(1, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'late paid recovery emits payment completion once');
            Reconciler::run_order(42);
            same(1, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'repeated recovery cannot duplicate late paid completion');
            same(true, isset($fake_scheduled[$job_key]), 'late paid review still has scheduled recovery');

            $order = $setup(0);
            $attempts = Gateway::order_attempts($order);
            $calls = array();
            $fake_remote_handler = static function (string $url, array $args) use (&$calls, $unpaid): array {
                $calls[] = array($args['method'] ?? 'GET', $url);
                return array('response' => array('code' => 200), 'body' => json_encode($unpaid));
            };
            foreach (array(600, 1200, 2400, 3600, 3600, 3600) as $index => $delay) {
                Reconciler::run_order(42);
                same($abandoned_session_test_now + $delay, $fake_scheduled[$job_key]['time'] ?? null, 'unpaid poll ' . ($index + 1) . ' has bounded exponential backoff');
                same(false, $fake_scheduled[$job_key]['recurrence'] ?? null, 'unpaid poll ' . ($index + 1) . ' replaces the per-order job with a single event');
                same($index + 1, $order->get_meta('_bactive_paymongo_reconcile_poll_count', true), 'unpaid poll ' . ($index + 1) . ' persists its poll count');
                same($attempts, Gateway::order_attempts($order), 'unpaid poll ' . ($index + 1) . ' preserves the exact attempt');
                $abandoned_session_test_now += $delay;
            }
            same(array_fill(0, 6, array('GET', $session_url)), $calls, 'unpaid backoff performs only canonical reads');
            $no_payment_effects($order, 'unpaid backoff');
        } finally {
            Order_Lock::release(42);
            unset($GLOBALS['abandoned_session_test_now']);
            $fake_remote_handler = null;
        }
    }
}
