<?php
require 'wp-load.php';
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

add_filter('upload_mimes', function($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
});
add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
    if (strpos($filename, '.svg') !== false) {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}, 10, 4);

function upload_svg($file_path) {
    $file_array = [
        'name' => basename($file_path),
        'tmp_name' => ABSPATH . $file_path,
    ];
    if (!file_exists($file_array['tmp_name'])) {
        return false;
    }
    $id = media_handle_sideload($file_array, 0);
    return is_wp_error($id) ? false : $id;
}

$lockup_id = upload_svg('bactive-logo-lockup.svg');
$monogram_id = upload_svg('bactive-logo-monogram.svg');

if ($lockup_id) {
    update_option('theme_mods_blocksy', array_merge(get_option('theme_mods_blocksy', []), ['custom_logo' => $lockup_id]));
    set_theme_mod('custom_logo', $lockup_id);
}
if ($monogram_id) {
    update_option('site_icon', $monogram_id);
}
echo "Logo: $lockup_id, Favicon: $monogram_id";
