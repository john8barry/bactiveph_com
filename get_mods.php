<?php
require 'wp-load.php';
$mods = get_option('theme_mods_blocksy', []);
echo json_encode($mods);
