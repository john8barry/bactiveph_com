<?php
// Isolated public-boundary tests. No WordPress, network, mail or database.
namespace {
    define('ABSPATH', '/fixture/');
    $GLOBALS['options'] = ['enabled' => false, 'test_mode' => true, 'launch_cutoff' => 123];
    $GLOBALS['subscribe_calls'] = 0;
    function get_option($key, $default = null) { return $GLOBALS['options']; }
    function current_user_can($cap) { return $GLOBALS['can_manage'] ?? true; }
    function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
    function shortcode_atts($defaults, $actual, $tag) { return array_merge($defaults, (array) $actual); }
    function wp_unique_id($prefix) { static $n = 0; return $prefix . ++$n; }
    function wp_enqueue_script($name) {}
    function esc_url($s) { return htmlspecialchars($s, ENT_QUOTES); }
    function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES); }
    function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
    function wp_nonce_field($a, $b, $c) { echo '<input name="' . $b . '" value="nonce">'; }
    function admin_url($s) { return 'https://bactiveph.com/wp-admin/' . $s; }
    function home_url($s) { return 'https://bactiveph.com' . $s; }
    function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($s)); }
    function wp_unslash($s) { return stripslashes($s); }
    function nocache_headers() {}
    function wp_verify_nonce($n, $a) { return $n === 'nonce'; }
    function is_wp_error($v) { return $v instanceof WP_Error; }
    class WP_Error { public function __construct(...$args) {} }
    class JsonResponse extends \Exception { public array $result; public int $status; }
    function wp_send_json($data, $status) { $e = new JsonResponse(); $e->result = $data; $e->status = $status; throw $e; }
}
namespace Bactive\Brevo {
    class Config {
        public static function readiness($events = true) { return ['ready' => self::enabled()]; }
        public static function enabled() { return $GLOBALS['enabled'] ?? false; }
        public static function get($name, $default = null) { return $GLOBALS['options'][$name] ?? $default; }
    }
    class Consent {
        public static function status() { return ['state' => $GLOBALS['consent_state'] ?? 'unknown']; }
        public static function subscribe($email, $source, $token, $consent = false) { $GLOBALS['subscribe_calls']++; return ['state' => 'pending']; }
    }
    require __DIR__ . '/../includes/frontend.php';
    require __DIR__ . '/../includes/admin.php';
    function check($result, $message) { if (!$result) throw new \RuntimeException($message); }
    check(strpos(Frontend::shortcode(), '<form') === false, 'Disabled signup must not render a form.');
    $GLOBALS['enabled'] = true;
    $GLOBALS['options']['turnstile_site_key'] = '\"><script>alert(1)</script>';
    $html = Frontend::shortcode(['source' => '\"><script>alert(1)</script>']);
    check(strpos($html, '<script>') === false, 'Site key/source must be escaped.');
    check(strpos($html, 'checked') === false, 'Consent must be unchecked.');
    check(strpos($html, 'name="source" value="homepage"') !== false, 'Unknown source must use allowlisted fallback.');
    $_GET = ['ba_signup' => 'confirmed'];
    check(strpos(Frontend::shortcode(), 'You’re on the list') === false, 'Query alone must not claim confirmed.');
    $GLOBALS['consent_state'] = 'confirmed';
    check(strpos(Frontend::shortcode(), 'You’re on the list') !== false, 'Verified identity receives confirmation feedback.');
    $_GET = ['ba_signup' => 'expired'];
    check(strpos(Frontend::shortcode(), 'has expired') !== false, 'Expired links show a recovery action.');
    $_GET = [];
    $html2 = Frontend::shortcode();
    preg_match('/id="([^"]+)email"/', $html, $a);
    preg_match('/id="([^"]+)email"/', $html2, $b);
    check($a[1] !== $b[1], 'Multiple forms need unique label/input IDs.');
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_ACCEPT' => 'application/json'];
    $valid = ['ba_signup_nonce' => 'nonce', 'email' => 'test@example.invalid', 'source' => 'footer', 'consent' => '1', 'cf-turnstile-response' => 'token'];
    foreach ([['consent' => ''], ['ba_signup_nonce' => 'bad'], ['email' => ['array']], ['source' => 'arbitrary'], ['cf-turnstile-response' => str_repeat('x', 2049)]] as $bad) {
        $_POST = array_merge($valid, $bad);
        try { Frontend::submit(); } catch (\JsonResponse $e) { check($e->status === 400, 'Invalid request must fail.'); }
    }
    check($GLOBALS['subscribe_calls'] === 0, 'Invalid inputs must not call the provider adapter.');
    $_POST = $valid;
    try { Frontend::submit(); } catch (\JsonResponse $e) { check($e->status === 200 && $e->result['ok'], 'Valid request must pass explicit consent.'); }
    check($GLOBALS['subscribe_calls'] === 1, 'One signup per valid submission.');
    $saved = Admin::sanitize(['enabled' => true, 'test_mode' => false, 'launch_cutoff' => 0, 'api_key' => 'never-save', 'daily_event_cap' => 9999, 'test_recipients' => "valid@example.invalid\nnot-an-email"]);
    check($saved['enabled'] === false && $saved['test_mode'] === true && $saved['launch_cutoff'] === 123 && !isset($saved['api_key']), 'Settings cannot enable live mail or accept a secret.');
    check($saved['daily_event_cap'] === 100 && $saved['test_recipients'] === ['valid@example.invalid'], 'Settings enforce quota ceiling and exact valid recipients.');
    $GLOBALS['can_manage'] = false;
    check(Admin::sanitize(['coupon_id' => 123]) === $GLOBALS['options'], 'Unauthorized settings save cannot change configuration.');
    echo "Frontend and admin boundary tests passed.\n";
}
