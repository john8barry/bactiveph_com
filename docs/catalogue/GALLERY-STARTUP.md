# Gallery startup correction

On a fresh mobile Courtline Dress page, choosing Jujube Red and a size could resolve the right WooCommerce variation while leaving the white photograph visible. Touching a gallery thumbnail first masked the problem. The native Blocksy default-gallery handler starts its lazy slider without awaiting its promise, then clicks a captured thumbnail after 500 ms. A slow mount loses that click; a later selection can also be overtaken by the old callback.

The child theme now waits for the native slider before calling the unchanged native image-update function for qualified main-product forms with embedded default-gallery variations. Native selection, availability, price, quantity and cart handling continue immediately. The wrapper is renewed on Woo's attribute-update event because Blocksy installs its handler lazily. After the native operation, one guarded frame checks the active thumbnail against the exact selected image URL. This also handles the native same-image shortcut after manual browsing. Ambiguous image matches are left alone. It does not replay variation events, replace image metadata, invent a variation, or patch the parent theme.

Pending image operations are discarded after a newer request, changed attributes or variation ID, reset, removal, gallery replacement, manual gallery interaction, or explicit native-control fallback. Failed mounting restores native controls. AJAX/custom galleries remain on their existing native path. Existing thumbnail keyboard controls remain in place.

## Verification and release record

### Native first-render follow-up

After PR102, a fresh Flow Skort page with saved White/S defaults could restore the native dropdowns unexpectedly. Flexy's mount promise resolves after assigning its instance but before the first animation frame changes `data-flexy` from `no` to ready. The child now observes that native attribute and continues immediately when rendering is ready. A five-second deadline restores native controls only if rendering never completes; no polling or fixed selection delay is added.

Woo's reset handler already requests the original image through the same wrapper. A second reset cancellation was discarding that valid request when Clear arrived before the first native frame. Removing the duplicate cancellation lets the reset restore the original image while still superseding the old variation.

The regression fixture now runs the actual bundled Flexy implementation and parent mount function, including server-default gallery state. It covers default selection, a newer choice during the first frame, Clear before rendering, manual navigation, cleanup and timeout. The focused gallery, selector and related-card suites pass 32 tests. The deployed predecessor fails the new default-readiness case. Real local desktop/mobile checks cover defaults, rapid changes, keyboard gallery navigation, reset and explicit dropdown fallback.

Courtline117 lossless delivery is active; its 20-minute public monitor passed 27 checks over 1,206 seconds. Keep that option exactly unchanged during this follow-up. Further lossless batches and PR101 automatic defaults remain held until this correction has independent production verification. The local editor save/reopen workflow and next-day release checks are still unfinished.

Issue: [#48](https://github.com/john8barry/bactiveph_com/issues/48). This correction precedes further lossless-delivery and automatic-layout activation. Local source tests and fresh mobile browser verification are recorded separately from production proof; this document does not claim deployment.

Run the focused gallery regression along with the selector and related-card suites. The gallery regression executes the native Blocksy image handler and exercises mounting beyond its old 500 ms deadline, rapid selections, reset, manual navigation, detached galleries, rejection, native fallback and untouched AJAX handling. Release qualification also uses the active parent-theme source from the verified private restore.

Only `assets/js/catalog-visuals.js` changes at runtime. Both repository theme mirrors must match. Before production replacement, confirm the exact GitHub account, reviewed merged source, fresh writer window and live predecessor hash; back up the existing file with its mode and verify the encrypted archive. Use conditional atomic replacement and independent file/public readback. Test the first selection before touching a thumbnail, then repeat selection, reset and manual browsing on desktop and mobile.

Rollback restores only this file, and only while its bytes and mode still match this release. Refuse a later edit; never restore the whole database or overwrite newer commerce data. Keep PR100 collection files, uploaded assets and release options unchanged. Reconcile the subsequent image-delivery preservation manifest to this independently verified patch before continuing that release. Complete 20-minute monitoring and next-day checks before calling the release finished.
