<?php

declare(strict_types=1);

define('BACTIVE_PAYMONGO_TESTING', true);
define('ABSPATH', __DIR__ . '/');
define('BACTIVE_PAYMONGO_TEST_ENCRYPTION_KEY', 'deterministic-test-key-not-a-production-secret');
define('BActive\\PayMongo\\GATEWAY_ID', 'bactive_paymongo');
define('BActive\\PayMongo\\VERSION', 'test');
define('BActive\\PayMongo\\PLUGIN_FILE', dirname(__DIR__) . '/bactive-paymongo-hosted-checkout.php');

require_once __DIR__ . '/abandoned-session-recovery.php';

class Test_Order_Util
{
    public static bool $hpos = true;
    public static function custom_orders_table_usage_is_enabled(): bool
    {
        return self::$hpos;
    }
}
class_alias(Test_Order_Util::class, 'Automattic\\WooCommerce\\Utilities\\OrderUtil');
$wpdb = (object) array('last_error' => '');

require_once dirname(__DIR__) . '/includes/class-integrity.php';
require_once dirname(__DIR__) . '/includes/class-secrets.php';
require_once dirname(__DIR__) . '/includes/class-api-client.php';

use BActive\PayMongo\Api_Client;
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Integrity;
use BActive\PayMongo\Order_Lock;
use BActive\PayMongo\Readiness;
use BActive\PayMongo\Reconciler;
use BActive\PayMongo\Secrets;
use BActive\PayMongo\Webhook;

$tests = 0;
$failures = array();

function check(bool $condition, string $label): void
{
    global $tests, $failures;
    ++$tests;
    if (!$condition) {
        $failures[] = $label;
    }
}

function same($expected, $actual, string $label): void
{
    check($expected === $actual, $label . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

function test_claim_option(string $kind, string $identity, string $mode = 'test'): string
{
    return 'bactive_paymongo_' . $mode . '_' . $kind . '_'
        . hash('sha256', $mode . '|' . $identity);
}

function test_quarantine_option(string $identity, string $mode = 'test'): string
{
    return 'bactive_paymongo_quarantine_' . $mode . '_'
        . hash('sha256', $mode . '|' . $identity);
}

function test_effects_option(string $kind, string $identity, string $mode = 'test'): string
{
    return 'bactive_paymongo_effects_' . $mode . '_' . $kind . '_'
        . hash('sha256', $mode . '|' . $identity);
}

function test_review_resolution_option(int $order_id, string $mode = 'test'): string
{
    return 'bactive_paymongo_review_resolution_' . $mode . '_'
        . hash('sha256', $mode . '|' . $order_id);
}

function test_processing_incident_option(string $identity, string $mode = 'test'): string
{
    return 'bactive_paymongo_processing_incident_' . $mode . '_'
        . hash('sha256', $mode . '|' . $identity);
}

function test_operator_disposition_option(string $payment_id, string $mode = 'test'): string
{
    return 'bactive_paymongo_operator_disposition_' . $mode . '_'
        . hash('sha256', $mode . '|' . $payment_id);
}

/** @return array<string,mixed> */
function test_options_with_prefix(array $options, string $prefix): array
{
    return array_filter(
        $options,
        static fn(string $key): bool => str_starts_with($key, $prefix),
        ARRAY_FILTER_USE_KEY
    );
}

/** @return array<string,mixed> */
function session(string $source_type = 'qrph', string $provider = '', string $status = 'paid'): array
{
    $source = array('type' => $source_type);
    if ($provider !== '') {
        $source['details'] = array('bank_code' => $provider);
    }

    return array(
        'id' => 'cs_test_session_123',
        'type' => 'checkout_session',
        'attributes' => array(
            'status' => 'active',
            'livemode' => false,
            'reference_number' => 'BA-42-1',
            'metadata' => array(
                'integration' => 'bactive-paymongo',
                'order_id' => '42',
                'correlation_id' => 'correlation-123',
            ),
            'payments' => array(
                array(
                    'id' => 'pay_test_payment_123',
                    'type' => 'payment',
                    'attributes' => array(
                        'amount' => 12345,
                        'currency' => 'PHP',
                        'status' => $status,
                        'livemode' => false,
                        'source' => $source,
                    ),
                ),
            ),
        ),
    );
}

/** @return array<string,mixed> */
function current_event(string $source_type = 'qrph', string $provider = ''): array
{
    return array(
        'event_type' => 'send.webhook',
        'data' => array(
            'type' => 'checkout_session.payment.paid',
            'resource' => 'checkout_session',
            'livemode' => false,
            'data' => session($source_type, $provider),
        ),
    );
}

/** @return array<string,mixed> */
function legacy_event(string $source_type = 'qrph', string $provider = ''): array
{
    return array(
        'data' => array(
            'id' => 'evt_test_event_123',
            'type' => 'event',
            'attributes' => array(
                'type' => 'checkout_session.payment.paid',
                'livemode' => false,
                'data' => session($source_type, $provider),
            ),
        ),
    );
}

/** @return array{live:bool,order_id:int,amount:int,reference:string,correlation:string,session_ids:array<int,string>} */
function context(): array
{
    return array(
        'live' => false,
        'order_id' => 42,
        'amount' => 12345,
        'reference' => 'BA-42-1',
        'correlation' => 'correlation-123',
        'session_ids' => array('cs_test_session_123'),
    );
}

foreach (array(
    '0' => 0,
    '0.1' => 10,
    '0.01' => 1,
    '1' => 100,
    '1.2' => 120,
    '123456789.99' => 12345678999,
) as $amount => $minor) {
    same($minor, Integrity::amount_to_minor((string) $amount), 'amount converts ' . $amount);
}
foreach (array('', '-1', '.50', '01', '1.', '1.234', '1e2', '1000000000000000') as $amount) {
    same(null, Integrity::amount_to_minor($amount), 'amount rejects ' . var_export($amount, true));
}

$expired_session = array(
    'data' => array(
        'id' => 'cs_test_session_123',
        'type' => 'checkout_session',
        'attributes' => array('status' => 'expired', 'livemode' => false),
    ),
);
check(
    Integrity::checkout_session_is_expired($expired_session, 'cs_test_session_123', false),
    'exact sandbox session expiry readback accepted'
);
$wrong_expired_session = $expired_session;
$wrong_expired_session['data']['id'] = 'cs_test_session_other';
check(
    !Integrity::checkout_session_is_expired($wrong_expired_session, 'cs_test_session_123', false),
    'different session expiry readback rejected'
);
$wrong_expired_mode = $expired_session;
$wrong_expired_mode['data']['attributes']['livemode'] = true;
check(
    !Integrity::checkout_session_is_expired($wrong_expired_mode, 'cs_test_session_123', false),
    'different session expiry mode rejected'
);
$active_session = $expired_session;
$active_session['data']['attributes']['status'] = 'active';
check(
    !Integrity::checkout_session_is_expired($active_session, 'cs_test_session_123', false),
    'active session is not treated as expired'
);
same('active', Integrity::checkout_session_status($active_session, 'cs_test_session_123', false), 'active session status readback accepted');
$paid_session_response = array('data' => session());
same(
    array('pay_test_payment_123'),
    Integrity::checkout_session_paid_payment_ids($paid_session_response, 'cs_test_session_123', false),
    'authenticated Checkout Session exposes exact paid payment ID'
);
$missing_payments = $paid_session_response;
unset($missing_payments['data']['attributes']['payments']);
same(
    null,
    Integrity::checkout_session_paid_payment_ids($missing_payments, 'cs_test_session_123', false),
    'missing payments collection is not mistaken for unpaid'
);
$expired_pending_response = array('data' => session('qrph', '', 'pending'));
$expired_pending_response['data']['attributes']['status'] = 'expired';
same(
    array(
        'paid' => array(),
        'pending' => array('pay_test_payment_123'),
        'failed' => array(),
    ),
    Integrity::checkout_session_payment_state($expired_pending_response, 'cs_test_session_123', false),
    'expired Checkout Session retains its pending Payment state'
);
$invalid_payment_status = $expired_pending_response;
$invalid_payment_status['data']['attributes']['payments'][0]['attributes']['status'] = 'awaiting';
same(
    null,
    Integrity::checkout_session_payment_state($invalid_payment_status, 'cs_test_session_123', false),
    'unknown Payment status makes the Checkout Session unsafe to classify'
);

same(
    'apply',
    Integrity::paid_event_disposition('bactive_paymongo', 'bactive_paymongo', false, true, false, '', 'pay_test_payment_123', false),
    'active PayMongo order may apply verified payment'
);
same(
    'duplicate',
    Integrity::paid_event_disposition('bactive_paymongo', 'bactive_paymongo', true, false, false, 'pay_test_payment_123', 'pay_test_payment_123', false),
    'exact previously applied payment is idempotent'
);
same(
    'quarantine',
    Integrity::paid_event_disposition('cod', 'bactive_paymongo', true, false, false, '', 'pay_test_payment_123', true),
    'late paid event after COD switch and expiry is quarantined'
);
same(
    'quarantine',
    Integrity::paid_event_disposition('bactive_paymongo', 'bactive_paymongo', false, true, false, '', 'pay_test_payment_123', true),
    'late paid event for expired session is quarantined'
);
same(
    'quarantine',
    Integrity::paid_event_disposition('bactive_paymongo', 'bactive_paymongo', false, true, true, '', 'pay_test_payment_123', false),
    'paid event for closed order is quarantined'
);

check(
    Integrity::payment_completion_verified(true, true, 'pay_test_payment_123', 'pay_test_payment_123'),
    'successful WooCommerce completion with exact readback accepted'
);
check(
    !Integrity::payment_completion_verified(false, true, 'pay_test_payment_123', 'pay_test_payment_123'),
    'false WooCommerce completion result rejected despite paid-looking readback'
);
check(
    !Integrity::payment_completion_verified(true, false, 'pay_test_payment_123', 'pay_test_payment_123'),
    'unpaid WooCommerce readback rejected'
);
check(
    !Integrity::payment_completion_verified(true, true, 'pay_test_payment_123', 'pay_test_payment_other'),
    'mismatched WooCommerce transaction readback rejected'
);
check(Integrity::cod_transition_is_valid(250000, 5000), 'COD cap boundary with exact fee accepted');
check(!Integrity::cod_transition_is_valid(250001, 5000), 'COD total above cap rejected');
check(!Integrity::cod_transition_is_valid(100000, 0), 'COD transition without fee rejected');
check(!Integrity::cod_transition_is_valid(100000, 10000), 'COD transition with duplicate fee rejected');

$secret = 'whsk_test_secret';
$now = 1788559200;
$raw = '{"data":"signed-exactly"}';
$test_signature = hash_hmac('sha256', $now . '.' . $raw, $secret);
$live_signature = hash_hmac('sha256', $now . '.' . $raw, $secret);
same('ok', Integrity::verify_signature($raw, "t={$now},te={$test_signature},li=", $secret, false, $now)['code'], 'test signature accepted');
same('ok', Integrity::verify_signature($raw, "t={$now},te=,li={$live_signature}", $secret, true, $now)['code'], 'live signature accepted');
same('signature_mismatch', Integrity::verify_signature($raw . 'x', "t={$now},te={$test_signature},li=", $secret, false, $now)['code'], 'tampered payload rejected');
same('signature_timestamp_outside_tolerance', Integrity::verify_signature($raw, "t={$now},te={$test_signature},li=", $secret, false, $now + 301)['code'], 'stale signature rejected');
same('signature_timestamp_outside_tolerance', Integrity::verify_signature($raw, "t={$now},te={$test_signature},li=", $secret, false, $now - 301)['code'], 'future signature rejected');
same('signature_malformed', Integrity::verify_signature($raw, "t={$now},t={$now},te={$test_signature}", $secret, false, $now)['code'], 'duplicate signature part rejected');
same('signature_value_invalid', Integrity::verify_signature($raw, "t={$now},te=not-a-signature,li=", $secret, false, $now)['code'], 'malformed signature value rejected');

foreach (array(
    array('qrph', '', 'qrph', ''),
    array('paymaya', '', 'paymaya', ''),
    array('maya', '', 'paymaya', ''),
    array('shopee_pay', '', 'shopee_pay', ''),
    array('shopeepay', '', 'shopee_pay', ''),
    array('dob', 'bpi', 'dob', 'bpi'),
    array('dob', 'ubp', 'dob', 'ubp'),
    array('dob_ubp', '', 'dob', 'ubp'),
) as $case) {
    [$source, $provider, $expected_method, $expected_provider] = $case;
    $payload = current_event($source, $provider);
    $raw_payload = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $normalized = Integrity::normalize_event($payload, (string) $raw_payload);
    check(is_array($normalized), 'current envelope normalizes for ' . $source . ':' . $provider);
    if (!is_array($normalized)) {
        continue;
    }
    $validated = Integrity::validate_paid_event($normalized, context());
    same(true, $validated['ok'], 'payment validates for ' . $source . ':' . $provider);
    if ($validated['ok']) {
        same($expected_method, $validated['method'], 'method maps for ' . $source . ':' . $provider);
        same($expected_provider, $validated['provider'], 'provider maps for ' . $source . ':' . $provider);
    }
}

$wrong_ubp_provider = current_event('dob_ubp', 'bpi');
$wrong_ubp_normalized = Integrity::normalize_event($wrong_ubp_provider, (string) json_encode($wrong_ubp_provider));
check(is_array($wrong_ubp_normalized), 'DOB UBP mismatch envelope normalizes');
if (is_array($wrong_ubp_normalized)) {
    same(false, Integrity::validate_paid_event($wrong_ubp_normalized, context())['ok'], 'DOB UBP rejects BPI provider');
}
$unknown_union_provider = current_event('dob', 'unionbank-affiliate');
$unknown_union_normalized = Integrity::normalize_event(
    $unknown_union_provider,
    (string) json_encode($unknown_union_provider)
);
check(is_array($unknown_union_normalized), 'unknown Union-named DOB envelope normalizes');
if (is_array($unknown_union_normalized)) {
    same(
        false,
        Integrity::validate_paid_event($unknown_union_normalized, context())['ok'],
        'unknown Union-named DOB provider is not misclassified as UBP'
    );
}

$legacy = legacy_event();
$legacy_normalized = Integrity::normalize_event($legacy, (string) json_encode($legacy));
check(is_array($legacy_normalized), 'legacy event envelope normalizes');
if (is_array($legacy_normalized)) {
    same('evt_test_event_123', $legacy_normalized['data']['id'], 'legacy event ID retained');
    same(true, Integrity::validate_paid_event($legacy_normalized, context())['ok'], 'legacy event validates');
}

$current = current_event();
$normalized_one = Integrity::normalize_event($current, (string) json_encode($current));
$normalized_two = Integrity::normalize_event($current, (string) json_encode($current));
check(is_array($normalized_one) && str_starts_with($normalized_one['data']['id'], 'evt_'), 'current event gets derived event ID');
same($normalized_one['data']['id'] ?? '', $normalized_two['data']['id'] ?? '', 'derived event ID is deterministic');

$bad_envelope = current_event();
$bad_envelope['event_type'] = 'unexpected';
same(null, Integrity::normalize_event($bad_envelope, (string) json_encode($bad_envelope)), 'wrong delivery envelope rejected');
$bad_resource = current_event();
$bad_resource['data']['resource'] = 'payment';
same(null, Integrity::normalize_event($bad_resource, (string) json_encode($bad_resource)), 'wrong resource rejected');

$base = Integrity::normalize_event(current_event(), (string) json_encode(current_event()));
check(is_array($base), 'base event available for negative tests');
if (is_array($base)) {
    $mutations = array(
        'event type' => static function (array &$p): void { $p['data']['attributes']['type'] = 'payment.paid'; },
        'event mode' => static function (array &$p): void { $p['data']['attributes']['livemode'] = true; },
        'session ID' => static function (array &$p): void { $p['data']['attributes']['data']['id'] = 'cs_other'; },
        'reference' => static function (array &$p): void { $p['data']['attributes']['data']['attributes']['reference_number'] = 'BA-42-2'; },
        'order metadata' => static function (array &$p): void { $p['data']['attributes']['data']['attributes']['metadata']['order_id'] = '43'; },
        'correlation metadata' => static function (array &$p): void { $p['data']['attributes']['data']['attributes']['metadata']['correlation_id'] = 'wrong'; },
        'integration metadata' => static function (array &$p): void { $p['data']['attributes']['data']['attributes']['metadata']['integration'] = 'other'; },
        'amount' => static function (array &$p): void { $p['data']['attributes']['data']['attributes']['payments'][0]['attributes']['amount'] = 12346; },
        'currency' => static function (array &$p): void { $p['data']['attributes']['data']['attributes']['payments'][0]['attributes']['currency'] = 'USD'; },
        'status' => static function (array &$p): void { $p['data']['attributes']['data']['attributes']['payments'][0]['attributes']['status'] = 'failed'; },
        'payment mode' => static function (array &$p): void { $p['data']['attributes']['data']['attributes']['payments'][0]['attributes']['livemode'] = true; },
        'method' => static function (array &$p): void { $p['data']['attributes']['data']['attributes']['payments'][0]['attributes']['source']['type'] = 'card'; },
        'DOB bank' => static function (array &$p): void {
            $p['data']['attributes']['data']['attributes']['payments'][0]['attributes']['source'] = array('type' => 'dob', 'details' => array('bank_code' => 'bdo'));
        },
    );
    foreach ($mutations as $label => $mutate) {
        $candidate = $base;
        $mutate($candidate);
        same(false, Integrity::validate_paid_event($candidate, context())['ok'], $label . ' mismatch rejected');
    }

    $one_paid = $base;
    $failed = $one_paid['data']['attributes']['data']['attributes']['payments'][0];
    $failed['id'] = 'pay_test_failed_456';
    $failed['attributes']['status'] = 'failed';
    array_unshift($one_paid['data']['attributes']['data']['attributes']['payments'], $failed);
    same(true, Integrity::validate_paid_event($one_paid, context())['ok'], 'one paid attempt among failures accepted');

    $two_paid = $base;
    $duplicate = $two_paid['data']['attributes']['data']['attributes']['payments'][0];
    $duplicate['id'] = 'pay_test_payment_456';
    $two_paid['data']['attributes']['data']['attributes']['payments'][] = $duplicate;
    same('paid_payment_count_invalid', Integrity::validate_paid_event($two_paid, context())['code'], 'multiple paid attempts rejected');

    $mixed_paid = $base;
    $disallowed = $mixed_paid['data']['attributes']['data']['attributes']['payments'][0];
    $disallowed['id'] = 'pay_test_payment_disallowed';
    $disallowed['attributes']['currency'] = 'USD';
    $disallowed['attributes']['source'] = array('type' => 'card');
    $mixed_paid['data']['attributes']['data']['attributes']['payments'][] = $disallowed;
    same(
        'paid_payment_count_invalid',
        Integrity::validate_paid_event($mixed_paid, context())['code'],
        'valid paid entry plus disallowed paid entry is not filtered into fulfillment'
    );
}

$encrypted = Secrets::encrypt('sk_test_round_trip_secret');
check(str_starts_with($encrypted, 'enc:v1:'), 'secret ciphertext is versioned');
same('sk_test_round_trip_secret', Secrets::decrypt($encrypted), 'secret decrypts with authenticated encryption');
$tampered = substr($encrypted, 0, -2) . 'AA';
same('', Secrets::decrypt($tampered), 'tampered secret fails closed');
same('', Secrets::decrypt('plaintext-secret'), 'plaintext secret rejected');

$client = new Api_Client('sk_test_placeholder');
$method = new ReflectionMethod($client, 'parse_capabilities');
$method->setAccessible(true);
same(
    array('qrph', 'paymaya'),
    $method->invoke($client, array('data' => array('attributes' => array('payment_methods' => array('QRPH', 'paymaya', 'qrph'))))),
    'capability list normalized'
);
same(
    array('qrph', 'dob_ubp'),
    $method->invoke($client, array('data' => array('attributes' => array('capabilities' => array(
        'qrph' => 'active',
        'dob' => 'processing',
        'dob_ubp' => true,
        'card' => 'disabled',
    ))))),
    'active capability map filtered'
);
same(null, $method->invoke($client, array('unexpected' => true)), 'unknown capability shape rejected');

// Minimal WooCommerce boundary harness for webhook state-transition regressions.
if (!class_exists('WC_Payment_Gateway')) {
    class WC_Payment_Gateway
    {
        public string $id = '';
        public string $method_title = '';
        public string $method_description = '';
        public bool $has_fields = false;
        public array $supports = array();
        public array $form_fields = array();
        public array $settings = array();
        public string $title = '';
        public string $description = '';
        public string $enabled = 'no';
        public array $errors = array();

        public function init_settings(): void
        {
            $this->settings = (array) get_option('woocommerce_' . $this->id . '_settings', array());
        }

        public function get_option($key, $empty_value = null)
        {
            global $fake_mutating_settings_getter;
            if ($fake_mutating_settings_getter ?? false) {
                if (!isset($this->settings[$key])) {
                    $this->settings[$key] = $this->form_fields[$key]['default'] ?? '';
                }
                if ($empty_value !== null && $this->settings[$key] === '') {
                    $this->settings[$key] = $empty_value;
                }
            }
            return $this->settings[$key] ?? $empty_value;
        }

        public function get_title(): string
        {
            return $this->title;
        }

        public function get_field_key($key): string
        {
            return 'woocommerce_' . $this->id . '_' . $key;
        }

        public function add_error($message): void { $this->errors[] = $message; }
        public function display_errors(): void {}
        public function is_available(): bool { return $this->enabled === 'yes'; }
        public function process_admin_options(): bool { return true; }
    }
}

final class Fake_Order_Data_Store
{
    public function read(&$order): void
    {
        ++$order->read_count;
        if ($order->fail_read_number === $order->read_count) {
            throw new RuntimeException('simulated readback failure');
        }
        if ($order->payment_complete_calls > 0) {
            if ($order->forced_readback_paid !== null) {
                $order->paid = $order->forced_readback_paid;
            }
            if ($order->forced_readback_transaction !== null) {
                $order->transaction_id = $order->forced_readback_transaction;
            }
        }
    }
}

if (!class_exists('WC_Order')) {
    class WC_Order
    {
        public int $id = 42;
        public string $payment_method = 'bactive_paymongo';
        public string $payment_method_title = 'Pay online securely';
        public string $currency = 'PHP';
        public string $total = '123.45';
        public string $status = 'pending';
        public bool $paid = false;
        public string $transaction_id = '';
        public ?DateTimeImmutable $date_paid = null;
        public bool $payment_complete_result = true;
        public int $payment_complete_calls = 0;
        public int $read_count = 0;
        public int $fail_read_number = -1;
        public ?bool $forced_readback_paid = null;
        public ?string $forced_readback_transaction = null;
        public array $meta = array();
        public array $notes = array();
        public array $changes = array();
        public array $items = array('line_item' => array(), 'fee' => array());
        public int $save_calls = 0;
        public int $save_attempts = 0;
        public bool $save_error_caught = false;
        public int $throw_on_save_attempt = -1;
        public int $status_transition_effects = 0;
        /** @var array<string,mixed>|false */
        protected $status_transition = false;
        private Fake_Order_Data_Store $store;

        public function __construct()
        {
            $this->store = new Fake_Order_Data_Store();
            $this->meta['_bactive_paymongo_attempts'] = array(
                array(
                    'session_id' => 'cs_test_session_123',
                    'mode' => 'test',
                    'reference' => 'BA-42-1',
                    'correlation_id' => 'correlation-123',
                ),
            );
        }

        public function get_id(): int { return $this->id; }
        public function get_total(): string { return $this->total; }
        public function get_currency(): string { return $this->currency; }
        public function get_payment_method(): string { return $this->payment_method; }
        public function get_payment_method_title(): string { return $this->payment_method_title; }
        public function get_status(): string { return $this->status; }
        public function get_transaction_id(): string { return $this->transaction_id; }
        public function get_date_paid(string $context = 'view'): ?DateTimeImmutable { return $this->date_paid; }
        public function get_changes(): array { return $this->changes; }
        public function get_items(string $type = 'line_item'): array { return $this->items[$type] ?? array(); }
        public function get_formatted_billing_full_name(): string { return 'Test Buyer'; }
        public function get_billing_email(): string { return 'buyer@example.test'; }
        public function get_billing_phone(): string { return '+639171234567'; }
        public function get_order_number(): string { return (string) $this->id; }
        public function get_checkout_order_received_url(): string { return 'https://bactive.test/order-received/' . $this->id; }
        public function is_paid(): bool { return $this->paid; }
        public function needs_payment(): bool { return !$this->paid && in_array($this->status, array('pending', 'failed'), true); }
        public function has_status($statuses): bool { return in_array($this->status, (array) $statuses, true); }
        public function get_meta(string $key, bool $single = true) { return $this->meta[$key] ?? ''; }
        public function meta_exists(string $key): bool { return array_key_exists($key, $this->meta); }
        public function update_meta_data(string $key, $value): void { $this->meta[$key] = $value; }
        public function delete_meta_data(string $key): void { unset($this->meta[$key]); }
        public function add_order_note(string $note): void { $this->notes[] = $note; }
        public function save(): int
        {
            global $fake_before_order_save, $fake_orders, $fake_persist_order_saves, $fake_persist_order_filter;
            try {
                ++$this->save_attempts;
                if (is_callable($fake_before_order_save)) {
                    $fake_before_order_save($this, $this->store);
                }
                do_action('woocommerce_before_order_object_save', $this, $this->store);
                if ($this->throw_on_save_attempt === $this->save_attempts) {
                    throw new RuntimeException('simulated data-store update failure');
                }
                ++$this->save_calls;
                if ($fake_persist_order_saves) {
                    $persisted = clone $this;
                    if (is_callable($fake_persist_order_filter)) {
                        $persisted = $fake_persist_order_filter($persisted);
                    }
                    $fake_orders[$this->id] = $persisted;
                }
                do_action('woocommerce_after_order_object_save', $this, $this->store);
            } catch (Exception $error) {
                $this->save_error_caught = true;
            }
            $this->status_transition();
            return $this->id;
        }
        public function get_data_store(): Fake_Order_Data_Store { return $this->store; }
        public function set_status(string $status, $note = '', bool $manual = false): void {
            $from = $this->status;
            $this->status = $status;
            $this->paid = in_array($status, array('processing', 'completed'), true);
            if ($from !== $status) {
                $this->status_transition = array(
                    'from' => $from,
                    'to' => $status,
                    'note' => $note,
                    'manual' => $manual,
                );
            }
        }
        public function set_transaction_id(string $transaction_id): void { $this->transaction_id = $transaction_id; }
        public function set_payment_method($payment_method = ''): void {
            $previous_method = $this->payment_method;
            $previous_title = $this->payment_method_title;
            if ($payment_method instanceof WC_Payment_Gateway) {
                $this->payment_method = $payment_method->id;
                $this->payment_method_title = $payment_method->get_title();
            } else {
                $this->payment_method = (string) $payment_method;
            }
            if ($previous_method !== $this->payment_method) {
                $this->changes['payment_method'] = $previous_method;
            }
            if ($previous_title !== $this->payment_method_title) {
                $this->changes['payment_method_title'] = $previous_title;
            }
        }
        public function set_payment_method_title(string $title): void {
            if ($this->payment_method_title !== $title) {
                $this->changes['payment_method_title'] = $this->payment_method_title;
            }
            $this->payment_method_title = $title;
        }
        public function set_date_paid($timestamp): void {
            $this->date_paid = (new DateTimeImmutable())->setTimestamp((int) $timestamp);
        }
        public function needs_processing(): bool { return true; }

        protected function status_transition(): void
        {
            $transition = $this->status_transition;
            $this->status_transition = false;
            if (!is_array($transition)) {
                return;
            }
            ++$this->status_transition_effects;
            do_action('woocommerce_order_status_' . (string) ($transition['to'] ?? ''), $this->id, $this, $transition);
            do_action(
                'woocommerce_order_status_' . (string) ($transition['from'] ?? '') . '_to_' . (string) ($transition['to'] ?? ''),
                $this->id,
                $this
            );
            do_action('woocommerce_order_status_changed', $this->id, $transition['from'] ?? '', $transition['to'] ?? '', $this);
        }

        public function update_status(string $status, string $note = '', bool $manual = false): void
        {
            $this->status = $status;
            $this->paid = in_array($status, array('processing', 'completed'), true);
            if ($note !== '') {
                $this->notes[] = $note;
            }
        }

        public function payment_complete(string $transaction_id = ''): bool
        {
            ++$this->payment_complete_calls;
            if ($this->payment_complete_result) {
                $this->transaction_id = $transaction_id;
                $this->paid = true;
                $this->status = 'processing';
            }
            return $this->payment_complete_result;
        }
    }
}

final class Fake_Catching_Status_Order extends WC_Order
{
    public function arm_status_transition(string $from, string $to): void
    {
        $this->status_transition = array('from' => $from, 'to' => $to, 'note' => '', 'manual' => false);
    }

    public function save(): int
    {
        global $fake_before_order_save;
        try {
            if (is_callable($fake_before_order_save)) {
                $fake_before_order_save($this, $this->get_data_store());
            }
        } catch (Throwable $error) {
            $this->save_error_caught = true;
        }
        if ($this->status_transition !== false) {
            ++$this->status_transition_effects;
        }
        return $this->id;
    }
}

/**
 * Mirrors WooCommerce 10.8's critical behavior: status_transition() catches an
 * Exception from an extension hook and returns no failure signal to its caller.
 */
final class Fake_Swallowing_Status_Order extends WC_Order
{
    protected function status_transition(): void
    {
        $transition = $this->status_transition;
        $this->status_transition = false;
        if (!is_array($transition)) {
            return;
        }
        ++$this->status_transition_effects;
        try {
            do_action('woocommerce_order_status_' . (string) ($transition['to'] ?? ''), $this->id, $this, $transition);
            do_action(
                'woocommerce_order_status_' . (string) ($transition['from'] ?? '') . '_to_' . (string) ($transition['to'] ?? ''),
                $this->id,
                $this
            );
            do_action('woocommerce_order_status_changed', $this->id, $transition['from'] ?? '', $transition['to'] ?? '', $this);
        } catch (Exception $error) {
            // This is the real WC_Order behavior the plugin must not rely on.
        }
    }
}

final class Fake_Meta_Deletion_Order extends WC_Order
{
    /** @var array<int,object> Mirrors WC_Data's unfiltered pending metadata. */
    protected array $meta_data = array();

    public function arm_paymongo_meta_deletion(string $key): void
    {
        $this->meta_data = array((object) array('key' => $key, 'value' => null));
        unset($this->meta[$key]);
    }

    public function arm_status_transition(string $from, string $to): void
    {
        $this->status_transition = array('from' => $from, 'to' => $to, 'note' => '', 'manual' => false);
    }

    public function update_meta_data(string $key, $value): void
    {
        parent::update_meta_data($key, $value);
        foreach ($this->meta_data as $meta) {
            if ((string) ($meta->key ?? '') === $key) {
                $meta->value = $value;
                return;
            }
        }
        $this->meta_data[] = (object) array('key' => $key, 'value' => $value);
    }

    public function pending_meta_value(string $key)
    {
        foreach ($this->meta_data as $meta) {
            if ((string) ($meta->key ?? '') === $key) {
                return $meta->value;
            }
        }
        return null;
    }
}

final class Fake_WC_Session
{
    /** @var array<string,mixed> */
    public array $data = array();
    public string $customer_id = 'test-woo-session-123';
    public int $set_calls = 0;

    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, $value): void
    {
        ++$this->set_calls;
        $this->data[$key] = $value;
    }

    public function get_customer_id(): string
    {
        return $this->customer_id;
    }
}

final class Fake_WC_Container
{
    public Fake_WC_Session $session;

    public function __construct()
    {
        $this->session = new Fake_WC_Session();
    }
}

final class Fake_WC_Logger
{
    public function log($level, $message, $context = array()): void
    {
    }
}

$fake_orders = array();
$fake_options = array();
$fake_scheduled = array();
$fake_order_query_ids = array();
$fake_order_query_handler = null;
$fake_remote_handler = null;
$fake_before_order_save = null;
$fake_clone_order_reads = false;
$fake_persist_order_saves = false;
$fake_persist_order_filter = null;
$fake_hooks = array();
$fake_hook_calls = array();
$fake_option_add_failures = array();
$fake_option_update_swallow = array();
$fake_option_update_handler = null;
$fake_option_read_missing = array();
$fake_wc = new Fake_WC_Container();

if (!function_exists('__')) {
    function __($text, $domain = '') { return $text; }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = '') { return $text; }
}
if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()): void {
        throw new RuntimeException('wp_die:' . (string) $message);
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text): string { return trim(strip_tags((string) $text)); }
}
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;
        /** @var array<string,array<int,string>> */
        public array $errors = array();
        public function __construct(string $code = '', string $message = '') {
            $this->code = $code;
            $this->message = $message;
            if ($code !== '') {
                $this->errors[$code] = array($message);
            }
        }
        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
        public function add(string $code, string $message): void {
            $this->errors[$code][] = $message;
            if ($this->code === '') {
                $this->code = $code;
                $this->message = $message;
            }
        }
        public function has_errors(): bool { return $this->errors !== array(); }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($value): bool { return $value instanceof WP_Error; }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool {
        global $fake_hooks;
        $fake_hooks[(string) $hook][(int) $priority][] = array(
            'callback' => $callback,
            'accepted_args' => (int) $accepted_args,
        );
        return true;
    }
}
if (!function_exists('remove_action')) {
    function remove_action($hook, $callback, $priority = 10): bool {
        global $fake_hooks;
        $entries = $fake_hooks[(string) $hook][(int) $priority] ?? array();
        foreach ($entries as $index => $entry) {
            if (($entry['callback'] ?? null) === $callback) {
                unset($fake_hooks[(string) $hook][(int) $priority][$index]);
                return true;
            }
        }
        return false;
    }
}
if (!function_exists('do_action')) {
    function do_action($hook, ...$args): void {
        global $fake_hooks, $fake_hook_calls;
        $hook = (string) $hook;
        $fake_hook_calls[$hook] = (int) ($fake_hook_calls[$hook] ?? 0) + 1;
        $priorities = $fake_hooks[$hook] ?? array();
        ksort($priorities, SORT_NUMERIC);
        foreach ($priorities as $entries) {
            foreach ($entries as $entry) {
                call_user_func_array(
                    $entry['callback'],
                    array_slice($args, 0, max(0, (int) $entry['accepted_args']))
                );
            }
        }
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        global $fake_hooks;
        $priorities = $fake_hooks[(string) $hook] ?? array();
        ksort($priorities, SORT_NUMERIC);
        foreach ($priorities as $entries) {
            foreach ($entries as $entry) {
                $accepted = max(1, (int) $entry['accepted_args']);
                $value = call_user_func_array(
                    $entry['callback'],
                    array_slice(array_merge(array($value), $args), 0, $accepted)
                );
            }
        }
        return $value;
    }
}
if (!function_exists('absint')) {
    function absint($value): int { return abs((int) $value); }
}
if (!function_exists('wc_get_is_paid_statuses')) {
    function wc_get_is_paid_statuses(): array { return array('processing', 'completed'); }
}
if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id(): int { return 1; }
}
if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id) {
        global $fake_clone_order_reads, $fake_orders;
        $order = $fake_orders[(int) $order_id] ?? null;
        return $fake_clone_order_reads && $order instanceof WC_Order ? clone $order : $order;
    }
}
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        global $fake_options, $fake_option_read_missing;
        if (in_array((string) $key, $fake_option_read_missing, true)) {
            return $default;
        }
        return $fake_options[$key] ?? $default;
    }
}
if (!function_exists('add_option')) {
    function add_option($key, $value, $deprecated = '', $autoload = null): bool {
        global $fake_options, $fake_option_add_failures;
        if (in_array((string) $key, $fake_option_add_failures, true)) {
            return false;
        }
        if (array_key_exists($key, $fake_options)) {
            return false;
        }
        $fake_options[$key] = $value;
        return true;
    }
}
if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null): bool {
        global $fake_options, $fake_option_update_swallow, $fake_option_update_handler;
        if (is_callable($fake_option_update_handler)
            && $fake_option_update_handler((string) $key, $value) === 'swallow') {
            return true;
        }
        if (in_array((string) $key, $fake_option_update_swallow, true)) {
            return true;
        }
        $fake_options[$key] = $value;
        return true;
    }
}
if (!function_exists('delete_option')) {
    function delete_option($key): bool {
        global $fake_options;
        $exists = array_key_exists($key, $fake_options);
        unset($fake_options[$key]);
        return $exists;
    }
}
if (!function_exists('maybe_serialize')) {
    function maybe_serialize($value): string { return is_array($value) || is_object($value) ? serialize($value) : (string) $value; }
}
if (!function_exists('is_serialized')) {
    function is_serialized($data): bool {
        if (!is_string($data)) { return false; }
        if ($data === 'N;') { return true; }
        return preg_match('/^[aObisd]:/', $data) === 1 && @unserialize($data, array('allowed_classes' => false)) !== false;
    }
}
if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = array()) {
        global $fake_scheduled;
        $key = $hook . '|' . serialize($args);
        return $fake_scheduled[$key]['time'] ?? false;
    }
}
if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = array()): bool {
        global $fake_scheduled;
        $fake_scheduled[$hook . '|' . serialize($args)] = array('time' => (int) $timestamp, 'recurrence' => $recurrence);
        return true;
    }
}
if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = array()): bool {
        global $fake_scheduled;
        $fake_scheduled[$hook . '|' . serialize($args)] = array('time' => (int) $timestamp, 'recurrence' => false);
        return true;
    }
}
if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = array()): int {
        global $fake_scheduled;
        $key = $hook . '|' . serialize($args);
        $had = isset($fake_scheduled[$key]);
        unset($fake_scheduled[$key]);
        return $had ? 1 : 0;
    }
}
if (!function_exists('wc_get_orders')) {
    function wc_get_orders($args): array {
        global $fake_order_query_ids, $fake_order_query_handler;
        if (is_callable($fake_order_query_handler)) {
            return (array) $fake_order_query_handler($args);
        }
        $limit = max(1, (int) ($args['limit'] ?? 10));
        $page = max(1, (int) ($args['page'] ?? 1));
        return array_slice($fake_order_query_ids, ($page - 1) * $limit, $limit);
    }
}
if (!function_exists('WC')) {
    function WC() {
        global $fake_wc;
        return $fake_wc;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability): bool {
        global $fake_current_user_caps;
        return $fake_current_user_caps === null || in_array($capability, $fake_current_user_caps, true);
    }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int { return 77; }
}
if (!function_exists('home_url')) {
    function home_url($path = ''): string { return 'https://bactive.test' . '/' . ltrim((string) $path, '/'); }
}
if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth'): string { return 'deterministic-test-salt-' . $scheme; }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value = null, $url = ''): string {
        $args = is_array($key) ? $key : array((string) $key => $value);
        $target = is_array($key) ? (string) $value : (string) $url;
        return $target . (str_contains($target, '?') ? '&' : '?') . http_build_query($args);
    }
}
if (!function_exists('wp_http_validate_url')) {
    function wp_http_validate_url($url) { return filter_var($url, FILTER_VALIDATE_URL) ? $url : false; }
}
if (!function_exists('wc_add_notice')) {
    function wc_add_notice($message, $type = 'success'): void {}
}
if (!function_exists('wc_get_checkout_url')) {
    function wc_get_checkout_url(): string { return 'https://bactive.test/checkout'; }
}
if (!function_exists('wc_format_decimal')) {
    function wc_format_decimal($number, $dp = false): string { return number_format((float) $number, (int) $dp, '.', ''); }
}
if (!function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency(): string { return 'PHP'; }
}
if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = array()) {
        global $fake_remote_handler;
        return is_callable($fake_remote_handler)
            ? $fake_remote_handler((string) $url, (array) $args)
            : new WP_Error('no_fake_remote', 'No fake remote response configured.');
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response): int { return (int) ($response['response']['code'] ?? 0); }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response): string { return (string) ($response['body'] ?? ''); }
}
if (!function_exists('wc_get_logger')) {
    function wc_get_logger(): Fake_WC_Logger { return new Fake_WC_Logger(); }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
}

require_once dirname(__DIR__) . '/includes/class-order-lock.php';
require_once dirname(__DIR__) . '/includes/class-readiness.php';
require_once dirname(__DIR__) . '/includes/class-reconciler.php';
require_once dirname(__DIR__) . '/includes/class-gateway.php';
require_once dirname(__DIR__) . '/includes/class-webhook.php';

$expire_transport_url = '';
$expire_transport_args = array();
$fake_remote_handler = static function (string $url, array $args) use (&$expire_transport_url, &$expire_transport_args): array {
    $expire_transport_url = $url;
    $expire_transport_args = $args;
    return array(
        'response' => array('code' => 200),
        'body' => '{}',
    );
};
$expire_transport_client = new Api_Client('sk_test_expire_transport_123456789');
$expire_transport_result = $expire_transport_client->expire_checkout_session(
    'cs_transport_session_123',
    'bactive-expire-transport-123'
);
check(is_array($expire_transport_result), 'bodyless Checkout Session expiry response is accepted');
same(
    'https://api.paymongo.com/v1/checkout_sessions/cs_transport_session_123/expire',
    $expire_transport_url,
    'Checkout Session expiry targets the exact provider resource'
);
same('POST', $expire_transport_args['method'] ?? '', 'Checkout Session expiry uses POST');
check(!array_key_exists('body', $expire_transport_args), 'Checkout Session expiry sends no request body');
same(
    'bactive-expire-transport-123',
    $expire_transport_args['headers']['Idempotency-Key'] ?? '',
    'Checkout Session expiry retains its exact idempotency key'
);
$fake_remote_handler = null;

$lifecycle_test_gateway = new Gateway(false);
$apply_payment = new ReflectionMethod(Webhook::class, 'apply_payment');
$apply_payment->setAccessible(true);
$validated_payment = array(
    'payment_id' => 'pay_test_payment_123',
    'event_id' => 'evt_test_event_123',
    'session_id' => 'cs_test_session_123',
    'method' => 'qrph',
    'provider' => '',
    'amount' => 12345,
    'mode' => 'test',
);

// A signed event is never acknowledged unless its quarantine record survives
// an independent option read. In particular, an order-store miss must release
// the event claim for PayMongo retry when durable incident storage is down.
$quarantine_method = new ReflectionMethod(Webhook::class, 'quarantine');
$quarantine_method->setAccessible(true);
$claimed_quarantine_method = new ReflectionMethod(Webhook::class, 'finish_claimed_quarantine');
$claimed_quarantine_method->setAccessible(true);
$prepare_quarantine_method = new ReflectionMethod(Webhook::class, 'prepare_quarantine_record');
$prepare_quarantine_method->setAccessible(true);
$finish_quarantine_method = new ReflectionMethod(Webhook::class, 'finish_quarantine_record');
$finish_quarantine_method->setAccessible(true);
$global_event_id = 'evt_missing_order_123';
$global_quarantine_option = test_quarantine_option($global_event_id);
$global_event_claim_option = test_claim_option('event', $global_event_id);
$fake_options = array(
    $global_event_claim_option => array(
        'status' => 'processing',
        'claimed_at' => time(),
        'kind' => 'event',
        'identity' => $global_event_id,
        'mode' => 'test',
    ),
);
$fake_option_add_failures = array($global_quarantine_option);
$global_quarantine_failed = $claimed_quarantine_method->invoke(
    null,
    'order_not_found',
    $global_event_id,
    'cs_missing_order_123',
    0,
    '',
    false
);
same(false, $global_quarantine_failed, 'missing-order quarantine fails when its durable add fails');
check(!isset($fake_options[$global_event_claim_option]), 'missing-order quarantine failure releases the event claim for provider retry');
$fake_option_add_failures = array();

$readback_event_id = 'evt_quarantine_readback_123';
$readback_quarantine_option = test_quarantine_option($readback_event_id, 'local');
$fake_options = array();
$fake_option_read_missing = array($readback_quarantine_option);
same(
    false,
    $quarantine_method->invoke(null, 'order_not_found', $readback_event_id, 'cs_readback_123', 0, '', 'local'),
    'global quarantine fails when its independent readback is missing'
);
$fake_option_read_missing = array();

$update_event_id = 'evt_quarantine_update_123';
$update_quarantine_option = test_quarantine_option($update_event_id, 'local');
$fake_options = array();
$prepared_quarantine = $prepare_quarantine_method->invoke(
    null,
    'session_not_authorized',
    $update_event_id,
    'cs_update_123',
    42,
    '',
    'local'
);
check(!empty($prepared_quarantine['durable']), 'quarantine fixture persists before annotation');
$fake_option_update_swallow = array($update_quarantine_option);
same(false, $finish_quarantine_method->invoke(null, $prepared_quarantine), 'quarantine annotation fails on a swallowed option update');
$fake_option_update_swallow = array();
$fake_options = array();

// PayMongo's documented webhook example is abbreviated. A signed delivery
// that fails strict inline validation is recovered only from a fresh,
// authenticated Checkout Session GET; a transient GET remains retryable.
$recover_signed_event = new ReflectionMethod(Webhook::class, 'recover_invalid_signed_event');
$recover_signed_event->setAccessible(true);
$compact_event_id = 'evt_compact_webhook_123';
$compact_order = new WC_Order();
$compact_attempt = $compact_order->meta['_bactive_paymongo_attempts'][0];
$fake_orders = array(42 => clone $compact_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_compact_webhook_123456789'),
    ),
    test_claim_option('event', $compact_event_id) => array(
        'status' => 'processing',
        'claimed_at' => time(),
        'kind' => 'event',
        'identity' => $compact_event_id,
        'mode' => 'test',
    ),
);
$compact_remote_calls = 0;
$fake_remote_handler = static function (string $url, array $args) use (&$compact_remote_calls): array {
    ++$compact_remote_calls;
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => session())),
    );
};
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$compact_result = $recover_signed_event->invoke(
    null,
    $compact_order,
    $compact_attempt,
    $compact_event_id,
    'cs_test_session_123',
    false
);
same('processed', $compact_result, 'abbreviated signed webhook recovers from authenticated session readback');
same(1, $compact_remote_calls, 'abbreviated signed webhook performs one exact Checkout Session GET');
same(true, $fake_orders[42]->paid, 'authenticated compact-webhook recovery persists paid state');
same(
    'processed',
    $fake_options[test_claim_option('event', $compact_event_id)]['status'] ?? '',
    'authenticated compact-webhook recovery closes the original event claim'
);
$fake_before_order_save = null;
$fake_clone_order_reads = false;
$fake_persist_order_saves = false;

$retry_event_id = 'evt_compact_retry_123';
$retry_order = new WC_Order();
$retry_attempt = $retry_order->meta['_bactive_paymongo_attempts'][0];
$fake_orders = array(42 => $retry_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_compact_retry_123456789'),
    ),
    test_claim_option('event', $retry_event_id) => array(
        'status' => 'processing',
        'claimed_at' => time(),
        'kind' => 'event',
        'identity' => $retry_event_id,
        'mode' => 'test',
    ),
);
$fake_remote_handler = static fn(string $url, array $args): WP_Error => new WP_Error('transport', 'retry');
same(
    'retry',
    $recover_signed_event->invoke(null, $retry_order, $retry_attempt, $retry_event_id, 'cs_test_session_123', false),
    'abbreviated signed webhook remains retryable when authenticated retrieval fails'
);
check(
    !isset($fake_options[test_claim_option('event', $retry_event_id)]),
    'failed compact-webhook retrieval releases the original event claim'
);
same(false, $retry_order->paid, 'failed compact-webhook retrieval cannot mark the order paid');
$fake_remote_handler = null;
$fake_options = array();

$successful_order = new WC_Order();
$fake_orders[42] = clone $successful_order;
$fake_options = array();
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'successful webhook test acquires order fence');
try {
    $apply_payment->invoke(null, $successful_order, $validated_payment, false);
} finally {
    Order_Lock::release(42);
}
same(0, $successful_order->payment_complete_calls, 'verified webhook bypasses unsafe WC_Order::payment_complete save path');
same(true, $fake_orders[42]->paid, 'verified webhook leaves the persisted order paid');
same('processing', $fake_orders[42]->status, 'verified webhook persists the exact filtered paid status');
same('pay_test_payment_123', $fake_orders[42]->transaction_id, 'verified webhook persists exact transaction ID');
check($fake_orders[42]->date_paid instanceof DateTimeImmutable, 'verified webhook persists payment date');
same('', $fake_orders[42]->meta['_bactive_paymongo_settlement_pending'] ?? '', 'verified webhook closes the settlement marker');
same(1, $fake_hook_calls['woocommerce_pre_payment_complete'] ?? 0, 'verified webhook emits pre-payment hook only after paid readback');
same(1, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'verified webhook emits paid status hook exactly once');
same(1, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'verified webhook emits payment-complete hook exactly once');
same(
    'done',
    $fake_options[test_effects_option('payment', 'pay_test_payment_123')]['status'] ?? '',
    'verified webhook durably closes its at-most-once effects record'
);
same(
    'pay_test_payment_123',
    $fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['payment_id'] ?? '',
    'before-save lifecycle preserves the locked webhook payment ID in the database snapshot'
);
check(
    !empty($fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['paid_at']),
    'before-save lifecycle preserves the locked webhook paid timestamp in the database snapshot'
);
$fake_before_order_save = null;
$fake_clone_order_reads = false;
$fake_persist_order_saves = false;

// WC_Order::save() catches an Exception from its data store and then normally
// runs status_transition() anyway. A failure on the paid-state save must leave
// the durable settlement marker, emit no status/payment/stock effects, and
// keep the event retryable through an explicit global incident.
$swallowed_payment_order = new WC_Order();
$swallowed_payment_order->throw_on_save_attempt = 2;
$fake_orders[42] = clone $swallowed_payment_order;
$fake_options = array();
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$swallowed_payment_threw = false;
check(Order_Lock::acquire(42), 'swallowed paid-save test acquires order fence');
try {
    $apply_payment->invoke(null, $swallowed_payment_order, $validated_payment, false);
} catch (RuntimeException $error) {
    $swallowed_payment_threw = true;
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($swallowed_payment_threw, 'swallowed paid-state save throws for webhook retry');
same('pending', $fake_orders[42]->status, 'swallowed paid-state save leaves database order unpaid');
same('', $fake_orders[42]->transaction_id, 'swallowed paid-state save leaves no database transaction ID');
same(
    'pay_test_payment_123',
    $fake_orders[42]->meta['_bactive_paymongo_settlement_pending'] ?? '',
    'swallowed paid-state save retains durable settlement recovery marker'
);
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'swallowed paid-state save emits zero paid status hooks');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'swallowed paid-state save emits zero payment-complete hooks');
same(
    'payment_state_readback_failed',
    $fake_options['bactive_paymongo_disable_drain_error']['code'] ?? '',
    'swallowed paid-state save records an explicit fail-closed incident'
);

// A data store that persists a different transaction value also fails before
// any fulfillment effects, even though its save call itself returned.
$bad_readback_order = new WC_Order();
$fake_orders[42] = clone $bad_readback_order;
$fake_options = array();
$fake_hook_calls = array();
$bad_readback_save = 0;
$fake_persist_order_filter = static function (WC_Order $persisted) use (&$bad_readback_save): WC_Order {
    ++$bad_readback_save;
    if ($bad_readback_save === 2) {
        $persisted->transaction_id = 'pay_test_payment_other';
    }
    return $persisted;
};
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$bad_readback_threw = false;
check(Order_Lock::acquire(42), 'bad-readback webhook test acquires order fence');
try {
    $apply_payment->invoke(null, $bad_readback_order, $validated_payment, false);
} catch (RuntimeException $error) {
    $bad_readback_threw = true;
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_persist_order_filter = null;
}
check($bad_readback_threw, 'mismatched transaction readback throws for webhook retry');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'mismatched paid readback emits zero paid status hooks');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'mismatched paid readback emits zero payment-complete hooks');
same('payment_state_readback_failed', $fake_options['bactive_paymongo_disable_drain_error']['code'] ?? '', 'mismatched paid readback records exact incident');

$cod_order = new WC_Order();
$cod_order->payment_method = 'cod';
$cod_order->status = 'processing';
$cod_order->paid = true;
$cod_order->meta['_bactive_paymongo_attempts'][0]['expired_at'] = time();
$fake_orders[42] = $cod_order;
$fake_options = array();
check(Order_Lock::acquire(42), 'late-COD webhook test acquires order fence');
try {
    $apply_payment->invoke(null, $cod_order, $validated_payment, false);
} finally {
    Order_Lock::release(42);
}
same(0, $cod_order->payment_complete_calls, 'late paid event after COD switch never completes payment');
same('on-hold', $cod_order->status, 'late paid event after COD switch pauses fulfillment');
same('paid_after_payment_method_changed', $cod_order->meta['_bactive_paymongo_review_required'] ?? '', 'late paid event after COD switch is quarantined');
same('pay_test_payment_123', $cod_order->meta['_bactive_paymongo_unexpected_payment_id'] ?? '', 'late paid event retains reconciliation payment ID');

// First option writes (where WordPress passes false as the old value) must
// never persist raw API credentials.
$fake_orders = array();
$fake_order_query_ids = array();
$fake_options = array();
$direct_add_cases = array(
    'raw credential' => array('enabled' => 'yes', 'live_secret_key' => 'sk_live_direct_add_secret_123456'),
    'malformed value' => 'not-an-array',
    'apparently harmless config' => array('enabled' => 'no', 'test_mode' => 'yes'),
);
foreach ($direct_add_cases as $label => $direct_add_value) {
    $direct_add_threw = false;
    try {
        Gateway::guard_settings_add('woocommerce_bactive_paymongo_settings', $direct_add_value);
    } catch (Error $error) {
        $direct_add_threw = true;
    }
    check($direct_add_threw, 'unguarded direct settings add rejects ' . $label . ' before insert');
    check(!isset($fake_options['woocommerce_bactive_paymongo_settings']), 'unguarded direct settings add persists no ' . $label);
    same('settings_add_unguarded', $fake_options['bactive_paymongo_settings_write_rejected']['code'] ?? '', 'unguarded direct settings add records exact rejection for ' . $label);
}
$first_settings = Gateway::filter_settings_update(
    array(
        'enabled' => 'no',
        'test_mode' => 'yes',
        'test_secret_key' => 'sk_test_first_write_secret_123456',
        'live_secret_key' => '',
    ),
    false
);
check(str_starts_with((string) $first_settings['test_secret_key'], 'enc:v1:'), 'first settings write encrypts test secret');
same('sk_test_first_write_secret_123456', Secrets::decrypt((string) $first_settings['test_secret_key']), 'first-write ciphertext decrypts exactly');
$guarded_first_add_threw = false;
try {
    Gateway::guard_settings_add('woocommerce_bactive_paymongo_settings', $first_settings);
} catch (Error $error) {
    $guarded_first_add_threw = true;
}
check(!$guarded_first_add_threw, 'update_option first-write lane may insert its exact encrypted settings value');
$fake_options['woocommerce_bactive_paymongo_settings'] = $first_settings;
Gateway::after_settings_add('woocommerce_bactive_paymongo_settings', $first_settings);
check(!Order_Lock::settings_write_active(), 'fresh-install add hook releases the exact settings writer lease');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'fresh disabled settings save remains safely unavailable');

// A settings writer owns one database lease from pre-update drain through the
// exact stored-value and PayMongo readiness readback. A second writer is a
// no-op, and no Checkout Session POST can begin inside that boundary.
$settings_key = Secrets::encrypt('sk_test_settings_lease_123456789');
$old_settings = array(
    'enabled' => 'yes',
    'test_mode' => 'yes',
    'title' => 'Pay online securely',
    'description' => 'Old description',
    'test_secret_key' => $settings_key,
    'live_secret_key' => '',
);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => $old_settings,
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 4,
);
$settings_gateway_before_write = new Gateway(false);
$first_settings_write = $old_settings;
$first_settings_write['title'] = 'Pay with PayMongo';
$first_settings_write = Gateway::filter_settings_update($first_settings_write, $old_settings);
check(Order_Lock::settings_write_active(), 'first settings writer retains its database lease before commit');
same(true, Reconciler::is_draining(), 'settings lease drains checkout before the option commit');

$competing_settings_write = $old_settings;
$competing_settings_write['description'] = 'Competing description';
$competing_settings_write = Gateway::filter_settings_update($competing_settings_write, $old_settings);
same($old_settings, $competing_settings_write, 'concurrent settings writer is reduced to an exact no-op');
same($old_settings, $fake_options['woocommerce_bactive_paymongo_settings'], 'competing writer cannot alter the stored settings');

$settings_remote_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$settings_remote_calls): array {
    $settings_remote_calls[] = $url;
    if (str_ends_with($url, '/v1/merchants/capabilities/payment_methods')) {
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array('attributes' => array('payment_methods' => array('qrph'))))),
        );
    }
    if (str_contains($url, '/v1/webhooks?')) {
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array(array(
                'id' => 'hook_settings_lease_123',
                'type' => 'webhook',
                'attributes' => array(
                    'url' => Readiness::endpoint_url(false),
                    'status' => 'enabled',
                    'events' => array('checkout_session.payment.paid'),
                    'livemode' => false,
                    'secret_key' => 'whsk_settings_lease_123456789',
                ),
            )))),
        );
    }
    return new WP_Error('unexpected_settings_call', 'Unexpected settings-readiness request.');
};
$settings_submit_attempt = new ReflectionMethod(Gateway::class, 'submit_attempt');
$settings_submit_attempt->setAccessible(true);
$settings_lease_order = new WC_Order();
$settings_lease_attempt = $settings_lease_order->meta['_bactive_paymongo_attempts'][0];
$settings_lease_attempt['generation'] = 1;
$settings_lease_attempt['config_generation'] = 4;
$fake_orders = array(42 => clone $settings_lease_order);
check(Order_Lock::acquire(42), 'settings issuance-fence test acquires its order lock');
try {
    $settings_lease_result = $settings_submit_attempt->invoke(
        $settings_gateway_before_write,
        $settings_lease_order,
        12345,
        array($settings_lease_attempt),
        0,
        false
    );
} finally {
    Order_Lock::release(42);
}
same('fail', $settings_lease_result['result'] ?? '', 'settings lease blocks Checkout Session issuance before commit');
same(array(), $settings_remote_calls, 'settings lease permits zero provider calls before final settings readback');

$settings_commit_guard_threw = false;
try {
    Gateway::guard_settings_update_commit(
        'woocommerce_bactive_paymongo_settings',
        $old_settings,
        $first_settings_write
    );
} catch (Error $error) {
    $settings_commit_guard_threw = true;
}
check(!$settings_commit_guard_threw, 'exact normalized settings owner passes final pre-SQL guard');
$fake_options['woocommerce_bactive_paymongo_settings'] = $first_settings_write;
Gateway::after_settings_update($old_settings, $first_settings_write);
check(!Order_Lock::settings_write_active(), 'matching settings owner releases its lease after exact readback');
same(false, $fake_options['bactive_paymongo_settings_write_error'] ?? false, 'matching settings owner leaves no write error');
same('no', $fake_options['bactive_paymongo_draining'] ?? '', 'matching ready settings owner reopens checkout after exact readback');
same('Pay with PayMongo', $fake_options['woocommerce_bactive_paymongo_settings']['title'] ?? '', 'matching settings owner commits its intended value');
check(count($settings_remote_calls) === 2, 'matching settings owner performs bounded capability and webhook readback');

// A global filter runs after the option-specific normalization hook. If it
// substitutes any other final value, the generic pre-SQL action must preserve
// the old database credentials and release the mismatched request lease.
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => $old_settings,
    'bactive_paymongo_draining' => 'no',
);
$filtered_before_global_tamper = $old_settings;
$filtered_before_global_tamper['description'] = 'Intended description';
$filtered_before_global_tamper = Gateway::filter_settings_update($filtered_before_global_tamper, $old_settings);
$globally_tampered_settings = $filtered_before_global_tamper;
$globally_tampered_settings['test_secret_key'] = 'sk_test_raw_global_filter_secret_123456';
$global_filter_guard_threw = false;
try {
    Gateway::guard_settings_update_commit(
        'woocommerce_bactive_paymongo_settings',
        $old_settings,
        $globally_tampered_settings
    );
} catch (Error $error) {
    $global_filter_guard_threw = true;
}
check($global_filter_guard_threw, 'later global filter substitution is blocked before settings SQL');
same($old_settings, $fake_options['woocommerce_bactive_paymongo_settings'], 'later global filter cannot replace encrypted credentials');
same('settings_commit_mismatch', $fake_options['bactive_paymongo_settings_write_rejected']['code'] ?? '', 'later global filter substitution records exact rejection');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'later global filter substitution remains fail-closed');
check(!Order_Lock::settings_write_active(), 'later global filter mismatch releases its settings lease');

// A losing writer can arrive after the winner has verified readiness and set
// draining=no but before that winner releases its database lease. The lease is
// sufficient to block issuance; the loser must not strand the shared flag at
// yes because its no-op option write has no matching after-update hook.
$settings_gap_fingerprint = hash('sha256', 'settings-winner-final-release-gap');
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => $old_settings,
    'bactive_paymongo_draining' => 'no',
);
check(Order_Lock::acquire_settings($settings_gap_fingerprint), 'settings final-gap winner acquires database lease');
$settings_token_property = new ReflectionProperty(Order_Lock::class, 'settings_token');
$settings_token_property->setAccessible(true);
$settings_fingerprint_property = new ReflectionProperty(Order_Lock::class, 'settings_fingerprint');
$settings_fingerprint_property->setAccessible(true);
$settings_gap_winner_token = (string) $settings_token_property->getValue();
$settings_gap_winner_fingerprint = (string) $settings_fingerprint_property->getValue();
$settings_token_property->setValue(null, '');
$settings_fingerprint_property->setValue(null, '');
$settings_gap_loser = $old_settings;
$settings_gap_loser['description'] = 'Late losing writer';
$settings_gap_loser = Gateway::filter_settings_update($settings_gap_loser, $old_settings);
same($old_settings, $settings_gap_loser, 'late losing settings writer remains an exact no-op');
same('no', $fake_options['bactive_paymongo_draining'] ?? '', 'late losing settings writer cannot re-drain verified checkout');
check(Order_Lock::settings_write_active(), 'winner database lease still fences issuance during late loser');
$settings_token_property->setValue(null, $settings_gap_winner_token);
$settings_fingerprint_property->setValue(null, $settings_gap_winner_fingerprint);
Order_Lock::release_settings();
check(!Order_Lock::settings_write_active(), 'settings final-gap winner releases exact lease');
same('no', $fake_options['bactive_paymongo_draining'] ?? '', 'settings final-gap ends ready rather than stranded');

// WordPress routes a missing option through add_option(), which has a distinct
// dynamic action. The add-hook wrapper must complete readiness and reopen on a
// fresh enabled installation, rather than leaving the lease stuck until
// shutdown.
$fresh_enabled_settings = array(
    'enabled' => 'yes',
    'test_mode' => 'yes',
    'title' => 'Pay online securely',
    'description' => 'Choose a payment method on PayMongo.',
    'test_secret_key' => 'sk_test_fresh_install_123456789',
    'live_secret_key' => '',
);
$fake_options = array();
$settings_remote_calls = array();
$fresh_enabled_settings = Gateway::filter_settings_update($fresh_enabled_settings, false);
$fake_options['woocommerce_bactive_paymongo_settings'] = $fresh_enabled_settings;
Gateway::after_settings_add('woocommerce_bactive_paymongo_settings', $fresh_enabled_settings);
check(str_starts_with((string) ($fresh_enabled_settings['test_secret_key'] ?? ''), 'enc:v1:'), 'fresh enabled install encrypts its API key');
check(!Order_Lock::settings_write_active(), 'fresh enabled add hook releases its settings lease');
same(false, $fake_options['bactive_paymongo_settings_write_error'] ?? false, 'fresh enabled add hook leaves no write error');
same('no', $fake_options['bactive_paymongo_draining'] ?? '', 'fresh enabled add hook completes readiness and reopens checkout');
check(count($settings_remote_calls) === 2, 'fresh enabled add hook verifies capability and webhook state');
$fake_remote_handler = null;

// Malformed updates are exact no-ops, and deleting the settings option is
// pre-SQL fenced until every tracked order has been drained with the retained
// credentials.
$malformed_old_settings = $old_settings;
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => $malformed_old_settings,
    'bactive_paymongo_draining' => 'no',
);
$malformed_settings_result = Gateway::filter_settings_update('erase-the-option', $malformed_old_settings);
same($malformed_old_settings, $malformed_settings_result, 'malformed settings update returns the exact prior value');
same($malformed_old_settings, $fake_options['woocommerce_bactive_paymongo_settings'], 'malformed settings update retains encrypted credentials');
same('settings_shape_invalid', $fake_options['bactive_paymongo_settings_write_rejected']['code'] ?? '', 'malformed settings update records exact rejection');
same('no', $fake_options['bactive_paymongo_draining'] ?? '', 'malformed no-op update does not strand checkout draining');

$settings_delete_order = new WC_Order();
$settings_delete_order->meta['_bactive_paymongo_attempts'][0]['created_at'] = time();
$fake_orders = array(42 => $settings_delete_order);
$fake_order_query_ids = array(42);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => $malformed_old_settings,
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 10,
);
$fake_remote_handler = static function (): WP_Error {
    return new WP_Error('simulated_delete_drain_failure', 'Simulated provider failure.');
};
$blocked_delete_threw = false;
try {
    Gateway::guard_settings_delete('woocommerce_bactive_paymongo_settings');
} catch (Error $error) {
    $blocked_delete_threw = true;
}
check($blocked_delete_threw, 'settings deletion is blocked before SQL when tracked state cannot drain');
same($malformed_old_settings, $fake_options['woocommerce_bactive_paymongo_settings'], 'blocked settings deletion retains encrypted credentials');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'blocked settings deletion keeps checkout draining');
same(11, (int) ($fake_options[Reconciler::CONFIG_GENERATION_OPTION] ?? 0), 'blocked settings deletion invalidates in-flight issuance');
same('paymongo_active_sessions_remain', $fake_options['bactive_paymongo_disable_drain_error']['code'] ?? '', 'blocked settings deletion records exact drain failure');
check(!Order_Lock::settings_write_active(), 'blocked settings deletion releases its exact settings lease');
$fake_remote_handler = null;

$fake_orders = array();
$fake_order_query_ids = array();
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => $malformed_old_settings,
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 20,
);
$safe_delete_threw = false;
try {
    Gateway::guard_settings_delete('woocommerce_bactive_paymongo_settings');
} catch (Error $error) {
    $safe_delete_threw = true;
}
check(!$safe_delete_threw, 'empty source scan permits guarded settings deletion');
check(Order_Lock::settings_write_active(), 'guarded settings deletion retains its lease through SQL');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'guarded settings deletion closes checkout before SQL');
same(21, (int) ($fake_options[Reconciler::CONFIG_GENERATION_OPTION] ?? 0), 'guarded settings deletion invalidates in-flight issuance');
unset($fake_options['woocommerce_bactive_paymongo_settings']);
Gateway::after_settings_delete('woocommerce_bactive_paymongo_settings');
check(!Order_Lock::settings_write_active(), 'post-delete readback releases exact settings lease');
check(!isset($fake_options['woocommerce_bactive_paymongo_settings']), 'guarded settings deletion leaves no credential option after simulated SQL');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'completed settings deletion remains safely unavailable');

// An API request that was durably recorded but has no returned session ID is
// still an outstanding payment ambiguity until it is explicitly resolved.
$pending_request_order = new WC_Order();
$pending_request_order->meta['_bactive_paymongo_attempts'] = array(
    array(
        'generation' => 1,
        'session_id' => '',
        'mode' => 'test',
        'request_pending' => true,
        'request_started_at' => time(),
    ),
);
check(Gateway::has_outstanding_attempts($pending_request_order), 'blank-session provider request remains outstanding');
$pending_request_order->meta['_bactive_paymongo_attempts'][0]['request_rejected_at'] = time();
check(!Gateway::has_outstanding_attempts($pending_request_order), 'definitively rejected provider request is not outstanding');

// A recent ambiguous provider request remains pending across repeated recovery
// passes, then becomes manual review only after the documented 23-hour bound.
$young_ambiguous_order = new WC_Order();
$young_ambiguous_order->meta['_bactive_paymongo_attempts'] = array(
    array(
        'generation' => 1,
        'session_id' => '',
        'mode' => 'test',
        'request_pending' => true,
        'request_started_at' => time() - 300,
    ),
);
$fake_orders = array(42 => $young_ambiguous_order);
$fake_options = array();
$fake_scheduled = array();
check(Reconciler::reconcile_order(42), 'recent ambiguous request remains in automatic recovery');
same(true, (bool) ($young_ambiguous_order->meta['_bactive_paymongo_attempts'][0]['request_pending'] ?? false), 'recent ambiguous request stays pending');
same('', $young_ambiguous_order->meta[Reconciler::UNRESOLVED_META] ?? '', 'recent ambiguous request is not prematurely marked manual review');
$young_ambiguous_order->meta['_bactive_paymongo_attempts'][0]['request_started_at'] = time() - 82801;
check(Reconciler::reconcile_order(42), 'same ambiguous request reaches bounded manual review on a later pass');
same('checkout_creation_ambiguous', $young_ambiguous_order->meta[Reconciler::UNRESOLVED_META] ?? '', 'aged retry inherits and closes the ambiguity bound');

// A blank-session provider ambiguity cannot be converted to COD even when the
// recalculated COD amount and single fee are otherwise valid.
$cod_product = new class {
    public function get_total(): string { return '100.00'; }
};
$cod_fee = new class {
    public function get_name(): string { return 'COD Fee'; }
    public function get_total(): string { return '50.00'; }
};
$cod_pending_order = new WC_Order();
$cod_pending_order->payment_method = 'cod';
$cod_pending_order->items = array('line_item' => array($cod_product), 'fee' => array($cod_fee));
$cod_pending_order->meta['_bactive_paymongo_attempts'] = array(
    array(
        'generation' => 1,
        'session_id' => '',
        'mode' => 'test',
        'request_pending' => true,
        'request_started_at' => time(),
    ),
);
$fake_orders = array(42 => clone $cod_pending_order);
$fake_options = array();
$fake_scheduled = array();
$fake_remote_handler = null;
$cod_transition_blocked = false;
try {
    $lifecycle_test_gateway->handle_order_before_save($cod_pending_order, $cod_pending_order->get_data_store());
} catch (Error $error) {
    $cod_transition_blocked = true;
}
check($cod_transition_blocked, 'COD transition fails closed while a provider request is ambiguous');
check(Gateway::has_outstanding_attempts($cod_pending_order), 'blocked COD transition retains the provider ambiguity');
check(!Order_Lock::held_by_request(42), 'failed COD transition releases its order fence');

// Lifecycle fences acquired by the generic WooCommerce guard remain held
// through the real save/delete boundary, then release only on the matching
// after-action hook.
$lifecycle_order = new WC_Order();
$lifecycle_order->meta['_bactive_paymongo_attempts'][0]['expired_at'] = time();
$fake_orders = array(42 => clone $lifecycle_order);
$fake_options = array();
$lifecycle_test_gateway->handle_order_before_save($lifecycle_order, $lifecycle_order->get_data_store());
check(Order_Lock::held_by_request(42), 'before-save guard retains its fence through the data-store write');
$lifecycle_test_gateway->handle_order_before_save($lifecycle_order, $lifecycle_order->get_data_store());
$lifecycle_test_gateway->handle_order_after_save($lifecycle_order, $lifecycle_order->get_data_store());
check(Order_Lock::held_by_request(42), 'nested save cannot release the outer data-store fence');
$lifecycle_test_gateway->handle_order_after_save($lifecycle_order, $lifecycle_order->get_data_store());
check(!Order_Lock::held_by_request(42), 'after-save hook releases the retained data-store fence');

// WooCommerce catches Exception inside WC_Order::save() and would ordinarily
// emit a queued status transition even when the database write was blocked.
// The fence abort neutralizes that transition and escapes the catch boundary.
$busy_status_order = new Fake_Catching_Status_Order();
$busy_status_order->status = 'cancelled';
$busy_status_order->changes = array('status' => 'cancelled');
$busy_status_order->arm_status_transition('pending', 'cancelled');
$fake_orders = array(42 => clone $busy_status_order);
$fake_options = array(
    'bactive_paymongo_order_lock_42' => array(
        'token' => 'webhook-owns-order',
        'acquired_at' => time(),
    ),
);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$busy_status_order->save();
check($busy_status_order->save_error_caught, 'busy payment fence aborts the contending WooCommerce save');
same(0, $busy_status_order->status_transition_effects, 'blocked save emits no status transition side effect');
check(!Order_Lock::held_by_request(42), 'busy payment fence never grants request-local ownership');
$fake_before_order_save = null;

// An order object loaded before a paid webhook cannot overwrite the fresh
// processing order after it eventually acquires the same fence.
$paid_stored_order = new WC_Order();
$paid_stored_order->status = 'processing';
$paid_stored_order->paid = true;
$paid_stored_order->transaction_id = 'pay_stale_race_123';
$paid_stored_order->date_paid = new DateTimeImmutable('@1788559200');
$paid_stored_order->meta['_bactive_paymongo_attempts'][0]['payment_id'] = 'pay_stale_race_123';
$paid_stored_order->meta['_bactive_paymongo_attempts'][0]['paid_at'] = 1788559200;
$stale_after_webhook_order = new Fake_Catching_Status_Order();
$stale_after_webhook_order->status = 'cancelled';
$stale_after_webhook_order->paid = false;
$stale_after_webhook_order->changes = array('status' => 'cancelled');
$stale_after_webhook_order->arm_status_transition('pending', 'cancelled');
$fake_orders = array(42 => clone $paid_stored_order);
$fake_options = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$stale_after_webhook_order->save();
check($stale_after_webhook_order->save_error_caught, 'stale pre-webhook object cannot overwrite paid order state');
same(0, $stale_after_webhook_order->status_transition_effects, 'stale paid-order save emits no cancellation side effect');
same('processing', $fake_orders[42]->status, 'fresh paid status remains authoritative after stale save');
same('pay_stale_race_123', $fake_orders[42]->transaction_id, 'fresh paid transaction remains authoritative after stale save');
check(!Order_Lock::held_by_request(42), 'stale paid-order abort releases its acquired fence');
$fake_before_order_save = null;

// Core payment facts must enter the fresh stored-state fence even when the
// stale object still says BACS and no status/amount field changed.
$stale_bacs_payment_order = new Fake_Catching_Status_Order();
$stale_bacs_payment_order->payment_method = 'bacs';
$stale_bacs_payment_order->transaction_id = 'stale_admin_transaction';
$stale_bacs_payment_order->date_paid = new DateTimeImmutable('@1788559100');
$stale_bacs_payment_order->changes = array(
    'transaction_id' => 'stale_admin_transaction',
    'date_paid' => $stale_bacs_payment_order->date_paid,
);
$fake_orders = array(42 => clone $paid_stored_order);
$fake_options = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$stale_bacs_payment_order->save();
check($stale_bacs_payment_order->save_error_caught, 'BACS-labelled stale transaction/date save cannot bypass PayMongo fence');
same('pay_stale_race_123', $fake_orders[42]->transaction_id, 'BACS-labelled stale save preserves settled transaction');
check(!Order_Lock::held_by_request(42), 'blocked BACS-labelled stale save releases its fence');
$fake_before_order_save = null;

// WC_Data represents a pending metadata deletion as an internal null entry
// that get_meta_data() hides. The guard must still run and restore the fresh
// authoritative PayMongo metadata before the data-store write.
$stale_meta_delete_order = new Fake_Meta_Deletion_Order();
$stale_meta_delete_order->payment_method = 'bacs';
$stale_meta_delete_order->changes = array();
$stale_meta_delete_order->arm_paymongo_meta_deletion('_bactive_paymongo_attempts');
$fake_orders = array(42 => clone $paid_stored_order);
$fake_options = array();
$lifecycle_test_gateway->handle_order_before_save($stale_meta_delete_order, $stale_meta_delete_order->get_data_store());
same(
    'pay_stale_race_123',
    $stale_meta_delete_order->meta['_bactive_paymongo_attempts'][0]['payment_id'] ?? '',
    'stale PayMongo metadata deletion restores authoritative attempt history'
);
check(
    is_array($stale_meta_delete_order->pending_meta_value('_bactive_paymongo_attempts')),
    'pending WC_Data metadata tombstone is replaced before persistence'
);
$lifecycle_test_gateway->handle_order_after_save($stale_meta_delete_order, $stale_meta_delete_order->get_data_store());
check(!Order_Lock::held_by_request(42), 'repaired metadata-only save releases its retained fence');

$duplicate_paid_transition_order = new Fake_Catching_Status_Order();
$duplicate_paid_transition_order->status = 'processing';
$duplicate_paid_transition_order->paid = true;
$duplicate_paid_transition_order->transaction_id = $paid_stored_order->transaction_id;
$duplicate_paid_transition_order->date_paid = $paid_stored_order->date_paid;
$duplicate_paid_transition_order->changes = array('status' => 'processing');
$duplicate_paid_transition_order->arm_status_transition('pending', 'processing');
$fake_orders = array(42 => clone $paid_stored_order);
$fake_options = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$duplicate_paid_transition_order->save();
check($duplicate_paid_transition_order->save_error_caught, 'stale transition matching webhook target is still blocked');
same(0, $duplicate_paid_transition_order->status_transition_effects, 'matching stale target cannot replay paid status side effects');
check(!Order_Lock::held_by_request(42), 'matching stale transition abort releases its acquired fence');
$fake_before_order_save = null;

// A fresh paid object can still advance normal fulfillment from processing to
// completed when all protected payment facts agree with stored state.
$fresh_fulfillment_order = new Fake_Catching_Status_Order();
$fresh_fulfillment_order->status = 'completed';
$fresh_fulfillment_order->paid = true;
$fresh_fulfillment_order->transaction_id = $paid_stored_order->transaction_id;
$fresh_fulfillment_order->date_paid = $paid_stored_order->date_paid;
$fresh_fulfillment_order->meta = $paid_stored_order->meta;
$fresh_fulfillment_order->changes = array('status' => 'completed');
$fresh_fulfillment_order->arm_status_transition('processing', 'completed');
$fake_orders = array(42 => clone $paid_stored_order);
$fake_options = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fresh_fulfillment_order->save();
check(!$fresh_fulfillment_order->save_error_caught, 'fresh paid order may advance normal fulfillment');
same(1, $fresh_fulfillment_order->status_transition_effects, 'verified fulfillment save retains its status transition');
same(
    'pay_stale_race_123',
    $fresh_fulfillment_order->meta['_bactive_paymongo_attempts'][0]['payment_id'] ?? '',
    'verified fulfillment save preserves authoritative PayMongo metadata'
);
$lifecycle_test_gateway->handle_order_after_save($fresh_fulfillment_order, $fresh_fulfillment_order->get_data_store());
check(!Order_Lock::held_by_request(42), 'verified fulfillment save releases its retained fence');
$fake_before_order_save = null;

$empty_existing_order = new WC_Order();
$empty_existing_order->meta['_bactive_paymongo_attempts'] = array();

// Two first-time classic-checkout POSTs for one Woo session are serialized
// before WC_Checkout::update_session() can dirty the shared row. Same-request
// hook reentry is harmless; a different database token fails before any
// session write, including a forged/unavailable payment method submission.
$fake_options = array();
$checkout_errors = new WP_Error();
$lifecycle_test_gateway->acquire_checkout_submission_lock();
$lifecycle_test_gateway->guard_checkout_submission(
    array('payment_method' => 'bactive_paymongo'),
    $checkout_errors
);
check(!$checkout_errors->has_errors(), 'first PayMongo checkout acquires the Woo-session fence');
check(Order_Lock::checkout_held_by_request(), 'checkout fence is retained across the current request');
$lifecycle_test_gateway->acquire_checkout_submission_lock();
$lifecycle_test_gateway->guard_checkout_submission(
    array('payment_method' => 'bactive_paymongo'),
    $checkout_errors
);
check(!$checkout_errors->has_errors(), 'same-request checkout validation is reentrant');
$lifecycle_test_gateway->release_checkout_submission_lock();
check(!Order_Lock::checkout_held_by_request(), 'checkout fence releases its exact request token');
$checkout_option = 'bactive_paymongo_checkout_lock_' . hash_hmac(
    'sha256',
    '1|' . $fake_wc->session->get_customer_id(),
    wp_salt('auth')
);
$fake_options[$checkout_option] = array('token' => 'cod-checkout-request', 'acquired_at' => time());
$fake_wc->session->data['order_awaiting_payment'] = 77;
$session_writes_before_loss = $fake_wc->session->set_calls;
$paymongo_contention_blocked = false;
try {
    $lifecycle_test_gateway->acquire_checkout_submission_lock();
} catch (Exception $error) {
    $paymongo_contention_blocked = true;
}
check($paymongo_contention_blocked, 'PayMongo submit is rejected before session mutation while same-session COD owns checkout');
same($session_writes_before_loss, $fake_wc->session->set_calls, 'losing PayMongo request performs zero Woo session writes');
same(77, $fake_wc->session->data['order_awaiting_payment'], 'losing PayMongo request cannot erase the winner order pointer');
check(!Order_Lock::checkout_held_by_request(), 'contended checkout never gains request-local ownership');
$fake_options[$checkout_option] = array('token' => 'paymongo-checkout-request', 'acquired_at' => time());
$cod_contention_blocked = false;
try {
    $lifecycle_test_gateway->acquire_checkout_submission_lock();
} catch (Exception $error) {
    $cod_contention_blocked = true;
}
check($cod_contention_blocked, 'COD submit is rejected before session mutation while same-session PayMongo owns checkout');
same($session_writes_before_loss, $fake_wc->session->set_calls, 'losing COD request performs zero Woo session writes');
same(77, $fake_wc->session->data['order_awaiting_payment'], 'losing COD request cannot erase the winner order pointer');
check(!Order_Lock::checkout_held_by_request(), 'contended COD checkout never gains request-local ownership');
$fake_options[$checkout_option] = array('token' => 'valid-checkout-request', 'acquired_at' => time());
$_POST['payment_method'] = 'cheque';
$forged_contention_blocked = false;
try {
    $lifecycle_test_gateway->acquire_checkout_submission_lock();
} catch (Exception $error) {
    $forged_contention_blocked = true;
}
unset($_POST['payment_method']);
check($forged_contention_blocked, 'forged unavailable method is rejected before Woo can dirty the shared session');
same($session_writes_before_loss, $fake_wc->session->set_calls, 'losing forged-method request performs zero Woo session writes');
same(77, $fake_wc->session->data['order_awaiting_payment'], 'losing forged-method request cannot overwrite the winner order pointer');
unset($fake_options[$checkout_option]);
$fake_wc->session->data = array();

$snapshot_line_item = new class {
    public function get_product_id(): int { return 501; }
    public function get_variation_id(): int { return 0; }
    public function get_quantity(): int { return 1; }
    public function get_subtotal(): string { return '123.45'; }
    public function get_total(): string { return '123.45'; }
    public function get_subtotal_tax(): string { return '0.00'; }
    public function get_total_tax(): string { return '0.00'; }
    public function get_taxes(): array { return array('subtotal' => array(), 'total' => array()); }
};

// First orders have ID 0 in woocommerce_checkout_create_order. Preserve a
// semantic cart/order snapshot by object identity, then acquire the new order
// fence and prove every item class persisted before process_payment can run.
$new_checkout_order = new WC_Order();
$new_checkout_order->id = 0;
$new_checkout_order->items['line_item'] = array($snapshot_line_item);
$fake_options = array();
$new_checkout_errors = new WP_Error();
$lifecycle_test_gateway->acquire_checkout_submission_lock();
$lifecycle_test_gateway->guard_checkout_submission(
    array('payment_method' => 'bactive_paymongo'),
    $new_checkout_errors
);
$lifecycle_test_gateway->handle_checkout_create_order($new_checkout_order, array());
$new_checkout_order->id = 77;
$fake_orders = array(77 => clone $new_checkout_order);
$lifecycle_test_gateway->finalize_checkout_lock($new_checkout_order);
check(Order_Lock::held_by_request(77), 'new-order final readback acquires and retains the assigned order fence');
$lifecycle_test_gateway->release_checkout_lock($new_checkout_order);
$lifecycle_test_gateway->release_checkout_submission_lock();
check(!Order_Lock::held_by_request(77), 'new-order success cleanup releases its order fence');

$missing_item_checkout = new WC_Order();
$missing_item_checkout->id = 0;
$missing_item_checkout->items['line_item'] = array($snapshot_line_item);
$fake_options = array();
$missing_item_errors = new WP_Error();
$lifecycle_test_gateway->acquire_checkout_submission_lock();
$lifecycle_test_gateway->guard_checkout_submission(
    array('payment_method' => 'bactive_paymongo'),
    $missing_item_errors
);
$lifecycle_test_gateway->handle_checkout_create_order($missing_item_checkout, array());
$missing_item_checkout->id = 78;
$persisted_missing_item = clone $missing_item_checkout;
$persisted_missing_item->items['line_item'] = array();
$fake_orders = array(78 => $persisted_missing_item);
$missing_item_blocked = false;
try {
    $lifecycle_test_gateway->finalize_checkout_lock($missing_item_checkout);
} catch (Exception $error) {
    $missing_item_blocked = true;
}
check($missing_item_blocked, 'new-order readback blocks a swallowed line-item persistence failure');
check(Order_Lock::held_by_request(78), 'failed new-order snapshot retains its fence until exception cleanup');
$lifecycle_test_gateway->release_checkout_lock($missing_item_checkout);
$lifecycle_test_gateway->release_checkout_submission_lock();
check(!Order_Lock::held_by_request(78), 'failed new-order snapshot cleanup releases exact fences');

// A nonzero WooCommerce awaiting-payment pointer is itself a payment safety
// boundary. A transient object-store miss must not be interpreted as proof
// that the prior provider session is gone.
$fake_wc->session->data['order_awaiting_payment'] = 99;
$fake_orders = array();
$fake_options = array();
$missing_prior_blocked = false;
try {
    $lifecycle_test_gateway->handle_checkout_create_order($empty_existing_order, array());
} catch (Exception $error) {
    $missing_prior_blocked = true;
}
check($missing_prior_blocked, 'unreadable prior awaiting-payment order blocks a second checkout');
check(!Order_Lock::held_by_request(99), 'unreadable prior-order guard releases its acquired fence');
$fake_wc->session->data = array();

$fake_orders = array(42 => clone $empty_existing_order);
$fake_options = array(
    'bactive_paymongo_order_lock_42' => array(
        'token' => 'different-checkout-request',
        'acquired_at' => time(),
    ),
);
$checkout_contention_blocked = false;
try {
    $lifecycle_test_gateway->handle_checkout_create_order($empty_existing_order, array());
} catch (Exception $error) {
    $checkout_contention_blocked = true;
}
check($checkout_contention_blocked, 'existing order with no prior attempt locks before its first state read');
check(!Order_Lock::held_by_request(42), 'contended existing-order checkout never claims request ownership');

$fake_options = array();
$lifecycle_test_gateway->handle_checkout_create_order($empty_existing_order, array());
check(Order_Lock::held_by_request(42), 'empty existing-order checkout retains its fence through order creation');
$lifecycle_test_gateway->finalize_checkout_lock($empty_existing_order);
check(Order_Lock::held_by_request(42), 'verified checkout readback retains its order fence through payment dispatch');
$lifecycle_test_gateway->release_checkout_lock($empty_existing_order);
check(!Order_Lock::held_by_request(42), 'explicit checkout cleanup releases the verified order fence');

$fake_orders = array(42 => clone $empty_existing_order);
$fake_options = array();
$lifecycle_test_gateway->handle_checkout_create_order($empty_existing_order, array());
$fake_orders[42]->payment_method = 'cod';
$checkout_readback_blocked = false;
try {
    $lifecycle_test_gateway->finalize_checkout_lock($empty_existing_order);
} catch (Exception $error) {
    $checkout_readback_blocked = true;
}
check($checkout_readback_blocked, 'checkout-created readback blocks a swallowed or mismatched order save');
check(Order_Lock::held_by_request(42), 'failed checkout readback retains its fence until exception cleanup');
$lifecycle_test_gateway->release_checkout_lock($empty_existing_order);
check(!Order_Lock::held_by_request(42), 'checkout exception cleanup releases the mismatched-order fence');

// A late checkout hook cannot mark the order paid/non-payable under the
// checkout-owned fence and then let Woo emit the queued status side effects.
$mutated_checkout_order = new Fake_Catching_Status_Order();
$mutated_checkout_order->meta['_bactive_paymongo_attempts'] = array();
$fake_orders = array(42 => clone $mutated_checkout_order);
$fake_options = array();
$lifecycle_test_gateway->handle_checkout_create_order($mutated_checkout_order, array());
$mutated_checkout_order->status = 'processing';
$mutated_checkout_order->paid = true;
$mutated_checkout_order->changes = array('status' => 'processing');
$mutated_checkout_order->arm_status_transition('pending', 'processing');
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$mutated_checkout_order->save();
check($mutated_checkout_order->save_error_caught, 'checkout-owned save rejects a late paid-status mutation');
same(0, $mutated_checkout_order->status_transition_effects, 'rejected checkout mutation emits no paid status side effect');
check(Order_Lock::held_by_request(42), 'rejected checkout save retains its fence through exception cleanup');
$lifecycle_test_gateway->release_checkout_lock($mutated_checkout_order);
check(!Order_Lock::held_by_request(42), 'checkout mutation cleanup releases the retained fence');
$fake_before_order_save = null;

// Final readback independently requires the persisted order to remain unpaid
// and payable, even if an unexpected data-store path bypassed the last hook.
$final_paid_readback_order = new WC_Order();
$final_paid_readback_order->meta['_bactive_paymongo_attempts'] = array();
$fake_orders = array(42 => clone $final_paid_readback_order);
$fake_options = array();
$lifecycle_test_gateway->handle_checkout_create_order($final_paid_readback_order, array());
$fake_orders[42]->status = 'processing';
$fake_orders[42]->paid = true;
$final_paid_readback_blocked = false;
try {
    $lifecycle_test_gateway->finalize_checkout_lock($final_paid_readback_order);
} catch (Exception $error) {
    $final_paid_readback_blocked = true;
}
check($final_paid_readback_blocked, 'checkout final readback rejects a paid or non-payable stored order');
$lifecycle_test_gateway->release_checkout_lock($final_paid_readback_order);
check(!Order_Lock::held_by_request(42), 'failed paid-state readback releases during exception cleanup');

$fake_orders = array(42 => clone $lifecycle_order);
$fake_options = array();
same(null, $lifecycle_test_gateway->guard_order_deletion(null, $lifecycle_order, false), 'verified terminal PayMongo order may enter deletion');
$lifecycle_test_gateway->block_unsafe_delete_action(42, $lifecycle_order);
check(Order_Lock::held_by_request(42), 'delete guard retains its fence through the destructive data-store action');
$lifecycle_test_gateway->release_delete_lock(42);
check(!Order_Lock::held_by_request(42), 'post-delete hook releases the retained deletion fence');

// WooCommerce invokes woocommerce_create_refund before saving the refund
// child, refunding a gateway, restoring stock, or changing parent status. Both
// partial and full PayMongo refund records stop at that pre-side-effect gate.
$fake_orders = array(42 => clone $paid_stored_order);
foreach (array('partial' => '50.00', 'full' => '123.45') as $refund_kind => $refund_amount) {
    $refund_side_effects = 0;
    $refund_blocked = false;
    try {
        $lifecycle_test_gateway->guard_refund_creation(
            (object) array('kind' => $refund_kind),
            array('order_id' => 42, 'amount' => $refund_amount, 'refund_payment' => false)
        );
        ++$refund_side_effects;
    } catch (Exception $error) {
        $refund_blocked = true;
    }
    check($refund_blocked, $refund_kind . ' PayMongo WooCommerce refund is rejected before creation');
    same(0, $refund_side_effects, $refund_kind . ' refund rejection has no child/payment/stock/status side effect');
}
$non_paymongo_refund_order = new WC_Order();
$non_paymongo_refund_order->payment_method = 'cod';
$non_paymongo_refund_order->meta['_bactive_paymongo_attempts'] = array();
$fake_orders = array(42 => $non_paymongo_refund_order);
$non_paymongo_refund_allowed = true;
try {
    $lifecycle_test_gateway->guard_refund_creation(
        (object) array('kind' => 'cod'),
        array('order_id' => 42, 'amount' => '10.00', 'refund_payment' => false)
    );
} catch (Exception $error) {
    $non_paymongo_refund_allowed = false;
}
check($non_paymongo_refund_allowed, 'refund guard does not alter non-PayMongo order behavior');

// A replayed cancel URL for an already-expired generation must be a no-op for
// every newer attempt on the same order.
$cancel_replay_order = new WC_Order();
$cancel_replay_started_at = time() - 120;
$cancel_replay_attempts = array(
    array(
        'generation' => 1,
        'fingerprint' => hash('sha256', '42|12345|PHP|test|old'),
        'session_id' => 'cs_old_expired_123',
        'mode' => 'test',
        'reference' => 'BA-42-1',
        'correlation_id' => 'cancel-old-correlation-123',
        'idempotency_key' => 'bactive-checkout-test-42-old',
        'created_at' => $cancel_replay_started_at - 60,
        'config_generation' => 1,
        'request_started_at' => $cancel_replay_started_at - 60,
        'expired_at' => time() - 60,
    ),
    array(
        'generation' => 2,
        'fingerprint' => hash('sha256', '42|12345|PHP|test|new'),
        'session_id' => 'cs_new_active_456',
        'mode' => 'test',
        'reference' => 'BA-42-2',
        'correlation_id' => 'cancel-new-correlation-456',
        'idempotency_key' => 'bactive-checkout-test-42-new',
        'created_at' => $cancel_replay_started_at,
        'config_generation' => 1,
        'request_started_at' => $cancel_replay_started_at,
    ),
);
$cancel_replay_order->meta['_bactive_paymongo_attempts'] = $cancel_replay_attempts;
$fake_orders = array(42 => $cancel_replay_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_cancel_replay_secret_123456'),
    ),
);
$cancel_replay_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$cancel_replay_calls) {
    $cancel_replay_calls[] = $url;
    return new WP_Error('unexpected_cancel_call', 'A newer Checkout Session must not be touched.');
};
$cancel_gateway = new Gateway(false);
$expire_attempts = new ReflectionMethod(Gateway::class, 'expire_outstanding_attempts');
$expire_attempts->setAccessible(true);
$attempt_fingerprint = new ReflectionMethod(Gateway::class, 'attempt_request_fingerprint');
$attempt_fingerprint->setAccessible(true);
$old_cancel_fingerprint = $attempt_fingerprint->invoke(null, $cancel_replay_attempts[0]);
check(Order_Lock::acquire(42), 'cancel replay regression acquires order fence');
try {
    $cancel_args = array($cancel_replay_order, &$cancel_replay_attempts, true, $old_cancel_fingerprint);
    $cancel_result = $expire_attempts->invokeArgs($cancel_gateway, $cancel_args);
} finally {
    Order_Lock::release(42);
}
same(true, $cancel_result['verified'] ?? false, 'terminal cancel generation is already safely closed');
same(array(), $cancel_replay_calls, 'stale cancel generation makes zero provider call for newer session');
check(empty($cancel_replay_attempts[1]['expired_at']), 'stale cancel generation leaves newer attempt active');

// The same generation/reference/correlation may exist in both namespaces only
// after corruption or a legacy copy. A signed cancel identity must still select
// exactly one mode and must never expire the other provider session.
$cancel_mode_started_at = time() - 30;
$cancel_test_attempt = array(
    'generation' => 7,
    'fingerprint' => hash('sha256', 'shared-corrupt-cart-fingerprint'),
    'session_id' => 'cs_cancel_test_exact_123',
    'mode' => 'test',
    'reference' => 'BA-42-7',
    'correlation_id' => 'shared-cancel-correlation-123',
    'idempotency_key' => 'shared-cancel-idempotency-123',
    'created_at' => $cancel_mode_started_at,
    'config_generation' => 9,
    'request_started_at' => $cancel_mode_started_at,
);
$cancel_live_attempt = $cancel_test_attempt;
$cancel_live_attempt['mode'] = 'live';
$cancel_live_attempt['session_id'] = 'cs_cancel_live_exact_456';
$cancel_mode_order = new WC_Order();
$cancel_mode_attempts = array($cancel_live_attempt, $cancel_test_attempt);
$cancel_mode_order->meta['_bactive_paymongo_attempts'] = $cancel_mode_attempts;
$fake_orders = array(42 => $cancel_mode_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_cancel_mode_exact_123456'),
        'live_secret_key' => Secrets::encrypt('sk_live_cancel_mode_exact_123456'),
    ),
);
$cancel_mode_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$cancel_mode_calls): array {
    $cancel_mode_calls[] = $url;
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => array(
            'id' => 'cs_cancel_test_exact_123',
            'type' => 'checkout_session',
            'attributes' => array('status' => 'expired', 'livemode' => false, 'payments' => array()),
        ))),
    );
};
$test_cancel_fingerprint = $attempt_fingerprint->invoke(null, $cancel_test_attempt);
$live_cancel_fingerprint = $attempt_fingerprint->invoke(null, $cancel_live_attempt);
check($test_cancel_fingerprint !== $live_cancel_fingerprint, 'cancel attempt fingerprint binds provider mode');
$checkout_payload = new ReflectionMethod(Gateway::class, 'checkout_payload');
$checkout_payload->setAccessible(true);
$cancel_payload = $checkout_payload->invoke($cancel_gateway, $cancel_mode_order, 12345, $cancel_test_attempt);
$cancel_query = array();
parse_str(
    (string) parse_url((string) ($cancel_payload['data']['attributes']['cancel_url'] ?? ''), PHP_URL_QUERY),
    $cancel_query
);
same(
    $test_cancel_fingerprint,
    $cancel_query['attempt'] ?? '',
    'provider cancel URL carries the exact mode-bound request fingerprint'
);
$attempt_by_fingerprint = new ReflectionMethod(Gateway::class, 'attempt_by_fingerprint');
$attempt_by_fingerprint->setAccessible(true);
same(
    $cancel_test_attempt,
    $attempt_by_fingerprint->invoke($cancel_gateway, $cancel_mode_order, $test_cancel_fingerprint),
    'cancel lookup selects the exact sandbox attempt despite a colliding generation'
);
$cancel_token = new ReflectionMethod(Gateway::class, 'cancel_token');
$cancel_token->setAccessible(true);
check(
    $cancel_token->invoke($cancel_gateway, 42, $cancel_test_attempt)
        !== $cancel_token->invoke($cancel_gateway, 42, $cancel_live_attempt),
    'cancel authorization token binds provider mode through the exact attempt fingerprint'
);
check(Order_Lock::acquire(42), 'mode-bound cancel regression acquires order fence');
try {
    $cancel_mode_args = array($cancel_mode_order, &$cancel_mode_attempts, true, $test_cancel_fingerprint);
    $cancel_mode_result = $expire_attempts->invokeArgs($cancel_gateway, $cancel_mode_args);
} finally {
    Order_Lock::release(42);
}
same(true, $cancel_mode_result['verified'] ?? false, 'exact sandbox cancel is independently verified');
same(2, count($cancel_mode_calls), 'exact sandbox cancel performs one expire and one readback');
check(
    count(array_filter(
        $cancel_mode_calls,
        static fn(string $url): bool => str_contains($url, 'cs_cancel_test_exact_123')
    )) === 2,
    'exact sandbox cancel calls only the selected session'
);
check(!empty($cancel_mode_attempts[1]['expired_at']), 'exact sandbox cancel marks only its selected attempt expired');
check(empty($cancel_mode_attempts[0]['expired_at']), 'exact sandbox cancel leaves colliding live generation untouched');

// An exact duplicate is ambiguous even though both records hash identically.
// Fail before the provider mutation rather than choosing the first array row.
$duplicate_cancel_order = new WC_Order();
$duplicate_cancel_attempts = array($cancel_test_attempt, $cancel_test_attempt);
$duplicate_cancel_order->meta['_bactive_paymongo_attempts'] = $duplicate_cancel_attempts;
$fake_orders = array(42 => $duplicate_cancel_order);
$duplicate_cancel_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$duplicate_cancel_calls): WP_Error {
    $duplicate_cancel_calls[] = $url;
    return new WP_Error('unexpected_duplicate_cancel_call', 'An ambiguous cancel must not reach PayMongo.');
};
same(
    null,
    $attempt_by_fingerprint->invoke($cancel_gateway, $duplicate_cancel_order, $test_cancel_fingerprint),
    'duplicate exact cancel identity is never selected by array order'
);
check(Order_Lock::acquire(42), 'duplicate cancel regression acquires order fence');
try {
    $duplicate_cancel_args = array(
        $duplicate_cancel_order,
        &$duplicate_cancel_attempts,
        true,
        $test_cancel_fingerprint,
    );
    $duplicate_cancel_result = $expire_attempts->invokeArgs($cancel_gateway, $duplicate_cancel_args);
} finally {
    Order_Lock::release(42);
}
same(false, $duplicate_cancel_result['verified'] ?? true, 'duplicate exact cancel identity fails closed');
same(array(), $duplicate_cancel_calls, 'duplicate exact cancel identity makes zero provider calls');
same(
    array($cancel_test_attempt, $cancel_test_attempt),
    $duplicate_cancel_attempts,
    'duplicate exact cancel identity leaves the attempt ledger byte-identical'
);
$fake_remote_handler = null;

// A same-second CAS renewal is ownership validation, not a false lock loss.
$fake_options = array();
check(Order_Lock::acquire(42), 'order fence acquired');
check(Order_Lock::renew(42), 'same-second order fence renewal succeeds');
check(Order_Lock::held_by_request(42), 'same-second renewal retains request ownership');
Order_Lock::release(42);
check(!array_key_exists('bactive_paymongo_order_lock_42', $fake_options), 'order fence releases exact owned token');

// A lost fence invalidates request-local ownership and prevents a stale
// expiry result from being saved by its caller.
$lost_lock_order = new WC_Order();
$fake_orders = array(42 => $lost_lock_order);
$fake_options = array();
check(Order_Lock::acquire(42), 'lock-loss regression acquires initial fence');
$fake_options['bactive_paymongo_order_lock_42'] = array('token' => 'different-owner-token', 'acquired_at' => time());
check(!Order_Lock::renew(42), 'different database fence rejects renewal');
check(!Order_Lock::held_by_request(42), 'failed renewal clears stale request token');
unset($fake_options['bactive_paymongo_order_lock_42']);

// The rotating source scan must only enqueue work. Calling it with a payable
// session succeeds without any configured HTTP handler, proving it performs
// no synchronous provider GET and cannot starve later pages on network time.
$scan_order = new WC_Order();
$fake_orders = array(42 => $scan_order);
$fake_order_query_ids = array(42);
$fake_options = array(Reconciler::CONFIG_GENERATION_OPTION => 0);
$fake_scheduled = array();
$fake_remote_handler = null;
Reconciler::run();
check(
    wp_next_scheduled(Reconciler::ORDER_HOOK, array(42)) !== false,
    'rotating recovery scan enqueues per-order reconciliation only'
);

$fake_orders = array();
$fake_order_query_ids = range(1, 51);
$fake_options = array(Reconciler::CONFIG_GENERATION_OPTION => 0);
$fake_scheduled = array();
Reconciler::run();
same(2, (int) ($fake_options['bactive_paymongo_reconcile_scan_page'] ?? 0), 'full first recovery page advances the durable cursor');
Reconciler::run();
check(
    wp_next_scheduled(Reconciler::ORDER_HOOK, array(51)) !== false,
    'rotating recovery reaches the fifty-first tracked order on the next pass'
);

// Controlled drain must consume a shrinking source set from page 1. Offset
// pagination would skip order 101 after orders 1..100 remove their markers.
$fake_orders = array();
for ($drain_id = 1; $drain_id <= 101; ++$drain_id) {
    $drain_order = new WC_Order();
    $drain_order->id = $drain_id;
    $drain_order->meta['_bactive_paymongo_attempts'][0]['expired_at'] = time();
    $drain_order->meta[Reconciler::REQUIRED_META] = 'yes';
    $fake_orders[$drain_id] = $drain_order;
}
$drain_query_pages = array();
$fake_order_query_handler = static function (array $args) use (&$fake_orders, &$drain_query_pages): array {
    $page = max(1, (int) ($args['page'] ?? 1));
    $limit = max(1, (int) ($args['limit'] ?? 10));
    $drain_query_pages[] = $page;
    $ids = array();
    foreach ($fake_orders as $order_id => $candidate) {
        if ($candidate instanceof WC_Order && $candidate->meta_exists(Reconciler::REQUIRED_META)) {
            $ids[] = (int) $order_id;
        }
    }
    sort($ids, SORT_NUMERIC);
    return array_slice($ids, ($page - 1) * $limit, $limit);
};
$fake_options = array();
$fake_scheduled = array();
$drain_result = Reconciler::expire_all_tracked(new Gateway(false));
same(true, $drain_result, 'controlled drain reaches the shifted one-hundred-first tracked order');
same(1, max($drain_query_pages), 'controlled drain never advances an offset over its shrinking source set');
check(!Reconciler::has_tracked_orders(), 'controlled drain proves an empty exact source scan before success');
$fake_order_query_handler = null;
$fake_orders = array();
$fake_order_query_ids = array();

// A provider request with no recoverable session ID ages into an explicit
// manual-review state instead of polling forever or allowing a second charge.
$ambiguous_order = new WC_Order();
$ambiguous_order->meta['_bactive_paymongo_attempts'] = array(
    array(
        'generation' => 1,
        'session_id' => '',
        'mode' => 'test',
        'request_pending' => true,
        'request_started_at' => time() - 82801,
    ),
);
$fake_orders = array(42 => $ambiguous_order);
$fake_options = array();
$fake_scheduled = array();
check(Reconciler::reconcile_order(42), 'aged ambiguous provider request reconciles into review state');
same('checkout_creation_ambiguous', $ambiguous_order->meta[Reconciler::UNRESOLVED_META] ?? '', 'aged ambiguous request blocks fulfillment and repeat payment');
same(false, (bool) ($ambiguous_order->meta['_bactive_paymongo_attempts'][0]['request_pending'] ?? true), 'aged ambiguous request stops automatic polling');

// Authenticated retrieval recovery applies the same exactly-one-paid rule as
// the webhook path. Mixed or duplicate payments pause fulfillment for review.
$mixed_retrieval_order = new WC_Order();
$mixed_retrieval_session = session();
$mixed_retrieval_payment = $mixed_retrieval_session['attributes']['payments'][0];
$mixed_retrieval_payment['id'] = 'pay_retrieval_disallowed_456';
$mixed_retrieval_payment['attributes']['currency'] = 'USD';
$mixed_retrieval_payment['attributes']['source'] = array('type' => 'card');
$mixed_retrieval_session['attributes']['payments'][] = $mixed_retrieval_payment;
$fake_orders = array(42 => $mixed_retrieval_order);
$fake_options = array('woocommerce_bactive_paymongo_settings' => array('test_mode' => 'yes'));
$fake_scheduled = array();
$mixed_retrieval_attempt = $mixed_retrieval_order->meta['_bactive_paymongo_attempts'][0];
same(
    'quarantined',
    Webhook::reconcile_checkout_session(
        $mixed_retrieval_order,
        array('data' => $mixed_retrieval_session),
        $mixed_retrieval_attempt,
        false
    ),
    'retrieval with valid and disallowed paid entries is quarantined'
);
same(0, $mixed_retrieval_order->payment_complete_calls, 'mixed retrieval never completes WooCommerce payment');
same(
    'reconciliation_paid_payment_count_invalid',
    $mixed_retrieval_order->meta[Reconciler::UNRESOLVED_META] ?? '',
    'mixed retrieval records exact reconciliation reason'
);

$duplicate_retrieval_order = new WC_Order();
$duplicate_retrieval_session = session();
$duplicate_retrieval_payment = $duplicate_retrieval_session['attributes']['payments'][0];
$duplicate_retrieval_payment['id'] = 'pay_retrieval_duplicate_456';
$duplicate_retrieval_session['attributes']['payments'][] = $duplicate_retrieval_payment;
$fake_orders = array(42 => $duplicate_retrieval_order);
$fake_options = array('woocommerce_bactive_paymongo_settings' => array('test_mode' => 'yes'));
$fake_scheduled = array();
$duplicate_retrieval_attempt = $duplicate_retrieval_order->meta['_bactive_paymongo_attempts'][0];
same(
    'quarantined',
    Webhook::reconcile_checkout_session(
        $duplicate_retrieval_order,
        array('data' => $duplicate_retrieval_session),
        $duplicate_retrieval_attempt,
        false
    ),
    'retrieval with two valid paid entries is quarantined'
);
same(0, $duplicate_retrieval_order->payment_complete_calls, 'duplicate-paid retrieval never completes WooCommerce payment');
same(
    'reconciliation_paid_payment_count_invalid',
    $duplicate_retrieval_order->meta[Reconciler::UNRESOLVED_META] ?? '',
    'duplicate-paid retrieval records exact reconciliation reason'
);

// Missed-webhook recovery uses the authenticated Checkout Session readback and
// applies the exact paid payment once.
$recovery_order = new WC_Order();
$fake_orders = array(42 => $recovery_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array('test_mode' => 'yes'),
);
$fake_scheduled = array();
$recovery_attempt = $recovery_order->meta['_bactive_paymongo_attempts'][0];
$recovery_result = Webhook::reconcile_checkout_session($recovery_order, array('data' => session()), $recovery_attempt, false);
same(
    'processed',
    $recovery_result,
    'missed signed webhook is recovered from authenticated session readback'
);
same(0, $recovery_order->payment_complete_calls, 'missed-webhook recovery bypasses unsafe payment_complete save path');
same(true, $recovery_order->paid, 'missed-webhook recovery persists the paid order state');
same('pay_test_payment_123', $recovery_order->transaction_id, 'missed-webhook recovery stores exact transaction ID');

// An expired Checkout Session may still contain a pending Payment. It remains
// tracked without fulfillment and is recovered if a later authenticated GET
// reports that exact Payment as paid.
$pending_recovery_order = new WC_Order();
$pending_recovery_session = session('qrph', '', 'pending');
$pending_recovery_session['attributes']['status'] = 'expired';
$fake_orders = array(42 => $pending_recovery_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array('test_mode' => 'yes'),
);
$fake_scheduled = array();
$pending_recovery_attempt = $pending_recovery_order->meta['_bactive_paymongo_attempts'][0];
same(
    'payment_pending',
    Webhook::reconcile_checkout_session(
        $pending_recovery_order,
        array('data' => $pending_recovery_session),
        $pending_recovery_attempt,
        false
    ),
    'expired session with a pending Payment remains in reconciliation'
);
same(0, $pending_recovery_order->payment_complete_calls, 'pending Payment never completes WooCommerce');
check(Gateway::has_outstanding_attempts($pending_recovery_order), 'pending Payment keeps its attempt outstanding');
$pending_recovery_session['attributes']['payments'][0]['attributes']['status'] = 'paid';
same(
    'processed',
    Webhook::reconcile_checkout_session(
        $pending_recovery_order,
        array('data' => $pending_recovery_session),
        $pending_recovery_attempt,
        false
    ),
    'later paid readback recovers an expired pending Payment'
);
same(0, $pending_recovery_order->payment_complete_calls, 'later paid readback bypasses unsafe payment_complete save path');
same(true, $pending_recovery_order->paid, 'later paid readback persists the paid order state');
same('pay_test_payment_123', $pending_recovery_order->transaction_id, 'later paid readback stores the exact transaction ID');

// If settings change while create_checkout_session is in flight, the returned
// URL is never issued; the just-created session is expired and read back with
// the captured old-mode key.
$race_order = new WC_Order();
$race_attempt = array(
    'generation' => 1,
    'fingerprint' => hash('sha256', '42|12345|PHP|test'),
    'mode' => 'test',
    'reference' => 'BA-42-1',
    'correlation_id' => 'correlation-123',
    'idempotency_key' => 'bactive-checkout-test-42-1',
    'created_at' => time(),
    'session_id' => '',
    'checkout_url' => '',
);
$race_order->meta['_bactive_paymongo_attempts'] = array($race_attempt);
$fake_orders = array(42 => clone $race_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_race_secret_123456789'),
    ),
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 7,
);
$fake_scheduled = array();
$remote_calls = array();
$request_was_persisted_before_post = false;
$fake_remote_handler = static function (string $url, array $args) use (&$remote_calls, &$request_was_persisted_before_post): array {
    global $fake_options, $fake_orders;
    $remote_calls[] = $url;
    if (str_ends_with($url, '/v2/checkout_sessions')) {
        $request_was_persisted_before_post =
            (bool) ($fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['request_pending'] ?? false)
            && !empty($fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['request_started_at']);
        $fake_options[Reconciler::CONFIG_GENERATION_OPTION] = 8;
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array(
                'id' => 'cs_race_session_123',
                'type' => 'checkout_session',
                'attributes' => array(
                    'checkout_url' => 'https://checkout.paymongo.com/race-session',
                    'livemode' => false,
                ),
            ))),
        );
    }
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => array(
            'id' => 'cs_race_session_123',
            'type' => 'checkout_session',
            'attributes' => array('status' => 'expired', 'livemode' => false, 'payments' => array()),
        ))),
    );
};
$race_gateway = new Gateway(false);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$submit_attempt = new ReflectionMethod(Gateway::class, 'submit_attempt');
$submit_attempt->setAccessible(true);
check(Order_Lock::acquire(42), 'config-race test acquires order fence');
try {
    $race_result = $submit_attempt->invoke($race_gateway, $race_order, 12345, array($race_attempt), 0, false);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('fail', $race_result['result'] ?? '', 'config generation change suppresses returned checkout URL');
check($request_was_persisted_before_post, 'provider request marker is durably saved before Checkout Session POST');
check(count(array_filter($remote_calls, static fn(string $url): bool => str_ends_with($url, '/expire'))) === 1, 'config generation race expires the new provider session');
same(
    'expired',
    isset($fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['expired_at']) ? 'expired' : '',
    'config generation race persists verified expiry'
);

// If the authorized-attempt save is lost, even an expired mutation response
// is unsafe when independent retrieval contains a paid payment. Persist the
// session correlation and hold the gateway for reconciliation.
$persistence_paid_order = new WC_Order();
$persistence_paid_order->meta['_bactive_paymongo_attempts'] = array($race_attempt);
$fake_orders = array(42 => clone $persistence_paid_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_persistence_paid_123456789'),
    ),
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 20,
);
$fake_scheduled = array();
$persistence_paid_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$persistence_paid_calls): array {
    global $fake_persist_order_filter;
    $persistence_paid_calls[] = $url;
    if (str_ends_with($url, '/v2/checkout_sessions')) {
        $fake_persist_order_filter = static function (WC_Order $persisted): WC_Order {
            global $fake_persist_order_filter;
            $persisted->meta['_bactive_paymongo_attempts'][0]['session_id'] = '';
            $persisted->meta['_bactive_paymongo_attempts'][0]['checkout_url'] = '';
            $fake_persist_order_filter = null;
            return $persisted;
        };
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array(
                'id' => 'cs_persistence_paid_123',
                'type' => 'checkout_session',
                'attributes' => array(
                    'checkout_url' => 'https://checkout.paymongo.com/persistence-paid',
                    'livemode' => false,
                ),
            ))),
        );
    }
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => array(
            'id' => 'cs_persistence_paid_123',
            'type' => 'checkout_session',
            'attributes' => array(
                'status' => 'expired',
                'livemode' => false,
                'payments' => array(array(
                    'id' => 'pay_persistence_paid_123',
                    'type' => 'payment',
                    'attributes' => array(
                        'status' => 'paid',
                        'livemode' => false,
                        'currency' => 'PHP',
                        'amount' => 12345,
                        'source' => array('type' => 'qrph'),
                    ),
                )),
            ),
        ))),
    );
};
$persistence_paid_gateway = new Gateway(false);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'expired-plus-paid persistence recovery acquires order fence');
try {
    $persistence_paid_result = $submit_attempt->invoke(
        $persistence_paid_gateway,
        $persistence_paid_order,
        12345,
        array($race_attempt),
        0,
        false
    );
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_persist_order_filter = null;
}
same('fail', $persistence_paid_result['result'] ?? '', 'expired-plus-paid persistence failure issues no link');
same(3, count($persistence_paid_calls), 'persistence failure always creates, expires, and independently retrieves');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'expired-plus-paid readback keeps gateway draining');
same(
    'cs_persistence_paid_123',
    $fake_options['bactive_paymongo_disable_drain_error']['session_id'] ?? '',
    'expired-plus-paid drain record preserves exact session correlation'
);
$persistence_paid_attempts = Gateway::order_attempts($fake_orders[42]);
same('cs_persistence_paid_123', $persistence_paid_attempts[0]['session_id'] ?? '', 'expired-plus-paid order preserves session ID');
same(
    array('pay_persistence_paid_123'),
    $persistence_paid_attempts[0]['reconciliation_payment_ids'] ?? array(),
    'expired-plus-paid order preserves retrieved payment IDs'
);
same(
    'checkout_persistence_failed',
    $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '',
    'expired-plus-paid order requires manual reconciliation'
);
same(0, $fake_orders[42]->payment_complete_calls, 'persistence-failure cleanup never fulfills from an expiry response');

// An expired session with a pending Payment is equally unsafe to classify as
// unpaid. Preserve both identities and keep the global drain engaged until a
// later authenticated readback resolves the Payment.
$persistence_pending_order = new WC_Order();
$persistence_pending_order->meta['_bactive_paymongo_attempts'] = array($race_attempt);
$fake_orders = array(42 => clone $persistence_pending_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_persistence_pending_123456'),
    ),
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 22,
);
$fake_scheduled = array();
$persistence_pending_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$persistence_pending_calls): array {
    global $fake_persist_order_filter;
    $persistence_pending_calls[] = $url;
    if (str_ends_with($url, '/v2/checkout_sessions')) {
        $fake_persist_order_filter = static function (WC_Order $persisted): WC_Order {
            global $fake_persist_order_filter;
            $persisted->meta['_bactive_paymongo_attempts'][0]['session_id'] = '';
            $persisted->meta['_bactive_paymongo_attempts'][0]['checkout_url'] = '';
            $fake_persist_order_filter = null;
            return $persisted;
        };
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array(
                'id' => 'cs_persistence_pending_123',
                'type' => 'checkout_session',
                'attributes' => array(
                    'checkout_url' => 'https://checkout.paymongo.com/persistence-pending',
                    'livemode' => false,
                ),
            ))),
        );
    }
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => array(
            'id' => 'cs_persistence_pending_123',
            'type' => 'checkout_session',
            'attributes' => array(
                'status' => 'expired',
                'livemode' => false,
                'payments' => array(array(
                    'id' => 'pay_persistence_pending_123',
                    'type' => 'payment',
                    'attributes' => array(
                        'status' => 'pending',
                        'livemode' => false,
                    ),
                )),
            ),
        ))),
    );
};
$persistence_pending_gateway = new Gateway(false);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'expired-plus-pending persistence recovery acquires order fence');
try {
    $persistence_pending_result = $submit_attempt->invoke(
        $persistence_pending_gateway,
        $persistence_pending_order,
        12345,
        array($race_attempt),
        0,
        false
    );
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_persist_order_filter = null;
}
same('fail', $persistence_pending_result['result'] ?? '', 'expired-plus-pending persistence failure issues no link');
same(3, count($persistence_pending_calls), 'pending persistence failure creates, expires, and independently retrieves');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'expired-plus-pending readback keeps gateway draining');
$persistence_pending_attempts = Gateway::order_attempts($fake_orders[42]);
same('cs_persistence_pending_123', $persistence_pending_attempts[0]['session_id'] ?? '', 'expired-plus-pending order preserves session ID');
same(
    array('pay_persistence_pending_123'),
    $persistence_pending_attempts[0]['reconciliation_payment_ids'] ?? array(),
    'expired-plus-pending order preserves pending Payment ID'
);
check(empty($persistence_pending_attempts[0]['expired_at']), 'pending Payment prevents terminal expiry marker');
same(
    'checkout_persistence_failed',
    $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '',
    'expired-plus-pending order remains explicitly unresolved'
);
check(Gateway::has_outstanding_attempts($fake_orders[42]), 'expired-plus-pending order remains outstanding');
same(0, $fake_orders[42]->payment_complete_calls, 'pending persistence cleanup never fulfills WooCommerce');

// An unreadable independent retrieval follows the same durable correlation
// and global-drain path even when the expire response itself says expired.
$persistence_readback_order = new WC_Order();
$persistence_readback_order->meta['_bactive_paymongo_attempts'] = array($race_attempt);
$fake_orders = array(42 => clone $persistence_readback_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_persistence_readback_123456'),
    ),
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 21,
);
$fake_scheduled = array();
$persistence_readback_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$persistence_readback_calls) {
    global $fake_persist_order_filter;
    $persistence_readback_calls[] = $url;
    if (str_ends_with($url, '/v2/checkout_sessions')) {
        $fake_persist_order_filter = static function (WC_Order $persisted): WC_Order {
            global $fake_persist_order_filter;
            $persisted->meta['_bactive_paymongo_attempts'][0]['session_id'] = '';
            $persisted->meta['_bactive_paymongo_attempts'][0]['checkout_url'] = '';
            $fake_persist_order_filter = null;
            return $persisted;
        };
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array(
                'id' => 'cs_persistence_readback_123',
                'type' => 'checkout_session',
                'attributes' => array(
                    'checkout_url' => 'https://checkout.paymongo.com/persistence-readback',
                    'livemode' => false,
                ),
            ))),
        );
    }
    if (str_ends_with($url, '/expire')) {
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array(
                'id' => 'cs_persistence_readback_123',
                'type' => 'checkout_session',
                'attributes' => array('status' => 'expired', 'livemode' => false, 'payments' => array()),
            ))),
        );
    }
    return new WP_Error('simulated_readback_failure', 'Independent retrieval failed.');
};
$persistence_readback_gateway = new Gateway(false);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'failed-readback persistence recovery acquires order fence');
try {
    $persistence_readback_result = $submit_attempt->invoke(
        $persistence_readback_gateway,
        $persistence_readback_order,
        12345,
        array($race_attempt),
        0,
        false
    );
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_persist_order_filter = null;
}
same('fail', $persistence_readback_result['result'] ?? '', 'failed independent readback issues no link');
same(3, count($persistence_readback_calls), 'failed readback still follows create, expire, retrieve sequence');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'failed independent readback keeps gateway draining');
same(
    'cs_persistence_readback_123',
    Gateway::order_attempts($fake_orders[42])[0]['session_id'] ?? '',
    'failed independent readback still persists session correlation'
);
same(
    'checkout_persistence_failed',
    $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '',
    'failed independent readback leaves exact order review marker'
);

// Failed-session recovery must replace the exact pre-request identity, not the
// first attempt that happens to share a generation/reference/correlation tuple.
$persist_failed_correlation = new ReflectionMethod(Gateway::class, 'persist_failed_session_correlation');
$persist_failed_correlation->setAccessible(true);
$recovery_started_at = time() - 15;
$recovery_expected = array(
    'generation' => 11,
    'fingerprint' => hash('sha256', 'shared-recovery-cart-fingerprint'),
    'mode' => 'test',
    'reference' => 'BA-42-11',
    'correlation_id' => 'shared-recovery-correlation-123',
    'idempotency_key' => 'shared-recovery-idempotency-123',
    'created_at' => $recovery_started_at,
    'config_generation' => 13,
    'request_started_at' => $recovery_started_at,
    'request_pending' => true,
    'session_id' => '',
    'checkout_url' => '',
);
$recovery_live_attempt = $recovery_expected;
$recovery_live_attempt['mode'] = 'live';
$recovery_order = new WC_Order();
$recovery_order->meta['_bactive_paymongo_attempts'] = array($recovery_live_attempt, $recovery_expected);
$fake_orders = array(42 => clone $recovery_order);
$fake_options = array();
$fake_before_order_save = null;
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'mode-bound failed-session recovery acquires order fence');
try {
    $mode_bound_recovery_result = $persist_failed_correlation->invoke(
        new Gateway(false),
        42,
        $recovery_expected,
        'cs_recovered_test_exact_123',
        true,
        array()
    );
} finally {
    Order_Lock::release(42);
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($mode_bound_recovery_result, 'failed-session recovery persists one exact mode-bound attempt');
$mode_bound_recovery_attempts = Gateway::order_attempts($fake_orders[42]);
same('', $mode_bound_recovery_attempts[0]['session_id'] ?? '', 'failed-session recovery leaves colliding live attempt untouched');
check(empty($mode_bound_recovery_attempts[0]['expired_at']), 'failed-session recovery leaves colliding live attempt active');
same(
    'cs_recovered_test_exact_123',
    $mode_bound_recovery_attempts[1]['session_id'] ?? '',
    'failed-session recovery assigns provider session only to exact sandbox attempt'
);
check(!empty($mode_bound_recovery_attempts[1]['expired_at']), 'failed-session recovery marks only exact sandbox attempt expired');

// Two byte-identical request identities cannot safely identify which row owns
// the provider result. Reject without appending/replacing or saving either row.
$duplicate_recovery_order = new WC_Order();
$duplicate_recovery_order->meta['_bactive_paymongo_attempts'] = array($recovery_expected, $recovery_expected);
$duplicate_recovery_before = $duplicate_recovery_order->meta['_bactive_paymongo_attempts'];
$fake_orders = array(42 => clone $duplicate_recovery_order);
$fake_options = array();
$fake_before_order_save = null;
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'duplicate failed-session recovery acquires order fence');
try {
    $duplicate_recovery_result = $persist_failed_correlation->invoke(
        new Gateway(false),
        42,
        $recovery_expected,
        'cs_recovered_ambiguous_456',
        true,
        array()
    );
} finally {
    Order_Lock::release(42);
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same(false, $duplicate_recovery_result, 'duplicate failed-session identity fails closed');
same(
    $duplicate_recovery_before,
    Gateway::order_attempts($fake_orders[42]),
    'duplicate failed-session identity leaves the persisted ledger byte-identical'
);
same(0, $fake_orders[42]->save_calls, 'duplicate failed-session identity performs no order write');

// An exact settings comparison independently closes the race even if two
// concurrent configuration writes collide on the same generation number.
$settings_race_order = new WC_Order();
$settings_race_order->meta['_bactive_paymongo_attempts'] = array($race_attempt);
$fake_orders = array(42 => clone $settings_race_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_settings_race_old_123456'),
    ),
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 9,
);
$fake_scheduled = array();
$settings_race_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$settings_race_calls): array {
    global $fake_options;
    $settings_race_calls[] = $url;
    if (str_ends_with($url, '/v2/checkout_sessions')) {
        $fake_options['woocommerce_bactive_paymongo_settings']['test_secret_key'] =
            Secrets::encrypt('sk_test_settings_race_new_654321');
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array(
                'id' => 'cs_settings_race_123',
                'type' => 'checkout_session',
                'attributes' => array(
                    'checkout_url' => 'https://checkout.paymongo.com/settings-race',
                    'livemode' => false,
                ),
            ))),
        );
    }
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => array(
            'id' => 'cs_settings_race_123',
            'type' => 'checkout_session',
            'attributes' => array('status' => 'expired', 'livemode' => false, 'payments' => array()),
        ))),
    );
};
$settings_race_gateway = new Gateway(false);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'exact-settings race test acquires order fence');
try {
    $settings_race_result = $submit_attempt->invoke(
        $settings_race_gateway,
        $settings_race_order,
        12345,
        array($race_attempt),
        0,
        false
    );
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('fail', $settings_race_result['result'] ?? '', 'exact settings change suppresses returned checkout URL');
same(
    9,
    (int) ($fake_options[Reconciler::CONFIG_GENERATION_OPTION] ?? -1),
    'exact settings race is caught even when generation does not advance'
);
check(
    count(array_filter($settings_race_calls, static fn(string $url): bool => str_ends_with($url, '/expire'))) === 1,
    'exact settings race expires the just-created provider session'
);

// If a configuration race creates a session that cannot be proven expired or
// paid, the entire gateway remains draining and the order is put into explicit
// review instead of reopening under the replacement credentials.
$settings_failure_order = new WC_Order();
$settings_failure_order->meta['_bactive_paymongo_attempts'] = array($race_attempt);
$fake_orders = array(42 => clone $settings_failure_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_settings_failure_old_123456'),
    ),
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 12,
);
$fake_scheduled = array();
$settings_failure_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$settings_failure_calls): array {
    global $fake_options;
    $settings_failure_calls[] = $url;
    if (str_ends_with($url, '/v2/checkout_sessions')) {
        $fake_options['woocommerce_bactive_paymongo_settings']['test_secret_key'] =
            Secrets::encrypt('sk_test_settings_failure_new_654321');
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array(
                'id' => 'cs_settings_failure_123',
                'type' => 'checkout_session',
                'attributes' => array(
                    'checkout_url' => 'https://checkout.paymongo.com/settings-failure',
                    'livemode' => false,
                ),
            ))),
        );
    }
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => array(
            'id' => 'cs_settings_failure_123',
            'type' => 'checkout_session',
            'attributes' => array('status' => 'active', 'livemode' => false, 'payments' => array()),
        ))),
    );
};
$settings_failure_gateway = new Gateway(false);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'unresolved settings-race test acquires order fence');
try {
    $settings_failure_result = $submit_attempt->invoke(
        $settings_failure_gateway,
        $settings_failure_order,
        12345,
        array($race_attempt),
        0,
        false
    );
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('fail', $settings_failure_result['result'] ?? '', 'unresolved settings race suppresses checkout URL');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'unresolved settings race leaves gateway draining');
same(
    'config_change_session_unresolved',
    $fake_options['bactive_paymongo_disable_drain_error']['code'] ?? '',
    'unresolved settings race records a durable drain error'
);
same(
    'config_change_session_unresolved',
    $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '',
    'unresolved settings race marks its exact order for review'
);
same(3, count($settings_failure_calls), 'unresolved settings race performs create, expire, and independent readback');

// A definitive provider rejection is terminal. A later retry must create a
// clean generation instead of reusing the rejected request and carrying its
// terminal marker onto the newly authorized Checkout Session.
$retry_order = new WC_Order();
$rejected_attempt = array(
    'generation' => 1,
    'fingerprint' => hash('sha256', '42|12345|PHP|test'),
    'mode' => 'test',
    'reference' => 'BA-42-1',
    'correlation_id' => 'correlation-rejected-123',
    'idempotency_key' => 'bactive-checkout-test-42-rejected',
    'created_at' => time(),
    'config_generation' => 11,
    'session_id' => '',
    'checkout_url' => '',
    'request_pending' => false,
    'request_rejected_at' => time(),
);
$retry_order->meta['_bactive_paymongo_attempts'] = array($rejected_attempt);
$fake_orders = array(42 => clone $retry_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_terminal_retry_123456789'),
    ),
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 11,
);
$fake_scheduled = array();
$retry_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$retry_calls): array {
    $retry_calls[] = $url;
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => array(
            'id' => 'cs_retry_session_123',
            'type' => 'checkout_session',
            'attributes' => array(
                'checkout_url' => 'https://checkout.paymongo.com/retry-session',
                'livemode' => false,
            ),
        ))),
    );
};
$retry_gateway = new Gateway(false);
$create_or_reuse_session = new ReflectionMethod(Gateway::class, 'create_or_reuse_session');
$create_or_reuse_session->setAccessible(true);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'terminal-attempt retry test acquires order fence');
try {
    $retry_result = $create_or_reuse_session->invoke($retry_gateway, $retry_order, 12345, false);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('success', $retry_result['result'] ?? '', 'definitively rejected request may be retried');
same(
    'https://checkout.paymongo.com/retry-session',
    $retry_result['redirect'] ?? '',
    'retry returns only the newly authorized Checkout Session URL'
);
same(1, count($retry_calls), 'terminal-attempt retry creates exactly one provider session');
$persisted_retry_attempts = Gateway::order_attempts($fake_orders[42]);
same(2, count($persisted_retry_attempts), 'terminal-attempt retry preserves history and creates a new generation');
same(2, (int) ($persisted_retry_attempts[1]['generation'] ?? 0), 'terminal-attempt retry advances generation');
check(
    empty($persisted_retry_attempts[1]['request_rejected_at'])
        && empty($persisted_retry_attempts[1]['request_aborted_at']),
    'new retry generation does not inherit terminal request markers'
);
check(Gateway::has_outstanding_attempts($fake_orders[42]), 'newly authorized retry remains outstanding for settlement');

// A stored signing secret is valid only for the exact provider webhook ID
// from which it was obtained. A delete/recreate at the same URL must fail
// closed when list-webhooks omits the new secret.
$binding_api_key = 'sk_test_webhook_binding_123456789';
$binding_secret = 'whsk_webhook_binding_123456789';
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'enabled' => 'yes',
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt($binding_api_key),
    ),
    'bactive_paymongo_test_webhook_secret' => Secrets::encrypt($binding_secret),
    'bactive_paymongo_test_webhook_secret_binding' => array(
        'webhook_id' => 'hook_original_binding_123',
        'secret_fingerprint' => Secrets::fingerprint($binding_secret),
        'recorded_at' => time(),
    ),
);
$fake_remote_handler = static function (string $url, array $args): array {
    if (str_ends_with($url, '/v1/merchants/capabilities/payment_methods')) {
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array('attributes' => array('payment_methods' => array('qrph'))))),
        );
    }
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => array(array(
            'id' => 'hook_recreated_binding_456',
            'type' => 'webhook',
            'attributes' => array(
                'url' => Readiness::endpoint_url(false),
                'status' => 'enabled',
                'events' => array('checkout_session.payment.paid'),
                'livemode' => false,
            ),
        )))),
    );
};
$binding_result = Readiness::verify_and_provision(new Gateway(false), false);
check(is_wp_error($binding_result), 'changed webhook ID without a returned secret fails readiness');
same('paymongo_webhook_secret_missing', $binding_result->get_error_code(), 'changed webhook ID reports exact secret-binding failure');
same(array(), Readiness::state(false), 'changed webhook ID clears cached readiness');
$fake_remote_handler = null;

// Provider-paid evidence without a settlement marker is never a normal
// payable state. It is protected across checkout, reconciliation, refunds,
// deletion, and stale WooCommerce saves even when the attempt list is empty.
$facts_only_order = new WC_Order();
$facts_only_order->meta['_bactive_paymongo_attempts'] = array();
$facts_only_order->meta['_bactive_paymongo_paid_event_id'] = 'evt_torn_facts_123';
$facts_only_order->meta['_bactive_paymongo_paid_session_id'] = 'cs_torn_facts_123';
$facts_only_order->meta['_bactive_paymongo_source_method'] = 'qrph';
check(Gateway::has_protected_payment_state($facts_only_order), 'zero-attempt provider facts remain protected');
check(Gateway::has_inconsistent_provider_payment_state($facts_only_order), 'zero-attempt unpaid provider facts are inconsistent');

$fake_orders = array(42 => $facts_only_order);
$fake_options = array();
$fake_scheduled = array();
check(Reconciler::reconcile_order(42), 'zero-attempt provider facts enter reconciliation');
same(
    'provider_payment_state_inconsistent',
    $facts_only_order->meta[Reconciler::UNRESOLVED_META] ?? '',
    'zero-attempt provider facts persist an exact manual-review marker'
);
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'torn provider facts drain new PayMongo checkout');

$paid_attempt_torn = new WC_Order();
$paid_attempt_torn->meta['_bactive_paymongo_attempts'][0]['payment_id'] = 'pay_torn_attempt_123';
$paid_attempt_torn->meta['_bactive_paymongo_attempts'][0]['paid_event_id'] = 'evt_torn_attempt_123';
$paid_attempt_torn->meta['_bactive_paymongo_attempts'][0]['paid_at'] = time();
check(Gateway::has_inconsistent_provider_payment_state($paid_attempt_torn), 'paid-attempt metadata without core paid state is inconsistent');
$fake_orders = array(42 => $paid_attempt_torn);
$fake_options = array();
$fake_scheduled = array();
check(Reconciler::reconcile_order(42), 'paid-attempt torn state enters reconciliation');
same(
    'provider_payment_state_inconsistent',
    $paid_attempt_torn->meta[Reconciler::UNRESOLVED_META] ?? '',
    'paid-attempt torn state cannot be silently unscheduled'
);

$coherent_history = new WC_Order();
$coherent_history->status = 'processing';
$coherent_history->paid = true;
$coherent_history->transaction_id = 'pay_coherent_history_123';
$coherent_history->date_paid = new DateTimeImmutable('@1788559200');
$coherent_history->meta['_bactive_paymongo_paid_event_id'] = 'evt_coherent_history_123';
$coherent_history->meta['_bactive_paymongo_paid_session_id'] = 'cs_test_session_123';
$coherent_history->meta['_bactive_paymongo_paid_mode'] = 'test';
$coherent_history->meta['_bactive_paymongo_source_method'] = 'qrph';
$coherent_history->meta['_bactive_paymongo_source_provider'] = '';
$coherent_history->meta['_bactive_paymongo_attempts'][0]['payment_id'] = 'pay_coherent_history_123';
$coherent_history->meta['_bactive_paymongo_attempts'][0]['paid_event_id'] = 'evt_coherent_history_123';
$coherent_history->meta['_bactive_paymongo_attempts'][0]['paid_at'] = 1788559200;
check(!Gateway::has_inconsistent_provider_payment_state($coherent_history), 'coherent paid history is not re-queued as torn state');

$scan_args = array();
$fake_order_query_handler = static function (array $args) use (&$scan_args): array {
    $scan_args = $args;
    return array();
};
check(!Reconciler::has_tracked_orders(), 'empty provider-fact source scan completes');
$scan_keys = $scan_args['meta_query'][0]['key'] ?? array();
same(1, count($scan_args['meta_query'] ?? array()), 'HPOS recovery discovery uses one metadata clause');
same('IN', $scan_args['meta_query'][0]['compare_key'] ?? '', 'HPOS recovery matches its complete key allowlist in one join');
check(in_array('_bactive_paymongo_paid_event_id', $scan_keys, true), 'recovery discovery scans zero-attempt paid-event facts');
check(in_array('_bactive_paymongo_paid_session_id', $scan_keys, true), 'recovery discovery scans zero-attempt paid-session facts');
check(in_array('_bactive_paymongo_attempts', $scan_keys, true), 'active drain discovery retains attempt-only source coverage');
same(17, count($scan_keys), 'recovery discovery retains every existing payment-state key');
Test_Order_Util::$hpos = false;
check(!Reconciler::has_tracked_orders(), 'empty CPT provider-fact source scan completes');
check(!isset($scan_args['meta_query']), 'CPT does not receive the unsupported top-level metadata argument');
same(true, $scan_args['bactive_paymongo_source_scan'] ?? false, 'CPT source scan sets its internal translation marker');
$ordinary_args = array('post_type' => 'shop_order', 'meta_query' => array(array('key' => 'other_extension_key', 'value' => 'kept')));
same($ordinary_args, Reconciler::filter_cpt_source_query($ordinary_args, array()), 'CPT adapter leaves ordinary order queries untouched');
$translated = Reconciler::filter_cpt_source_query($ordinary_args, $scan_args);
same('AND', $translated['meta_query']['relation'] ?? '', 'CPT preserves existing metadata restrictions');
same($ordinary_args['meta_query'], $translated['meta_query'][0] ?? null, 'CPT keeps other extension metadata predicates intact');
same($scan_keys, $translated['meta_query'][1][0]['key'] ?? array(), 'CPT and HPOS discover the same payment-state keys');
Test_Order_Util::$hpos = true;
$fake_order_query_handler = static function (array $args): array {
    global $wpdb;
    $wpdb->last_error = 'synthetic database query failure';
    return array();
};
check(Reconciler::has_tracked_orders(), 'a database failure cannot clear the outstanding-payment gate');
$wpdb->last_error = '';
$fake_order_query_handler = null;

// Active-order discovery queries retained audit history, then excludes only a
// coherent settled order. This lets a drain reach zero without deleting
// immutable provider facts, while an attempt-only payable session stays live.
$fake_orders = array(42 => clone $coherent_history);
$fake_order_query_handler = static function (array $args): array {
    return (int) ($args['page'] ?? 1) === 1 ? array(42) : array();
};
check(!Reconciler::has_tracked_orders(), 'coherent settled audit history is excluded from the active drain set');
check(Reconciler::expire_all_tracked(new Gateway(false)) === true, 'coherent settled history permits an empty controlled drain');
same(
    'evt_coherent_history_123',
    $fake_orders[42]->meta['_bactive_paymongo_paid_event_id'] ?? '',
    'controlled drain retains coherent provider audit facts'
);

$attempt_only_active = new WC_Order();
$attempt_only_active->meta[Reconciler::REQUIRED_META] = '';
$fake_orders = array(42 => $attempt_only_active);
check(Reconciler::has_tracked_orders(), 'attempt-only payable session remains in the active drain set');
$fake_order_query_handler = null;

$prior_review_order = new WC_Order();
$prior_review_order->meta['_bactive_paymongo_attempts'] = array();
$prior_review_order->meta[Reconciler::REQUIRED_META] = 'yes';
$prior_review_order->meta[Reconciler::UNRESOLVED_META] = 'session_not_authorized';
$prior_review_order->meta['_bactive_paymongo_review_required'] = 'session_not_authorized';
$fake_orders = array(42 => $prior_review_order);
$fake_options = array();
$fake_wc->session->data['order_awaiting_payment'] = 42;
$new_cod_order = new WC_Order();
$new_cod_order->id = 0;
$new_cod_order->payment_method = 'cod';
$prior_review_blocked = false;
$lifecycle_test_gateway->acquire_checkout_submission_lock();
try {
    $lifecycle_test_gateway->handle_checkout_create_order($new_cod_order, array());
} catch (Exception $error) {
    $prior_review_blocked = true;
} finally {
    $lifecycle_test_gateway->release_request_locks();
}
check($prior_review_blocked, 'zero-attempt prior review blocks creation of a second COD order');
$fake_wc->session->data = array();

// A cached-ready gateway still cannot issue a provider request when the order
// carries torn provider-paid evidence. The state predicate is an independent
// final fence immediately before session creation.
$protected_key = 'sk_test_protected_state_123456789';
$protected_webhook_secret = 'whsk_protected_state_123456789';
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'enabled' => 'yes',
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt($protected_key),
    ),
    'bactive_paymongo_draining' => 'no',
    Reconciler::CONFIG_GENERATION_OPTION => 0,
);
$fake_remote_handler = static function (string $url, array $args) use ($protected_webhook_secret): array {
    if (str_ends_with($url, '/v1/merchants/capabilities/payment_methods')) {
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('data' => array('attributes' => array('payment_methods' => array('qrph'))))),
        );
    }
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => array(array(
            'id' => 'hook_protected_state_123',
            'type' => 'webhook',
            'attributes' => array(
                'url' => Readiness::endpoint_url(false),
                'status' => 'enabled',
                'events' => array('checkout_session.payment.paid'),
                'livemode' => false,
                'secret_key' => $protected_webhook_secret,
            ),
        )))),
    );
};
$protected_gateway = new Gateway(false);
check(Readiness::verify_and_provision($protected_gateway, false) === true, 'protected-state regression establishes exact cached readiness');
$protected_no_post = new WC_Order();
$protected_no_post->meta['_bactive_paymongo_attempts'] = array();
$protected_no_post->meta['_bactive_paymongo_paid_event_id'] = 'evt_no_post_torn_123';
$protected_no_post->meta['_bactive_paymongo_paid_session_id'] = 'cs_no_post_torn_123';
$protected_no_post->meta['_bactive_paymongo_source_method'] = 'qrph';
$fake_orders = array(42 => $protected_no_post);
$protected_provider_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$protected_provider_calls): WP_Error {
    $protected_provider_calls[] = $url;
    return new WP_Error('unexpected_provider_call', 'Torn provider facts must stop before HTTP.');
};
$protected_result = $protected_gateway->process_payment(42);
same('fail', $protected_result['result'] ?? '', 'torn provider-paid state blocks PayMongo checkout even when readiness is cached');
same(array(), $protected_provider_calls, 'torn provider-paid state permits zero Checkout Session provider calls');
$fake_remote_handler = null;

$fake_orders = array(42 => clone $protected_no_post);
$partial_refund_blocked = false;
try {
    $lifecycle_test_gateway->guard_refund_creation(
        (object) array('kind' => 'torn-provider-state'),
        array('order_id' => 42, 'amount' => '1.00', 'refund_payment' => false)
    );
} catch (Exception $error) {
    $partial_refund_blocked = true;
}
check($partial_refund_blocked, 'zero-attempt provider facts block WooCommerce refund creation');
same(false, $lifecycle_test_gateway->guard_order_deletion(null, $fake_orders[42], false), 'zero-attempt provider facts block order deletion');

$stale_facts_delete = new Fake_Meta_Deletion_Order();
$stale_facts_delete->payment_method = 'cod';
$stale_facts_delete->status = 'cancelled';
$stale_facts_delete->changes = array('payment_method' => 'cod', 'status' => 'cancelled');
$stale_facts_delete->arm_paymongo_meta_deletion('_bactive_paymongo_paid_event_id');
$stale_facts_delete->arm_status_transition('pending', 'cancelled');
$protected_facts_stored = clone $facts_only_order;
$protected_facts_stored->meta[Reconciler::UNRESOLVED_META] = '';
$protected_facts_stored->meta['_bactive_paymongo_review_required'] = '';
$fake_orders = array(42 => $protected_facts_stored);
$fake_options = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
try {
    $stale_facts_delete->save();
} catch (Throwable $error) {
    $stale_facts_delete->save_error_caught = true;
}
check($stale_facts_delete->save_error_caught, 'zero-attempt provider-fact overwrite is blocked');
same(0, $stale_facts_delete->status_transition_effects, 'zero-attempt provider-fact overwrite emits no status effects');
same('evt_torn_facts_123', $fake_orders[42]->meta['_bactive_paymongo_paid_event_id'] ?? '', 'zero-attempt provider facts remain authoritative');
$fake_before_order_save = null;

$stale_paid_attempt = new Fake_Catching_Status_Order();
$stale_paid_attempt->status = 'cancelled';
$stale_paid_attempt->changes = array('status' => 'cancelled');
$stale_paid_attempt->arm_status_transition('pending', 'cancelled');
$fake_orders = array(42 => clone $paid_attempt_torn);
$fake_options = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$stale_paid_attempt->save();
check($stale_paid_attempt->save_error_caught, 'paid-attempt marker-missing stale transition is blocked');
same(0, $stale_paid_attempt->status_transition_effects, 'paid-attempt marker-missing transition emits no status effects');
$fake_before_order_save = null;

// Third-party save hooks must not smuggle an unarmed WooCommerce status
// transition through either settlement phase. The request remains retryable,
// no payment/status effect is emitted, and a phase-two target mutation is
// permanently fenced from automatic replay.
$process_claimed_payment = new ReflectionMethod(Webhook::class, 'process_claimed_payment');
$process_claimed_payment->setAccessible(true);

$phase1_before_validated = $validated_payment;
$phase1_before_validated['payment_id'] = 'pay_phase1_before_123';
$phase1_before_validated['event_id'] = 'evt_phase1_before_123';
$phase1_before_order = new WC_Order();
$fake_orders = array(42 => clone $phase1_before_order);
$fake_options = array();
$fake_hook_calls = array();
$phase1_before_save_count = 0;
$phase1_before_mutator = static function ($saving_order) use (&$phase1_before_save_count): void {
    ++$phase1_before_save_count;
    if ($phase1_before_save_count === 1 && $saving_order instanceof WC_Order) {
        $saving_order->set_status('processing');
    }
};
add_action('woocommerce_before_order_object_save', $phase1_before_mutator, 10, 1);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $phase1_before_result = $process_claimed_payment->invoke(null, $phase1_before_order, $phase1_before_validated, false);
} finally {
    remove_action('woocommerce_before_order_object_save', $phase1_before_mutator, 10);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('retry', $phase1_before_result, 'before-save phase-one status injection keeps the provider event retryable');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'before-save phase-one injection emits zero status hooks');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'before-save phase-one injection emits zero payment-complete hooks');
check(
    !isset($fake_options[test_effects_option('payment', 'pay_phase1_before_123')]),
    'before-save phase-one injection never arms payment effects'
);
check(
    !isset($fake_options[test_claim_option('payment', 'pay_phase1_before_123')])
        && !isset($fake_options[test_claim_option('event', 'evt_phase1_before_123')]),
    'before-save phase-one injection releases both provider claims'
);

$phase2_before_validated = $validated_payment;
$phase2_before_validated['payment_id'] = 'pay_phase2_before_123';
$phase2_before_validated['event_id'] = 'evt_phase2_before_123';
$phase2_before_order = new WC_Order();
$fake_orders = array(42 => clone $phase2_before_order);
$fake_options = array();
$fake_hook_calls = array();
$phase2_before_save_count = 0;
$phase2_before_mutator = static function ($saving_order) use (&$phase2_before_save_count): void {
    ++$phase2_before_save_count;
    if ($phase2_before_save_count === 2 && $saving_order instanceof WC_Order) {
        $saving_order->set_status('completed');
    }
};
add_action('woocommerce_before_order_object_save', $phase2_before_mutator, 10, 1);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $phase2_before_result = $process_claimed_payment->invoke(null, $phase2_before_order, $phase2_before_validated, false);
} finally {
    remove_action('woocommerce_before_order_object_save', $phase2_before_mutator, 10);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$phase2_before_effect_option = test_effects_option('payment', 'pay_phase2_before_123');
same('retry', $phase2_before_result, 'before-save phase-two target mutation keeps the provider event retryable');
same('completed', $fake_orders[42]->status, 'before-save phase-two target mutation is detected from persisted state');
same('armed', $fake_options[$phase2_before_effect_option]['status'] ?? '', 'before-save phase-two mutation leaves effects armed, not emitted');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'before-save phase-two target mutation emits zero paid status hooks');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'before-save phase-two target mutation emits zero payment-complete hooks');

$phase2_before_retry_order = clone $fake_orders[42];
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $phase2_before_retry_result = $process_claimed_payment->invoke(
        null,
        $phase2_before_retry_order,
        $phase2_before_validated,
        false
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('retry', $phase2_before_retry_result, 'phase-two target mismatch cannot be replayed automatically');
same('armed', $fake_options[$phase2_before_effect_option]['status'] ?? '', 'phase-two target mismatch keeps its at-most-once record armed');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'phase-two mismatch retry emits zero status hooks');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'phase-two mismatch retry emits zero payment-complete hooks');

$phase1_after_validated = $validated_payment;
$phase1_after_validated['payment_id'] = 'pay_phase1_after_123';
$phase1_after_validated['event_id'] = 'evt_phase1_after_123';
$phase1_after_order = new WC_Order();
$fake_orders = array(42 => clone $phase1_after_order);
$fake_options = array();
$fake_hook_calls = array();
$phase1_after_save_count = 0;
$phase1_after_mutator = static function ($saving_order) use (&$phase1_after_save_count): void {
    ++$phase1_after_save_count;
    if ($phase1_after_save_count === 1 && $saving_order instanceof WC_Order) {
        $saving_order->set_status('processing');
    }
};
add_action('woocommerce_after_order_object_save', $phase1_after_mutator, 10, 1);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $phase1_after_result = $process_claimed_payment->invoke(null, $phase1_after_order, $phase1_after_validated, false);
} finally {
    remove_action('woocommerce_after_order_object_save', $phase1_after_mutator, 10);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('retry', $phase1_after_result, 'after-save phase-one status injection keeps the provider event retryable');
same('pending', $fake_orders[42]->status, 'after-save phase-one injection cannot alter persisted order status');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'after-save phase-one injection emits zero status hooks');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'after-save phase-one injection emits zero payment-complete hooks');
check(
    !isset($fake_options[test_effects_option('payment', 'pay_phase1_after_123')]),
    'after-save phase-one injection never arms payment effects'
);

$phase2_after_validated = $validated_payment;
$phase2_after_validated['payment_id'] = 'pay_phase2_after_123';
$phase2_after_validated['event_id'] = 'evt_phase2_after_123';
$phase2_after_order = new WC_Order();
$fake_orders = array(42 => clone $phase2_after_order);
$fake_options = array();
$fake_hook_calls = array();
$phase2_after_save_count = 0;
$phase2_after_mutator = static function ($saving_order) use (&$phase2_after_save_count): void {
    ++$phase2_after_save_count;
    if ($phase2_after_save_count === 2 && $saving_order instanceof WC_Order) {
        $saving_order->set_status('completed');
    }
};
add_action('woocommerce_after_order_object_save', $phase2_after_mutator, 10, 1);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $phase2_after_result = $process_claimed_payment->invoke(null, $phase2_after_order, $phase2_after_validated, false);
} finally {
    remove_action('woocommerce_after_order_object_save', $phase2_after_mutator, 10);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$phase2_after_effect_option = test_effects_option('payment', 'pay_phase2_after_123');
same('retry', $phase2_after_result, 'after-save phase-two status injection keeps the provider event retryable');
same('processing', $fake_orders[42]->status, 'after-save phase-two injection leaves only the armed paid status persisted');
same('armed', $fake_options[$phase2_after_effect_option]['status'] ?? '', 'after-save phase-two injection leaves payment effects armed');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'after-save phase-two injection emits zero paid status hooks');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'after-save phase-two injection emits zero payment-complete hooks');

// Once an at-most-once effects record reaches processing, an exception could
// mean an extension already sent mail, changed stock, or created fulfillment.
// Automatic retries never replay it. Only an exact operator action with a
// fresh provider GET can close that one incident, without emitting hooks.
$ambiguous_validated = $validated_payment;
$ambiguous_validated['payment_id'] = 'pay_effects_ambiguous_123';
$ambiguous_validated['event_id'] = 'evt_effects_ambiguous_123';
$ambiguous_order = new WC_Order();
$ambiguous_key = 'sk_test_effects_ambiguity_123456789';
$fake_orders = array(42 => clone $ambiguous_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt($ambiguous_key),
    ),
);
$fake_hook_calls = array();
$ambiguous_thrower = static function ($order_id, $payment_id): void {
    throw new RuntimeException('simulated downstream payment effect failure');
};
add_action('woocommerce_pre_payment_complete', $ambiguous_thrower, 10, 2);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $ambiguous_result = $process_claimed_payment->invoke(null, $ambiguous_order, $ambiguous_validated, false);
} finally {
    remove_action('woocommerce_pre_payment_complete', $ambiguous_thrower, 10);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$ambiguous_effect_option = test_effects_option('payment', 'pay_effects_ambiguous_123');
same('retry', $ambiguous_result, 'exception after effects begin keeps provider delivery retryable');
same('processing', $fake_options[$ambiguous_effect_option]['status'] ?? '', 'ambiguous downstream effects remain durably processing');
same('pay_effects_ambiguous_123', $fake_orders[42]->meta['_bactive_paymongo_settlement_pending'] ?? '', 'ambiguous effects retain exact settlement marker');
same('payment_effects_ambiguous', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'ambiguous effects persist exact review code');
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'ambiguous effects record exactly one review incident');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'ambiguous effects drain new PayMongo checkout');
same(1, $fake_hook_calls['woocommerce_pre_payment_complete'] ?? 0, 'crashing downstream hook begins exactly once');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'crash before status replay emits zero paid status hooks');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'crash before payment-complete replay emits zero completion hooks');
check(
    !isset($fake_options[test_claim_option('payment', 'pay_effects_ambiguous_123')])
        && !isset($fake_options[test_claim_option('event', 'evt_effects_ambiguous_123')]),
    'ambiguous effects release provider claims for safe redelivery'
);

$ambiguous_retry_order = clone $fake_orders[42];
$review_incidents_before_automatic_retry = (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0);
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $ambiguous_retry_result = $process_claimed_payment->invoke(null, $ambiguous_retry_order, $ambiguous_validated, false);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('retry', $ambiguous_retry_result, 'automatic retry refuses ambiguous effect replay');
same('processing', $fake_options[$ambiguous_effect_option]['status'] ?? '', 'automatic retry leaves processing effect untouched');
same(
    $review_incidents_before_automatic_retry,
    (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0),
    'automatic retry does not duplicate the durable order review incident'
);
same(0, $fake_hook_calls['woocommerce_pre_payment_complete'] ?? 0, 'automatic retry never re-emits pre-payment hook');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'automatic retry never re-emits status hook');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'automatic retry never re-emits completion hook');

$clean_ambiguous_order = clone $fake_orders[42];
$clean_ambiguous_options = $fake_options;
$exact_ambiguous_session = session();
$exact_ambiguous_session['attributes']['payments'][0]['id'] = 'pay_effects_ambiguous_123';

$outstanding_ambiguity = clone $clean_ambiguous_order;
$outstanding_ambiguity->meta['_bactive_paymongo_attempts'][] = array(
    'generation' => 2,
    'session_id' => 'cs_later_outstanding_456',
    'mode' => 'test',
    'reference' => 'BA-42-2',
    'correlation_id' => 'later-outstanding-correlation',
);
$fake_orders = array(42 => $outstanding_ambiguity);
$fake_options = $clean_ambiguous_options;
$operator_remote_calls = 0;
$fake_remote_handler = static function (string $url, array $args) use (&$operator_remote_calls): WP_Error {
    ++$operator_remote_calls;
    return new WP_Error('unexpected_get', 'Outstanding attempts must block before provider GET.');
};
check(Order_Lock::acquire(42), 'outstanding-attempt ambiguity test acquires order fence');
try {
    $outstanding_resolution = Webhook::resolve_ambiguous_effects($outstanding_ambiguity);
} finally {
    Order_Lock::release(42);
}
check(!$outstanding_resolution, 'later outstanding attempt blocks effects acknowledgement');
same(0, $operator_remote_calls, 'later outstanding attempt blocks before provider retrieval');
same('payment_effects_ambiguous', $outstanding_ambiguity->meta[Reconciler::UNRESOLVED_META] ?? '', 'blocked outstanding resolution preserves exact review marker');
same('processing', $fake_options[$ambiguous_effect_option]['status'] ?? '', 'blocked outstanding resolution preserves processing effect');

$collision_ambiguity = clone $clean_ambiguous_order;
$collision_ambiguity->meta['_bactive_paymongo_attempts'][] = array(
    'generation' => 2,
    'session_id' => 'cs_later_paid_456',
    'mode' => 'test',
    'reference' => 'BA-42-2',
    'correlation_id' => 'later-paid-correlation',
    'payment_id' => 'pay_conflicting_later_456',
    'paid_event_id' => 'evt_conflicting_later_456',
    'paid_at' => time(),
);
$fake_orders = array(42 => $collision_ambiguity);
$fake_options = $clean_ambiguous_options;
$fake_remote_handler = static function (string $url, array $args) use ($exact_ambiguous_session): array {
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => $exact_ambiguous_session)),
    );
};
check(Order_Lock::acquire(42), 'identity-collision ambiguity test acquires order fence');
try {
    $collision_resolution = Webhook::resolve_ambiguous_effects($collision_ambiguity);
} finally {
    Order_Lock::release(42);
}
check(!$collision_resolution, 'later conflicting paid identity blocks effects acknowledgement');
same('payment_effects_ambiguous', $collision_ambiguity->meta[Reconciler::UNRESOLVED_META] ?? '', 'identity collision preserves exact review marker');
same('processing', $fake_options[$ambiguous_effect_option]['status'] ?? '', 'identity collision preserves processing effect');

$invalid_ambiguity = clone $clean_ambiguous_order;
$fake_orders = array(42 => $invalid_ambiguity);
$fake_options = $clean_ambiguous_options;
$fake_remote_handler = static fn(string $url, array $args): WP_Error => new WP_Error('provider_unavailable', 'retry');
check(Order_Lock::acquire(42), 'invalid-readback ambiguity test acquires order fence');
try {
    $invalid_resolution = Webhook::resolve_ambiguous_effects($invalid_ambiguity);
} finally {
    Order_Lock::release(42);
}
check(!$invalid_resolution, 'failed provider readback cannot acknowledge ambiguous effects');
same('payment_effects_ambiguous', $invalid_ambiguity->meta[Reconciler::UNRESOLVED_META] ?? '', 'failed provider readback preserves review marker');
same('processing', $fake_options[$ambiguous_effect_option]['status'] ?? '', 'failed provider readback preserves processing effect');

$resolved_ambiguity = clone $clean_ambiguous_order;
$fake_orders = array(42 => $resolved_ambiguity);
$fake_options = $clean_ambiguous_options;
$fake_hook_calls = array();
$fake_remote_handler = static function (string $url, array $args) use ($exact_ambiguous_session): array {
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => $exact_ambiguous_session)),
    );
};
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'operator effects acknowledgement acquires order fence');
try {
    $operator_resolution = Webhook::resolve_ambiguous_effects($resolved_ambiguity);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_remote_handler = null;
}
check($operator_resolution, 'exact provider readback permits explicit effects acknowledgement');
same('', $fake_orders[42]->meta['_bactive_paymongo_settlement_pending'] ?? '', 'operator acknowledgement clears exact settlement marker');
same('', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'operator acknowledgement clears exact review marker');
same('done', $fake_options[$ambiguous_effect_option]['status'] ?? '', 'operator acknowledgement closes effects record');
same('operator_verified_no_reemit', $fake_options[$ambiguous_effect_option]['resolution'] ?? '', 'operator acknowledgement records no-replay resolution');
same(0, $fake_hook_calls['woocommerce_pre_payment_complete'] ?? 0, 'operator acknowledgement emits no pre-payment hook');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'operator acknowledgement emits no status hook');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'operator acknowledgement emits no payment-complete hook');

// Quarantine acknowledgement is conditional on two independent durable
// writes: the global evidence record and the exact on-hold order state. A
// swallowed WooCommerce save keeps claims retryable and emits no stock/status
// or payment effects; the global incident keeps the gateway closed.
$swallowed_quarantine_validated = $validated_payment;
$swallowed_quarantine_validated['payment_id'] = 'pay_swallowed_quarantine_123';
$swallowed_quarantine_validated['event_id'] = 'evt_swallowed_quarantine_123';
$swallowed_quarantine_order = new WC_Order();
$swallowed_quarantine_order->payment_method = 'cod';
$swallowed_quarantine_order->throw_on_save_attempt = 1;
$fake_orders = array(42 => clone $swallowed_quarantine_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $swallowed_quarantine_result = $process_claimed_payment->invoke(
        null,
        $swallowed_quarantine_order,
        $swallowed_quarantine_validated,
        false
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$swallowed_quarantine_option = test_quarantine_option('evt_swallowed_quarantine_123');
same('retry', $swallowed_quarantine_result, 'swallowed unexpected-payment hold keeps both provider claims retryable');
same('pending', $fake_orders[42]->status, 'swallowed unexpected-payment hold cannot persist an on-hold status');
same(false, $fake_orders[42]->paid, 'swallowed unexpected-payment hold cannot mark the database order paid');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'swallowed unexpected-payment hold emits zero on-hold hooks');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'swallowed unexpected-payment hold emits zero payment-complete hooks');
check(is_array($fake_options[$swallowed_quarantine_option] ?? null), 'swallowed unexpected-payment hold retains durable global evidence');
check(empty($fake_options[$swallowed_quarantine_option]['order_annotated']), 'swallowed unexpected-payment evidence remains explicitly unannotated');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'swallowed unexpected-payment hold drains checkout');
check(
    !isset($fake_options[test_claim_option('payment', 'pay_swallowed_quarantine_123')])
        && !isset($fake_options[test_claim_option('event', 'evt_swallowed_quarantine_123')]),
    'swallowed unexpected-payment hold releases exact provider claims'
);

$quarantine_retrieved_method = new ReflectionMethod(Webhook::class, 'quarantine_retrieved_payment');
$quarantine_retrieved_method->setAccessible(true);
$retrieved_swallow_order = new WC_Order();
$retrieved_swallow_order->throw_on_save_attempt = 1;
$retrieved_swallow_session = session();
$retrieved_swallow_session['attributes']['payments'][0]['id'] = 'pay_retrieved_swallow_123';
$fake_orders = array(42 => clone $retrieved_swallow_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $retrieved_swallow_result = $quarantine_retrieved_method->invoke(
        null,
        $retrieved_swallow_order,
        $retrieved_swallow_session,
        'evt_retrieved_swallow_123',
        'cs_test_session_123',
        'amount_mismatch',
        false
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$retrieved_swallow_option = test_quarantine_option('evt_retrieved_swallow_123');
check(!$retrieved_swallow_result, 'swallowed authenticated-retrieval hold is not acknowledged');
same('pending', $fake_orders[42]->status, 'swallowed authenticated-retrieval hold leaves persisted status unchanged');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'swallowed authenticated-retrieval hold emits zero status hooks');
check(is_array($fake_options[$retrieved_swallow_option] ?? null), 'swallowed authenticated-retrieval hold preserves durable evidence');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'swallowed authenticated-retrieval hold drains checkout');
check(isset($fake_scheduled[Reconciler::ORDER_HOOK . '|' . serialize(array(42))]), 'swallowed authenticated-retrieval hold remains scheduled');

$generic_quarantine_order = new WC_Order();
$generic_quarantine_order->meta['_bactive_paymongo_attempts'] = array();
$fake_orders = array(42 => clone $generic_quarantine_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $generic_quarantine_result = $quarantine_method->invoke(
        null,
        'session_not_authorized',
        'evt_generic_quarantine_123',
        'cs_unauthorized_123',
        42,
        '',
        'local'
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$generic_quarantine_option = test_quarantine_option('evt_generic_quarantine_123', 'local');
$generic_review_effect = test_effects_option('review', 'evt_generic_quarantine_123', 'local');
check($generic_quarantine_result, 'session-not-authorized quarantine succeeds only after exact order readback');
same('on-hold', $fake_orders[42]->status, 'session-not-authorized quarantine durably holds the order');
same('session_not_authorized', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'session-not-authorized quarantine persists exact review code');
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'session-not-authorized quarantine records one incident');
same(true, (bool) ($fake_options[$generic_quarantine_option]['order_annotated'] ?? false), 'session-not-authorized quarantine independently annotates global evidence');
same('done', $fake_options[$generic_review_effect]['status'] ?? '', 'session-not-authorized on-hold effect closes exactly once');

$generic_failure_order = new WC_Order();
$generic_failure_order->meta['_bactive_paymongo_attempts'] = array();
$generic_failure_order->throw_on_save_attempt = 1;
$fake_orders = array(42 => clone $generic_failure_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $generic_failure_result = $quarantine_method->invoke(
        null,
        'session_not_authorized',
        'evt_generic_quarantine_fail_123',
        'cs_unauthorized_fail_123',
        42,
        '',
        'local'
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check(!$generic_failure_result, 'session-not-authorized quarantine returns retry on swallowed order save');
same('pending', $fake_orders[42]->status, 'failed session-not-authorized quarantine cannot persist on-hold');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'failed session-not-authorized quarantine emits zero status effects');
same('quarantine_persist_failed', $fake_options['bactive_paymongo_disable_drain_error']['code'] ?? '', 'failed session-not-authorized quarantine records exact global incident');
check(isset($fake_scheduled[Reconciler::ORDER_HOOK . '|' . serialize(array(42))]), 'failed session-not-authorized quarantine remains scheduled');

// A second payment identity can never overwrite the first observed provider
// facts. It is quarantined under its own durable identity; if that quarantine
// cannot persist, both claims remain retryable and the gateway drains.
$collision_first_payment = 'pay_collision_first_123';
$collision_first_event = 'evt_collision_first_123';
$collision_second_validated = $validated_payment;
$collision_second_validated['payment_id'] = 'pay_collision_second_456';
$collision_second_validated['event_id'] = 'evt_collision_second_456';
$collision_order = new WC_Order();
$collision_order->transaction_id = $collision_first_payment;
$collision_order->meta['_bactive_paymongo_settlement_pending'] = $collision_first_payment;
$collision_order->meta['_bactive_paymongo_paid_event_id'] = $collision_first_event;
$collision_order->meta['_bactive_paymongo_paid_session_id'] = 'cs_test_session_123';
$collision_order->meta['_bactive_paymongo_source_method'] = 'qrph';
$collision_order->meta['_bactive_paymongo_source_provider'] = '';
$collision_order->meta['_bactive_paymongo_attempts'][0]['payment_id'] = $collision_first_payment;
$collision_order->meta['_bactive_paymongo_attempts'][0]['paid_event_id'] = $collision_first_event;
$collision_order->meta['_bactive_paymongo_attempts'][0]['paid_at'] = time();
$fake_orders = array(42 => clone $collision_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $collision_result = $process_claimed_payment->invoke(null, $collision_order, $collision_second_validated, false);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$collision_quarantine_option = test_quarantine_option('evt_collision_second_456');
same('processed', $collision_result, 'second payment identity is durably quarantined without provider retry');
same('on-hold', $fake_orders[42]->status, 'second payment identity pauses fulfillment');
same('payment_identity_collision', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'second payment identity stores exact collision code');
same($collision_first_payment, $fake_orders[42]->transaction_id, 'second payment cannot overwrite first transaction ID');
same($collision_first_payment, $fake_orders[42]->meta['_bactive_paymongo_settlement_pending'] ?? '', 'second payment cannot overwrite first settlement marker');
same($collision_first_event, $fake_orders[42]->meta['_bactive_paymongo_paid_event_id'] ?? '', 'second payment cannot overwrite first event fact');
same($collision_first_payment, $fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['payment_id'] ?? '', 'second payment cannot overwrite first attempt fact');
same('pay_collision_second_456', $fake_options[$collision_quarantine_option]['payment_id'] ?? '', 'collision evidence retains second payment identity separately');
same(true, (bool) ($fake_options[$collision_quarantine_option]['order_annotated'] ?? false), 'collision evidence is independently linked to held order');

$failed_collision_validated = $collision_second_validated;
$failed_collision_validated['payment_id'] = 'pay_collision_failed_789';
$failed_collision_validated['event_id'] = 'evt_collision_failed_789';
$failed_collision_order = clone $collision_order;
$fake_orders = array(42 => clone $failed_collision_order);
$fake_options = array();
$fake_scheduled = array();
$failed_collision_quarantine = test_quarantine_option('evt_collision_failed_789');
$fake_option_add_failures = array($failed_collision_quarantine);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $failed_collision_result = $process_claimed_payment->invoke(
        null,
        $failed_collision_order,
        $failed_collision_validated,
        false
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_option_add_failures = array();
}
same('retry', $failed_collision_result, 'collision remains retryable when durable quarantine storage fails');
same($collision_first_payment, $fake_orders[42]->transaction_id, 'failed collision quarantine preserves first transaction');
same($collision_first_payment, $fake_orders[42]->meta['_bactive_paymongo_settlement_pending'] ?? '', 'failed collision quarantine preserves first settlement marker');
same($collision_first_event, $fake_orders[42]->meta['_bactive_paymongo_paid_event_id'] ?? '', 'failed collision quarantine preserves first event');
same($collision_first_payment, $fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['payment_id'] ?? '', 'failed collision quarantine preserves first attempt');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'failed collision quarantine drains checkout');
check(
    !isset($fake_options[test_claim_option('payment', 'pay_collision_failed_789')])
        && !isset($fake_options[test_claim_option('event', 'evt_collision_failed_789')]),
    'failed collision quarantine releases second-payment claims'
);

// PayMongo may redeliver the same payment through a different event identity.
// Once the payment identity is proven equal, retain the first canonical event
// as audit history and complete settlement exactly once.
$same_payment_id = 'pay_same_payment_123';
$same_payment_first_event = 'evt_same_payment_first_123';
$same_payment_retry = $validated_payment;
$same_payment_retry['payment_id'] = $same_payment_id;
$same_payment_retry['event_id'] = 'evt_same_payment_retry_456';
$same_payment_order = new WC_Order();
$same_payment_order->meta['_bactive_paymongo_paid_event_id'] = $same_payment_first_event;
$same_payment_order->meta['_bactive_paymongo_paid_session_id'] = 'cs_test_session_123';
$same_payment_order->meta['_bactive_paymongo_paid_mode'] = 'test';
$same_payment_order->meta['_bactive_paymongo_source_method'] = 'qrph';
$same_payment_order->meta['_bactive_paymongo_source_provider'] = '';
$same_payment_order->meta['_bactive_paymongo_attempts'][0]['payment_id'] = $same_payment_id;
$same_payment_order->meta['_bactive_paymongo_attempts'][0]['paid_event_id'] = $same_payment_first_event;
$same_payment_order->meta['_bactive_paymongo_attempts'][0]['paid_at'] = time();
$fake_orders = array(42 => clone $same_payment_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $same_payment_result = $process_claimed_payment->invoke(null, $same_payment_order, $same_payment_retry, false);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('processed', $same_payment_result, 'same payment under a later event identity settles successfully');
same(true, $fake_orders[42]->paid, 'same-payment event retry persists paid state');
same($same_payment_id, $fake_orders[42]->transaction_id, 'same-payment event retry persists exact transaction');
same($same_payment_first_event, $fake_orders[42]->meta['_bactive_paymongo_paid_event_id'] ?? '', 'same-payment event retry retains first canonical event');
same($same_payment_first_event, $fake_orders[42]->meta['_bactive_paymongo_attempts'][0]['paid_event_id'] ?? '', 'same-payment event retry retains first attempt event');
same(1, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'same-payment event retry emits completion effects exactly once');

// Review/on-hold effects use the same at-most-once boundary. Cover crashes
// before replay, during replay, and after replay but before the done marker.
$review_armed_order = new WC_Order();
$review_armed_order->meta['_bactive_paymongo_attempts'] = array();
$review_armed_order->throw_on_save_attempt = 2;
$fake_orders = array(42 => clone $review_armed_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $review_armed_result = $quarantine_method->invoke(
        null,
        'session_not_authorized',
        'evt_review_armed_123',
        'cs_review_armed_123',
        42,
        '',
        'local'
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$review_armed_effect = test_effects_option('review', 'evt_review_armed_123', 'local');
check(!$review_armed_result, 'review crash before status persistence is not acknowledged');
same('armed', $fake_options[$review_armed_effect]['status'] ?? '', 'review crash before replay leaves effect armed');
same('pending', $fake_orders[42]->status, 'review crash before replay leaves database status pending');
same('session_not_authorized', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'review crash before replay retains exact markers');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'review crash before replay emits zero on-hold effects');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'review crash before replay drains checkout');

$review_armed_retry_order = clone $fake_orders[42];
$review_armed_retry_order->throw_on_save_attempt = -1;
$fake_orders = array(42 => $review_armed_retry_order);
$fake_hook_calls = array();
$review_incidents_before_armed_retry = (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $review_armed_retry_result = $quarantine_method->invoke(
        null,
        'session_not_authorized',
        'evt_review_armed_123',
        'cs_review_armed_123',
        42,
        '',
        'local'
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($review_armed_retry_result, 'armed review effect resumes safely on matching provider retry');
same('done', $fake_options[$review_armed_effect]['status'] ?? '', 'matching retry emits and closes armed review effect');
same('on-hold', $fake_orders[42]->status, 'matching retry durably reaches on-hold');
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'matching retry retains one review incident');
same(
    $review_incidents_before_armed_retry,
    (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0),
    'matching retry never recounts an existing durable order quarantine'
);
same(1, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'matching retry emits armed on-hold effect exactly once');

$review_during_order = new WC_Order();
$review_during_order->meta['_bactive_paymongo_attempts'] = array();
$fake_orders = array(42 => clone $review_during_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$review_during_thrower = static function ($order_id, $order, $transition): void {
    throw new RuntimeException('simulated review transition effect failure');
};
add_action('woocommerce_order_status_on-hold', $review_during_thrower, 10, 3);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $review_during_result = $quarantine_method->invoke(
        null,
        'session_not_authorized',
        'evt_review_during_123',
        'cs_review_during_123',
        42,
        '',
        'local'
    );
} finally {
    remove_action('woocommerce_order_status_on-hold', $review_during_thrower, 10);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$review_during_effect = test_effects_option('review', 'evt_review_during_123', 'local');
check(!$review_during_result, 'review crash during replay is not acknowledged');
same('processing', $fake_options[$review_during_effect]['status'] ?? '', 'review crash during replay remains durably processing');
same('on-hold', $fake_orders[42]->status, 'review crash during replay retains persisted on-hold state');
same(1, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'review crash occurs after exactly one on-hold hook begins');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'review crash prevents later status-changed hook');

// Real WooCommerce catches Exception inside WC_Order::status_transition().
// The plugin must emit the armed action sequence outside that swallowing
// wrapper or a partially failed extension hook would be falsely marked done.
$review_swallowing_order = new Fake_Swallowing_Status_Order();
$review_swallowing_order->meta['_bactive_paymongo_attempts'] = array();
$fake_orders = array(42 => clone $review_swallowing_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$review_swallowing_thrower = static function ($order_id, $order, $transition): void {
    throw new RuntimeException('simulated extension failure swallowed by WooCommerce core');
};
add_action('woocommerce_order_status_on-hold', $review_swallowing_thrower, 10, 3);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $review_swallowing_result = $quarantine_method->invoke(
        null,
        'session_not_authorized',
        'evt_review_swallowing_123',
        'cs_review_swallowing_123',
        42,
        '',
        'local'
    );
} finally {
    remove_action('woocommerce_order_status_on-hold', $review_swallowing_thrower, 10);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$review_swallowing_effect = test_effects_option('review', 'evt_review_swallowing_123', 'local');
check(!$review_swallowing_result, 'Woo-style swallowed hook failure is not acknowledged');
same('processing', $fake_options[$review_swallowing_effect]['status'] ?? '', 'Woo-style swallowed hook failure leaves effect processing');
same('on-hold', $fake_orders[42]->status, 'Woo-style swallowed hook failure retains durable status');
same(1, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'Woo-style swallowed hook failure begins first action once');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'Woo-style swallowed hook failure never reaches changed action');

$review_after_order = new WC_Order();
$review_after_order->meta['_bactive_paymongo_attempts'] = array();
$fake_orders = array(42 => clone $review_after_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$review_after_effect = test_effects_option('review', 'evt_review_after_123', 'local');
$fake_option_update_handler = static function (string $key, $value) use ($review_after_effect): string {
    return $key === $review_after_effect
        && is_array($value)
        && ($value['status'] ?? '') === 'done'
            ? 'swallow'
            : '';
};
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $review_after_result = $quarantine_method->invoke(
        null,
        'session_not_authorized',
        'evt_review_after_123',
        'cs_review_after_123',
        42,
        '',
        'local'
    );
} finally {
    $fake_option_update_handler = null;
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$review_after_quarantine = test_quarantine_option('evt_review_after_123', 'local');
check(!$review_after_result, 'review crash after replay but before done readback is not acknowledged');
same('processing', $fake_options[$review_after_effect]['status'] ?? '', 'review crash after replay remains durably processing');
same('on-hold', $fake_orders[42]->status, 'review crash after replay retains exact on-hold state');
same(1, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'review crash after replay emits on-hold hook once');
same(1, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'review crash after replay reaches final status hook once');
check(empty($fake_options[$review_after_quarantine]['order_annotated']), 'review crash before done leaves quarantine explicitly unannotated');

$review_after_order_snapshot = clone $fake_orders[42];
$review_after_options_snapshot = $fake_options;
$invalid_review_order = clone $review_after_order_snapshot;
$invalid_review_order->meta['_bactive_paymongo_review_effect_session_id'] = 'cs_unrelated_review_456';
$fake_orders = array(42 => $invalid_review_order);
$fake_options = $review_after_options_snapshot;
$fake_hook_calls = array();
check(Order_Lock::acquire(42), 'unrelated review-resolution test acquires order fence');
try {
    $invalid_review_resolution = Webhook::resolve_review_for_operator($invalid_review_order);
} finally {
    Order_Lock::release(42);
}
check(!$invalid_review_resolution, 'unrelated review effect context cannot be acknowledged');
same('session_not_authorized', $invalid_review_order->meta[Reconciler::UNRESOLVED_META] ?? '', 'unrelated review context preserves exact marker');
same('processing', $fake_options[$review_after_effect]['status'] ?? '', 'unrelated review context preserves processing effect');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'blocked review acknowledgement emits no on-hold hook');

$exact_review_order = clone $review_after_order_snapshot;
$fake_orders = array(42 => $exact_review_order);
$fake_options = $review_after_options_snapshot;
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'exact review-resolution test acquires order fence');
try {
    $exact_review_resolution = Webhook::resolve_review_for_operator($exact_review_order);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($exact_review_resolution, 'exact quarantine and order facts permit explicit review acknowledgement');
same('', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'exact review acknowledgement clears only matching marker');
same('', $fake_orders[42]->meta['_bactive_paymongo_review_effect_identity'] ?? '', 'exact review acknowledgement clears effect association');
same('done', $fake_options[$review_after_effect]['status'] ?? '', 'exact review acknowledgement closes processing effect');
same('operator_verified_no_reemit', $fake_options[$review_after_effect]['resolution'] ?? '', 'exact review acknowledgement records no-replay resolution');
same(true, (bool) ($fake_options[$review_after_quarantine]['order_annotated'] ?? false), 'exact review acknowledgement links global quarantine evidence');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'exact review acknowledgement never re-emits on-hold hook');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'exact review acknowledgement never re-emits status-changed hook');

// A signed customer cancel races with payment. If the independent readback is
// paid and the payment is quarantined, the cancel path must preserve that exact
// incident/effect identity rather than replacing it with a generic expiry code.
$cancel_paid_order = new WC_Order();
$cancel_paid_order->payment_method = 'cod';
$cancel_paid_order->meta['_bactive_paymongo_attempts'][0]['generation'] = 1;
$cancel_paid_order->meta['_bactive_paymongo_attempts'][0]['created_at'] = time();
$cancel_paid_order->meta['_bactive_paymongo_attempts'][0]['fingerprint'] = hash('sha256', 'cancel-paid-order-42');
$cancel_paid_order->meta['_bactive_paymongo_attempts'][0]['idempotency_key'] = 'bactive-checkout-test-42-cancel-paid';
$cancel_paid_order->meta['_bactive_paymongo_attempts'][0]['config_generation'] = 1;
$cancel_paid_order->meta['_bactive_paymongo_attempts'][0]['request_started_at'] = $cancel_paid_order->meta['_bactive_paymongo_attempts'][0]['created_at'];
$cancel_paid_attempts = Gateway::order_attempts($cancel_paid_order);
$cancel_paid_fingerprint = $attempt_fingerprint->invoke(null, $cancel_paid_attempts[0]);
check((bool) preg_match('/^[a-f0-9]{64}$/D', $cancel_paid_fingerprint), 'paid-during-cancel fixture has an exact immutable attempt fingerprint');
$cancel_paid_session = session();
$cancel_paid_session['attributes']['payments'][0]['id'] = 'pay_cancel_race_paid_123';
$fake_orders = array(42 => clone $cancel_paid_order);
$fake_options = array(
    'woocommerce_bactive_paymongo_settings' => array(
        'test_mode' => 'yes',
        'test_secret_key' => Secrets::encrypt('sk_test_cancel_paid_race_123456789'),
    ),
);
$fake_hook_calls = array();
$cancel_paid_provider_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$cancel_paid_provider_calls, $cancel_paid_session): array {
    $cancel_paid_provider_calls[] = $url;
    if (str_ends_with($url, '/expire')) {
        return array('response' => array('code' => 200), 'body' => '');
    }
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => $cancel_paid_session)),
    );
};
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'paid-during-cancel regression acquires order fence');
try {
    $cancel_paid_args = array($cancel_paid_order, &$cancel_paid_attempts, true, $cancel_paid_fingerprint);
    $cancel_paid_result = $expire_attempts->invokeArgs(new Gateway(false), $cancel_paid_args);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_remote_handler = null;
}
same(false, $cancel_paid_result['verified'] ?? true, 'paid-during-cancel is never classified as safely expired');
same(true, $cancel_paid_result['settlement'] ?? false, 'paid-during-cancel enters settlement/quarantine path');
same(2, count($cancel_paid_provider_calls), 'paid-during-cancel performs expire plus independent readback');
same('on-hold', $fake_orders[42]->status, 'paid-during-cancel durably pauses fulfillment');
same('paid_after_payment_method_changed', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'paid-during-cancel persists exact quarantine reason');
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'paid-during-cancel records exactly one review incident');
$cancel_review_identity = (string) ($fake_orders[42]->meta['_bactive_paymongo_review_effect_identity'] ?? '');
check($cancel_review_identity !== '', 'paid-during-cancel retains exact review effect identity');
$cancel_review_effect_option = test_effects_option('review', $cancel_review_identity);
same('done', $fake_options[$cancel_review_effect_option]['status'] ?? '', 'paid-during-cancel closes its review transition effect once');

$cancel_preserve_method = new ReflectionMethod(Gateway::class, 'preserve_or_flag_unverified_cancel');
$cancel_preserve_method->setAccessible(true);
$cancel_final_order = clone $fake_orders[42];
$cancel_code_before = $cancel_final_order->meta[Reconciler::UNRESOLVED_META] ?? '';
$cancel_incidents_before = (int) ($cancel_final_order->meta['_bactive_paymongo_review_incidents'] ?? 0);
$cancel_effect_before = $fake_options[$cancel_review_effect_option] ?? array();
$cancel_preserve_method->invoke(
    new Gateway(false),
    $cancel_final_order,
    $cancel_paid_attempts,
    $cancel_paid_result,
    'cs_test_session_123'
);
same($cancel_code_before, $cancel_final_order->meta[Reconciler::UNRESOLVED_META] ?? '', 'cancel finalization preserves exact paid quarantine code');
same($cancel_code_before, $cancel_final_order->meta['_bactive_paymongo_review_required'] ?? '', 'cancel finalization preserves matching review marker');
same($cancel_incidents_before, (int) ($cancel_final_order->meta['_bactive_paymongo_review_incidents'] ?? 0), 'cancel finalization does not duplicate review incident');
same($cancel_review_identity, $cancel_final_order->meta['_bactive_paymongo_review_effect_identity'] ?? '', 'cancel finalization preserves effect association');
same($cancel_effect_before, $fake_options[$cancel_review_effect_option] ?? array(), 'cancel finalization leaves exact effect record unchanged');
check(
    !isset($fake_options[Reconciler::review_incident_option(42, 'session_cancel_expiry_unverified', 'test')]),
    'cancel finalization creates no generic overwrite review'
);

// A first review-marker save can fail after the immutable quarantine evidence
// is durable. Matching retries repair only the missing order incident link,
// preserve the original evidence, and remain operator-resolvable.
$repair_validated = $validated_payment;
$repair_validated['payment_id'] = 'pay_quarantine_repair_123';
$repair_validated['event_id'] = 'evt_quarantine_repair_123';
$repair_order = new WC_Order();
$repair_order->payment_method = 'cod';
$repair_order->throw_on_save_attempt = 1;
$fake_orders = array(42 => clone $repair_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $repair_first_result = $process_claimed_payment->invoke(null, $repair_order, $repair_validated, false);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$repair_quarantine_option = test_quarantine_option('evt_quarantine_repair_123');
$repair_quarantine_recorded_at = (int) ($fake_options[$repair_quarantine_option]['recorded_at'] ?? 0);
same('retry', $repair_first_result, 'first quarantine marker-save failure remains retryable');
check($repair_quarantine_recorded_at > 0, 'first quarantine marker-save failure retains immutable provider evidence');
same('test', $fake_options[$repair_quarantine_option]['mode'] ?? '', 'first quarantine evidence is isolated to test mode');
same(0, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'first quarantine marker-save failure leaves no phantom order count');
same('', $fake_orders[42]->meta['_bactive_paymongo_settlement_pending'] ?? '', 'quarantine failure is not misclassified as apply-settlement recovery');

$repair_retry_order = clone $fake_orders[42];
$repair_retry_order->throw_on_save_attempt = -1;
$fake_orders = array(42 => $repair_retry_order);
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $repair_retry_result = $process_claimed_payment->invoke(null, $repair_retry_order, $repair_validated, false);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same('processed', $repair_retry_result, 'matching retry completes failed quarantine hold');
same('on-hold', $fake_orders[42]->status, 'matching quarantine retry durably holds order');
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'matching quarantine retry repairs missing order incident exactly once');
same($repair_quarantine_recorded_at, (int) ($fake_options[$repair_quarantine_option]['recorded_at'] ?? 0), 'matching quarantine retry preserves the original evidence timestamp');
same(true, (bool) ($fake_options[$repair_quarantine_option]['order_annotated'] ?? false), 'matching quarantine retry links the original evidence to the order');
same('', $fake_orders[42]->meta['_bactive_paymongo_settlement_pending'] ?? '', 'successful quarantine retry carries no settlement marker');

$review_resolution_source_order = clone $fake_orders[42];
$review_resolution_source_options = $fake_options;
$review_resolution_option = test_review_resolution_option(42);

// The first operator action is itself a two-phase operation. Simulate a CPT
// metadata tear that persists the target fingerprint and every review-marker
// deletion but loses the target pending marker. The external intent plus the
// retained REQUIRED marker must keep the exact action recoverable and block all
// stale-save, deletion, settings-rotation, drain, and deactivation paths.
$fake_orders = array(42 => clone $review_resolution_source_order);
$fake_options = $review_resolution_source_options;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$fake_persist_order_filter = static function (WC_Order $persisted): WC_Order {
    global $fake_persist_order_filter;
    $persisted->delete_meta_data('_bactive_paymongo_resolved_payment_pending');
    $fake_persist_order_filter = null;
    return $persisted;
};
check(Order_Lock::acquire(42), 'fingerprint-only review tear acquires order fence');
try {
    $review_fingerprint_tear_result = Webhook::resolve_review_for_operator(clone $fake_orders[42]);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_persist_order_filter = null;
}
$review_fingerprint_torn = $fake_orders[42];
check(!$review_fingerprint_tear_result, 'fingerprint-only first review save is not acknowledged');
same('', $review_fingerprint_torn->meta[Reconciler::UNRESOLVED_META] ?? '', 'fingerprint-only tear may delete unresolved marker');
same('', $review_fingerprint_torn->meta['_bactive_paymongo_review_required'] ?? '', 'fingerprint-only tear may delete review marker');
same('', $review_fingerprint_torn->meta['_bactive_paymongo_review_incidents'] ?? '', 'fingerprint-only tear may delete order incident count');
same('yes', $review_fingerprint_torn->meta[Reconciler::REQUIRED_META] ?? '', 'fingerprint-only tear retains enumerable recovery marker');
check(
    preg_match('/^[a-f0-9]{64}$/D', (string) ($review_fingerprint_torn->meta['_bactive_paymongo_resolved_evidence_fingerprint'] ?? '')) === 1,
    'fingerprint-only tear may persist exact resolved fingerprint'
);
same('', $review_fingerprint_torn->meta['_bactive_paymongo_resolved_payment_pending'] ?? '', 'fingerprint-only tear exposes missing target pending half');
same('processing', $fake_options[$review_resolution_option]['status'] ?? '', 'fingerprint-only tear retains external processing intent');
same('test', $fake_options[$review_resolution_option]['mode'] ?? '', 'fingerprint-only tear retains its exact test-mode intent');
check(Webhook::review_resolution_recovery_pending($review_fingerprint_torn), 'fingerprint-only tear remains drain-active');
check(Webhook::review_resolution_recovery_action_available($review_fingerprint_torn), 'fingerprint-only tear keeps review recovery action available');
check(!Webhook::resolved_payment_disposition_action_available($review_fingerprint_torn), 'fingerprint-only tear cannot expose second operator action early');
check(Gateway::has_protected_payment_state($review_fingerprint_torn), 'external review intent protects torn order state');
$review_torn_actions = Reconciler::order_actions(array(), $review_fingerprint_torn);
check(isset($review_torn_actions['bactive_paymongo_resolve_review']), 'marker-deleted tear exposes labeled review recovery action');
check(!isset($review_torn_actions['bactive_paymongo_finalize_resolved_payment']), 'marker-deleted tear hides payment disposition action');

$stale_review_save = new Fake_Catching_Status_Order();
$stale_review_save->status = $review_fingerprint_torn->status;
$stale_review_save->paid = $review_fingerprint_torn->paid;
$stale_review_save->payment_method = 'bacs';
$stale_review_save->payment_method_title = $review_fingerprint_torn->payment_method_title;
$stale_review_save->currency = $review_fingerprint_torn->currency;
$stale_review_save->total = $review_fingerprint_torn->total;
$stale_review_save->transaction_id = $review_fingerprint_torn->transaction_id;
$stale_review_save->date_paid = $review_fingerprint_torn->date_paid;
$stale_review_save->meta = $review_fingerprint_torn->meta;
$stale_review_save->changes = array('payment_method' => $review_fingerprint_torn->payment_method);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$stale_review_save->save();
$fake_before_order_save = null;
check($stale_review_save->save_error_caught, 'active review intent blocks a stale core payment-method save');
same('cod', $fake_orders[42]->payment_method, 'stale save cannot mutate review-recovery core facts');
same(false, $lifecycle_test_gateway->guard_order_deletion(null, $fake_orders[42], false), 'active review intent blocks order deletion preflight');
$review_delete_action_blocked = false;
try {
    $lifecycle_test_gateway->block_unsafe_delete_action(42, $fake_orders[42]);
} catch (RuntimeException $error) {
    $review_delete_action_blocked = true;
}
check($review_delete_action_blocked, 'active review intent blocks destructive order action');
check(!Order_Lock::held_by_request(42), 'blocked review-intent deletion releases its order fence');

$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
Reconciler::run_order(42);
$fake_before_order_save = null;
$fake_clone_order_reads = false;
$fake_persist_order_saves = false;
$review_fingerprint_torn = $fake_orders[42];
same('', $review_fingerprint_torn->meta[Reconciler::UNRESOLVED_META] ?? '', 'reconciler does not replace recoverable tear with generic inconsistency');
same('yes', $review_fingerprint_torn->meta[Reconciler::REQUIRED_META] ?? '', 'reconciler preserves enumerable review intent marker');
check(Webhook::review_resolution_recovery_action_available($review_fingerprint_torn), 'scheduled reconciliation preserves human recovery action');

$fake_options['woocommerce_bactive_paymongo_settings'] = array(
    'enabled' => 'yes',
    'test_mode' => 'yes',
    'test_secret_key' => Secrets::encrypt('sk_test_review_resolution_rotation_123456'),
    'live_secret_key' => '',
);
$fake_options['bactive_paymongo_draining'] = 'no';
$fake_options[Reconciler::CONFIG_GENERATION_OPTION] = 50;
$review_rotation_old = $fake_options['woocommerce_bactive_paymongo_settings'];
$review_rotation_candidate = $review_rotation_old;
$review_rotation_candidate['test_mode'] = 'no';
$review_rotation_candidate['live_secret_key'] = 'sk_live_review_resolution_rotation_123456';
$fake_order_query_ids = array(42);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$review_rotation_result = Gateway::filter_settings_update($review_rotation_candidate, $review_rotation_old);
$fake_before_order_save = null;
$fake_clone_order_reads = false;
$fake_persist_order_saves = false;
same($review_rotation_old, $review_rotation_result, 'active review intent rejects mode and credential rotation');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'rejected review-time rotation keeps checkout draining');
same('paymongo_active_sessions_remain', $fake_options['bactive_paymongo_disable_drain_error']['code'] ?? '', 'review-time rotation records exact active-state blocker');
same(51, (int) ($fake_options[Reconciler::CONFIG_GENERATION_OPTION] ?? 0), 'review-time rotation invalidates in-flight issuance');
check(!Order_Lock::settings_write_active(), 'rejected review-time rotation releases settings lease');

$review_deactivation_blocked = false;
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    Reconciler::guard_deactivation();
} catch (RuntimeException $error) {
    $review_deactivation_blocked = str_starts_with($error->getMessage(), 'wp_die:');
} finally {
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($review_deactivation_blocked, 'active review intent blocks plugin deactivation');
check(Webhook::review_resolution_recovery_pending($fake_orders[42]), 'deactivation attempt preserves review intent');

$fake_order_query_ids = array();
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'fingerprint-only review recovery reacquires order fence');
try {
    $review_fingerprint_recovery = Webhook::resolve_review_for_operator(clone $fake_orders[42]);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$review_fingerprint_recovered = $fake_orders[42];
check($review_fingerprint_recovery, 'fingerprint-only tear converges through same human action');
same('done', $fake_options[$review_resolution_option]['status'] ?? '', 'fingerprint-only recovery closes external intent after exact readback');
same('', $review_fingerprint_recovered->meta['_bactive_paymongo_review_incidents'] ?? '', 'fingerprint-only recovery durably clears the linked order incident');
$review_fingerprint_receipts = test_options_with_prefix($fake_options, 'bactive_paymongo_review_receipt_');
same(1, count($review_fingerprint_receipts), 'fingerprint-only recovery writes one immutable completion receipt');
same('done', reset($review_fingerprint_receipts)['status'] ?? '', 'fingerprint-only recovery completion receipt is final');
check(Webhook::resolved_payment_disposition_action_available($review_fingerprint_recovered), 'fingerprint-only recovery exposes second operator action only after completion');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'fingerprint-only tear and recovery emit no duplicate on-hold hook');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'fingerprint-only tear and recovery emit no status-change hook');

// Mirror the other metadata half: the pending marker may persist while the
// resolved fingerprint does not. This layout is ordinarily inconsistent, but
// the external first-action intent must prevent generic quarantine replacement.
$fake_orders = array(42 => clone $review_resolution_source_order);
$fake_options = $review_resolution_source_options;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$fake_persist_order_filter = static function (WC_Order $persisted): WC_Order {
    global $fake_persist_order_filter;
    $persisted->delete_meta_data('_bactive_paymongo_resolved_evidence_fingerprint');
    $fake_persist_order_filter = null;
    return $persisted;
};
check(Order_Lock::acquire(42), 'pending-only review tear acquires order fence');
try {
    $review_pending_tear_result = Webhook::resolve_review_for_operator(clone $fake_orders[42]);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_persist_order_filter = null;
}
$review_pending_torn = $fake_orders[42];
check(!$review_pending_tear_result, 'pending-only first review save is not acknowledged');
same('', $review_pending_torn->meta['_bactive_paymongo_resolved_evidence_fingerprint'] ?? '', 'pending-only tear exposes missing fingerprint half');
check(
    preg_match('/^[a-f0-9]{64}$/D', (string) ($review_pending_torn->meta['_bactive_paymongo_resolved_payment_pending'] ?? '')) === 1,
    'pending-only tear may persist exact target pending marker'
);
check(Gateway::has_inconsistent_provider_payment_state($review_pending_torn), 'pending-only layout is independently inconsistent');
check(Webhook::review_resolution_recovery_action_available($review_pending_torn), 'pending-only tear remains exactly recoverable');
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
Reconciler::run_order(42);
$fake_before_order_save = null;
$fake_clone_order_reads = false;
$fake_persist_order_saves = false;
same('', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'active intent suppresses generic inconsistency quarantine for pending-only tear');
check(Order_Lock::acquire(42), 'pending-only review recovery reacquires order fence');
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $review_pending_recovery = Webhook::resolve_review_for_operator(clone $fake_orders[42]);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($review_pending_recovery, 'pending-only tear converges through same human action');
same('done', $fake_options[$review_resolution_option]['status'] ?? '', 'pending-only recovery closes external intent');
same('', $fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? '', 'pending-only recovery durably clears the linked order incident');
$review_pending_receipts = test_options_with_prefix($fake_options, 'bactive_paymongo_review_receipt_');
same(1, count($review_pending_receipts), 'pending-only recovery writes one immutable completion receipt');

// A late extension mutation during the authorized target save is rejected by
// the exact state assertion. The pre-save intent leaves the untouched source
// order recoverable without any status effects.
$fake_orders = array(42 => clone $review_resolution_source_order);
$fake_options = $review_resolution_source_options;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$review_target_mutator = static function ($saving_order): void {
    if ($saving_order instanceof WC_Order && $saving_order->get_id() === 42) {
        $saving_order->update_meta_data('_bactive_paymongo_resolved_evidence_fingerprint', str_repeat('f', 64));
    }
};
add_action('woocommerce_before_order_object_save', $review_target_mutator, PHP_INT_MAX, 1);
check(Order_Lock::acquire(42), 'extension-mutated review resolution acquires order fence');
try {
    $review_mutation_result = Webhook::resolve_review_for_operator(clone $fake_orders[42]);
} finally {
    Order_Lock::release(42);
    remove_action('woocommerce_before_order_object_save', $review_target_mutator, PHP_INT_MAX);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check(!$review_mutation_result, 'extension-mutated first review target is not acknowledged');
same('processing', $fake_options[$review_resolution_option]['status'] ?? '', 'extension mutation retains external processing intent');
same('paid_after_payment_method_changed', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'extension mutation leaves stored source review authoritative');
check(Webhook::review_resolution_recovery_action_available($fake_orders[42]), 'extension-mutated first action remains recoverable');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'extension-mutated first action emits no status effects');
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'extension-mutated review retry reacquires order fence');
try {
    $review_mutation_recovery = Webhook::resolve_review_for_operator(clone $fake_orders[42]);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($review_mutation_recovery, 'extension-mutated first action converges original immutable target');
same('done', $fake_options[$review_resolution_option]['status'] ?? '', 'extension-mutated retry closes intent');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'extension-mutated retry emits no status effects');

// Exact order persistence followed by a lost final intent update must not
// release the count twice or rewrite the order on the retry.
$fake_orders = array(42 => clone $review_resolution_source_order);
$fake_options = $review_resolution_source_options;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$fake_option_update_handler = static function (string $key, $value) use ($review_resolution_option): string {
    return $key === $review_resolution_option
        && is_array($value)
        && ($value['status'] ?? '') === 'done'
            ? 'swallow'
            : '';
};
check(Order_Lock::acquire(42), 'finish-loss review resolution acquires order fence');
try {
    $review_finish_loss_result = Webhook::resolve_review_for_operator(clone $fake_orders[42]);
} finally {
    Order_Lock::release(42);
    $fake_option_update_handler = null;
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$review_finish_loss_order = $fake_orders[42];
check(!$review_finish_loss_result, 'lost review-intent final update is not acknowledged');
same('processing', $fake_options[$review_resolution_option]['status'] ?? '', 'lost final update retains processing intent');
$review_finish_loss_receipts = test_options_with_prefix($fake_options, 'bactive_paymongo_review_receipt_');
same(1, count($review_finish_loss_receipts), 'lost final update retains one immutable completion receipt');
same('done', reset($review_finish_loss_receipts)['status'] ?? '', 'lost final update receipt records completed order mutation');
check(Webhook::review_resolution_recovery_action_available($review_finish_loss_order), 'lost final update remains actionable');
$review_finish_loss_saves = $review_finish_loss_order->save_calls;
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'finish-loss review retry reacquires order fence');
try {
    $review_finish_loss_recovery = Webhook::resolve_review_for_operator(clone $review_finish_loss_order);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($review_finish_loss_recovery, 'finish-only review retry closes exact intent');
same($review_finish_loss_saves, $fake_orders[42]->save_calls, 'finish-only review retry does not rewrite exact order');
same('done', $fake_options[$review_resolution_option]['status'] ?? '', 'finish-only review retry durably closes intent');
same($review_finish_loss_receipts, test_options_with_prefix($fake_options, 'bactive_paymongo_review_receipt_'), 'finish-only review retry reuses the exact immutable completion receipt');

// A zero-attempt operational review has no provider evidence to index. Even
// after all human-review markers are gone and the one-off schedule is lost, the
// retained REQUIRED marker keeps the processing intent discoverable to the
// periodic scan and blocks drain/deactivation until human recovery.
$zero_attempt_order = new WC_Order();
unset($zero_attempt_order->meta['_bactive_paymongo_attempts']);
$fake_orders = array(42 => clone $zero_attempt_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'zero-attempt operational review acquires order fence');
try {
    $zero_attempt_hold = Webhook::hold_order_for_review(
        clone $fake_orders[42],
        'zero_attempt_operational_review',
        '',
        null
    );
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($zero_attempt_hold, 'zero-attempt operational review becomes durable');
$zero_attempt_source = clone $fake_orders[42];
check(!array_key_exists('_bactive_paymongo_attempts', $zero_attempt_source->meta), 'zero-attempt source has no attempt-index metadata');
$zero_attempt_resolution_option = test_review_resolution_option(42, 'local');
$fake_option_update_handler = static function (string $key, $value) use ($zero_attempt_resolution_option): string {
    return $key === $zero_attempt_resolution_option
        && is_array($value)
        && ($value['status'] ?? '') === 'done'
            ? 'swallow'
            : '';
};
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'zero-attempt review resolution acquires order fence');
try {
    $zero_attempt_resolution = Webhook::resolve_review_for_operator(clone $zero_attempt_source);
} finally {
    Order_Lock::release(42);
    $fake_option_update_handler = null;
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$zero_attempt_torn = $fake_orders[42];
check(!$zero_attempt_resolution, 'zero-attempt crash before intent completion is not acknowledged');
same('', $zero_attempt_torn->meta[Reconciler::UNRESOLVED_META] ?? '', 'zero-attempt target removes unresolved review marker');
same('', $zero_attempt_torn->meta['_bactive_paymongo_review_required'] ?? '', 'zero-attempt target removes human review marker');
same('yes', $zero_attempt_torn->meta[Reconciler::REQUIRED_META] ?? '', 'zero-attempt target retains independent discovery marker');
check(!array_key_exists('_bactive_paymongo_attempts', $zero_attempt_torn->meta), 'zero-attempt torn target still has no attempt index');
same('processing', $fake_options[$zero_attempt_resolution_option]['status'] ?? '', 'zero-attempt torn target retains external intent');
$fake_scheduled = array();
$fake_order_query_ids = array(42);
check(Reconciler::has_tracked_orders(), 'periodic source scan rediscovers zero-attempt intent after scheduler loss');
$zero_attempt_drain = Reconciler::expire_all_tracked(new Gateway(false));
check(is_wp_error($zero_attempt_drain), 'zero-attempt active intent blocks explicit drain');
same('paymongo_active_sessions_remain', $zero_attempt_drain->get_error_code(), 'zero-attempt drain returns exact active-state blocker');
$zero_attempt_deactivation_blocked = false;
try {
    Reconciler::guard_deactivation();
} catch (RuntimeException $error) {
    $zero_attempt_deactivation_blocked = str_starts_with($error->getMessage(), 'wp_die:');
}
check($zero_attempt_deactivation_blocked, 'zero-attempt active intent blocks deactivation after scheduler loss');
check(Order_Lock::acquire(42), 'zero-attempt human recovery reacquires order fence');
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $zero_attempt_recovery = Webhook::resolve_review_for_operator(clone $fake_orders[42]);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($zero_attempt_recovery, 'zero-attempt active intent converges through human recovery');
same('done', $fake_options[$zero_attempt_resolution_option]['status'] ?? '', 'zero-attempt recovery closes external intent');
same('yes', $fake_orders[42]->meta[Reconciler::REQUIRED_META] ?? '', 'discovery marker survives until intent is done');
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
Reconciler::run_order(42);
$fake_before_order_save = null;
$fake_clone_order_reads = false;
$fake_persist_order_saves = false;
same('', $fake_orders[42]->meta[Reconciler::REQUIRED_META] ?? '', 'normal reconciler clears discovery marker only after intent completion');
check(!Reconciler::has_tracked_orders(), 'zero-attempt order leaves active source set after safe cleanup');
$fake_order_query_ids = array();

// Restore the canonical paid review and prove its ordinary successful path;
// the additional cases above each used isolated snapshots.
$fake_orders = array(42 => clone $review_resolution_source_order);
$fake_options = $review_resolution_source_options;
$fake_hook_calls = array();
$fake_scheduled = array();
$repair_review_order = clone $fake_orders[42];
$fake_orders = array(42 => $repair_review_order);
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'repaired quarantine review acquires order fence');
try {
    $repair_review_result = Webhook::resolve_review_for_operator($repair_review_order);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($repair_review_result, 'repaired quarantine remains explicitly operator-resolvable');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'repaired quarantine resolution emits no duplicate on-hold hook');

// A resolved paid quarantine requires a second explicit operator action. That
// action performs a fresh authenticated provider GET and records the exact paid
// state, but emits none of WooCommerce's stock/email/fulfillment/payment hooks.
$resolved_paid_snapshot = clone $fake_orders[42];
$repair_paid_at = (int) ($resolved_paid_snapshot->meta['_bactive_paymongo_attempts'][0]['paid_at'] ?? 0);
check($repair_paid_at > 0, 'resolved paid quarantine retains exact observed payment time');
check(Webhook::resolved_payment_disposition_action_available($resolved_paid_snapshot), 'resolved paid quarantine exposes explicit no-effects disposition');
$resolved_actions = Reconciler::order_actions(array(), $resolved_paid_snapshot);
check(isset($resolved_actions['bactive_paymongo_finalize_resolved_payment']), 'resolved paid quarantine exposes labeled WooCommerce order action');

$fake_options['woocommerce_bactive_paymongo_settings'] = array(
    'test_mode' => 'yes',
    'test_secret_key' => Secrets::encrypt('sk_test_operator_disposition_123456789'),
);
$resolved_paid_options_snapshot = $fake_options;
$repair_provider_session = session();
$repair_provider_session['attributes']['payments'][0]['id'] = 'pay_quarantine_repair_123';
$disposition_provider_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$disposition_provider_calls, $repair_provider_session): array {
    $disposition_provider_calls[] = $url;
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => $repair_provider_session)),
    );
};
$fake_orders = array(42 => clone $resolved_paid_snapshot);
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$disposition_order = clone $resolved_paid_snapshot;
try {
    Reconciler::finalize_resolved_payment($disposition_order);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_remote_handler = null;
}
$disposition_readback = $fake_orders[42];
$disposition_option = test_operator_disposition_option('pay_quarantine_repair_123');
same(1, count($disposition_provider_calls), 'operator disposition performs one bounded authenticated provider GET');
same('processing', $disposition_readback->status, 'operator disposition records exact paid processing status');
check($disposition_readback->is_paid(), 'operator disposition readback is paid');
same('pay_quarantine_repair_123', $disposition_readback->transaction_id, 'operator disposition records exact PayMongo transaction ID');
same('bactive_paymongo', $disposition_readback->payment_method, 'operator disposition restores exact PayMongo gateway ID');
same('Pay online securely', $disposition_readback->payment_method_title, 'operator disposition records configured gateway title');
same($repair_paid_at, $disposition_readback->date_paid?->getTimestamp() ?? 0, 'operator disposition retains observed payment time');
same('', $disposition_readback->meta['_bactive_paymongo_unexpected_payment_id'] ?? '', 'operator disposition clears exact unexpected-payment hold');
same('', $disposition_readback->meta['_bactive_paymongo_resolved_evidence_fingerprint'] ?? '', 'operator disposition consumes resolved evidence fingerprint');
same('', $disposition_readback->meta['_bactive_paymongo_resolved_payment_pending'] ?? '', 'operator disposition clears its drain-active pending marker');
same('paid_verified_no_reemit', $disposition_readback->meta['_bactive_paymongo_operator_disposition']['type'] ?? '', 'operator disposition persists exact no-replay audit type');
same('cod', $disposition_readback->meta['_bactive_paymongo_operator_disposition']['prior_payment_method'] ?? '', 'operator disposition audit retains prior payment method');
same('on-hold', $disposition_readback->meta['_bactive_paymongo_operator_disposition']['prior_status'] ?? '', 'operator disposition audit retains prior hold status');
same(77, (int) ($disposition_readback->meta['_bactive_paymongo_operator_disposition']['resolved_by'] ?? 0), 'operator disposition audit retains exact operator');
same('done', $fake_options[$disposition_option]['status'] ?? '', 'operator disposition completes its durable external intent after exact order readback');
same('pay_quarantine_repair_123', $fake_options[$disposition_option]['identity'] ?? '', 'operator disposition intent is keyed to the exact PayMongo payment');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'operator disposition emits no processing-status hook');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold_to_processing'] ?? 0, 'operator disposition emits no status-transition hook');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'operator disposition emits no generic status-changed hook');
same(0, $fake_hook_calls['woocommerce_pre_payment_complete'] ?? 0, 'operator disposition emits no pre-payment-complete hook');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'operator disposition emits no payment-complete hook');
check(!Webhook::resolved_payment_disposition_action_available($disposition_readback), 'completed operator disposition cannot be replayed');
check(!isset(Reconciler::order_actions(array(), $disposition_readback)['bactive_paymongo_finalize_resolved_payment']), 'completed operator disposition removes order action');

$tampered_resolved_order = clone $resolved_paid_snapshot;
$tampered_resolved_order->meta['_bactive_paymongo_source_method'] = 'shopee_pay';
check(!Webhook::resolved_payment_disposition_action_available($tampered_resolved_order), 'altered resolved evidence suppresses operator payment disposition');

// A local mutation during the provider GET must invalidate the first snapshot;
// stale evidence never authorizes a payment/status write.
$fake_orders = array(42 => clone $resolved_paid_snapshot);
$fake_options = $resolved_paid_options_snapshot;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$fake_remote_handler = static function (string $url, array $args) use (&$fake_orders, $repair_provider_session): array {
    $fake_orders[42]->total = '124.45';
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => $repair_provider_session)),
    );
};
$stale_disposition_order = clone $resolved_paid_snapshot;
try {
    Reconciler::finalize_resolved_payment($stale_disposition_order);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_remote_handler = null;
}
same('on-hold', $fake_orders[42]->status, 'provider-round-trip mutation leaves resolved order on hold');
check(!$fake_orders[42]->is_paid(), 'provider-round-trip mutation never marks resolved order paid');
same('', $fake_orders[42]->transaction_id, 'provider-round-trip mutation writes no transaction ID');
same('', $fake_orders[42]->meta['_bactive_paymongo_operator_disposition'] ?? '', 'provider-round-trip mutation writes no disposition audit');
same('resolved_payment_disposition_failed', $fake_options['bactive_paymongo_reconcile_diagnostic_42']['code'] ?? '', 'provider-round-trip mutation records exact diagnostic');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'provider-round-trip mutation emits no processing hook');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'provider-round-trip mutation emits no payment-complete hook');

// CPT stores custom metadata before core order fields. If the write tears at
// that boundary, the external processing intent must retain the exact recovery
// plan and a retry must converge storage without replaying any Woo effect.
$fake_orders = array(42 => clone $resolved_paid_snapshot);
$fake_options = $resolved_paid_options_snapshot;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$cpt_before = clone $resolved_paid_snapshot;
$fake_persist_order_filter = static function (WC_Order $persisted) use ($cpt_before): WC_Order {
    global $fake_persist_order_filter;
    $persisted->status = $cpt_before->status;
    $persisted->paid = $cpt_before->paid;
    $persisted->payment_method = $cpt_before->payment_method;
    $persisted->payment_method_title = $cpt_before->payment_method_title;
    $persisted->transaction_id = $cpt_before->transaction_id;
    $persisted->date_paid = $cpt_before->date_paid;
    $persisted->changes = array();
    $fake_persist_order_filter = null;
    return $persisted;
};
$cpt_provider_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$cpt_provider_calls, $repair_provider_session): array {
    $cpt_provider_calls[] = $url;
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => $repair_provider_session)),
    );
};
try {
    Reconciler::finalize_resolved_payment(clone $resolved_paid_snapshot);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$cpt_torn = $fake_orders[42];
same('on-hold', $cpt_torn->status, 'CPT meta-first tear retains prior core status');
check(!$cpt_torn->is_paid(), 'CPT meta-first tear does not claim a paid core state');
same('paid_verified_no_reemit', $cpt_torn->meta['_bactive_paymongo_operator_disposition']['type'] ?? '', 'CPT meta-first tear may persist the exact final audit');
same('', $cpt_torn->meta['_bactive_paymongo_resolved_payment_pending'] ?? '', 'CPT meta-first tear may consume the order pending marker');
same('processing', $fake_options[$disposition_option]['status'] ?? '', 'CPT meta-first tear retains external processing intent');
check(Webhook::operator_disposition_recovery_pending($cpt_torn), 'CPT meta-first tear remains explicitly recoverable');
check(Webhook::resolved_payment_disposition_action_available($cpt_torn), 'CPT meta-first tear keeps the operator action visible');

$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    Reconciler::finalize_resolved_payment(clone $cpt_torn);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_remote_handler = null;
    $fake_persist_order_filter = null;
}
$cpt_recovered = $fake_orders[42];
same(2, count($cpt_provider_calls), 'CPT recovery repeats fresh provider verification before converging');
same('processing', $cpt_recovered->status, 'CPT recovery converges exact paid core status');
same('pay_quarantine_repair_123', $cpt_recovered->transaction_id, 'CPT recovery converges exact transaction ID');
same('done', $fake_options[$disposition_option]['status'] ?? '', 'CPT recovery completes external intent only after exact readback');
check(!Webhook::operator_disposition_recovery_pending($cpt_recovered), 'CPT recovery closes the recovery action');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'CPT tear and recovery emit no paid status hook');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'CPT tear and recovery emit no status-changed hook');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'CPT tear and recovery emit no payment-complete hook');

// HPOS stores core order fields before custom metadata. The mirror-image torn
// state must be recognized from the same immutable intent and converge safely.
$fake_orders = array(42 => clone $resolved_paid_snapshot);
$fake_options = $resolved_paid_options_snapshot;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$hpos_before = clone $resolved_paid_snapshot;
$fake_persist_order_filter = static function (WC_Order $persisted) use ($hpos_before): WC_Order {
    global $fake_persist_order_filter;
    $persisted->meta = $hpos_before->meta;
    $persisted->changes = array();
    $fake_persist_order_filter = null;
    return $persisted;
};
$hpos_provider_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$hpos_provider_calls, $repair_provider_session): array {
    $hpos_provider_calls[] = $url;
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => $repair_provider_session)),
    );
};
try {
    Reconciler::finalize_resolved_payment(clone $resolved_paid_snapshot);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$hpos_torn = $fake_orders[42];
same('processing', $hpos_torn->status, 'HPOS core-first tear may persist target core status');
check($hpos_torn->is_paid(), 'HPOS core-first tear may persist paid core state');
same('pay_quarantine_repair_123', $hpos_torn->transaction_id, 'HPOS core-first tear may persist exact transaction ID');
same('', $hpos_torn->meta['_bactive_paymongo_operator_disposition'] ?? '', 'HPOS core-first tear may retain prior custom audit state');
same('pay_quarantine_repair_123', $hpos_torn->meta['_bactive_paymongo_unexpected_payment_id'] ?? '', 'HPOS core-first tear may retain prior quarantine marker');
same('processing', $fake_options[$disposition_option]['status'] ?? '', 'HPOS core-first tear retains external processing intent');
check(Webhook::operator_disposition_recovery_pending($hpos_torn), 'HPOS core-first tear remains explicitly recoverable');
check(Webhook::resolved_payment_disposition_action_available($hpos_torn), 'HPOS core-first tear keeps the operator action visible');

$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    Reconciler::finalize_resolved_payment(clone $hpos_torn);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_remote_handler = null;
    $fake_persist_order_filter = null;
}
$hpos_recovered = $fake_orders[42];
same(2, count($hpos_provider_calls), 'HPOS recovery repeats fresh provider verification before converging');
same('processing', $hpos_recovered->status, 'HPOS recovery retains exact paid core status');
same('', $hpos_recovered->meta['_bactive_paymongo_unexpected_payment_id'] ?? '', 'HPOS recovery consumes prior quarantine marker');
same('paid_verified_no_reemit', $hpos_recovered->meta['_bactive_paymongo_operator_disposition']['type'] ?? '', 'HPOS recovery converges exact final audit');
same('done', $fake_options[$disposition_option]['status'] ?? '', 'HPOS recovery completes external intent only after exact readback');
check(!Webhook::operator_disposition_recovery_pending($hpos_recovered), 'HPOS recovery closes the recovery action');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'HPOS tear and recovery emit no paid status hook');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'HPOS tear and recovery emit no status-changed hook');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'HPOS tear and recovery emit no payment-complete hook');

// A lower-priority extension may try to rewrite the armed paid target during
// WC_Order::save(). The last-priority fence must suppress the queued transition
// and stop persistence, leaving only the recoverable external intent.
$fake_orders = array(42 => clone $resolved_paid_snapshot);
$fake_options = $resolved_paid_options_snapshot;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$mutate_disposition_target = static function ($saving_order): void {
    if ($saving_order instanceof WC_Order && $saving_order->get_id() === 42) {
        $saving_order->set_status('completed');
    }
};
add_action('woocommerce_before_order_object_save', $mutate_disposition_target, 20, 1);
$mutation_provider_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$mutation_provider_calls, $repair_provider_session): array {
    $mutation_provider_calls[] = $url;
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => $repair_provider_session)),
    );
};
try {
    Reconciler::finalize_resolved_payment(clone $resolved_paid_snapshot);
} finally {
    remove_action('woocommerce_before_order_object_save', $mutate_disposition_target, 20);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$mutation_blocked = $fake_orders[42];
same('on-hold', $mutation_blocked->status, 'extension target rewrite is stopped before order persistence');
same('processing', $fake_options[$disposition_option]['status'] ?? '', 'extension target rewrite retains external processing intent');
check(Webhook::operator_disposition_recovery_pending($mutation_blocked), 'extension target rewrite remains recoverable');
same(0, $fake_hook_calls['woocommerce_order_status_completed'] ?? 0, 'extension target rewrite emits no completed-status hook');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'extension target rewrite emits no status-changed hook');

$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    Reconciler::finalize_resolved_payment(clone $mutation_blocked);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_remote_handler = null;
}
same(2, count($mutation_provider_calls), 'extension-blocked recovery repeats fresh provider verification');
same('processing', $fake_orders[42]->status, 'extension-blocked recovery converges only the original target');
same('done', $fake_options[$disposition_option]['status'] ?? '', 'extension-blocked recovery completes its intent');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'extension-blocked retry emits no processing hook');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'extension-blocked retry emits no payment-complete hook');

// If the exact order write succeeds but the final intent update is not
// observable, the order remains recoverable. The next explicit action verifies
// PayMongo again and closes the intent without saving or emitting effects.
$fake_orders = array(42 => clone $resolved_paid_snapshot);
$fake_options = $resolved_paid_options_snapshot;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$finish_provider_calls = array();
$fake_remote_handler = static function (string $url, array $args) use (&$finish_provider_calls, $repair_provider_session): array {
    $finish_provider_calls[] = $url;
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array('data' => $repair_provider_session)),
    );
};
$fake_option_update_handler = static function (string $key, $value) use ($disposition_option): string {
    return $key === $disposition_option
        && is_array($value)
        && ($value['status'] ?? '') === 'done'
            ? 'swallow'
            : '';
};
try {
    Reconciler::finalize_resolved_payment(clone $resolved_paid_snapshot);
} finally {
    $fake_option_update_handler = null;
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$finish_ambiguous = $fake_orders[42];
same('processing', $finish_ambiguous->status, 'finish-readback loss retains exact paid order state');
same('processing', $fake_options[$disposition_option]['status'] ?? '', 'finish-readback loss retains processing intent');
check(Webhook::operator_disposition_recovery_pending($finish_ambiguous), 'finish-readback loss remains recoverable');

$finish_save_calls = $finish_ambiguous->save_calls;
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    Reconciler::finalize_resolved_payment(clone $finish_ambiguous);
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
    $fake_remote_handler = null;
}
same(2, count($finish_provider_calls), 'finish-only recovery repeats fresh provider verification');
same($finish_save_calls, $fake_orders[42]->save_calls, 'finish-only recovery does not rewrite an already exact order');
same('done', $fake_options[$disposition_option]['status'] ?? '', 'finish-only recovery closes exact external intent');
check(!Webhook::operator_disposition_recovery_pending($fake_orders[42]), 'finish-only recovery removes the operator action');
same(0, $fake_hook_calls['woocommerce_order_status_processing'] ?? 0, 'finish-only recovery emits no paid status hook');
same(0, $fake_hook_calls['woocommerce_payment_complete'] ?? 0, 'finish-only recovery emits no payment-complete hook');

// The review-resolution action and payment-disposition action are distinct
// human decisions. A mode/key rotation attempted between them must fail closed
// and retain the exact old-mode credential required for the second action.
$fake_orders = array(42 => clone $resolved_paid_snapshot);
$fake_order_query_ids = array(42);
$fake_options = $resolved_paid_options_snapshot;
$fake_options['bactive_paymongo_draining'] = 'no';
$fake_options[Reconciler::CONFIG_GENERATION_OPTION] = 30;
$fake_hook_calls = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
$rotation_old_settings = $fake_options['woocommerce_bactive_paymongo_settings'];
$rotation_candidate = $rotation_old_settings;
$rotation_candidate['test_mode'] = 'no';
$rotation_candidate['live_secret_key'] = 'sk_live_pending_disposition_rotation_123456';
$rotation_result = Gateway::filter_settings_update($rotation_candidate, $rotation_old_settings);
$fake_before_order_save = null;
$fake_clone_order_reads = false;
$fake_persist_order_saves = false;
$rotation_order = $fake_orders[42];
same($rotation_old_settings, $rotation_result, 'pending operator disposition rejects mode and credential rotation');
same($rotation_old_settings, $fake_options['woocommerce_bactive_paymongo_settings'], 'pending operator disposition retains exact stored old-mode credential');
same('yes', $fake_options['bactive_paymongo_draining'] ?? '', 'rejected disposition-time rotation leaves checkout draining');
same('paymongo_active_sessions_remain', $fake_options['bactive_paymongo_disable_drain_error']['code'] ?? '', 'rejected disposition-time rotation records exact active-state blocker');
same(31, (int) ($fake_options[Reconciler::CONFIG_GENERATION_OPTION] ?? 0), 'rejected disposition-time rotation invalidates in-flight checkout issuance');
same('yes', $rotation_order->meta[Reconciler::REQUIRED_META] ?? '', 'reconciliation may mark pending disposition without invalidating it');
check(Webhook::resolved_payment_disposition_action_available($rotation_order), 'pending disposition remains actionable after rejected rotation');
check(!Order_Lock::settings_write_active(), 'sensitive-only no-op rotation releases its settings lease without waiting for a skipped update hook');
$fake_order_query_ids = array();

$generic_repair_order = new WC_Order();
$generic_repair_order->meta['_bactive_paymongo_attempts'] = array();
$generic_repair_order->throw_on_save_attempt = 1;
$fake_orders = array(42 => clone $generic_repair_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $generic_repair_first = $quarantine_method->invoke(
        null,
        'session_not_authorized',
        'evt_generic_repair_123',
        'cs_generic_repair_123',
        42,
        '',
        'local'
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$generic_repair_evidence = test_quarantine_option('evt_generic_repair_123', 'local');
$generic_repair_recorded_at = (int) ($fake_options[$generic_repair_evidence]['recorded_at'] ?? 0);
check(!$generic_repair_first, 'generic review first-save failure is retryable');
check($generic_repair_recorded_at > 0, 'generic review first-save failure retains immutable evidence');
same('local', $fake_options[$generic_repair_evidence]['mode'] ?? '', 'generic review evidence is isolated to local mode');
same(0, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'generic review first-save failure persists no phantom order count');
$generic_repair_retry_order = clone $fake_orders[42];
$generic_repair_retry_order->throw_on_save_attempt = -1;
$fake_orders = array(42 => $generic_repair_retry_order);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
try {
    $generic_repair_retry = $quarantine_method->invoke(
        null,
        'session_not_authorized',
        'evt_generic_repair_123',
        'cs_generic_repair_123',
        42,
        '',
        'local'
    );
} finally {
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($generic_repair_retry, 'generic review retry repairs first-save orphan');
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'generic review retry restores one order incident');
same($generic_repair_recorded_at, (int) ($fake_options[$generic_repair_evidence]['recorded_at'] ?? 0), 'generic review retry preserves the immutable evidence timestamp');
same(true, (bool) ($fake_options[$generic_repair_evidence]['order_annotated'] ?? false), 'generic review retry links the original evidence to the order');

$processing_incident_method = new ReflectionMethod(Webhook::class, 'record_processing_incident');
$processing_incident_method->setAccessible(true);
$processing_repair_validated = $validated_payment;
$processing_repair_validated['payment_id'] = 'pay_processing_repair_123';
$processing_repair_validated['event_id'] = 'evt_processing_repair_123';
$processing_repair_validated['mode'] = 'test';
$processing_repair_identity = '42|payment_effects_ambiguous|evt_processing_repair_123|cs_test_session_123|pay_processing_repair_123';
$processing_repair_incident = test_processing_incident_option($processing_repair_identity);
$processing_repair_order = new WC_Order();
$processing_repair_order->throw_on_save_attempt = 1;
$fake_orders = array(42 => clone $processing_repair_order);
$fake_options = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'processing incident first-save test acquires order fence');
try {
    $processing_incident_method->invoke(null, $processing_repair_order, $processing_repair_validated, 'payment_effects_ambiguous');
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$processing_repair_recorded_at = (int) ($fake_options[$processing_repair_incident]['recorded_at'] ?? 0);
check($processing_repair_recorded_at > 0, 'processing incident first-save failure retains immutable evidence');
same('test', $fake_options[$processing_repair_incident]['mode'] ?? '', 'processing incident evidence is isolated to test mode');
same(0, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'processing incident first-save failure leaves no phantom order count');
$processing_retry_order = clone $fake_orders[42];
$processing_retry_order->throw_on_save_attempt = -1;
$fake_orders = array(42 => $processing_retry_order);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'processing incident repair acquires order fence');
try {
    $processing_incident_method->invoke(null, $processing_retry_order, $processing_repair_validated, 'payment_effects_ambiguous');
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'processing incident retry restores one order incident');
same($processing_repair_recorded_at, (int) ($fake_options[$processing_repair_incident]['recorded_at'] ?? 0), 'processing incident retry preserves the immutable evidence timestamp');
same('test', $fake_orders[42]->meta['_bactive_paymongo_processing_incident_mode'] ?? '', 'processing incident retry restores exact mode association');
same('payment_effects_ambiguous', $fake_orders[42]->meta['_bactive_paymongo_processing_incident_code'] ?? '', 'processing incident retry restores exact association');

// Gateway lifecycle and scheduled reconciliation reviews use the same durable
// incident linkage. A data-store failure after the unique option is created
// must be repairable without replacing the immutable incident evidence.
$gateway_flag_review = new ReflectionMethod(Gateway::class, 'flag_review');
$gateway_flag_review->setAccessible(true);
$gateway_repair_order = new WC_Order();
$gateway_repair_order->throw_on_save_attempt = 1;
$fake_orders = array(42 => clone $gateway_repair_order);
$fake_options = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'gateway incident first-save test acquires order fence');
try {
    $gateway_flag_review->invoke(new Gateway(false), $gateway_repair_order, 'session_expiry_unverified', true, 'local');
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$gateway_review_option = Reconciler::review_incident_option(42, 'session_expiry_unverified', 'local');
$gateway_review_recorded_at = (int) ($fake_options[$gateway_review_option]['recorded_at'] ?? 0);
check($gateway_review_recorded_at > 0, 'gateway incident first-save failure retains immutable evidence');
same('local', $fake_options[$gateway_review_option]['mode'] ?? '', 'gateway incident evidence is isolated to local mode');
same(0, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'gateway incident first-save failure leaves no phantom order count');
$gateway_repair_retry = clone $fake_orders[42];
$gateway_repair_retry->throw_on_save_attempt = -1;
$fake_orders = array(42 => $gateway_repair_retry);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'gateway incident repair acquires order fence');
try {
    $gateway_flag_review->invoke(new Gateway(false), $gateway_repair_retry, 'session_expiry_unverified', true, 'local');
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'gateway incident retry restores one order incident');
same($gateway_review_recorded_at, (int) ($fake_options[$gateway_review_option]['recorded_at'] ?? 0), 'gateway incident retry preserves the immutable evidence timestamp');
same('local', $fake_orders[42]->meta['_bactive_paymongo_review_mode'] ?? '', 'gateway incident retry restores exact local-mode association');
same('session_expiry_unverified', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'gateway incident retry restores exact reason');

$reconciler_flag_failure = new ReflectionMethod(Reconciler::class, 'flag_failure');
$reconciler_flag_failure->setAccessible(true);
$reconciler_repair_order = new WC_Order();
$reconciler_repair_order->throw_on_save_attempt = 1;
$fake_orders = array(42 => clone $reconciler_repair_order);
$fake_options = array();
$fake_scheduled = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'scheduled reconciliation incident first-save test acquires order fence');
try {
    $reconciler_flag_failure->invoke(null, $reconciler_repair_order, 'reconciliation_readback_invalid', 'local');
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$reconciler_review_option = Reconciler::review_incident_option(42, 'reconciliation_readback_invalid', 'local');
$reconciler_review_recorded_at = (int) ($fake_options[$reconciler_review_option]['recorded_at'] ?? 0);
check($reconciler_review_recorded_at > 0, 'scheduled reconciliation first-save failure retains immutable evidence');
same('local', $fake_options[$reconciler_review_option]['mode'] ?? '', 'scheduled reconciliation evidence is isolated to local mode');
same(0, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'scheduled reconciliation first-save failure leaves no phantom order count');
$reconciler_repair_retry = clone $fake_orders[42];
$reconciler_repair_retry->throw_on_save_attempt = -1;
$fake_orders = array(42 => $reconciler_repair_retry);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'scheduled reconciliation incident repair acquires order fence');
try {
    $reconciler_flag_failure->invoke(null, $reconciler_repair_retry, 'reconciliation_readback_invalid', 'local');
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'scheduled reconciliation retry restores one order incident');
same($reconciler_review_recorded_at, (int) ($fake_options[$reconciler_review_option]['recorded_at'] ?? 0), 'scheduled reconciliation retry preserves the immutable evidence timestamp');
same('local', $fake_orders[42]->meta['_bactive_paymongo_review_mode'] ?? '', 'scheduled reconciliation retry restores exact local-mode association');
same('reconciliation_readback_invalid', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'scheduled reconciliation retry restores exact reason');

// An unverified cancel can crash after the on-hold hook begins. The persisted
// review effect must stay processing, matching retries must never replay it,
// and an operator can acknowledge it only after the attempt is independently
// made terminal.
$cancel_ambiguous_order = new WC_Order();
$cancel_ambiguous_attempts = Gateway::order_attempts($cancel_ambiguous_order);
$cancel_ambiguous_result = array('verified' => false, 'settlement' => false, 'lock_lost' => false);
$fake_orders = array(42 => clone $cancel_ambiguous_order);
$fake_options = array();
$fake_hook_calls = array();
$fake_scheduled = array();
$cancel_ambiguous_thrower = static function ($order_id, $order, $transition): void {
    throw new RuntimeException('simulated cancel review transition failure');
};
add_action('woocommerce_order_status_on-hold', $cancel_ambiguous_thrower, 10, 3);
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'cancel review ambiguity test acquires order fence');
try {
    $cancel_ambiguous_first = $cancel_preserve_method->invoke(
        new Gateway(false),
        $cancel_ambiguous_order,
        $cancel_ambiguous_attempts,
        $cancel_ambiguous_result,
        'cs_test_session_123'
    );
} finally {
    Order_Lock::release(42);
    remove_action('woocommerce_order_status_on-hold', $cancel_ambiguous_thrower, 10);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
$cancel_ambiguous_identity = 'evt_local_' . substr(hash('sha256', 'test|session_cancel_expiry_unverified|cs_test_session_123|42'), 0, 48);
$cancel_ambiguous_effect = test_effects_option('review', $cancel_ambiguous_identity);
$cancel_ambiguous_evidence = test_quarantine_option($cancel_ambiguous_identity);
$cancel_ambiguous_recorded_at = (int) ($fake_options[$cancel_ambiguous_evidence]['recorded_at'] ?? 0);
check(!$cancel_ambiguous_first, 'cancel hook crash is not acknowledged as a completed review hold');
same('processing', $fake_options[$cancel_ambiguous_effect]['status'] ?? '', 'cancel hook crash leaves exact review effect processing');
same('on-hold', $fake_orders[42]->status, 'cancel hook crash preserves durable on-hold status');
same('session_cancel_expiry_unverified', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'cancel hook crash preserves exact incident reason');
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'cancel hook crash records one order incident');
check($cancel_ambiguous_recorded_at > 0, 'cancel hook crash retains immutable quarantine evidence');
same('test', $fake_options[$cancel_ambiguous_evidence]['mode'] ?? '', 'cancel hook crash evidence is isolated to test mode');
same(1, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'cancel hook crash begins on-hold effect exactly once');

$cancel_ambiguous_retry = clone $fake_orders[42];
$fake_orders = array(42 => $cancel_ambiguous_retry);
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'cancel review no-replay retry acquires order fence');
try {
    $cancel_ambiguous_retry_result = $cancel_preserve_method->invoke(
        new Gateway(false),
        $cancel_ambiguous_retry,
        $cancel_ambiguous_attempts,
        $cancel_ambiguous_result,
        'cs_test_session_123'
    );
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check(!$cancel_ambiguous_retry_result, 'matching cancel retry refuses to auto-replay processing effect');
same('processing', $fake_options[$cancel_ambiguous_effect]['status'] ?? '', 'matching cancel retry preserves processing effect');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'matching cancel retry emits no duplicate on-hold hook');
same(1, (int) ($fake_orders[42]->meta['_bactive_paymongo_review_incidents'] ?? 0), 'matching cancel retry retains one durable order incident');
same($cancel_ambiguous_recorded_at, (int) ($fake_options[$cancel_ambiguous_evidence]['recorded_at'] ?? 0), 'matching cancel retry preserves original quarantine evidence');

$cancel_operator_order = clone $fake_orders[42];
$cancel_operator_order->meta['_bactive_paymongo_attempts'][0]['expired_at'] = time();
$fake_orders = array(42 => $cancel_operator_order);
$fake_hook_calls = array();
$fake_before_order_save = array($lifecycle_test_gateway, 'handle_order_before_save');
$fake_clone_order_reads = true;
$fake_persist_order_saves = true;
check(Order_Lock::acquire(42), 'cancel review operator acknowledgement acquires order fence');
try {
    $cancel_operator_result = Webhook::resolve_review_for_operator($cancel_operator_order);
} finally {
    Order_Lock::release(42);
    $fake_before_order_save = null;
    $fake_clone_order_reads = false;
    $fake_persist_order_saves = false;
}
check($cancel_operator_result, 'terminal cancel attempt permits exact operator acknowledgement');
same('done', $fake_options[$cancel_ambiguous_effect]['status'] ?? '', 'cancel operator acknowledgement closes exact effect record');
same('operator_verified_no_reemit', $fake_options[$cancel_ambiguous_effect]['resolution'] ?? '', 'cancel operator acknowledgement records no-replay resolution');
same('', $fake_orders[42]->meta[Reconciler::UNRESOLVED_META] ?? '', 'cancel operator acknowledgement clears exact review marker');
same(0, $fake_hook_calls['woocommerce_order_status_on-hold'] ?? 0, 'cancel operator acknowledgement emits no on-hold hook');
same(0, $fake_hook_calls['woocommerce_order_status_changed'] ?? 0, 'cancel operator acknowledgement emits no status-changed hook');

require __DIR__ . '/recovery-boundaries.php';
require __DIR__ . '/rollout-restriction.php';
require __DIR__ . '/settings-review-drain.php';
abandoned_session_recovery_tests();

if ($failures !== array()) {
    // Synthetic fixtures only. Surface failures in the check API as well as
    // downloadable logs so a failed release gate is independently diagnosable.
    if (getenv('GITHUB_ACTIONS') === 'true') {
        // GitHub limits per-step annotations; keep the complete failure list
        // together instead of silently losing assertions after the first ten.
        $report = count($failures) . " failures in {$tests} checks:\n- " . implode("\n- ", $failures);
        $annotation = str_replace(array('%', "\r", "\n"), array('%25', '%0D', '%0A'), $report);
        fwrite(STDERR, "::error title=PayMongo contract assertions::{$annotation}\n");
    }
    fwrite(STDERR, "FAILED {$tests} checks:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS {$tests} checks\n";
