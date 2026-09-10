<?php
/** Standalone guard/markup regression checks; no WordPress, network or database. */
namespace BactivePH\SageHeader {
    function realpath($p) { return rtrim($p, '/'); }
    function is_readable($p) { return !in_array($p, $GLOBALS['missing'], true); }
}
namespace {
    const ABSPATH = '/home/waypmvhk/bactiveph.com/';
    $missing = array(); $admin = false; $customize = false;
    $site = 'https://bactiveph.com'; $stylesheet = 'blocksy-child'; $actions = array(); $filters = array();
    function is_admin() { return $GLOBALS['admin']; }
    function is_customize_preview() { return $GLOBALS['customize']; }
    function get_stylesheet() { return $GLOBALS['stylesheet']; }
    function home_url($p = '') { return $GLOBALS['site'] . $p; }
    function site_url() { return $GLOBALS['site']; }
    function untrailingslashit($p) { return rtrim($p, '/'); }
    function get_stylesheet_directory() { return rtrim(ABSPATH, '/') . '/wp-content/themes/blocksy-child'; }
    function get_stylesheet_directory_uri() { return home_url('/wp-content/themes/blocksy-child'); }
    function add_action($name, $callback, $priority = 10) { $GLOBALS['actions'][$name] = $callback; }
    function add_filter(...$args) { $GLOBALS['filters'][] = $args; }
    function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    function esc_html($s) { return esc_attr($s); }
    function esc_url($s) { return esc_attr($s); }
    function is_page($s) { return $s === 'contact'; }
    class Blocksy_Header_Builder_Render {}
    require dirname(__DIR__) . '/wordpress/wp-content/mu-plugins/bactiveph-sage-header.php';
    function check($ok, $name) { if (!$ok) throw new \RuntimeException($name); echo "PASS $name\n"; }
    check(\BactivePH\SageHeader\ready(), 'complete bundle is eligible');
    check(\BactivePH\SageHeader\links('primary') === array(
        'Pickleball Looks' => '/pickleball-looks/',
        'About' => '/about-our-story/',
        'Contact' => '/contact/',
    ), 'primary destinations and URLs are preserved in requested priority order');
    check(\BactivePH\SageHeader\links('collections') === array(
        'Leggings' => '/collections/leggings',
        'Pickleball Dresses' => '/collections/pickleball-dresses',
        'Pilates & Yoga' => '/collections/pilates-and-yoga/',
        'Sets' => '/collections/sets',
        'Skorts' => '/collections/skorts',
        'Sports Bras' => '/collections/sports-bras',
        'Tops & Tanks' => '/collections/tops',
        'Shop All' => '/shop/',
    ), 'Shop categories stay alphabetical with Shop All last');
    foreach (array('/template-parts/header-sage.php', '/assets/css/header-sage.css', '/assets/js/header-sage.js') as $file) {
        $missing = array(get_stylesheet_directory() . $file);
        check(!\BactivePH\SageHeader\ready(), 'missing asset retains original header: ' . $file);
    }
    $missing = array(); $admin = true;
    check(!\BactivePH\SageHeader\ready(), 'admin excluded'); $admin = false; $customize = true;
    check(!\BactivePH\SageHeader\ready(), 'customizer retains native builder'); $customize = false;
    $site = 'https://unrelated.example'; check(!\BactivePH\SageHeader\ready(), 'wrong destination excluded'); $site = 'https://bactiveph.com';
    $stylesheet = 'another-theme'; check(!\BactivePH\SageHeader\ready(), 'wrong theme excluded'); $stylesheet = 'blocksy-child';
    $markup = '';
    foreach (array('desktop', 'mobile') as $device) {
        $logo = '<a class="site-logo-container" href="https://bactiveph.com/"><img src="logo.png" alt="B Active"></a>';
        ob_start(); include dirname(__DIR__) . '/wordpress/wp-content/themes/blocksy-child/template-parts/header-sage.php'; $markup .= ob_get_clean();
    }
    $dom = new \DOMDocument(); @$dom->loadHTML($markup); $xpath = new \DOMXPath($dom);
    check($xpath->query('//form[@method="get"][@role="search"]')->length === 2, 'both searches use ordinary GET');
    check($xpath->query('//input[@name="s"]')->length === 2, 'search query contract preserved');
    check($xpath->query('//header')->length === 0, 'no duplicate header landmark');
    check($xpath->query('//details[contains(@class,"bactive-header__collections")][@open]')->length === 1, 'mobile categories initially expanded');
    check($xpath->query('//a[@href="https://bactiveph.com/contact/"][@aria-current="page"]')->length === 2, 'current primary page exposed to assistive technology');
    check($xpath->query('//input[@id="bactive-header-search-desktop"]')->length === 1 && $xpath->query('//input[@id="bactive-header-search-mobile"]')->length === 1, 'search labels have distinct device targets');
    $desktopLabels = array();
    foreach ($xpath->query('//nav[contains(concat(" ",normalize-space(@class)," ")," bactive-header__primary ")]/*[self::a or self::details]') as $node) {
        $desktopLabels[] = trim($node->nodeName === 'details' ? $xpath->query('./summary', $node)->item(0)->textContent : $node->textContent);
    }
    check($desktopLabels === array('Shop', 'Pickleball Looks', 'About', 'Contact'), 'desktop top-level navigation follows requested priority order');
    $mobileLabels = array();
    foreach ($xpath->query('//nav[contains(concat(" ",normalize-space(@class)," ")," bactive-header__mobile-panel ")]/*[self::details[contains(concat(" ",normalize-space(@class)," ")," bactive-header__collections ")] or self::div[contains(concat(" ",normalize-space(@class)," ")," bactive-header__mobile-primary ")]]') as $node) {
        if ($node->nodeName === 'details') {
            $mobileLabels[] = trim($xpath->query('./summary', $node)->item(0)->textContent);
            continue;
        }
        foreach ($xpath->query('./a', $node) as $link) {
            $mobileLabels[] = trim($link->textContent);
        }
    }
    check($mobileLabels === array('Shop', 'Pickleball Looks', 'About', 'Contact'), 'mobile top-level navigation follows requested priority order');
    $expectedCollections = array('Leggings', 'Pickleball Dresses', 'Pilates & Yoga', 'Sets', 'Skorts', 'Sports Bras', 'Tops & Tanks', 'Shop All');
    foreach (array(
        'desktop' => '//nav[contains(concat(" ",normalize-space(@class)," ")," bactive-header__primary ")]//div[contains(concat(" ",normalize-space(@class)," ")," bactive-header__dropdown ")]/a',
        'mobile' => '//nav[contains(concat(" ",normalize-space(@class)," ")," bactive-header__mobile-panel ")]//div[contains(concat(" ",normalize-space(@class)," ")," bactive-header__collection-links ")]/a',
    ) as $deviceName => $query) {
        $collectionLabels = array();
        foreach ($xpath->query($query) as $node) {
            $collectionLabels[] = trim($node->textContent);
        }
        check($collectionLabels === $expectedCollections, $deviceName . ' renders title-case alphabetical categories with Shop All last');
    }
    check(!str_contains($markup, 'role="menu"'), 'ordinary site navigation semantics retained');
    echo "Header guard and markup checks passed.\n";
}
