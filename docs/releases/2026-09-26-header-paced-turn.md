# Deliberate 3D logo pacing

Issue #153 refines the approved 3D turn and sage sweep after the fast handoff read as a glitch. Base: `45269e6f86aaaa37cdb13fc0e70bb30a2f00bc53`.

The outgoing turn now lasts 520ms, with a 260ms fade starting at 220ms. The incoming logo rotates for 1060ms with a measured acceleration/deceleration curve and a 420ms fade. It starts immediately so interrupted scroll reversals do not retain an idle delay. The B settles for 60ms before the 650ms sage sweep, completing the sequence at 1770ms (previously 890ms). Both directions share the timing. Bar compression remains 220ms.

Only CSS timing/easing and its explanatory comment change in identical mirrors. Assets, transforms, runtime logic, navigation, load gating, reduced motion and final dimensions are unchanged. Mobile stays 56px compact, desktop 78px.

## Validation

Eleven header runtime tests, PHP navigation contracts, JS/PHP syntax, mirror comparison and diff checks pass. Independent source review found no blockers. Desktop/mobile visual checks and rapid reversals pass after removing the entry delay. All seven widths (320,390,768,999,1000,1280,1440) preserve content offset and have no horizontal overflow. Reduced motion retains zero-duration swap and no sweep. Impeccable reports only three pre-existing height-animation findings; the overshooting easing findings are removed.

## Backup, release and rollback

Fresh complete production backup verified before edits: seven archives, 568,643,929 bytes. Slow transfer was recovered with ZIP-entry reuse; every reconstructed file matches the exact fresh server SHA-256 and passes archive integrity checks, independently repeated by the coordinator. Private manifest: `~/Library/Application Support/BactivePH/header-paced-turn-20260926/production-backup/manifest.json`. Exact current CSS preimage and mode0644 retained. John explicitly required waiting for this backup before implementation.

After required CI and merge, deploy only reviewed CSS over verified SFTP using atomic replacement, exclusive writer lock, site identity and source/destination hash guards. Invalidate native page cache; verify ordinary cached/public routes and actual served asset bytes, desktop/mobile sequence and fresh logs/hashes over five minutes. Record final commit, hash and monitoring in issue153.

Rollback only the exact prior CSS after rejecting intervening changes, then refresh caches and verify. Never restore the database for this visual change; preserve orders/payment state.
