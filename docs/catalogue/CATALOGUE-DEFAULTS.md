# Automatic product presentation and colour editing

Status at the September 15 checkpoint: **automatic defaults, the colour editor and the reviewed metadata are enabled in production** from PR #101, merge `8461d1832749047f39078a9b8ed4dced47adb40a`, reviewed source `b37b04dc4ae1dfaed03f3b409050292b9fccc94f`. Independent readback verified all eleven installed files, the metadata after-state, all five enabled switches and 342 protected files. All 272 captured product/variation row hashes stayed unchanged.

All 15 qualified products passed anonymous cart samples and test-item removal. The live browser checks below passed; production editor inspection was read-only, with save/reopen tested separately in the isolated fixture. The defaults 20-minute monitor **PASSED 119 checks over 1,220.32 seconds**. Next-day verification is **PENDING for September 15 after 14:00 UTC**. The next-day check is not marked complete.

Lossless delivery is verified for all ten products with reviewed conversions; the other five qualified products retain full-resolution JPEG originals. All three resumed image batches passed their monitors. The four catalogue-mapping holds remain. See the [release record](../releases/2026-09-15-catalogue-defaults.md), [lossless delivery](LOSSLESS-DELIVERY.md), [gallery behaviour](GALLERY-STARTUP.md) and [product checklist](PRODUCT-VERIFICATION.csv).

## What becomes automatic

New and existing products use the enabled shared product layout and collection cards. A new eligible product needs no per-product release allowlist entry. The held products retain native selectors and unapproved photo mappings remain blocked. Simple products retain their normal purchase flow. Variable products keep WooCommerce's actual attributes, variation matching, defaults, prices, stock, quantities and cart IDs. Numeric sizes stay numeric. Unsupported attributes keep their native controls.

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

The verified release catalogue has 19 published products and 253 variations. The applied migration checked 44 metadata rows and added 32 settings rows: 15 product rows and 17 term rows. Independent native readback matched the approved after-state. It retained 50 approved circles and seeded 50 representative previews only where the current variation set, size, term, attachment and source hash match the reviewed manifest. A future product with different size photos requires explicit representative review.

Bubble Dress (56), Rally Skort (160), Ribbed Tank (211) and Sculpt Leggings (238) remain held. Their mappings were not migrated; their native selectors remain available. Trashed Pleated Skort (148) and Aria Set (347) remain excluded. Court Skort's Lavender remains name-only pending its photo question. Shared presentation may be used on held products, but holds do not disappear through a normal editor save.

Migration records exact before/after metadata bytes. Existing overrides, including unexpected/duplicate metadata rows, are preserved. Intentional legacy omissions become Name only. Global defaults are seeded only for unambiguous, unanimous reviewed shades. The migration never enables the feature switches.

The production release used the guarded private operator and exact plan. For a future reviewed migration, the CLI-only helper is available after WordPress and the new model are loaded:

```text
wp eval-file tools/catalogue_settings_migration.php dry-run MANIFEST_PATH MANIFEST_SHA256
wp eval-file tools/catalogue_settings_migration.php apply PRIVATE_PLAN_PATH PLAN_SHA256
wp eval-file tools/catalogue_settings_migration.php rollback PRIVATE_PLAN_PATH PLAN_SHA256
```

Persist the dry-run JSON privately, inspect it and calculate its hash before apply. Execute during the single-writer window. All rows are checked before any write; InnoDB transactions and exact comparisons prevent partial rollback or overwriting later metadata edits. An uncertain commit must be reconciled from current row states before retrying. Cart activity and changing stock/prices must not be reverted by a visual rollback. No full database restore is used for routine rollback.

## What has been verified

- **Production installation and metadata:** native readback passed at 02:30:16 UTC. All eleven files match the merged source, all five switches are enabled, the 44-row plan matches its after-state, and the separate lossless setting remains unchanged. The before/after check found identical hashes for 272 product/variation rows covering content, URLs, attributes, explicit images, SKU and commerce fields. Orders and sessions were outside that capture.
- **Live merchant controls:** Courtline's Colours & photos panel displayed the migrated reviewed Jujube Red and White mappings, the native picker and photo-review controls. The global Colour editor displayed its default-circle picker. No production product save or term creation was used for this inspection. Isolated save/reopen tests prove persistence on the same editor/model bytes, including an unconfirmed save remaining unreviewed.
- **Live customer flows:** Courtline desktop/mobile selections, full photos, keyboard Enter, Reset and native fallback passed. Its existing generic size-guide fallback remains; a dedicated chart is not claimed (issue #74). Court Skort White/10 retained the 853×1280 photo, numeric sizes and illustrated guide. Elite Powder Blue previewed within a related card without navigation. Shop cards used four desktop/two mobile columns; homepage editorial imagery, copy and sizing link were preserved and stacked on mobile. Held Rally Skort retained native dropdowns. No JavaScript errors were recorded on the checked pages.
- **Live anonymous carts:** all 15 qualified products passed one reviewed variation sample, with native variation/image/price/cart identity confirmed and every test item removed. Court Skort's AJAX White/10 sample resolved variation 782. No orders or payments were created.
- **Earlier gallery and image monitors:** PR #105 passed 69 checks over 1,206.23 seconds. The resumed lossless batches for 36/50/83, 89/95/111 and 128/217/565 passed 90/153/216 checks over 1,206.46/1,210.32/1,212.26 seconds. The separate defaults monitor passed 119 checks over 1,220.32 seconds.
- **Recovery:** the complete encrypted off-server backup `fresh-20260915T015424Z` passed integrity checks and an actual contained restore. The restore verified 137 tables, 19 products and 253 variations with external networking, mail, payments and jobs blocked. A separate encrypted scoped backup binds the eleven prior file states, including six absent files, and the option before-state. Owned metadata before/after values are bound by the reviewed migration plan and durable intent; the full backup covers the database. Interrupted installation/reverse rollback and later-edit refusal were exercised locally; this release did not test rollback by reverting production.
- **Local integration:** the merged PR #105 integration passed 54 JavaScript tests against repository and restored WooCommerce/Blocksy engines, plus five PHP suites. The variable-product fixture preserved different size photos, sold-out controls, current prices/stock, stale-editor refusal and representative-image fallback. The server matrix covered all 19 products and 253 variations; local cart items were cleared.

The earlier experimental size-clear/reselection fixture was not reproduced by the recorded real local Flow and Courtline checks; no universal timing guarantee is claimed. The later observed Rally and aligned-photo deadline defects were addressed by PR #104/#105 and verified separately. Keep first selection, rapid re-selection, Reset, keyboard, mobile and native fallback in subsequent browser checks; details remain in [Gallery behaviour](GALLERY-STARTUP.md).

Four active parent-theme vendor dependency findings remain tracked separately; these checks do not claim overall security clearance. Private recovery receipts remain outside GitHub. Never publish keys, credentials, customer data or raw backups.

## Remaining verification

The defaults monitor completed 119 passing checks over 1,220.32 seconds, with the expected merged source, defaults option, unchanged lossless option and current identity bound to its receipt.

1. Perform the next-day check **September 15 after 14:00 UTC**, including first colour/size selection, Reset, image delivery, named fallbacks, representative previews and the held-product boundaries.
2. Reconcile issue #48 and the product checklist with that result. Keep Bubble Dress, Rally Skort, Ribbed Tank and Sculpt Leggings held; keep Court Skort Lavender name-only and Courtline's dedicated-chart question under issue #74.

The scoped rollback remains available: first disable the exact owned defaults option, reconcile uncertain metadata state, restore only matching owned metadata/files, and refuse later edits. Never restore an old database over current commerce activity. The initial product-160 URL guard correctly refused a later stored slug change; release evidence was refreshed to preserve `/product/the-rally-skort/` before any defaults write. The fresh full backup contains that new slug; the metadata plan remained byte-identical.

Final defaults monitor receipt: **PASS**, SHA-256 `3d5962fc835eb115c47b0c5035dddeba2920eea09545c57e47d056328cb675e0`.
Next-day receipt: **PENDING — September 15 after 14:00 UTC.**

The production installation, native readback, browser samples and cart samples are verified. Next-day verification and the recorded product/photo questions remain unfinished.


## Next-day follow-up completed

The bounded September 15 checks passed after 14:00 UTC. See [the next-day report](../releases/2026-09-15-catalogue-nextday.md) for current evidence, later catalogue changes and remaining holds. Earlier pending statements above describe the release-time checkpoint.

The verification CSV retains its September 12 catalogue, variation-count and photo-reference columns as a historical baseline. This follow-up updates status, monitoring and next control points only. Use the next-day report for observed later colour changes; do not use old CSV colours as instructions to restore product data.
