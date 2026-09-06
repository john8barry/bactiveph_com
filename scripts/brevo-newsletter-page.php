<?php
/** Exact homepage adapter. WP-CLI only; default is a read-only plan. */
if (!defined('WP_CLI') || WP_CLI !== true) {
    exit(1);
}
$site = rtrim(home_url(), '/');
if (!in_array($site, ['https://bactiveph.com', 'https://staging.bactiveph.com'], true)
    || rtrim(site_url(), '/') !== $site || (int) get_option('page_on_front') !== 14) {
    WP_CLI::error('Homepage target does not match the reviewed site.');
}
$page = get_post(14);
if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
    WP_CLI::error('Reviewed homepage is unavailable.');
}
$before = (string) $page->post_content;
$expected = '09ecc6e9965faee64f87fbe3a5be12fb9cf62578b47f32c20879286bcb855909';
$button = '<button type="submit" style="background-color:#2B2A28;color:#FAF8F4;border:none;border-radius:2px;padding:15px 30px;text-transform:uppercase;font-weight:600;cursor:pointer">Join</button>';
$replacement = "<!-- wp:shortcode -->\n[bactive_newsletter_form source=\"homepage\"]\n<!-- /wp:shortcode -->";
if (substr_count($before, $replacement) === 1 && strpos($before, $button) === false) {
    WP_CLI::success('Homepage shortcode is already present; no change made.');
    return;
}
if (!hash_equals($expected, hash('sha256', $before)) || substr_count($before, $button) !== 1) {
    WP_CLI::error('Homepage content changed. Review its new revision before applying.');
}
$after = str_replace($button, $replacement, $before);
$receipt = ['site' => $site, 'page_id' => 14, 'before_sha256' => $expected, 'after_sha256' => hash('sha256', $after)];
if (getenv('BACTIVE_BREVO_APPLY_PAGE') !== $site) {
    WP_CLI::line(wp_json_encode(['mode' => 'plan'] + $receipt));
    return;
}
if (!shortcode_exists('bactive_newsletter_form') || !class_exists('Bactive\\Brevo\\Config') || !\Bactive\Brevo\Config::readiness(false)['ready']) {
    WP_CLI::error('The reviewed signup integration is not ready.');
}
// Authorization and a verified off-server backup must precede setting the apply variable.
$result = wp_update_post(wp_slash(['ID' => 14, 'post_content' => $after]), true);
if (is_wp_error($result)) {
    WP_CLI::error('Homepage update did not complete. Independently inspect the target before retrying.');
}
clean_post_cache(14);
$saved = get_post(14);
if (!$saved || !hash_equals($receipt['after_sha256'], hash('sha256', $saved->post_content))) {
    WP_CLI::error('Homepage readback mismatch. Do not retry blindly.');
}
WP_CLI::line(wp_json_encode(['mode' => 'applied'] + $receipt));
