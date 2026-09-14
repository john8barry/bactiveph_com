# Lossless catalogue image delivery

Status: PR #100's four source files and 22 immutable derivative assets are deployed. Related-card previews are live. Lossless delivery is enabled only for Courtline Dress (117); the other products retain their current original-image delivery until their own qualified activation.

The optional `bactive_catalog_lossless_release` option defaults off. It maps exact attachment IDs and approved products to source-SHA filenames in `uploads/bactive-lossless`. Both the source and derivative hashes and dimensions must match. Missing, changed, unreviewed or out-of-scope images fall back to the original. Original attachments and product image assignments are retained.

Production requires HTTPS. HTTP is accepted only when WordPress explicitly declares its environment local and the upload hostname is exactly loopback. No public endpoint writes this option.

The reviewed candidate contains 25 attachment rows referencing 22 unique lossless WebP files. These replace 39,465,296 PNG bytes with 21,636,468 WebP bytes, a 45.18% reduction in those assets. Decoded pixels, dimensions and ICC profiles match. Ten qualified products have PNG derivatives; the other five keep JPEG originals. Four held products remain excluded.

Isolated verification: all 25 mappings resolve and all 181 variations belonging to the 15 qualified products preserve their non-image WooCommerce payloads. Local first-resolution measurements ranged from 27 to 149 ms per product with mapped assets; these are local filesystem measurements, not production latency or Core Web Vitals. PHP contracts and nine native selector tests pass.

Courtline's mapped White variation 121 passed the anonymous cart check with image 462; desktop/mobile checks also verified Red. Its 20-minute canary monitor passed 27 checks over 1,206.06 seconds. Next-day verification remains pending. These checks qualify that canary, not every product or overall Core Web Vitals.

PR #102's gallery source `eb961fa` is live. PR #103 is merged at `eb8e80a0d289b8b267d53591ee57cb253361ae54`, with candidate source SHA-256 `3be7d856278ca5e4380587c7e2aa0ad26296e241126cff0e620409771285ceb4`, but is not deployed at this checkpoint. Local desktop/mobile qualification passed after native browser navigation recovered; action-time writer readback and independent deployment verification remain separate gates. Hold further lossless activation until the gallery correction has independent production verification.

Then qualify the remaining mapped products (36, 50, 83, 89, 95, 111, 128, 217 and 565) in batches of at most three, with fresh guarded release and monitoring evidence. JPEG-only products 154, 185, 573, 660 and 677 retain their full originals; do not create no-op lossless activations for them. PR #101's automatic defaults and colour editor remain draft work and still need native editor UI proof.

Run `php tests/catalog-lossless.php /isolated/site/wp-load.php` only against the contained local clone with its reviewed option enabled. The check temporarily filters the option within its own PHP process and compares native variation payloads; it does not write the option, stock, orders or prices.

Rollback must disable only the exact expected lossless option and restore only the expected candidate file hash. Stop on unexpected later edits. Leave originals and immutable derivative files intact; never restore an old database over new orders. The isolated option disable/restore, original-image fallback, unexpected-option refusal, and file restore/reapply rehearsal passed. Each later activation needs a fresh backup and rollback binding; preserve the existing release journals and recovery metadata.


## Related-card colour previews

User-requested colour clicks now preview the existing image within related cards. Photo/title/Select options links retain their destinations. Only published variations with exact parent/colour and one explicit shared attachment qualify; missing, wildcard or conflicting images keep their links. Sold-out images remain eligible. Warm-Up Jacket Magenta currently has conflicting attachment IDs, so retains navigation.

The script preloads before replacing the image, prevents stale rapid-click results, supports keyboard Space/Enter and preserves modified-click navigation. Failed loading keeps the old image and restores the colour link. A selected preview suppresses the theme hover image. Related cards are visible in the approved two-column mobile layout; focused/visited colour labels retain sage styling.

Related previews were deployed with PR #100. Earlier local browser evidence: Court Black/White previews on Flow stayed on Flow; Space selected White. On mobile390px, Strappy Purple preview stayed on Court, loaded its682px image, retained sage labels and showed two163.594px columns with no overflow. Three JavaScript interaction/error/race checks and PHP ambiguity guards passed. Court Lavender remains pending confirmation of its pink/lilac assigned photo; no palette or catalogue records changed.

Earlier local Court Dress checks loaded the1122px lossless files and retained them after colour selection/reset. That local result does not imply production activation for Court Dress or the remaining mapped products.
