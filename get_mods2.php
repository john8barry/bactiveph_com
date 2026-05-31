<?php
require 'wp-config.php';
$mods = get_option('theme_mods_blocksy');
if ($mods && isset($mods['color_palette'])) {
    file_put_contents('bactive_mods_export.json', json_encode($mods));
} else {
    echo 'No mods found';
}
