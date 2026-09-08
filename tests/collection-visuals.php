<?php
define('ABSPATH', __DIR__);
$release = array();
function add_filter(...$args) {}
function add_action(...$args) {}
function add_shortcode(...$args) {}
function apply_filters($name, $value) { global $release; return $release; }
function is_front_page() { return true; }
class WC_Product { public function get_id() { return 36; } }
require __DIR__ . '/../wordpress/wp-content/themes/blocksy-child/inc/collection-visuals.php';
function check($condition) { if (!$condition) { throw new RuntimeException('Collection release gate failed'); } }
check(array() === bactive_collection_release());
$release = array('version'=>'test-1', 'enabled'=>false, 'product_ids'=>array(36));
check(array() === bactive_collection_product_classes(array(), new WC_Product()));
$release['enabled'] = true;
check(array('bactive-collection-product') === bactive_collection_product_classes(array(), new WC_Product()));
$release['product_ids'] = array('36');
check(array() === bactive_collection_product_classes(array(), new WC_Product()));
$release['editorial'] = array('enabled'=>true, 'heading'=>'Missing required content');
check('' === bactive_editorial_shortcode());
$release['version'] = '<script>';
check(array() === bactive_collection_release());
echo "Collection activation, exact IDs and incomplete editorial fail-closed: PASS\n";
