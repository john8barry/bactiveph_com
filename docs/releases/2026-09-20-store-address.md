# B Active WooCommerce business address

Issue [#129](https://github.com/john8barry/bactiveph_com/issues/129) records this customer-communication and fulfilment consistency release. The public footer and published legal pages already use the approved address. This release supplies the same address to WooCommerce settings and future WooCommerce email footers without touching customer or historical order records.

## Approved value

`Unit No. C07, Lombardy Bldg., Palmetto Place, Purok 16, Gem Village, Ma-a, Talomo District, 8000 City of Davao, Davao del Sur, Philippines`

WooCommerce stores the address as these fields:

- address line 1: `Unit No. C07, Lombardy Bldg., Palmetto Place`
- address line 2: `Purok 16, Gem Village, Ma-a, Talomo District`
- city: `City of Davao`
- postcode: `8000`
- country/state: `PH:DAS` (Philippines / Davao del Sur)

The email footer is `{site_title}` followed by the exact approved value. It deliberately does not use WooCommerce's formatted `{store_address}` placeholder, so a future display-format change cannot alter the approved wording.

## Guarded release

`tools/apply_store_address.php` is a WP-CLI-only helper with target identity, administrator capability, active WooCommerce/child-theme, InnoDB, option-existence, and exact-preimage checks. It supports `check`, `apply`, `verify`, and guarded `rollback` modes. Each write runs in one database transaction and a failed write reports success only after verified rollback.

The baseline on both sites is blank street/city/postcode fields and the default WooCommerce email-footer template. Production already uses `PH:DAS`; staging's `PH:DVO` base state is changed to the approved `PH:DAS` during its test release. The helper must stop if any of those values change before application.

Take a new complete UpdraftPlus backup and retain an off-server verified copy before each environment changes. Validate staging before production. Verify the resulting base location, tax basis, shipping-rate behavior, no-send email-footer rendering, public site pages, and error logs. Do not change individual customer addresses, existing orders, payment settings, delivery rules, external-provider profiles, or legal-page content.

## Rollback

Only run `rollback` while every setting still equals this release's exact post-change value. It returns address fields and email text to their recorded preimages; staging also returns to `PH:DVO`. It never restores a full database backup and never rewrites existing orders or customer data.

## Release verification

On 2026-09-20, staging completed a fresh six-archive UpdraftPlus backup (135,831,404 bytes) that was independently downloaded and archive-checked before the staged run. The staged helper check, apply, and verify modes all passed. Its country/state changed from the staged preimage `PH:DVO` to `PH:DAS`; the rendered WooCommerce email footer exactly matched the site title followed by the approved address, without resolving or retaining a `{store_address}` placeholder. No email was sent.

Before production, the successful 2026-09-20T07:03:56Z complete UpdraftPlus backup from the coordinated pickup release was revalidated. That verified off-server backup contains seven archives totalling 556,540,448 bytes. The production helper check, apply, and verify modes passed. It changed only the blank address line 1, address line 2, city, postcode, and default email footer; production already had the required `PH:DAS` country/state value.

Independent production readback confirmed `wc_get_base_location()` is Philippines / Davao del Sur, tax remains enabled and based on shipping, and the `PH:DAS`, `City of Davao`, `8000` package still matches the `Davao City` shipping zone. The active cashier plugin reads the WooCommerce base location, city, and postcode; no orders, payment records, customer addresses, or messages were created. The production error log stayed at 454,465 bytes through the change.

The post-release public sitemap scan returned 73 of 73 URLs with HTTP 200 and the exact approved address, with no conflicting business-address matches. Desktop and 390-pixel mobile footer renders both showed the approved text cleanly. The temporary remote release helper was removed after verification. Issue [#129](https://github.com/john8barry/bactiveph_com/issues/129) contains the sanitized staging and production evidence.
