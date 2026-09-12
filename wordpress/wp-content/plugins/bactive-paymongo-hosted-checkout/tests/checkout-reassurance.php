<?php
// Exercise the exact rendering function from reviewed local source, without bootstrapping WP.
$reassurance_source = file_get_contents(dirname(__DIR__) . '/bactive-paymongo-hosted-checkout.php');
$reassurance_start = strpos($reassurance_source, 'function checkout_reassurance(): void');
$reassurance_end = strpos($reassurance_source, "\n/**", $reassurance_start);
check($reassurance_start !== false && $reassurance_end !== false, 'reassurance function source is available');
eval('namespace BActive\\PayMongo; ' . substr($reassurance_source, $reassurance_start, $reassurance_end - $reassurance_start));
if (!function_exists('esc_html')) {
    function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
$reassurance_wc_before = $fake_wc;
foreach (array('wallets', 'wallets-cod', 'cod-only', 'none', 'error', 'no-commerce') as $reassurance_case) {
    $fake_wc = $reassurance_case === 'no-commerce' ? null : new class($reassurance_case) {
        private string $scenario;
        public function __construct(string $scenario) { $this->scenario = $scenario; }
        public function payment_gateways() { return $this; }
        public function get_available_payment_gateways(): array {
            if ($this->scenario === 'error') { throw new RuntimeException('synthetic unavailable'); }
            $out = array();
            if (in_array($this->scenario, array('wallets', 'wallets-cod'), true)) { $out['bactive_paymongo'] = new stdClass(); }
            if (in_array($this->scenario, array('cod-only', 'wallets-cod'), true)) { $out['cod'] = new stdClass(); }
            return $out;
        }
    };
    ob_start();
    \BActive\PayMongo\checkout_reassurance();
    $reassurance_html = ob_get_clean();
    $reassurance_text = html_entity_decode(strip_tags($reassurance_html), ENT_QUOTES, 'UTF-8');
    same(in_array($reassurance_case, array('wallets', 'wallets-cod'), true), str_contains($reassurance_text, 'PayMongo: QRPh, Maya, ShopeePay & GrabPay'), 'qualified reassurance only when gateway available: ' . $reassurance_case);
    same(in_array($reassurance_case, array('cod-only', 'wallets-cod'), true), str_contains($reassurance_text, ' · COD · '), 'COD reassurance follows availability: ' . $reassurance_case);
    check(!preg_match('/BPI|UBP|UnionBank|GCash|cards/i', $reassurance_text), 'unqualified methods absent: ' . $reassurance_case);
    check(str_contains($reassurance_text, 'Secure checkout') && str_contains($reassurance_text, '7-day size-exchange guarantee'), 'existing reassurance retained: ' . $reassurance_case);
}
$fake_wc = $reassurance_wc_before;
