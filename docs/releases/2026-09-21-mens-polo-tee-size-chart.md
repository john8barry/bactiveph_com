# Men's Polo / Tee illustrated size chart

Issue [#74](https://github.com/john8barry/bactiveph_com/issues/74). Owner: John Barry / B Active. Medium storefront guidance improvement: the two men's Everyday Active tops need the supplied original sizing chart. Implementation and local checks are complete; production release and independent live verification are pending.

## Exact product associations

The coordinator confirmed these published men's products from the current catalogue and product photos. Both share the single `mens-polo-tee` chart and chooser entry.

| Product | ID | Exact slug |
| --- | --- | --- |
| Everyday Active Tee | 1079 | `everyday-active-tee` |
| Everyday Active Polo | 1117 | `every-active-polo` |

The similarly named women's Match Polo (`the-match-polo`, 565) and Everyday Tee (`the-everyday-tee`, 660) are excluded. Categories, similar names, selected variations and query-string product overrides do not assign a chart.

## Source and behavior

Original file: `photo_2026-09-21 17.09.44.jpeg`, 853×1280 pixels, 102,859 bytes. Its unchanged bytes are stored as `assets/images/size-guides/mens-polo-tee-illustrated-20260921.jpg` in the child theme. SHA-256: `6abaa6fe70448b00c60110129f6f20a966aa465ffe28572c1fc96e86f84c2c92`.

Each matching dialog and `/size-guide/?chart=mens-polo-tee#mens-polo-tee-size-chart` displays one original image and a full-size link. The hidden accessible description preserves all 20 values for S/M/L/XL/XXL, the original Bust/Shoulder Width/Sleeve Length/Cuff labels, measuring instructions, tolerance and sizing-up advice. No visible duplicate table or inferred size conversion is added.

Registry entries now hold arrays of explicit product slugs; the seven previous associations and chart assets retain their existing content. Both PHP mirrors match. CSS, JavaScript, catalogue records, prices, inventory, variations and commerce behavior are unchanged.

## Verification and release control

All 23 focused size-guide tests pass, including both men's routes, every measurement, source hashes, retained charts, a single shared chooser entry, exact fallback URLs, negative/lookalike products, invalid/nonscalar queries and variation-query independence. Both PHP lints, mirror comparison, JavaScript syntax and diff whitespace checks pass. These local checks do not establish a live release.

The coordinator must record the reviewed commit/PR, qualified backup and fresh scoped PHP preimage, then install the image before applying only this size-guide delta to fresh production PHP. Verify current target identities, destination hashes and exclusive writer ownership immediately before replacement. Preserve unrelated live changes. Acceptance requires authenticated file readback, anonymous checks across the current catalogue and fallback pages, desktop/mobile dialog and enlargement checks, protected-file checks and bounded critical-error monitoring. Record live results here and retain issue #74 for remaining chart inputs.

## Rollback

Restore the scoped PHP preimage only while its live hash matches this release; otherwise reverse only this size-guide delta in the latest file. Remove the new image only after verifying no live reference remains and its bytes still match this release. Do not restore the database or modify commerce data.
