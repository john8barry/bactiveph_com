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

Production deployment and live verification: pending.
