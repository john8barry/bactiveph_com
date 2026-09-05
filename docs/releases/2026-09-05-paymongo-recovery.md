# PayMongo production recovery

Work record: [#2](https://github.com/john8barry/bactiveph_com/issues/2),
[PR #4](https://github.com/john8barry/bactiveph_com/pull/4).
Owner: Fix PayMongo production payments, task
`01a070a0-166f-7ea1-8558-94b88f35a074`.

John explicitly authorized implementation of the complete recovery plan.
The intended outcome is production checkout accepting QRPh, Maya, ShopeePay,
BPI Direct Debit, and UBP Direct Debit, with existing COD policy retained.
John performs the exact-total live payment tests. This document is a progress
record, not a claim that payment activation or live acceptance is complete.

## Verified starting failure

Authenticated server inspection found no PayMongo plugin on production,
no new-gateway settings or protected runtime constants, and disabled legacy
gateway settings. Staging retained the old Cynder gateway only. The test API
returned all five approved capability identifiers and one disabled legacy
staging webhook. Production uses classic checkout and PHP currency.

Source tests had passed, but the new gateway had never been installed or
configured. The canonical workspace was dirty and behind remote; its contents
are preserved. The isolated payment checkout incorporates remote main
`f5f6c043a96b35df331a7c004f59fd0da29bc625`, including the accepted footer release.
Both active design tasks initially confirmed they had no server writer scope.
The subsequently authorized homepage staging change receives a separate,
serialized writer window before the payment repair is installed.

## Completed containment

The four exact helpers covered by [#9](https://github.com/john8barry/bactiveph_com/issues/9)
were moved through trusted SFTP to an owner-only directory outside the public
webroot. No helper was executed. Original hashes were checked immediately
before each move; private server and off-server copies remain recoverable.

Independent verification at 2026-09-05 09:21:04 UTC confirmed all source paths
absent, all four hashes/sizes correct, private directory mode 0700 and file
modes 0600. All four former public routes returned 404; home, shop, cart, and
login returned 200. The production root error log was unchanged from before
containment. This clears that specific live-key installation gate; it does not
close the wider historical security investigation.

## Validation and release status

- Initial reconciled source: PHP 8.1, 8.2, and 8.3 each passed 1,064 contract
  checks, with all 13 plugin/test PHP files passing PHP 8.2 lint. Repair
  validation below supersedes these counts.
- Runtime package contains exactly 11 files, excluding tests and credentials;
  current secret-value and credential-pattern scans pass.
- Fresh production and staging backups are separately verified off-server.
  No staging database will be imported into production.
- Sandbox acceptance is partial, as recorded below. Live credentials and
  capabilities, all five real payments, inbox receipt, refund verification,
  public issuance and production monitoring remain pending.

## Staging integration finding

The initial 11-file artifact (SHA-256
`5fa083451fa096091e0b8d4853d763a6c18d44e9416a34f2d5242e7538179ca4`)
was installed on staging after a fresh six-component, 122,400,017-byte backup
passed off-server checksums and archive integrity. Runtime hashes, manager-only
defaults, recovery scheduling, and preserved configuration/theme files were
independently read back.

The first normal WooCommerce settings save exposed a High-severity launch
blocker before settings storage or provider provisioning: recovery discovery
generated 17 HPOS metadata joins. Authenticated database inspection found that
query still running after 331 seconds; a scheduled scan repeated it. The exact
stalled settings process and two identified staging query connections were
stopped. No new webhook or Checkout Session was issued; production payment
settings were not changed.

The repair preserves all 17 discovery keys in one key-IN/EXISTS clause. It also
translates the scan through WooCommerce's supported CPT query hook because that
datastore ignores the original top-level meta_query. Database errors now fail
the recovery scan instead of being interpreted as an empty queue. Existing
incident discovery, pagination, payment locks, signed validation, and controlled
drains remain intact. Independent review approved the query repair; a disposable
real WordPress/WooCommerce regression checks both datastores before promotion.

The initial artifact is superseded and must not be deployed to production.
Local regression evidence for the repair:

- PHP 8.1, 8.2, and 8.3 each pass 1,075 contract checks. All 14 PHP files pass
  lint; the payment workflow passes actionlint.
- Disposable real WordPress/WooCommerce/MariaDB: HPOS passes 62 checks, first
  settings save 0.077 seconds, maximum one metadata join; CPT passes 35 checks,
  first save 0.069 seconds. Both discover the exact 70 payment candidates over
  two pages, preserve every marker, and reject a real database query failure.
  The runner names and removes all disposable containers, sanitizes timeouts,
  and reports overall success only after independent resource cleanup.
- Both environment backups are now verified off-server: staging 122,400,017
  bytes and production 329,690,615 bytes, six components each. Every checksum
  and gzip/ZIP integrity check passed. Backups contain private data and are
  excluded from the repository.

## Repaired staging deployment

Runtime commit `76a2036579dcc9604840b2a4364ff3b79df8950a` passed all four jobs in
[CI run 33960248974](https://github.com/john8barry/bactiveph_com/actions/runs/33960248974).
The repaired 88,924-byte artifact has SHA-256
`6264ac421f7450264c9dee6515b8252c6ee61c6ac8b191a25eeb09d5fa243fbe`.
All 11 installed staging runtime files and seven protected files were
independently verified after replacement; no maintenance file remained.

The normal WooCommerce settings handler then saved successfully. Test issuance
is enabled for managers and unavailable to guests. Independent provider readback
verified enabled test webhook `hook_vCuwVTbA8bniSsjqu8LUtL5z`, subscribed only to
`checkout_session.payment.paid` at
`https://staging.bactiveph.com/?wc-api=bactive_paymongo_test`. A bounded recovery
run completed in 0.0925 seconds without a scan failure or failed scheduled action.
GET requests receive 405; missing, incorrect and wrong-mode signatures receive
401. Callback responses are private/no-store, reach the origin and receive no
authentication challenge.

A separate staging policy mismatch made the existing PHP 50 COD fee taxable.
Only that fee's taxable flag was changed to match the existing production and
source policy. The prior file is recoverable privately; independent readback
verified staging `functions.php` SHA-256
`50714a89863d97af81e2ffa573b6dfbde8685de686e97bf7f677c9f56410a2dd`.
This deliberate change supersedes that one protected-file baseline. Production
shipping, tax and COD policy were not changed.

## Actual sandbox results

| Method/case | Staging order | Total | Verified outcome |
| --- | --- | --- | --- |
| Maya success | 371 | PHP 975 | Provider paid; matching Woo transaction; Processing; effects complete |
| QRPh success | 377 | PHP 640 | Provider paid; matching Woo transaction; Processing; fixture stock 20 to 19 |
| ShopeePay success | 378 | PHP 640 | Provider paid; matching Woo transaction; Processing; fixture stock 19 to 18 |
| BPI authorization | 375 | PHP 640 | Provider rejected the account before payment initiation |
| UBP authorization | 376 | PHP 640 | Provider rejected the account before payment initiation |
| ShopeePay decline | 374 | PHP 640 | Failed Payment; Payment Intent still processing; Woo remains pending |

These were backend-issued manager test orders completed using the actual hosted
provider screens. They establish hosted settlement and payment correlation;
they do not substitute for a normal signed-in manager browser checkout.
Maya's product did not manage stock, so its stock flag is not a numerical stock
test. QRPh and ShopeePay each reduced the managed staging fixture by one.
All three paid orders have matching provider payment IDs, genuine provider event
identities, completed effects and no pending settlement or review markers.
The canonical Apache log contains successful callback POSTs at 10:56:38,
11:14:00 and 11:22:01 UTC, aligned with the three orders' stored effects times.
The access log has no event identifier, so per-order attribution combines
timing with the stored provider event and processed claims. At 11:39 UTC there
were zero failed recovery jobs; orders 377 and 378 still had reconciliation
queue markers, so this does not establish a fully drained recovery queue.

QRPh used the official sandbox simulation control after the original customer
QR checkout tab was closed. A reconstructed signed duplicate, using the exact
stored provider event identity and authenticated payment facts, returned HTTP
200 `duplicate`; transaction, status, paid time, event, stock, note IDs and
effects hash stayed unchanged. This was not dashboard webhook redelivery.

The configured-method API lists all five identifiers, but hosted BPI and UBP
authorization returned respectively `Dob payment method on bpi is not allowed
for your account` and the equivalent `ubp` message. No successful bank-source
payload exists yet. Account enablement or provider clarification and successful
bank transactions are required; the identifier list is not proof of usable
bank authorization. BPI's signed Cancel/back action was independently verified
to expire its session while leaving the Woo order unpaid and stock untouched.
That backend-order cancellation is not full PayMongo-to-COD browser acceptance.

For ShopeePay decline order 374, a single checkpointed test expiry request
returned HTTP 400 `resource_invalid_state`. Fresh readback at 11:36 UTC still
showed an active session, processing intent, failed Payment, pending unpaid
Woo order and no stock effect. The attempt remains unexpired and available to
recovery. No replacement session was issued for that order. This did not
reproduce the conditional concern about expiry of an in-flight intent; it is
an unresolved provider test state, not an asserted runtime defect.

Independent pure-runtime validation of the real QRPh payment accepted its
matching facts and rejected wrong amount, currency, order metadata, event mode,
session mode and session identity. Stored order, stock and effects stayed
unchanged. These checks do not claim full HTTP quarantine/failure-path acceptance.

## Checkout and operational gates still open

Anonymous desktop checkout showed the correct Davao total of PHP 690 for the
PHP 560 fixture, PHP 80 delivery and one PHP 50 COD fee. Changing the destination
to Metro Manila displayed PHP 790 with PHP 180 delivery and the same single fee.
A product-restricted PHP 100 staging coupon reduced that total to PHP 690 and
included VAT from PHP 60 to PHP 49.29. No production coupon was created.

Mobile testing exposed two additional checkout defects, now fixed on staging:

- Blocksy renders mobile and desktop quantity inputs with the same form name.
  Keyboard entry into the mobile control left the later hidden desktop control
  unchanged, and Update cart returned quantity one instead of five. The child
  script now synchronizes only exact-name peers in the same row and form before
  WooCommerce serializes them. Capture listeners survive AJAX replacement and
  preserve native quantity/stock validation. Parent theme files are untouched.
- A stale COD selection kept a PHP 50 fee in the cart above the existing cap
  until checkout removed it. The fee now uses the same discounted, tax-exclusive
  product-net limit as gateway availability: PHP 2,500 inclusive. Shipping and
  tax do not change that existing eligibility rule.

Independent review approved child `custom.js` SHA-256
`d503b720a567fda83fbf8f72b89de3efa82c8b431483fa75f048030e4c3984f1`
and the single-condition change to `functions.php`. Node syntax, PHP 8.2 lint,
six actual theme-function boundary cases (including PHP 2,500.01) and the
interface detector pass. Staging browser verification passed mobile keyboard
quantity one to five, another update to six after AJAX replacement, desktop
six to one, and refresh persistence. At width 390, checkout's scroll width is
390 and its submit button remains within the viewport.

With the fee guard installed, six PHP 560 items (PHP 3,000 product net) plus
PHP 80 Davao delivery immediately total PHP 3,440, without a COD fee. Returning
to five items (PHP 2,500 product net, PHP 2,800 displayed product subtotal) gives
PHP 2,930 with delivery and exactly one PHP 50 fee. The independent staging
PHP readback is SHA-256
`5c0f7e0c6dcaf9759b8044ab60d1abf499bb48c731bf5f15dfa56c2100fabf95`;
this supersedes the earlier fee-tax correction's hash. Both exact prior files
have private server and off-server recovery copies. These theme fixes do not
change the gateway package or complete payment acceptance.

Staging customer and merchant notification routing was aligned to the store's
public inbox, with exact prior option values retained privately. This is routing
evidence only. SMTP2GO activation and inbox delivery remain unverified and belong
to the coordinated email task; ordinary host mail can still be attempted while
the SMTP2GO plugin is disabled.

Remaining launch gates include normal manager browser checkout and PayMongo/COD
switching, complete failure/race/recovery acceptance, both bank sandbox flows
and source mapping, merchant login and live capabilities/key, actual inbox
receipt, all five John-authorized real payments and eligible provider refund.
Legacy callback/runtime remains installed while historical test intents are
not proven terminal. Production has not received the new gateway or live key.
Public issuance, customer payment claims, 30-minute monitoring and the next-day
reconciliation follow-up come only after these gates pass.

The next control point is merchant account access to resolve the two bank
denials, normal staging manager sign-in, and email transport approval. Continue
the existing isolated payment branch and preserve the canonical dirty workspace.

## Recovery controls

New issuance remains restricted to store managers during verification. Keep
callbacks and scheduled reconciliation active while any payment is unresolved.
Rollback stops new PayMongo issuance and preserves eligible COD, then reconciles
outstanding sessions before removing runtime or credentials. Never restore an
older database over new orders or restore the quarantined unsafe helpers as
part of an ordinary payment rollback.

Use the [payment runbook](../paymongo-production-runbook.md) for the transaction
matrix, protected configuration, manual provider-side refund procedure, and
exact acceptance gates. Each live method needs provider/order/stock/email
evidence; a browser return, source test, merge, or successful upload is
insufficient.
