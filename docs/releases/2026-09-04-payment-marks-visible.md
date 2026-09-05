# Footer payment marks: visible inventory and matching COD tile

Work record: [issue #5](https://github.com/john8barry/bactiveph_com/issues/5). Severity: medium, missing customer-facing payment branding. Owner: footer-branding task. Gateway activation remains owned by [#2](https://github.com/john8barry/bactiveph_com/issues/2) / [PR #4](https://github.com/john8barry/bactiveph_com/pull/4).

## User correction and scope

The user explicitly confirmed QR Ph, Maya, ShopeePay, BPI Online and UnionBank Online, separate PayMongo processor branding, and generic COD. V1 hid the entire online payment group until the new gateway was ready. This caused the reported missing footer logos.

V2 keeps all five approved marks and the processor visible regardless of gateway readiness. Until the gateway is available, a truthful note says: "Online payments are being set up. Check available options at checkout." Readiness continues to govern that note; it does not govern the brand inventory. Actual checkout availability, provider configuration, payment execution, COD eligibility/fees, orders and shipping rules are unchanged.

The COD mark is now a passive, locally hosted SVG: the same 121x49 rounded-pill silhouette as the five official PayMongo payment-method assets, with a white money icon and COD lettering. All six use the same 108x44 tile and intrinsic aspect ratio, in a three-column grid. COD still renders only when its existing gateway is enabled. PayMongo is a separate processor wordmark, not a seventh customer payment method. J&T/LBC and all BIR/business identity content are preserved.

## Reconciliation and verification

- Baseline: remote main `a029911a6f52ccddbe08a8857ebf925d7dc2ea01` (v1 PR #6). Branch: `codex/footer-payment-marks-visible`. Work is isolated from the canonical checkout's unrelated dirty changes.
- Both checked-in theme mirrors have identical partial/COD asset bytes. The only deployable changes are `template-parts/trust-bar.php` and `assets/images/payments/cod.svg` in the active child theme.
- The unchanged CLI guard and the modified regression harness run in local PHP 8.2 WebAssembly, with the documented CLI SAPI name. All seven scenarios pass: ready, not-ready, missing-gateway, gateway-error, manager-error, all-disabled and no-commerce. Exact logo count/order, setup-note visibility, COD enablement, forbidden marks and courier presence are asserted.
- Independent read-only review found no release-blocking source/security/accessibility issue in the bounded diff. The new SVG contains no scripts, handlers, external references or executable content. No new application dependencies are introduced.
- Source package: `a607706161ee4ae17c33d49b9afafb65b282e111`, [PR #8](https://github.com/john8barry/bactiveph_com/pull/8). The subsequent closeout changes only release documentation.
- Staging and production now return `data-bactive-trust-version="2026-09-04-v2"`. Native browser checks and independent public HTTP/asset checks are recorded below.
- Existing dependency alerts remain tracked by [#7](https://github.com/john8barry/bactiveph_com/issues/7); this presentation-only change does not modify those packages. The payment coordinator's separate legacy-credential containment hold is [#9](https://github.com/john8barry/bactiveph_com/issues/9). This release does not rotate credentials, clear that hold or certify whole-site security.

## Deployment outcome: 2026-09-05 UTC

V2 is deployed to **https://bactiveph.com**, after staging acceptance. The earlier session's network/browser restrictions are historical, not the current release state: the resumed task had network access and used the existing project credentials through trusted-host SSH/SFTP on port 21098. No network policy, authentication control or public administration endpoint was introduced or bypassed.

The PayMongo coordinator explicitly acknowledged the serialized backup/deployment window in #5 and #2. Footer coordinator: Codex task `01a06d82-576d-76f3-bffb-6f9142e371a9`. No payment/provider writer overlapped this release.

- Verified distinct staging/production home URL, site URL, database, `blocksy-child` theme and search-index setting immediately before writing. Staging remains noindex; production remains indexable.
- Fresh full Updraft backups contain DB, plugins, themes, uploads, mu-plugins and others. All six components from each site were copied off-server, matched by SHA-256/size, and passed ZIP or gzip integrity checks. Staging backup timestamp `1788589792`: **121,584,684 bytes**. Production timestamp `1788590085`: **329,606,932 bytes**. Updraft has no configured remote destination; the verified off-server copies are the retained recovery sets, not a claim of a configured cloud destination.
- Uploads were hash-checked and PHP-linted in a private directory outside the web root. Action-time old-file checks preceded atomic asset-first/partial-second renames. Only `assets/images/payments/cod.svg` and `template-parts/trust-bar.php` were deployed. The temporary upload directory was removed after its files were moved.
- Independent full-child-theme inventories confirm exactly those two files changed on each site. Root footer, shared footer, functions, existing logo assets and BIR/business identity remain byte-for-byte unchanged.
- Both sites pass WordPress core checksum verification. Active-plugin, COD/BACS, shipping-rate, currency/default-country and guest-checkout policy hashes, plus gateway-enabled maps, are unchanged. Error-log hashes are unchanged; neither site has a WP debug log.
- Site page caches were purged through the existing LiteSpeed hook. Every sampled public HTML response reports Cloudflare `DYNAMIC`; no Cloudflare configuration change or broad edge purge was needed.

## Browser and public acceptance

- Seven PHP 8.2 regression scenarios pass. The ready-state fixture is an exact-template simulation, not evidence that PayMongo is activated. The ready fixture and the actual unavailable staging state were visually checked at 390px, 768px and 1440px.
- Actual staging home/footer passes 390px, 768px and 1440px checks. All nine images load, and document width equals viewport width. J&T-to-LBC keyboard traversal has a visible 2px focus outline. Five staging pages and all nine assets pass public HTTP/byte verification.
- Actual staging checkout accepts selecting generic Cash on Delivery and shows its existing fee. No customer data was entered and no order or payment was submitted. The one test-cart item was removed; the empty cart was independently verified afterward.
- Actual production passes signed-in desktop (1440px), signed-in mobile (390px) and signed-out desktop (1272px) browser checks. Screenshots were captured in the controlling task. All six payment badges measure **108 x 43.734px**, retaining the official **121:49** aspect ratio. PayMongo remains a separate **92 x 15.773px** wordmark. All nine images load with no horizontal overflow.
- All **61** production URLs from the advertised sitemap plus the homepage pass footer/content checks. Empty-cart `/checkout/` returns the expected 302 to `/cart/`, which returns 200 with v2; the other pages return 200 directly. All nine public logo assets match reviewed local bytes. No Ninja Van, Visa, Mastercard or GCash marks occur in those footers; J&T/LBC, COD copy and the BIR link are retained.
- Production browser navigation became unavailable after successful signed-out visual acceptance, so an additional populated production checkout test was not completed. This is not represented as a completed live payment/order test. Staging checkout and unchanged live policy provide the bounded checkout evidence for this display-only release.
- The real production PayMongo gateway is not ready. All five marks remain visible with the setup notice. Actual online-rail activation and payment execution remain **UNVERIFIED** in this release and owned by #2, with the #9 containment hold retained.

Sanitized receipts and private recovery sets are retained locally under `tmp/footer-display-2026-09-04/`: per-environment `*-receipt.json`, `*-audit-before.json`, `*-audit-after.json`, `*-public-receipt.json`, `*-snapshot/manifest.json` and `*-backup/manifest.json`. Backup archives and credentials are excluded from Git. The canonical checkout's unrelated dirty work is preserved; this is not a claim that the entire checkout is synchronized with production.

Deployed SHA-256:

| Active-theme file | SHA-256 |
| --- | --- |
| `template-parts/trust-bar.php` | `8bf5a8dfb2ab216c19489717cbb07860191ec289f2066b03de789650a0a47d00` |
| `assets/images/payments/cod.svg` | `11b4d44398e908610faecde5cb060c53bb4e25953849ada1147d51f5440cd2b5` |

## Rollback

The exact pre-write partial is retained in each environment's snapshot; SHA-256 `3d235ab558371c42e0235cbed4e50072cef23969d060e4945b9ac193d08526ee`. Before rollback, acquire the writer window and verify the current partial still has the v2 hash above. PHP-lint the snapshot, upload privately, verify its hash, then atomically restore only that partial. The new unreferenced COD asset may remain harmlessly. Purge the site page cache and verify the restored v1 marker and checkout. Stop on later-writer drift; preserve newer payment-gateway or business-identity releases. Full Updraft recovery is reserved for broader recovery. Do not restore compromised credentials from backup. No database changes are part of v2.

Memory files updated: none.
