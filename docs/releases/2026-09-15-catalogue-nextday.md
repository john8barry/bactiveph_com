# Catalogue next-day verification — September 15, 2026

The bounded next-day release checks passed after 14:00 UTC. This confirms the released presentation remains available; it does not clear unresolved catalogue mappings or replace the original full variation/cart audit. Runtime release is PR #101, merge `8461d1832749047f39078a9b8ed4dced47adb40a`.

## Current evidence

- All 19 ordinary product URLs, homepage and shop returned successfully. Qualified configuration version is `catalogue-defaults-20260915`; four held products retain native controls.
- Six public CSS/JS assets match the reviewed source. One full-source image per product was downloaded and decoded successfully (19 samples).
- Browser samples passed Courtline first selection, mobile keyboard selection, native fallback and related Flow Lavender image preview without navigation; Court Skort White/10, numeric sizing and illustrated guide; reset clears both attributes and restores WooCommerce's disabled selection-needed guard.
- Shop renders four desktop columns and two mobile columns without horizontal overflow. The homepage editorial image loads when scrolled into view.
- No production data, carts, orders or payments were changed. Existing backup/restore and guarded rollback evidence remains recorded in the release report.

## Remaining catalogue work

| Product | Current next control point |
|---|---|
| Bubble Dress 56 | Enhancement hold remains; resolve recorded ambiguous variations before requalification. |
| Rally Skort 160 | Enhancement hold remains. Current default is L/Black; preserve the later edit and review current mappings. |
| Ribbed Tank 211 | Enhancement hold remains; review the recorded missing-colour variation. |
| Sculpt Leggings 238 | Enhancement hold remains. Now lists Black, Blue, Blush Pink, Fuchsia, Gray, Lime Green and Purple. Old Almond/Stone conflict is historical; review the new mapping rather than restore old data. |
| Court Skort 185 | Lavender remains name-only pending shade/photo review. |
| Courtline Dress 117 | Dedicated size-chart question remains #74; generic guidance is available. |
| Warm-Up Jacket 573 | Magenta has a colour circle but no reviewed preview; review its mapping before enabling that preview. Other qualified enhancements remain live. |

Elite Dress 95 no longer lists Pure White. Current public counts are 49 circles and 49 previews, compared with 50 at release. These later catalogue changes were preserved; GET evidence cannot establish their author or intent. Issue #48 remains open for unfinished catalogue work.

## Evidence boundaries

HTTP does not independently prove current deployed PHP bytes or database editor settings. Those native checks and editor persistence tests are earlier release evidence. Browser checks are samples, not all colour/size combinations; image decoding does not certify every garment's colour or detail. No new cart audit was performed.

Private sanitized receipt hashes (SHA256):

- HTTP: `2f7d6c53dbd891b16a1573122d87a31da1ad7ad64199e7628ec3444fbd2455e8`
- Catalogue drift: `db5feb79dd2077b1e05e8a6958758dfdb7605dafa62ab4f219b334819d8da8ab`
- Browser: `973ecea5a0d3cc9c932956bbb46b4370069905b378bd30bbf5594722ab6381fa`

Earlier release counts and acceptance evidence remain historical snapshots. New production repairs require a fresh target/writer preflight; this follow-up made no production changes.

The verification CSV retains its September 12 catalogue, variation-count and photo-reference columns as a historical baseline. This follow-up updates status, monitoring and next control points only. Use the next-day report for observed later colour changes; do not use old CSV colours as instructions to restore product data.
