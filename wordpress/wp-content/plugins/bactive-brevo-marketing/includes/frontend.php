<?php
namespace Bactive\Brevo;

defined('ABSPATH') || exit;

/** Public signup UI. Marketing consent is independent of placing an order. */
final class Frontend {
    public static function register(): void {
        add_shortcode('bactive_newsletter_form', [self::class, 'shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_post_nopriv_bactive_brevo_subscribe', [self::class, 'submit']);
        add_action('admin_post_bactive_brevo_subscribe', [self::class, 'submit']);
        add_action('wp_ajax_nopriv_bactive_brevo_nonce', [self::class, 'nonce']);
        add_action('wp_ajax_bactive_brevo_nonce', [self::class, 'nonce']);
        add_action('woocommerce_before_checkout_form', [self::class, 'checkout'], 8);
    }

    public static function assets(): void {
        if (!Config::enabled()) {
            return;
        }
        $base = plugins_url('../assets/', __FILE__);
        wp_enqueue_style('bactive-newsletter', $base . 'newsletter.css', [], '0.1.0');
        wp_register_script('bactive-newsletter', $base . 'newsletter.js', [], '0.1.0', true);
        wp_register_script('bactive-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=bactiveNewsletterReady&render=explicit', ['bactive-newsletter'], null, true);
    }

    public static function shortcode($attributes = []): string {
        if (!Config::readiness(false)['ready']) {
            return '<p class="ba-newsletter-unavailable">Newsletter signup is temporarily unavailable. Please check back soon.</p>';
        }
        $attributes = shortcode_atts(['source' => 'homepage'], $attributes, 'bactive_newsletter_form');
        $source = in_array($attributes['source'], ['homepage', 'footer', 'checkout'], true) ? $attributes['source'] : 'homepage';
        $id = wp_unique_id('ba-newsletter-');
        wp_enqueue_script('bactive-turnstile');
        $status = isset($_GET['ba_signup']) && is_string($_GET['ba_signup']) ? sanitize_key(wp_unslash($_GET['ba_signup'])) : '';
        $messages = ['pending' => 'Check your inbox to confirm your signup. You can unsubscribe at any time.', 'expired' => 'This confirmation link has expired. Please sign up again below.', 'error' => 'We could not start your signup. Refresh this page and try again.'];
        $message = $status === 'confirmed' && (Consent::status()['state'] ?? '') === 'confirmed' ? 'You’re on the list. Look out for your B Active welcome email.' : ($messages[$status] ?? '');
        ob_start();
        ?>
        <form class="ba-newsletter-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-nonce-url="<?php echo esc_url(admin_url('admin-ajax.php?action=bactive_brevo_nonce')); ?>" aria-label="Join the B Active newsletter">
            <input type="hidden" name="action" value="bactive_brevo_subscribe">
            <input type="hidden" name="source" value="<?php echo esc_attr($source); ?>">
            <?php wp_nonce_field('bactive_brevo_subscribe', 'ba_signup_nonce', false); ?>
            <label for="<?php echo esc_attr($id); ?>email">Email address</label>
            <input id="<?php echo esc_attr($id); ?>email" name="email" type="email" autocomplete="email" inputmode="email" maxlength="254" placeholder="you@example.com" required>
            <label class="ba-newsletter-consent" for="<?php echo esc_attr($id); ?>consent">
                <input id="<?php echo esc_attr($id); ?>consent" type="checkbox" name="consent" value="1" required>
                <span>I’d like B Active emails about new drops, offers and my shopping activity. I can unsubscribe anytime. <a href="<?php echo esc_url(home_url('/privacy/')); ?>">Privacy policy</a></span>
            </label>
            <div class="ba-newsletter-captcha" data-sitekey="<?php echo esc_attr(Config::get('turnstile_site_key', '')); ?>"></div>
            <button type="submit">Join the club</button>
            <p class="ba-newsletter-status" role="status" aria-live="polite" aria-atomic="true"><?php echo esc_html($message); ?></p>
            <noscript><p>Please enable JavaScript to complete the signup security check.</p></noscript>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    public static function checkout(): void {
        if (!Config::enabled() || (function_exists('is_order_received_page') && is_order_received_page()) || (function_exists('is_checkout_pay_page') && is_checkout_pay_page())) {
            return;
        }
        echo '<details class="ba-newsletter-checkout"><summary>Join the B Active club</summary><p>Confirm your email to receive your first-order 5% code. Newsletter signup is optional and does not place your order.</p>';
        echo self::shortcode(['source' => 'checkout']); // All variable markup is escaped by shortcode().
        echo '</details>';
    }

    public static function submit(): void {
        nocache_headers();
        $nonce = isset($_POST['ba_signup_nonce']) && is_string($_POST['ba_signup_nonce']) ? wp_unslash($_POST['ba_signup_nonce']) : '';
        $valid = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && wp_verify_nonce($nonce, 'bactive_brevo_subscribe');
        $email = isset($_POST['email']) && is_string($_POST['email']) ? trim(wp_unslash($_POST['email'])) : '';
        $source = isset($_POST['source']) && is_string($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : '';
        $token = isset($_POST['cf-turnstile-response']) && is_string($_POST['cf-turnstile-response']) ? wp_unslash($_POST['cf-turnstile-response']) : '';
        $consent = isset($_POST['consent']) && $_POST['consent'] === '1';
        $result = new \WP_Error('signup_invalid', 'Refresh this page, check your email address and complete the consent and security checks.');
        if ($valid && $consent && is_email($email) && strlen($email) <= 254 && strlen($token) <= 2048 && in_array($source, ['homepage', 'footer', 'checkout'], true)) {
            $result = Consent::subscribe($email, $source, $token, true);
        }
        $ok = !is_wp_error($result);
        // Do not expose API responses, contact membership, addresses or internal failures.
        $message = $ok ? 'Check your inbox to confirm your signup. You can unsubscribe at any time.' : 'We could not start your signup. Refresh this page and complete all fields and the security check. If it still fails, please try again later.';
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            wp_send_json(['ok' => $ok, 'message' => $message], $ok ? 200 : 400);
        }
        $destination = $source === 'checkout' && function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');
        wp_safe_redirect(add_query_arg('ba_signup', $ok ? 'pending' : 'error', $destination), 303);
        exit;
    }

    /** Public cache-safe nonce bootstrap; it reveals no subscriber information. */
    public static function nonce(): void {
        nocache_headers();
        wp_send_json(['nonce' => wp_create_nonce('bactive_brevo_subscribe')]);
    }
}
