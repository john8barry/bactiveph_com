# Compact header mark and mobile height

Issue: #146. Base: `1bcd37ce371542a835b30be8937549ecfbfffcc2`.

The compact header now crossfades to John’s supplied black transparent standalone B. The PNG is used byte-for-byte, with its original proportions and transparency; expanded branding and the existing accessible home link stay intact. Image loading must succeed before the full logo fades out. Failed image loads and disabled JavaScript preserve the original logo.

Mobile compresses from 96px to 56px (including the sage rule), preserving 48px bag/menu touch widths and at least 48px control heights. Desktop remains 110px to 78px. Thresholds, 220ms easing, stable expanded spacer, menu ordering, native disclosures, keyboard locks, admin offsets and safe areas remain. A pointer or touch interaction no longer leaves a closed menu’s focus locking the header at its expanded size; keyboard interaction still holds the size.

## Validation

Eleven automated runtime tests pass, including pointer/keyboard switching, open-menu locks and logo load/failure/idempotency. PHP header/footer contracts, syntax, mirrors and diff checks pass. Browser geometry at 320, 390, 768, 999, 1000, 1280, 1440px confirms intended heights, no horizontal overflow, stable document position, loaded compact mark, and preserved mobile targets. Desktop/mobile visuals reviewed together.

Impeccable detector flags the two existing height-animation patterns. These remain intentional, confined to the fixed header over an unchanged spacer; the new logo crossfade uses opacity/transform. No runtime dependencies or database/payment changes.

## Backup and rollback

Fresh production backup completed before implementation: seven archives covering database, plugins, themes, uploads, MU plugins and other files; 568,475,681 bytes verified by remote/local SHA-256 and archive integrity. Private receipt: `~/Library/Application Support/BactivePH/compact-header-20260926/production-backup/manifest.json`. Exact CSS/JS preimages and 0644 permissions are retained beside it; the new image was verified absent.

Deployment scope: mirrored source CSS/JS plus `assets/images/header-sage-mark.png`; only these three assets reach production. Under the exclusive writer lock, verify site identity, clean merged source, backup hashes and exact destination preimages; stage image first, then CSS/JS via atomic verified SFTP replacements. Refresh LiteSpeed page caches and verify ordinary public URLs plus served assets.

Rollback restores existing JS/CSS first in reverse install order, then removes only the exact new image after a fresh hash check. Stop on intervening changes. Reapply cache purge, recheck live routes and monitor before releasing the lock. Preserve orders/database.

Independent source review found no release blockers. Reduced-motion styles have zero transition duration; landscape search remains reachable in its scrolling panel; Escape returns focus; simulated admin offsets and native no-JavaScript menu behavior pass. Real iOS/Android hardware and software-keyboard behavior are not certified by browser emulation.

## Live receipt

Implementation commit: `500ae3eaa9779194c0506e2d50e50b4f7c34f1d5`; PR #147; merged source: `a6141085f0a5bc84c719730f5a1dda48d8d463bc`. Both PR and merged-head Sage header CI passed. Independent source and deployment-helper reviews passed.

Only the three reviewed assets were atomically installed with mode 0644 under the production writer lock. Exact authenticated readback matched source. No template, MU plugin, database, order or payment changes.

Initial ordinary pages still referenced old cache versions. A queued purge and targeted CLI request were insufficient. The native site LSCache page purge (`LiteSpeed\Purge::purge_all_lscache`) followed by an uncached loopback request processed the purge; ordinary homepage/shop then returned new versions on both MISS and subsequent HIT responses. No Cloudflare-wide purge was used. This follows the [LiteSpeed page-cache API](https://docs.litespeedtech.com/lscache/lscwp/api/) and [CLI guidance](https://docs.litespeedtech.com/lscache/lscwp/cli/); public readback is the acceptance proof.

All eight ordinary routes returned 200: homepage, shop, every-active-polo product, cart, checkout, account, men’s Tops, and men’s Bottoms. Empty checkout redirected normally to cart. All served CSS/JS versions and the PNG matched source. Live browser checks confirmed 96→56px mobile after pointer menu close, 110→78px desktop, unchanged content position, standalone B on both devices, no overflow, correct Men navigation, working search/account/bag and no console errors. A local compact-animation sample used 2.5ms layout and 3.3ms scripting; this is laboratory evidence, not a field performance claim.

Final authenticated readback at 494 seconds after installation found all three hashes unchanged and zero new bytes in error_log/debug.log. Writer lock released after acceptance. Private backup, exact preimages and rollback script remain available; receipt directory also contains public-final.json, deployment.json, latest-readback.json and writer-released.json.

Served versions: CSS `1790416426`, JavaScript `1790416430`.

| Asset | SHA-256 |
| --- | --- |
| header-sage-mark.png | `d0cad61159dcfde7b7a43823d5520c8b26523fea934724812fad2706e5afb370` |
| header-sage.css | `67c2269073c2d5b196c14f1c86bce2ecb718cb0772188b2d76cd769777fde5fe` |
| header-sage.js | `9854cee5b0dad1848e86e9a379e4962b2a56ff577933f326bde248b9427fa9db` |
