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

Live release receipt will be appended after deployment and five-minute monitoring.
