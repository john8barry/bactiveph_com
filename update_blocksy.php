<?php
require 'wp-load.php';

$mods = get_option('theme_mods_blocksy', []);
$mods['color_palette'] = [
    ['color' => '#2B2A28', 'id' => 'paletteColor1'],
    ['color' => '#1C1B19', 'id' => 'paletteColor2'],
    ['color' => '#5E6E54', 'id' => 'paletteColor3'],
    ['color' => '#FAF8F4', 'id' => 'paletteColor4'],
    ['color' => '#FFFFFF', 'id' => 'paletteColor5'],
    ['color' => '#E6DFD5', 'id' => 'paletteColor6'],
    ['color' => '#9CAE92', 'id' => 'paletteColor7'],
    ['color' => '#D8A7A0', 'id' => 'paletteColor8'],
];

$mods['background_color'] = '#FAF8F4';
$mods['background_pattern'] = 'none';

$mods['font_family'] = 'Inter';
$mods['heading_font_family'] = 'Fraunces';
$mods['font_color'] = '#2B2A28';
$mods['heading_color'] = '#1C1B19';

// Apply primary button styles
$mods['button_background_color'] = '#2B2A28';
$mods['button_text_color'] = '#FAF8F4';
$mods['button_hover_background_color'] = '#5E6E54';
$mods['button_hover_text_color'] = '#FFFFFF';
$mods['button_border_radius'] = 2;
$mods['button_text_transform'] = 'uppercase';

update_option('theme_mods_blocksy', $mods);
echo 'Blocksy mods updated successfully!';
