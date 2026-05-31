<?php
require_once('wp-load.php');
$args = array('post_type' => 'product', 'posts_per_page' => -1);
$loop = new WP_Query( $args );
while ( $loop->have_posts() ) : $loop->the_post();
    global $product;
    echo $product->get_slug() . " -> " . $product->get_permalink() . "\n";
endwhile;
wp_reset_query();
?>