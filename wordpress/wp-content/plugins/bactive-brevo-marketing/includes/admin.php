<?php
namespace Bactive\Brevo;

defined('ABSPATH') || exit;

/** Nonsecret configuration only. Activation is a separate verified operation. */
final class Admin {
    public static function register(): void {
        add_action('admin_menu', static function (): void {
            add_options_page('B Active marketing', 'B Active marketing', 'manage_options', 'bactive-brevo', [self::class, 'page']);
        });
        add_action('admin_init', static function (): void {
            register_setting('bactive_brevo', 'bactive_brevo_settings', ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize']]);
        });
    }

    public static function sanitize($input): array {
        $current = get_option('bactive_brevo_settings', []);
        $current = is_array($current) ? $current : [];
        if (!current_user_can('manage_options') || !is_array($input)) {
            return $current;
        }
        foreach (['confirmed_list_id', 'doi_template_id', 'coupon_id'] as $name) {
            if (isset($input[$name]) && is_scalar($input[$name])) {
                $current[$name] = max(0, (int) $input[$name]);
            }
        }
        if (isset($input['turnstile_site_key']) && is_string($input['turnstile_site_key'])) {
            $current['turnstile_site_key'] = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($input['turnstile_site_key'], 0, 128));
        }
        if (isset($input['test_recipients']) && is_string($input['test_recipients'])) {
            $emails = preg_split('/[\s,;]+/', strtolower(trim($input['test_recipients'])));
            $current['test_recipients'] = array_values(array_unique(array_filter(array_slice($emails ?: [], 0, 10), 'is_email')));
        }
        if (array_key_exists('enabled_stages_present', $input)) {
            $allowed = ['welcome', 'cart', 'care', 'review', 'winback'];
            $requested = is_array($input['enabled_stages'] ?? null) ? $input['enabled_stages'] : [];
            $current['enabled_stages'] = array_values(array_unique(array_filter(
                array_slice($requested, 0, count($allowed)),
                static fn($stage): bool => is_string($stage) && in_array($stage, $allowed, true)
            )));
        }
        foreach (['daily_event_cap' => [1, 100], 'daily_signup_cap' => [1, 50], 'per_contact_daily_cap' => [1, 2]] as $name => $bounds) {
            if (isset($input[$name]) && is_scalar($input[$name])) {
                $current[$name] = min($bounds[1], max($bounds[0], (int) $input[$name]));
            }
        }
        // Never accept secrets, enabled, test_mode, launch_cutoff, or verification flags here.
        // Stage selection alone cannot send: the protected release gates still apply.
        return $current;
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $status = Config::enabled() ? 'Enabled for the configured site' : 'Disabled';
        $readiness = Config::readiness();
        $labels = [
            'disabled' => 'Marketing is switched off.', 'site_mismatch' => 'This site is outside the configured production/test targets.',
            'api_key_missing' => 'The protected Brevo API key is missing.', 'webhook_token_missing' => 'The protected webhook token is missing.',
            'turnstile_secret_missing' => 'The protected security-check secret is missing.', 'turnstile_site_key_missing' => 'The security-check site key is missing.',
            'confirmed_list_missing' => 'Choose the confirmed subscriber list.', 'doi_template_missing' => 'Choose the double opt-in template.',
            'invalid_redirect' => 'The confirmation return URL is invalid.', 'launch_cutoff_missing' => 'The launch cutoff has not been recorded.',
            'test_recipients_missing' => 'Add the approved test recipients.', 'woocommerce_missing' => 'WooCommerce is unavailable.',
            'automations_unverified' => 'Brevo workflows have not passed acceptance.', 'action_scheduler_missing' => 'The background scheduler is unavailable.',
            'real_cron_unverified' => 'Recent scheduled-command execution has not been verified.', 'storage_unavailable' => 'Marketing storage is unavailable.',
        ];
        ?>
        <div class="wrap">
            <h1>B Active marketing</h1>
            <p>Brevo manages newsletter and shopping follow-up emails. SMTP2GO continues to deliver WordPress and WooCommerce notifications.</p>
            <p><strong><?php echo esc_html($status); ?></strong> · <?php echo Config::get('test_mode', true) ? 'Test recipients only' : 'Live audience'; ?></p>
            <?php if (empty($readiness['ready'])) : ?>
                <h2>Before sending</h2>
                <ul>
                    <?php foreach (($readiness['blockers'] ?? []) as $blocker) : ?>
                        <li><?php echo esc_html($labels[$blocker] ?? 'A configuration check needs attention.'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p>Activation, audience changes and workflow verification belong to the migration acceptance step. Saving these settings does not enable sending.</p>
            <form method="post" action="options.php">
                <?php settings_fields('bactive_brevo'); ?>
                <table class="form-table" role="presentation">
                    <?php
                    foreach (['confirmed_list_id' => 'Confirmed subscriber list ID', 'doi_template_id' => 'Double opt-in template ID', 'coupon_id' => 'BACTIVE5 coupon ID', 'turnstile_site_key' => 'Turnstile site key', 'daily_event_cap' => 'Maximum marketing events per day', 'daily_signup_cap' => 'Maximum signup requests per day', 'per_contact_daily_cap' => 'Maximum events per contact per day'] as $key => $label) {
                        $number = $key !== 'turnstile_site_key';
                        $default = ['daily_event_cap' => 100, 'daily_signup_cap' => 50, 'per_contact_daily_cap' => 2][$key] ?? ($number ? 0 : '');
                        echo '<tr><th scope="row"><label for="ba-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="ba-' . esc_attr($key) . '" name="bactive_brevo_settings[' . esc_attr($key) . ']" type="' . ($number ? 'number' : 'text') . '" value="' . esc_attr((string) Config::get($key, $default)) . '"' . ($number ? ' min="0" step="1"' : ' maxlength="128" autocomplete="off"') . '></td></tr>';
                    }
                    ?>
                    <tr><th scope="row"><label for="ba-test-recipients">Test recipients</label></th><td><textarea id="ba-test-recipients" class="large-text" rows="3" name="bactive_brevo_settings[test_recipients]"><?php echo esc_textarea(implode("\n", (array) Config::get('test_recipients', []))); ?></textarea><p class="description">One exact approved email address per line, up to ten. Staging requires test mode and this allowlist.</p></td></tr>
                    <tr><th scope="row">Enabled marketing stages</th><td><fieldset>
                        <input type="hidden" name="bactive_brevo_settings[enabled_stages_present]" value="1">
                        <?php foreach (['welcome' => 'Welcome after confirmed signup', 'cart' => 'Cart reminders at 2 and 24 hours', 'care' => 'Care advice after verified payment', 'review' => 'Review request after completion', 'winback' => 'Winback after completion'] as $stage => $label) : ?>
                            <label style="display:block;margin-bottom:4px"><input type="checkbox" name="bactive_brevo_settings[enabled_stages][]" value="<?php echo esc_attr($stage); ?>" <?php checked(in_array($stage, Config::enabled_stages(), true)); ?>> <?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                        <p class="description">A stage is still blocked until the release cutoff, provider workflow acceptance, and fresh CLI-cron checks pass. Disable a stage before changing its workflow.</p>
                    </fieldset></td></tr>
                </table>
                <?php submit_button('Save configuration'); ?>
            </form>
            <h2>Protected configuration</h2>
            <p>The API key, webhook token and Turnstile secret must be supplied in protected server configuration. Their values are never displayed here.</p>
            <h2>Marketing queue</h2>
            <?php if (Store::ready()) : $queue = Store::status(); ?>
                <table class="widefat striped" style="max-width:700px">
                    <caption class="screen-reader-text">All stored queue events by state</caption>
                    <thead><tr><th scope="col">State</th><th scope="col">All stored events</th></tr></thead>
                    <tbody>
                    <?php foreach (['pending' => 'Waiting for eligibility and schedule', 'accepted' => 'Provider acceptance', 'workflow_received' => 'Workflow intake', 'review_required' => 'Needs review before any retry', 'failed' => 'Failed', 'suppressed' => 'Suppressed'] as $state => $label) : ?>
                        <tr><th scope="row"><?php echo esc_html($label); ?></th><td><?php echo esc_html((string) (int) ($queue['outbox'][$state] ?? 0)); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php $operational = $queue['queue']; $cron = $queue['cron']; ?>
                <h3>Current environment queue</h3>
                <p><?php echo esc_html((string) ($operational['scope']['site'] ?? '')); ?> · <?php echo esc_html((string) ($operational['scope']['mode'] ?? '')); ?> mode. Contact, cart, order, provider-payload, and delivery-key data are never shown here.</p>
                <?php if (!empty($operational['stages'])) : ?>
                    <table class="widefat striped" style="max-width:900px">
                        <thead><tr><th scope="col">Stage</th><th scope="col">Pending</th><th scope="col">Accepted</th><th scope="col">Workflow intake</th><th scope="col">Review held</th><th scope="col">Failed</th><th scope="col">Suppressed</th></tr></thead>
                        <tbody>
                        <?php foreach ($operational['stages'] as $stage => $counts) : ?>
                            <tr><th scope="row"><?php echo esc_html($stage); ?></th><?php foreach (['pending', 'accepted', 'workflow_received', 'review_required', 'failed', 'suppressed'] as $state) : ?><td><?php echo esc_html((string) (int) ($counts[$state] ?? 0)); ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No current-environment queue rows are recorded.</p>
                <?php endif; ?>
                <?php $overdue = $operational['overdue'] ?? []; ?>
                <p><strong>Overdue pending jobs:</strong> <?php echo esc_html((string) (int) ($overdue['count'] ?? 0)); ?><?php if (!empty($overdue['oldest_due_at'])) : ?>; oldest due <?php echo esc_html(gmdate('Y-m-d H:i:s', (int) $overdue['oldest_due_at']) . ' UTC'); ?> (<?php echo esc_html((string) (int) ($overdue['oldest_age_seconds'] ?? 0)); ?> seconds overdue)<?php endif; ?>.</p>
                <?php if (!empty($operational['review_error_breakdown'])) : ?>
                    <h3>Review and error reasons</h3>
                    <table class="widefat striped" style="max-width:700px">
                        <thead><tr><th scope="col">State</th><th scope="col">Reason</th><th scope="col">Events</th></tr></thead>
                        <tbody><?php foreach ($operational['review_error_breakdown'] as $entry) : ?><tr><td><?php echo esc_html((string) $entry['state']); ?></td><td><?php echo esc_html((string) $entry['reason']); ?></td><td><?php echo esc_html((string) (int) $entry['count']); ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                <?php endif; ?>
                <h3>Scheduler and sending allowance</h3>
                <p><strong>CLI cron:</strong> <?php echo esc_html((string) ($cron['state'] ?? 'not_recorded')); ?>; <?php echo esc_html((string) (int) ($cron['observed_ticks'] ?? 0)); ?> observed ticks<?php if (!empty($cron['last_tick_at'])) : ?>; last tick <?php echo esc_html(gmdate('Y-m-d H:i:s', (int) $cron['last_tick_at']) . ' UTC'); ?><?php endif; ?><?php if (($cron['age_seconds'] ?? null) !== null) : ?>; age <?php echo esc_html((string) (int) $cron['age_seconds']); ?> seconds<?php endif; ?>.</p>
                <?php $quota = $operational['quota'] ?? []; ?>
                <p><strong>Local event allowance for <?php echo esc_html((string) ($quota['period_utc'] ?? 'today')); ?> UTC:</strong> <?php echo esc_html((string) (int) ($quota['local_remaining'] ?? 0)); ?> remaining of <?php echo esc_html((string) (int) ($quota['local_event_limit'] ?? 0)); ?> after <?php echo esc_html((string) (int) ($quota['local_reservations'] ?? 0)); ?> reservations. Brevo dashboard quota is not queried by this plugin.</p>
                <p>Inbox delivery is verified separately in Brevo logs and the approved test inbox. Review-held events are never automatically resent.</p>
                <?php if (!empty($queue['storage_error']['code'])) : ?>
                    <p><strong>Queue storage needs attention:</strong> <?php echo esc_html((string) $queue['storage_error']['code']); ?> (<?php echo esc_html(gmdate('Y-m-d H:i:s', (int) ($queue['storage_error']['at'] ?? 0)) . ' UTC'); ?>). Review storage and the affected work before activation.</p>
                <?php endif; ?>
            <?php else : ?>
                <p>Queue status is unavailable until marketing storage is healthy.</p>
            <?php endif; ?>
            <h2>Signup forms</h2>
            <p>Use <code>[bactive_newsletter_form source="footer"]</code> or <code>[bactive_newsletter_form source="homepage"]</code>. Checkout includes an optional signup panel outside the order form.</p>
        </div>
        <?php
    }
}
