import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php run_swatches_internal.php 2>&1');
?>"""

with open('run_swatches_internal.php', 'w') as f:
    f.write("""<?php
require_once('wp-load.php');

global $wpdb;

// 1. Update Attribute Types
$wpdb->update(
    $wpdb->prefix . 'woocommerce_attribute_taxonomies',
    ['attribute_type' => 'color'],
    ['attribute_name' => 'colour']
);

$wpdb->update(
    $wpdb->prefix . 'woocommerce_attribute_taxonomies',
    ['attribute_type' => 'button'],
    ['attribute_name' => 'size']
);

// Clear transients
delete_transient('wc_attribute_taxonomies');

// 2. Set Hex Colors for Colour Terms
$colors = [
    'Court Ivory' => '#FAF8F4',
    'Midnight' => '#1F2A44',
    'Onyx' => '#1C1B19',
    'Sakura' => '#E9C3CA',
    'Powder' => '#BFD2E0',
    'Sagewood' => '#9CAE92',
    'Wisteria' => '#B9A7C9',
    'Stone' => '#9A958C',
    'Apricot' => '#E7B58E',
    'Almond' => '#C9B7A4',
    'Bloom' => '#C65F8E',
    'Clay Red' => '#A9544A',
    'Night Indigo' => '#1F2A44',
    'Meadow Green' => '#9CAE92',
    'Oil Blue' => '#1F2A44',
    'Green Jasper' => '#9CAE92'
];

foreach ($colors as $name => $hex) {
    $term = get_term_by('name', $name, 'pa_colour');
    if ($term) {
        update_term_meta($term->term_id, 'product_attribute_color', $hex);
    }
}

echo "Swatches configured successfully!";
?>""")

with open('run_swatches_root.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('run_swatches_internal.php', 'rb') as f:
        ftp.storbinary('STOR staging/run_swatches_internal.php', f)
    with open('run_swatches_root.php', 'rb') as f:
        ftp.storbinary('STOR run_swatches_root.php', f)
    
    response = requests.get('https://bactiveph.com/run_swatches_root.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('staging/run_swatches_internal.php')
    ftp.delete('run_swatches_root.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
