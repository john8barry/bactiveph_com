<?php
/**
 * Plugin Name: B Active Sage Header
 * Description: The approved B Active header, isolated from commerce and theme settings.
 * Version: 1.0.0
 */
namespace BactivePH\SageHeader;

defined('ABSPATH') || exit;

/** Enable only on the two named B Active installations, with a complete bundle. */
function ready() {
    $sites = array(
        '/home/waypmvhk/bactiveph.com' => 'https://bactiveph.com',
        '/home/waypmvhk/staging.bactiveph.com' => 'https://staging.bactiveph.com',
    );
    $root = realpath(ABSPATH);
    if (!isset($sites[$root]) || is_admin() || is_customize_preview()
        || get_stylesheet() !== 'blocksy-child'
        || untrailingslashit(home_url()) !== $sites[$root]
        || untrailingslashit(site_url()) !== $sites[$root]
        || realpath(get_stylesheet_directory()) !== $root . '/wp-content/themes/blocksy-child'
        || get_stylesheet_directory_uri() !== $sites[$root] . '/wp-content/themes/blocksy-child') {
        return false;
    }
    foreach (array('/template-parts/header-sage.php', '/assets/css/header-sage.css', '/assets/js/header-sage.js') as $file) {
        if (!is_readable(get_stylesheet_directory() . $file)) {
            return false;
        }
    }
    return class_exists('Blocksy_Header_Builder_Render');
}

add_action('wp_enqueue_scripts', static function () {
    if (!ready() || !wp_style_is('blocksy-child-custom', 'registered')) {
        return;
    }
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    wp_enqueue_style('bactive-sage-header', $uri . '/assets/css/header-sage.css', array('blocksy-child-custom'), filemtime($dir . '/assets/css/header-sage.css'));
    wp_enqueue_script('bactive-sage-header', $uri . '/assets/js/header-sage.js', array(), filemtime($dir . '/assets/js/header-sage.js'), array('strategy' => 'defer', 'in_footer' => true));

    // Keep Blocksy's document shell, schema, skip link and native drawer canvas.
    add_filter('blocksy:header:rows-render', static function ($content, $rows, $device) {
        if ($content !== null || !in_array($device, array('desktop', 'mobile'), true)) {
            return $content;
        }
        $renderer = new \Blocksy_Header_Builder_Render();
        $logo = $renderer->render_single_item('logo', array('device' => $device));
        if (!is_string($logo) || trim($logo) === '') {
            return $content;
        }
        ob_start();
        include get_stylesheet_directory() . '/template-parts/header-sage.php';
        return ob_get_clean();
    }, 20, 3);

    add_filter('blocksy:header:wrapper-attr', static function ($attributes) {
        $attributes['class'] = trim(($attributes['class'] ?? '') . ' bactive-header--sage');
        return $attributes;
    });
}, 100);

/** All paths are owned storefront destinations, never request-derived URLs. */
function links($group) {
    if ($group === 'collections') {
        return array(
            'Shop all' => '/shop/',
            'Pickleball dresses' => '/collections/pickleball-dresses',
            'Skorts' => '/collections/skorts',
            'Tops & tanks' => '/collections/tops',
            'Sports bras' => '/collections/sports-bras',
            'Leggings' => '/collections/leggings',
            'Sets' => '/collections/sets',
            'Pickleball paddles' => '/collections/paddles',
        );
    }
    return array('Pickleball Looks' => '/pickleball-looks/', 'About' => '/about-our-story/', 'Contact' => '/contact/');
}

function current_attribute($path) {
    $slug = trim($path, '/');
    return (is_page($slug) || ($slug === 'shop' && function_exists('is_shop') && is_shop())) ? ' aria-current="page"' : '';
}

/** One fixed, authored outline icon set. */
function icon($name) {
    $paths = array(
        'search' => '<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
        'account' => '<circle cx="12" cy="7" r="4"/><path d="M3 22v-2c0-4 4-6 9-6s9 2 9 6v2Z"/>',
        'bag' => '<path d="M5 7h14l1 15H4L5 7Z"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="m4 4 16 16M20 4 4 20"/>',
    );
    if (isset($paths[$name])) {
        echo '<svg class="bactive-header__icon bactive-header__icon--' . esc_attr($name) . '" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
}

function search_form($device) {
    $id = 'bactive-header-search-' . $device;
    ?>
    <form class="bactive-header__search-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <label for="<?php echo esc_attr($id); ?>">Search B Active</label>
        <div class="bactive-header__search-fields">
            <input id="<?php echo esc_attr($id); ?>" type="search" name="s" placeholder="What are you looking for?" required>
            <button type="submit" aria-label="Submit search"><?php icon('search'); ?></button>
        </div>
    </form>
    <?php
}
