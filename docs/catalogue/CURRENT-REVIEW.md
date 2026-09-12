# Current catalogue review: 12 September 2026

The authenticated snapshot contains 19 published variable products, 253 variations and 96 unique gallery/variation attachments. Every attachment was individually inspected. This records source-image review, not completed UI, cart or production verification.

`CURRENT-IMAGE-MANIFEST.json` records every variation mapping and the ordered galleries. Source attachment IDs and SHA-256 hashes identify private originals. Supplier-bearing source filenames, URLs, SKUs and local credential paths are deliberately absent. No image assignments were changed. Re-read the live catalogue immediately before release; this snapshot must not overwrite later edits.

`REVIEWED-REGISTRY.json` matches the existing selector schema. Global and every product enhancement switch are **false**. The 15 qualified source-review entries have `reviewed: true`; all four held entries have `reviewed: false` and empty palettes. Shared card previews may consume reviewed palettes independently of enhancement switches. Its approved palette entries mean reviewed visual approximations under the user's existing AI-image approval, not deployment approval or physical fabric calibration. Never enable the entire file blindly: release only individually qualified products. Term ID and slug must still match live WooCommerce.

Courtline Dress (117), then Flow Skort (154), remain the canaries. Their current originals need no regeneration. White/Jujube trim differs on Courtline; white/black piping differs across Flow colours. Preserve these details. The other 13 non-held products can proceed to UI testing using current originals. Use contain framing for diptychs and close crops; do not invent missing front/back views, footwear or texture detail.

## Holds and colour exceptions

- Bubble Dress (56): duplicate Black XL, missing-colour M80 with visibly incorrect pleated image61, and unlisted Onyx82. Preserve records and image mappings.
- Rally Skort (160): unlisted Onyx177–180 and Court Ivory181; Court Ivory181 uses black image164. Preserve mappings.
- Sculpt Leggings (238): Almond and Stone share dusty-pink image602. No shade is approved or inferred for either label.
- Ribbed Tank (211): XL646 has no colour; image596 does not authorize inferring its colour. Preserve mappings.
- Court Skort (185): nine colours / 37 actual variations with numeric sizes. Lavender750 appears bright pink. Root inspected it and selected **text fallback**; the runtime palette omits that entry. Do not rename it or recolour it. Its other eight shades are product-specific approximations.
- Bloom and Sakura Pink remain distinct. Bloom has no active variation in this snapshot; no shared image or swatch is assigned to it.
- Purple differs materially between Romper693 and Strappy711. Powder Blue and Sakura also vary by product. Always consume product-specific palettes; never collapse them into a global colour-by-name map.
- Pleated Skort148 and Aria Set347 remain excluded while trashed.

## Evidence and next control point

The checklist has one row per published product; the image manifest has 253 uniquely owned variation records and 96 attachment hashes. Product/variation identities and current images are preserved, and no SKU, price or inventory changes are proposed. No generation gap was identified that blocks current UI qualification. Any later generated asset needs its own source/fidelity review and neutral public filename.

Backup and restore qualification belong to the root release record. This independent source review does not re-certify them. UI/cart matrix, mobile/desktop, accessibility, performance and all production gates remain untested here. Refresh evidence, complete those gates, and only then enable the qualified batch.
