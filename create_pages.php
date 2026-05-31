<?php
require( dirname( __FILE__ ) . '/wp-load.php' );

function create_bactive_page($title, $slug) {
    $page = get_page_by_path($slug);
    if(!$page) {
        $id = wp_insert_post(array(
            'post_title' => $title,
            'post_name' => $slug,
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
        return $id;
    }
    return $page->ID;
}

// Create core pages
$home_id = create_bactive_page('Home', 'home');
create_bactive_page('About B Active', 'about');
create_bactive_page('Journal', 'journal');
create_bactive_page('Shipping & Returns', 'shipping-returns');
create_bactive_page('Contact', 'contact');

// Set Front Page
update_option('show_on_front', 'page');
update_option('page_on_front', $home_id);

echo "Pages created and front page set.";
unlink(__FILE__);
?>
