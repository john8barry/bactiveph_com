# Product photo release and remaining asset work

Work item: [#115](https://github.com/john8barry/bactiveph_com/issues/115). Owner: B Active product-photo task; merchant decisions and replacement originals belong to John. Severity: medium storefront image presentation and future catalogue-input integrity.

## Delivered

PRs [#118](https://github.com/john8barry/bactiveph_com/pull/118) and [#119](https://github.com/john8barry/bactiveph_com/pull/119) merged. Seven child-theme files from `f218eadbbb78a18faadd04e49b307affc9e56356` deployed after exact destination/source/rollback hash checks. The runtime validator loaded successfully. LiteSpeed purge completed. Deployment was serialized with the independent cashier task; no cashier, payment, order, stock or price fields were written by this task.

Gallery and collection frames follow actual photo proportions. Unloaded images reserve space; direct single-image galleries, carousel slides, hover previews, selected-colour overlays and native restoration are covered. Original files and all pre-existing product/variation image assignments remain byte-for-byte/ID-for-ID unchanged.

New product photo assignments require reviewed, upright, opaque, fully decoded JPEG/PNG/WebP files: 2:3, minimum 800×1200, preferred 1024×1536. Gallery detail/comparison exceptions have a separate role and minimum 800×800. Reviews bind to exact bytes and role. Native saves, metadata, Woo REST versions/batches, CSV and publication are guarded. Unchanged legacy images do not block ordinary price/stock maintenance; general Media uploads remain unrestricted.

## Evidence

- 111 original image files downloaded and matched to server SHA-256 values; inventory includes 22 published products and one private internal product.
- 71 standalone policy assertions, 63 JavaScript assertions and existing PHP/Python contracts pass.
- 45 native integration assertions pass on exact production versions: WordPress 7.1, WooCommerce 11.1.0 and PHP 8.2.33. Synthetic fixture used 128 MB PHP memory, isolated networking and no production customer/order data.
- Independent review found and resolved CSV intermediate writes, REST batch/legacy image-order semantics, scheduled publication, lazy-image geometry, direct single-image gallery sizing and excess memory allocation.
- Thirteen real-browser geometry assertions pass. Live desktop/mobile checks cover skort cards, single-image galleries, comparison slides, selected-colour previews, native variation resolution and clearing selections.
- Independent anonymous HTTP checks: all 22 product pages and 14 discovered collection/shop URLs return 200; all 111 inventory images and 125 additional live image URLs return HEAD 200. Served release CSS/JS hashes match the expected files at ordinary cached URLs.
- Authenticated live readback confirms low-resolution attachment 164 is rejected and reviewed portrait 939 is accepted. All original hashes and prior product/variation image assignments remain unchanged.
- Full database/files Updraft backup downloaded off-server, hashes verified and archive integrity checked. The narrow release rollback restores individual theme files, not the database; later cashier/order activity must be preserved.
- The 1,206-second health monitor completed 25 rounds across 26 URLs: 650 checks, zero failures. Final runtime readback reconfirmed WordPress 7.1, WooCommerce 11.1.0, PHP 8.2.33, the expected policy SHA-256 and attachment 600 review removal. Root PHP error log was not modified after deployment at the inspected checkpoint; WordPress debug logging was absent.

## Remaining asset work — issue stays open

63 originals meet dimension requirements. A full-size review found baked-in grey top/bottom bands in Match Dress attachment 600; its initially granted framing review was revoked and independently read back. The other 62 originals passed full-size visual review and retain their exact-file approval.

Attachment 473 is a larger, clean version of the same Match Dress photograph; a safe portrait derivative can replace both uses without altering the person or garment. Of 42 larger non-portrait files, 40 proposed centered crops preserve the existing subject, 593 requires a left-offset crop, and 592 cannot be safely cropped. The two wide comparison files 912/913 also require background-only extension for portrait use or an explicitly approved alternative. Exact crop/resize processing awaits the user's pending editing-method choice. AI-generated attempts changed subjects/details and were rejected; none were installed.

Four low-resolution originals remain: 164 (Rally Skort), 58/60/61 (Bubble Dress). No exact matching higher-resolution photograph exists in their own product galleries. One legacy Bubble assignment depicts a pleated skort rather than the bubble hem; existing mapping holds remain. Do not invent a colour mapping or claim that enlargement restores source detail. Obtain better originals or explicit merchant approval of suitable alternatives.

## Unrelated findings and scope limits

A pre-existing live `custom.css` discrepancy against the repository mirror was observed by the independent readback; it predates this release and was not overwritten. No claim is made that the entire production installation equals the repository.

During native variant selection, All-Size Pleated Skort Black variants had blank prices in the pre-deployment snapshot and displayed unavailable. A priced Lavender/L variant resolved in stock with an enabled add-to-cart control. This task did not change those commerce fields; merchant pricing reconciliation remains separate.

## Rollback and next control point

Restore only the seven theme files from their recorded pre-release hashes. Remove the policy include before removing the new policy module. Any concurrent destination change stops automatic rollback; report the conflict instead of overwriting it. Attachment review records are individually reversible and inert without the module. Original image files and assignments were not overwritten.

Next: receive the pixel-preserving processing decision, create/review non-destructive derivatives from the prepared crop coordinates, resolve the four source/mapping blockers, then perform a fresh serialized assignment migration and complete live visual verification. Do not close #115 while these asset criteria remain unresolved.
