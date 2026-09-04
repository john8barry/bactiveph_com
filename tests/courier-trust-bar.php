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
    $template = $argv[1];
    $checks = array();
    foreach (array('ready','not-ready','missing-gateway','gateway-error','manager-error','all-disabled','no-commerce') as $scenario) {
        $GLOBALS['scenario'] = $scenario;
        ob_start();
        include $template;
        $html = ob_get_clean();
        $ready = $scenario === 'ready';
        foreach (array('QR Ph','Maya','ShopeePay','BPI Online','UnionBank Online','PayMongo') as $name) {
            if (str_contains($html, 'alt="'.$name.'"') !== $ready) {
                throw new \RuntimeException($scenario . ': wrong payment mark ' . $name);
            }
        }
        $cod = !in_array($scenario, array('manager-error','all-disabled','no-commerce'), true);
        if (str_contains($html, '>COD</span>') !== $cod) { throw new \RuntimeException($scenario . ': wrong COD availability'); }
        foreach (array('Visa','Mastercard','GCash','Ninja Van','Bank transfer') as $forbidden) {
            if (stripos($html, $forbidden) !== false) { throw new \RuntimeException('Forbidden mark ' . $forbidden); }
        }
        if (!str_contains($html, 'LBC Express') || !str_contains($html, 'J&T Express')) { throw new \RuntimeException('Courier missing'); }
        $checks[] = $scenario;
        if ($ready && ($argv[2] ?? '') === 'render') {
            echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment branding verification</title><style>body{margin:0;background:#f9f7f2;font-family:Arial,sans-serif;color:#2b2a28}.bactive-custom-footer{max-width:1180px;margin:64px auto;padding:24px}h1{font-size:24px;font-weight:500}p{line-height:1.5}</style><body><footer class="bactive-custom-footer"><h1>Shipping & payment options</h1><p>B Active · ready-state visual verification</p>' . $html . '</footer></body></html>';
            exit;
        }
    }
    echo json_encode(array('passed'=>$checks, 'count'=>count($checks))) . PHP_EOL;
}
