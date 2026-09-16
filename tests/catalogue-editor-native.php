<?php
/** Run only in the contained, network-disabled recovery clone. All DB changes roll back. */
require '/var/www/html/wp-load.php';
if ( 'local' !== wp_get_environment_type() || 'cli' !== PHP_SAPI ) { throw new RuntimeException('Contained CLI clone required'); }
require_once '/candidate/catalogue-settings.php';
require_once '/candidate/catalogue-editor.php';
function ce_assert($condition,$message) { if(!$condition) { throw new RuntimeException($message); } }
global $wpdb;
$wpdb->query('START TRANSACTION');
try {
    $term=get_term_by('slug','black','pa_colour'); ce_assert($term, 'Existing clone term required');
    $p=new WC_Product_Variable(); $p->set_name('Isolated catalogue editor regression'); $p->set_status('publish'); $p->save();
    $input=['stamp'=>bactive_catalogue_product_stamp($p),'settings_stamp'=>bactive_catalogue_editor_stamp($p),'layout'=>'auto','colours'=>''];
    $attribute=new WC_Product_Attribute(); $attribute->set_id(wc_attribute_taxonomy_id_by_name('pa_colour')); $attribute->set_name('pa_colour'); $attribute->set_options([(int)$term->term_id]); $attribute->set_variation(true); $attribute->set_visible(true);
    // Equivalent to the pending object Woo passes before its main product save.
    $pending=wc_get_product($p->get_id()); $pending->set_attributes([$attribute]);
    $result=bactive_catalogue_editor_validate($p,$input,$pending);
    $key='pa_colour:'.$term->term_id;
    ce_assert(!is_wp_error($result) && $result['settings']['colours'][$key]['mode']==='inherit', 'First attribute save reconciles pending terms');
    $pending->update_meta_data('_bactive_colour_settings',$result['settings']); $pending->update_meta_data('_bactive_layout_mode','auto'); $pending->save();
    $p=wc_get_product($p->get_id());
    $input=['stamp'=>bactive_catalogue_product_stamp($p),'settings_stamp'=>bactive_catalogue_editor_stamp($p),'layout'=>'auto','colours'=>[$key=>['mode'=>'custom','hex'=>'#123456','preview_image_id'=>'0','confirm'=>'1']]];
    $v=new WC_Product_Variation(); $v->set_parent_id($p->get_id()); $v->set_attributes(['pa_colour'=>'black','pa_size'=>'s']); $v->set_regular_price('100'); $v->set_image_id(561); $v->set_status('publish'); $v->save();
    clean_post_cache($p->get_id()); wc_delete_product_transients($p->get_id()); bactive_catalogue_forget($p->get_id());
    $p=wc_get_product($p->get_id());
    ce_assert(bactive_catalogue_preview($p,bactive_catalogue_product_colours($p)[$key])['id']===561, 'New product colour derives a shared photo without approval metadata');
    $v2=new WC_Product_Variation(); $v2->set_parent_id($p->get_id()); $v2->set_attributes(['pa_colour'=>'black','pa_size'=>'m']); $v2->set_regular_price('100'); $v2->set_image_id(561); $v2->set_status('publish'); $v2->save();
    clean_post_cache($p->get_id()); wc_delete_product_transients($p->get_id()); bactive_catalogue_forget($p->get_id()); $p=wc_get_product($p->get_id());
    ce_assert(bactive_catalogue_preview($p,bactive_catalogue_product_colours($p)[$key])['id']===561, 'Added size with same photo keeps automatic preview');
    $v2->set_image_id(450); $v2->save(); clean_post_cache($p->get_id()); wc_delete_product_transients($p->get_id()); bactive_catalogue_forget($p->get_id()); $p=wc_get_product($p->get_id());
    ce_assert(null===bactive_catalogue_preview($p,bactive_catalogue_product_colours($p)[$key]), 'Different size photo removes automatic preview');
    $v2->set_image_id(561); $v2->save(); clean_post_cache($p->get_id()); wc_delete_product_transients($p->get_id()); bactive_catalogue_forget($p->get_id()); $p=wc_get_product($p->get_id());
    ce_assert(bactive_catalogue_preview($p,bactive_catalogue_product_colours($p)[$key])['id']===561, 'Restored matching photo restores automatic preview');
    $result=bactive_catalogue_editor_validate($p,$input);
    ce_assert(!is_wp_error($result) && $result['mapping_changed'] && $result['settings']['colours'][$key]['hex']==='#123456', 'Real variation save preserves entered shade');
    ce_assert($result['settings']['colours'][$key]['review']==='', 'Unseen mapping remains unreviewed');
    $p->update_meta_data('_bactive_colour_settings',$result['settings']); $p->save();
    $p=wc_get_product($p->get_id());
    ce_assert(bactive_catalogue_effective_hex($p,bactive_catalogue_product_colours($p)[$key])==='#123456', 'Saved shade survives native reopen');
    ce_assert(is_wp_error(bactive_catalogue_editor_validate($p,$input)), 'Old form cannot overwrite a later settings save');
    echo "Native Woo pending attributes, variation save, persistence and conflict checks PASS\n";
} finally { $wpdb->query('ROLLBACK'); }
