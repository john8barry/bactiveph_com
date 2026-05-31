<?php
require 'wp-load.php';

function bactive_setup_footer_widgets() {
    $widgets = get_option('widget_text', array());
    $sidebars = get_option('sidebars_widgets', array());

    // 1. Shop
    $widgets[101] = array(
        'title' => 'Shop',
        'text' => '<ul>
<li><a href="/collections/pickleball-dresses">Pickleball Dresses</a></li>
<li><a href="/collections/skorts">Skorts</a></li>
<li><a href="/collections/tops-tanks">Tanks</a></li>
<li><a href="/collections/sports-bras">Sports Bras</a></li>
<li><a href="/collections/leggings">Leggings</a></li>
<li><a href="/collections/sets">Sets</a></li>
<li><a href="/collections/paddles">Pickleball Paddles</a></li>
</ul>',
        'filter' => false,
        'visual' => true,
    );
    $sidebars['ct-footer-sidebar-1'] = array('text-101');

    // 2. Help
    $widgets[102] = array(
        'title' => 'Help',
        'text' => '<ul>
<li><a href="/shipping-returns">Shipping & Returns</a></li>
<li><a href="/size-guide">Size Guide</a></li>
<li><a href="/faq">FAQ</a></li>
<li><a href="/contact">Contact</a></li>
<li><a href="/fabric-guide">Fabric & Care</a></li>
</ul>',
        'filter' => false,
        'visual' => true,
    );
    $sidebars['ct-footer-sidebar-2'] = array('text-102');

    // 3. Brand
    $widgets[103] = array(
        'title' => 'Brand',
        'text' => '<ul>
<li><a href="/about">About</a></li>
<li><a href="/journal">Journal</a></li>
<li><a href="/our-store">Our Store</a></li>
</ul>',
        'filter' => false,
        'visual' => true,
    );
    $sidebars['ct-footer-sidebar-3'] = array('text-103');

    // 4. Newsletter
    $widgets[104] = array(
        'title' => 'Stay in the loop',
        'text' => '<p>Join the club for 5% off your first order, new drops, and Davao court days.</p>
<input type="email" placeholder="Email address" style="width:100%; margin-bottom:10px;" />
<button style="width:100%">JOIN</button>
<div style="margin-top:15px; display:flex; gap:10px;">
  <a href="#">IG</a> <a href="#">FB</a> <a href="#">TikTok</a>
</div>',
        'filter' => false,
        'visual' => true,
    );
    $sidebars['ct-footer-sidebar-4'] = array('text-104');

    update_option('widget_text', $widgets);
    update_option('sidebars_widgets', $sidebars);
    
    // Also inject Blocksy mods for the footer bottom row
    $mods = get_option('theme_mods_blocksy', []);
    
    // Create the social accounts HTML
    $social_html = '
    <div style="display:flex; gap:15px; align-items:center;">
        <span>Ships nationwide via J&T & Ninja Van</span>
        <span><b>GCash | Maya | Visa | Mastercard | COD</b></span>
    </div>';

    $mods['copyright_text'] = '© 2026 B Active | <a href="/privacy">Privacy</a> · <a href="/terms">Terms</a>';
    
    // The socials placement can be used for custom HTML if we override it, or we just use footer_html
    $mods['footer_placements'] = [
        'current_section' => 'main',
        'sections' => [
            [
                'id' => 'main',
                'label' => 'Main Footer',
                'items' => [
                    [
                        'id' => 'footer_middle_row',
                        'placements' => [
                            ['id' => 'widget_area_1'],
                            ['id' => 'widget_area_2'],
                            ['id' => 'widget_area_3'],
                            ['id' => 'widget_area_4']
                        ]
                    ],
                    [
                        'id' => 'footer_bottom_row',
                        'placements' => [
                            ['id' => 'copyright'],
                            ['id' => 'socials']
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    $mods['socials'] = [
        [
            'id' => 'custom',
            'icon' => 'truck',
            'url' => '#',
            'label' => 'Ships nationwide via J&T & Ninja Van | GCash · Maya · Visa · Mastercard · COD'
        ]
    ];
    
    update_option('theme_mods_blocksy', $mods);
}

bactive_setup_footer_widgets();
echo "Footer widgets and layout applied successfully.";
