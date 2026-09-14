<?php
// Newly generated local configuration. Never copy production wp-config.php here.
// There is intentionally no matching imported database or WordPress core yet.
define( 'DB_NAME', 'catalog_recovery' );
define( 'DB_USER', 'catalog_recovery' );
define( 'DB_PASSWORD', '@@LOCAL_PASSWORD@@' );
define( 'DB_HOST', 'catalog-db' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_HOME', 'http://127.0.0.1:@@LOCAL_PORT@@' );
define( 'WP_SITEURL', 'http://127.0.0.1:@@LOCAL_PORT@@' );
define( 'DISABLE_WP_CRON', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', true );
define( 'WP_DEBUG', false );
define( 'AUTH_KEY', '@@AUTH_KEY@@' );
define( 'SECURE_AUTH_KEY', '@@SECURE_AUTH_KEY@@' );
define( 'LOGGED_IN_KEY', '@@LOGGED_IN_KEY@@' );
define( 'NONCE_KEY', '@@NONCE_KEY@@' );
define( 'AUTH_SALT', '@@AUTH_SALT@@' );
define( 'SECURE_AUTH_SALT', '@@SECURE_AUTH_SALT@@' );
define( 'LOGGED_IN_SALT', '@@LOGGED_IN_SALT@@' );
define( 'NONCE_SALT', '@@NONCE_SALT@@' );
$table_prefix = 'recovery_';
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! is_file( ABSPATH . 'wp-settings.php' ) ) {
    http_response_code( 503 );
    exit( 'Recovery core and database prerequisites have not been qualified.' );
}
require_once ABSPATH . 'wp-settings.php';
