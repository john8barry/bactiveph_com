<?php
/** Standalone footer navigation regression checks; no WordPress, network or database. */
define('ABSPATH', '/home/waypmvhk/bactiveph.com/');

function get_stylesheet_directory_uri() { return 'https://bactiveph.com/wp-content/themes/blocksy-child'; }
function get_theme_mod($name) { return $name === 'custom_logo' ? 1 : null; }
function wp_get_attachment_image_src() { return array('https://bactiveph.com/logo.png', 300, 174); }
function home_url($path = '') { return 'https://bactiveph.com' . $path; }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html_e($value) { echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function get_template_part($slug) { $GLOBALS['template_parts'][] = $slug; }
function shortcode_exists($tag) { $GLOBALS['shortcode_checks'][] = $tag; return true; }
function do_shortcode($code) { $GLOBALS['shortcodes'][] = $code; return '<form class="bactive-footer__form"></form>'; }
function check($ok, $name) { if (!$ok) throw new RuntimeException($name); echo "PASS $name\n"; }

$template_parts = array();
$shortcode_checks = array();
$shortcodes = array();
ob_start();
include dirname(__DIR__) . '/wordpress/wp-content/themes/blocksy-child/template-parts/footer-sage.php';
$markup = ob_get_clean();
$dom = new DOMDocument();
@$dom->loadHTML($markup);
$xpath = new DOMXPath($dom);

function links_for(DOMXPath $xpath, $title_id) {
    $links = array();
    $query = '//nav[h3[@id="' . $title_id . '"]]//ul/li/a';
    foreach ($xpath->query($query) as $node) {
        $links[trim($node->textContent)] = $node->getAttribute('href');
    }
    return $links;
}

check(links_for($xpath, 'bactive-footer-shop-title') === array(
    'Leggings' => 'https://bactiveph.com/collections/leggings',
    'Pickleball Dresses' => 'https://bactiveph.com/collections/pickleball-dresses',
    'Sets' => 'https://bactiveph.com/collections/sets',
    'Skorts' => 'https://bactiveph.com/collections/skorts',
    'Sports Bras' => 'https://bactiveph.com/collections/sports-bras',
    'Tops & Tanks' => 'https://bactiveph.com/collections/tops',
), 'footer Shop destinations and URLs are preserved alphabetically');
check(links_for($xpath, 'bactive-footer-help-title') === array(
    'Contact' => 'https://bactiveph.com/contact',
    'Fabric & Care' => 'https://bactiveph.com/fabric-guide',
    'FAQ' => 'https://bactiveph.com/faq',
    'Shipping & Returns' => 'https://bactiveph.com/shipping-returns',
    'Size Guide' => 'https://bactiveph.com/size-guide',
), 'footer Help destinations and URLs are preserved alphabetically');
check(links_for($xpath, 'bactive-footer-brand-title') === array(
    'About' => 'https://bactiveph.com/about',
    'BIR Registration' => 'https://bactiveph.com/bir-registration/',
    'Journal' => 'https://bactiveph.com/journal',
    'Our Store' => 'https://bactiveph.com/our-store',
), 'footer Brand destinations and URLs are preserved alphabetically');
check($template_parts === array('template-parts/trust-bar'), 'trust bar include is preserved');
check($shortcode_checks === array('bactive_newsletter_form'), 'newsletter shortcode availability is checked');
check($shortcodes === array('[bactive_newsletter_form source="footer"]'), 'newsletter shortcode keeps the footer source');
echo "Footer navigation checks passed.\n";
