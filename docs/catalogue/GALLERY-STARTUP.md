# Gallery startup correction

On a fresh mobile Courtline Dress page, choosing Jujube Red and a size could resolve the right WooCommerce variation while leaving the white photograph visible. Touching a gallery thumbnail first masked the problem. The native Blocksy default-gallery handler starts its lazy slider without awaiting its promise, then clicks a captured thumbnail after 500 ms. A slow mount loses that click; a later selection can also be overtaken by the old callback.

The child theme now waits for the native slider before calling the unchanged native image-update function for qualified main-product forms with embedded default-gallery variations. Native selection, availability, price, quantity and cart handling continue immediately. The wrapper is renewed on Woo's attribute-update event because Blocksy installs its handler lazily. After the native operation, one guarded frame checks the active thumbnail against the exact selected image URL. This also handles the native same-image shortcut after manual browsing. Ambiguous image matches are left alone. It does not replay variation events, replace image metadata, invent a variation, or patch the parent theme.

Pending image operations are discarded after a newer request, changed attributes or variation ID, reset, removal, gallery replacement, manual gallery interaction, or explicit native-control fallback. Failed mounting restores native controls. AJAX/custom galleries remain on their existing native path. Existing thumbnail keyboard controls remain in place.

## Verification and release record

Issue: [#48](https://github.com/john8barry/bactiveph_com/issues/48). This correction precedes further lossless-delivery and automatic-layout activation. Local source tests and fresh mobile browser verification are recorded separately from production proof; this document does not claim deployment.

Run the focused gallery regression along with the selector and related-card suites. The gallery regression executes the native Blocksy image handler and exercises mounting beyond its old 500 ms deadline, rapid selections, reset, manual navigation, detached galleries, rejection, native fallback and untouched AJAX handling. Release qualification also uses the active parent-theme source from the verified private restore.

Only `assets/js/catalog-visuals.js` changes at runtime. Both repository theme mirrors must match. Before production replacement, confirm the exact GitHub account, reviewed merged source, fresh writer window and live predecessor hash; back up the existing file with its mode and verify the encrypted archive. Use conditional atomic replacement and independent file/public readback. Test the first selection before touching a thumbnail, then repeat selection, reset and manual browsing on desktop and mobile.

Rollback restores only this file, and only while its bytes and mode still match this release. Refuse a later edit; never restore the whole database or overwrite newer commerce data. Keep PR100 collection files, uploaded assets and release options unchanged. Reconcile the subsequent image-delivery preservation manifest to this independently verified patch before continuing that release. Complete 20-minute monitoring and next-day checks before calling the release finished.
