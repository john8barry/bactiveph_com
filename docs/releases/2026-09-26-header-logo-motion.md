# Header logo motion refinement

Issue #149 tracks the release and final production acceptance. Base: `26e5c2b48bfab01fb71a9d470cb1769128e0c7b5`.

The full wordmark draws inward and clips its lower lettering as it fades in 130ms. The standalone B follows its forward lean from a small diagonal offset, with a 45ms lead-in and 420ms settle. The easing produces a restrained overshoot; reverse transitions begin from their current interpolated values. This is one bounded logo gesture, with no looping, blur or navigation animation.

Runtime scope is the mirrored header-sage.css only. The final 56px mobile and 78px desktop bars, original image assets, menus, typography, accessible home link, scroll thresholds and interaction locks are unchanged. New transforms and clipping apply only when reduced motion is not requested. Missing B images retain the wordmark without clipping or disappearing.

## Acceptance evidence

Eleven existing runtime tests, PHP header/footer navigation contracts, syntax, mirror comparisons and diff checks pass. Independent source review found no blocking defects. At 320/390/768/999px the compact bar remains 56px; at 1000/1280/1440px it remains 78px. Browser checks show unchanged content position and no horizontal overflow. Forward/reverse transitions and interruption return to the correct state. Reduced motion computes zero-duration transitions with no added transforms/clipping.

A local reversal sample measured 1.6ms layout and 2.0ms scripting. This is a laboratory sample, not physical-device or field performance certification. Desktop/mobile visuals were reviewed together. The Impeccable detector reports the existing fixed-header height animations and the deliberately subtle overshoot; both are retained for this approved motion treatment. No runtime dependencies added.

## Backup, release and rollback

Before editing, a fresh complete production backup was downloaded privately: seven archives, 568,640,614 bytes, all remote/local SHA-256 and archive integrity checks passed. Receipt: `~/Library/Application Support/BactivePH/header-motion-20260926/production-backup/manifest.json`. Exact current CSS preimage and mode 0644 are retained beside it.

Deploy only the reviewed CSS after passing CI and merge. Under the production writer lock, reverify site identity, merged source and destination hash, then use verified SFTP atomic replacement. Use native LiteSpeed page-cache purge plus an uncached loopback request; require ordinary homepage/shop cache HIT responses to reference the new CSS and match its hash. Check main storefront routes and live desktop/mobile motion, then monitor fresh error bytes and hashes for at least five minutes before releasing the lock and closing #149.

Rollback restores only the exact CSS preimage after rejecting intervening writes, refreshes page cache, and repeats live checks. No database, order, payment or unrelated asset restoration. Final commit, deployed hash, public results and monitoring receipt are recorded in issue #149 and the private release directory.
