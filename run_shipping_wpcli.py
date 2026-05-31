import os
import ftplib
import requests
import uuid
import sys
from dotenv import load_dotenv

load_dotenv()

php_script = """<?php
// Since we run via WP-CLI eval-file, WP is already loaded.

try {
    // Clear existing shipping zones (except Rest of the World)
    $zones = WC_Shipping_Zones::get_zones();
    foreach ($zones as $zone_data) {
        $zone = new WC_Shipping_Zone($zone_data['zone_id']);
        $zone->delete();
    }

    // 1. Davao City Zone
    $zone_davao = new WC_Shipping_Zone();
    $zone_davao->set_zone_name('Davao City');
    $zone_davao->add_location('8000', 'postcode');
    $zone_davao->save();

    // Add Flexible Shipping for Davao
    $instance_id = $zone_davao->add_shipping_method('flexible_shipping');
    if ($instance_id) {
        $option_key = 'woocommerce_flexible_shipping_' . $instance_id . '_settings';
        $settings = array(
            'title' => 'Standard Delivery (Davao City)',
            'method_free_shipping_requires' => 'min_amount',
            'method_free_shipping_amount' => '2000'
        );
        update_option($option_key, $settings);
    }
    
    // Add Local Pickup
    $pickup_id = $zone_davao->add_shipping_method('local_pickup');
    if ($pickup_id) {
        $option_key = 'woocommerce_local_pickup_' . $pickup_id . '_settings';
        $settings = array(
            'title' => 'Local Pickup',
            'cost' => '0',
            'tax_status' => 'none'
        );
        update_option($option_key, $settings);
    }

    // 2. Mindanao Zone
    $zone_mindanao = new WC_Shipping_Zone();
    $zone_mindanao->set_zone_name('Mindanao (excl. Davao)');
    $mindanao_states = ['PH02', 'PH03', 'PH85', 'PH13', 'PH46', 'PH86', 'PH48', 'PH87', 'PH88', 'PH49', 'PH51', 'PH52', 'PH89', 'PH90', 'PH59', 'PH60', 'PH62', 'PH65', 'PH66', 'PH67', 'PH97', 'PH68', 'PH70', 'PH72', 'PH73', 'PH74', 'PH96', 'PH75', 'PH76'];
    foreach ($mindanao_states as $state) {
        $zone_mindanao->add_location($state, 'state');
    }
    $zone_mindanao->save();

    $instance_id = $zone_mindanao->add_shipping_method('flexible_shipping');
    if ($instance_id) {
        $option_key = 'woocommerce_flexible_shipping_' . $instance_id . '_settings';
        $settings = array(
            'title' => 'Standard Delivery (Mindanao)',
            'method_free_shipping_requires' => 'min_amount',
            'method_free_shipping_amount' => '2000'
        );
        update_option($option_key, $settings);
    }

    // 3. Luzon & Visayas
    $zone_lz = new WC_Shipping_Zone();
    $zone_lz->set_zone_name('Luzon & Visayas');
    $zone_lz->add_location('PH', 'country');
    $zone_lz->save();

    $instance_id = $zone_lz->add_shipping_method('flexible_shipping');
    if ($instance_id) {
        $option_key = 'woocommerce_flexible_shipping_' . $instance_id . '_settings';
        $settings = array(
            'title' => 'Standard Delivery (Luzon & Visayas)',
            'method_free_shipping_requires' => 'min_amount',
            'method_free_shipping_amount' => '2000'
        );
        update_option($option_key, $settings);
    }

    echo "Shipping zones configured successfully.\\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\\n";
}
?>"""

script_name = f"eval_{uuid.uuid4().hex[:8]}.php"

with open(script_name, 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
    ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
    ftp.cwd('staging.bactiveph.com')
    with open(script_name, 'rb') as f:
        ftp.storbinary(f'STOR {script_name}', f)
        
    print(f"Uploaded {script_name}")
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass

# Now run it via WP-CLI using the existing run_command_remote.py logic
# run_command_remote.py executes a shell command. We will run `php wp-cli.phar eval-file <script>`
cmd = f"php wp-cli.phar eval-file {script_name} && rm {script_name}"
print(f"Executing: {cmd}")
import subprocess
result = subprocess.run(["python3", "run_command_remote.py", cmd], capture_output=True, text=True)
print(result.stdout)
if result.stderr:
    print("STDERR:", result.stderr)

os.remove(script_name)
