<?php
require( dirname( __FILE__ ) . '/wp-load.php' );

function update_page_content($title, $content) {
    $page = get_page_by_title($title);
    if($page) {
        wp_update_post([
            'ID' => $page->ID,
            'post_content' => $content
        ]);
        echo "Updated page: $title\n";
    }
}

$home_content = '
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Court-Ready Confidence. Designed for the Asian Fit.</h1>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Premium women’s pickleball apparel and activewear crafted in Davao City, Philippines. Elevate your game with buttery-soft, squat-proof fabrics and timeless designs.</p>
<!-- /wp:paragraph -->
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/shop/">Shop The Collection</a></div>
<!-- /wp:button -->
<!-- wp:heading -->
<h2 class="wp-block-heading">Why B Active?</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Because you shouldn’t have to compromise between performance and style. We designed B Active for women who want premium quality without the insane markups.</p>
<!-- /wp:paragraph -->
';

$about_content = '
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Our Story</h1>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>B Active was born on the pickleball courts of Davao City. We noticed a gap: premium, flattering activewear was either too expensive, or the fit was all wrong for Asian women.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>We created B Active to solve that. No fast-fashion synthetics. No awkward fits. Just buttery-soft, supportive, and elegant pieces designed to move with you—from the court to the coffee shop.</p>
<!-- /wp:paragraph -->
';

$court_edit_content = '
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">The Court Edit</h1>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Everything you need for your next match. Discover our curated collection of high-performance skorts, supportive sports bras, and breathable tops.</p>
<!-- /wp:paragraph -->
';

$size_guide_content = '
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Size Guide</h1>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We design specifically for the Asian fit. This means slightly shorter inseams on our leggings, tailored waistbands, and proportions that flatter.</p>
<!-- /wp:paragraph -->
';

update_page_content('Home', $home_content);
update_page_content('About / Our Story', $about_content);
update_page_content('The Court Edit', $court_edit_content);
update_page_content('Size Guide', $size_guide_content);

echo "Page content setup completed.";
?>
