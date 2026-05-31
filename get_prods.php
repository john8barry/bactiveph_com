<?php
require 'wp-config.php';
global $wpdb;
$products = $wpdb->get_results("SELECT post_name, post_title FROM {$wpdb->prefix}posts WHERE post_type='product' AND post_status='publish' LIMIT 5");
foreach ($products as $p) {
    echo $p->post_name . " | " . $p->post_title . "
";
}
if (empty($products)) echo 'No products found.';
