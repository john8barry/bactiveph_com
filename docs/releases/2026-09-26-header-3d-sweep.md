# 3D logo turn and sage sweep

Issue #151 records the selected visual direction, release and live acceptance. Base: `93eb9dd3eab3bf752c5a9a6bd8d6edd446b5210d`.

John selected a 3D turn with a sage sweep after finding the earlier logo swap too discreet. The full logo turns edge-on over 340ms. The standalone B rotates forward over 700ms after a 120ms offset, with perspective and a settling ease. A 480ms sage/ivory reflection starts at 410ms and crosses only the B artwork, ending the gesture at 890ms. The wordmark stays visible during its turn before fading. Reverse scrolling interpolates from the current pose and immediately cancels the decorative sweep.

Runtime scope is header-sage.css in both source mirrors. Original image bytes, JavaScript, menu typography/order, native home link, keyboard/disclosure locks and final bar dimensions remain unchanged. Mobile stays 56px compact, desktop 78px. No new dependencies. All new motion is inside prefers-reduced-motion:no-preference. A failed B load preserves original branding; unsupported mask rendering omits the reflection. The pseudo-element ignores pointer input.

## Validation

Eleven runtime tests and PHP header/footer contracts pass, along with syntax, source mirrors and diff checks. Independent source review found no blockers. Browser checks cover 320/390/768/999/1000/1280/1440px, desktop/mobile visual motion, reversal/interruption, stable content position, no horizontal overflow and reduced-motion zero-duration state changes with no sweep. Masks resolve the exact supplied transparent B.

A local forward-motion sample measured 2.1ms layout and 4.1ms scripting. This is laboratory evidence, not physical-device or field certification. The Impeccable detector flags existing fixed-header height animation and the intentionally settling easing; these are retained for the selected choreography. Gradient repainting is confined to a 64px desktop or 52px mobile masked canvas, with no animation loop.

## Backup, release and rollback

Complete production backup verified before edits: seven archives, 568,642,511 bytes, all remote/local SHA-256 and archive integrity checks passed. Private receipt: `~/Library/Application Support/BactivePH/bolder-header-20260926/production-backup/manifest.json`. Exact CSS preimage/mode 0644 retained beside it.

Deploy only reviewed CSS after CI/merge using verified SFTP atomic replacement under the exclusive production writer lock and exact source/destination guards. Refresh site page cache through the native LiteSpeed page purge and uncached loopback request. Require ordinary public pages—including cached homepage/shop—to reference the current CSS with matching served bytes, verify live desktop/mobile motion and routes, then monitor fresh error bytes and hashes for at least five minutes.

Rollback restores only the exact prior CSS after rejecting intervening writes, refreshes caches and repeats live checks. Preserve all orders/database/payment state. Final commit, deployed SHA-256, public readback and monitoring receipt are recorded in issue #151 and the private release directory before closure.
