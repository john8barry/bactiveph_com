<?php
require_once('wp-load.php');
$args = array('post_type' => 'product', 'posts_per_page' => -1, 'post_status' => 'any');
$loop = new WP_Query( $args );
while ( $loop->have_posts() ) : $loop->the_post();
    global $product;
    echo $product->get_slug() . " -> " . $product->get_permalink() . " (" . $product->get_status() . ")\n";
endwhile;
wp_reset_query();
?>