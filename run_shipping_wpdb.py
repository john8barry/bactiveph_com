import os
import ftplib
import requests
import uuid
from dotenv import load_dotenv

load_dotenv()

php_script = """<?php
require 'wp-load.php';
global $wpdb;

$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_shipping_zones WHERE zone_id > 0");
$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_shipping_zone_locations");
$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_shipping_zone_methods");

// 1. Davao City
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zones", array('zone_name' => 'Davao City', 'zone_order' => 1));
$zone_davao_id = $wpdb->insert_id;
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array('zone_id' => $zone_davao_id, 'location_code' => '8000', 'location_type' => 'postcode'));

$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_methods", array('zone_id' => $zone_davao_id, 'method_id' => 'flexible_shipping', 'method_order' => 1, 'is_active' => 1));
$method_davao_flat = $wpdb->insert_id;
update_option('woocommerce_flexible_shipping_' . $method_davao_flat . '_settings', array(
    'title' => 'Standard Delivery (Davao City)',
    'method_free_shipping_requires' => 'min_amount',
    'method_free_shipping_amount' => '2000'
));

// Local pickup
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_methods", array('zone_id' => $zone_davao_id, 'method_id' => 'local_pickup', 'method_order' => 2, 'is_active' => 1));
$method_davao_pickup = $wpdb->insert_id;
update_option('woocommerce_local_pickup_' . $method_davao_pickup . '_settings', array(
    'title' => 'Local Pickup',
    'cost' => '0',
    'tax_status' => 'none'
));

// 2. Mindanao (Excl Davao)
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zones", array('zone_name' => 'Mindanao (excl. Davao)', 'zone_order' => 2));
$zone_min_id = $wpdb->insert_id;
$mindanao_states = ['PH02', 'PH03', 'PH85', 'PH13', 'PH46', 'PH86', 'PH48', 'PH87', 'PH88', 'PH49', 'PH51', 'PH52', 'PH89', 'PH90', 'PH59', 'PH60', 'PH62', 'PH65', 'PH66', 'PH67', 'PH97', 'PH68', 'PH70', 'PH72', 'PH73', 'PH74', 'PH96', 'PH75', 'PH76'];
foreach ($mindanao_states as $state) {
    $wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array('zone_id' => $zone_min_id, 'location_code' => $state, 'location_type' => 'state'));
}

$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_methods", array('zone_id' => $zone_min_id, 'method_id' => 'flexible_shipping', 'method_order' => 1, 'is_active' => 1));
$method_min_flat = $wpdb->insert_id;
update_option('woocommerce_flexible_shipping_' . $method_min_flat . '_settings', array(
    'title' => 'Standard Delivery (Mindanao)',
    'method_free_shipping_requires' => 'min_amount',
    'method_free_shipping_amount' => '2000'
));

// 3. Luzon & Visayas
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zones", array('zone_name' => 'Luzon & Visayas', 'zone_order' => 3));
$zone_lz_id = $wpdb->insert_id;
$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_locations", array('zone_id' => $zone_lz_id, 'location_code' => 'PH', 'location_type' => 'country'));

$wpdb->insert("{$wpdb->prefix}woocommerce_shipping_zone_methods", array('zone_id' => $zone_lz_id, 'method_id' => 'flexible_shipping', 'method_order' => 1, 'is_active' => 1));
$method_lz_flat = $wpdb->insert_id;
update_option('woocommerce_flexible_shipping_' . $method_lz_flat . '_settings', array(
    'title' => 'Standard Delivery (Luzon & Visayas)',
    'method_free_shipping_requires' => 'min_amount',
    'method_free_shipping_amount' => '2000'
));

echo "Shipping zones SQL complete.";
?>"""

script_name = f"setup_{uuid.uuid4().hex[:8]}.php"

with open(script_name, 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
    ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
    ftp.cwd('staging.bactiveph.com')
    with open(script_name, 'rb') as f:
        ftp.storbinary(f'STOR {script_name}', f)
    
    response = requests.get(f'https://staging.bactiveph.com/{script_name}?nocache=1', verify=False, auth=('bactive_team', 'BActive_Stg_2026!'))
    print(response.text)
    
    ftp.delete(script_name)
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass
    os.remove(script_name)
