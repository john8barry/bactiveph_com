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
are preserved. The initial isolated payment checkout incorporated remote main
`f5f6c043a96b35df331a7c004f59fd0da29bc625`, including the then-current footer release.
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
| Anonymous COD checkout | 379 | PHP 590 | Normal browser order; one fee; stock 18 to 17; no online transaction or paid timestamp |

The online probes were backend-issued manager test orders completed using the
actual hosted provider screens. They establish hosted settlement and payment
correlation;
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

Mobile testing exposed two additional checkout defects, validated on staging
and subsequently deployed as a narrow production theme update:

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

The normal anonymous staging checkout then created COD order 379 for PHP 590:
PHP 560 product, PHP 100 gross coupon discount, PHP 80 Davao delivery, one
nontaxable PHP 50 fee and PHP 49.29 included VAT. Independent WooCommerce
readback confirmed `created_via=checkout`, guest customer ID zero, Processing,
empty transaction ID, no paid timestamp, stock 18 to 17 and coupon usage zero
to one. The synthetic order is explicitly marked DO NOT FULFILL. This proves
ordinary guest COD ordering, not inbox delivery or an online payment.

## Narrow production theme release

Commit `405f925340f7588e0610e6ec9ed3b4bbb345f79a` passed all four checks in
[CI run 33965083789](https://github.com/john8barry/bactiveph_com/actions/runs/33965083789).
Only these two runtime files were deployed, using the staged and independently
reviewed changes:

| Production file | Deployed SHA-256 |
| --- | --- |
| Child theme `assets/js/custom.js` | `d503b720a567fda83fbf8f72b89de3efa82c8b431483fa75f048030e4c3984f1` |
| Child theme `functions.php` | `949a12f435e8ce6efec5b8301465f80017143e3cb410ef920eedb5d893383b56` |

Each upload checked its exact old hash immediately before atomic replacement.
Original bytes are verified off-server and in a private server recovery
directory outside the webroot. A new SFTP connection verified each new hash;
the versioned JavaScript URL returned the same bytes with HTTP 200. PHP lint
and an independent WordPress reflection check verified the fee guard and
preserved nontaxable fee. No cache-wide purge was needed.

Before/after readback confirms unchanged WordPress configuration, `.htaccess`,
child stylesheet and footer, active-plugin list, shipping/tax/COD settings,
database identity, latest production order 520 and variation 52 stock of two.
The production error log is unchanged; no debug log appeared. The new gateway
directory and settings remain absent. This is a two-file deployment from PR
#4, not deployment of the whole branch or gateway. Main at that checkpoint was
`f5f6c043a96b35df331a7c004f59fd0da29bc625`.

Anonymous Chrome verification passed: ordinary selection of The Rally Dress,
large/mocha, added variation 52 at quantity one. At width 390 both quantity
controls changed to two using keyboard input. The normal cart update POST
returned HTTP 200 and the server cart displayed PHP 4,500. Desktop reload
retained quantity two. The test item was then removed and the cart verified
empty, matching its initial state. No production order or payment was submitted.
The final narrow readback, 33.1 minutes after the baseline, still showed identical
protected files, settings, active plugins, database identity, order and stock
state, and error log. This monitoring covers the two theme fixes; public PayMongo
activation and its separate 30-minute payment monitoring have not occurred.
An earlier in-app browser attempt did not send an observed add-to-cart request;
the successful independent Chrome path resolves that verification ambiguity
without another source change.

To roll back this narrow release, first verify the two deployed hashes above,
then restore only the corresponding private pre-change files by atomic SFTP
replacement. Verify old JavaScript hash
`fa339b4ca6691e841a29744dc4fa8beebcdd3f51d9d86cf39cc7010693b39f81`
and old PHP hash
`9841e5b63cf6fd67028d306faa839000716345334390ab9ee0d2c9b16053e6ab`,
PHP lint, the versioned public asset and storefront health. This rollback does
not change payment runtime, configuration, orders, stock or the database.

## Remaining launch controls

### Recovery scheduling repair

Read-only staging inspection found that one pending job for order 374 prevented
orders 376, 377 and 378 from receiving recovery jobs. Discovery itself returned
all six tracked payment orders in 0.0029 seconds. Paid orders 377/378 had completed
effects and no unresolved condition, but their cleanup markers remained because
they had not received a recovery action.

The runtime requested `unique=true` for a shared Action Scheduler hook/group.
The deployed database store applies that uniqueness across all order arguments.
The repair keeps the existing exact-order check, creates jobs without the
cross-order uniqueness flag, and falls back to per-order WP-Cron when an enqueue
returns zero. Existing order locks and effect idempotency remain in force.

The first expanded local test exposed another evidence gap: a fresh WooCommerce
fixture was still using its transitional queue store, whose uniqueness rule
differs. The runner now completes the supported migration only in its disposable
database and asserts the actual `ActionScheduler_DBStore`. Against that store,
the old code failed when scheduling a second order. The repaired code passes
75 HPOS and 48 CPT checks, including three independent queued orders, repeated
same-order scheduling and both initial/retry zero-return fallbacks. All 1,075
contract checks pass under PHP 8.1, 8.2 and 8.3. Independent review approved
reconciler SHA-256
`a3bd0b713482e3bf234e3b28fa718bb462b5de2b70f31d73f92399020a18b63e`.
Runtime commit `cf891ca7a31d86ad19f8ac2d68dd270a96645448` passed all four jobs in
[CI run 33966994518](https://github.com/john8barry/bactiveph_com/actions/runs/33966994518).
The current 89,136-byte, 11-file artifact has SHA-256
`a759d31c5b475d14d498a879a8d561e9b7ea2952d1b7aa43b8171e131640681e`.
It supersedes the `76a2036` artifact for future production deployment.

A fresh six-component staging backup, 122,510,116 bytes, passed off-server
checksums and archive integrity before the update. Only the reconciler and
plugin readme differed; their old bytes remain privately recoverable. Independent
transport and runtime readback verified all 11 package files, eight unchanged
protected files, unchanged encrypted settings/key bindings, unchanged paid-order
transactions/notes/effects and fixture stock 17. A second independent audit at
13:02:19 UTC confirmed the same artifact, manager-only test issuance, absent
maintenance state and HTTP 401/no-store/no-challenge unsigned callback rejection.
The new gateway remains absent from production.

The existing scheduler subsequently completed recovery actions 663 and 664 for
paid orders 377 and 378 before the bounded acceptance helper began. No manual
action execution was needed. Both reconciliation markers cleared. Fresh independent transport
readback at 13:04:18 UTC confirmed unchanged transaction IDs, totals, paid times,
event IDs, note IDs, effect hashes and fixture stock. The explicit source backfill
then preserved one pending job each for unpaid orders 374 and 376, with no
failed actions for either paid order. This verifies actual queue execution and
cleanup while site traffic was present. A separate 13:08 UTC readback matched
the exact action identities and logs: actions 662, 663 and 664 completed through
WP-Cron at 13:01:55-13:01:57 UTC. It also independently rechecked paid-order
invariants and stock. Timing on a quiet site and the incoming request that
triggered cron remain unproven.

Order 374 remains a known provider test hold: authenticated readback at 13:01 UTC
still returned a failed Payment inside a processing intent and active session.
Order 376 has an active unused session and no Payment Intent or payment. Both
WooCommerce orders remain pending and unpaid, with no stock reduction. Their
recovery jobs are retained; no further expiry or replacement was attempted.

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
denials, normal staging manager sign-in, and verified email delivery. Continue
the existing isolated payment branch and preserve the canonical dirty workspace.

PayMongo's current [capability guidance](https://docs.paymongo.com/docs/account-settings-account-capabilities)
requires manual support requests for BPI and UBP, with an estimated 1-3 business
days subject to review. John should inspect Settings > Payment Methods in live
mode and confirm any existing requests before submitting another. The observed
test-mode authorization denials need explicit test/live clarification; a live
status alone does not resolve the sandbox discrepancy. A private support draft
also identifies the stuck ShopeePay test records. No support message was sent.

The current payment task owns the next-day reconciliation follow-up, due 24 hours
after independently verified public activation. Its scheduled time and the
30-minute activation monitor remain unset until that activation actually occurs.

## September 6 normal browser acceptance and coordination

This checkpoint supersedes the earlier staging access, visual release and email
status above. Production PayMongo is still absent; full payment acceptance is
not complete.

Normal staging access now works. Wordfence was disabled only on staging at
John's request. The coordinator created one temporary `shop_manager` through
supported WordPress core and signed in through the normal login page. Independent
readback confirmed all existing user/password/metadata records unchanged, no
administrator capability and no account notification attempts. After testing,
the coordinator removed its role and revoked every session through supported
WordPress APIs. Independent readback at 07:59:05 UTC verified zero capabilities
and sessions, retained user/order ownership, unchanged existing accounts,
payment/order facts and fixture stock 12. No account email was attempted. The
user remains solely to preserve the test-order audit trail.

| Normal browser case | Order | Total | Independent result |
| --- | --- | --- | --- |
| Maya payment and native email | 381 | PHP 640 | Provider paid, genuine signed callback, matching Woo transaction/Processing, stock 17→16 |
| Mobile QRPh after cancellation | 382 | PHP 740 | First session expired; two rapid checkout clicks issued one replacement; provider paid, callback/Processing, stock 16→15 |
| ShopeePay payment | 385 | PHP 740 | Provider paid, genuine signed callback, matching transaction/Processing, stock 14→13 |
| PayMongo to COD | 383→384 | PHP 740→790 | Both old online sessions expired and remained unpaid; COD Processing with exactly one PHP 50 fee, no online transaction/paid date, stock 15→14 |
| Scheduled recovery with callback delivery held | 386 | PHP 740 | Provider paid while Woo remained Pending; native action 747 completed, matching transaction/Processing and stock 13→12 |

Order 382 used the official QRPh simulation link. The checkout tab navigated away
before authorization and the simulator remained on PayMongo; genuine callback
settlement completed without a merchant return. This proves return independence,
not a literal operating-system browser shutdown. At 390px the populated form and
button fit the viewport with no horizontal overflow. Davao/Manila online totals
were PHP 640/740; Manila COD PHP 790 contained one PHP 50 fee; returning to online removed it.
A screenshot scaling artifact prevents claiming complete visual certification
from that capture alone.

During the COD journey, an early selection made during initial recalculation did
not persist. One online resubmission therefore created another unused generation,
with its predecessor independently confirmed expired. The final test waited for
the stable COD selection and PHP 790 total before submission. The gateway then closed
the old session and displayed its expected request to submit checkout again.
Only after independent provider readback did the coordinator complete COD 384.
No online charge was authorized on order 383.

Order 386 exercised actual missed-callback recovery. A reviewed temporary staging
handler accepted only that fresh order/session, valid test signature and exact
payment facts, and returned 503 before the normal callback handler. Independent
provider readback showed paid Maya while Woo remained Pending, without a
transaction, paid date or stock reduction. The original five-minute scheduled
action 747 then started through Async Request at 07:22:23 UTC and completed at
07:22:34 UTC. It recorded the matching payment and completed payment effects;
stock moved 13→12 once. No manual queue or reconciliation execution was used.

The Apache access log independently records six callback 503 responses followed
by a 200 after recovery. Later readback retained the same transaction, paid time,
four notes, effects and stock. The browser did return despite an attempted
temporary block; it displayed confirmation processing and did not settle the
order. This case proves scheduled recovery after an unpaid return, while 382
separately proves callback settlement without a merchant return. The interceptor
and its private binding were removed, binding first, at 07:30 UTC. Fresh runtime
readback confirms the normal callback hook only, identical 11-file gateway and
unchanged protected configuration and all recorded protected orders.

Order 387 tested three separately constructed, correctly signed negative HTTP
callbacks: amount increased by one centavo, currency changed to USD, and wrong
order metadata. Each one-shot request returned 503 `retry_later`. Fresh independent
Woo/provider readbacks before and after each confirmed no payment or Payment
Intent, unchanged unpaid order, two notes, no transaction/paid date/effects and
fixture stock 12. These are reconstructed rejection probes, not provider-origin
events. The native signed Cancel flow then expired the unused session; Woo
remained pending and unpaid, with no stock change. The customer saw the expected
safe-cancellation notice. No probe was repeated or payment authorized.

Both native order 381 messages were accepted exactly once through the actual
callback transition and shown Delivered in authenticated SMTP2GO Activity, with
distinct New Order and Customer Processing subjects and provider IDs. The
reviewed temporary exact-order mail adapter was removed and independently verified
absent. Durable accepted-send claims, the paid order and stock were unchanged.
[Issue #23](https://github.com/john8barry/bactiveph_com/issues/23) retains the separate
inbox-acknowledgment gate. Production SMTP2GO is enabled with API logging off;
staging SMTP2GO remains disabled. Disabled SMTP2GO does not itself suppress
ordinary host mail. John's earlier production connection-test acknowledgment
must not be reused as acknowledgment of these checkout messages.

Footer and hero releases are complete on production, independently accepted,
and their writers have returned control. Issues #14 and #13 are closed. Exact
approved files, protected payment settings/orders/stock/logs, cached delivery and
bounded monitoring were verified. The payment branch incorporates main through
main `ff9eb2a40810e3edc253fab0fbcde3561c11b5a7`, including typography PR #28,
compact hero PR #33, storefront punctuation PR #29/#36 and header PR #35. All 11 gateway runtime
files remain the identical `cf891ca` package. Canonical dirty work is preserved. CI run 34015874850 passed PHP 8.1–8.3 and real Woo datastores before the
subsequent documentation, typography and compact-hero source reconciliation.
Those deployments remain with their separately authorized writers. An eventual payment
deployment requires fresh exact snapshots and verified backup freshness.

The new punctuation workflow exposed one stale mirror: the top-level child
theme functions copy lacked the COD fee cap already present in the deployed
WordPress copy. The mirror now contains the identical tested guard. This changes
no production file or policy; both source copies must compare byte-for-byte.

The inherited active Graphify rule and provider configuration were removed in
this isolated branch under the retirement instruction. No Graphify command or
provider call was used; historical output remains excluded from Git.

Remaining work is resolution of the held decline and both bank authorization
flows and bank-source mapping, merchant login/live capability/key, actual inbox
acknowledgment, all five John-authorized live payments, eligible provider refund, anonymous activation,
accurate payment claims and 30 minutes of monitoring. The private bank/stuck-Shopee
support draft remains unsent. Do not retry held orders 374 or 376 without definitive
provider state and the controlled continuation.

## September 6 unattended recovery and blocked cart retest

The payment branch incorporated main `91a499a99667ab5d895609e0c6c5104c0ee4dd89`
at `a245a576da9a82e7b0f468c9fcd0ea3bd431d61b`. Payment CI run 34031986283 and
punctuation run 34031986334 passed all five checks. The existing-session expiry
suite now brings the PHP 8.1–8.3 runs to 1,215 checks, including the exact
23-hour boundary, independent expiry readback and pending-payment protection.
Three mutation controls failed as intended. All 11 gateway runtime files still
match the unchanged `cf891ca` artifact.

A private staging-only OS worker now runs the named PayMongo WordPress cron
hooks and the native Action Scheduler runner restricted to the PayMongo
hook/group. Existing account crontab bytes were preserved. The installed
WP-CLI 2.6 and PHP 8.2 contracts, exact private runtime hashes, native housekeeping
scope, locking and failure behavior were independently reviewed. The 22
selection/error checks and 15 native cleaner/filter checks passed on PHP 8.1–8.3.
Qualified deployment manifest SHA-256:
`31be387cf3e6f7b9e58a0b32305ec8e3aacf53887f4b304013d5d04192a55a5a`.

The first unattended cycle completed at 11:55:37 UTC. The next completed at
11:56:35 UTC; native action 773 independently records starting at 11:56:32 and
completing at 11:56:34 through WP-CLI. No manual launcher or queue execution was
used. MailPoet recorded no action in that interval. Protected payment facts and
stock 12 were unchanged. This proves unattended execution, not resolution of
the held provider payment. Production worker deployment remains pending with
the production gateway.

A fresh normal-browser cart retest found PayMongo hidden despite a valid
temporary manager login, enabled test gateway and no settings-write lock.
The stored global drain was `yes`. Order 374's unsuccessful abandonment expiry
had created `reconciliation_abandoned_expiry_failed` at 10:56:30 UTC. The
review-inbox path intentionally closes new issuance. The order remained unpaid,
without a transaction or stock effect. The drain was not overridden, and no
replacement session, new order or charge was created. Unchanged-active-cart
reuse and changed-cart replacement therefore remain unproven through this
browser retest; the earlier rapid-click/COD cases do not substitute for them.

The unused fixture was removed through the normal cart. Independent cleanup
readback at 12:39:09 UTC confirmed the existing QA user's roles and sessions
removed, unchanged account/password data and orders 381–387, stock 12 and zero
account email attempts. A browser reload confirmed an empty anonymous cart.
Order 387 had already been automatically cancelled at 08:54 UTC; its provider
session remained expired and unpaid. Wordfence remains disabled only on staging.

Header/typography and care-copy production releases now have independent
acceptance, including exact target hashes and unchanged protected commerce.
The care owner's cache invocation had an ambiguous return and was not retried;
the approved copy was independently reconciled in source and verified in normal
public responses. Production still lacks the new gateway and its settings.
Content deployment is not payment acceptance.

A nonsensitive gateway settings save can clear the drain while an unresolved
payment review remains. The installed `cf891ca` runtime is held from production
pending the focused fix and regression; no drain override was performed.

The next control point is that guard fix, merchant sign-in/MFA, definitive recovery of held
sandbox order 374 and separate BPI/UBP test/live authorization. Keep callback,
credential and recovery processing intact. The support draft remains unsent.

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


## September 6 source repair after the staging review hold

The settings review-hold and cache notification fixes are integrated as
`b50bbaa` and `0cda693`. Expiry recovery is integrated as `9a96481`: orders already
held for review retain provider GET reconciliation without repeated automatic
age-based expiry requests. A processing Payment Intent remains outstanding even
when its Payment failed or its Checkout Session reports expired. The PHP
8.1–8.3 contract matrix passes 1,415 checks on the combined source.

A separate native two-process WordPress reproduction found that `add_option`
can overwrite an active lease after a contender cached the option's absence.
This affects the order, checkout and settings lock families. The repair routes
all twenty production create-once option writes through prepared insert-only
SQL, retains exact-value stale-claim deletion, and requires a durable installation
UUID before webhook creation. Native HPOS and CPT checks passed, including
sixty helper checks and ninety separate-process contention assertions per
datastore. Testing additionally corrected scalar normalization and existing
empty-row reads. Independent review corrected exception-path cache invalidation;
the final integration and remote CI remain required before staging deployment.

These source fixes have not yet replaced the staging runtime from `cf891ca`.
Staging issuance remains closed under the unresolved provider/order review,
with the temporary QA account revoked and recovery processing retained. The
fresh staging full backup was independently verified off-server. Production
PayMongo activation, bank acceptance, the five approved live payments, refund,
intended-inbox confirmation and public checkout acceptance remain incomplete.
