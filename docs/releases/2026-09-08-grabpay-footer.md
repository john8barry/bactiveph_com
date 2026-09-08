# GrabPay footer badge

Work item: [#53](https://github.com/john8barry/bactiveph_com/issues/53).
Severity: low, display-only. Owner: courier/payment footer task.
Status: **LOCAL VERIFIED; STAGING PREPARATION IN PROGRESS; NOT PRODUCTION**.

## Scope

Add GrabPay after Maya in the existing payment-options footer. Preserve QR Ph,
ShopeePay, BPI Online, UnionBank Online, enabled-only COD, separate PayMongo
attribution, J&T/LBC nationwide and GrabExpress with its Davao City-only label.
The existing responsive layout, typography, badge sizing and aspect-ratio
handling are unchanged. No payment, checkout, shipping, order, plugin or
configuration change is included. This logo does not activate GrabPay checkout;
runtime payment readiness remains a separate task in #2.

The Impeccable polish review preserved the incumbent visual system and original
brand artwork. Its context discovery stalled on an unrelated canonical-checkout
file; direct target source and existing design notes were used instead. The
design notes contain historical typography; current source typography was kept.

## Asset provenance and exact overlay

Use the original [PayMongo GrabPay SVG](https://www.paymongo.com/images/logos/payment-methods/grabpay.svg),
retrieved and independently verified on 2026-09-08. It is the same 121 by 49
pill-shaped family used by the other payment marks, with original green and
white artwork. Its 5,468 bytes are unchanged. No scripts, event handlers,
external references, embedded images or foreign objects are present.

Only these two child-theme files are deployable; both source mirrors agree:

| File | SHA-256 |
| --- | --- |
| `template-parts/trust-bar.php` | `146cb5146676917b5c80aaffac2b215121aa2f99d8d2c2a3a8c3fa05eb31fab5` |
| `assets/images/payments/grabpay.svg` | `d140bd7323719935e2e05c102553ac3283e5ccb3ee22739673b3b3164d3e6e23` |

Render marker: `2026-09-08-v5`. Tests, release notes and private helpers are not
deployed. Source base: remote main
`5dcfbdece8067850b9665f6b5d33dd0557bf0212`, branch `codex/grabpay-footer`.
The canonical dirty checkout remains untouched; its filesystem reads stall and
its full current status is unavailable. This change uses an isolated clone.

## Verification

PASS: native PHP 8.3.33 syntax checks; seven runtime scenarios (ready,
not-ready, missing gateway, gateway error, manager error, all disabled and no
commerce); exact payment order/count and accessible local GrabPay image;
enabled-only COD with fail-closed behavior; both theme mirrors; passive SVG
inspection; `git diff --check`; Impeccable detector `[]`; independent exact-file
source/security/brand review.

PASS: seven negative fixtures reject missing GrabPay, wrong asset, missing alt,
distorted dimensions, COD fail-open, missing Davao-only label and stale version.
Local component browser screenshots at desktop and 390px phone width show the
matching badge. At 390px all seven payment images load at 121 by 49 with
`object-fit: contain`, and no horizontal overflow. These are component previews,
not staging or production verification.

Staging authenticated identity at 09:08:30 UTC: exact home/siteurl
`https://staging.bactiveph.com`, isolated DB `waypmvhk_stg`, `blocksy-child`,
`blog_public=0`. Original partial hash is
`1a1c06aec18da9ea80ae659eadea100b78e3a9966b08871a99482fb3302536f1`.
Original staging `template-parts/footer-sage.php` is
`6e03b8048bf197cbae05e1e52b92c3e92e448048106a2fd463fcdc533f35a7e4`,
which differs from production and is not in this deployment allowlist.

The first backup action-time guard stopped before backup creation: aggregate
settings and payment-cron hashes advanced, while full child-theme, order/meta/
item hashes and gateway flags stayed unchanged. A same-connection diagnostic
subsequently found stable values. Original snapshots are retained and the
payment coordinator is classifying this drift before a new baseline/backup.
No deployment has occurred. The stopped guard is not a successful backup.

Private recovery/diagnostic artifacts are held in
`/private/tmp/bactiveph-grabpay-20260908-6jE1ip/`, outside the repository. Never
publish backup contents, option values, credentials or order data.

## Remaining gates and rollback

The payment coordinator owns staging serialization. A fresh complete supported
backup, private off-server integrity verification, protected-state consistency,
exact two-file staging installation and actual staging browser verification
remain required. Production additionally requires fresh explicit human approval
for this artifact, a serialized writer window and production-specific backup,
preflight and independent readback. Existing #2, #7 and #9 holds are not cleared.

For rollback, first verify no later writer superseded this exact v5 partial,
then atomically restore the snapshotted v4 partial. Lint/hash before installation,
refresh only page cache, and verify the public footer. The unused new SVG can
remain; no deletion or database restore is necessary.

No global or project memory files were updated.
