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
