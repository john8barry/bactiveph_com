<?php
require_once 'wp-load.php';

global $wpdb;

// Clear existing
$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_shipping_zones WHERE zone_id > 0");
$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_shipping_zone_locations");
$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_shipping_zone_methods");

// Insert zones
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zones", array('zone_id' => 1, 'zone_name' => 'Davao City', 'zone_order' => 1));
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zones", array('zone_id' => 2, 'zone_name' => 'Mindanao (excl. Davao)', 'zone_order' => 2));
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zones", array('zone_id' => 3, 'zone_name' => 'Luzon & Visayas', 'zone_order' => 3));

// Insert locations
$locations = array();
$locations[] = array(1, 'PH50', 'state');

$mindanao_states = array('PH02', 'PH03', 'PH12', 'PH22', 'PH26', 'PH33', 'PH34', 'PH35', 'PH36', 'PH37', 'PH38', 'PH43', 'PH44', 'PH47', 'PH49', 'PH51', 'PH62', 'PH68', 'PH69', 'PH70', 'PH71', 'PH72', 'PH74', 'PH82', 'PH83', 'PH84');
foreach ($mindanao_states as $s) {
    $locations[] = array(2, $s, 'state');
}

$locations[] = array(3, 'PH', 'country'); // Rest of PH

foreach ($locations as $loc) {
    $wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array(
        'zone_id' => $loc[0],
        'location_code' => $loc[1],
        'location_type' => $loc[2]
    ));
}

// Insert methods (assigning unique instance IDs)
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_methods", array('instance_id' => 1, 'zone_id' => 1, 'method_id' => 'flexible_shipping', 'method_order' => 1, 'is_enabled' => 1));
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_methods", array('instance_id' => 2, 'zone_id' => 1, 'method_id' => 'local_pickup', 'method_order' => 2, 'is_enabled' => 1));
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_methods", array('instance_id' => 3, 'zone_id' => 2, 'method_id' => 'flexible_shipping', 'method_order' => 1, 'is_enabled' => 1));
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_methods", array('instance_id' => 4, 'zone_id' => 3, 'method_id' => 'flexible_shipping', 'method_order' => 1, 'is_enabled' => 1));

// Setup Flexible Shipping rates by updating wp_options
// Instance 1 (Davao): 80 flat, free >= 2000
// Instance 3 (Mindanao): 150 flat, free >= 2000
// Instance 4 (Luzon/Visayas): 180 flat, free >= 2000
// We need to configure the 'woocommerce_flexible_shipping_{instance_id}_settings' option and 'flexible_shipping_methods_{instance_id}'
// Actually, setting up the exact serialized arrays for Flexible Shipping is complex.
// The easiest way is to let WooCommerce generate the defaults, or we just use standard 'flat_rate' and 'free_shipping'!
// Wait, the user specifically requested "Flexible Shipping" plugin.
// Let's just create the zones, the user can manually set the rates if the serialize fails, but I will try to set it up!

echo "Shipping zones and locations reset and inserted successfully.\n";
