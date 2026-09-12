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

Live installation completed September 12, 2026, at 05:27:20 UTC. PR [#81](https://github.com/john8barry/bactiveph_com/pull/81), source head `0cdfb7e7d33707155ccd4c729647fb2335315c00`, merged as `9249d093be012916746cf32733913809c2391908`. Independent finish review returned **ship**, with no material findings; documentation review confirmed the scoped record and provenance. All four GitHub workflows passed on both PR and merged revision.

- Anonymous ordinary-URL readback passed for all 19 published products. Only the two approved products render their matching illustration. Both standalone figures, exact 55 measurements and fallback anchors passed.
- Both public JPEG URLs returned HTTP 200, image/jpeg, and byte-for-byte original checksums. Public dedicated CSS matched its deployment checksum.
- Logged-in live browser verification confirmed Bubble Dress's loaded 853-by-1280 image, desktop/mobile containment, and the full-resolution image tab. Local screenshots also cover Court Skort. A separate Chrome automation check timed out; logged-out verification is anonymous HTTP readback, not a completed Chrome UI test.
- Production `functions.php`: `b5b0282725e767677fd9b7fe60fa28667684953291fadf2f55a1e89ce9520b00`; `size-guide.css`: `321a300c740001e80663fab375194e400582e183bcc9c7a60a65160daec0978a`. Image hashes are in the provenance table above.
- Final readback at 05:31:31 UTC (251 seconds after installation) confirmed all seven changed/protected file checksums, zero new error-log bytes and zero critical errors. Shared custom CSS/JS and dialog JavaScript stayed unchanged. Cache plugin remained inactive.
- Fresh PHP/CSS preimages were captured at 05:25 UTC, and the same-session six-component off-server Updraft backup was re-hashed successfully. Remote staging directory was removed, all SSH/SFTP connections closed, and the writer window explicitly released at 05:31 UTC to the payment and shipping tasks.
- Production intentionally retained the pre-shipping-release sections of `functions.php`; PR #79 is merged but its production rollout belongs to the shipping task. That task will patch its sections onto a fresh readback, preserving these illustrations. Do not overwrite the whole theme from Git to resolve that recorded difference.

Private evidence lives in the project-owned `illustrated-size-guides-20260912` recovery directory: exact preimages/candidates, manifest, deployment receipt, public verification, final monitoring and browser captures. No memory files were updated.

## Rollback

Restore this change's fresh exact PHP/CSS preimages only if live files still match this release; otherwise reverse only the illustration delta in the latest files. Retain the two harmless original image assets for recovery; remove them only if no remaining live reference exists. No database or plugin-state rollback. The preimage restores the already approved product-specific tables, not the obsolete universal chart. Keep the original supplied JPEGs unchanged.

Issue #74 remains open for the other products' missing chart inputs; this addition must not invent measurements or expand product associations.
