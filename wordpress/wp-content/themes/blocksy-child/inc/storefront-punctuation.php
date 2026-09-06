<?php
/** Keep automatically generated storefront punctuation consistent with edited copy. */
defined( 'ABSPATH' ) || exit;

function bactive_storefront_punctuation_enabled() {
    return ! is_admin() || ( defined( 'WC_DOING_AJAX' ) && WC_DOING_AJAX );
}

add_filter( 'document_title_separator', function ( $separator ) {
    return bactive_storefront_punctuation_enabled() ? '|' : $separator;
} );

// Preserve ASCII hyphens rather than letting wptexturize introduce new dash glyphs.
// All authored prose and ranges are edited at their source, not rewritten here.
add_filter( 'gettext_with_context', function ( $translation, $text, $context, $domain ) {
    if ( bactive_storefront_punctuation_enabled() && 'default' === $domain
        && ( ( 'en dash' === $context && '&#8211;' === $text )
            || ( 'em dash' === $context && '&#8212;' === $text ) ) ) {
        return '-';
    }
    return $translation;
}, 10, 4 );

add_filter( 'woocommerce_format_price_range', function ( $price ) {
    if ( ! bactive_storefront_punctuation_enabled() ) {
        return $price;
    }
    return str_replace( array( '&ndash;', '&#8211;', '&#x2013;', "\u{2013}" ), 'to', $price );
} );

add_filter( 'ngettext_with_context', function ( $translation, $single, $plural, $number, $context, $domain ) {
    if ( bactive_storefront_punctuation_enabled() && 'woocommerce' === $domain
        && 'with first and last result' === $context
        && 'Showing %1$d&ndash;%2$d of %3$d result' === $single
        && 'Showing %1$d&ndash;%2$d of %3$d results' === $plural ) {
        return str_replace( array( '&ndash;', '&#8211;', '&#x2013;', "\u{2013}" ), ' to ', $translation );
    }
    return $translation;
}, 10, 6 );
