# Brevo campaign operations

This runbook governs one-off B Active promotional or newsletter campaigns after the Brevo migration is accepted. It does not authorize a campaign, create a provider object, schedule mail, or replace SMTP2GO. A campaign is sent only when John gives a direct instruction for that campaign.

SMTP2GO remains the WordPress and WooCommerce transactional-mail transport. Brevo remains marketing-only.

## Confirmed provider reference

The following is the last authenticated evidence, recorded on 2026-09-10. Refresh it in Brevo before any campaign because provider state and daily capacity change independently of this repository.

| Object or fact | Confirmed value | Operating note |
| --- | --- | --- |
| Sender | `B Active <hello@bactiveph.com>` | Verified sender and reply-to for the reviewed templates. |
| Sending domain | `bactiveph.com` with `move.bactiveph.com` | Domain, DKIM and DMARC were authenticated; SMTP2GO and inbound Cloudflare routing were preserved. |
| Confirmed-subscriber list | `B Active Confirmed Subscribers`, ID `3` | The only approved marketing-list target. Recheck consent and suppression state before use. |
| DOI template | ID `1` | Native DOI template; it is not a promotional campaign template. |
| Marketing templates | Welcome `3`, Cart 2h `4`, Cart 24h `5`, Care `6`, Review `7`, Winback `8` | These are automation templates and must retain their event purpose. |
| Contact attributes | `BA_DOI_TOKEN`, `BA_CONSENT_SOURCE` | Required for double opt-in; never include their values in campaign exports or logs. |
| Authorized test identity | Provider contact ID `2` | Exclude it from campaigns. Do not record its email address in campaign material. |
| Campaigns and workflows | Zero campaigns; workflows paused; no suppression webhook | This is the current pre-activation state, not launch acceptance. |
| Coupon | `BACTIVE5`, no bound published coupon ID | The offer remains unavailable until its guarded coupon has a separately verified ID and publication record. |
| Free-plan capacity | 300 daily sends was last read back | The plugin reports only its local reservation cap. Check Brevo's current dashboard balance before a send; no paid upgrade is assumed. |

The provider's daily balance, workflow IDs, webhook ID, campaign-template ID and live coupon ID are currently unconfirmed or intentionally absent. Do not invent them in settings, logs, or release records.

## Preflight checklist

Complete and record these checks in issue #16 before preparing a campaign.

1. Confirm the exact production URL, deployed plugin archive hash, and current Brevo dashboard account. Reconcile the current MailPoet consent/suppression inventory without importing unconfirmed or unsubscribed contacts.
2. Run `wp bactive-brevo status` from the explicit WordPress path. Confirm the correct `site` and `mode`, fresh CLI-cron evidence, no unexpected overdue job, and no review-held or failed reason that needs resolution. The command is sanitized and deliberately does not read Brevo's balance.
3. In Brevo, read the current daily allowance and subtract all expected DOI, automation and campaign sends. Stop if the campaign would exceed the live allowance or leave inadequate capacity for consent and operational mail.
4. Build the audience from confirmed, unsuppressed contacts only. Exclude every approved test recipient, including provider contact ID `2`, and exclude anyone with ambiguous migration consent. Do not use historical MailPoet contacts as a fallback audience.
5. Review the campaign content in a reusable branded Brevo newsletter template. Record its newly assigned provider template ID only after its creation is authorized. Confirm sender, reply-to, subject, preview text, business address, privacy link, unsubscribe behavior, links, images, mobile layout and the lack of checkout/order data.
6. Send a proof only to the currently authorized test recipient. Verify actual inbox rendering and unsubscribe behavior. Provider acceptance, opens, and workflow receipts are not proof of inbox delivery.
7. Before a real send, record the content hash, audience count, exclusion count, planned local time, current dashboard allowance, template ID, campaign ID, operator and explicit authorization in issue #16. Obtain a final fresh dashboard readback immediately before scheduling or sending.

## Send, observe, and recover

Create one campaign per approved message. Do not establish a recurring promotional schedule. Schedule only within the confirmed allowance, and preserve the exact audience/exclusion filters with the campaign record.

For 30 minutes after a send, inspect Brevo campaign status, bounces, complaints, unsubscribes and the plugin's sanitized queue status. Recheck the next day and daily for seven days when an activation or a new campaign process is being accepted. Add newly reported hard bounces, complaints and unsubscribes through the authenticated suppression path; do not use a campaign response to grant consent.

If the audience, quota, template, sender, unsubscribe behavior, or delivery evidence is wrong, pause the unsent campaign and prevent additional sends. Keep existing suppression, consent, queue, campaign and coupon-claim evidence. Disabling marketing and pausing workflows does not change SMTP2GO or erase customer orders. Follow the migration runbook for the full operational rollback sequence.
