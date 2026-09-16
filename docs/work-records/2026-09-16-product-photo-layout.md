# Product photo frame repair

The catalogue used 3:4 frames around 2:3 photos. The layout now follows each delivered photograph without cropping or stretching: normalized portrait assets naturally display at 2:3, and comparison/detail or temporarily unresolved legacy assets retain their native shape.

Product galleries override both container and image ratio constraints while preserving Blocksy Flexy slide-height ownership. Colour-only previews temporarily set a measured viewport height, resize with the viewport, and restore the exact previous inline height and priority on native variation selection, reset or manual browsing. Collection cards use the currently visible image ratio, including hover swaps; a selected colour continues to suppress the native hover swap.

Validation: 52 JavaScript tests passed across catalogue-colour-photo, collection-visuals and catalog-gallery-startup, including real Flexy lifecycle fixtures, legacy-ratio regression assertions, hover/detail swaps, preview resize, exact height restoration and rapid selection/reset. Both theme mirrors match. Browser geometry and production verification remain the integrating release owner's responsibility; these tests are not live-delivery evidence.

Rollback: restore the four CSS/JavaScript files from the pre-release backup only after confirming their current hashes still match this release. This layout change does not alter image assets, product assignments, variation identity or image validation policy.

## Lazy-image geometry correction

Native browser review found an unloaded lazy image had no intrinsic ratio and collapsed to zero height. Image rules now use `aspect-ratio: auto 2 / 3`: the fallback reserves portrait space before loading, while the loaded image’s natural ratio still controls detail/comparison geometry. Containers retain `auto` so they do not force landscape exceptions into portrait boxes. `tests/product-photo-layout.html` is an actual-browser geometry regression fixture covering initial unloaded images and loaded landscape exceptions for gallery and collection frames.

## Single-photo galleries

The all-route browser audit also found Blocksy's single-photo gallery bypasses `.flexy-view` and retains its inline 9:16 image style. The same natural-image sizing now covers only direct media/image children of `.ct-product-gallery-container`; thumbnail strips and their square crops remain outside that selector. The browser fixture now checks this direct-gallery structure and preserves thumbnail geometry.
