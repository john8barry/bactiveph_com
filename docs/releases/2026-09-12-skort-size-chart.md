# Product-specific size guidance

Issue [#74](https://github.com/john8barry/bactiveph_com/issues/74) tracks the incorrect shared garment chart. Severity: medium. Owner: B Active / John Barry.

## Change and source

John supplied the authoritative Court Skort and Bubble Dress charts on September 11, 2026, and explicitly restricted each image to its named product. The previous shared S/M/L/XL Bust/Waist/Hips table came from placeholder build-guide content and is removed from the shared renderer.

- The exact `the-court-skort` product receives the numeric skort table. The exact `the-bubble-dress` product receives the separate dress table. Category membership never selects either chart.
- All six numeric sizes (4, 6, 8, 10, 12, 14), all 30 measurements, and the measuring/tolerance instructions are transcribed exactly, including inner length `10.0` for size 14.
- No conversion to store letter-size variations is invented. The chart directs shoppers to contact B Active to confirm their matching size.
- The Bubble Dress chart preserves all five sizes and 25 values, including XXL length 84, hip 98, and the source's Coat Length and Slack Bottom labels. Slack Bottom remains the supplied flat half-width; values are not recalculated.
- Every other/unknown product receives sizing help without a measurement table, including the other three skorts and all other dresses. Its non-JavaScript link reaches the standalone guide's other-styles section.
- The standalone guide labels the two specific products separately. Product fallback links reach their matching chart sections.
- Product variations, inventory, catalog records, payments, and shared custom CSS/JavaScript are unchanged.

## Acceptance and verification

- Both PHP source mirrors lint and match.
- Twelve size-guide tests pass: dialog behavior, both exact table transcriptions, exact-product resolution, same-category/unknown-product fallback, and guarded standalone-page rendering.
- Independent read-only review found no blocking transcription, routing, accessibility, or security findings.
- The Impeccable UI skill informed the contained mobile table, readable measurement instructions, and wider desktop dialog; its final detector returned no findings.
- Local browser verification covers desktop and 390-pixel mobile containment, native dialog dismissal, and focus return.
- Authenticated production preflight confirms 19 published products, Court Skort ID185 / slug `the-court-skort`, Bubble Dress ID56 / slug `the-bubble-dress`, and an existing Contact page.

## Release control

Production target: `https://bactiveph.com`, active child theme `blocksy-child`. John's existing approval to fix the size guide and get it live covers this narrow correction. A serialized theme-only writer window was released by the payment task. Normal payment callbacks/recovery may continue; no stable-order assumption is made.

Deploy only the size-guide section of the current live `functions.php` and `assets/css/size-guide.css`. Preserve unrelated live bytes, including a pre-existing whitespace difference from Git. Require fresh six-component UpdraftPlus backup with private off-server checksum/archive verification, exact live preimage checks, PHP lint, and independent public readback. Invalidate an active page cache when needed; never activate an inactive cache plugin to satisfy a release check.

## Live release result

PR [#78](https://github.com/john8barry/bactiveph_com/pull/78), head `125cba3f8a2dd24c87509a030172d2b31a1aff91`, merged as `20fab86c7280329d47bc34f91364118b78e6a78e`. All three checks passed on the PR and merged revision. Production installation completed September 12, 2026 at 04:55:18 UTC.

- The fresh six-component UpdraftPlus backup (370,581,242 bytes) passed private off-server SHA-256 and archive-integrity checks.
- Deployed `functions.php`: `68342a293a90f4b3c8a6dabf3a7edb1a59c21025d5521df64c18c27037662725`.
- Deployed `assets/css/size-guide.css`: `cd2e8883b54473ff5d8a683e93591318e942c6f7d1aae7034a740ac18b3ad32a`.
- Anonymous ordinary-URL readback passed on all 19 published products: only Court Skort and Bubble Dress have their respective tables; the other 17 have sizing help. Both standalone tables, exact measurements, section anchors, Contact page and public CSS checksum passed.
- Logged-in browser checks confirmed the two product charts, mobile containment, other-skorts fallback, direct Bubble Dress section navigation, Escape and close-button focus return.
- Shared `custom.css`, `custom.js` and `size-guide.js` hashes remain unchanged by this release. No catalog, stock, order or payment writes were performed.
- The first installation restored both exact preimages when its cache check found LiteSpeed inactive. Fresh authenticated readback confirmed restoration. The retry preserved the inactive plugin state, omitted the inapplicable purge, and verified dynamic public responses. No cache settings or plugins were changed.
- No new critical PHP errors appeared during 380 seconds of post-release monitoring. The final log delta was 162 bytes; exact hashes stayed unchanged. Final monitoring and writer-window release are recorded on issue #74.

PR #76 initially selected the whole skorts category. John clarified the product-only scope before any theme installation. The follow-up correction must be included in this release; the category-wide version must never be deployed.

## Remaining dependency

The other products' charts have not been supplied. Issue #74 stays open for those owner inputs. The current live Court Skort selector was observed with numeric options 6/8/10/12; the earlier all-letter catalog snapshot is stale. This release retains all six approved chart sizes without changing purchasable options or inferring a letter conversion. Do not reintroduce the placeholder chart, infer measurements from product names, or share either approved chart across a category.

## Rollback

Keep the private pre-release two-file snapshot and full UpdraftPlus backup in the project recovery directory. If these files are unchanged since deployment, restore the exact two preimages atomically and invalidate only the selected site's page cache. If later theme edits exist, apply only the inverse size-guide patch to the latest files; do not overwrite later work. Restoring the previous chart restores known incorrect shared guidance, so rollback is for a functional regression, not an approved sizing source.
