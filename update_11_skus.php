<?php
require_once('wp-load.php');
$products_data = json_decode(base64_decode('W3sic2t1IjogIkFTNTAxOSIsICJzaG9ydF9kZXNjIjogIlNldC1wb2ludCBzdHlsZS4gQSB6aXAtZnJvbnQsIHRlbm5pcy1pbnNwaXJlZCBkcmVzcyB3aXRoIGNyaXNwIHN0cmlwZWQgdHJpbSBhbmQgYSBwbGVhdGVkIHNraXJ0IHRoYXQgbW92ZXMgdGhlIG1vbWVudCB5b3UgZG8uIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBTd2VhdC13aWNraW5nLCBidXR0ZXJ5LXNvZnQgXHUyMDIyIEZyb250LXppcCBuZWNrbGluZSB3aXRoIGNvbnRyYXN0IHN0cmlwZWQgdHJpbSBcdTIwMjIgQnVpbHQtaW4gc2hvcnRzIHdpdGggYmFsbCBwb2NrZXQgXHUyMDIyIEJ1aWx0LWluIGxpZ2h0LXN1cHBvcnQgYnJhIHdpdGggcmVtb3ZhYmxlIHBhZHMgXHUyMDIyIFBsZWF0ZWQgc2tpcnQgXHUyMDIyIEFzaWFuIGZpdCwgU1x1MjAxM1hMIiwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gTW9kZWwgaXMgMTY2IGNtLCB3ZWFycyBNLiIsICJwYV9mZWF0dXJlcyI6IFsiQnVpbHQtaW4gc2hvcnRzIiwgIkJhbGwgcG9ja2V0IiwgIkJ1aWx0LWluIGJyYSJdfSwgeyJza3UiOiAiQVM1MDI4IiwgInNob3J0X2Rlc2MiOiAiQ2xlYW4gbGluZXMsIGZ1bGwgcmFuZ2UuIEEgc2Nvb3AtbmVjayByYWNlcmJhY2sgZHJlc3Mgd2l0aCBhIHBsZWF0ZWQgc2tpcnQgXHUyMDE0IGVhc3kgdG8gbW92ZSBpbiwgZWFzeSB0byBsb3ZlLiIsICJmZWF0dXJlcyI6ICJDb3VydFNvZnRcdTIxMjIgZm91ci13YXkgc3RyZXRjaCBcdTIwMjIgU3dlYXQtd2lja2luZywgYnV0dGVyeS1zb2Z0IFx1MjAyMiBSYWNlcmJhY2sgY3V0IGZvciBmcmVlIHNob3VsZGVycyBcdTIwMjIgQnVpbHQtaW4gc2hvcnRzIHdpdGggYmFsbCBwb2NrZXQgXHUyMDIyIEJ1aWx0LWluIGxpZ2h0LXN1cHBvcnQgYnJhIHdpdGggcmVtb3ZhYmxlIHBhZHMgXHUyMDIyIFBsZWF0ZWQgc2tpcnQgXHUyMDIyIEFzaWFuIGZpdCwgU1x1MjAxM1hMIiwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gTW9kZWwgaXMgMTY1IGNtLCB3ZWFycyBNLiIsICJwYV9mZWF0dXJlcyI6IFsiQnVpbHQtaW4gc2hvcnRzIiwgIkJhbGwgcG9ja2V0IiwgIkJ1aWx0LWluIGJyYSJdfSwgeyJza3UiOiAiWVk5MTg3IiwgInNob3J0X2Rlc2MiOiAiUXVpZXRseSBwcmVtaXVtLiBBIHRleHR1cmVkIGV5ZWxldCBkcmVzcyB3aXRoIGEgZmxhdHRlcmluZyBkcm9wLXdhaXN0IGZsYXJlIHRoYXQgdGFrZXMgeW91IGZyb20gdGhlIGNvdXJ0IHRvIHRoZSBjYWZcdTAwZTkgd2l0aG91dCBtaXNzaW5nIGEgYmVhdC4iLCAiZmVhdHVyZXMiOiAiVGV4dHVyZWQgZXllbGV0IGtuaXQgXHUyMDIyIEZvdXItd2F5IHN0cmV0Y2gsIGJyZWF0aGFibGUgXHUyMDIyIEJ1aWx0LWluIHNob3J0cyB3aXRoIGJhbGwgcG9ja2V0IFx1MjAyMiBCdWlsdC1pbiBsaWdodC1zdXBwb3J0IGJyYSB3aXRoIHJlbW92YWJsZSBwYWRzIFx1MjAyMiBEcm9wLXdhaXN0IGZsYXJlZCBza2lydCBcdTIwMjIgQXNpYW4gZml0LCBTXHUyMDEzWEwiLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBNb2RlbCBpcyAxNjcgY20sIHdlYXJzIE0uIiwgInBhX2ZlYXR1cmVzIjogWyJCdWlsdC1pbiBzaG9ydHMiLCAiQmFsbCBwb2NrZXQiLCAiQnVpbHQtaW4gYnJhIl19LCB7InNrdSI6ICJBUzgxMSIsICJzaG9ydF9kZXNjIjogIk1hZGUgdG8gYmUgbm90aWNlZC4gQSBmaXQtYW5kLWZsYXJlIGRyZXNzIHdpdGggY29udHJhc3QgdHJpbSBhbmQgYSB0d2lybC1yZWFkeSBza2lydCB0aGF0IHBlcmZvcm1zIGFzIGhhcmQgYXMgaXQgbG9va3MuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBTd2VhdC13aWNraW5nLCBidXR0ZXJ5LXNvZnQgXHUyMDIyIENvbnRyYXN0IHRyaW0gZGV0YWlsIFx1MjAyMiBCdWlsdC1pbiBzaG9ydHMgd2l0aCBiYWxsIHBvY2tldCBcdTIwMjIgQnVpbHQtaW4gc3VwcG9ydCBicmEgd2l0aCByZW1vdmFibGUgcGFkcyBcdTIwMjIgRmxhcmVkIHNraXJ0IFx1MjAyMiBBc2lhbiBmaXQsIFNcdTIwMTNYTCIsICJmaXQiOiAiVHJ1ZSB0byBzaXplIChBc2lhbiBmaXQpLCBTXHUyMDEzWEwuIE1vZGVsIGlzIDE2OCBjbSwgd2VhcnMgTS4iLCAicGFfZmVhdHVyZXMiOiBbIkJ1aWx0LWluIHNob3J0cyIsICJCYWxsIHBvY2tldCIsICJCdWlsdC1pbiBicmEiXX0sIHsic2t1IjogIkRLMjEtMDE1IiwgInNob3J0X2Rlc2MiOiAiWW91ciBldmVyeS1kYXksIGFueS1kYXkgc2tvcnQuIEEgZmxhcmVkIGN1dCB3aXRoIHNlY3VyZSBidWlsdC1pbiBzaG9ydHMgdGhhdCBzdGF5IHB1dCB0aHJvdWdoIGV2ZXJ5IHJhbGx5IGFuZCBlcnJhbmQuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBCdWlsdC1pbiBzaG9ydHMgd2l0aCBwb2NrZXQgKG5vIHJpZGUtdXApIFx1MjAyMiBGbGFyZWQsIGZsYXR0ZXJpbmcgY3V0IFx1MjAyMiBIaWdoLCBzbW9vdGhpbmcgd2Fpc3RiYW5kIFx1MjAyMiBBc2lhbiBmaXQsIFNcdTIwMTNYTCIsICJmaXQiOiAiVHJ1ZSB0byBzaXplIChBc2lhbiBmaXQpLCBTXHUyMDEzWEwuIE1vZGVsIGlzIDE2NSBjbSwgd2VhcnMgTS4iLCAicGFfZmVhdHVyZXMiOiBbIkJ1aWx0LWluIHNob3J0cyIsICJQb2NrZXRzIl19LCB7InNrdSI6ICJESzI0MDQyMDM2NyIsICJzaG9ydF9kZXNjIjogIk1vdmVtZW50LCBidWlsdCBpbi4gQSBjbGVhbiBBLWxpbmUgc2tvcnQgd2l0aCBhIGRpYWdvbmFsIHNlYW0gYW5kIGNyaXNwIHBpcGluZyB0aGF0IGZsYXR0ZXJzIGFzIHlvdSBmbG93IGFjcm9zcyB0aGUgY291cnQuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBCdWlsdC1pbiBzaG9ydHMgd2l0aCBwb2NrZXQgXHUyMDIyIERpYWdvbmFsIHNlYW0gd2l0aCBjb250cmFzdCBwaXBpbmcgXHUyMDIyIEEtbGluZSBmbGFyZWQgaGVtIFx1MjAyMiBIaWdoIHdhaXN0YmFuZCBcdTIwMjIgQXNpYW4gZml0LCBTXHUyMDEzWEwiLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBNb2RlbCBpcyAxNjYgY20sIHdlYXJzIE0uIiwgInBhX2ZlYXR1cmVzIjogWyJCdWlsdC1pbiBzaG9ydHMiLCAiUG9ja2V0cyJdfSwgeyJza3UiOiAiRDMxIiwgInNob3J0X2Rlc2MiOiAiTGlnaHQgYXMgdGhlIHJhbGx5IGlzIGZhc3QuIEEgYnJlZXp5IEEtbGluZSBza29ydCB0aGF0IGtlZXBzIHlvdSBjb29sIGFuZCBjb3ZlcmVkLCBwb2ludCBhZnRlciBwb2ludC4iLCAiZmVhdHVyZXMiOiAiQnJlZXplS25pdFx1MjEyMiBsaWdodHdlaWdodCwgYnJlYXRoYWJsZSBmYWJyaWMgXHUyMDIyIE1vaXN0dXJlLXdpY2tpbmcgXHUyMDIyIEJ1aWx0LWluIHNob3J0cyB3aXRoIHBvY2tldCBcdTIwMjIgQS1saW5lIGZsYXJlIFx1MjAyMiBIaWdoIHdhaXN0YmFuZCBcdTIwMjIgQXNpYW4gZml0LCBTXHUyMDEzWEwiLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBNb2RlbCBpcyAxNjcgY20sIHdlYXJzIE0uIiwgInBhX2ZlYXR1cmVzIjogWyJCdWlsdC1pbiBzaG9ydHMiLCAiUG9ja2V0cyJdfSwgeyJza3UiOiAiRDI5IiwgInNob3J0X2Rlc2MiOiAiT3VyIHByZW1pdW0gcGxlYXRlZCBza29ydC4gQSBzdHJ1Y3R1cmVkIHdhaXN0YmFuZCwgZGVlcCBwbGVhdHMsIGFuZCBzZWN1cmUgaW5uZXIgc2hvcnRzIFx1MjAxNCBjb3VydC1yZWFkeSBjb25maWRlbmNlIGluIGV2ZXJ5IHN0ZXAuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBCdWlsdC1pbiBzaG9ydHMgd2l0aCBiYWxsIHBvY2tldCBcdTIwMjIgRGVlcCwgc3RydWN0dXJlZCBwbGVhdHMgXHUyMDIyIEhpZ2gsIHNtb290aGluZyB3YWlzdGJhbmQgXHUyMDIyIEFzaWFuIGZpdCwgU1x1MjAxM1hMIiwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gTW9kZWwgaXMgMTY1IGNtLCB3ZWFycyBNLiIsICJwYV9mZWF0dXJlcyI6IFsiQnVpbHQtaW4gc2hvcnRzIiwgIkJhbGwgcG9ja2V0Il19LCB7InNrdSI6ICJBRFdYMTI0OSIsICJzaG9ydF9kZXNjIjogIlN1cHBvcnQgd2l0aCBhIGxpdHRsZSBkcmFtYS4gQSBzdHJhcHB5IG11bHRpLWJhY2sgc3BvcnRzIGJyYSB0aGF0IGZyYW1lcyB5b3VyIGJhY2sgYW5kIG1vdmVzIHdpdGggeW91LCBvbiBjb3VydCBhbmQgaW4gdGhlIHN0dWRpby4iLCAiZmVhdHVyZXMiOiAiU2Vjb25kU2tpblx1MjEyMiBzZWFtbGVzcywgbm8tZGlnIGZhYnJpYyBcdTIwMjIgTGlnaHRcdTIwMTNtZWRpdW0gc3VwcG9ydCBcdTIwMjIgU3RyYXBweSBtdWx0aS1iYWNrIGRlc2lnbiBcdTIwMjIgUmVtb3ZhYmxlIHBhZHMgXHUyMDIyIEJ1dHRlcnktc29mdCwgc3dlYXQtd2lja2luZyBcdTIwMjIgQXNpYW4gZml0LCBTXHUyMDEzWEwiLCAiZml0IjogIlRydWUgdG8gc2l6ZSAoQXNpYW4gZml0KSwgU1x1MjAxM1hMLiBNb2RlbCBpcyAxNjcgY20sIHdlYXJzIE0uIiwgInBhX2ZlYXR1cmVzIjogW119LCB7InNrdSI6ICJBRENLMTU4MyIsICJzaG9ydF9kZXNjIjogIlNjdWxwdCBhbmQgc3VwcG9ydCwgaW4gb25lIHB1bGwtb24uIEhpZ2gtd2Fpc3QgbGVnZ2luZ3Mgd2l0aCBmbGF0dGVyaW5nIGNvbnRvdXIgc2VhbXMgYW5kIHNxdWF0LXByb29mIGNvdmVyYWdlIHlvdSBjYW4gdHJ1c3QuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBTcXVhdC1wcm9vZiwgb3BhcXVlIGNvdmVyYWdlIFx1MjAyMiBIaWdoLCBzbW9vdGhpbmcgd2Fpc3RiYW5kIFx1MjAyMiBGbGF0dGVyaW5nIGNvbnRvdXIgc2VhbXMgXHUyMDIyIEFzaWFuIGZpdCwgU1x1MjAxM1hMIiwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gTW9kZWwgaXMgMTY4IGNtLCB3ZWFycyBNLiIsICJwYV9mZWF0dXJlcyI6IFsiU3F1YXQtcHJvb2YiXX0sIHsic2t1IjogIkNLMTIzNyIsICJzaG9ydF9kZXNjIjogIllvdXIgZXZlcnlkYXkgYmFzZSBsYXllci4gQnV0dGVyeS1zb2Z0LCBoaWdoLXdhaXN0IGxlZ2dpbmdzIHRoYXQgZ28gZnJvbSB3YXJtLXVwIHRvIHdpbmQtZG93biB3aXRob3V0IGEgc2luZ2xlIGFkanVzdG1lbnQuIiwgImZlYXR1cmVzIjogIkNvdXJ0U29mdFx1MjEyMiBmb3VyLXdheSBzdHJldGNoIFx1MjAyMiBTcXVhdC1wcm9vZiwgb3BhcXVlIGNvdmVyYWdlIFx1MjAyMiBIaWdoLCBzbW9vdGhpbmcgd2Fpc3RiYW5kIFx1MjAyMiBCdXR0ZXJ5LXNvZnQsIHN3ZWF0LXdpY2tpbmcgXHUyMDIyIEFzaWFuIGZpdCwgU1x1MjAxM1hMIiwgImZpdCI6ICJUcnVlIHRvIHNpemUgKEFzaWFuIGZpdCksIFNcdTIwMTNYTC4gTW9kZWwgaXMgMTY2IGNtLCB3ZWFycyBNLiIsICJwYV9mZWF0dXJlcyI6IFsiU3F1YXQtcHJvb2YiXX1d'), true);

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
        echo "Updated SKU: " . $pd['sku'] . "\n";
    } else {
        echo "SKU NOT FOUND: " . $pd['sku'] . "\n";
    }
}
echo "Done updating remaining 11 SKUs.\n";
?>
