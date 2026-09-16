# Colour editor repair

Owner: current B Active colour-editor task. Severity: medium, customer-facing catalogue presentation and merchant editing. Parent record: issue #48. John requested correction for the Eyelet URL and Sculpt Leggings and prevention for future product editing.

## Confirmed causes

- The Eyelet URL resolves to product 95, currently titled The Elite Dress. Its new Off White term had no product-specific row. Final live readback also found no global shade saved for that term. Separately, the legacy palette omission branch suppresses new-term inheritance even when a global shade exists on an already migrated product.
- Sculpt Leggings 238 has newer colour assignments but remains in the historical hard-coded hold list. Its six currently listed colours have global shades; four Blue variations are outside the parent list. Preserve that exclusion until John chooses otherwise.
- The picker accepts a colour while the mode remains global, causing that entered colour to be ignored.
- WooCommerce saves variations by AJAX before Update. The editor treats those changes as a competing settings edit and discards valid submitted colour settings.

## Acceptance

- New terms on migrated products inherit globals; explicit Name only and custom overrides remain authoritative.
- Editing a picker selects custom mode and updates the status; empty, held and name-only states explain the next action.
- Changes to variation photos preserve entered settings but withhold new photo approval until the current mapping is reviewed.
- Competing owned-settings edits remain rejected. New and removed attribute terms reconcile without losing existing rows or recreating removed ones.
- Product 95 and 238 live circles and variation selection independently verified; editor save/reopen qualified in the contained native WooCommerce clone without taking over a merchant session.
- Preserve prices, stock, product/variation IDs, URLs, image assignments and unrelated product holds.

## Verification and delivery

Candidate is in isolated branch `codex/catalogue-editor-repair-20260915`, based on `4377e80f`. Production source hashes of the three touched files match that baseline. Source review identified and corrected first-product AJAX and omitted-row preservation cases. Local PHP tests cover inheritance, mapping review, permissions, conflicts and reconciliation; a DOM test covers picker mode and status.

Local verification passed: 55 JavaScript tests, five PHP contract suites, 19 Python tests, mirror/syntax checks and independent read-only review. A network-disabled, transaction-rolled-back WooCommerce clone also passed first-attribute save, real variation save, shade persistence after reopen and stale-settings rejection. The clone emitted its pre-existing duplicate ABSPATH warning, with no failed assertion.

## Live release

PR #108 merged as `03a3d33ac539f6a8b41d7f3e0de3c05395de90a1`; exact reviewed source `982e055f8e0792476b36f8ce807abe1cbd4e22a1`. PR and main CI passed. Three source files were backed up, hash-compared before installation, installed through same-directory renames and independently hash-verified. Both full product/variation snapshots were unchanged across the source deployment.

Final readback found Off White's global shade empty. After the active edit lock expired, a conditional metadata update added only `pa_colour:115` to product 95 with custom shade `#f4f3ef`, reusing its existing white dress shade and preview 451. The four Off White variations were checked against that same image before the write. The new row remains unreviewed; no photo approval was invented. All previous settings were preserved. The command returned non-JSON after cache invalidation; an independent fresh WP read verified the exact after-state, so it was not retried.

Browser readback verified all three Eyelet circles and all six currently offered Sculpt circles. Eyelet Off White/M and Sculpt Fuchsia/S showed matching photos and enabled Add to cart. Anonymous HTTP requests returned 200 for both. Read-only Woo verification found a shade and purchasable mapped variations for every listed colour. No order was placed.

Merchant edits continued during verification: Sculpt Gray/S was removed and an additional non-purchasable Purple/L row appeared. These changes were preserved, not treated as permission to recreate/delete variations. Blue remains excluded from the parent colour list. Other product holds remain unchanged. We did not take over a merchant's editor; live save/reopen was not claimed.

Private receipt directory: `BactivePH/editor-repair-20260915` under the owner's Application Support. It contains the three original files, before/after hashes, protected product snapshot hashes, the single-row metadata before/after receipt and fresh live readback. Conditional rollback restores only matching release files and, if needed, the exact product-95 metadata value using its saved after-state as the comparison. Refuse rollback on a newer merchant edit; never restore commerce tables.

## Editor instructions

Reload an editor opened before this deployment. In Product data → Colours & photos, leave Use global shade for existing defaults, or choose a shade with Select Color (now automatically selects Custom shade for this product), then Update. Name only intentionally suppresses a circle. For a new colour with no global default, choose a custom shade or configure its global colour default. Size-specific images remain in Variations. Photo review is a separate step: after changing sizes/photos, reopen the panel, inspect the current photos, tick the confirmation and Update.
