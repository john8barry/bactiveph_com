<?php
/** CLI-only template regression harness; never install in the web root. */
namespace BActive\PayMongo {
    class Gateway {
        public function is_available() {
            if ($GLOBALS['scenario'] === 'gateway-error') { throw new \RuntimeException('unavailable'); }
            return $GLOBALS['scenario'] === 'ready';
        }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('ABSPATH', __DIR__);
    error_reporting(E_ALL);
    set_error_handler(function ($severity, $message) { throw new \ErrorException($message, 0, $severity); });
    function WC() {
        if ($GLOBALS['scenario'] === 'no-commerce') { return null; }
        return new class {
            public function payment_gateways() {
                if ($GLOBALS['scenario'] === 'manager-error') { throw new \RuntimeException('unavailable'); }
                return new class {
                    public function payment_gateways() {
                        $gateways = array('cod'=>(object)array('enabled'=>$GLOBALS['scenario'] === 'all-disabled' ? 'no' : 'yes'));
                        if (!in_array($GLOBALS['scenario'], array('missing-gateway','all-disabled'), true)) {
                            $gateways['bactive_paymongo'] = new \BActive\PayMongo\Gateway();
                        }
                        return $gateways;
                    }
                };
            }
        };
    }
    function get_stylesheet_directory_uri() { return '/wp-content/themes/blocksy-child'; }
    function esc_url($url) { return htmlspecialchars($url, ENT_QUOTES); }
    function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES); }
    function assert_sage_payment_layout($html) {
        $selector = preg_quote('.bactive-custom-footer.bactive-footer--sage .bactive-trust__list--payments', '~');
        $desktop = '~@media\s*\(min-width:\s*768px\)\s*\{\s*' . $selector
            . '\s*\{\s*grid-template-columns:\s*repeat\(5,\s*minmax\(0,\s*1fr\)\);\s*\}\s*\}~';
        $mobile = '~@media\s*\(max-width:\s*767px\)\s*\{\s*' . $selector
            . '\s*\{\s*grid-template-columns:\s*repeat\(3,\s*minmax\(0,\s*1fr\)\);\s*\}\s*\}~';
        // Both rules must share a gap-free boundary, not merely exist somewhere in CSS.
        // Incumbent sage CSS otherwise leaves six columns between 600 and 767px.
        if (!preg_match($desktop, $html) || !preg_match($mobile, $html)) {
            throw new \RuntimeException('Sage payment column count or breakpoint regression');
        }
    }
    $template = $argv[1];
    $checks = array();
    $render_scenario = ($argv[2] ?? '') === 'render' ? ($argv[3] ?? 'ready') : null;
    $render_html = null;
    foreach (array('ready','not-ready','missing-gateway','gateway-error','manager-error','all-disabled','no-commerce') as $scenario) {
        $GLOBALS['scenario'] = $scenario;
        ob_start();
        include $template;
        $html = ob_get_clean();
        foreach (array('QR Ph','Maya','GrabPay','ShopeePay','PayMongo') as $name) {
            if (!str_contains($html, 'alt="'.$name.'"')) {
                throw new \RuntimeException($scenario . ': wrong payment mark ' . $name);
            }
        }
        $cod = !in_array($scenario, array('manager-error','all-disabled','no-commerce'), true);
        if (str_contains($html, 'alt="Cash on Delivery"') !== $cod) { throw new \RuntimeException($scenario . ': wrong COD availability'); }
        preg_match_all('/alt="(QR Ph|Maya|GrabPay|ShopeePay|BPI Online|UnionBank Online|Cash on Delivery|PayMongo)"/', $html, $marks);
        $expected_marks = array('QR Ph', 'Maya', 'GrabPay', 'ShopeePay');
        if ($cod) { $expected_marks[] = 'Cash on Delivery'; }
        $expected_marks[] = 'PayMongo';
        if ($marks[1] !== $expected_marks) { throw new \RuntimeException($scenario . ': wrong payment count or order'); }
        foreach (array('Online payments are being set up.', 'Eligibility and fees shown at checkout.') as $removed_note) {
            if (str_contains($html, $removed_note)) { throw new \RuntimeException($scenario . ': redundant footer note'); }
        }
        foreach (array('Visa','Mastercard','GCash','BPI','UnionBank','Ninja Van','Bank transfer') as $forbidden) {
            if (stripos($html, $forbidden) !== false) { throw new \RuntimeException('Forbidden mark ' . $forbidden); }
        }
        if (!str_contains($html, 'LBC Express') || !str_contains($html, 'J&T Express')) { throw new \RuntimeException('Courier missing'); }
        preg_match_all('/<a class="bactive-trust__badge bactive-trust__badge--carrier[^"]*" href="([^"]+)"/', $html, $couriers);
        if ($couriers[1] !== array('https://www.jtexpress.ph/track-and-trace', 'https://www.lbcexpress.com/ph/track', 'https://www.grab.com/ph/express/')) {
            throw new \RuntimeException($scenario . ': wrong courier count, order or destination');
        }
        preg_match('/aria-labelledby="bactive-shipping-label">(.*?)<\/div>/s', $html, $nationwide);
        preg_match('/aria-labelledby="bactive-local-shipping-label">(.*?)<\/div>/s', $html, $local);
        if (!isset($nationwide[1], $local[1]) || str_contains($nationwide[1], 'grabexpress')
            || substr_count($nationwide[1], '<li>') !== 2
            || !str_contains($local[1], '>Davao City only</span>')
            || !str_contains($local[1], 'aria-label="GrabExpress delivery within Davao City only (opens in a new tab)"')
            || substr_count($local[1], '<li>') !== 1
            || !str_contains($local[1], '/assets/images/couriers/grabexpress.png')
            || !str_contains($local[1], 'width="2868" height="800" alt=""')
            || !str_contains($local[1], 'target="_blank" rel="noopener noreferrer"')) {
            throw new \RuntimeException($scenario . ': GrabExpress must be explicitly local, accessible and unstretched');
        }
        preg_match('/<img[^>]+src="[^"]*\/assets\/images\/payments\/grabpay\.svg"[^>]*>/', $html, $grabpay);
        if (empty($grabpay[0]) || !str_contains($grabpay[0], 'width="121" height="49" alt="GrabPay"')) {
            throw new \RuntimeException($scenario . ': GrabPay must use the matching local asset and dimensions');
        }
        if (substr_count($html, '<img ') !== ($cod ? 9 : 8)) { throw new \RuntimeException($scenario . ': wrong total logo count'); }
        assert_sage_payment_layout($html);
        if (!str_contains($html, 'data-bactive-trust-version="2026-09-12-v6"')) { throw new \RuntimeException('Wrong combined release version'); }
        $checks[] = $scenario;
        if ($scenario === $render_scenario) { $render_html = $html; }
    }
    $layout_negative_checks = array();
    foreach (array(
        'missing-mobile-three-columns' => array('repeat(3, minmax(0, 1fr))', 'repeat(6, minmax(0, 1fr))'),
        'mobile-breakpoint-gap' => array('max-width: 767px', 'max-width: 599px'),
        'desktop-breakpoint-gap' => array('min-width: 768px', 'min-width: 769px'),
        'stale-bank-columns' => array('repeat(5, minmax(0, 1fr))', 'repeat(7, minmax(0, 1fr))'),
    ) as $name => $mutation) {
        $rejected = false;
        try { assert_sage_payment_layout(str_replace($mutation[0], $mutation[1], $html)); }
        catch (\RuntimeException $error) { $rejected = true; }
        if (!$rejected) { throw new \RuntimeException('Layout test missed ' . $name); }
        $layout_negative_checks[] = $name;
    }
    if ($render_scenario !== null) {
        if ($render_html === null) { throw new \InvalidArgumentException('Unknown render scenario'); }
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment branding verification</title><style>body{margin:0;background:#faf8f4;font-family:Arial,sans-serif;color:#2b2a28}.bactive-custom-footer{max-width:1180px;margin:64px auto;padding:24px}h1{font-size:24px;font-weight:500}p{line-height:1.5}</style><body><footer class="bactive-custom-footer"><h1>Shipping & payment options</h1><p>B Active · ' . esc_attr($render_scenario) . ' visual verification</p>' . $render_html . '</footer></body></html>';
        exit;
    }
    echo json_encode(array('passed'=>$checks, 'count'=>count($checks), 'layout_negative_passed'=>$layout_negative_checks)) . PHP_EOL;
}
