# Missing Men → Bottoms navigation

Severity: P2. Status: released and verified on September 25, 2026 UTC (September 24 in Denver). Related: issue #136.

Evidence: On September 24, live browser readback of /collections/men/ lists Essential Workout Shorts in Bottoms and Men. Its category link /collections/men/bottoms/ displays the shorts as its single result. Current origin/main f4a32287 checks the nonexistent anticipated slug bottoms-men instead of the existing bottoms slug. Shared desktop/mobile menu therefore omits Bottoms.

Scope: Correct only the category lookup and destination in the Sage MU plugin, and update the existing contract fixtures. Preserve catalog-visible count gating and all other menu behavior. No product or category changes.

Acceptance: Contract passes for missing, error, empty, hidden and visible products and both desktop/mobile rendered links. After approved deployment, verify Men → Bottoms on ordinary cached pages and the shorts at its destination.

Production impact: One navigation link becomes available; no checkout or database change. Production unchanged at preparation. Latest three Sage CI runs were successful; open PR titles showed no overlapping menu fix.

Release: Obtain John approval, verify live plugin preimage against current source, back up the exact file off-server, lint and atomically replace only that file, invalidate affected page cache, then verify destination, desktop/mobile menus and live hash.

Rollback: Guard against subsequent changes, restore exact backed-up plugin, lint, invalidate affected cache and verify prior menu. No database rollback.

Validation: PHP lint, 32 header contract assertions, six footer contract assertions, all nine JavaScript behavior tests, and diff whitespace checks passed. Independent read-only review found no code findings. Full diff reviewed with no credentials, generated assets, or unrelated changes.

## Production receipt

John approved publication in this task. PR #144 merged as `7d80240768602daaa245d7f94ae9deaae82e592b`, from reviewed implementation `cff01736722122e670dd5d6d3ddba86cebe0d1ea`. Both PR and merged-main Sage header CI passed; remote refs were read back.

Authenticated preflight confirmed production home/siteurl/database identity, the active child theme, and Bottoms term 135 with parent Men and one catalog-visible product. Its canonical URL is `/collections/men/bottoms/`. The old `bottoms-men` provisioning instruction in issue #136 is superseded; no taxonomy or product edit was needed.

A fresh successful Updraft backup covered all six component kinds in seven archives totaling 568,622,030 bytes. Every archive was copied off-server, size/SHA-256 matched and ZIP/gzip integrity checked. An exact plugin preimage is retained separately in private recovery storage. One MU-plugin file was staged outside the web root, linted, guarded against preimage drift, atomically installed under the production writer lock, and read back byte-for-byte with mode 0644 retained.

- Before SHA-256: `577bcb8e3ac053c50439830b338947d8d6975f66f9e427aba3593b1f115b014e`
- Deployed SHA-256: `614d1a077c5cb0f15a132479c4579e1a5f1a75791dcf29ebc3ecfcf0bb6ade1d`

The shared header required LiteSpeed public-page cache invalidation. The first homepage read was stale while the queued purge completed; a full repeat passed on ordinary homepage, shop, Bottoms, men's Tops, cart and empty-checkout requests. All returned HTTP 200 (checkout normally redirected to cart); both menu markups contained the correct link. Cached homepage/shop/category hits retained the fix; Cloudflare reported DYNAMIC. No Cloudflare configuration or zone-wide purge was used.

Live browser checks verified desktop and 390px mobile menus. Tapping mobile Men → Bottoms opened Essential Workout Shorts. At 136 seconds after installation, the file hash still matched and both error/debug log checks showed zero new bytes and zero critical patterns. The writer lock was released. Private recovery files remain under the local BactivePH mens-bottoms-20260924 release directory. The rollback operator supports guarded restoration, purge, old-menu verification and unlock.

Pre-existing repository Dependabot alerts were inspected: 19 open (10 high, 8 medium, 1 low), in existing theme package manifests and the sodium_compat PHPUnit development manifest. None concerns the changed MU-plugin file; no dependencies were changed in this release. These existing alerts are not claimed resolved or assessed as a full security audit.
