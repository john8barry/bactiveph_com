# Brevo marketing migration

Tracking: [issue #16](https://github.com/john8barry/bactiveph_com/issues/16). Priority: normal requested feature; production email continuity is a release requirement.

Status: local implementation. No Brevo account, live marketing activation, test send, or MailPoet removal has been completed by this task.

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

Seven unsent [email drafts](brevo-email-drafts/README.md) cover DOI and the six marketing messages. The native Free branding, final field bindings, provider workflow intake/receipts, unsubscribe behavior and inbox delivery remain unverified until the account is ready.

The local browser could not attach, and Chrome was unavailable; desktop/mobile visual verification remains pending. Native datastore fixtures use repository WordPress 7.0 and WooCommerce 10.8.1; production was inventoried as WordPress 7.1, so staging remains the runtime parity check. No fixture has outbound network access or mail delivery.
