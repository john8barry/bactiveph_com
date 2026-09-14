<?php
/** Read-only integration check against an isolated WordPress bootstrap. */
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_readable($argv[1])) {
    fwrite(STDERR, "Usage: php catalog-gallery-ajax.php /isolated/site/wp-load.php\n"); exit(1);
}
define('DOING_AJAX', true);
require $argv[1];
function gallery_check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
gallery_check(!is_product(), 'Test must exercise a non-product AJAX context');
$fixture='<figure data-src="https://example.test/full.jpg"><img src="https://example.test/small.jpg" srcset="https://example.test/small.jpg 600w" sizes="600px"></figure><ol class="flexy-pills"><li><span><img src="https://example.test/thumb.jpg" srcset="https://example.test/thumb.jpg 100w" sizes="100px"></span></li></ol>';
$expected='<figure data-src="https://example.test/full.jpg"><img src="https://example.test/full.jpg"></figure><ol class="flexy-pills"><li><span><img src="https://example.test/thumb.jpg" srcset="https://example.test/thumb.jpg 100w" sizes="100px"></span></li></ol>';
gallery_check(preg_replace('/\s+>/', '>', bactive_catalog_normalize_gallery_originals($fixture))===$expected, 'Original figures and small thumbnail strips');
foreach (['wp_ajax_blocksy_get_product_view_for_variation','wp_ajax_nopriv_blocksy_get_product_view_for_variation','wp_ajax_unrelated_gallery'] as $action) {
    // Only changes this CLI process; never invoke the endpoint or write options.
    remove_all_actions($action);
    $result=null;
    add_action($action, function () use (&$result,$fixture) { $result=bactive_catalog_gallery_originals($fixture); });
    foreach (['185','56','1','0','-185','185.0','0185','999999999999999999999999', ['185'],null] as $id) {
        $_GET['product_id']=$id;
        do_action($action);
        $enabled=$id==='185' && $action!=='wp_ajax_unrelated_gallery';
        gallery_check(preg_replace('/\s+>/', '>', $result)===($enabled?$expected:$fixture), 'Endpoint product gate');
    }
}
$_GET=[];
$_REQUEST['wc-ajax']='get_variation';
$product=wc_get_product(185);
gallery_check($product && bactive_catalog_visuals_config(bactive_catalog_visuals_registry(),185), 'Released private Court Skort fixture required');
$count=0;
foreach ($product->get_children() as $id) {
    $data=$product->get_available_variation($id);
    gallery_check(is_string($data['blocksy_gallery_html']??null), 'Native AJAX gallery must be present');
    $tags=new WP_HTML_Tag_Processor($data['blocksy_gallery_html']);$original=null;$figures=0;
    while ($tags->next_tag(['tag_closers'=>'visit'])) {
        if ($tags->get_tag()==='FIGURE') { $original=$tags->is_tag_closer()?null:$tags->get_attribute('data-src'); }
        if ($tags->get_tag()==='IMG' && $original) {
            gallery_check($tags->get_attribute('src')===$original && $tags->get_attribute('srcset')===null && $tags->get_attribute('sizes')===null,'AJAX gallery lost original source'); ++$figures;
        }
    }
    gallery_check($figures===9, 'All nine gallery figures required');++$count;
}
gallery_check($count===37,'Current Court Skort matrix must contain 37 variations');
echo "AJAX galleries: 37 variations; endpoint/invalid-ID gates and thumbnail preservation PASS\n";
