# Colour editor repair

Owner: current B Active colour-editor task. Severity: medium, customer-facing catalogue presentation and merchant editing. Parent record: issue #48. John requested correction for the Eyelet URL and Sculpt Leggings and prevention for future product editing.

## Confirmed causes

- The Eyelet URL resolves to product 95, currently titled The Elite Dress. Its new Off White term has a valid global shade but no product-specific row. The legacy palette omission branch suppresses that shade even though the product has already migrated to native settings.
- Sculpt Leggings 238 has newer colour assignments but remains in the historical hard-coded hold list. Its six currently listed colours have global shades; four Blue variations are outside the parent list. Preserve that exclusion until John chooses otherwise.
- The picker accepts a colour while the mode remains global, causing that entered colour to be ignored.
- WooCommerce saves variations by AJAX before Update. The editor treats those changes as a competing settings edit and discards valid submitted colour settings.

## Acceptance

- New terms on migrated products inherit globals; explicit Name only and custom overrides remain authoritative.
- Editing a picker selects custom mode and updates the status; empty, held and name-only states explain the next action.
- Changes to variation photos preserve entered settings but withhold new photo approval until the current mapping is reviewed.
- Competing owned-settings edits remain rejected. New and removed attribute terms reconcile without losing existing rows or recreating removed ones.
- Product 95 and 238 live circles, variation selection and editor save/reopen independently verified before completion.
- Preserve prices, stock, product/variation IDs, URLs, image assignments and unrelated product holds.

## Verification and delivery

Candidate is in isolated branch `codex/catalogue-editor-repair-20260915`, based on `4377e80f`. Production source hashes of the three touched files match that baseline. Source review identified and corrected first-product AJAX and omitted-row preservation cases. Local PHP tests cover inheritance, mapping review, permissions, conflicts and reconciliation; a DOM test covers picker mode and status.

Local verification passed: 55 JavaScript tests, five PHP contract suites, 19 Python tests, mirror/syntax checks and independent read-only review. A network-disabled, transaction-rolled-back WooCommerce clone also passed first-attribute save, real variation save, shade persistence after reopen and stale-settings rejection. The clone emitted its pre-existing duplicate ABSPATH warning, with no failed assertion.

Deployment and Sculpt release remain pending. Production uses a backup and conditional hash checks for exactly three source files. No product metadata writes are necessary. Rollback restores only these files when their current hashes still match this release; never restore commerce data or overwrite a later merchant edit. Live editor save testing must not take over Marnie's active product edit; save/reopen is qualified in the isolated native clone instead.
