# Courier and payment branding release

Work record: [issue #5](https://github.com/john8barry/bactiveph_com/issues/5).
Dependencies: payment activation [#2](https://github.com/john8barry/bactiveph_com/issues/2), business identity [#3](https://github.com/john8barry/bactiveph_com/issues/3).

## Customer behavior

- Shipping partners are J&T Express and LBC Express. FAQ and product shipping-tab copy match.
- The site-wide footer and checkout reassurance communicate generic Cash on Delivery. The shipping page identifies both couriers as eligible COD delivery partners and refers customers to checkout for eligibility/fees.
- Five online-payment marks (QR Ph, Maya, ShopeePay, BPI Online, UnionBank Online) plus separate PayMongo processor branding render only when the `bactive_paymongo` gateway is an instance of `BActive\PayMongo\Gateway` and `is_available()` returns true. No bank-transfer fallback, card marks or GCash marks.
- COD independently follows the gateway's enabled setting. Customer/cart-specific eligibility is checked at checkout, not promised in the footer.
- Equal 108x44 payment tiles retain the official asset aspect ratios. Courier badges use an equal 132x44 target. There are responsive layouts, accessible tracking-link names, keyboard focus, image dimensions and lazy loading.
- No provider credentials, gateway configuration, order/payment execution, shipping rates or financial policy changes are included.

## Asset provenance

The five SVG tiles are exact official PayMongo assets, each with a 121x49 viewBox:

- [QR Ph](https://www.paymongo.com/images/logos/payment-methods/qrph.svg)
- [Maya](https://www.paymongo.com/images/logos/payment-methods/maya.svg)
- [ShopeePay](https://www.paymongo.com/images/logos/payment-methods/shopeepay.svg)
- [BPI](https://www.paymongo.com/images/logos/payment-methods/bpi.svg)
- [UnionBank](https://www.paymongo.com/images/logos/payment-methods/unionbank.svg)

The PayMongo wordmark is the official 2025 horizontal MongoGreen PNG distributed by its website; the logo is not redrawn, cropped or warped. J&T and LBC assets were obtained from their official websites. All are hosted locally under the child theme. SVG inspection found no scripts, event handlers, foreign objects or external resource references. The app does not contact a logo CDN at runtime.

## Staging evidence

Exact target: `https://staging.bactiveph.com`, separate database `waypmvhk_stg`, child theme `blocksy-child`, noindex setting retained.

- Fresh full Updraft backup: all six components copied off-server, matching hashes, ZIP/gzip integrity verified (121421114 bytes).
- Seven standalone CLI tests pass: ready, not ready, missing gateway, gateway exception, manager exception, all disabled, WooCommerce unavailable. Test code is CLI-guarded, run via SSH stdin, and excluded from web deployment.
- Real staging browser checks at 390px and 1440px pass: no horizontal overflow, logo distortion, missing images or page-generated errors; homepage/shop/FAQ/shipping pages use the new footer and have no Ninja Van copy.
- A render of the actual deployed template with a mocked ready gateway shows all five payment marks and PayMongo with correct aspect ratios. This is visual/template proof, **not provider activation proof**.
- COD is selectable at actual staging checkout, generic reassurance is present, and no order was submitted. A temporary browser cart was cleared afterward.
- Staging had a drifted standalone root footer with old marks and no `wp_footer()` call. Restoring the existing tracked Blocksy wrapper reconnects the shared footer and footer scripts. Production already has that wrapper; do not overwrite production root footer.

## Exact production overlay

Production target is `https://bactiveph.com`, database `waypmvhk_bactwp`, child theme `blocksy-child`.

The production BIR lane issued ALL-CLEAR and released its writer lane. Its post-release footer/functions hashes were independently re-downloaded and matched. The latest BIR link, registered address and B Active-only identity are retained.

Deploy only `template-parts/trust-bar.php`, its eight assets, the narrow shared-footer include, and the two requested strings in `functions.php`. Both checked-in theme mirrors reflect the resulting production source. Existing social links and BIR/address source drift are reconciled to the current live footer, not newly changed by this release.

The `wp-content` source mirror previously marked the COD fee taxable while production and the `wordpress/wp-content` mirror already marked it non-taxable. The mirrored source now agrees with production. **The production deployment does not change the fee/tax flag.** Generic fee copy avoids making a separate stale fee claim. Gateway activation and removal of legacy staging checkout/payment policy copy remain owned by #2.

Database delta: in FAQ page21 replace `Ninja Van` with `LBC Express`; in shipping page20 replace the fixed-fee COD sentence with `available on eligible orders. Choose Cash on Delivery at checkout to see any applicable fee.` All other content is retained. Each update checks the pre-change content hash and performs independent readback.

Production uses a fresh full Updraft restore set and exact post-BIR file/page snapshots. Uploads are hash-checked and PHP-linted in a private directory outside the web root, then atomically renamed with action-time source-hash checks. Cache purge and public readback are required after deployment. No public PHP administration helper is installed.

## Rollback and control point

Restore only this release's changed footer/functions/courier fragments from its post-BIR snapshots after verifying no later writer has modified them. Preserve newer payment changes if present. Purge the site-wide page cache and verify home/shop/product/checkout. Unreferenced new assets may remain. Full Updraft recovery is reserved for broader recovery, not a routine footer rollback.

Payment activation must purge page and edge caches, then verify the live five-mark footer and working checkout. Availability-based markup can otherwise remain cached. The gateway lane owns that transition.

No core/parent-theme modifications, dependency updates or secrets are included. Security alert API access is unavailable (403/404), not a clean-security claim. Private backups and sanitized local receipts stay outside Git. No memory files were updated.

Status at commit: staging passed; production deployment and live readback pending. The work record contains subsequent deployment receipts and acceptance status.
