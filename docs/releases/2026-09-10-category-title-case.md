# Shop navigation title case

Work record: [issue 68](https://github.com/john8barry/bactiveph_com/issues/68). John authorized implementation and production publication on September 10, 2026.

## Language standard and scope

Short standalone navigation destinations use title case. Principal words are capitalized; prose sentences remain sentence case. The shared desktop and mobile Shop menu now reads:

1. Leggings
2. Pickleball Dresses
3. Pilates & Yoga
4. Sets
5. Skorts
6. Sports Bras
7. Tops & Tanks
8. Shop All

The release changed **Pickleball dresses** to **Pickleball Dresses**, **Sports bras** to **Sports Bras**, and **Shop all** to **Shop All**. The footer and category headings already used the correct forms. No order, URL, top-level navigation, footer, product data, database content, payment, checkout, email, provider setting, or staging file changed.

## Verification

- Pull request [69](https://github.com/john8barry/bactiveph_com/pull/69) passed the Sage header contract check and merged to `main` as `ee6a51c7b71b56c4140ef8e0d22b6c91b6becc31`.
- PHP syntax checks, the standalone desktop/mobile header contract, JSON parsing, `git diff --check`, secret-pattern scanning, and the Impeccable detector completed before release. The detector's two color advisories were verified as pre-existing unchanged sidecar values.
- Independent source and live-language reviews confirmed that only the three shared header labels required capitalization changes; prose was correctly excluded.
- The exact production MU-plugin file changed from SHA-256 `08890b3ec64388e9df070266bd7445961559443b897e59a1845c2e73c1ddceb0` to `6a2af47bdde8f80741964716d501d87ab7c62b9fa9ad69611eaf8a156d0e41c1` through a guarded atomic replacement.
- Production PHP lint passed after installation. The 11-file PayMongo plugin manifest remained unchanged at SHA-256 `3a983a4348403a1476e68733c4be9162c1e11b58d69ad96791ca96fa71aa48f5`.
- Public homepage and shop-page readback confirmed the exact title-case labels and preserved order/URLs in desktop and mobile menu markup. No old lowercase navigation variant or PHP warning, fatal, or parse-error text remained.
- The empty checkout path retained its normal HTTP 200 cart redirect. The production writer lock was explicitly released after the payment coordinator independently verified the receipt and payment manifest.

Production deployment and live readback completed on September 10, 2026.

## Rollback

The exact pre-change file is stored privately at `/Users/johnbarry/Library/Application Support/BactivePH/category-title-case/2026-09-10/production-file-backup/bactiveph-sage-header.before.php` with SHA-256 `08890b3ec64388e9df070266bd7445961559443b897e59a1845c2e73c1ddceb0`.

Before rollback, verify the live file still matches the release hash so a later writer is not overwritten. Restore only this MU-plugin file with the same guarded atomic procedure, preserve its mode, run production PHP lint, and repeat the public desktop/mobile menu and checkout-health checks.
