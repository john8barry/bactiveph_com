<?php
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function get_term_by( $field, $slug, $taxonomy ) { return 'black' === $slug && 'pa_colour' === $taxonomy ? (object) array( 'term_id' => 7 ) : false; }
function is_wp_error( $value ) { return false; }
require __DIR__ . '/../wordpress/wp-content/themes/blocksy-child/inc/catalog-visuals.php';
function check( $condition ) { if ( ! $condition ) { throw new Exception( 'Contract failed' ); } }
$registry = array( 'schema_version' => 1, 'version' => 'test-1', 'enabled' => true,
    'products' => array( 36 => array( 'enabled' => true, 'palette' => array(
        'attribute_pa_colour' => array( 'black' => array( 'approved' => true, 'term_id' => 7, 'hex' => '#010203' ) ) ) ) ) );
check( null === bactive_catalog_visuals_config( null, 36 ) );
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
echo "Catalog registry gate and exact shade mapping: PASS\n";
