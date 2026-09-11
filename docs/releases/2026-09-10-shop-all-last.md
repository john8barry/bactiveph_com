# Shop all final menu position

Work record: [issue 65](https://github.com/john8barry/bactiveph_com/issues/65). John authorized implementation and production publication on September 10, 2026.

## Behavior and scope

**Shop all** now appears after the seven alphabetized product categories in the shared desktop and mobile Shop menu:

1. Leggings
2. Pickleball dresses
3. Pilates & Yoga
4. Sets
5. Skorts
6. Sports bras
7. Tops & Tanks
8. Shop all

No label, URL, top-level navigation, footer, styling, database content, product, payment, checkout, email, provider setting, or staging file changed.

## Verification

- Pull request [66](https://github.com/john8barry/bactiveph_com/pull/66) passed the Sage header contract check and merged to `main` as `2cdeb7eee4011b33e0deb892686ad8165180e0b6`.
- PHP syntax checks, the standalone desktop/mobile header contract, `git diff --check`, secret-pattern scanning, and the Impeccable detector passed before release.
- Independent source review confirmed that the implementation only moved Shop all, both modes render the same map, and the active design documentation matches the new hierarchy.
- The exact production MU-plugin file changed from SHA-256 `7e1c3a8e2cb7215ffb0c4ea5a606b899360e03b42b9d7dd15de8350f987f8565` to `08890b3ec64388e9df070266bd7445961559443b897e59a1845c2e73c1ddceb0` through a guarded atomic replacement.
- Production PHP lint passed after installation. The 11-file PayMongo plugin manifest remained unchanged at SHA-256 `3a983a4348403a1476e68733c4be9162c1e11b58d69ad96791ca96fa71aa48f5`.
- Public homepage and shop-page readback confirmed the exact order and final Shop all URL in desktop and mobile menu markup, with no PHP warning, fatal, or parse-error text.
- The empty checkout path retained its normal HTTP 200 cart redirect. The production writer lock was explicitly released after independent verification by the payment coordinator.

Production deployment and live readback completed on September 10, 2026.

## Rollback

The exact pre-change file is stored privately at `/Users/johnbarry/Library/Application Support/BactivePH/shop-all-last/2026-09-10/production-file-backup/bactiveph-sage-header.before.php` with SHA-256 `7e1c3a8e2cb7215ffb0c4ea5a606b899360e03b42b9d7dd15de8350f987f8565`.

Before rollback, verify the live file still matches the release hash so a later writer is not overwritten. Restore only this MU-plugin file with the same guarded atomic procedure, preserve its mode, run production PHP lint, and repeat the public desktop/mobile menu and checkout-health checks.
