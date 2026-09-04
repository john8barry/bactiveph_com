<?php
require_once 'wp-load.php';

$page_slug = 'faq';
$page = get_page_by_path($page_slug);

$faq_content = '
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">FAQ</h1>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:paragraph -->
<p><strong>Do you ship nationwide?</strong><br>Yes — across the Philippines via J&T Express and Ninja Van, with free shipping over ₱2,000.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>What payment methods do you accept?</strong><br>Pay online through PayMongo using QRPh, Maya, ShopeePay, BPI Online, or UnionBank Online. Cash on Delivery is also available for eligible orders.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>What is "Asian fit"?</strong><br>Our patterns are designed from Filipina measurements rather than adapted from Western sizing, so straps, lengths and waistbands sit where they should. We run true to size, S–XL.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Can I exchange for a different size?</strong><br>Yes — within 7 days of delivery, for unworn items with tags. See <a href="/shipping-returns">Shipping &amp; Returns</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Do the dresses have built-in shorts and a bra?</strong><br>Most do. Each product page lists exactly what\'s built in, the inseam length, and the support level.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Are there pockets?</strong><br>Yes — our dresses and skorts include a ball pocket; several styles add a phone pocket. Check the product page.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Do you have a physical store?</strong><br>Yes, in Davao City — see <a href="/our-store">Our Store</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>How do I track my order?</strong><br>You\'ll receive a tracking link by email when your order ships.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
';

if ($page) {
    wp_update_post(array(
        'ID' => $page->ID,
        'post_content' => $faq_content
    ));
    echo "FAQ page updated successfully.";
} else {
    // create the page
    $id = wp_insert_post(array(
        'post_title' => 'FAQ',
        'post_name' => 'faq',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => $faq_content
    ));
    echo "FAQ page created and updated successfully (ID: $id).";
}
?>
