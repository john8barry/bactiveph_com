# Lossless catalogue image delivery

Status: isolated qualification; not deployed. The existing live original-image fix remains active.

The optional `bactive_catalog_lossless_release` option defaults off. It maps exact attachment IDs and approved products to source-SHA filenames in `uploads/bactive-lossless`. Both the source and derivative hashes and dimensions must match. Missing, changed, unreviewed or out-of-scope images fall back to the original. Original attachments and product image assignments are retained.

Production requires HTTPS. HTTP is accepted only when WordPress explicitly declares its environment local and the upload hostname is exactly loopback. No public endpoint writes this option.

The reviewed candidate contains 25 attachment rows referencing 22 unique lossless WebP files. These replace 39,465,296 PNG bytes with 21,636,468 WebP bytes, a 45.18% reduction in those assets. Decoded pixels, dimensions and ICC profiles match. Ten qualified products have PNG derivatives; the other five keep JPEG originals. Four held products remain excluded.

Isolated verification: all 25 mappings resolve and all 181 variations belonging to the 15 qualified products preserve their non-image WooCommerce payloads. Local first-resolution measurements ranged from 27 to 149 ms per product with mapped assets; these are local filesystem measurements, not production latency or Core Web Vitals. PHP contracts and nine native selector tests pass.

Pending: completion of enabled derivative browser checks, production backup and serialized release, production performance readback and monitoring. Do not enable production until these gates pass.

Run `php tests/catalog-lossless.php /isolated/site/wp-load.php` only against the contained local clone with its reviewed option enabled. The check temporarily filters the option within its own PHP process and compares native variation payloads; it does not write the option, stock, orders or prices.

Rollback must disable only the exact expected lossless option and restore only the expected candidate file hash. Stop on unexpected later edits. Leave originals and immutable derivative files intact; never restore an old database over new orders. The isolated option disable/restore, original-image fallback, unexpected-option refusal, and file restore/reapply rehearsal passed. Production backup and rollback binding remain pending.


## Related-card colour previews (local)

User-requested colour clicks now preview the existing image within related cards. Photo/title/Select options links retain their destinations. Only published variations with exact parent/colour and one explicit shared attachment qualify; missing, wildcard or conflicting images keep their links. Sold-out images remain eligible. Warm-Up Jacket Magenta currently has conflicting attachment IDs, so retains navigation.

The script preloads before replacing the image, prevents stale rapid-click results, supports keyboard Space/Enter and preserves modified-click navigation. Failed loading keeps the old image and restores the colour link. A selected preview suppresses the theme hover image. Related cards are visible in the approved two-column mobile layout; focused/visited colour labels retain sage styling.

Local browser evidence: Court Black/White previews on Flow stayed on Flow; Space selected White. On mobile390px, Strappy Purple preview stayed on Court, loaded its682px image, retained sage labels and showed two163.594px columns with no overflow. Three new JavaScript interaction/error/race checks and PHP ambiguity guards pass. Local files were backed up before copying. These changes are not live. Court Lavender remains pending confirmation of its pink/lilac assigned photo; no palette or catalogue records changed.

Native in-page navigation restored browser testing. Court Dress galleries load the1122px lossless files and retain them after colour selection/reset. Remaining lossless zoom and browser coverage is still pending.
