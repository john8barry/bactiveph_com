# GrabExpress branding for Davao City only

Work records: [#11](https://github.com/john8barry/bactiveph_com/issues/11) and the
combined [footer-copy cleanup #10](https://github.com/john8barry/bactiveph_com/issues/10).
Delivery record: [PR #12](https://github.com/john8barry/bactiveph_com/pull/12).
Status: **LIVE VERIFIED**, combined footer `2026-09-05-v4`.
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
`git diff --check` passed. Independent source/security/accessibility review
passed against the exact partial and asset hashes above.

### Staging acceptance

The exact two-file overlay from source commit `541bd44` is installed on staging.
Native PHP lint passed before installation. Authenticated readback at
2026-09-05 07:48:15 UTC confirmed exactly those two child-theme changes, unchanged
hashed payment/shipping policy and gateway enablement, unchanged error/debug
logs, and `Success: WordPress installation verifies against checksums.`

Public checks passed across seven routes (home, shop, shipping/returns, FAQ,
product, cart and checkout's normal empty-cart redirect) and all ten asset
responses matched local SHA-256. Both removed notes are absent everywhere
checked; Davao-only grouping and all existing payment marks are retained.

Main-agent screenshots verified the actual staging footer at desktop 1280px and
mobile 390px. Browser geometry checks passed at 390, 768 and 1440px: no horizontal
overflow, all images loaded and every courier link remains 132 by 44px. Grab's
2868:800 logo renders about 93.20 by 25.99px, without distortion. Keyboard Tab
from LBC reaches GrabExpress with a visible 2px solid outline. The independent
footer-copy task also visually accepted desktop 1440px and mobile 390px; both
notes are absent and business/BIR content is intact. A supplemental tablet
screenshot could not be captured; its DOM/layout checks passed. Temporary
browser size overrides were cleared.

The fresh production instruction was independently read in **Remove repetitive
footer text**, task `01a07062-0209-7a60-9a70-8f21d68533f8`, user turn
`01a0708c-0d42-7352-95d6-2637cd8d066b`: "get it in production if it looks alright".
The preceding user-visible response explicitly said the removal was bundled with
GrabExpress and awaiting combined approval. The combined staging visual
condition has passed. This records actual same-project user authority, not an
approval inferred solely from an agent message. The courier coordinator remains
the single production writer. This was the staging checkpoint; production
acceptance is recorded below.

### Production acceptance

Fresh production Updraft backup timestamp `1788594862`: PASS, all six
components, 329,610,949 bytes. Every archive was copied off-server and verified
against its size and SHA-256, then passed ZIP/gzip integrity checks. The
independent footer-copy task also checked all six off-server hashes and sizes.
No existing backup was pruned.

Production deployment completed at 2026-09-05 07:58:44 UTC. Native PHP lint and
hash checks passed in a private directory outside the web root. Only the two
allowlisted files above were atomically installed, asset first, followed by
the page-cache refresh. The deployed source is the reviewed `541bd44` overlay,
unchanged in published head `e2eb041bd38ec564c817d4bf7424f214feb989f8`.
The server is verified by exact file hashes, not an assumed Git checkout.

Authenticated production readbacks at 08:03:13 and 08:05:44 UTC both passed:

- Exactly the GrabExpress PNG and trust-bar partial changed; all other
  child-theme bytes match the pre-release snapshot.
- Hashed shipping/payment policy and gateway enablement are unchanged.
- Error/debug-log hashes are unchanged; WordPress core checksum verification
  passed. No new browser console errors were observed.
- Normal anonymous checks passed twice across all seven routes and all ten
  assets. Both removed notes are absent, local/nationwide groups are distinct,
  all payment marks and the BIR link remain, and checkout retains its normal
  empty-cart redirect. No order or payment was submitted.

Main-agent production screenshots passed at 1440px desktop, 390px mobile and
the normal 736px browser panel. Every courier tile is 132 by 44px, all ten images
load, and there is no horizontal overflow. Native logo proportions are intact.
The independent footer-copy task separately accepted desktop 1440px and mobile
390px, exact origin hashes/logs, and four normal anonymous public routes at
08:02:24 UTC. A single earlier anonymous request returned HTTP 525; its bounded
recheck returned 200 at 08:01:45 UTC, and subsequent full checks passed. No TLS,
DNS or security control was altered. All temporary browser size overrides were
cleared.

The final origin observation is nearly seven minutes after deployment, with
no new critical errors or configuration drift. Sanitized receipts are retained
in the private release directory; secret-bearing backup contents are not
published. PR #12 holds the exact source, tests and evidence. The canonical
dirty checkout remains deliberately untouched and must not be described as
synchronized with this isolated release.

**UNVERIFIED / out of scope:** this presentation release does not activate or
prove PayMongo rails, create a GrabExpress checkout method, or clear #2, #7 or
#9. Those existing owners and holds remain unchanged.

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

Production acceptance and recovery readiness are complete. Close #10 and #11
only after the reviewed PR is merged and the resulting main tree is verified
against these deployed hashes; record that exact merge/ref in the work items.
No global or project memory files were updated.
