import env_loader  # loads .env
import os
import json
import ftplib
import base64
import requests

products = [
    {
        "sku": "YY9141",
        "name": "The Court Dress",
        "price": "1950",
        "category": "Pickleball Dresses",
        "tags": ["The Court Edit"],
        "colors": ["Court Ivory", "Wisteria", "Stone"],
        "short_desc": "Move freely, look effortless. Our signature pleated pickleball dress pairs a flattering silhouette with serious performance.",
        "features": "CourtSoft™ four-way stretch • Sweat-wicking, buttery-soft • Built-in shorts (4\\\" inseam) with ball pocket • Built-in light-support bra with removable pads • Pleated skirt that holds its shape",
        "pa_features": ["Built-in shorts", "Ball pocket", "Built-in bra", "Pockets"],
        "fit": "True to size (Asian fit), S–XL. Model is 165 cm, wears M."
    },
    {
        "sku": "YY8793",
        "name": "The Rally Dress",
        "price": "1890",
        "category": "Pickleball Dresses",
        "tags": ["The Court Edit"],
        "colors": ["Midnight"],
        "short_desc": "Open-back ease, on-court confidence. A scoop-back dress with a side tie that skims and flatters.",
        "features": "CourtSoft™ four-way stretch • Sweat-wicking • Built-in shorts with ball pocket • Flattering scoop back",
        "pa_features": ["Built-in shorts", "Ball pocket"],
        "fit": "True to size (Asian fit), S–XL. Model is 168 cm, wears M."
    },
    {
        "sku": "YY4001",
        "name": "The Bubble Dress",
        "price": "2190",
        "category": "Pickleball Dresses",
        "tags": ["The Court Edit"],
        "colors": ["Court Ivory", "Sakura", "Powder", "Wisteria", "Onyx"],
        "short_desc": "The dress everyone asks about. A sculpted bodice meets a playful bubble hem — court-ready, café-ready.",
        "features": "CourtSoft™ four-way stretch • Built-in shorts with ball pocket • Built-in bra, removable pads • Statement bubble hem",
        "pa_features": ["Built-in shorts", "Ball pocket", "Built-in bra"],
        "fit": "True to size (Asian fit), S–XL. Model is 165 cm, wears M."
    },
    {
        "sku": "AS5019",
        "name": "The Match Dress",
        "price": "2450",
        "category": "Pickleball Dresses",
        "tags": ["The Court Edit"],
        "colors": ["Court Ivory"],
        "short_desc": "Zip up and serve. A modern zip-front dress with clean striped trim that brings classic country-club style into the modern game.",
        "features": "CourtSoft™ four-way stretch • Zip-front bodice for adjustable airflow • Striped trim detailing • Built-in shorts with ball pocket • Built-in light-support bra",
        "pa_features": ["Built-in shorts", "Ball pocket", "Built-in bra"],
        "fit": "True to size (Asian fit), S–XL. Model is 166 cm, wears M."
    },
    {
        "sku": "AS5028",
        "name": "The Serve Dress",
        "price": "2450",
        "category": "Pickleball Dresses",
        "tags": ["The Court Edit"],
        "colors": ["Sakura"],
        "short_desc": "Built for your strongest swing. A streamlined racerback dress with a gracefully pleated skirt.",
        "features": "CourtSoft™ four-way stretch • Racerback design for full mobility • Elegant pleated skirt • Built-in shorts with ball pocket • Built-in bra with removable pads",
        "pa_features": ["Built-in shorts", "Ball pocket", "Built-in bra"],
        "fit": "True to size (Asian fit), S–XL. Model is 165 cm, wears M."
    },
    {
        "sku": "YY9187",
        "name": "The Eyelet Dress",
        "price": "2450",
        "category": "Pickleball Dresses",
        "tags": ["The Court Edit"],
        "colors": ["Onyx", "Court Ivory", "Powder"],
        "short_desc": "A fresh take on court style. Featuring a drop-waist silhouette and unique textured eyelet fabric that breathes as you move.",
        "features": "Breathable textured eyelet fabric • Drop-waist pleated skirt • Built-in shorts with ball pocket • Built-in light-support bra",
        "pa_features": ["Built-in shorts", "Ball pocket", "Built-in bra"],
        "fit": "True to size (Asian fit), S–XL. Model is 167 cm, wears M."
    },
    {
        "sku": "AS818",
        "name": "The Varsity Dress",
        "price": "2650",
        "category": "Pickleball Dresses",
        "tags": ["The Court Edit"],
        "colors": ["Sagewood"],
        "short_desc": "Preppy meets performance. A collared dress with crisp contrast trim and a pleated skirt that means business.",
        "features": "CourtSoft™ four-way stretch • Collared, contrast-trim detail • Built-in shorts with ball pocket • Built-in support bra",
        "pa_features": ["Built-in shorts", "Ball pocket", "Built-in bra"],
        "fit": "True to size (Asian fit), S–XL. Model is 167 cm, wears M."
    },
    {
        "sku": "AS811",
        "name": "The Ace Dress",
        "price": "2650",
        "category": "Pickleball Dresses",
        "tags": ["The Court Edit"],
        "colors": ["Apricot", "Clay Red"],
        "short_desc": "Feminine, flared, and fierce. Contrast trim highlights a beautifully flared skirt that moves with every volley.",
        "features": "CourtSoft™ four-way stretch • Contrast trim styling • Flared, fluid skirt • Built-in shorts with ball pocket • Built-in bra",
        "pa_features": ["Built-in shorts", "Ball pocket", "Built-in bra"],
        "fit": "True to size (Asian fit), S–XL. Model is 166 cm, wears M."
    },
    {
        "sku": "DK21-015",
        "name": "The Everyday Skort",
        "price": "990",
        "category": "Skorts",
        "tags": ["Everyday Active"],
        "colors": ["Sakura", "Stone", "Court Ivory", "Onyx"],
        "short_desc": "The skort you'll reach for daily. A simple, flattering flared cut that transitions effortlessly from the court to coffee.",
        "features": "CourtSoft™ four-way stretch • Flattering flared silhouette • Built-in shorts (no ride-up) • Secure ball pocket",
        "pa_features": ["Built-in shorts", "Ball pocket", "Pockets"],
        "fit": "True to size (Asian fit), S–XL. High-waisted."
    },
    {
        "sku": "DK251204445",
        "name": "The Pleated Skort",
        "price": "1090",
        "category": "Skorts",
        "tags": ["Everyday Active"],
        "colors": ["Onyx"],
        "short_desc": "Swing freely, stay covered. A high-waist pleated skort with secure built-in shorts.",
        "features": "CourtSoft™ four-way stretch • Built-in shorts (no ride-up) with pocket • High, flattering waistband",
        "pa_features": ["Built-in shorts", "Ball pocket", "Pockets"],
        "fit": "True to size (Asian fit), S–XL. Model is 165 cm, wears M."
    },
    {
        "sku": "DK240420367",
        "name": "The Flow Skort",
        "price": "1090",
        "category": "Skorts",
        "tags": ["Everyday Active"],
        "colors": ["Court Ivory"],
        "short_desc": "Dynamic design for dynamic play. A piped diagonal seam gives this skort a beautiful sense of motion.",
        "features": "CourtSoft™ four-way stretch • Diagonal seam with piping • Built-in shorts (no ride-up) • Secure ball pocket",
        "pa_features": ["Built-in shorts", "Ball pocket", "Pockets"],
        "fit": "True to size (Asian fit), S–XL. High-waisted."
    },
    {
        "sku": "D31",
        "name": "The Breeze Skort",
        "price": "1250",
        "category": "Skorts",
        "tags": ["Everyday Active"],
        "colors": ["Night Indigo", "Meadow Green", "Sakura", "Onyx", "Court Ivory"],
        "short_desc": "Stay cool when the match heats up. Crafted in our ultra-light BreezeKnit™ fabric with a classic A-line cut.",
        "features": "BreezeKnit™ ultra-lightweight fabric • Classic A-line silhouette • Built-in shorts • Ball pocket",
        "pa_features": ["Built-in shorts", "Ball pocket", "Pockets"],
        "fit": "True to size (Asian fit), S–XL."
    },
    {
        "sku": "D29",
        "name": "The Court Skort",
        "price": "1290",
        "category": "Skorts",
        "tags": ["Everyday Active"],
        "colors": ["Midnight", "Court Ivory", "Oil Blue", "Sakura", "Green Jasper"],
        "short_desc": "Our most premium pleated skort. Designed with crisp, sharp pleats that hold their shape through every wash and wear.",
        "features": "Premium structure, soft feel • Sharp, lasting pleats • Built-in shorts • Ball pocket",
        "pa_features": ["Built-in shorts", "Ball pocket", "Pockets"],
        "fit": "True to size (Asian fit), S–XL. High-waisted."
    },
    {
        "sku": "WX1506",
        "name": "The Ribbed Tank",
        "price": "895",
        "category": "Tops & Tanks",
        "tags": ["Everyday Active"],
        "colors": ["Sakura"],
        "short_desc": "Your everyday MVP. A ribbed racerback crop that layers over any bra and wears solo just as well.",
        "features": "Buttery-soft ribbed knit • Racerback cut for free movement • Crop length pairs with high-waist skorts/leggings",
        "pa_features": [],
        "fit": "True to size (Asian fit), S–XL. Model is 167 cm, wears M."
    },
    {
        "sku": "ADWX1249",
        "name": "The Strappy Bra",
        "price": "950",
        "category": "Sports Bras",
        "tags": ["Everyday Active"],
        "colors": ["Apricot", "Powder", "Onyx", "Court Ivory"],
        "short_desc": "Support that looks stunning. A multi-strap back design offering medium support for the court and beyond.",
        "features": "CourtSoft™ four-way stretch • Elegant multi-strap back • Medium support • Removable pads",
        "pa_features": [],
        "fit": "True to size (Asian fit), S–XL."
    },
    {
        "sku": "ADCK1583",
        "name": "The Sculpt Legging",
        "price": "1090",
        "category": "Leggings",
        "tags": ["Everyday Active"],
        "colors": ["Almond", "Stone"],
        "short_desc": "Lift, smooth, and support. A contouring, 100% squat-proof legging designed for high performance.",
        "features": "CourtSoft™ four-way stretch • Squat-proof and opaque • High-waist contouring fit • Seamless front (no camel toe)",
        "pa_features": ["Squat-proof"],
        "fit": "True to size (Asian fit), S–XL. High-waisted, full length."
    },
    {
        "sku": "CK1237",
        "name": "The Core Legging",
        "price": "1190",
        "category": "Leggings",
        "tags": ["Everyday Active"],
        "colors": ["Wisteria"],
        "short_desc": "Your essential foundation. A beautifully simple, buttery-soft high-waist legging that stays put.",
        "features": "CourtSoft™ four-way stretch • Ultra-soft, second-skin feel • High, secure waistband • Squat-proof",
        "pa_features": ["Squat-proof"],
        "fit": "True to size (Asian fit), S–XL. High-waisted."
    },
    {
        "sku": "C36",
        "name": "The Halter Set",
        "price": "2490",
        "category": "Sets",
        "tags": ["The Rally Set"],
        "colors": ["Sakura", "Court Ivory", "Almond", "Bloom", "Powder", "Onyx"],
        "short_desc": "One decision, head-to-toe. A flattering halter bra and high-waist leggings that move as one.",
        "features": "CourtSoft™ four-way stretch, squat-proof • Halter bra with removable pads • High-waist leggings with side pockets • Mix-and-match with The Court Edit",
        "pa_features": ["Pockets", "Squat-proof"],
        "fit": "True to size (Asian fit), S–XL. Model is 166 cm, wears M."
    }
]

php_script = """<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

$products_data = json_decode(base64_decode('""" + base64.b64encode(json.dumps(products).encode()).decode() + """'), true);

// Clean up "Test Product"
$test_prod = get_page_by_title('Test Product', OBJECT, 'product');
if ($test_prod) {
    wp_delete_post($test_prod->ID, true);
}

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

// Function to upload image
function bactive_upload_image($filename, $title) {
    $upload_dir = wp_upload_dir();
    if (!file_exists(ABSPATH . 'product_images/' . $filename)) return false;
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
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);
    update_post_meta($attach_id, '_wp_attachment_image_alt', $title);
    return $attach_id;
}

// 3. Create Products
foreach ($products_data as $pd) {
    $existing = wc_get_product_id_by_sku($pd['sku']);
    if ($existing) {
        $product = wc_get_product($existing);
    } else {
        $product = new WC_Product_Variable();
        $product->set_name($pd['name']);
        $product->set_sku($pd['sku']);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
    }
    
    // Set content
    if (isset($pd['short_desc'])) {
        $excerpt = $pd['short_desc'] . "<br><br><ul>";
        $features = explode('•', $pd['features']);
        foreach ($features as $feat) {
            $excerpt .= "<li>" . trim($feat) . "</li>";
        }
        $excerpt .= "</ul><br><strong>Fit & Sizing:</strong> " . $pd['fit'];
        $product->set_short_description($excerpt);
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
    
    // Features attribute
    if (isset($pd['pa_features']) && !empty($pd['pa_features'])) {
        $attr_feat = new WC_Product_Attribute();
        $attr_feat->set_id(wc_attribute_taxonomy_id_by_name('pa_features'));
        $attr_feat->set_name('pa_features');
        $feat_opts = [];
        foreach($pd['pa_features'] as $f_name) {
            $t = get_term_by('name', $f_name, 'pa_features');
            if($t) $feat_opts[] = $t->term_id;
        }
        $attr_feat->set_options($feat_opts);
        $attr_feat->set_position(2);
        $attr_feat->set_visible(true); // Visible on product page / filters
        $attr_feat->set_variation(false);
        $prod_attributes[] = $attr_feat;
    }
    
    $product->set_attributes($prod_attributes);
    $product_id = $product->save();
    
    // Images lookup
    $files = glob(ABSPATH . "product_images/*" . $pd['sku'] . "*.png");
    $gallery_ids = [];
    if ($files) {
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
    
    // Create or Update Variations
    // We map variations to images linearly if we have multiple images.
    $main_img_id = $product->get_image_id();
    $all_imgs = [];
    if ($main_img_id) $all_imgs[] = $main_img_id;
    $all_imgs = array_merge($all_imgs, $gallery_ids);
    
    $existing_vars = $product->get_children();
    foreach ($existing_vars as $vid) {
        $v = wc_get_product($vid);
        if ($v) $v->delete(true);
    }
    
    $color_idx = 0;
    foreach ($pd['colors'] as $color) {
        // Find matching image based on index
        $var_img_id = $main_img_id;
        if (isset($all_imgs[$color_idx])) {
            $var_img_id = $all_imgs[$color_idx];
        } else if (count($all_imgs) > 0) {
            $var_img_id = $all_imgs[count($all_imgs) - 1]; // fallback to last
        }
        
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
            if ($var_img_id) {
                $variation->set_image_id($var_img_id);
            }
            $variation->save();
        }
        $color_idx++;
    }
}

echo "Catalog updated successfully!";
?>
"""

with open('setup_catalog_root.php', 'w') as f:
    f.write(f"<?php\\necho shell_exec('cd staging && php setup_catalog_internal.php 2>&1');\\n?>")

with open('setup_catalog_internal.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    
    with open('setup_catalog_internal.php', 'rb') as f:
        ftp.storbinary('STOR staging/setup_catalog_internal.php', f)
    with open('setup_catalog_root.php', 'rb') as f:
        ftp.storbinary('STOR setup_catalog_root.php', f)
        
    print("Executing update script...")
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()

response = requests.get('https://bactiveph.com/setup_catalog_root.php', timeout=300)
print("Output:", response.text)

# Cleanup
ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    ftp.delete('staging/setup_catalog_internal.php')
    ftp.delete('setup_catalog_root.php')
except Exception as e:
    pass
finally:
    ftp.quit()
