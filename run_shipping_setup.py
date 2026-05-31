import os
import ftplib
import requests
import uuid
from dotenv import load_dotenv

load_dotenv()

php_script = """<?php
require 'wp-load.php';

// Clear existing shipping zones (except Rest of the World)
$zones = WC_Shipping_Zones::get_zones();
foreach ($zones as $zone_data) {
    $zone = new WC_Shipping_Zone($zone_data['id']);
    $zone->delete();
}

// 1. Davao City Zone
$zone_davao = new WC_Shipping_Zone();
$zone_davao->set_zone_name('Davao City');
$zone_davao->add_location('PH50', 'state'); // Davao del Sur (usually Davao City is mapped here, or we can use specific postcodes)
// Assuming PH50 is the state code. Let's add postcode matching for Davao City: 8000
$zone_davao->add_location('8000', 'postcode');
$zone_davao->save();

// Add Flat Rate for Davao
$instance_id = $zone_davao->add_shipping_method('flat_rate');
$flat_rate = new WC_Shipping_Flat_Rate($instance_id);
$flat_rate->init_instance_settings();
$flat_rate->instance_settings['title'] = 'Standard Delivery (Davao City)';
$flat_rate->instance_settings['cost'] = '80';
update_option($flat_rate->get_instance_option_key(), $flat_rate->instance_settings);

// Add Local Pickup
$pickup_id = $zone_davao->add_shipping_method('local_pickup');
$pickup = new WC_Shipping_Local_Pickup($pickup_id);
$pickup->init_instance_settings();
$pickup->instance_settings['title'] = 'Local Pickup';
$pickup->instance_settings['cost'] = '0';
update_option($pickup->get_instance_option_key(), $pickup->instance_settings);

// Add Free Shipping >= 2000
$free_id = $zone_davao->add_shipping_method('free_shipping');
$free_shipping = new WC_Shipping_Free_Shipping($free_id);
$free_shipping->init_instance_settings();
$free_shipping->instance_settings['title'] = 'Free Shipping';
$free_shipping->instance_settings['requires'] = 'min_amount';
$free_shipping->instance_settings['min_amount'] = '2000';
update_option($free_shipping->get_instance_option_key(), $free_shipping->instance_settings);


// 2. Mindanao (Excl Davao) Zone
$zone_mindanao = new WC_Shipping_Zone();
$zone_mindanao->set_zone_name('Mindanao (excl. Davao)');
// Let's add other Mindanao states
$mindanao_states = ['PH02', 'PH03', 'PH85', 'PH13', 'PH46', 'PH86', 'PH48', 'PH87', 'PH88', 'PH49', 'PH51', 'PH52', 'PH89', 'PH90', 'PH59', 'PH60', 'PH62', 'PH65', 'PH66', 'PH67', 'PH97', 'PH68', 'PH70', 'PH72', 'PH73', 'PH74', 'PH96', 'PH75', 'PH76']; // This is just an approximation for Mindanao states
foreach ($mindanao_states as $state) {
    $zone_mindanao->add_location($state, 'state');
}
$zone_mindanao->save();

$instance_id = $zone_mindanao->add_shipping_method('flat_rate');
$flat_rate = new WC_Shipping_Flat_Rate($instance_id);
$flat_rate->init_instance_settings();
$flat_rate->instance_settings['title'] = 'Standard Delivery (Mindanao)';
$flat_rate->instance_settings['cost'] = '150';
update_option($flat_rate->get_instance_option_key(), $flat_rate->instance_settings);

$free_id = $zone_mindanao->add_shipping_method('free_shipping');
$free_shipping = new WC_Shipping_Free_Shipping($free_id);
$free_shipping->init_instance_settings();
$free_shipping->instance_settings['title'] = 'Free Shipping';
$free_shipping->instance_settings['requires'] = 'min_amount';
$free_shipping->instance_settings['min_amount'] = '2000';
update_option($free_shipping->get_instance_option_key(), $free_shipping->instance_settings);


// 3. Luzon & Visayas Zone
$zone_lz = new WC_Shipping_Zone();
$zone_lz->set_zone_name('Luzon & Visayas');
// Since it's basically the rest of PH, we can just make it apply to country PH
$zone_lz->add_location('PH', 'country');
$zone_lz->save();

$instance_id = $zone_lz->add_shipping_method('flat_rate');
$flat_rate = new WC_Shipping_Flat_Rate($instance_id);
$flat_rate->init_instance_settings();
$flat_rate->instance_settings['title'] = 'Standard Delivery (Luzon & Visayas)';
$flat_rate->instance_settings['cost'] = '180';
update_option($flat_rate->get_instance_option_key(), $flat_rate->instance_settings);

$free_id = $zone_lz->add_shipping_method('free_shipping');
$free_shipping = new WC_Shipping_Free_Shipping($free_id);
$free_shipping->init_instance_settings();
$free_shipping->instance_settings['title'] = 'Free Shipping';
$free_shipping->instance_settings['requires'] = 'min_amount';
$free_shipping->instance_settings['min_amount'] = '2000';
update_option($free_shipping->get_instance_option_key(), $free_shipping->instance_settings);


// Also update Rest of World to be catch all weight based if needed (flat rate 200 for now)
$zone_rest = new WC_Shipping_Zone(0); // 0 is Rest of World
$instance_id = $zone_rest->add_shipping_method('flat_rate');
$flat_rate = new WC_Shipping_Flat_Rate($instance_id);
$flat_rate->init_instance_settings();
$flat_rate->instance_settings['title'] = 'Standard Delivery (Rest of PH/World)';
$flat_rate->instance_settings['cost'] = '200';
update_option($flat_rate->get_instance_option_key(), $flat_rate->instance_settings);

echo "Shipping zones configured.\n";
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
