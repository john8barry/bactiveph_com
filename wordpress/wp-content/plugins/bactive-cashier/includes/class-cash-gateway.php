<?php
namespace BActive\Cashier;
defined('ABSPATH') || exit;
final class Cash_Gateway extends \WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'bactive_in_store_cash';
        $this->method_title = 'Cash received in store';
        $this->title = 'Cash received in store';
        $this->enabled = 'yes';
        $this->has_fields = false;
        $this->supports = array('products');
    }
    // Recording cash is permitted exclusively through the authenticated cashier endpoint.
    public function is_available() { return false; }
    public function process_payment($order_id) { return array('result' => 'failure'); }
}
