<?php
/** Run with PHP CLI from the repository root; never deploy this harness. */
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define( 'ABSPATH', __DIR__ . '/../wordpress/' );
$hooks = array();
$admin = false;
$ajax = false;
$shortcode_tags = array();
function is_admin() { return $GLOBALS['admin']; }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) {
    $GLOBALS['hooks'][$name][] = array( $callback, $args );
}
function apply_filters( $name, $value, ...$args ) {
    foreach ( $GLOBALS['hooks'][$name] ?? array() as $hook ) {
        $value = $hook[0]( ...array_slice( array_merge( array($value), $args ), 0, $hook[1] ) );
    }
    return $value;
}
function _x( $text, $context, $domain = 'default' ) {
    return apply_filters( 'gettext_with_context', $text, $text, $context, $domain );
}
function __( $text ) { return $text; }
function check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException($message); }
}
require __DIR__ . '/../wordpress/wp-includes/formatting.php';
require __DIR__ . '/../wp-content/themes/blocksy-child/inc/storefront-punctuation.php';
check( apply_filters('document_title_separator', '-') === '|', 'Title separator' );
$text = wptexturize('Well-made activewear - comfortable -- breathable --- durable. "Fit" 80 to 84.', true);
check( ! preg_match('/[\x{2013}\x{2014}]/u', html_entity_decode($text, ENT_QUOTES, 'UTF-8')), 'Typography reintroduced dashes' );
check( str_contains($text, 'Well-made') && str_contains($text, '&#8220;Fit&#8221;'), 'Hyphens or quote typography changed' );
$price = '<bdi>₱10.00</bdi> <span aria-hidden="true">&ndash;</span> <bdi>₱20.00</bdi><span class="screen-reader-text">Price range: 10 through 20</span>';
$result = apply_filters('woocommerce_format_price_range', $price);
check( $result === str_replace('&ndash;', 'to', $price), 'Price markup/accessibility changed' );
$single = 'Showing %1$d&ndash;%2$d of %3$d result';
$plural = 'Showing %1$d&ndash;%2$d of %3$d results';
$result = apply_filters('ngettext_with_context', $plural, $single, $plural, 18, 'with first and last result', 'woocommerce');
check( sprintf($result, 1, 16, 18) === 'Showing 1 to 16 of 18 results', 'Shop range' );
check( _x('&#8211;', 'en dash', 'other') === '&#8211;', 'Unrelated translation changed' );
check( apply_filters('ngettext_with_context', $plural, $single, $plural, 18, 'other', 'woocommerce') === $plural, 'Unrelated plural translation changed' );
$admin = true;
check( apply_filters('document_title_separator', '-') === '-', 'Admin title changed' );
check( apply_filters('woocommerce_format_price_range', $price) === $price, 'Admin price changed' );
check( _x('&#8211;', 'en dash') === '&#8211;', 'Admin typography changed' );
$ajax = true;
check( apply_filters('woocommerce_format_price_range', $price) === $price, 'Unrelated admin AJAX changed' );
define('WC_DOING_AJAX', true);
check( apply_filters('woocommerce_format_price_range', $price) === str_replace('&ndash;', 'to', $price), 'Storefront AJAX price missed' );
echo "Storefront punctuation regression checks passed\n";
