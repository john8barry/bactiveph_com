<?php
/**
 * Approved Sage welcome footer. Presentation only; payment visibility and
 * courier destinations continue to come from the existing trust-bar template.
 */
defined('ABSPATH') || exit;

$bactive_footer_theme_url = get_stylesheet_directory_uri();
$bactive_footer_logo = wp_get_attachment_image_src(
    (int) get_theme_mod('custom_logo'),
    'full'
);
?>
<!--
THESIS: A welcoming newsletter band gives B Active a clear, useful page ending.
OWN-WORLD: Sampled sage #99ab90, ivory #f9f7f4, charcoal #242222; Fraunces and Inter.
STORY: Join the club, find store information, then check delivery and payment options.
FIRST VIEWPORT: Sage signup above four ivory columns, a balanced service strip, and readable legal text.
FORM: User-approved Sage welcome comp, 2026-09-05; established footer refinement.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
-->
<style id="bactive-footer-sage-styles">
    .bactive-custom-footer.bactive-footer--sage {
        --footer-sage: #99ab90;
        --footer-ivory: #f9f7f4;
        --footer-ink: #242222;
        --footer-rule: #e4dcd2;
        --footer-focus: #40543c;
        margin: 0;
        padding: 0;
        border: 0;
        background: var(--footer-ivory);
        color: var(--footer-ink);
        font-family: 'Inter', sans-serif;
        font-size: clamp(16px, 1.2vw, 20px);
        line-height: 1.5;
        text-align: left;
    }
    .bactive-footer--sage *,
    .bactive-footer--sage *::before,
    .bactive-footer--sage *::after { box-sizing: border-box; }
    .bactive-footer--sage ::selection { background: var(--footer-ink); color: var(--footer-ivory); }
    .bactive-footer--sage .bactive-footer__inner {
        width: min(1464px, 87.5%);
        margin-inline: auto;
    }
    .bactive-footer--sage h2,
    .bactive-footer--sage h3 {
        margin: 0;
        color: var(--footer-ink) !important;
        font-family: 'Fraunces', Georgia, serif !important;
        font-weight: 400 !important;
        letter-spacing: -0.02em !important;
        text-wrap: balance;
    }
    .bactive-footer--sage a {
        color: var(--footer-ink);
        text-decoration: none;
        text-underline-offset: 0.22em;
        transition: color 160ms ease-out !important;
    }
    .bactive-footer--sage a:hover { color: var(--footer-focus); text-decoration: underline; }
    .bactive-footer--sage a:focus-visible,
    .bactive-footer--sage input:focus-visible,
    .bactive-footer--sage button:focus-visible {
        outline: 2px solid var(--footer-focus);
        outline-offset: 4px;
    }
    .bactive-footer--sage .bactive-footer__signup {
        padding-block: 48px 54px;
        background: var(--footer-sage);
    }
    .bactive-footer--sage .bactive-footer__signup-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
        align-items: center;
        gap: 64px;
    }
    .bactive-footer--sage .bactive-footer__signup h2 {
        font-size: clamp(36px, 3.35vw, 56px);
        line-height: 1.15;
    }
    .bactive-footer--sage .bactive-footer__signup p {
        margin: 14px 0 0;
        color: var(--footer-ink);
        line-height: 1.6;
    }
    .bactive-footer--sage .bactive-footer__form {
        display: flex;
        width: 100%;
        min-width: 0;
        margin: 0;
        align-items: stretch;
    }
    .bactive-footer--sage .bactive-footer__form input[type="email"] {
        flex: 1 1 0;
        width: 0;
        min-width: 0;
        height: 72px;
        margin: 0;
        padding: 20px 24px;
        border: 1px solid var(--footer-ink);
        border-right: 0;
        border-radius: 3px 0 0 3px;
        background: var(--footer-ivory);
        color: var(--footer-ink);
        caret-color: var(--footer-focus);
        box-shadow: none;
        font: inherit;
    }
    .bactive-footer--sage .bactive-footer__form input::placeholder {
        color: #5d5b58;
        opacity: 1;
    }
    .bactive-footer--sage .bactive-footer__form button {
        flex: 0 0 25%;
        min-width: 104px;
        min-height: 72px;
        margin: 0;
        padding: 18px 24px !important;
        border: 1px solid var(--footer-ink) !important;
        border-radius: 0 3px 3px 0 !important;
        background: var(--footer-ink) !important;
        color: var(--footer-ivory) !important;
        box-shadow: none !important;
        font-family: 'Inter', sans-serif !important;
        font-size: inherit;
        font-weight: 500 !important;
        letter-spacing: 0.01em !important;
        line-height: 1.3;
        transform: none !important;
        transition: background-color 160ms ease-out !important;
    }
    .bactive-footer--sage .bactive-footer__form button:hover {
        border-color: var(--footer-focus) !important;
        background: var(--footer-focus) !important;
        transform: none !important;
    }
    .bactive-footer--sage .bactive-footer__main {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) repeat(3, minmax(0, 1fr));
        gap: clamp(32px, 4.5vw, 76px);
        padding-block: 44px 32px;
    }
    .bactive-footer--sage .bactive-footer__brand { min-width: 0; }
    .bactive-footer--sage .bactive-footer__logo-link {
        display: block;
        width: min(300px, 100%);
        margin-bottom: 20px;
    }
    .bactive-footer--sage .bactive-footer__logo {
        display: block;
        width: 100%;
        height: auto;
        max-height: 174px;
        object-fit: contain;
        object-position: left center;
    }
    .bactive-footer--sage .bactive-footer__contact {
        display: flex;
        width: fit-content;
        max-width: 100%;
        min-height: 44px;
        align-items: center;
        gap: 14px;
        overflow-wrap: anywhere;
    }
    .bactive-footer--sage .bactive-footer__contact svg {
        flex: 0 0 24px;
        width: 24px;
        height: 24px;
    }
    .bactive-footer--sage .bactive-footer__social {
        display: flex;
        gap: 24px;
        margin-top: 12px;
    }
    .bactive-footer--sage .bactive-footer__social a {
        display: flex;
        width: 44px;
        height: 44px;
        align-items: center;
        justify-content: center;
    }
    .bactive-footer--sage .bactive-footer__social img {
        display: block;
        width: 32px;
        height: 32px;
        object-fit: contain;
    }
    .bactive-footer--sage .bactive-footer__links h3 {
        margin-bottom: 12px;
        font-size: clamp(23px, 1.7vw, 28px);
        line-height: 1.35;
        text-transform: uppercase;
    }
    .bactive-footer--sage .bactive-footer__links ul {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .bactive-footer--sage .bactive-footer__links li { margin: 0; padding: 0; }
    .bactive-footer--sage .bactive-footer__links li::before { content: none; }
    .bactive-footer--sage .bactive-footer__links a {
        display: block;
        width: fit-content;
        max-width: 100%;
        min-height: 40px;
        padding-block: 5px;
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust {
        grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
        align-items: start;
        gap: 48px;
        margin: 0;
        padding-block: 28px 24px;
        border-top: 1px solid var(--footer-rule);
        color: var(--footer-ink);
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__shipping {
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: nowrap;
        gap: 28px;
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__group {
        align-items: flex-start;
        gap: 12px;
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__group--payments {
        align-self: stretch;
        padding-left: 64px;
        border-left: 1px solid var(--footer-rule);
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__label {
        color: var(--footer-ink);
        font-size: clamp(14px, 1.05vw, 18px);
        font-weight: 400;
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__list { gap: 12px; }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__list--payments {
        grid-template-columns: repeat(6, minmax(0, 1fr));
        width: 100%;
        gap: 12px;
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge {
        width: 100%;
        height: 50px;
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--carrier {
        width: 156px;
        height: 56px;
        border-color: #d8cfc2;
        border-radius: 7px;
        background: transparent;
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--lbc { width: 172px; }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--jt img { max-width: 110px; max-height: 32px; }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--lbc span { font-size: 15px; }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--grab img { max-width: 120px; max-height: 32px; }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__processor {
        align-self: center;
        justify-content: center;
        margin-top: 2px;
        color: var(--footer-ink);
        font-size: clamp(13px, 1vw, 16px);
    }
    .bactive-custom-footer.bactive-footer--sage .bactive-trust__processor img { width: 125px; }
    .bactive-footer--sage .bactive-footer__legal {
        padding-block: 22px 30px;
        border-top: 1px solid var(--footer-rule);
        font-size: clamp(13px, 1vw, 16px);
        line-height: 1.8;
        text-align: center;
    }
    .bactive-footer--sage .bactive-footer__legal p {
        max-width: 1280px;
        margin: 0 auto 12px;
    }
    .bactive-footer--sage .bactive-footer__legal p:last-child { margin-bottom: 0; }
    .bactive-footer--sage .bactive-footer__legal a {
        display: inline-block;
        padding: 5px 4px;
    }
    @media (max-width: 1439px) {
        .bactive-footer--sage .bactive-footer__signup-layout { gap: 40px; }
        .bactive-footer--sage .bactive-footer__main { gap: 32px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust { gap: 28px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__group--payments { padding-left: 28px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--carrier { width: 132px; height: 48px; padding-inline: 10px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--lbc { width: 142px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--lbc span { font-size: 12px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--jt img { max-width: 94px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__badge--grab img { max-width: 110px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__list--payments { gap: 8px; }
    }
    @media (max-width: 1279px) {
        .bactive-custom-footer.bactive-footer--sage .bactive-trust { grid-template-columns: 1fr; gap: 28px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__shipping { justify-content: flex-start; gap: 40px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__group--payments { border-left: 0; padding-left: 0; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__list--payments { max-width: 760px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__processor { align-self: flex-start; }
    }
    @media (max-width: 899px) {
        .bactive-footer--sage .bactive-footer__signup-layout { grid-template-columns: 1fr; gap: 26px; }
        .bactive-footer--sage .bactive-footer__signup { padding-block: 36px 40px; }
        .bactive-footer--sage .bactive-footer__form { max-width: 610px; }
        .bactive-footer--sage .bactive-footer__form input[type="email"] { height: 60px; }
        .bactive-footer--sage .bactive-footer__form button { min-height: 60px; }
        .bactive-footer--sage .bactive-footer__main { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 36px 32px; }
        .bactive-footer--sage .bactive-footer__logo-link { width: min(260px, 100%); }
        .bactive-footer--sage .bactive-footer__links a { min-height: 44px; padding-block: 10px; }
    }
    @media (max-width: 599px) {
        .bactive-footer--sage .bactive-footer__inner { width: calc(100% - 40px); }
        .bactive-footer--sage .bactive-footer__signup h2 { font-size: 36px; }
        .bactive-footer--sage .bactive-footer__form input[type="email"] { height: 56px; padding-inline: 16px; }
        .bactive-footer--sage .bactive-footer__form button { min-width: 92px; min-height: 56px; padding: 14px 18px !important; }
        .bactive-footer--sage .bactive-footer__main { gap: 32px 24px; padding-block: 32px; }
        .bactive-footer--sage .bactive-footer__brand { grid-column: 1 / -1; }
        .bactive-footer--sage .bactive-footer__logo-link { width: 250px; }
        .bactive-footer--sage .bactive-footer__links--brand { grid-column: 1 / -1; }
        .bactive-footer--sage .bactive-footer__links--brand ul { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 24px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__shipping { flex-direction: column; gap: 24px; }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__group--payments { padding-top: 24px; border-top: 1px solid var(--footer-rule); }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__list--payments { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .bactive-custom-footer.bactive-footer--sage .bactive-trust__processor { flex-wrap: wrap; }
        .bactive-footer--sage .bactive-footer__legal { padding-block: 22px 24px; line-height: 1.7; }
        .bactive-footer--sage .bactive-footer__legal a { min-height: 44px; padding-block: 10px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .bactive-footer--sage a,
        .bactive-footer--sage button { transition: none !important; }
    }
</style>
<footer class="bactive-custom-footer bactive-footer--sage" data-bactive-footer-version="sage-2026-09-05">
    <section class="bactive-footer__signup" aria-labelledby="bactive-footer-signup-title">
        <div class="bactive-footer__inner bactive-footer__signup-layout">
            <div>
                <h2 id="bactive-footer-signup-title">Stay in the loop</h2>
                <p id="bactive-footer-signup-description">Join the club for 5% off your first order and new drops.</p>
            </div>
            <?php // Preserve the existing form behavior; do not invent a mailing-list connection. ?>
            <form class="bactive-footer__form" action="#" aria-labelledby="bactive-footer-signup-title">
                <input type="email" aria-label="Email address" aria-describedby="bactive-footer-signup-description" placeholder="Email address" autocomplete="email" />
                <button type="submit">Join</button>
            </form>
        </div>
    </section>
    <div class="bactive-footer__inner">
        <div class="bactive-footer__main">
            <div class="bactive-footer__brand">
                <a class="bactive-footer__logo-link" href="<?php echo esc_url(home_url('/')); ?>" aria-label="B Active home">
                    <?php if ($bactive_footer_logo) : ?>
                        <img class="bactive-footer__logo" src="<?php echo esc_url($bactive_footer_logo[0]); ?>" width="<?php echo esc_attr($bactive_footer_logo[1]); ?>" height="<?php echo esc_attr($bactive_footer_logo[2]); ?>" alt="B Active" loading="lazy" decoding="async" />
                    <?php else : ?>
                        <?php esc_html_e('B Active', 'blocksy-child'); ?>
                    <?php endif; ?>
                </a>
                <a class="bactive-footer__contact" href="mailto:hello@bactiveph.com">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="m3 5 9 7 9-7"/></svg>
                    <span>hello@bactiveph.com</span>
                </a>
                <a class="bactive-footer__contact" href="tel:+639686899110">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7.6 3.4 5.1 2.5a1.8 1.8 0 0 0-2.3 1.2C1.2 10.9 13.1 22.8 20.3 21.2a1.8 1.8 0 0 0 1.2-2.3l-.9-2.5a1.8 1.8 0 0 0-2.3-1.1l-2.5.9a14.8 14.8 0 0 1-8-8l.9-2.5a1.8 1.8 0 0 0-1.1-2.3Z"/></svg>
                    <span>0968 689 9110</span>
                </a>
                <div class="bactive-footer__social">
                    <a href="https://www.instagram.com/bactiveph/" aria-label="Instagram"><img src="<?php echo esc_url($bactive_footer_theme_url . '/assets/images/ig_logo.png'); ?>" width="32" height="32" alt="" loading="lazy" decoding="async" /></a>
                    <a href="https://www.facebook.com/BarryActive/" aria-label="Facebook"><img src="<?php echo esc_url($bactive_footer_theme_url . '/assets/images/fb_logo.png'); ?>" width="32" height="32" alt="" loading="lazy" decoding="async" /></a>
                </div>
            </div>
            <nav class="bactive-footer__links" aria-labelledby="bactive-footer-shop-title">
                <h3 id="bactive-footer-shop-title">Shop</h3>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/collections/pickleball-dresses')); ?>">Pickleball Dresses</a></li>
                    <li><a href="<?php echo esc_url(home_url('/collections/skorts')); ?>">Skorts</a></li>
                    <li><a href="<?php echo esc_url(home_url('/collections/tops')); ?>">Tops &amp; Tanks</a></li>
                    <li><a href="<?php echo esc_url(home_url('/collections/sports-bras')); ?>">Sports Bras</a></li>
                    <li><a href="<?php echo esc_url(home_url('/collections/leggings')); ?>">Leggings</a></li>
                    <li><a href="<?php echo esc_url(home_url('/collections/sets')); ?>">Sets</a></li>
                    <li><a href="<?php echo esc_url(home_url('/collections/paddles')); ?>">Pickleball Paddles</a></li>
                </ul>
            </nav>
            <nav class="bactive-footer__links" aria-labelledby="bactive-footer-help-title">
                <h3 id="bactive-footer-help-title">Help</h3>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/shipping-returns')); ?>">Shipping &amp; Returns</a></li>
                    <li><a href="<?php echo esc_url(home_url('/size-guide')); ?>">Size Guide</a></li>
                    <li><a href="<?php echo esc_url(home_url('/faq')); ?>">FAQ</a></li>
                    <li><a href="<?php echo esc_url(home_url('/contact')); ?>">Contact</a></li>
                    <li><a href="<?php echo esc_url(home_url('/fabric-guide')); ?>">Fabric &amp; Care</a></li>
                </ul>
            </nav>
            <nav class="bactive-footer__links bactive-footer__links--brand" aria-labelledby="bactive-footer-brand-title">
                <h3 id="bactive-footer-brand-title">Brand</h3>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/about')); ?>">About</a></li>
                    <li><a href="<?php echo esc_url(home_url('/journal')); ?>">Journal</a></li>
                    <li><a href="<?php echo esc_url(home_url('/our-store')); ?>">Our Store</a></li>
                    <li><a href="<?php echo esc_url(home_url('/bir-registration/')); ?>">BIR Registration</a></li>
                </ul>
            </nav>
        </div>
        <?php get_template_part('template-parts/trust-bar'); ?>
        <div class="bactive-footer__legal">
            <p><strong>B Active</strong> &middot; Registered address: Unit No. C07, Lombardy Bldg., Palmetto Place, Purok 16, Gem Village, Ma-a, Talomo District, 8000 City of Davao, Davao del Sur, Philippines</p>
            <p>&copy; 2026 B Active. All rights reserved. | <a href="<?php echo esc_url(home_url('/privacy')); ?>">Privacy</a> &middot; <a href="<?php echo esc_url(home_url('/terms')); ?>">Terms</a></p>
        </div>
    </div>
</footer>
