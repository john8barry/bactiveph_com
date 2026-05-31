<?php
require( dirname( __FILE__ ) . '/wp-load.php' );

// 2. Check Static Front Page
$show_on_front = get_option('show_on_front');
$page_on_front = get_option('page_on_front');
$page_title = $page_on_front ? get_the_title($page_on_front) : 'None';
$hello_world = get_post(1);
$sample_page = get_page_by_title('Sample Page');

echo "--- Front Page Settings ---\n";
echo "Front page setting: $show_on_front\n";
echo "Page on front ID: $page_on_front ($page_title)\n";
echo "Hello World exists: " . ($hello_world && $hello_world->post_status !== 'trash' ? 'Yes (' . $hello_world->post_status . ')' : 'No') . "\n";
echo "Sample Page exists: " . ($sample_page && $sample_page->post_status !== 'trash' ? 'Yes (' . $sample_page->post_status . ')' : 'No') . "\n";

if($hello_world && $hello_world->post_status !== 'trash') wp_delete_post(1, true);

// 4. Check admin author exposure
echo "\n--- Users ---\n";
$users = get_users();
foreach($users as $user) {
    echo "User: {$user->user_login}, Display Name: {$user->display_name}, Nicename: {$user->user_nicename}\n";
}

// 5. Active Plugins
echo "\n--- Active Plugins ---\n";
$active_plugins = get_option('active_plugins');
foreach($active_plugins as $plugin) {
    echo "- $plugin\n";
}

// Purge LiteSpeed Cache
echo "\n--- Purge Cache ---\n";
if (class_exists('LiteSpeed\Purge')) {
    LiteSpeed\Purge::purge_all();
    echo "LiteSpeed Cache purged.\n";
} else if (has_action('litespeed_purge_all')) {
    do_action('litespeed_purge_all');
    echo "LiteSpeed Cache purged via action.\n";
} else {
    echo "LiteSpeed Cache not active or class not found.\n";
}
?>
