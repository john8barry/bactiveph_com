# Brevo marketing 1.0.0 — local acceptance receipt

Tracking: [issue #16](https://github.com/john8barry/bactiveph_com/issues/16), [draft PR #20](https://github.com/john8barry/bactiveph_com/pull/20). Status: implemented, verified in CI, and running in staging test mode for the sole approved recipient. DOI confirmation and confirmed-list membership are verified. Brevo's Brand Library and seven active templates are configured; seven authorized marketing test messages were delivered, and the final Welcome message passed desktop Gmail visual review. Remaining workflow/staging acceptance and production release are pending.

## Result

The first-party plugin connects explicit newsletter consent to Brevo double opt-in, prepares the agreed marketing events, and protects the shared BACTIVE5 first-order coupon. SMTP2GO remains the WordPress/WooCommerce mail transport. Marketing is disabled and restricted to test recipients by default. Brevo account objects and authorized test emails are recorded below; no live coupon, production server file, or MailPoet setting was changed.

The approved Sage footer uses the shortcode in both tracked theme copies. The homepage adapter is WP-CLI only, defaults to a read-only plan and requires the exact reviewed page-14 content hash before applying. Seven deterministic email templates are included and match their active Brevo provider copies.

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
- [GitHub run 34007955440](https://github.com/john8barry/bactiveph_com/actions/runs/34007955440) passed all four jobs on runtime commit `64def75f0e9de73ec65fd42cc57daa693821051b`. Its expanded native fixtures passed 52 backend checks and 32 coupon checks in each storage mode, plus concurrent claim processes. This includes real cart/session capture, anonymous exclusion, quantity drift, accepted-stage deduplication and pending-COD cancellation. The redundant local expanded run passed HPOS before it was stopped after both CI modes passed; it is not counted as a completed local CPT run.
- Independent review covered consent possession, suppression races, durable enqueue, sending-state cancellation, first-order/order-pay protection, provider identity validation, site/mode binding, admin status fields and CI fixture boundaries. No outstanding source-review blockers were reported.
- New-file credential-pattern/binary scan and whitespace checks passed. The narrow pattern scan is not a repository-wide security clearance; existing security/dependency issues remain separate release considerations.
- Fixtures use an internal Docker network without host ports and block real HTTP/mail. Disposable containers, database and temporary files are removed by the runner.

## Remaining release requirements

1. The Brevo Free account, authenticated sender/domain, `move.bactiveph.com` branding, API key, confirmed list ID `3`, required contact attributes, protected webhook token, and managed Turnstile widget are configured. Cloudflare inbound routing and SMTP2GO records were preserved. The Brand Library and active templates are DOI `1`, Welcome `3`, Cart 2h `4`, Cart 24h `5`, Care `6`, Review `7`, and Winback `8`. Seven authorized marketing tests were delivered to the sole test recipient; the final Welcome render passed desktop Gmail review after switching the logo to Brevo's content-library host. Workflow IDs and the authenticated suppression webhook remain pending staging endpoint availability.
2. Resolve the payment dependency: PayMongo-marked purchase follow-ups remain `payment_unknown` until the payment integration supplies a complete public settlement classifier. Its current protection predicate is insufficient to authorize purchase marketing.
3. Exact commit `d452ca48daecab9e1ba918b783e6d572f2a54c47` is installed on staging as deterministic archive SHA-256 `4e27bbf52a3461d2bc39fe16c4a52c4f6962915705053aa08274312d8881b575`. Its fresh six-component, 122,567,295-byte backup passed off-server size, SHA-256 and archive-integrity checks. Plugin 1.0.0 runs in test mode with `jgbarry@gmail.com` as the sole recipient. Template ID `1` reads active and DOI-classified; DOI possession and list ID `3` membership are confirmed. Event sends remain blocked and the outbox state needs fresh host readback after the current shared-host writer finishes.
4. Verify mobile form/email rendering, actual Turnstile, cache headers, unsubscribe suppression, cron execution, workflow intake and scheduled inbox receipt. Desktop Gmail rendering is verified for the final Welcome template; this does not prove the remaining workflow paths.
5. Reconcile source, current MailPoet census, provider settings and host state before final production activation. Deactivate and later remove MailPoet only after replacement acceptance; preserve its suppression/export/settings/tables for rollback.

The payment coordinator acknowledged the classifier dependency and confirmed this lane is local only until account/canary readiness and an explicit staging handoff. Its current shared-host queue is email, footer, then the narrow hero correction. The classifier record alone does not trigger payment mutations or block ordinary consent/newsletter work.

Identification of new carts/orders requires the confirmed possession cookie, valid for 30 days. Account email alone is deliberately insufficient. Existing signed order proofs can support their later follow-ups. Event API acceptance and workflow receipts are not inbox-delivery proof.

Rollback and the full acceptance checklist are in [the migration runbook](../operations/brevo-migration.md). Keep the plugin loaded while saved campaign-discounted orders remain payable; disabling marketing and unpublishing the coupon must not remove their order-pay guard.
