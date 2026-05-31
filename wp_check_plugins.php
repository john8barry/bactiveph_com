<?php
require( dirname( __FILE__ ) . '/wp-load.php' );
if ( ! function_exists( 'get_plugins' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$all_plugins = get_plugins();
echo json_encode($all_plugins);
?>
