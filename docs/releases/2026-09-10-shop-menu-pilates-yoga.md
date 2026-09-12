# Shop menu Pilates & Yoga link

Work record: [issue 62](https://github.com/john8barry/bactiveph_com/issues/62). John authorized implementation and production publication on September 10, 2026.

## Behavior and scope

The shared desktop and mobile Shop menu now includes **Pilates & Yoga**, linked to the existing `/collections/pilates-and-yoga/` destination. **Tops & tanks** was corrected to **Tops & Tanks**. The collection links remain alphabetical:

1. Leggings
2. Pickleball dresses
3. Pilates & Yoga
4. Sets
5. Shop all
6. Skorts
7. Sports bras
8. Tops & Tanks

No top-level navigation, footer, theme styling, database content, product, payment, checkout, email, provider setting, or staging file changed.

## Verification

- Pull request [63](https://github.com/john8barry/bactiveph_com/pull/63) passed the Sage header contract check and merged to `main` as `59d4e1f4a2564635783947b5deeaf66b18adfcfa`.
- PHP syntax checks, the standalone desktop/mobile header contract, `git diff --check`, and the Impeccable detector passed before release.
- Independent source review found no defects and confirmed the public collection destination returned HTTP 200.
- The exact production MU-plugin file changed from SHA-256 `ab85105503f43c0ae10f3eac1fd61aadeb8b0c64e05310d365d717e0a65504e9` to `7e1c3a8e2cb7215ffb0c4ea5a606b899360e03b42b9d7dd15de8350f987f8565` through a guarded atomic replacement.
- Production PHP lint passed after installation. The 11-file PayMongo plugin manifest remained unchanged at SHA-256 `3a983a4348403a1476e68733c4be9162c1e11b58d69ad96791ca96fa71aa48f5`.
- Public homepage and shop-page readback confirmed the exact ordered labels and Pilates URL in both desktop and mobile menu markup, with no PHP warning, fatal, or parse-error text.
- The empty checkout path retained its normal HTTP 200 cart redirect. The production writer lock was explicitly released after an independent verification by the payment coordinator.

Production deployment and live readback completed on September 10, 2026.

## Rollback

The exact pre-change file is stored privately at `/Users/johnbarry/Library/Application Support/BactivePH/menu-pilates-yoga/2026-09-10/production-file-backup/bactiveph-sage-header.before.php` with SHA-256 `ab85105503f43c0ae10f3eac1fd61aadeb8b0c64e05310d365d717e0a65504e9`.

Before rollback, verify the live file still matches the release hash so a later writer is not overwritten. Restore only this MU-plugin file with the same guarded atomic procedure, preserve its mode, run production PHP lint, and repeat the public desktop/mobile menu plus checkout-health checks.
