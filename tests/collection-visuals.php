<?php
define('ABSPATH', __DIR__);
$release = array(); $option = array(); $front = true; $page_id = 14; $registry = array();
function add_filter(...$args) {} function add_action(...$args) {} function add_shortcode(...$args) {}
function get_option($name,$default) { global $option,$registry; return $name==='bactive_catalog_visuals_release' ? $registry : $option; }
function apply_filters($name,$value) { global $release; return $release ?: $value; }
$product_page=false; $loop_name='';
function is_product() { global $product_page; return $product_page; }
function wc_get_loop_prop($name) { global $loop_name; return $loop_name; }
function is_front_page() { global $front; return $front; }
function get_queried_object_id() { global $page_id; return $page_id; }
function esc_attr($v) { return htmlspecialchars($v,ENT_QUOTES); }
function esc_attr__($v,$d) { return esc_attr($v); }
function esc_html($v) { return htmlspecialchars($v,ENT_QUOTES); }
function esc_url($v) { return str_starts_with($v,'https://') ? esc_attr($v) : ''; }
function add_query_arg($key,$value,$url) { return $url.'?'.http_build_query([$key=>$value]); }
function get_term_by(...$args) { return (object)['term_id'=>7,'name'=>'White <script>alert(1)</script>']; }
function is_wp_error($v) { return false; }
require __DIR__.'/../wordpress/wp-content/themes/blocksy-child/inc/catalog-visuals.php';
function serialize_block($b) { return $b['innerHTML']; }
function get_post($id) { return (object)['post_type'=>'page','post_status'=> $id===304 ? 'draft':'publish','post_password'=>'']; }
function get_permalink($p) { return 'https://bactiveph.com/size-guide/'; }
function wp_unique_id($p) { return $p.'1'; }
function wp_get_attachment_image(...$args) { return '<img src="https://bactiveph.com/new.jpg" width="800" height="900" alt="Court dress">'; }
function wp_kses_uri_attributes() { return ['src','href']; }
function wp_kses_bad_protocol($v,$p) { return $v; }
function wp_allowed_protocols() { return ['https']; }
function _doing_it_wrong(...$args) { throw new RuntimeException('Invalid HTML API usage'); }
function _wp_can_use_pcre_u() { return true; }
require __DIR__.'/../wordpress/wp-includes/utf8.php';
foreach (['class-wp-html-attribute-token','class-wp-html-text-replacement','class-wp-html-span','class-wp-html-decoder','class-wp-html-tag-processor'] as $file) {
 require __DIR__.'/../wordpress/wp-includes/html-api/'.$file.'.php';
}
class WC_Product {
 public function get_children() { return [1,2]; }
 public function get_id() { return 36; }
 public function is_type($t) { return $t==='variable'; }
 public function get_variation_attributes() { return ['pa_colour'=>['white']]; }
 public function get_permalink() { return 'https://bactiveph.com/product/dress/'; }
}
require __DIR__.'/../wordpress/wp-content/themes/blocksy-child/inc/collection-visuals.php';
function check($condition,$message) { if (!$condition) throw new RuntimeException($message); }
check([]===bactive_collection_release(),'Off by default');
$option=['version'=>'test-1','enabled'=>true,'product_ids'=>[36]];
$product=new WC_Product();
check(['bactive-collection-product']===bactive_collection_product_classes([],$product),'Option fallback');
$release=$option; $release['enabled']=false; $release['editorial']=['enabled'=>true];
check([]===bactive_collection_product_classes([],$product),'Independent cards off');
check(true===bactive_collection_release()['editorial']['enabled'],'Independent editorial on');
$release['enabled']=true; $release['product_ids']=['36'];
check([]===bactive_collection_product_classes([],$product),'Exact integer IDs');
$release['product_ids']=[36];
$registry=['schema_version'=>1,'version'=>'test-1','enabled'=>false,'products'=>[36=>['enabled'=>false,'reviewed'=>true,'palette'=>['attribute_pa_colour'=>['white'=>['term_id'=>7,'approved'=>true,'hex'=>'#ffffff'],'orphan'=>['term_id'=>7,'approved'=>true,'hex'=>'#000000'],'bad'=>['term_id'=>7,'approved'=>true,'hex'=>'red;position:fixed']]]]]];
check(null===bactive_catalog_visuals_config($registry,36),'Product enhancement remains off');
ob_start(); bactive_collection_colour_previews(); $html=ob_get_clean();
check(str_contains($html,'attribute_pa_colour=white'),'Product colour links');
check(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>'),'Escaped label');
check(!str_contains($html,'orphan') && !str_contains($html,'position:fixed'),'Reject orphan and invalid hex');
$registry['products'][36]['reviewed']=false; ob_start(); bactive_collection_colour_previews(); check(''===ob_get_clean(),'Held products have no previews');
$release['editorial']=['enabled'=>true,'heading'=>'<script>','body'=>'Existing copy','link_label'=>'Size guide','attachment_id'=>10,'page_id'=>20];
check(str_contains(bactive_editorial_shortcode(),'https://bactiveph.com/size-guide/'),'Page CTA');
check(str_contains(bactive_editorial_shortcode(),'&lt;script&gt;'),'Escaped heading');
$release['editorial']['page_id']=304; check(''===bactive_editorial_shortcode(),'Draft CTA blocked');
$original='<div class="wp-block-group"><div class="wp-block-columns"><img src="https://bactiveph.com/old.jpg" srcset="old-2.jpg 2x"><h2>Designed for an Asian fit.</h2><p>Existing facts unchanged.</p><a href="/size-guide/">See the size guide</a></div></div>';
$block=['blockName'=>'core/group','innerHTML'=>$original];
$release['enabled']=false;
$release['editorial']=['enabled'=>true,'source_sha256'=>hash('sha256',$original)];
$out=bactive_editorial_existing_block($original,$block);
check(str_contains($out,'bactive-editorial-existing'),'Matched editorial alone activates');
check(str_contains($out,'Existing facts unchanged.') && str_contains($out,'href="/size-guide/"'),'Preserve facts and CTA');
$release['editorial']['attachment_id']=10; $out=bactive_editorial_existing_block($original,$block);
check(str_contains($out,'new.jpg') && !str_contains($out,'old-2.jpg'),'Replace image and remove stale srcset');
$page_id=304; check($original===bactive_editorial_existing_block($original,$block),'Other pages unchanged'); $page_id=14;
$block['innerHTML'].=' changed'; check($original===bactive_editorial_existing_block($original,$block),'Changed source unchanged');
$release['version']='<script>'; check([]===bactive_collection_release(),'Bad version blocked');
echo "Collection/editorial activation, palette, preservation and image guards: PASS\n";

// A mixed related grid gets one frame, without promoting held product mappings.
$release=['version'=>'test-1','enabled'=>true,'product_ids'=>[117]];
$product_page=true; $loop_name='related'; $page_id=117;
$registry=['schema_version'=>1,'version'=>'test-1','enabled'=>true,'products'=>[
117=>['enabled'=>true],36=>['enabled'=>false,'reviewed'=>true,'palette'=>['attribute_pa_colour'=>['white'=>['term_id'=>7,'approved'=>true,'hex'=>'#ffffff']]]]]];
check(['bactive-collection-product']===bactive_collection_product_classes([],$product),'Unreleased related card shares frame');
ob_start(); bactive_collection_colour_previews(); check(''===ob_get_clean(),'Unreleased related card never gets previews even with reviewed palette');
$registry['products'][36]['reviewed']=false;
check(['bactive-collection-product']===bactive_collection_product_classes([],$product),'Held related card shares frame');
ob_start(); bactive_collection_colour_previews(); check(''===ob_get_clean(),'Held related card has no previews');
$loop_name='upsells'; check([]===bactive_collection_product_classes([],$product),'Other product loops unchanged');
$loop_name='related'; $registry['products'][117]['enabled']=false;
check([]===bactive_collection_product_classes([],$product),'Unenhanced page unchanged');
$registry['products'][117]['enabled']=true; $release['enabled']=false;
check([]===bactive_collection_product_classes([],$product),'Collection off preserves related cards');
$release['enabled']=true; $product_page=false;
check([]===bactive_collection_product_classes([],$product),'Non-product page unchanged');
echo "Mixed related cards preserve independent release gates: PASS\n";

class WC_Product_Variation {
 public function __construct(public $image=11, public $colour='white',public $parent=36) {}
 public function get_status(){return 'publish';}
 public function get_parent_id(){return $this->parent;}
 public function get_attributes(){return ['pa_colour'=>$this->colour];}
 public function get_image_id($context){check($context==='edit','No parent-image inheritance');return $this->image;}
}
$test_variations=[1=>new WC_Product_Variation(),2=>new WC_Product_Variation()];
function wc_get_product($id){global $test_variations;return $test_variations[$id]??null;}
function wp_get_attachment_image_src($id,$size){return ['https://bactiveph.com/'.$id.'.jpg',800,1200];}
$loop_name='related';
check(bactive_collection_preview_image($product,'pa_colour','white')==='https://bactiveph.com/11.jpg','Same exact colour image previews');
$test_variations[2]->image=12;
check(bactive_collection_preview_image($product,'pa_colour','white')==='','Ambiguous images preserve links');
$test_variations[2]->image=0;
check(bactive_collection_preview_image($product,'pa_colour','white')==='','Missing images preserve links');
$test_variations[2]->colour='';
check(bactive_collection_preview_image($product,'pa_colour','white')==='','Wildcard colour preserves links');
$loop_name='upsells';
check(bactive_collection_preview_image($product,'pa_colour','white')==='','Only related loops preview');
echo "Related colour image ambiguity and scope guards: PASS\n";
