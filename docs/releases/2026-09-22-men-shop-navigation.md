# Men Shop navigation

Work record: [issue #136](https://github.com/john8barry/bactiveph_com/issues/136). Owner: John Barry / B Active.

## Behavior

The custom Sage Shop menu gains **Men** between Leggings and Pickleball Dresses. Its nested **Tops** link opens the existing men's Tops archive at `/collections/tops-men/`, which currently contains the Everyday Active Tee and Everyday Active Polo. The existing mixed-gender Tops & Tanks link remains unchanged.

The nested **Bottoms** link is implemented for `/collections/bottoms-men/` but appears only when WooCommerce has a `bottoms-men` product category with at least one catalog-visible published product. At implementation, that category does not exist and its archive returns 404. Do not show a customer-facing broken or empty destination. When the first men's bottoms product is published and visible, create or assign it to that category and purge the homepage and shop page caches so the link becomes visible.

Desktop and mobile use native nested `<details>` elements. Escape closes the innermost open group and restores focus to its summary. Existing Shop links, ordering, Shop All placement, primary navigation, checkout and product data are unchanged.

## Verification

- `php tests/sage-header-contract.php` covers both rendered menus, exact destinations, empty/error/published Bottoms states and existing header guards.
- PHP syntax checks, JavaScript syntax check, mirror comparisons, `git diff --check` and the Impeccable detector must pass.
- Browser fixture checks at 1280 and 390 pixels cover nested click, Escape/focus behavior and horizontal overflow. Production verification must repeat these checks on ordinary public pages after deployment.

## Release and rollback

The release changes the Sage MU plugin and the child-theme Sage header PHP, CSS and JavaScript. Install the same child-theme files in both tracked source mirrors, but deploy only the active production paths. Before replacement, take a fresh exact-file off-server backup; verify destination identity, current hashes and a single writer; then use guarded atomic replacement and production PHP lint. Purge only the affected public pages and read back the exact links on desktop and mobile. Verify the empty checkout path and bounded critical-error logs. No database, payment or product write is part of this code release.

For rollback, first verify that each affected live file still matches this release's deployed hash. Restore only its exact preimage, retaining file modes; then lint and repeat the menu, checkout and log checks. If a later writer changed a file, reverse only this change against that current version.

## Production receipt — September 22, 2026

[PR #137](https://github.com/john8barry/bactiveph_com/pull/137) passed its Sage header workflow, was independently reviewed, and merged as `2224bf7e3f65dd72eefecdf23ecf8c8a7b4a5a7d` from implementation `497b6cf34e01c989f384a758da5ebfbf8df1d3b3`. The merged-main workflow also passed. The dirty shared checkout was not modified; implementation and release records used isolated branches.

Authenticated production preflight confirmed `https://bactiveph.com` and all four target files byte-for-byte equal to the pre-change main branch. A fresh complete UpdraftPlus backup started at 16:15:57 UTC. Seven archives across six component kinds totaled **563,198,539 bytes**; server/local SHA-256, sizes, ZIP/gzip integrity, and off-server storage all verified. Exact four-file preimages and the backup remain in a private local recovery directory.

The four files were staged outside the web root, hash-checked, linted, and atomically replaced with the MU plugin last. Production PHP lint and authenticated live readback passed:

| Production file | Before SHA-256 | Deployed SHA-256 |
| --- | --- | --- |
| `wp-content/themes/blocksy-child/assets/css/header-sage.css` | `e1522a21b278d2ec40ffe5add60e825d698de5ef0f17c534b970cc83bf37d097` | `0a083f8474511dda5001a155ab3e32e491b256f9257bc587e3742aac5983c687` |
| `wp-content/themes/blocksy-child/assets/js/header-sage.js` | `dca5f273be322a0e631e4ebd799731ab0812f725b084c2db88f38b269475df94` | `a49301ff29ef6d08b89588d237f698abdb5c2de4c82d6478a6e1515b7f38366a` |
| `wp-content/themes/blocksy-child/template-parts/header-sage.php` | `48c53d38926c9eb6fcaef00538850b7afb0ae62d752f8ba1936453c9a1c8862f` | `c6240a5e64109447dd07abe6e93c77d88fd441d260c65f3d3db5d0a4bc2bdf32` |
| `wp-content/mu-plugins/bactiveph-sage-header.php` | `6a2af47bdde8f80741964716d501d87ab7c62b9fa9ad69611eaf8a156d0e41c1` | `d7d2d9d189dcb8ca5029e742425b1c515b42df1ae151d0b6e927d0bd5857d75c` |

LiteSpeed site cache was purged once because the header appears on every public page. Ordinary homepage, shop, men's Tops, and men's product requests then returned HTTP 200 with Men → Tops on both desktop and mobile; subsequent cache hits retained it. Men and men's Tops archives returned HTTP 200. Bottoms remained hidden and its absent archive was not linked. The empty checkout path returned HTTP 200 after its normal cart redirect. Live 1280×900 and 390×844 browser checks passed for click, Escape/focus return, and no horizontal overflow. The bounded error-log check through roughly five minutes after replacement found zero new bytes, zero critical patterns, and no new debug log. All four live hashes still matched before the production writer lock was released and temporary server stage removed.

Issue #136 remains open for the first catalog-visible men's bottoms product, its category, cache invalidation, and live Bottoms-link verification. No product, order, payment, or database content was changed in this release.
