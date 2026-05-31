<?php
require_once( dirname( __FILE__ ) . '/wp-load.php' );
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

// Activate plugins
$plugins = array(
    'woocommerce/woocommerce.php',
    'wordfence/wordfence.php',
    'wps-hide-login/wps-hide-login.php',
    'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php',
    'updraftplus/updraftplus.php'
);

foreach ( $plugins as $plugin ) {
    $result = activate_plugin( $plugin );
    if ( is_wp_error( $result ) ) {
        echo "Error activating $plugin: " . $result->get_error_message() . "\n";
    } else {
        echo "Activated $plugin successfully.\n";
    }
}

// Check what themes exist
echo "\n--- Themes ---\n";
$themes = wp_get_themes();
foreach ( $themes as $theme ) {
    echo $theme->get_stylesheet() . "\n";
}

// Check what plugins exist
echo "\n--- Plugins ---\n";
$all_plugins = get_plugins();
foreach ( $all_plugins as $path => $data ) {
    echo $path . "\n";
}
?>
