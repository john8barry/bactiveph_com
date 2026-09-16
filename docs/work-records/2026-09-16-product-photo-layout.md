# Product photo frame repair

The catalogue used 3:4 frames around 2:3 photos. The layout now follows each delivered photograph without cropping or stretching: normalized portrait assets naturally display at 2:3, and comparison/detail or temporarily unresolved legacy assets retain their native shape.

Product galleries override both container and image ratio constraints while preserving Blocksy Flexy slide-height ownership. Colour-only previews temporarily set a measured viewport height, resize with the viewport, and restore the exact previous inline height and priority on native variation selection, reset or manual browsing. Collection cards use the currently visible image ratio, including hover swaps; a selected colour continues to suppress the native hover swap.

Validation: 52 JavaScript tests passed across catalogue-colour-photo, collection-visuals and catalog-gallery-startup, including real Flexy lifecycle fixtures, legacy-ratio regression assertions, hover/detail swaps, preview resize, exact height restoration and rapid selection/reset. Both theme mirrors match. Browser geometry and production verification remain the integrating release owner's responsibility; these tests are not live-delivery evidence.

Rollback: restore the four CSS/JavaScript files from the pre-release backup only after confirming their current hashes still match this release. This layout change does not alter image assets, product assignments, variation identity or image validation policy.
