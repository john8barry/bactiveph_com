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
