# Automatic product presentation and colour editing

Status: implemented in PR #101 on `codex/catalogue-defaults-20260914`; **not live**. This extends issue #48 and incorporates the merged PR #102 gallery startup fix and PR #103 first-render/reset correction. The existing 15 qualified product releases and PR #100 related-card previews are live. Further lossless-image activation, the new defaults, native editor and metadata migration require their own release evidence; see `LOSSLESS-DELIVERY.md` for the current canary and gallery-release checkpoint.

## What becomes automatic

New and existing products use the shared product layout and collection cards after the defaults release is enabled. Simple products retain their normal purchase flow. Variable products keep WooCommerce's actual attributes, variation matching, defaults, prices, stock, quantities and cart IDs. Numeric sizes stay numeric. Unsupported attributes keep their native controls.

Five independent switches control layout, cards, selectors, image quality and the admin editor. The existing homepage editorial switch remains separate. A product can use Native WooCommerce controls as an individual fallback. Missing or unreviewed colours/photos do not block publishing: named options, original images and native matching remain usable.

This release preserves the approved header, footer, sage/ivory palette, typography, size guidance, shipping work and SKU privacy. It does not generate inventory, convert simple products to variable products, merge colour terms, change URLs or overwrite image assignments.

## Adding a new colour

1. In **Products → Attributes → Colour → Configure terms**, add the colour name and its default colour circle with the colour picker. A blank value gives a name-only option. Keep Bloom and Sakura Pink separate.
2. Edit a product, choose the global Colour attribute, select its colours and save. For a variable product, use WooCommerce's Variations tab to create only the actual sizes/colours offered, with their prices and stock.
3. Open the product's **Colours & photos** tab. For each colour choose **Use global shade**, **Custom shade for this product**, or **Name only**. The shade applies across its sizes.
4. Choose a representative photo from the media library. Review the size photos shown below it. Different sizes may use different models/photos; edit those individually in the normal Variations tab.
5. Check the confirmation box for each reviewed colour and use Publish or Update. Updating a product alone never approves a new photo mapping.
6. Check the published product at desktop and mobile widths, including a real colour/size selection, reset and size guidance.

The global editor reports how many products inherit that shade. Existing migrated products retain their reviewed product-specific values, so a global change does not silently recolour them. Explicit product overrides always win. Renaming or replacing an image/variation mapping requires fresh photo review. An outdated product editing tab is refused rather than overwriting newer settings; reload it and review again.

The current editor integrates with the incumbent classic WooCommerce product editor. A different product editor must be qualified before enabling its custom UI; native WooCommerce editing remains available.

## Which photo customers see

| Situation | Behaviour |
|---|---|
| Colour selected, size incomplete | Show the explicitly reviewed representative photo. |
| Complete valid variation with its own photo | Show that variation's photo, including its different model. |
| Complete valid variation without its own photo | Use the reviewed representative in WooCommerce and the theme's gallery. No attachment assignment is written. |
| No valid reviewed representative | Preserve the native original/gallery fallback. |
| Related-card colour with a reviewed photo | Preview it in the card without navigating or changing cart/primary links. |
| Related-card colour without a reviewed photo | Keep the ordinary product link. |
| Reset, native dropdown fallback, or manual gallery browsing | Clear the temporary colour preview and retain native gallery operation. |

A temporary colour preview cannot open the different image underneath it. Native gallery zoom is suppressed while that preview covers the gallery; selecting a size or deliberately browsing thumbnails restores native behaviour. Existing configured lightbox behaviour remains unchanged.

Photo approval binds product, actual term identity/name, representative attachment and bytes, and every matching published variation's attributes, explicit image and bytes. Different size images are valid. Wildcard/missing colours are not approved. Duplicating a product clears copied approvals. Image replacement and variation changes invalidate cached results. Frontend code never guesses a representative from the first size.

## Imagery work

Generate needed imagery in Codex from the accepted product reference, retaining originals and source hashes. Do not add a paid generation service to WordPress. Preserve visible garment details; do not invent texture, rear construction or unsupported close-ups. Allow one correction attempt, then hold a persistent defect. Review every generated colour/image before assignment or approval.

Use the existing high-resolution source or exact reviewed lossless derivative. Do not upscale a thumbnail and label it high resolution. Keep filenames neutral, and do not expose SKUs through new UI data.

## Existing catalogue migration

The verified September 14 baseline has 19 published products and 253 variations. Migration seeds 15 qualified products, retains 50 approved circles, and seeds 50 representative previews only where the current variation set, size, term, attachment and source hash match the reviewed manifest. A future product with different size photos requires explicit representative review.

Bubble Dress (56), Rally Skort (160), Ribbed Tank (211) and Sculpt Leggings (238) remain held. Their mappings are not migrated. Trashed Pleated Skort (148) and Aria Set (347) remain excluded. Court Skort's Lavender remains name-only pending its photo question. Shared presentation may be used on held products, but holds do not disappear through a normal editor save.

Migration records exact before/after metadata bytes. Existing overrides, including unexpected/duplicate metadata rows, are preserved. Intentional legacy omissions become Name only. Global defaults are seeded only for unambiguous, unanimous reviewed shades. The migration never enables the feature switches.

Run the CLI-only migration helper after WordPress and the new model are loaded:

```text
wp eval-file tools/catalogue_settings_migration.php dry-run MANIFEST_PATH MANIFEST_SHA256
wp eval-file tools/catalogue_settings_migration.php apply PRIVATE_PLAN_PATH PLAN_SHA256
wp eval-file tools/catalogue_settings_migration.php rollback PRIVATE_PLAN_PATH PLAN_SHA256
```

Persist the dry-run JSON privately, inspect it and calculate its hash before apply. Execute during the single-writer window. All rows are checked before any write; InnoDB transactions and exact comparisons prevent partial rollback or overwriting later metadata edits. An uncertain commit must be reconciled from current row states before retrying. Cart activity and changing stock/prices must not be reverted by a visual rollback. No full database restore is used for routine rollback.

## Verification already completed locally

- Fresh encrypted off-server full backup: manifest `e537c4f008687e9836da049c5ca9aa2eb6d7169b7021f3ba407c1ddd3fff445d`.
- Actual isolated restore: separate receipt `c753118762d33488698cc9c89bbf510ae981790b0f0bb13109cad24c5fadf906`. The immutable backup manifest retains its original capture-time status. The restore receipt is the later proof.
- Restore contains no production users/orders and has network, mail, payments and jobs blocked. The new localhost-only admin preview uses a synthetic account.
- Actual migration apply, repeated apply, rollback, repeated rollback and later-writer refusal passed. The test preserved a later stock edit, then restored the test fixture and re-applied metadata for editor testing. Commerce fields were unchanged.
- A newly created private variable product preserved distinct S/M images, kept sold-out L unavailable, used a reviewed fallback for L's missing photo, and matched the native theme gallery to WooCommerce's payload. Photo changes invalidated review and an outdated editing form was refused.
- An actual internal WooCommerce REST update preserved the saved parent gallery and the variation's explicit empty image assignment. The reviewed representative appeared only in the display path; anonymous private-product access remained denied.
- After integration with PR #102, all 32 JavaScript tests passed against both repository and fresh-restore WooCommerce/Blocksy engines. Five PHP suites and 19 Python tests passed. The combined lifecycle covers representative preview, complete variation, clearing size, keyboard browsing and native fallback. Native synthetic gallery clicks no longer dismiss the returning colour-only preview. These tests simulate trusted-input handlers; actual browser delivery remains a separate gate.
- After merging PR #103 at `eb8e80a0d289b8b267d53591ee57cb253361ae54`, all 39 combined JavaScript tests passed against both repository and fresh-restore engines using `/usr/local/bin/node` v24.13.0. All 21 inherited gallery tests were preserved; the added case verifies that a new product's representative preview survives native Reset during Flexy's first render. The PR #101 keyboard and trusted-navigation guards remain intact. Five PHP suites and 19 Python tests also passed. This verifies the local merge, not the outstanding browser or production gates below.
- The native server matrix covered all 19 original products and 253 variations: 187 correct cart additions, 65 unavailable rejections, and the existing held Bubble variation 80 missing-colour rejection. All 43 absent combinations, 294 selection-order matches and 19 blank resets were checked. Non-image payloads and protected metadata were unchanged; the test cart was cleared.
- A separate contained restore completed the full deployment rollback rehearsal: all 11 intermediate file states booted with enhancements disabled; metadata and defaults-option changes applied and rolled back; all six originally absent files returned to absence. Later stock changes survived, while conflicting later option/file edits stopped rollback. Final reapplication also passed. This proves the tested local recovery path; the production adapter still needs its own current bindings and qualification.
- Current dependency triage found no new dependency path specific to this change. Four active parent-theme vendor findings remain unresolved; this does not constitute overall security clearance. Parent vendor updates remain a separate tracked responsibility.

These are local results, not production proof. Private receipts remain under `catalog-recovery/restore-20260914T184757Z`; no synthetic credentials, raw databases or customer data belong in GitHub.

## Remaining interaction qualification

A native-engine fixture exposes an existing animation edge: clear only size while keeping the colour, then reselect that same size during Reset's movement. The native theme can settle on another photo. The same result occurs without PR #101's layout or representative-preview modules; PR #103 addresses first-render readiness and does not resolve this separate edge. Actual browser reproduction and resolution are required before promoting automatic defaults. The fixture's simulated geometry is not production evidence. No speculative animation change is included in this merge.

## Remaining release sequence

1. Independently verify PR #103's gallery correction in production before continuing PR #100's lossless-image activation. Related previews and the Courtline lossless canary are already live. Use a new option-only release journal protecting the deployed gallery fix; preserve earlier file-release journals and backups.
2. Reconcile current main, this feature branch, the current catalogue, applicable security findings and the production target. Recheck the GitHub account before every push or mutation.
3. Finish actual admin media-picker/save and responsive storefront checks. The full native variation/cart matrix and source review have passed; rerun affected checks after any fixes.
4. Refresh the backup/target evidence as needed; qualify the production adapter against the completed full rollback rehearsal and stage the new files with defaults disabled. Bind production recovery to the production snapshot, including original file absence and later-edit guards.
5. During the single-writer window, compare the fresh migration plan with the approved baseline, apply only its owned metadata, then enable defaults in the reviewed order. Keep the five switches and individual native fallback usable.
6. Verify current cached URLs, image loading, colour previews, selections/reset, numeric sizes, quantity, cart contents and size guidance. Use bounded anonymous production cart checks without orders or payments; remove test items.
7. Monitor each release for 20 minutes and perform the next-day check. Update issue #48 and the product checklist with verified live evidence, held products, exact commits, rollback receipts and remaining imagery gaps.

Completion requires the automatic defaults and editor verified live, documented product-by-product status, a tested complete rollback and follow-up monitoring. Unresolved product/photo questions remain explicitly unfinished.
