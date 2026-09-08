# Brevo marketing migration

Tracking: [issue #16](https://github.com/john8barry/bactiveph_com/issues/16). Priority: normal requested feature; production email continuity is a release requirement.

Status: [draft PR #20](https://github.com/john8barry/bactiveph_com/pull/20), with local and CI implementation checks passing. The Brevo Free account, authenticated sender/domain, branded subdomain, API key, confirmed-subscriber list, consent attributes, Brand Library, seven templates, and Turnstile widget are configured. The exact reviewed plugin package is installed on staging in test mode with `jgbarry@gmail.com` as its sole allowed recipient. The DOI was confirmed and Brevo list ID `3` contains that confirmed test contact. Seven marketing test messages were delivered to the approved Gmail address, including a final rendered Welcome check after correcting the logo host. No live marketing activation, historical contact import, workflow activation, webhook registration, coupon publication, or MailPoet removal has been completed. See the [acceptance receipt](../releases/brevo-marketing.md).

## Scope and decisions

B Active is replacing MailPoet with Brevo Free for consent-based marketing. SMTP2GO remains the sole WordPress/WooCommerce transactional transport. No paid upgrade is authorized. Coordinator: the BactivePH Brevo migration task; John Barry owns account setup and final acceptance.

- Welcome once after double opt-in, with shared first-order coupon BACTIVE5 for 5% off.
- Cart reminders at 2 and 24 hours, only for identified, confirmed subscribers. Any submitted order cancels the cart sequence, including pending payment and COD.
- Care advice 2 days after recorded payment; review request 14 days after Woo Completed; winback after 90 days. No additional discounts.
- No historical event replay or automatic customer/order import. Zero currently eligible MailPoet subscribers.
- Brevo handles marketing templates, unsubscribe and delivery reporting; a first-party Woo plugin checks current eligibility before each event. It does not replace wp_mail or add a third-party behavioral tracker.

## Evidence and dependencies

Authenticated production inventory found five MailPoet contacts: four globally unconfirmed and one unsubscribed. Four list memberships marked subscribed do not override global status. No campaigns, forms, automations, sending queues, recorded sends or confirmation sends exist. Recheck at cutover. Preserve the unsubscribed suppression; do not import the four unconfirmed contacts as marketable or send them a welcome campaign.

The current footer form is a placeholder and the homepage advertises 5% without an existing coupon. Checkout is classic WooCommerce; HPOS is enabled. Production is bactiveph.com; staging is staging.bactiveph.com. Source/live revision equivalence is unavailable: verify deployed artifact hashes and settings.

Dependencies: payment recovery issue #2/PR #4 supplies the coupon protection predicate; a complete settled-payment classifier remains unavailable, so PayMongo-dependent care/review/winback jobs are held for review. Footer issue #14/merged PR #17 supplies the approved Sage layout at main cf6a7fb923c7329ab03514e9f8aaa2bdd7a1d6ce. Transactional email task owns SMTP2GO. PayMongo task coordinates the shared-host writer queue. Security containment #9 and dependency triage #7 remain separate open records and must be checked before release.

## Implementation contract

Plugin: wordpress/wp-content/plugins/bactive-brevo-marketing. Shortcode: [bactive_newsletter_form source="footer"] or source="homepage". Classic checkout consent is optional and unchecked. New subscription requests require explicit consent, server-verified Turnstile and Brevo native double opt-in. Browser redirects alone never prove consent.

Protected server configuration only: BACTIVE_BREVO_API_KEY, BACTIVE_BREVO_WEBHOOK_TOKEN and BACTIVE_BREVO_TURNSTILE_SECRET. Never store their values in Git, admin HTML, logs or receipts. Nonsecret settings live in bactive_brevo_settings. Create Brevo TEXT attributes BA_DOI_TOKEN and BA_CONSENT_SOURCE before DOI testing.

The suppression receiver is `POST /wp-json/bactive-brevo/v1/webhook` on the exact configured site. Configure a protected Bearer authorization token of at least 32 characters and verify the host forwards it. Webhooks can suppress an address, never grant consent. Optional workflow-intake receipts require the exact event delivery key and matching contact/site/mode; test the actual Brevo envelope before relying on receipts.

The read-only operator command is `wp bactive-brevo status`. During the authorized test/release window, real cron must run `wp bactive-brevo run-due` against the explicit WordPress path. Readiness requires two actual CLI ticks at least 30 seconds apart, with the latest within ten minutes. Never enable workflows or change verification flags merely to bypass a readiness failure. Record actual acceptance evidence before setting them.

Due event names: ba_welcome_ready, ba_cart_reminder_ready, ba_post_purchase_ready and ba_winback_ready. Stage distinguishes cart 2h/24h and care/review. Brevo workflows send immediately after these events; delays belong to the local scheduler so eligibility is checked at dispatch. No payment/session keys, addresses, phone numbers or raw provider payloads belong in marketing events. Ambiguous event API responses are quarantined, never blindly retried.

BACTIVE5 must be provisioned explicitly as a draft, bound by ID and campaign marker, and published only during verified activation. Native Woo coupon counters remain authoritative. Separate atomic identity claims prevent concurrent first-order redemptions; historical purchases/refunds and unresolved payment recovery make a customer ineligible. Configuration or activation alone must not create a public coupon.

## Provider setup receipt (2026-09-07)

- Brevo authenticated `bactiveph.com`, verified `B Active <hello@bactiveph.com>`, and branded the domain with `move.bactiveph.com`. Public Cloudflare, Google, and authoritative DNS readback agreed on the branded CNAME, both Brevo DKIM selectors, the Brevo verification TXT, and the single combined DMARC record. The existing Cloudflare MX/SPF and SMTP2GO path were preserved.
- Brevo Free reported 300 daily sends available. The dedicated API key is stored only in the project-ignored protected environment file; its value is absent from Git, receipts, and provider IDs below.
- Confirmed-subscriber list: `B Active Confirmed Subscribers`, ID `3`, independently read back empty.
- Contact attributes: `BA_DOI_TOKEN` and `BA_CONSENT_SOURCE`, both normal TEXT attributes.
- Native DOI template: `B Active – Confirm your signup`, ID `1`, sender `hello@bactiveph.com`. Creation did not send an email.
- Cloudflare Turnstile widget: `bactiveph-newsletter`, public site key `0x4AAAAAAEsSyWIvaEIenlIM`, managed mode, limited to `bactiveph.com`, `staging.bactiveph.com`, and `www.bactiveph.com`. Its secret and the generated webhook bearer token are stored only in the protected environment file.
- No MailPoet contact was imported, no Brevo contact was added, no workflow or campaign was enabled, and no email was sent. The suppression webhook remains uncreated until the exact staging endpoint exists and can be tested.

## Disabled staging deployment receipt (2026-09-08)

- The staging target was independently identified as `https://staging.bactiveph.com`, database `waypmvhk_stg`, PHP 8.2.33, WordPress 7.1, active child theme `blocksy-child`, and `blog_public=0`.
- Before the first host mutation, a fresh Updraft backup completed with database, plugins, themes, uploads, mu-plugins, and other files. All six components, totalling 122,567,295 bytes, were copied off-server and verified by exact size, SHA-256, and ZIP/gzip integrity. Backup email reporting was suppressed and no resumption remained scheduled.
- Source commit `d452ca48daecab9e1ba918b783e6d572f2a54c47` produced the deterministic 12-file runtime archive SHA-256 `4e27bbf52a3461d2bc39fe16c4a52c4f6962915705053aa08274312d8881b575`. The server linted all ten PHP files and installed plugin version 1.0.0 from that archive.
- Independent readback verified all 12 installed runtime files. The plugin is active while marketing remains explicitly disabled; test mode is true, the test-recipient list is empty, launch cutoff is zero, provider workflow verification is false, and no Brevo scheduler action exists. All five InnoDB tables were created and remain empty.
- The disabled shortcode returns only its unavailable message and checkout renders no newsletter panel. The PayMongo plugin/settings, recent-order fingerprint, child-theme functions and error log were unchanged across deployment. Brevo list ID `3` still has zero subscribers and the Free quota still reports 300. No provider write or email send occurred.
- Footer, homepage, MailPoet, webhook, workflow, coupon and cron changes were excluded. The next gate is an exact approved test-recipient address and separate authorization for a labeled DOI send.

## Staging DOI canary receipt (2026-09-08)

- John authorized test email only to `jgbarry@gmail.com`. Protected staging constants were installed without writing their values to Git, WordPress options, logs, command arguments, or receipts. Test mode remains true and that address is the sole recipient allowlist entry.
- Brevo rejected the initial DOI attempt definitively because template ID `1`, while active, was not classified as a DOI template. No ambiguous send occurred. The template retained the documented `{{ doubleoptin }}` link, received Brevo's `optin` tag, and then read back as both active and `doiTemplate=true`.
- The corrected plugin DOI request returned HTTP 201 exactly once. Brevo quota moved from 300 to 299 and its transactional log recorded requested, opened, and delivered for subject `Confirm your B Active signup`. An automated inbox scanner can produce an open, so it is not treated as consent or human inbox confirmation.
- Local state is `pending`, outbox rows remain zero, and confirmed list ID `3` remains empty until the recipient clicks the confirmation link. Purchase/event sends remain blocked by `automations_unverified` and `real_cron_unverified`.
- PayMongo settings/files, recent-order fingerprint, child-theme functions and the error log were unchanged. All host and browser connections were closed and the staging writer lane was released.
- Next control point: the recipient clicks the DOI link. Then verify the same browser's possession proof, Brevo list membership, local confirmed state, welcome-event queueing, and subsequent workflow delivery separately.

## Brand and template receipt (2026-09-08)

- Brevo's Brand Library now contains the B Active logo, Instagram and Facebook profiles, the sage `#99AB90`, ivory `#F9F7F4`, and charcoal `#242222` palette, and inbox-safe Arial heading/body fallbacks.
- Active provider templates are DOI ID `1`, Welcome ID `3`, Cart 2h ID `4`, Cart 24h ID `5`, Care ID `6`, Review ID `7`, and Winback 90d ID `8`. Authenticated API readback matched every provider template to the reviewed local HTML. All use `B Active <hello@bactiveph.com>` for sender and reply-to.
- Six initial marketing template tests were delivered to the sole authorized recipient. Gmail then exposed that its image proxy could not fetch the storefront-hosted logo through bot protection. All seven templates were updated to the account-owned Brevo content-library copy, with one final Welcome test sent after exact provider readback.
- The final Welcome test was requested and delivered at `2026-09-08T15:28:29+08:00`; quota moved from 293 to 292. Gmail Inbox visual readback showed the logo and complete branded layout, resolved `BACTIVE5`, branded `move.bactiveph.com` links, consent reason, registered address, privacy link, and unsubscribe link.
- Templates are ready for workflow wiring, but workflows remain paused. Welcome is held until the coupon is published and guarded; cart reminders need exact event routing and end-to-end staging proof; care, review, and winback remain held on the complete settled-payment classifier. The suppression webhook remains pending exact staging endpoint verification.

## Acceptance and release gates

1. Focused unit, failure-path and concurrency tests; real Woo classic checkout, order-pay and Store API tests with HPOS and legacy order storage. Demonstrate no wp_mail interception and no nonconsenting events.
2. Independent review of consent, guest identity, scheduler, coupon races, replay and ambiguous outcomes; secret scan and focused diff review before commit/PR.
3. New Free account verified, sender/domain authenticated, existing Cloudflare MX/SPF and SMTP2GO preserved. Confirm live plan limits and remaining quota; no paid action.
4. DOI, authenticated suppression webhooks and all four workflows configured. Templates reviewed with unsubscribe links, mandatory Brevo branding and the registered business address. Record exact nonsecret provider IDs.
5. Fresh full Updraft backup, verified complete and copied off-server, before any server mutation; serialized staging window from shared-host coordinator. Staging must stay noindex, test mode and exact-recipient allowlist only. Obtain concrete send approval before any email test.
6. Prove cron execution, DOI success/failure, unsubscribe suppression, coupon first-order rules, payment/refund eligibility and duplicate prevention end to end. Provider acceptance is not inbox delivery proof.
7. Reconcile current source/remote/live target, acquire production writer window and required final activation approval. Apply narrow artifacts/settings, activate marketing, replace the two placeholder forms preserving approved design, disable MailPoet checkout opt-in and deactivate MailPoet. Recheck forms and both email transports independently.
8. Preserve a private MailPoet export, settings and tables for rollback. Remove plugin files only after verified replacement acceptance and appropriate removal approval. Table purge is a separate destructive operation, not part of initial cutover.

## Rollback

Disable bactive_brevo_settings.enabled and pause the four Brevo workflows first. Keep this plugin active while any saved campaign-discounted order remains payable, because native order-pay does not revalidate first-order eligibility after plugin removal; preserve ambiguous-job ledger and suppression state. Remove the shortcode integration or display its unavailable state. Unpublish only the bound campaign coupon, keeping order claims and Woo counters. Restore previous theme snippets/settings from the exact backup if needed. SMTP2GO stays unchanged. Reactivating MailPoet must not reactivate campaigns or import suppressed contacts. Do not clear journals or restore a database over new customer orders.

## Provider references

- [Brevo Free limits](https://help.brevo.com/hc/en-us/articles/208580669-FAQs-What-are-the-limits-of-the-Free-plan)
- [Native DOI API](https://developers.brevo.com/reference/create-doi-contact)
- [Custom events API](https://developers.brevo.com/reference/create-event)
- [Authenticated webhooks](https://developers.brevo.com/reference/create-webhook)
- [WooCommerce coupon management](https://woocommerce.com/document/coupon-management/)

Official Woo connector 4.0.58 was inspected before choosing this narrow adapter. Its customer/order payloads include information unnecessary for these workflows, and its thank-you event does not prove payment. The adapter therefore uses current Woo payment state and a bounded schema rather than full connector sync.

## Local integration and remaining acceptance

The approved Sage footer now calls the shortcode in both tracked theme copies. The plugin adds consent and a security check below its existing email/button row. scripts/brevo-newsletter-page.php plans the exact page-14 placeholder replacement by default and refuses content drift from the authenticated SHA256. Setting its site-specific apply variable is reserved for the authorized backed-up deployment window.

The public form refreshes its nonce through an uncached same-origin endpoint so LiteSpeed page caching cannot retain an expired token. Exclude DOI callback queries and admin-post/admin-ajax/REST webhook responses from edge caching; verify actual response headers and forwarding of webhook authorization on staging. Signup confirmation requires the one-time emailed return proof, and neither a query parameter nor an unverified Woo account email establishes identity. Cart/order identification requires the possession cookie; purchase hooks retain a signed order-specific proof for later eligibility checks.

Seven [email templates](brevo-email-drafts/README.md) cover DOI and the six marketing messages and match their active provider copies. Desktop Gmail visual verification passed for the final Welcome message. Workflow event intake, suppression behavior, mobile rendering, and end-to-end scheduled delivery remain acceptance gates.

Native datastore fixtures use repository WordPress 7.0 and WooCommerce 10.8.1; production was inventoried as WordPress 7.1, so staging remains the runtime parity check. No fixture has outbound network access or mail delivery.
