<?php
require_once 'wp-load.php';

$address = array(
    'first_name' => 'John',
    'last_name'  => 'Barry',
    'company'    => '',
    'email'      => 'john@example.com',
    'phone'      => '09171234567',
    'address_1'  => '123 Fake St',
    'address_2'  => '',
    'city'       => 'Davao City',
    'state'      => 'PH50',
    'postcode'   => '8000',
    'country'    => 'PH'
);

// Create order
$order = wc_create_order();
$order->add_product( get_product( 38 ), 1 ); // Add variation 38
$order->set_address( $address, 'billing' );
$order->set_address( $address, 'shipping' );
$order->calculate_totals();
$order->update_status('processing', 'Order created programmatically', TRUE);

$order_id = $order->get_id();
$order_key = $order->get_order_key();

echo "https://staging.bactiveph.com/checkout/order-received/{$order_id}/?key={$order_key}\n";
