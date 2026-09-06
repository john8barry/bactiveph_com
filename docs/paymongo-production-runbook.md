# PayMongo Hosted Checkout production runbook

This runbook is the payment authority for B Active. It supersedes older build
notes that mention GCash, cards, GrabPay, Atome, or manual bank transfer.

## Approved customer payment methods

- PayMongo Hosted Checkout: QRPh, Maya, ShopeePay, BPI Direct Debit, and UBP
  Direct Debit only.
- WooCommerce Cash on Delivery remains available under the existing fee and
  order-value rules.
- WooCommerce manual bank transfer (`bacs`) is not offered.
- Legacy PayMongo WooCommerce gateways are not offered.

WooCommerce owns the catalog, cart, customer details, shipping, taxes, coupons,
and order. Clicking **Checkout securely** creates a pending WooCommerce order
and redirects the customer to `https://checkout.paymongo.com`. PayMongo owns
only the hosted payment-method selection and authorization screen. A browser
return is never treated as payment proof. Payment is confirmed by a valid signed
`checkout_session.payment.paid` webhook or authenticated, mode-correct recovery
readback that passes the same order, session, payment, currency and amount checks.

## Release artifact

The production plugin is
`wordpress/wp-content/plugins/bactive-paymongo-hosted-checkout`. Package that
directory without repository metadata or the entire `tests/` directory. The
CLI test harness is not a web endpoint and must never be uploaded into a public
WordPress installation. Record the zip SHA-256 in GitHub issue #2 before upload.
Use the identical tested artifact on staging and production.

Requirements:

- WordPress 6.8 or newer
- WooCommerce with PHP currency
- PHP 8.1 or newer with OpenSSL
- Classic WooCommerce checkout; Checkout Blocks are declared incompatible
- HTTPS with cart, checkout, order-pay, order-received, and every `wc-api`
  callback excluded from LiteSpeed, host/CDN, and Cloudflare page caching

## Verified administrative access

Production WordPress REST authentication was verified on 2026-09-05 using
the canonical project's ignored `.env`, not the isolated checkout's
`.env.example`. Use `WORDPRESS_URL`, `WORDPRESS_USERNAME`, and
`WORDPRESS_APPLICATION_PASSWORD`; never copy the password into a command,
URL, issue, chat, screenshot, or release artifact. Keep HTTPS certificate
verification enabled and reject redirects when sending authentication.

The read-only preflight is `GET /wp-json/wp/v2/users/me?context=edit` with
HTTP Basic authentication. Check the exact site/account and the
`install_plugins`, `activate_plugins`, and `manage_options` capabilities.
The production dashboard login is `https://bactiveph.com/login/`. An
application password authenticates REST calls; it is not a dashboard
password.

Do not mistake working REST access for a deployment capability: WordPress's
native plugin installer accepts WordPress.org slugs, not custom ZIP uploads,
and the currently installed backup plugin exposes no REST backup route.
Use an authenticated dashboard or an explicitly permitted hosting route
for the fresh backup and exact custom artifact. Do not add public PHP
helpers, weaken security controls, or tunnel around denied network routes.

For GitHub operations, select the already-saved `john8barry` account for
this repository in the command's environment. Verify its exact repository
and push permission first. Do not change the shared global active account:
other projects use `johnbarry-tpg`. Both branch remote readback and the
successful CI matrix remain mandatory before deployment.

## Secret handling

Never store `sk_*` or `whsk_*` values in Git, shell history, screenshots, issue
comments, logs, frontend JavaScript, or deployment artifacts. Enter keys only
in the authenticated WooCommerce payment settings page, or define the following
server-side constants in protected WordPress configuration:

- `BACTIVE_PAYMONGO_TEST_SECRET_KEY`
- `BACTIVE_PAYMONGO_LIVE_SECRET_KEY`
- `BACTIVE_PAYMONGO_TEST_WEBHOOK_SECRET`
- `BACTIVE_PAYMONGO_LIVE_WEBHOOK_SECRET`

The obsolete `cpanel_test_auth.py` credential-bearing upload/login probe was
removed under issue #9. Do not restore or run historical copies. On 2026-09-05,
an authenticated in-process WordPress hash comparison independently confirmed
that its known historical dashboard password no longer matched the affected
account on either production or staging. No login was attempted and no
password/session was changed. This is evidence of prior invalidation, not
proof that the account was never misused. Keep administrative/session review
and backup-restoration safeguards in the incident record; never restore the
exposed credential from an older database backup.

Values saved through WooCommerce are encrypted with AES-256-GCM using the site's
WordPress authentication salts. A database copied to a site with different
salts cannot decrypt them and the gateway fails closed.

Create, update, and delete the gateway settings only through WooCommerce's
normal settings flow. The plugin rejects malformed updates and unguarded direct
option creation before storage. Deletion is held behind the same serialized
drain and is blocked before SQL while any tracked or unresolved payment state
remains, so database-held credentials cannot disappear before recovery.

Signing-secret options accept only the exact encrypted value armed by verified
webhook provisioning. Direct option adds, updates, and deletes are rejected
before storage. A signing-secret replacement requires a settings lease and an
empty scan of orders and external incident records. The lease is renewed during
long drains and retained until settings or plugin deactivation is actually
written and read back.

Treat server-side constants as immutable for the entire lifetime of every
session created with them. A direct edit to `wp-config.php`, an environment
variable, or a secret mount bypasses the plugin's serialized settings drain;
never replace an API key or webhook secret in place while checkout is public or
any session is outstanding. For a rotation, first make checkout private, use
the normal WooCommerce setting to disable/drain new PayMongo issuance while the
old constants are still loaded, and independently prove zero `request_pending`
attempts, outstanding sessions, settlement markers, review incidents, and
legacy ambiguity. Then rotate the exact provider credential and server constant
atomically, restart the PHP workers, force readiness verification, and read back
the API-key fingerprint, exact webhook ID/URL/events/mode, signing-secret
binding, and one correctly signed delivery before reopening checkout. If any
step is ambiguous, keep the gateway draining and restore the old credential;
never retry an uncertain webhook creation or credential mutation blindly.

## Pre-deployment gates

1. Confirm the production and staging URLs, WordPress/WooCommerce/PHP versions,
   classic checkout, current payment gateways, current deployed plugin version,
   and current PayMongo live/test webhook inventory.
2. Take and read back a fresh database backup and a `wp-content` backup.
3. Run `php tests/run.php` under PHP 8.1, 8.2, and 8.3; lint every PHP file.
   Also run `python3 tests/run-datastore-integration.py` from the plugin directory
   after pulling the two digest-pinned images listed in the payment CI workflow.
   This uses a disposable local database with synthetic orders, no published
   ports, and an internal Docker network. It verifies first settings save,
   every recovery marker, single-join HPOS discovery, CPT translation,
   pagination, database-error handling, and independent per-order recovery
   jobs in the actual Action Scheduler database store. The disposable runner
   completes its own queue-store migration before testing; do not run that
   fixture or its migration against either real site. Its provider responses are fixtures;
   it does not replace the actual sandbox transaction matrix below.
4. Scan the complete diff and package for secrets, unexpected binaries,
   generated artifacts, and dependencies. This plugin has no third-party runtime
   dependencies.
5. Confirm PayMongo displays all five approved live capabilities as Active.
   A capabilities response identifies configured methods; it does not replace
   successful hosted authorization. Confirm each required bank through the
   actual sandbox flow and separately verify live activation. An account-denied
   response remains a launch blocker even when the identifier is listed.
6. Confirm WP-Cron is enabled or a real server cron invokes `wp-cron.php`, and
   Action Scheduler has no failed `bactive-paymongo` actions. Run one due
   reconciliation action and read back its completion before issuing a session.
   Verify that different outstanding orders each have a queued recovery job;
   one successful action or an empty failed-action count is insufficient.
   A completed payment's reconciliation marker must clear without repeating
   stock, fulfillment, email or payment effects. Page-triggered WP-Cron alone
   does not prove unattended timing on a quiet or cached site.
7. Keep **Private verification / Restrict PayMongo checkout to store managers**
   enabled (the default). Only `manage_woocommerce` or `manage_options` users
   may issue payments until the live canary passes. This restriction is checked
   at both gateway availability and the actual payment boundary; only an exact
   stored `restricted_rollout=no` opens issuance. The store and COD remain
   public. WooCommerce Coming Soon is only visual messaging, not an access
   control for direct checkout AJAX. Callbacks, cancel and recovery are exempt.

## Sandbox activation and transaction matrix

1. Upload and activate the exact plugin artifact on staging. The replacement
   hides all legacy PayMongo gateways from new checkout traffic.
2. Record the legacy plugin version/settings and inventory every Checkout
   Session or order it issued. Keep
   `wc-paymongo-payment-gateway/payments-paymongo-woocommerce.php` active and
   its callback reachable while any legacy session can still settle. Deactivate
   it only after authenticated provider readback proves zero outstanding or
   ambiguous legacy sessions.
3. Open WooCommerce > Settings > Payments > PayMongo Hosted Checkout.
4. Keep Private verification enabled, enable the gateway, keep Sandbox mode enabled,
   enter the test secret key,
   and save. Saving performs an authenticated capability check and provisions
   one test webhook for:
   `https://staging.bactiveph.com/?wc-api=bactive_paymongo_test`.
5. Independently read back registered gateways and populated checkouts. A
   signed-in store manager may see `bactive_paymongo` and eligible `cod`; a
   guest or ordinary customer must see eligible `cod` only. A forged/stale
   PayMongo submission must issue no session. `bacs` and every legacy PayMongo
   ID must be absent. Do not change production fulfilment/email integrations
   for sandbox orders: sandbox still emits ordinary Woo paid/stock/mail hooks.
6. Use a populated classic cart and place an independent sandbox order through
   each method. Also execute at least one explicit failure and abandonment path.
   Confirm exact products, coupon, shipping, tax and total; one stock reduction;
   one Woo transaction ID; the expected private order note; and no secrets or
   customer data in WooCommerce logs.
7. Test Cancel/back and COD using a populated classic cart in the same
   authenticated browser session. Record the original order and any replacement
   WooCommerce order created after cart changes. Read the previous session back
   through PayMongo: it must be safely expired before COD proceeds, with no
   competing payable session. Check the recalculated COD fee and eligibility.
   Test unchanged-cart reuse and changed-cart replacement separately. A
   backend-issued order's redirect to checkout does not establish browser
   cart/session continuity. Verify the expired-original late-payment review
   path in deterministic fixtures: retain the payment correlation without
   another paid transition, stock reduction, fulfillment or email effect.
   Record fixture evidence separately from actual browser and authenticated
   provider evidence. A reconstructed signed HTTP probe against an active
   unpaid session proves refusal and no payment effects; it does not prove
   expired-original review or a genuine paid event.
8. Exercise races and recovery: delayed paid webhook before and after Cancel;
   browser Back with cart changes and the previous `order_awaiting_payment`;
   replay of an old Cancel URL while a newer session exists; order-pay bypass;
   settings disable/mode/key changes through the normal form and Woo AJAX/REST;
   order trash/delete; failure immediately after `payment_complete()`; missed
   webhook recovery; a 51-order rotating scan; and 23-hour abandoned-session
   expiry/backoff. Use clock/provider fixtures for the 23-hour boundary,
   independent expiry readback and bounded retry delays; record actual worker
   liveness separately. A second charge or loss of payment correlation is a stop.
9. Verify the COD boundary with product totals on both sides of the configured
   cap and with coupon, shipping, and tax changes. Exactly one `COD Fee` must be
   present only when COD is eligible.
10. Capture authenticated successful BPI and UBP payment-source fields before
    declaring bank mapping accepted. Current code recognizes `bpi`/`ubp`
    provider or bank codes; bare `dob` is rejected, while `dob_ubp` identifies
    UBP. It does not learn mappings or activate bank capabilities. Unexpected
    payloads require a reviewed implementation change and regression coverage.

PayMongo's sandbox controls:

- Maya and ShopeePay: use the PayMongo test redirect and select Authorize for
  success or Fail for a negative test.
- QRPh: do not scan or pay the displayed QR code. Use PayMongo's `test_url`
  simulation control; test QR codes can otherwise represent a real payment.
- BPI: choose account `***0001`; OTP `123456` succeeds, `654321` is invalid,
  and `000000` is expired.
- UBP Direct Debit: OTP `111111` succeeds; `222222` through `666666` exercise failure
  cases.

For every success, validate the signed webhook and order independently in both
PayMongo and WooCommerce. A redirect or thank-you page alone is not evidence.

## Settlement and review safety model

- Test, live, and local operational records have separate identities. Provider
  idempotency keys, event claims, payment effects, quarantine records, review
  intents, webhook-secret bindings, and cancel authorization include their
  mode. A duplicate attempt identity or an ID with a missing mode is held for
  review; a later delivery cannot fill in its missing identity.
- An order has one active review tuple. Additional operational incidents are
  recorded first in a durable per-order inbox outside the order. Reconciliation
  discovers these inboxes even if order metadata failed to save, promotes the
  next incident after the current review closes, and never emits fulfillment
  hooks during promotion. Completed review decisions retain immutable receipts
  when a later decision reuses that order's active resolution pointer.
- Admin review visibility comes from durable order and incident records. A
  global numeric counter does not authorize any recovery action. Settings
  drains can clear only their own exact diagnostic; signed-event failures stay
  latched until the matching recovery is independently verified.
- Settlement is two-phase. First, the exact event/session/payment/method facts
  and recovery marker are saved and read back while the order is still unpaid.
  Second, an at-most-once effect record is armed, the paid transaction/status is
  saved with WooCommerce status hooks suppressed, and that paid state is read
  back before any stock, email, status, or payment-complete hook is emitted.
- The armed WooCommerce status-action sequence is emitted directly under that
  durable record. If any extension hook starts and then fails, the record stays
  `processing`. Automatic webhook and scheduler retries must never emit that
  sequence again because the earlier attempt may already have changed stock,
  sent mail, or invoked fulfillment.
- Before using `Resolve PayMongo effects ambiguity`, independently verify the
  exact PayMongo payment by authenticated GET and inspect the order, stock,
  customer email, fulfillment, and downstream integrations. The action performs
  another exact provider readback and closes the record without replaying any
  WooCommerce effect.
- Quarantine and operational cancel holds use the same at-most-once boundary.
  A failed on-hold hook remains `processing` and is never retried automatically.
  Before using `Resolve PayMongo reconciliation review`, first make every
  associated Checkout Session terminal by authenticated provider evidence, then
  verify the exact quarantine record and downstream business effects. This
  action also acknowledges without replay.
- Resolving a review never marks an unpaid order paid. For an exact provider-
  paid quarantine, successful resolution exposes a separate `Record verified
  PayMongo payment (no effects)` action. Before selecting it, the operator must
  independently verify or perform the correct stock, customer email,
  fulfillment, and downstream integration effects. The action performs a fresh
  mode-correct PayMongo GET, revalidates the unchanged order/attempt/quarantine
  fingerprint, and records the exact transaction, paid timestamp, gateway, and
  paid status without emitting any WooCommerce payment or status hooks. If the
  action is absent or fails, do not force-edit the payment fields; investigate
  the reconciliation diagnostic and retained evidence.
- That second action first writes an immutable payment-ID-keyed intent outside
  the order, advances it from `armed` to `processing`, and marks it `done` only
  after exact order readback. This is required because classic order storage
  (CPT) writes custom metadata before core order fields while HPOS writes them
  in the opposite order. A crash can therefore leave either half old. The
  action remains visible for either exact torn layout; every retry repeats the
  mode-correct PayMongo GET and only converges storage. It never emits the
  suppressed stock, email, fulfillment, payment, or status effects.
- A resolved paid disposition remains active for drain purposes from review
  resolution until both the order and its external intent are complete. During
  that interval, gateway disable/deactivation and API-key or test/live-mode
  rotation must remain blocked so the exact credential needed for provider
  verification cannot disappear. A reconciliation pass may add its queue
  marker without invalidating the disposition.
- Provider payment IDs, event IDs, session IDs, attempts, and resolved evidence
  remain durable audit history. A clean drain excludes coherent settled history
  from the active queue; it never deletes that history. An attempt-only or torn
  state remains active and blocks cutover/deactivation.

## Production cutover

1. Serialize with every other production writer. Reconfirm fresh, restorable
   database and `wp-content` backups, exact deployed files, and rollback owner.
2. Keep PayMongo's Private verification enabled. Upload the exact
   sandbox-tested artifact to production and activate it with the new gateway
   disabled/draining. Keep the legacy plugin and callbacks active, but verify
   its gateway IDs are hidden from all new checkouts.
3. In PayMongo live mode, reconfirm QRPh, Maya, ShopeePay, BPI Direct Debit, and
   UBP Direct Debit are Active. Do not infer capabilities from sandbox.
4. At the live-write control point, enter the live secret key, switch Sandbox
   mode off, enable the gateway, and save. Saving may create an external PayMongo
   webhook and therefore requires the named operator's current confirmation.
   The live webhook must be exactly:
   `https://bactiveph.com/?wc-api=bactive_paymongo_live`, enabled, and subscribed
   only to `checkout_session.payment.paid`.
5. Update and read back the rendered FAQ, checkout reassurance, Terms, Privacy,
   and footer. They must list only the five approved PayMongo methods plus COD;
   remove GCash, cards, GrabPay, manual bank transfer, and HitPay claims. Show
   PayMongo as processor branding, not as a sixth customer payment rail.
6. Independently read back both registered settings and populated manager and
   guest checkouts. The manager may see `bactive_paymongo` and eligible `cod`;
   the guest must see eligible `cod` only while verification is restricted;
   `bacs`, `paymongo`, `paymongo_hcp`, and every other legacy PayMongo gateway
   must be absent.
7. Purge the relevant LiteSpeed/host/CDN/Cloudflare caches. Prove two distinct
   callback probes are never cached and that cart, checkout, order-pay, and
   order-received remain uncached. Confirm the webhook gets an origin response,
   not a challenge, redirect, or cached HTML page.
8. With PayMongo issuance still manager-only, confirm production checkout creates a pending order and redirects only to an
   HTTPS `checkout.paymongo.com` URL. Stop before authorizing real money until
   the named operator approves the exact canary amount and order.
9. John authorizes one small real order for each of the five approved methods,
   after seeing each exact order and total. Independently verify every PayMongo
   payment ID/method/amount/status, callback delivery, WooCommerce transaction
   ID/status/order note, stock change, customer confirmation and merchant
   notification. Verify an eligible provider-side refund using the procedure
   below, and confirm no new critical PHP/Woo/Cloudflare errors.
10. Read back all legacy-issued sessions. Only after every one is paid,
    authenticated-expired, or explicitly reconciled may the legacy plugin be
    deactivated. Keep both new mode-specific webhooks active.
11. After all five live methods, the full checkout matrix, email delivery,
    refund verification and the exact containment hold in issue #9 pass, and
    no unexplained payment remains outstanding, disable Private verification through the ordinary
    WooCommerce settings flow (REST: `settings.restricted_rollout="no"`). This
    runs the serialized drain and invalidates stale issuance. Verify a fresh anonymous populated
    checkout exposes `bactive_paymongo` and eligible `cod` only. Align checkout,
    FAQ, policy and footer claims with the methods actually verified. Monitor
    payment failures, callback delivery, recovery backlog and critical errors
    for the first 30 minutes, then assign a next-day reconciliation follow-up.
    Record the deployed plugin hash and sanitized live evidence in issue #2.

Do not mark the GitHub issue complete until all acceptance criteria are proven.

## Monitoring and reconciliation

- PayMongo retries unsuccessful webhook deliveries, but disabled endpoints do
  not replay missed events. Check webhook status and recent delivery results
  during cutover and after any outage.
- Treat any quarantined event/admin notice as a fulfillment stop. Compare the
  PayMongo payment and Checkout Session with the WooCommerce order before a
  human changes order state.
- Never ask a customer to pay again while an earlier session is ambiguous.
- A failed Payment does not by itself resolve a processing Payment Intent.
  Keep the exact order/session outstanding and inspect authenticated provider
  state. An expiry rejection is not cancellation. Never clear recovery markers,
  switch to COD, replace credentials or issue another payment merely to bypass
  that state.
- Verify Action Scheduler and WP-Cron continue running. The five-minute source
  scan and per-order retry queue are the recovery path for missed webhooks.
- Resolve the WooCommerce `PayMongo reconciliation review` action only after an
  operator has compared provider, session, Woo order, fulfillment, and refund
  facts. The action acknowledges review; it does not change payment or status.
- A title or description edit never reopens a gate that was already closed.
  After resolving every incident and confirming terminal sessions, explicitly
  disable and then enable the gateway through its settings to run the complete
  verified drain and readiness flow. A temporary `verifying:` value is closed
  and belongs to one settings writer; a newer incident invalidates it.
- `unrecorded-incident` means an incident could not be durably recorded. It is
  deliberately closed even after ordinary settings changes. Preserve the
  provider/order evidence and repair the exact missing incident under independent
  review before attempting recovery; never replace this value manually with `no`.
- If that resolved review exposes `Record verified PayMongo payment (no
  effects)`, use it only after the same operator has verified or manually
  handled stock, mail, fulfillment, and downstream effects. Its durable audit
  metadata and fresh provider readback are the only supported route from that
  resolved paid quarantine to a paid WooCommerce order.
- Treat an effects record in `processing` as an at-most-once ambiguity, not a
  retry request. Never manually redeliver its WooCommerce hooks. Verify each
  downstream effect and use only the exact order action described above.
- All WooCommerce refund-record creation is blocked for PayMongo orders in
  version 1.0.0, before a refund child, stock change, provider call, or parent
  status change can occur. Confirm method-specific refund rules, issue and
  verify an eligible refund in PayMongo under a two-person operational check,
  then add a **private order note** containing the exact provider refund ID,
  amount, method, operator, and timestamp. Do not use WooCommerce's refund
  buttons or change the paid order status to Refunded in this release.

## Rollback

1. Hide/disable PayMongo Hosted Checkout in WooCommerce; leave COD enabled, but
   keep this plugin, its recovery scheduler, and both webhooks active.
2. Confirm manual bank transfer remains disabled in WooCommerce, then verify
   public checkout immediately shows COD only and no legacy gateways.
3. Retrieve and reconcile every Checkout Session/payment created during the
   cutover. Expire only sessions whose authenticated readback proves unpaid.
   Do not retry an ambiguous provider write and do not ask the customer to pay
   again.
4. Require zero outstanding attempts, `request_pending` markers, settlement
   markers, unresolved/review items, pending incident inboxes, unresolved external
   ledgers, and legacy sessions before deactivating
   either payment plugin. Do not delete payment/order audit metadata.
5. Restore the previous plugin/theme artifact only if code rollback is needed;
   restore the database only for confirmed data corruption.
6. Disable/delete the matching PayMongo webhook only after the plugin is safely
   inactive and all sessions and possible late deliveries are reconciled. The
   webhook is the final rollback step, never the first.
