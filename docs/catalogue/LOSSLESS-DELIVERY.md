# Lossless catalogue image delivery

Status at the September 15 release checkpoint: lossless delivery is active for **117, 36, 50, 83, 89, 95, 111, 128, 217 and 565**. All three resumed image batches passed their 20-minute monitors. Products **154, 185, 573, 660 and 677** retain full-resolution JPEG originals. The four held products remain outside the approved lossless mapping; shared layout does not clear their catalogue holds.

PR #100 deployed the four initial source files, related-card previews and 22 immutable derivatives. PR #101, merged at `8461d183`, subsequently deployed automatic defaults and the merchant editor with the integrated PR #105 gallery correction. Native readback, sampled live browser flows and all 15 qualified cart samples passed. The separate defaults monitor **PASSED 119 checks over 1,220.32 seconds**. Next-day verification remains **PENDING for September 15 after 14:00 UTC**. See the [defaults release record](../releases/2026-09-15-catalogue-defaults.md).

## Exact image delivery

The separate `bactive_catalog_lossless_release` option defaults off when absent. It maps exact attachment IDs and approved products to source-SHA filenames in `uploads/bactive-lossless`. Both source and derivative hashes and dimensions must match. Missing, changed, unreviewed or out-of-scope images fall back to the original. Original attachments and product image assignments are retained. The defaults image-quality switch is separate from this reviewed lossless mapping.

Production requires HTTPS. HTTP is accepted only when WordPress explicitly declares its environment local and the upload hostname is exactly loopback. No public endpoint writes this option.

The approved mapping contains 25 attachment rows referencing 22 unique lossless WebP files. They replace 39,465,296 PNG bytes with 21,636,468 WebP bytes, a 45.18% reduction in those assets. Decoded pixels, dimensions and ICC profiles match. This asset reduction is not a measured Core Web Vitals or page-speed result. JPEG-only products received no no-op lossless activation.

All 25 isolated mappings resolved and all 181 variations belonging to the 15 qualified products preserved their non-image WooCommerce payloads. Local first-resolution measurements ranged from 27 to 149 ms per mapped product; these are local filesystem measurements, not production latency.

## Production verification

| Release | Completed evidence |
|---|---|
| Courtline 117 initial canary | Mapped White variation 121/image 462 cart check and Red desktop/mobile checks; 27 monitor checks over 1,206.06 seconds. |
| PR #105 gallery correction | Live checks and 69 monitor checks over 1,206.23 seconds. |
| Resumed 36 / 50 / 83 image batch | Native image/cart and browser checks; 90 monitor checks over 1,206.46 seconds. |
| 89 / 95 / 111 image batch | Native image/cart and browser checks; 153 monitor checks over 1,210.32 seconds. |
| 128 / 217 / 565 image batch | Native image/cart and browser checks; 216 monitor checks over 1,212.26 seconds. |
| PR #101 defaults on the final ten-product mapping | Independent native readback and 15 anonymous cart samples passed with cleanup; sampled live browser checks passed. Its own 20-minute monitor passed 119 checks over 1,220.32 seconds. |

The current native lossless option wrapper SHA-256 is `e91fa08421f0d28b82fa7564bbba2e49c4055cdd5f51b74635fa737c4462cfdb`. This hashes the exact stored-option wrapper, not the JSON configuration file. The final option remained unchanged through the defaults release.

The earlier 36/50/83 attempt was rolled back to Courtline 117 after Rally Beige/M showed Navy while WooCommerce resolved variation 53 correctly. Its interrupted monitor remains a non-pass. PR #104 corrected native task/render ordering and settled photo position; PR #105 addressed an already aligned photo whose native movement flag remained set. The fresh resumed batches above supply the later qualification. Earlier HTTP passes are not used to claim that those interaction defects never occurred. See [Gallery behaviour](GALLERY-STARTUP.md) for the exact scope and timing limits.

## Related-card colour previews

Colour clicks with a valid reviewed representative preview that photo in the related card. Missing, unreviewed or ambiguous mappings retain their ordinary product link. Photo/title/Select options destinations, native variation images and cart identity remain unchanged. Approved sold-out photos can still be shown as previews; purchase availability continues to come from WooCommerce.

The script preloads before replacement, discards stale rapid-click results, supports Space/Enter and preserves modified-click navigation. Failed loading retains the old photo and restores the colour link. A selected preview suppresses the theme hover image. Related cards use two columns on mobile and retain sage focus/visited labels.

The live PR #101 browser check confirmed Elite Powder Blue previewed in place without changing the main page or product links. Shop used four desktop/two mobile columns without horizontal overflow in the checked mobile viewport. Court Skort Lavender remains name-only pending confirmation of its pink-looking assigned photo. New product overrides and explicit representative review are described in [the merchant guide](CATALOGUE-DEFAULTS.md).

## Recovery and remaining work

The final ten-product baseline is covered by full encrypted backup `fresh-20260915T015424Z` and its actual contained restore. The defaults release has a separate encrypted scoped backup. Their bindings and preservation checks are recorded in the [release record](../releases/2026-09-15-catalogue-defaults.md).

A lossless-only rollback must compare the exact current option wrapper before disabling its owned mapping. Preserve originals and immutable derivatives. Any runtime-file rollback must use the current release's file hashes and reject later edits; an older image journal is not authority to replace newer defaults files. Never restore an old database over current commerce activity.

For contained regression only, `php tests/catalog-lossless.php /isolated/site/wp-load.php` compares native variation payloads with the reviewed mapping. Its temporary option filter is process-local; it does not write stock, orders or prices.

Defaults monitor receipt: **PASS**, SHA-256 `3d5962fc835eb115c47b0c5035dddeba2920eea09545c57e47d056328cb675e0`.
Next-day verification: **PENDING — September 15 after 14:00 UTC.**
The four held catalogue mappings, Court Skort Lavender and the existing Courtline dedicated-size-chart question remain separately unresolved.
