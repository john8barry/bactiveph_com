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

Pending release. Record backup evidence, deployed hashes, guarded setting readback, browser screenshots, unchanged non-pickup methods, cache invalidation, and log review here before closing the issue.
