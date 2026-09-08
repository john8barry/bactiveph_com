<?php
/**
 * Draft containment for a future, separately qualified local restore.
 * No web access or existing-user login is allowed. This is not a DB sanitizer.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'pre_http_request', static function () {
    return new WP_Error( 'recovery_http_blocked', 'External HTTP is disabled in recovery.' );
}, PHP_INT_MAX, 3 );
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
add_filter( 'pre_schedule_event', '__return_false', PHP_INT_MAX );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false', PHP_INT_MAX );
add_filter( 'action_scheduler_queue_runner_concurrent_batches', '__return_zero', PHP_INT_MAX );
add_filter( 'xmlrpc_enabled', '__return_false', PHP_INT_MAX );
add_filter( 'wp_is_application_passwords_available', '__return_false', PHP_INT_MAX );
add_filter( 'wp_is_application_passwords_available_for_user', '__return_false', PHP_INT_MAX );
add_filter( 'authenticate', static function () {
    return new WP_Error( 'recovery_login_blocked', 'Login is disabled until synthetic-user isolation is qualified.' );
}, PHP_INT_MAX, 3 );

// Existing cookies and anonymous pages must not expose restored customer data.
add_action( 'muplugins_loaded', static function () {
    if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
        http_response_code( 403 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo 'Recovery web access is blocked pending independent review.';
        exit;
    }
}, PHP_INT_MIN );
