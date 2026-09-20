# In-store pickup clarification

Issue [#128](https://github.com/john8barry/bactiveph_com/issues/128) tracks a customer-fulfilment ambiguity: the Davao City collection method used “Local Pickup,” which customers could mistake for free local delivery.

## Change

- Present `In-Store Pickup` for WooCommerce pickup rates, new orders, historical order summaries, account views, emails, and cashier-created orders.
- Show “Collect your order from our Davao City store. This option does not include delivery.” beneath the pickup rate in classic cart and checkout.
- Associate the note with the shipping control after initial render and WooCommerce AJAX refreshes.
- Change only the title in shipping instance 14. Preserve its cost, tax status, zone, enabled state, order, eligibility, and stable `local_pickup` identifier.

No existing order record is rewritten and no customer email is sent by this release.

## Release and rollback

Deploy only the focused MU plugin and the one-line cashier plugin update. Apply the title migration through the private WP-CLI helper after it verifies the exact site, database, theme, zone, instance, enabled state, option name, and serialized settings hash. The helper supports check, apply, and rollback modes and refuses changed preimages.

Rollback removes the MU plugin, restores the exact cashier preimage, and runs the guarded settings rollback while the production setting still matches the recorded result hash. Do not replace the production database or overwrite unrelated live theme/plugin work.

## Live verification

Released to production on 2026-09-20 from merged PR [#131](https://github.com/john8barry/bactiveph_com/pull/131), merge commit `7d28d50fb5edae4ac02e95ec5c6e2ffcb9be278b`.

- A fresh six-component Updraft backup was downloaded off-server before deployment. Seven archives totaling 556,540,448 bytes passed SHA-256 and ZIP/gzip integrity checks.
- The guarded migration changed only `woocommerce_local_pickup_14_settings.title`; its recorded serialized hash moved from `34df324724468001bd255989dad90d57665b83a0c65b8bf81f52443f5d013a0c` to `0849c3bd15fde0ba930a8ad0e592a4b3f3684bc43acdb7948db8d5c9a4b1158b`. Cost remained empty, tax status remained `none`, and the method stayed enabled in Davao City zone 1.
- Production file readback matched the reviewed sources: MU plugin `9e00fd6fb14fdf08b41595b481ff6abd1d848e42a11ead90ddeb23ad170fa30f`; cashier plugin `b4c5d892216a6603439a14e423a7fc15e9e0eaf54455f1db94350d600f12cdcb`.
- CI passed the focused pickup regression suite and the real WooCommerce/HPOS cashier suite. Staging and production runtime fixtures also verified cached labels, one-option markup, new and historical order labels, account/order totals, and rendered HTML email without sending mail.
- Live desktop and 390 px mobile cart/checkout checks showed one persistent note with the exact approved copy. It remained associated with the pickup control after refresh, did not duplicate, had no horizontal overflow, and remained readable at 200% zoom.
- Live Davao checks preserved Standard Delivery at PHP 80 and Free Shipping at PHP 0 when eligible. Changing the address to Cebu removed pickup and showed only Standard Delivery (Luzon & Visayas) at PHP 180. Pickup as a sole option was verified with the production runtime template fixture.
- WordPress object cache and LiteSpeed page cache were purged. A post-purge public readback still showed the expected labels and single note. The production error log did not grow during deployment or verification, and no WordPress debug log was present.

Dependabot detail access remained unavailable with HTTP 403, so this release is not recorded as security-cleared. GitHub reported 19 pre-existing dependency alerts (10 high, 8 moderate, 1 low); [#7](https://github.com/john8barry/bactiveph_com/issues/7) remains the separate open tracking issue.

Rollback is guarded against intervening changes: remove the MU plugin only while its production hash matches the value above, restore the cashier file only while its hash matches the deployed value, and restore the pickup title only while the serialized setting matches the recorded after hash. Re-run the unchanged-shipping comparison, purge caches, and verify the public cart, checkout, and logs after rollback.
