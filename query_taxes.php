<?php
require_once 'wp-load.php';
global $wpdb;
$rates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates", ARRAY_A);
print_r($rates);
