# Original illustrated size guides

Issue [#74](https://github.com/john8barry/bactiveph_com/issues/74). Medium storefront guidance improvement; owner John Barry / B Active.

## Request and scope

Following approval of the product-specific tables, John requested the original images showing where to measure the body. Each original illustration now precedes its accessible table, scales without cropping, and opens at full resolution in a new tab. Existing tables, measurement instructions, exact-product routing and fallback links are preserved. Court Skort alone gets its skort illustration; Bubble Dress alone gets its dress illustration. The standalone guide includes both in separately named sections. Other products receive neither illustration.

This is a narrow extension of the existing ivory/charcoal size-guide design, not a redesign. The Impeccable skill informed responsive sizing, visible enlargement guidance and keyboard focus. No generated or modified image is used.

## Asset provenance

John supplied both original 853-by-1280 JPEGs on September 11, 2026. The copied shipping assets are byte-identical to the supplied files, with source-integrity regression tests:

| Asset | Original filename | SHA-256 |
| --- | --- | --- |
| `court-skort-illustrated-20260911.jpg` | `photo_2026-09-11 22.07.57.jpeg` | `9658c8213afa114480f5563f839fb890a93cb786bc019e374d788b6c0b6cdfaf` |
| `bubble-dress-illustrated-20260911.jpg` | `photo_2026-09-11 22.42.19.jpeg` | `42a81babe16dea20dbeb5bf6a86cd6c8d05880a4466ebd727c78136badbac2e9` |

## Verification and release controls

- Thirteen size-guide tests: existing dialog/measurement/routing coverage plus asset integrity, correct product association, dimensions, new-tab safety, illustration-first ordering and negative image routing.
- Both PHP mirrors match and lint; dedicated UI detector returned no findings.
- Local desktop and 390-pixel mobile inspection confirms uncropped images and scrollable tables. No shared custom CSS/JavaScript, catalog, stock, order or payment changes.
- Production is `https://bactiveph.com`, not staging. John's existing go-live approval applies to this requested correction. Serialized theme-only window: September 12, 2026, 05:25–05:45 UTC.
- Reuse the verified six-component UpdraftPlus database/files backup from this same session (04:20 UTC; 370,581,242 bytes), already copied off-server with SHA-256/archive checks. Capture a fresh exact-file snapshot immediately before this patch; database restoration is not part of rollback because orders/payments can continue.
- Deploy only the size-guide region of current live `functions.php`, dedicated `size-guide.css` and the two new JPEGs. Preserve unrelated production differences, including the separate shipping-minimum release. Stage images before publishing image references; compare live preimages immediately before replacement. Preserve the inactive cache-plugin state.

## Acceptance and current status

Local implementation complete; independent review, GitHub checks, production installation and live verification pending. Do not treat this record as proof of deployment until updated with the receipt.

## Rollback

Restore this change's fresh exact PHP/CSS preimages only if live files still match this release; otherwise reverse only the illustration delta in the latest files. Retain the two harmless original image assets for recovery; remove them only if no remaining live reference exists. No database or plugin-state rollback. The preimage restores the already approved product-specific tables, not the obsolete universal chart. Keep the original supplied JPEGs unchanged.

Issue #74 remains open for the other products' missing chart inputs; this addition must not invent measurements or expand product associations.
