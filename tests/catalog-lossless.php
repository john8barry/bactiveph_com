<?php
// Run against the isolated clone only, after loading its reviewed lossless option.
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_readable($argv[1])) {
    fwrite(STDERR, "Usage: php catalog-lossless.php /isolated/site/wp-load.php\n"); exit(1);
}
require $argv[1];
if(wp_get_environment_type()!=='local')throw new Exception('isolated only');
$config=get_option('bactive_catalog_lossless_release');
$expected_ids=[36,50,83,89,95,111,117,128,154,185,217,565,573,660,677];
if (!is_array($config) || ($config['schema_version']??null)!==1 || ($config['enabled']??null)!==true
    || ($config['product_ids']??null)!==$expected_ids || !is_array($config['assets']??null)
    || count($config['assets'])!==25) { throw new RuntimeException('Reviewed enabled 25-row/15-product fixture required'); }
$checks=[];$start=hrtime(true);
foreach($config['assets'] as $a){foreach($a['product_ids'] as $id){$u=wp_get_attachment_url($a['attachment_id']);$out=bactive_catalog_derivative_source($u,$id);if(!str_ends_with($out,'/bactive-lossless/'.$a['source_sha256'].'.webp'))throw new Exception('mapping '.$a['attachment_id']);$checks[]=$a['attachment_id'];}}
$cold=(hrtime(true)-$start)/1e6;
$_REQUEST['wc-ajax']='get_variation';$count=0;
$off=function(){return [];};
foreach($config['product_ids'] as $id){$p=wc_get_product($id);foreach($p->get_children() as $vid){add_filter('pre_option_bactive_catalog_lossless_release',$off);$before=$p->get_available_variation($vid);remove_filter('pre_option_bactive_catalog_lossless_release',$off);$after=$p->get_available_variation($vid);foreach(['image','blocksy_original_image','blocksy_gallery_html'] as $key){unset($before[$key],$after[$key]);}if($before!==$after)throw new Exception('commerce changed '.$vid);$count++;}}
if(count($checks)!==25 || $count!==181)throw new RuntimeException('Expected 25 mapping and 181 variation checks');
echo json_encode(['status'=>'PASS','attachment_checks'=>count($checks),'variation_commerce_checks'=>$count,'first_resolve_all_assets_ms'=>$cold]);
