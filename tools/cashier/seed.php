<?php
/** Synthetic fixture data; the identity guard prevents use on a real site. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'cashier_fixture'
    || home_url() !== 'http://localhost:8097' || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Isolated cashier fixture required.');
}
if (get_option('bactive_cashier_fixture_ids')) {
    echo wp_json_encode(get_option('bactive_cashier_fixture_ids')) . "\n";
    return;
}
$parent = new WC_Product_Variable();
$parent->set_name('Training Everyday Skort');
$parent->set_status('publish');
$parent->set_description('Synthetic training item. No real customer, stock, or payment.');
$size = new WC_Product_Attribute();
$size->set_name('Size');
$size->set_options(array('Small', 'Medium', 'Large'));
$size->set_visible(true);
$size->set_variation(true);
$colour = new WC_Product_Attribute();
$colour->set_name('Colour');
$colour->set_options(array('Sage'));
$colour->set_visible(true);
$colour->set_variation(true);
$parent->set_attributes(array($size, $colour));
$parent_id = $parent->save();
$ids = array('parent' => $parent_id);
foreach (array('Small' => 5, 'Medium' => 1, 'Large' => 0) as $label => $quantity) {
    $item = new WC_Product_Variation();
    $item->set_parent_id($parent_id);
    $item->set_status('publish');
    $item->set_attributes(array('size' => $label, 'colour' => 'Sage'));
    $item->set_regular_price('650.00');
    $item->set_manage_stock(true);
    $item->set_stock_quantity($quantity);
    $item->set_backorders('no');
    $item->set_sku('TRAIN-SKORT-' . strtoupper($label));
    $ids[strtolower($label)] = $item->save();
}
WC_Product_Variable::sync($parent_id);
$untracked = new WC_Product_Simple();
$untracked->set_name('Training untracked item (must be blocked)');
$untracked->set_status('publish');
$untracked->set_regular_price('200.00');
$untracked->set_manage_stock(false);
$untracked->set_stock_status('instock');
$ids['untracked'] = $untracked->save();
$backorder = new WC_Product_Simple();
$backorder->set_name('Training backorder item (must be blocked)');
$backorder->set_status('publish');
$backorder->set_regular_price('200.00');
$backorder->set_manage_stock(true);
$backorder->set_stock_quantity(0);
$backorder->set_backorders('yes');
$ids['backorder'] = $backorder->save();
$zone = new WC_Shipping_Zone();
$zone->set_zone_name('Synthetic Davao store');
$zone->add_location('PH', 'country');
$zone->save();
$ids['pickup_instance'] = $zone->add_shipping_method('local_pickup');
update_option('woocommerce_local_pickup_' . $ids['pickup_instance'] . '_settings', array(
    'enabled' => 'yes', 'title' => 'In-Store Pickup', 'cost' => '0', 'tax_status' => 'none',
));
update_option('bactive_cashier_fixture_ids', $ids, false);
echo wp_json_encode($ids) . "\n";
