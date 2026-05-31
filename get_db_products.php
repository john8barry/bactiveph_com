<?php
require_once('wp-load.php');
global $wpdb;
$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'product'");
echo "Total products in DB: $count\n";

$results = $wpdb->get_results("SELECT post_title, post_status, post_name FROM {$wpdb->prefix}posts WHERE post_type = 'product'");
foreach($results as $row) {
    echo $row->post_title . ' - ' . $row->post_name . ' (' . $row->post_status . ')\n';
}
?>