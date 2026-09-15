# Catalogue defaults production release — September 15, 2026

Issue: [#48](https://github.com/john8barry/bactiveph_com/issues/48). Live milestone: [issue #48 update](https://github.com/john8barry/bactiveph_com/issues/48#issuecomment-5673938517). Source: [PR #101](https://github.com/john8barry/bactiveph_com/pull/101), reviewed head `b37b04dc4ae1dfaed03f3b409050292b9fccc94f`, actual merge `8461d1832749047f39078a9b8ed4dced47adb40a`.

**Live installation, metadata, native readback, sampled browser flows and all 15 qualified cart samples passed. The defaults 20-minute monitor passed 119 checks over 1,220.32 seconds; next-day verification remains pending.** This record does not close the outstanding product mappings or follow-up checks.

## Delivered scope

The production setting `catalogue-defaults-20260915` enables layout, cards, selectors, image quality and the merchant editor. New eligible products no longer require a per-ID release entry. Product-specific Native WooCommerce fallback and the separate homepage/lossless settings remain available. Held products may use shared layout, but their selectors and unapproved mappings remain held.

Eleven theme files were independently read back with expected SHA-256 and mode 0644:

- `assets/css/catalog-visuals.css`
- `assets/css/catalogue-editor.css`
- `assets/js/catalog-visuals.js`
- `assets/js/catalogue-colour-photo.js`
- `assets/js/catalogue-editor.js`
- `assets/js/catalogue-layout.js`
- `inc/catalogue-settings.php`
- `inc/catalogue-editor.php`
- `functions.php`
- `inc/catalog-visuals.php`
- `inc/collection-visuals.php`

The gallery SHA-256 is `8dc3a98fc5a327dfd6fa88b6a1e7410641d00cdf8bb46f23f15e57718c2d091a`, incorporating PR #105 and the existing keyboard guard. No other theme/MU-plugin dependency was in the mutation scope; all 342 protected files matched.

The migration verified 44 rows and seeded 32 owned settings rows (15 product, 17 term), retaining 50 reviewed colour circles and 50 representative previews. Existing settings and the four held products were preserved. Independent before/after hashes matched for all 272 rows covering 19 products/253 variations: product content/URLs, attributes, explicit photos, SKU and commerce fields. Orders and sessions were outside this read-only capture.

The initial preflight correctly stopped on product 160's later stored slug change to `/product/the-rally-skort/`. Fresh native identity preserved that edit; the metadata plan remained byte-identical. The full backup and actual isolated restore also contain the new slug. The release did not rename products or rewrite their URLs.

## Live acceptance evidence

- Native readback at **02:30:16 UTC**: all eleven files, metadata after-state, active switches, original identities, separate final lossless option and 342 protected files passed.
- Live browser receipt at **02:43:48 UTC**: Courtline desktop/mobile full-photo selection, keyboard Enter, Reset and native dropdown fallback; Court Skort White/10 with numeric sizes, full 853×1280 photo and illustrated guide; Elite Powder Blue related-card preview without navigation; four desktop/two mobile shop columns; preserved responsive homepage editorial; held Rally Skort native dropdowns. No JavaScript errors were recorded on those checked pages. Courtline's existing generic size guide is preserved; a dedicated chart is not claimed (issue #74).
- Live merchant UI: Courtline's migrated review controls, native picker and representative chooser were visible; global Colour displayed its circle picker. Production inspection was read-only. Review/save/reopen persistence was separately tested in the isolated fixture on the same source bytes.
- Anonymous carts: products **36, 50, 83, 89, 95, 111, 117, 128, 154, 185, 217, 565, 573, 660 and 677** each passed one reviewed native variation/image/price/cart sample, and every test item was removed. Court Skort (185) used native AJAX White/10 variation **782**. No order or payment was created.
- Earlier PR #105 gallery monitoring passed **69 checks / 1,206.23 seconds**. Resumed image-batch monitors passed **90 / 1,206.46**, **153 / 1,210.32** and **216 / 1,212.26** checks/seconds. The earlier rolled-back 36/50/83 attempt and its interrupted monitor remain historical non-passes.

The ten active lossless products are 117, 36, 50, 83, 89, 95, 111, 128, 217 and 565. Products 154, 185, 573, 660 and 677 retain full-resolution JPEG originals. Asset-byte savings are verified; production Core Web Vitals were not measured by this release.

## Private evidence bindings

Receipts remain in the private B Active release/recovery directories. Only sanitized identifiers and hashes are recorded here; no credentials, raw databases, customer data or session payloads are published.

| Evidence | SHA-256 |
|---|---|
| `defaults-live-20260915/live-verification-summary.json` | `63a63c35523e882e678418871d01b4ebcf8280e5cb0f22b7cb494cdbc2326be9` |
| `defaults-live-20260915/native-readback.json` | `e2cc858d4759c9b7ed46dd3dc892d96160167bdff1466cb81ebf66ba363d7586` |
| `defaults-live-20260915/browser-root.json` | `c98a76ef50404db82c1c42a0289558b823048c7190e6cc1b0af9e15d4e942f89` |
| `defaults-commerce-before.json` | `926402f5a327b596de348c58a0564b7fa4a39c6139ffe8e8f90ed4e9354d0052` |
| `defaults-commerce-after.json` | `74bfeeda0e6ec5e98924e38f97e7ef85602c3e8d29a5539f4ff76a005c60a8da` |
| Current 19-product identity | `eae67de65a130f3f357092b799ec6f592df513162c031e2f5b7050afe99cabd2` |
| Applied migration plan | `a94c8483976a26df854c0c680c40b4b3efea6052935d9585ad15836b8591f3d4` |
| Native defaults option wrapper | `c46a0778a098a12bab8c232beb9b7aeebff3b00bae25622bb6ef13550b37df38` |
| Unchanged native lossless option wrapper | `e91fa08421f0d28b82fa7564bbba2e49c4055cdd5f51b74635fa737c4462cfdb` |
| Full backup `fresh-20260915T015424Z/manifest.json` | `70c6b3132c60f3d4e0eaced77beb7dc9a21534c6505e86c6120cf73b0aca6892` |
| Actual isolated restore `restore-20260915T015424Z/restore-receipt.json` | `84b049c0edc974c7b08e4fd58e7fdcd39c81ac3f3d2c5f13716f016192758fad` |
| Encrypted scoped defaults backup | `3aefa83e1fd25e286d2f644023a504d007cb31c67671ba7c31292e2461f36633` |

The full backup preceded the defaults write and passed integrity checks plus a real isolated restore. The scoped backup captures the eleven prior file states (including six absent files), their modes and the option before-state. Metadata before/after bytes are guarded by the reviewed migration plan and durable intent; the full backup covers the database. Local tests exercised interrupted file application and reverse rollback, including missing-model bootstrap, and refused later metadata/option/file edits. These tests do not claim that production was rolled back.

Routine rollback disables only the exact owned defaults option, reconciles uncertain metadata state, then restores only matching owned metadata/files. It must preserve newer edits, current commerce and the independent lossless mapping. Never use a whole-database restore for this visual rollback.

## Remaining control points

- **Defaults monitor: PASS — 119 checks over 1,220.32 seconds.** Started at 02:29:47 UTC; completed at 2026-09-15T02:50:08.312820+00:00. Receipt SHA-256 `3d5962fc835eb115c47b0c5035dddeba2920eea09545c57e47d056328cb675e0`. It binds the reviewed source, actual merge, native defaults/lossless option wrappers and current 19-product identity. The monitor checks HTTP/forms/public assets; native PHP, browser/editor and cart checks are proven by their separate receipts above.
- **Next-day verification: PENDING, September 15 after 14:00 UTC.** Include first selections, reset/native fallback, photo/preview identity, availability and the held boundaries.
- **Still held:** Bubble Dress (56), Rally Skort (160), Ribbed Tank (211) and Sculpt Leggings (238) catalogue/photo mappings. Trashed products 148/347 remain excluded. Court Skort Lavender stays name-only pending review. Courtline dedicated size-chart work remains under issue #74.
- Four existing parent-theme vendor dependency findings remain tracked separately; this release does not claim overall security clearance.

Merchant workflow: [catalogue defaults](../catalogue/CATALOGUE-DEFAULTS.md). Per-product evidence: [verification ledger](../catalogue/PRODUCT-VERIFICATION.csv). Image and gallery details: [lossless delivery](../catalogue/LOSSLESS-DELIVERY.md), [gallery startup](../catalogue/GALLERY-STARTUP.md).
