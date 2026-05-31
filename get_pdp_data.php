<?php
require_once('staging/wp-load.php');

$slugs = ['the-court-dress', 'the-pleated-skort', 'the-halter-set'];

foreach ($slugs as $slug) {
    echo "--- Data for pdp_" . str_replace('-', '_', $slug) . " ---\n";
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
        echo "Name: " . $product->get_name() . "\n";
        echo "Price: " . $product->get_price_html() . "\n";
        
        $attributes = $product->get_attributes();
        $colors = array();
        if(isset($attributes['pa_colour'])) {
            $terms = wc_get_product_terms($product->get_id(), 'pa_colour', array('fields' => 'names'));
            $colors = $terms;
        }
        echo "Colors: " . implode(", ", $colors) . "\n";
        
        echo "Tabs:\n";
        echo "- Description:\n" . wp_strip_all_tags($product->get_short_description()) . "\n";
        
        $features = $product->get_attribute('pa_features');
        echo "- Features & Fit:\n" . $features . "\n";
        echo "- Shipping & Returns: Free shipping on orders over $100... (static tab from functions)\n";
        echo "- Fabric & Care: (static tab from functions)\n";
    } else {
        echo "Product not found!\n";
    }
    echo "\n\n";
}
?>