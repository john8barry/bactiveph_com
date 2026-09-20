<?php
/** No WordPress install, network, mail, credentials, or database required. */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
$GLOBALS['test_options'] = [];
$GLOBALS['test_home'] = 'https://bactiveph.com';
$GLOBALS['test_site'] = 'https://bactiveph.com';
$GLOBALS['test_requests'] = [];
$GLOBALS['test_hooks'] = [];
$GLOBALS['captcha_reply'] = ['success' => true, 'hostname' => 'bactiveph.com', 'action' => 'newsletter'];
class WP_Error {
    public function __construct(private string $code, private string $message = '', private mixed $data = null) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_data(): mixed { return $this->data; }
}
class WP_REST_Request {
    public function __construct(public string $body = '', public array $headers = []) {}
    public function get_header(string $key): string { return $this->headers[strtolower($key)] ?? ''; }
    public function get_body(): string { return $this->body; }
}
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
function get_option(string $name, mixed $default = false): mixed { return $GLOBALS['test_options'][$name] ?? $default; }
function update_option(string $name, mixed $value, mixed $autoload = null): bool { $GLOBALS['test_options'][$name] = $value; return true; }
function delete_option(string $name): void { unset($GLOBALS['test_options'][$name]); }
function home_url(string $path = ''): string { return $GLOBALS['test_home'] . $path; }
function site_url(string $path = ''): string { return $GLOBALS['test_site'] . $path; }
function is_email(mixed $email): string|false { return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false; }
function wp_parse_url(string $url, int $component = -1): mixed { return parse_url($url, $component); }
function wp_json_encode(mixed $value): string|false { return json_encode($value); }
function wp_remote_retrieve_response_code(mixed $response): int { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body(mixed $response): string { return (string) ($response['body'] ?? ''); }
function wp_remote_request(string $url, array $args): array {
    $GLOBALS['test_requests'][] = [$url, $args];
    return ['response' => ['code' => 204], 'body' => ''];
}
function wp_remote_post(string $url, array $args): array {
    $GLOBALS['test_requests'][] = [$url, $args];
    return ['response' => ['code' => 200], 'body' => json_encode($GLOBALS['captcha_reply'])];
}
function wp_salt(string $scheme): string { return 'disposable-unit-test-salt'; }
function add_action(string $name, mixed $callback, int $priority = 10, int $accepted = 1): void { $GLOBALS['test_hooks'][] = $name; }
function wc_get_page_permalink(string $page): string { return home_url('/shop/'); }

foreach (['config', 'store', 'api', 'consent', 'automations', 'webhooks'] as $file) require dirname(__DIR__) . '/includes/' . $file . '.php';
use Bactive\Brevo\Api;
use Bactive\Brevo\Automations;
use Bactive\Brevo\Config;
use Bactive\Brevo\Consent;
use Bactive\Brevo\Webhooks;

$checks = [];
$assert = static function (mixed $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException('Unit failure: ' . $name);
    $checks[] = $name;
};
$err = static fn(mixed $value, string $code): bool => is_wp_error($value) && $value->get_error_code() === $code;
$settings = static function (array $value): void { $GLOBALS['test_options'][Config::OPTION] = array_merge(Config::defaults(), $value); };
$assert(!Config::enabled() && Config::mode() === 'test', 'new installation is disabled and test-only');
$settings(['enabled' => true]);
$assert(!Config::recipient_allowed('allowed@example.test'), 'test mode with no allowlist cannot send');
$settings(['enabled' => true, 'test_recipients' => ['allowed@example.test']]);
$assert(Config::recipient_allowed('ALLOWED@example.test') && !Config::recipient_allowed('other@example.test'), 'test recipient match is exact after case normalization');
foreach (['https://www.bactiveph.com', 'http://bactiveph.com', 'https://bactiveph.com:443', 'https://bactiveph.com/subdir', 'https://bactiveph.com.attacker.test'] as $site) {
    $GLOBALS['test_home'] = $GLOBALS['test_site'] = $site;
    $assert(!Config::enabled(), 'unapproved site is denied: ' . $site);
}
$GLOBALS['test_home'] = $GLOBALS['test_site'] = 'https://staging.bactiveph.com';
$assert(Config::enabled(), 'exact staging host is available only to allowlisted tests');
$settings(['enabled' => true, 'test_mode' => false, 'test_recipients' => ['allowed@example.test']]);
$assert(!Config::enabled(), 'staging host cannot send in live mode');
$GLOBALS['test_home'] = $GLOBALS['test_site'] = 'https://bactiveph.com';
$GLOBALS['test_site'] = 'https://staging.bactiveph.com';
$assert(!Config::enabled(), 'WordPress home and site identity must match');
$GLOBALS['test_site'] = $GLOBALS['test_home'];
$settings(['enabled' => true, 'test_recipients' => ['allowed@example.test'], 'confirmed_list_id' => 41, 'launch_cutoff' => time() - 3600,
    'api_key' => 'unsafe-option-secret', 'BACTIVE_BREVO_API_KEY' => 'unsafe-option-secret']);
$assert(Config::secret('api_key') === '' && Config::secret('unknown') === '', 'API credentials are never read from options');
define('BACTIVE_BREVO_API_KEY', 'disposable-unit-value');
define('BACTIVE_BREVO_WEBHOOK_TOKEN', str_repeat('unit-fixture-', 4));
define('BACTIVE_BREVO_TURNSTILE_SECRET', 'disposable-turnstile-unit-value');
$assert(Config::secret('api_key') === BACTIVE_BREVO_API_KEY, 'protected constant supplies API credential');
$assert(Config::limit('daily_event_cap', 50) === 50, 'send caps cannot exceed hard maximum');
$GLOBALS['test_options'][Config::OPTION]['doi_redirect_url'] = 'https://user@bactiveph.com/';
$assert(Config::redirect_url() === '', 'redirect rejects username without password');
$GLOBALS['test_options'][Config::OPTION]['doi_redirect_url'] = 'https://bactiveph.com/?ba_signup=confirmed';
$assert(Config::redirect_url() !== '', 'same-site HTTPS confirmation landing is accepted');
$assert(Automations::public_url('https://bactiveph.com/cart/?key=private#secret') === 'https://bactiveph.com/cart/', 'event URLs strip session/query/fragment values');
$assert(Automations::public_url('https://attacker.test/cart/') === '' && Automations::public_url('https://user@bactiveph.com/cart/') === '', 'event URLs reject other hosts and credentials');

foreach ([new WP_Error('http_request_failed'), ['response' => ['code' => 503], 'body' => 'failure'], ['response' => ['code' => 201], 'body' => 'invalid-json']] as $reply) {
    $assert($err(Api::classify_response($reply, true), 'provider_ambiguous'), 'uncertain POST cannot be automatically replayed');
}
$assert($err(Api::classify_response(new WP_Error('timeout'), false), 'provider_unavailable'), 'read timeout is safely retryable');
$assert($err(Api::classify_response(['response' => ['code' => 429]], true), 'provider_rate_limited'), 'explicit rate limit has separate retry classification');
$assert($err(Api::classify_response(['response' => ['code' => 400]], true), 'provider_rejected'), 'definitive rejection is not accepted');
$assert(Api::classify_response(['response' => ['code' => 204], 'body' => ''], true)['http_status'] === 204, 'documented empty success response is accepted');
$eligible = ['id' => 7, 'email' => 'allowed@example.test', 'emailBlacklisted' => false, 'listIds' => [41]];
$assert(Consent::provider_eligible($eligible, 'allowed@example.test'), 'provider readback requires exact eligible contact');
foreach (['id' => 0, 'email' => 'other@example.test', 'emailBlacklisted' => true, 'listIds' => [42]] as $key => $value) {
    $assert(!Consent::provider_eligible(array_replace($eligible, [$key => $value]), 'allowed@example.test'), 'provider eligibility rejects changed ' . $key);
}
foreach ([['id' => [7]], ['id' => '7junk'], ['email' => ['allowed@example.test']], ['listIds' => [[41]]], ['emailBlacklisted' => 0]] as $bad) {
    $assert(!Consent::provider_eligible(array_replace($eligible, $bad), 'allowed@example.test'), 'malformed provider identity is denied');
}
$assert($err(Consent::subscribe('allowed@example.test', 'footer', 'captcha-token', false), 'invalid_signup'), 'explicit false consent fails before provider access');
$assert($err(Consent::subscribe('allowed@example.test', 'unknown-source', 'captcha-token', true), 'invalid_signup'), 'unknown signup source fails before provider access');
$assert(Api::captcha('fixture-token') === true, 'CAPTCHA verifies expected site and action');
$GLOBALS['captcha_reply']['hostname'] = 'attacker.test';
$assert(Api::captcha('fixture-token') === false, 'CAPTCHA from a different hostname fails');
$GLOBALS['captcha_reply']['hostname'] = 'bactiveph.com';
$GLOBALS['captcha_reply']['action'] = 'checkout';
$assert(Api::captcha('fixture-token') === false, 'CAPTCHA from another action fails');
$assert(!Api::captcha('short'), 'undersized CAPTCHA token fails without HTTP');
$before = count($GLOBALS['test_requests']);
$assert($err(Api::event(['email' => 'other@example.test', 'provider_id' => 7], 'ba_welcome_ready', ['mode' => 'test']), 'recipient_not_allowed')
    && count($GLOBALS['test_requests']) === $before, 'test recipient restriction is enforced at provider boundary');
$assert($err(Api::event(['email' => 'allowed@example.test', 'provider_id' => 7], 'ba_welcome_ready', ['mode' => 'live']), 'event_environment_changed')
    && count($GLOBALS['test_requests']) === $before, 'mode changed after eligibility cannot cross provider boundary');
$assert(!is_wp_error(Api::event(['email' => 'allowed@example.test', 'provider_id' => 7], 'ba_welcome_ready', ['stage' => 'welcome', 'mode' => 'test'])), 'event API accepts an existing allowed contact');
[$url, $args] = end($GLOBALS['test_requests']);
$payload = json_decode($args['body'], true);
$assert($url === 'https://api.brevo.com/v3/events' && $args['redirection'] === 0 && $args['sslverify'] === true
    && $payload['identifiers'] === ['contact_id' => 7], 'provider transport pins HTTPS destination and numeric existing contact identity');

$request = static fn(array $body, array $headers = []): WP_REST_Request => new WP_REST_Request(json_encode($body), $headers + ['authorization' => 'Bearer ' . BACTIVE_BREVO_WEBHOOK_TOKEN]);
$assert(Webhooks::authenticate($request([])) === true, 'webhook constant-time bearer authentication accepts exact token');
$assert($err(Webhooks::authenticate($request([], ['authorization' => 'Bearer wrong'])), 'webhook_unauthorized'), 'invalid webhook authentication is denied');
$valid = ['event' => 'unsubscribed', 'email' => 'allowed@example.test', 'ts' => time(), 'id' => 77];
$assert(Webhooks::envelope($request($valid))['type'] === 'unsubscribed', 'valid suppression envelope accepted');
foreach ([['event' => 'subscribe'], ['event' => ['unsubscribed']], ['email' => ['allowed@example.test']], ['id' => ['invalid']]] as $bad) {
    $assert(is_wp_error(Webhooks::envelope($request(array_replace($valid, $bad)))), 'malformed webhook schema cannot cross boundary');
}
$assert($err(Webhooks::envelope($request(array_replace($valid, ['ts' => time() - 9 * DAY_IN_SECONDS]))), 'webhook_stale'), 'expired webhook timestamp is denied');
$assert($err(Webhooks::envelope($request(array_replace($valid, ['email' => 'other@example.test']))), 'webhook_test_recipient'), 'webhook obeys environment recipient restriction');
$assert($err(Webhooks::envelope(new WP_REST_Request(str_repeat('x', Webhooks::MAX_BODY + 1))), 'webhook_too_large'), 'oversized webhook is denied');
$assert($err(Webhooks::envelope(new WP_REST_Request('[]')), 'webhook_invalid'), 'webhook must be a JSON object');
$key = str_repeat('a', 64);
$receipt = ['event' => 'event_accepted', 'email' => 'allowed@example.test', 'event_properties' => ['delivery_key' => $key]];
$assert(Webhooks::envelope($request($receipt))['delivery_key'] === $key, 'event receipt extracts only a bounded delivery identifier');
$assert($err(Webhooks::envelope($request(array_replace($receipt, ['event_properties' => 'malformed']))), 'webhook_invalid_receipt'), 'malformed nested event data is rejected safely');
$cart = [['product_id' => 1, 'quantity' => 2, 'variation' => ['size' => 'M', 'color' => 'black']], ['product_id' => 2, 'quantity' => 1]];
$reverse = array_reverse($cart);
$assert(Automations::fingerprint($cart) === Automations::fingerprint($reverse), 'cart identity is independent of line order');
$reverse[0]['quantity'] = 2;
$assert(Automations::fingerprint($cart) !== Automations::fingerprint($reverse), 'cart quantity change invalidates previous snapshot');
$job = ['event_name' => 'ba_welcome_ready', 'stage' => 'welcome', 'entity_kind' => 'contact', 'delivery_key' => $key, 'mode' => 'test', 'site' => home_url()];
$assert($err(Automations::properties(array_replace($job, ['mode' => 'live']), []), 'event_environment_changed'), 'queued welcome cannot migrate between test and live mode');
$assert($err(Automations::properties(array_replace($job, ['site' => 'https://staging.bactiveph.com']), []), 'event_environment_changed'), 'queued event cannot migrate between site identities');
$assert($err(Automations::properties($job, []), 'welcome_offer_unavailable'), 'welcome waits until real coupon guard reports ready');
$assert($err(Automations::properties(array_replace($job, ['stage' => 'care']), []), 'invalid_stage'), 'event stage allowlist prevents wrong automation branch');
Automations::register();
$assert(!in_array('wp_mail', $GLOBALS['test_hooks'], true) && !in_array('phpmailer_init', $GLOBALS['test_hooks'], true), 'marketing hooks do not replace SMTP transport');
echo json_encode(['checks' => count($checks), 'passed' => $checks, 'network' => 'none']) . "\n";
