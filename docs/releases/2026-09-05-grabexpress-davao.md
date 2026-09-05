# GrabExpress branding for Davao City only

Work records: [#11](https://github.com/john8barry/bactiveph_com/issues/11) and the
combined [footer-copy cleanup #10](https://github.com/john8barry/bactiveph_com/issues/10).
Severity: low, customer-facing shipping information. Owner: **Update courier and
COD options**, task `01a06d82-576d-76f3-bffb-6f9142e371a9`.

## Scope and acceptance

John requested GrabExpress in the shipping options, limited to Davao City.
J&T and LBC remain under **Ships nationwide via**. GrabExpress is in a separate
accessible group labelled **Davao City only**, so it cannot inherit a nationwide
claim. All three courier tiles remain 132 by 44 CSS pixels. The groups wrap on
narrow screens; brand artwork preserves its intrinsic aspect ratio.

The combined release includes the independently reviewed removal of the two
footer payment notes, originally commit `1d3f91a03bb779659581283c6956962221fc2166`,
integrated as `1f8952f`. It preserves QR Ph, Maya, ShopeePay, BPI Online,
UnionBank Online, enabled-only COD and separate PayMongo branding. No shipping
zone, rate, COD eligibility, payment setting, checkout method or order changes
are authorized or included. No delivery-time, fee or broader coverage claim is
added. The separate payment/security work in #2, #7 and #9 is not cleared by this
presentation update.

## Official asset and exact files

- [Grab's official transparent RGB horizontal wordmark](https://assets.grab.com/wp-content/uploads/sites/12/2020/03/16093202/GrabExpress_Final_Logo_RGB_green_horizontal-01.png),
  [media record 21499](https://www.grab.com/ph/wp-json/wp/v2/media/21499).
  PNG, 2868 by 800, 3.585:1. Original bytes, no recolouring, cropping or redrawing.
- Customer link: [GrabExpress Philippines](https://www.grab.com/ph/express/),
  with an accessible name explaining local delivery and the new tab.
- Only two files are deployable, relative to the exact child-theme directory:
  `assets/images/couriers/grabexpress.png` and `template-parts/trust-bar.php`.
  Both repository theme mirrors match. Tests and release notes are not deployed.
- Asset SHA-256: `c3415d014123795dd433ce6eff61aaf438202227a38e61bc6e518511fd2b3a8a`.
- Combined v4 partial SHA-256: `1a1c06aec18da9ea80ae659eadea100b78e3a9966b08871a99482fb3302536f1`.
- Render marker: `2026-09-05-v4`.

## Reconciliation and verification

Source base: `c8a922f8b73bb133fb01017c4c14b50b15c8f299` on remote main.
The canonical dirty checkout is untouched; this work uses the isolated branch
`codex/grabexpress-davao`.

Authenticated staging and production identity/snapshot checks passed at
2026-09-05 07:36 UTC. Both had the v2 partial SHA-256
`8bf5a8dfb2ab216c19489717cbb07860191ec289f2066b03de789650a0a47d00`.
Staging is `https://staging.bactiveph.com`, its own `waypmvhk_stg` database,
`blog_public=0`; production is `https://bactiveph.com`, `waypmvhk_bactwp`,
`blog_public=1`. Both use `blocksy-child`.

Fresh staging Updraft backup timestamp `1788593937`: PASS, all six components
(database, plugins, themes, uploads, mu-plugins and others), 121,586,482 bytes.
Every component was copied off-server with matching SHA-256/size and passed
ZIP/gzip integrity checks. No old backup was pruned. The private snapshot,
backup manifest and verification receipts are retained under
`tmp/footer-display-2026-09-04/grabexpress-davao-2026-09-05/`, outside this branch.
Backup archives contain private information and must not be published.

PASS: PHP 8.2 WASM runtime harness, seven gateway states, exact courier/payment
counts and order, COD fail-closed behavior and both removed-note assertions.
Five negative fixtures were rejected: missing local label, unsafe courier URL,
distorted intrinsic dimensions, duplicate courier image and reintroduced note.
Template and asset mirrors match; the Impeccable source detector returned `[]`;
`git diff --check` passed. Native staging PHP lint, browser visuals, destination
readback and production acceptance remain **UNVERIFIED** until recorded below.

## Rollback and release control

One coordinator owns the combined #10/#11 writer lane. Before a destination
write, compare its entire child-theme hash inventory and hashed shipping/payment
policy with the verified snapshot. Lint/hash the two files in a private staging
directory outside the web root, then install the asset before atomically
replacing the partial. Never upload a public PHP runner or replace the theme,
database or configuration wholesale.

Rollback is the snapshotted exact v2 partial, restored atomically only after
checking no later writer superseded v4, followed by a page-cache refresh and
browser/hash readback. The new PNG can remain unused; deleting it is unnecessary
for functional rollback. No database restore is involved.

Production is unchanged. After combined staging acceptance, obtain John's
explicit approval for the two-file combined production release, take a fresh
complete production backup with a verified off-server copy, then deploy and
independently verify both issues. Keep #10 and #11 open until live acceptance.
No global or project memory files were updated.
