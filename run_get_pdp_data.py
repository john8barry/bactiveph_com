import ftplib
import requests

php_script = """<?php
require_once('staging/wp-load.php');

$slugs = ['the-court-dress', 'the-pleated-skort', 'the-halter-set'];

foreach ($slugs as $slug) {
    echo "--- Data for pdp_" . str_replace('-', '_', $slug) . " ---\\n";
    $product_id = wc_get_product_id_by_sku(''); // Not sku, by slug
    
    // get product by slug
    $args = array(
        'name'        => $slug,
        'post_type'   => 'product',
        'post_status' => 'publish',
        'numberposts' => 1
    );
    $products = get_posts($args);
    
    if($products) {
        $product = wc_get_product($products[0]->ID);
        echo "Name: " . $product->get_name() . "\\n";
        echo "Price: " . $product->get_price_html() . "\\n";
        
        $attributes = $product->get_attributes();
        $colors = array();
        if(isset($attributes['pa_colour'])) {
            $terms = wc_get_product_terms($product->get_id(), 'pa_colour', array('fields' => 'names'));
            $colors = $terms;
        }
        echo "Colors: " . implode(", ", $colors) . "\\n";
        
        echo "Tabs:\\n";
        echo "- Description:\\n" . wp_strip_all_tags($product->get_short_description()) . "\\n";
        
        $features = $product->get_attribute('pa_features');
        echo "- Features & Fit:\\n" . $features . "\\n";
        echo "- Shipping & Returns: Free shipping on orders over $100... (static tab from functions)\\n";
        echo "- Fabric & Care: (static tab from functions)\\n";
    } else {
        echo "Product not found!\\n";
    }
    echo "\\n\\n";
}
?>"""

with open('get_pdp_data.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('get_pdp_data.php', 'rb') as f:
        ftp.storbinary('STOR get_pdp_data.php', f)
        
    print("Executing extraction script...")
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()

response = requests.get('https://bactiveph.com/get_pdp_data.php', timeout=300)
print(response.text)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    ftp.delete('get_pdp_data.php')
except Exception as e:
    pass
finally:
    ftp.quit()

