# Product acceptance record

Copy once per product during execution. A missing result is NOT_TESTED, never PASS. Keep customer data, credentials and raw orders out of this record.

## Identity and decisions

- Product name, parent ID, URL and type:
- Current snapshot time and source:
- Variation IDs, SKU/history dependencies and stock-management model:
- Reference sheet and approved colour registry version:
- Known exception(s) and exact approved resolution:
- Owner of unresolved facts:
- Explicitly omitted unsupported views and reason:
- Original asset/attachment manifest and proposed replacement manifest:

## Before-change control

- Production/staging identity verified:
- Exact source commit/PR/review:
- Fresh per-product snapshot, expected values and timestamp:
- Complete off-server backup ID and verified restore receipt:
- Product rollback rehearsal and operator instructions:
- Newer-order/stock/price preservation test result:
- Active writer/release window:
- Scope of allowlisted field changes:

## Imagery and colour review

For every offered colour list: term ID/name, approved swatch, primary image, supporting images, existing/new attachment ID, source hash and independent review result.

- Silhouette, fit presentation, neckline, straps/crossings, band, seams, pleats and hem:
- Supported front/back/side details; no invented pockets/lining/weave:
- Source versus output, all colourways together, skin/background boundaries:
- Shade approval or product-specific override:
- Image order, responsive dimensions, image labels/alt text and included styling pieces:
- Review result, reviewer, evidence links and correction history:

## Exhaustive staging selection matrix

Attach one row per defined variation and meaningful negative combination. Include:

- Colour then size; size then colour; absent/sold-out/disabled/missing-price states.
- Defaults, clearing/reset, invalidated previous size, quantity and direct attribute links.
- Client selected values versus authoritative server variation ID.
- Correct gallery, price, availability and accessible status/focus.
- Every valid distinct variation added to a test cart; ID/colour/size/image/price/quantity confirmed; remove/re-add and refresh checked.
- Cart checks do not place real orders or initiate production payments.
- Fully sold-out and simple/single-colour behavior where applicable.
- Failed image load, script failure/native fallback, gallery/lightbox and navigation restoration.

Record total expected combinations, total tested, passing, failing, intentionally absent and not tested separately.

## Visual and technical checks

- Desktop 1440 and mobile 390 screenshots, plus relevant 320/768 edge cases:
- Correct image proportions and crops; no overflow; long colour names:
- Keyboard, focus, screen-reader labels/status, contrast and 44px targets:
- Image/derivative load checks, page weight/timing baseline and result:
- Price, add-to-cart, size guide, details/care and related/search/card surfaces:
- Independent review findings and confirmation pass:

## Production acceptance

- Release batch ID/time and applied exact field/asset manifest:
- Authenticated readback of each intended field/mapping:
- Anonymous normal URLs and actual responsive assets/cache state:
- Browser check of every offered colour and distinct selection behavior:
- Live cart sample: exact cases tested and rationale (do not call it exhaustive):
- Protected stock/order/price/SKU/ID comparison and legitimate concurrent activity:
- No new critical logs/errors in observation interval:
- 20-minute batch observation evidence; next-day follow-up result:
- Product/batch rollback still available and original media retained:

## Final disposition

- Status: NOT_STARTED / NEEDS_REFERENCE / IN_REVIEW / HELD / PASSED_STAGING / PASSED_LIVE / COMPLETE.
- Required gates passed:
- Missing evidence or exact remaining blocker:
- Accountable owner and next control point:
- Final independent reviewer and timestamp:

COMPLETE requires all applicable gates and live follow-up evidence. Do not transfer approval from one product to another or equate a local preview with production acceptance.
