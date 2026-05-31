<?php


/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'waypmvhk_stg' );

/** Database username */
define( 'DB_USER', 'waypmvhk_stg' );

/** Database password */
define( 'DB_PASSWORD', 'StagingDB_BActive2026!' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'mZ1B_BZ19v-BY4ZO={6{gCQ|`i/T!e@.Pla8l~S,OHHxvE;G*p ]yO2TA]y-O!4+');
define('SECURE_AUTH_KEY',  'vPLW5d1[uwN+*c}Dcz1Qj~&E2bcAj[uGo0pFZt+m?G}c$2/)|dR;wrR>0^0g`[$~');
define('LOGGED_IN_KEY',    'prG|I3Q}C5=3Cl2cnO7g=wA.yb9ODB3{{c|47L!?.XN3-B2SOge}%btRb4?N0k^L');
define('NONCE_KEY',        'Aw1p`-+Ya*OvK_)uDDlcq5%[h#[!1_]c??MJsfgVAFgo~|W,ek^%+y Fauz$(tt#');
define('AUTH_SALT',        'f=BzDSBC|`-hTUf~7|Vt[SyBk)]PtLR|$IT?En+:yp5:b?HI|uh|i~VnIy|?D|;5');
define('SECURE_AUTH_SALT', '[cbpR}J70u#`o%LC>N!hiYy$-W]dPLA{x(dTo}Wi!mCrmGyL5.Bd{$ko/~wAfB${');
define('LOGGED_IN_SALT',   'SZ`t/eZ/L-|y%bA8!Sw|uEh#l-E+WtUJD2Pe$|c}zsxxdwV$y{9Oz9hh8SQ; wFv');
define('NONCE_SALT',       'Tmb*S-#2!|R9Ta]+#];q3CJ%5f@zFz@j`]7EpVrZZ^iH8WWIQ:K:Al7$9;zOU%y.');


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */
define( 'DISALLOW_FILE_EDIT', true );
define( 'WP_HOME', 'https://staging.bactiveph.com' );
define( 'WP_SITEURL', 'https://staging.bactiveph.com' );



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
