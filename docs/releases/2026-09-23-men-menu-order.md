# Men menu order — September 23, 2026

Status: deployed and verified on production.
Severity: low, requested navigation adjustment.

Men currently follows Leggings in the shared Shop collection list. Move the existing nested Men entry directly after Tops & Tanks and before Shop All on desktop and mobile. Destinations, nested items, and Bottoms visibility rules remain unchanged.

Acceptance: both rendered menus end with Tops & Tanks → Men → Shop All; existing header contracts pass. The source change is one array-entry move in `wordpress/wp-content/mu-plugins/bactiveph-sage-header.php`; existing order assertions are updated.

Production impact: navigation ordering only. Release only the MU plugin after fresh target/hash verification and an exact-file backup, then invalidate affected header caches and verify both live menus. Roll back by restoring the verified preimage only if the deployed hash still matches; otherwise reverse this entry move against the current file. No database migration is needed.

Validation: PHP syntax, all 31 header guard/markup assertions (including exact desktop/mobile order), six footer contract assertions, and `git diff --check` passed. Reviewed the complete diff; no credentials, generated assets, or unrelated source changes are included. Base: `d165b3c2`; branch: `codex/men-menu-order`.

Independent read-only review found no actionable issues. All nine existing JavaScript behavior tests also passed. John approved commit and deployment in the project task.

## Production receipt

[PR #142](https://github.com/john8barry/bactiveph_com/pull/142) merged as `1f675ad82a5557c953317677f431eb27f5604137` from implementation `d792a7a049ecf2caf911f8d22d098426abc19657`. PR and merged-main Sage header workflows passed. The remote branch and main refs were read back. Existing dependency issues #7 and #9 remain outside this navigation-only change; no security clearance is claimed.

Strict-host SSH/SFTP preflight verified both WordPress URL options and exact MU-plugin parity with pre-change main. An exact-file off-server rollback copy was retained privately. Under the exclusive production writer lock, the plugin was staged outside the web root, linted, guarded against preimage drift, and atomically replaced with mode 0644 preserved. Immediate production lint and byte readback passed.

- Before SHA-256: `d7d2d9d189dcb8ca5029e742425b1c515b42df1ae151d0b6e927d0bd5857d75c`
- Deployed SHA-256: `577bcb8e3ac053c50439830b338947d8d6975f66f9e427aba3593b1f115b014e`

The initial LiteSpeed CLI purge did not clear the public menu: its admin-ajax transport returned a browser-check page. Recovery explicitly queued the page-cache purge and issued a fresh normal storefront HTTP request within the same WP-CLI invocation. Ordinary public homepage, shop, men's Tops, cart and empty-checkout requests then returned HTTP 200 and the expected desktop/mobile order. Homepage cache-hit readback also passed. No Cloudflare-wide purge was performed.

Live browser verification confirmed desktop and 390px mobile order, plus the working mobile Men → Tops disclosure. At 290 seconds after installation, authenticated readback still matched the deployed hash; error_log and debug.log had zero new bytes and zero critical patterns. The production writer lock was released. Private exact-file backup and sanitized receipts remain available for rollback. No order, payment or product data was changed.
