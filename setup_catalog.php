<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

$products_data = json_decode('[{"sku": "YY9141", "name": "The Court Dress", "price": "1950", "category": "Pickleball Dresses", "tags": ["The Court Edit"], "colors": ["Court Ivory", "Wisteria", "Stone"], "short_desc": "Move freely, look effortless. Our signature pleated pickleball dress pairs a flattering silhouette with serious performance.", "features": "CourtSoft\u2122 four-way stretch \u2022 Sweat-wicking, buttery-soft \u2022 Built-in shorts (4\" inseam) with ball pocket \u2022 Built-in light-support bra with removable pads \u2022 Pleated skirt that holds its shape \u2022 Asian fit, S\u2013XL", "fit": "True to size (Asian fit), S\u2013XL. Model is 165 cm, wears M."}, {"sku": "YY8793", "name": "The Rally Dress", "price": "1890", "category": "Pickleball Dresses", "tags": ["The Court Edit"], "colors": ["Midnight"], "short_desc": "Open-back ease, on-court confidence. A scoop-back dress with a side tie that skims and flatters.", "features": "CourtSoft\u2122 four-way stretch \u2022 Sweat-wicking \u2022 Built-in shorts with ball pocket \u2022 Flattering scoop back \u2022 Asian fit, S\u2013XL", "fit": "True to size (Asian fit), S\u2013XL. Model is 168 cm, wears M."}, {"sku": "YY4001", "name": "The Bubble Dress", "price": "2190", "category": "Pickleball Dresses", "tags": ["The Court Edit"], "colors": ["Court Ivory", "Sakura", "Powder", "Wisteria", "Onyx"], "short_desc": "The dress everyone asks about. A sculpted bodice meets a playful bubble hem \u2014 court-ready, caf\u00e9-ready.", "features": "CourtSoft\u2122 four-way stretch \u2022 Built-in shorts with ball pocket \u2022 Built-in bra, removable pads \u2022 Statement bubble hem \u2022 Asian fit, S\u2013XL", "fit": "True to size (Asian fit), S\u2013XL. Model is 165 cm, wears M."}, {"sku": "AS5019", "name": "The Match Dress", "price": "2450", "category": "Pickleball Dresses", "tags": ["The Court Edit"], "colors": ["Court Ivory"]}, {"sku": "AS5028", "name": "The Serve Dress", "price": "2450", "category": "Pickleball Dresses", "tags": ["The Court Edit"], "colors": ["Sakura"]}, {"sku": "YY9187", "name": "The Eyelet Dress", "price": "2450", "category": "Pickleball Dresses", "tags": ["The Court Edit"], "colors": ["Onyx", "Court Ivory", "Powder"]}, {"sku": "AS818", "name": "The Varsity Dress", "price": "2650", "category": "Pickleball Dresses", "tags": ["The Court Edit"], "colors": ["Sagewood"], "short_desc": "Preppy meets performance. A collared dress with crisp contrast trim and a pleated skirt that means business.", "features": "CourtSoft\u2122 four-way stretch \u2022 Collared, contrast-trim detail \u2022 Built-in shorts with ball pocket \u2022 Built-in support bra \u2022 Asian fit, S\u2013XL", "fit": "True to size (Asian fit), S\u2013XL. Model is 167 cm, wears M."}, {"sku": "AS811", "name": "The Ace Dress", "price": "2650", "category": "Pickleball Dresses", "tags": ["The Court Edit"], "colors": ["Apricot", "Clay Red"]}, {"sku": "DK21-015", "name": "The Everyday Skort", "price": "990", "category": "Skorts", "tags": ["Everyday Active"], "colors": ["Sakura", "Stone", "Court Ivory", "Onyx"]}, {"sku": "DK251204445", "name": "The Pleated Skort", "price": "1090", "category": "Skorts", "tags": ["Everyday Active"], "colors": ["Onyx"], "short_desc": "Swing freely, stay covered. A high-waist pleated skort with secure built-in shorts.", "features": "CourtSoft\u2122 four-way stretch \u2022 Built-in shorts (no ride-up) with pocket \u2022 High, flattering waistband \u2022 Asian fit, S\u2013XL", "fit": "True to size (Asian fit), S\u2013XL. Model is 165 cm, wears M."}, {"sku": "DK240420367", "name": "The Flow Skort", "price": "1090", "category": "Skorts", "tags": ["Everyday Active"], "colors": ["Court Ivory"]}, {"sku": "D31", "name": "The Breeze Skort", "price": "1250", "category": "Skorts", "tags": ["Everyday Active"], "colors": ["Night Indigo", "Meadow Green", "Sakura", "Onyx", "Court Ivory"]}, {"sku": "D29", "name": "The Court Skort", "price": "1290", "category": "Skorts", "tags": ["Everyday Active"], "colors": ["Midnight", "Court Ivory", "Oil Blue", "Sakura", "Green Jasper"]}, {"sku": "WX1506", "name": "The Ribbed Tank", "price": "895", "category": "Tops & Tanks", "tags": ["Everyday Active"], "colors": ["Sakura"], "short_desc": "Your everyday MVP. A ribbed racerback crop that layers over any bra and wears solo just as well.", "features": "Buttery-soft ribbed knit \u2022 Racerback cut for free movement \u2022 Crop length pairs with high-waist skorts/leggings \u2022 Asian fit, S\u2013XL", "fit": "True to size (Asian fit), S\u2013XL. Model is 167 cm, wears M."}, {"sku": "ADWX1249", "name": "The Strappy Bra", "price": "950", "category": "Sports Bras", "tags": ["Everyday Active"], "colors": ["Apricot", "Powder", "Onyx", "Court Ivory"]}, {"sku": "ADCK1583", "name": "The Sculpt Legging", "price": "1090", "category": "Leggings", "tags": ["Everyday Active"], "colors": ["Almond", "Stone"]}, {"sku": "CK1237", "name": "The Core Legging", "price": "1190", "category": "Leggings", "tags": ["Everyday Active"], "colors": ["Wisteria"]}, {"sku": "C36", "name": "The Halter Set", "price": "2490", "category": "Sets", "tags": ["The Rally Set"], "colors": ["Sakura", "Court Ivory", "Almond", "Bloom", "Powder", "Onyx"], "short_desc": "One decision, head-to-toe. A flattering halter bra and high-waist leggings that move as one.", "features": "CourtSoft\u2122 four-way stretch, squat-proof \u2022 Halter bra with removable pads \u2022 High-waist leggings with side pockets \u2022 Mix-and-match with The Court Edit \u2022 Asian fit, S\u2013XL", "fit": "True to size (Asian fit), S\u2013XL. Model is 166 cm, wears M."}]', true);

// 1. Setup Global Attributes
$attributes = [
    'pa_colour' => ['Court Ivory', 'Midnight', 'Onyx', 'Sakura', 'Powder', 'Sagewood', 'Wisteria', 'Stone', 'Apricot', 'Almond', 'Bloom', 'Clay Red', 'Night Indigo', 'Meadow Green', 'Oil Blue', 'Green Jasper'],
    'pa_size' => ['S', 'M', 'L', 'XL'],
    'pa_features' => ['Built-in shorts', 'Ball pocket', 'Built-in bra', 'Pockets', 'UPF50+', 'Squat-proof']
];

foreach ($attributes as $slug => $terms) {
    $attr_id = wc_attribute_taxonomy_id_by_name($slug);
    if (!$attr_id) {
        $attr_data = array(
            'name'         => ucfirst(str_replace('pa_', '', $slug)),
            'slug'         => str_replace('pa_', '', $slug),
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        );
        $attr_id = wc_create_attribute($attr_data);
    }
    register_taxonomy($slug, apply_filters('woocommerce_taxonomy_objects_' . $slug, array('product')), apply_filters('woocommerce_taxonomy_args_' . $slug, array('hierarchical' => true, 'show_ui' => false, 'query_var' => true, 'rewrite' => false)));
    
    foreach ($terms as $term) {
        if (!term_exists($term, $slug)) {
            wp_insert_term($term, $slug);
        }
    }
}

// 2. Setup Categories & Tags
$categories = ['Pickleball Dresses', 'Skorts', 'Tops & Tanks', 'Sports Bras', 'Leggings', 'Sets', 'Pickleball Paddles'];
foreach ($categories as $cat) {
    if (!term_exists($cat, 'product_cat')) wp_insert_term($cat, 'product_cat');
}
$tags = ['The Court Edit', 'The Rally Set', 'Everyday Active'];
foreach ($tags as $tag) {
    if (!term_exists($tag, 'product_tag')) wp_insert_term($tag, 'product_tag');
}

// Function to upload image from local kit
function bactive_upload_image($filename, $title) {
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents(ABSPATH . 'product_images/' . $filename);
    if (!$image_data) return false;
    
    $file = $upload_dir['path'] . '/' . $filename;
    file_put_contents($file, $image_data);
    
    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name($title),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attach_id = wp_insert_attachment($attachment, $file);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);
    update_post_meta($attach_id, '_wp_attachment_image_alt', $title);
    return $attach_id;
}

// 3. Create Products
foreach ($products_data as $pd) {
    $existing = wc_get_product_id_by_sku($pd['sku']);
    if ($existing) continue; // Skip if exists
    
    $product = new WC_Product_Variable();
    $product->set_name($pd['name']);
    $product->set_sku($pd['sku']);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    
    // Set content
    if (isset($pd['short_desc'])) {
        $excerpt = $pd['short_desc'] . "<br><br><ul>";
        $features = explode('•', $pd['features']);
        foreach ($features as $feat) {
            $excerpt .= "<li>" . trim($feat) . "</li>";
        }
        $excerpt .= "</ul><br><strong>Fit & Sizing:</strong> " . $pd['fit'];
        $product->set_short_description($excerpt);
    } else {
        $product->set_short_description("Move freely in " . $pd['name'] . ". Designed for an Asian fit.");
    }
    
    // Set category
    $term = get_term_by('name', $pd['category'], 'product_cat');
    if ($term) $product->set_category_ids([$term->term_id]);
    
    // Set tags
    $tag_ids = [];
    foreach ($pd['tags'] as $tag_name) {
        $t = get_term_by('name', $tag_name, 'product_tag');
        if ($t) $tag_ids[] = $t->term_id;
    }
    $product->set_tag_ids($tag_ids);
    
    // Set Attributes
    $prod_attributes = [];
    
    // Size attribute
    $attr_size = new WC_Product_Attribute();
    $attr_size->set_id(wc_attribute_taxonomy_id_by_name('pa_size'));
    $attr_size->set_name('pa_size');
    $size_options = [];
    foreach(['S', 'M', 'L', 'XL'] as $s_name) {
        $t = get_term_by('name', $s_name, 'pa_size');
        if($t) $size_options[] = $t->term_id;
    }
    $attr_size->set_options($size_options);
    $attr_size->set_position(0);
    $attr_size->set_visible(true);
    $attr_size->set_variation(true);
    $prod_attributes[] = $attr_size;
    
    // Colour attribute
    $attr_colour = new WC_Product_Attribute();
    $attr_colour->set_id(wc_attribute_taxonomy_id_by_name('pa_colour'));
    $attr_colour->set_name('pa_colour');
    $color_options = [];
    foreach($pd['colors'] as $c_name) {
        $t = get_term_by('name', $c_name, 'pa_colour');
        if($t) $color_options[] = $t->term_id;
    }
    $attr_colour->set_options($color_options);
    $attr_colour->set_position(1);
    $attr_colour->set_visible(true);
    $attr_colour->set_variation(true);
    $prod_attributes[] = $attr_colour;
    
    $product->set_attributes($prod_attributes);
    $product_id = $product->save();
    
    // Images lookup
    $files = glob(ABSPATH . "product_images/*" . $pd['sku'] . "*.png");
    if ($files) {
        $gallery_ids = [];
        foreach ($files as $index => $file) {
            $filename = basename($file);
            $img_id = bactive_upload_image($filename, $pd['name']);
            if ($img_id) {
                if ($index === 0) {
                    $product->set_image_id($img_id);
                } else {
                    $gallery_ids[] = $img_id;
                }
            }
        }
        if (!empty($gallery_ids)) {
            $product->set_gallery_image_ids($gallery_ids);
        }
    }
    $product->save();
    
    // Create Variations
    foreach ($pd['colors'] as $color) {
        foreach (['S', 'M', 'L', 'XL'] as $size) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_attributes([
                'pa_size' => sanitize_title($size),
                'pa_colour' => sanitize_title($color)
            ]);
            $variation->set_regular_price($pd['price']);
            $variation->set_price($pd['price']);
            $variation->set_manage_stock(false);
            $variation->set_stock_status('instock');
            $variation->save();
        }
    }
}

echo "Catalog generated successfully!";
?>
