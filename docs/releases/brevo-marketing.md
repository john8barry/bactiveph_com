# Brevo marketing 1.0.0 — local acceptance receipt

Tracking: [issue #16](https://github.com/john8barry/bactiveph_com/issues/16). Status: implemented locally; release and live acceptance pending. This receipt does not authorize a server change or email send.

## Result

The first-party plugin connects explicit newsletter consent to Brevo double opt-in, prepares the agreed marketing events, and protects the shared BACTIVE5 first-order coupon. SMTP2GO remains the WordPress/WooCommerce mail transport. Marketing is disabled and restricted to test recipients by default. No account objects, templates, real emails, live coupon, server files or MailPoet settings were changed by this implementation.

The approved Sage footer uses the shortcode in both tracked theme copies. The homepage adapter is WP-CLI only, defaults to a read-only plan and requires the exact reviewed page-14 content hash before applying. Seven email drafts are included for later provider setup.

## Source and package

- Feature branch: `codex/brevo-marketing-v1`, reconciled with main `5778889ba155c93eb12d0f49d52ee0196f481a53` before committing.
- Plugin version: `1.0.0`; PHP requirement: `8.2`.
- Deterministic runtime ZIP: 12 files; SHA256 `4e27bbf52a3461d2bc39fe16c4a52c4f6962915705053aa08274312d8881b575`.
- The ZIP contains only the plugin entry point, PHP includes and browser assets. Its contents were compared byte-for-byte with the reviewed source; tests, scripts, email drafts and credentials are excluded.
- Rebuild with `python3 wordpress/wp-content/plugins/bactive-brevo-marketing/build-release.py <new-output-path.zip>`. Build output must remain outside the plugin source tree.

## Local evidence

- PHP syntax, 65 backend assertions, 80 coupon assertions and frontend/admin boundary tests passed on PHP 8.1, 8.2 and 8.3. PHP 8.1 is an additional compatibility check, not a supported deployment target.
- Browser-script syntax and the signup client tests passed, including cached nonce refresh, duplicate submission, CAPTCHA and ambiguous network outcomes.
- Actual WordPress 7.0, WooCommerce 10.8.1 and MariaDB fixtures passed with HPOS and legacy order storage: 43 backend checks and 32 coupon checks per mode, plus separate concurrent checkout claim processes and idempotent retries.
- The additional real cart/session regression is being checked before the final commit; its result must be recorded below.
- Independent review covered consent possession, suppression races, durable enqueue, sending-state cancellation, first-order/order-pay protection, provider identity validation, site/mode binding, admin status fields and CI fixture boundaries. No outstanding source-review blockers were reported.
- New-file credential-pattern/binary scan and whitespace checks passed. The narrow pattern scan is not a repository-wide security clearance; existing security/dependency issues remain separate release considerations.
- Fixtures use an internal Docker network without host ports and block real HTTP/mail. Disposable containers, database and temporary files are removed by the runner.

## Remaining release requirements

1. John completes the new Brevo Free account sign-in, password and terms. Configure exact sender/domain/list/template/workflow IDs and protected secrets after account access is available. Preserve Cloudflare inbound routing and SMTP2GO records.
2. Resolve the payment dependency: PayMongo-marked purchase follow-ups remain `payment_unknown` until the payment integration supplies a complete public settlement classifier. Its current protection predicate is insufficient to authorize purchase marketing.
3. Obtain the serialized host window and verify a fresh complete off-server backup. Test the exact package on staging with the approved recipient allowlist and concrete test-send authorization. Production WordPress was inventoried as 7.1; the repository fixture is 7.0.
4. Verify desktop/mobile form rendering, actual Turnstile, cache headers, DOI possession, unsubscribe suppression, cron execution, workflow intake and inbox receipt. The browser could not attach locally, so visual verification is not claimed.
5. Reconcile source, current MailPoet census, provider settings and host state before final production activation. Deactivate and later remove MailPoet only after replacement acceptance; preserve its suppression/export/settings/tables for rollback.

Identification of new carts/orders requires the confirmed possession cookie, valid for 30 days. Account email alone is deliberately insufficient. Existing signed order proofs can support their later follow-ups. Event API acceptance and workflow receipts are not inbox-delivery proof.

Rollback and the full acceptance checklist are in [the migration runbook](../operations/brevo-migration.md). Keep the plugin loaded while saved campaign-discounted orders remain payable; disabling marketing and unpublishing the coupon must not remove their order-pay guard.
