<?php
require_once 'wp-load.php';

$page = get_page_by_path('about');
if (!$page) {
    // Try finding by page-item-35 or other
    $page = get_post(35);
    if (!$page) {
        $pages = get_pages(array('meta_key' => '_wp_page_template', 'hierarchical' => 0));
        foreach($pages as $p) {
            if (strpos(strtolower($p->post_title), 'about') !== false) {
                $page = $p;
                break;
            }
        }
    }
}

if (!$page) {
    echo "About page not found.\n";
    exit;
}

$content = '
<!-- wp:group {"layout":{"type":"constrained","contentSize":"800px"}} -->
<div class="wp-block-group">
<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">It started with a love for the game.</h1>
<!-- /wp:heading -->

<!-- wp:spacer {"height":"30px"} -->
<div style="height:30px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">When our founder, Marnie, fell for pickleball, she ran into a problem every player here knows: you either pay a fortune for imported activewear that still doesn\'t fit quite right, or you settle for something generic that wasn\'t made with you in mind. Beautiful, well-made, well-priced pickleball wear — cut for a Filipina body — simply didn\'t exist.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">So she made it.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>B Active</strong> is a little nudge — to move, to play, to live a healthier, more active life. (The "B" is also for <strong>Barry</strong>, the family behind the brand.) We design premium-feeling pickleball dresses, skorts, sets and activewear that are built to perform and made to flatter — with an <strong>Asian fit</strong>, performance fabrics, and the details that matter on court: built-in shorts, ball pockets, four-way stretch, buttery-soft feel.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">We\'re proud to be <strong>born in Davao</strong> — on the same courts where the city\'s growing community of women play, compete and cheer each other on — and to ship to active women across the Philippines.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">Our promise is simple: <strong>quality you can feel, in every stitch.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/the-court-edit/">Ready to play? → Shop The Court Edit</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
';

$post_data = array(
    'ID'           => $page->ID,
    'post_content' => $content,
);

wp_update_post( $post_data );

echo "About page updated successfully (ID: " . $page->ID . ").\n";

// Also update Yoast SEO Meta
update_post_meta($page->ID, '_yoast_wpseo_title', 'Our Story — The Philippines\' Premium Women\'s Pickleball Brand | B Active');
update_post_meta($page->ID, '_yoast_wpseo_metadesc', 'B Active is a women\'s pickleball and activewear brand born in Davao City. Premium quality, an Asian fit, and fair prices. This is our story.');

echo "SEO meta updated.\n";
?>
