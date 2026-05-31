<?php
require 'wp-load.php';
$content = file_get_contents('homepage_updated.html');
if ($content) {
    wp_update_post(array(
        'ID' => 14,
        'post_content' => $content
    ));
    echo 'Post 14 updated successfully via PHP.';
} else {
    echo 'Failed to read homepage_updated.html';
}
