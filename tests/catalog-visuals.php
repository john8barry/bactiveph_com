<?php
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
$option = array();
function get_option( $name, $default ) { global $option; return $option; }
function get_term_by( $field, $slug, $taxonomy ) { return 'black' === $slug && 'pa_colour' === $taxonomy ? (object) array( 'term_id' => 7 ) : false; }
function is_wp_error( $value ) { return false; }
require __DIR__ . '/../wordpress/wp-content/themes/blocksy-child/inc/catalog-visuals.php';
function check( $condition ) { if ( ! $condition ) { throw new Exception( 'Contract failed' ); } }
$registry = array( 'schema_version' => 1, 'version' => 'test-1', 'enabled' => true,
    'products' => array( 36 => array( 'enabled' => true, 'reviewed' => true, 'palette' => array(
        'attribute_pa_colour' => array( 'black' => array( 'approved' => true, 'term_id' => 7, 'hex' => '#010203' ) ) ) ) ) );
check( null === bactive_catalog_visuals_config( null, 36 ) );
check( array() === bactive_catalog_visuals_registry() );
$option = $registry;
check( $registry === bactive_catalog_visuals_registry() );
$option = 'invalid';
check( array() === bactive_catalog_visuals_registry() );
check( null === bactive_catalog_visuals_config( $registry, 37 ) );
check( null === bactive_catalog_visuals_config( $registry, '36' ) );
foreach ( array( 'schema_version' => 2, 'enabled' => 'true', 'version' => '</script>' ) as $key => $value ) {
    $bad = $registry; $bad[$key] = $value; check( null === bactive_catalog_visuals_config( $bad, 36 ) );
}
$config = bactive_catalog_visuals_config( $registry, 36 );
check( '#010203' === $config['palette']->attribute_pa_colour['black'] );
foreach ( array( 'approved' => false, 'term_id' => 8, 'hex' => '#123;url(evil)' ) as $key => $value ) {
    $bad = $registry; $bad['products'][36]['palette']['attribute_pa_colour']['black'][$key] = $value;
    check( 0 === count( (array) bactive_catalog_visuals_config( $bad, 36 )['palette'] ) );
}

$off=$registry; $off['enabled']=false; $off['products'][36]['enabled']=false;
check(null===bactive_catalog_visuals_config($off,36));
check('#010203'===bactive_catalog_visuals_palette($off,36)['attribute_pa_colour']['black']);
$off['products'][36]['reviewed']=false;
check([]===bactive_catalog_visuals_palette($off,36));
echo "Catalog registry gate and exact shade mapping: PASS\n";

// Only the main variation image changes; commerce fields and thumbnails are exact.
$product = new class { function get_id() { return 36; } };
$data = ['variation_id'=>99,'display_price'=>1500,'is_in_stock'=>true,'image'=>[
'src'=>'https://example.test/photo-600x800.jpg','src_w'=>600,'src_h'=>800,
'full_src'=>'https://example.test/photo.jpg','full_src_w'=>960,'full_src_h'=>1280,
'srcset'=>'small.jpg 600w, photo.jpg 960w','sizes'=>'600px','gallery_thumbnail_src'=>'thumb.jpg']];
$option=$registry;
$actual=bactive_catalog_variation_original($data,$product);
$expected=$data; $expected['image']=array_merge($data['image'],[
'src'=>$data['image']['full_src'],'src_w'=>960,'src_h'=>1280,'srcset'=>'','sizes'=>'']);
check($expected===$actual);
$option=[]; check($data===bactive_catalog_variation_original($data,$product));
$option=$registry; $missing=$data; unset($missing['image']['full_src']);
check($missing===bactive_catalog_variation_original($missing,$product));
$missing['image']['full_src']='javascript:alert(1)';
check($missing===bactive_catalog_variation_original($missing,$product));
echo "Original image delivery preserves variation and thumbnail data: PASS\n";

foreach ( ['full_src_w','full_src_h'] as $dimension ) {
    foreach ( [null,0,-1,'960'] as $invalid ) {
        $missing=$data; $missing['image'][$dimension]=$invalid;
        check($missing===bactive_catalog_variation_original($missing,$product));
    }
}
