<?php
require 'wp-load.php';

// 1. Clear existing locations
global $wpdb;
$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}woocommerce_shipping_zone_locations");

// 2. Define our 3 zones
$zone1_id = 1; // Davao City
$zone2_id = 2; // Mindanao
$zone3_id = 3; // Luzon & Visayas

// Mindanao states
$mindanao = array('AGN', 'AGS', 'BAS', 'BUK', 'CAM', 'COM', 'NCO', 'DAV', 'DAS', 'DAC', 'DAO', 'DIN', 'LAN', 'LAS', 'MAG', 'MSC', 'MSR', 'SAR', 'SCO', 'SUK', 'SLU', 'SUN', 'SUR', 'TAW', 'ZAN', 'ZAS', 'ZSI');

// All PH states
$all_ph = array_keys(WC()->countries->get_states('PH'));

// Zone 1: Let's use DAS (Davao del Sur) to represent Davao City. Or we can just use postcode '8000'.
// Let's use postcode '8000' and '80*' to be extremely accurate for Davao City.
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array('zone_id' => $zone1_id, 'location_code' => '80*', 'location_type' => 'postcode'));
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array('zone_id' => $zone1_id, 'location_code' => '8000', 'location_type' => 'postcode'));

// Also add DAS state to Zone 1 as fallback? No, let's keep Zone 1 strictly Postcode 80* for Davao City, and also DAS just in case.
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array('zone_id' => $zone1_id, 'location_code' => 'PH:DAS', 'location_type' => 'state'));

// Zone 2: All Mindanao states
foreach ($mindanao as $state) {
    if ($state !== 'DAS') { // exclude DAS to strictly keep it in zone 1
        $wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array('zone_id' => $zone2_id, 'location_code' => "PH:$state", 'location_type' => 'state'));
    }
}

// Zone 3: Luzon & Visayas (all other states not in Mindanao)
foreach ($all_ph as $state) {
    if (!in_array($state, $mindanao) && $state !== '00') { // 00 is Metro Manila, we should include it in Zone 3
        $wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array('zone_id' => $zone3_id, 'location_code' => "PH:$state", 'location_type' => 'state'));
    }
}
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array('zone_id' => $zone3_id, 'location_code' => "PH:00", 'location_type' => 'state')); // Metro Manila

echo "Shipping locations mapped correctly.\n";
