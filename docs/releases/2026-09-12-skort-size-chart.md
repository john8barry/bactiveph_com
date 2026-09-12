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

Deploy only the size-guide section of the current live `functions.php` and `assets/css/size-guide.css`. Preserve unrelated live bytes, including a pre-existing whitespace difference from Git. Require fresh six-component UpdraftPlus backup with private off-server checksum/archive verification, exact live preimage checks, PHP lint, cache invalidation, and independent public readback before declaring this release live.

Release result and deployed hashes will be recorded on issue #74 after installation.

PR #76 initially selected the whole skorts category. John clarified the product-only scope before any theme installation. The follow-up correction must be included in this release; the category-wide version must never be deployed.

## Remaining dependency

The other products' charts and the Court Skort numeric-to-letter size conversion have not been supplied. Issue #74 stays open for those owner inputs. Do not reintroduce the placeholder chart, infer measurements from product names, or share either approved chart across a category.

## Rollback

Keep the private pre-release two-file snapshot and full UpdraftPlus backup in the project recovery directory. If these files are unchanged since deployment, restore the exact two preimages atomically and invalidate only the selected site's page cache. If later theme edits exist, apply only the inverse size-guide patch to the latest files; do not overwrite later work. Restoring the previous chart restores known incorrect shared guidance, so rollback is for a functional regression, not an approved sizing source.
