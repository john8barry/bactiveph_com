<?php

declare(strict_types=1);

define('BACTIVE_PAYMONGO_TESTING', true);
define('ABSPATH', __DIR__ . '/');
define('BACTIVE_PAYMONGO_TEST_ENCRYPTION_KEY', 'deterministic-test-key-not-a-production-secret');

require_once dirname(__DIR__) . '/includes/class-integrity.php';
require_once dirname(__DIR__) . '/includes/class-secrets.php';
require_once dirname(__DIR__) . '/includes/class-api-client.php';

use BActive\PayMongo\Api_Client;
use BActive\PayMongo\Integrity;
use BActive\PayMongo\Secrets;

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

if ($failures !== array()) {
    fwrite(STDERR, "FAILED {$tests} checks:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS {$tests} checks\n";
