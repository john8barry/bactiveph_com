import json
import base64
import ftplib
import requests

data = [
  {"sku":"AS5019","short_desc":"Set-point style. A zip-front, tennis-inspired dress with crisp striped trim and a pleated skirt that moves the moment you do.","features":"CourtSoft™ four-way stretch • Sweat-wicking, buttery-soft • Front-zip neckline with contrast striped trim • Built-in shorts with ball pocket • Built-in light-support bra with removable pads • Pleated skirt • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 166 cm, wears M."},
  {"sku":"AS5028","short_desc":"Clean lines, full range. A scoop-neck racerback dress with a pleated skirt — easy to move in, easy to love.","features":"CourtSoft™ four-way stretch • Sweat-wicking, buttery-soft • Racerback cut for free shoulders • Built-in shorts with ball pocket • Built-in light-support bra with removable pads • Pleated skirt • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 165 cm, wears M."},
  {"sku":"YY9187","short_desc":"Quietly premium. A textured eyelet dress with a flattering drop-waist flare that takes you from the court to the café without missing a beat.","features":"Textured eyelet knit • Four-way stretch, breathable • Built-in shorts with ball pocket • Built-in light-support bra with removable pads • Drop-waist flared skirt • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 167 cm, wears M."},
  {"sku":"AS811","short_desc":"Made to be noticed. A fit-and-flare dress with contrast trim and a twirl-ready skirt that performs as hard as it looks.","features":"CourtSoft™ four-way stretch • Sweat-wicking, buttery-soft • Contrast trim detail • Built-in shorts with ball pocket • Built-in support bra with removable pads • Flared skirt • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 168 cm, wears M."},
  {"sku":"DK21-015","short_desc":"Your every-day, any-day skort. A flared cut with secure built-in shorts that stay put through every rally and errand.","features":"CourtSoft™ four-way stretch • Built-in shorts with pocket (no ride-up) • Flared, flattering cut • High, smoothing waistband • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 165 cm, wears M."},
  {"sku":"DK240420367","short_desc":"Movement, built in. A clean A-line skort with a diagonal seam and crisp piping that flatters as you flow across the court.","features":"CourtSoft™ four-way stretch • Built-in shorts with pocket • Diagonal seam with contrast piping • A-line flared hem • High waistband • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 166 cm, wears M."},
  {"sku":"D31","short_desc":"Light as the rally is fast. A breezy A-line skort that keeps you cool and covered, point after point.","features":"BreezeKnit™ lightweight, breathable fabric • Moisture-wicking • Built-in shorts with pocket • A-line flare • High waistband • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 167 cm, wears M."},
  {"sku":"D29","short_desc":"Our premium pleated skort. A structured waistband, deep pleats, and secure inner shorts — court-ready confidence in every step.","features":"CourtSoft™ four-way stretch • Built-in shorts with ball pocket • Deep, structured pleats • High, smoothing waistband • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 165 cm, wears M."},
  {"sku":"ADWX1249","short_desc":"Support with a little drama. A strappy multi-back sports bra that frames your back and moves with you, on court and in the studio.","features":"SecondSkin™ seamless, no-dig fabric • Light–medium support • Strappy multi-back design • Removable pads • Buttery-soft, sweat-wicking • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 167 cm, wears M."},
  {"sku":"ADCK1583","short_desc":"Sculpt and support, in one pull-on. High-waist leggings with flattering contour seams and squat-proof coverage you can trust.","features":"CourtSoft™ four-way stretch • Squat-proof, opaque coverage • High, smoothing waistband • Flattering contour seams • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 168 cm, wears M."},
  {"sku":"CK1237","short_desc":"Your everyday base layer. Buttery-soft, high-waist leggings that go from warm-up to wind-down without a single adjustment.","features":"CourtSoft™ four-way stretch • Squat-proof, opaque coverage • High, smoothing waistband • Buttery-soft, sweat-wicking • Asian fit, S–XL","fit":"True to size (Asian fit), S–XL. Model is 166 cm, wears M."}
]

for d in data:
    f_lower = d['features'].lower()
    pa = []
    if "built-in shorts" in f_lower: pa.append("Built-in shorts")
    if "ball pocket" in f_lower: pa.append("Ball pocket")
    if "built-in bra" in f_lower or "support bra" in f_lower: pa.append("Built-in bra")
    if "pocket" in f_lower and "ball pocket" not in f_lower: pa.append("Pockets")
    if "squat-proof" in f_lower: pa.append("Squat-proof")
    d['pa_features'] = pa

php_script = """<?php
require_once('wp-load.php');
$products_data = json_decode(base64_decode('""" + base64.b64encode(json.dumps(data).encode()).decode() + """'), true);

foreach ($products_data as $pd) {
    $existing = wc_get_product_id_by_sku($pd['sku']);
    if ($existing) {
        $product = wc_get_product($existing);
        
        // Update short description
        $excerpt = $pd['short_desc'] . "<br><br><ul>";
        $features = explode('•', $pd['features']);
        foreach ($features as $feat) {
            $excerpt .= "<li>" . trim($feat) . "</li>";
        }
        $excerpt .= "</ul><br><strong>Fit & Sizing:</strong> " . $pd['fit'];
        $product->set_short_description($excerpt);
        
        // Update pa_features
        $prod_attributes = $product->get_attributes();
        
        if (isset($pd['pa_features']) && !empty($pd['pa_features'])) {
            $attr_feat = new WC_Product_Attribute();
            $attr_feat->set_id(wc_attribute_taxonomy_id_by_name('pa_features'));
            $attr_feat->set_name('pa_features');
            $feat_opts = [];
            foreach($pd['pa_features'] as $f_name) {
                $t = get_term_by('name', $f_name, 'pa_features');
                if($t) {
                    $feat_opts[] = $t->term_id;
                } else {
                    $term = wp_insert_term($f_name, 'pa_features');
                    if (!is_wp_error($term)) {
                        $feat_opts[] = $term['term_id'];
                    }
                }
            }
            $attr_feat->set_options($feat_opts);
            $attr_feat->set_position(2);
            $attr_feat->set_visible(true);
            $attr_feat->set_variation(false);
            
            $prod_attributes['pa_features'] = $attr_feat;
        }
        
        $product->set_attributes($prod_attributes);
        $product->save();
        echo "Updated SKU: " . $pd['sku'] . "\\n";
    } else {
        echo "SKU NOT FOUND: " . $pd['sku'] . "\\n";
    }
}
echo "Done updating remaining 11 SKUs.\\n";
?>
"""

with open('update_11_skus.php', 'w') as f:
    f.write(php_script)

with open('run_update_11_skus.php', 'w') as f:
    f.write("<?php\necho shell_exec('cd staging && php update_11_skus.php 2>&1');\n?>")

# Upload and execute
ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('update_11_skus.php', 'rb') as f:
        ftp.storbinary('STOR staging/update_11_skus.php', f)
    with open('run_update_11_skus.php', 'rb') as f:
        ftp.storbinary('STOR run_update_11_skus.php', f)
    print("Files uploaded.")
except Exception as e:
    print("FTP Error:", e)
finally:
    ftp.quit()

print("Executing script...")
response = requests.get('https://bactiveph.com/run_update_11_skus.php', timeout=300)
print("Output:", response.text)

# Cleanup
ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    ftp.delete('staging/update_11_skus.php')
    ftp.delete('run_update_11_skus.php')
    print("Cleanup done.")
except Exception as e:
    print("Cleanup error:", e)
finally:
    ftp.quit()
