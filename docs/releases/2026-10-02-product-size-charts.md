# October product size-chart update

Work record: issue [#74](https://github.com/john8barry/bactiveph_com/issues/74). Owner: John Barry / B Active. Medium sizing-guidance correction. The supplied Men's T-Shirt chart has different measurements from the shared polo chart previously shown on Everyday Active Tee; Essential Workout Shorts also needs its supplied chart. This change prepares three confirmed men's associations. The two women's chart associations and production verification remain pending.

## Confirmed products and original sources

Current published catalogue readback identifies the three men's products below. Each selected original is 853×1280 pixels and is copied without editing or recompression into the child theme's `assets/images/size-guides/` directory.

| Product | ID | Exact slug | Chart key | Supplied file ending | SHA-256 |
| --- | --- | --- | --- | --- | --- |
| Everyday Active Tee | 1079 | `everyday-active-tee` | `mens-tee` | `04.03.22.jpeg` | `a798443704d5adf34377a468ad802cbd717c1f54914ccb5c72429c6ed72be12c` |
| Everyday Active Polo | 1117 | `every-active-polo` | `mens-polo-tee` | `04.03.27.jpeg` | `a873c0e9161028a868126d8189ea179c5435abfe38ead75f80482a4c5cd06d7a` |
| Essential Workout Shorts | 1133 | `essential-workout-shorts` | `mens-shorts` | `04.03.21.jpeg` | `d67e65357474a15b6ecd7dcb21ddd18e54e350b6239c6e9dd927e2f22d3dd563` |

Source filenames begin `photo_2026-10-02 `; shipped assets use `<chart-key>-illustrated-20261002.jpg`. The new polo file preserves the prior chart's measurements but is not byte-identical to the September 21 JPEG. Its existing `mens-polo-tee` fallback URL remains usable and now belongs only to the polo. The chooser and heading say Men's Polo; the original image and its alternative description retain the supplied Polo / Tee wording. The old JPEG remains intact for scoped recovery. No men's-bottoms chart or asset exists in the inspected starting source at `bcded33021c0153ebcbc8e8a999fdb7dff411233`; current live readback must be reconciled before deployment.

Everyday Active Tee now gets its own Length/Bust/Hem Width/Sleeve Length chart, including size S values `68, 96, 96, 22.5`. The polo retains its Bust/Shoulder Width/Sleeve Length/Cuff chart, including size S values `98, 43, 20.5, 34`. Shorts retain the source's Length/Waist/Hip/Leg Opening values and its “one side, laid flat” leg-opening instruction without doubling or recalculating values.

Each exact product and selected fallback displays one original image, a full-size link and the complete hidden text alternative. Existing charts, invalid-query behavior and contact guidance for unmatched products remain intact. CSS, JavaScript, catalogue records, purchasable size options, inventory, prices, orders and payments are outside this change.

## Pending women's charts

Neither supplied women's product name appeared in the current 27-product public catalogue. Confirm an existing product ID and slug from authenticated catalogue evidence or John before associating either chart. Do not map them to a similar title, add an unmatched public chooser entry or create a product from the image alone.

| Supplied chart | File ending | SHA-256 | Status |
| --- | --- | --- | --- |
| Clubhouse Zip Polo | `04.03.24.jpeg` | `adb1c2d8fa1985633ba2a2c41bbd256a0ba4a122b58ff784628c3e325fd599bc` | Original and all 30 measurements transcribed privately; no product association |
| Banded Waist Zip Polo | `04.03.25.jpeg` | `cf54503a521ad8f1a736810513104b71a45aaf088124961dcdbeefca0fc79c41` | Original and all 18 measurements transcribed privately; no product association |

Their source sizes S/M/L/XL/2XL/3XL, labels, measuring instructions and tolerance notes must remain unchanged. Completion of the men's work does not complete these two pending associations.

## Verification, release and rollback

All 25 focused size-guide tests pass after the final heading change. They cover original-image hashes, exact measurement transcription, the tee/polo split, preserved polo fallback URL, shorts routing, exclusion of unrelated/lookalike products, one chart per selected page, chooser links, invalid/nonscalar queries and unchanged dialog behavior. Both PHP mirrors lint and match; JavaScript syntax and diff whitespace checks pass. The writer verified that source outside the sizing block, CSS and JavaScript remain unchanged.

The coordinator's local browser checks at 1280×720 and 390×844 confirmed that all three men's images fit and that Escape/close return focus to the trigger. The final Men's Polo heading was also rechecked on desktop after the label change. These are local checks, not proof of a production installation; authenticated live preflight and production verification remain pending.

Before release, confirm the exact production target and current deployed files, authenticated product identities, current upstream checks, scoped recovery preimages and exclusive writer ownership. Stage images before applying only the reviewed sizing delta to fresh live PHP, with destination hashes rechecked immediately before replacement. Verify all currently published product routes, fallback pages and source-image hashes independently, review mobile/desktop display and dialog behavior, and monitor for new critical errors.

Rollback restores only the guarded PHP preimage while live bytes match this release; otherwise reverse only the sizing delta in a fresh readback. Remove a new image only after its hash and absence of live references are verified. Preserve unrelated live changes and all commerce data. No database rollback is part of this change. Keep issue #74 open for pending chart associations and missing inputs.
