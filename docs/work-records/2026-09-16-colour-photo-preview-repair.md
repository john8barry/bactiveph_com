# Colour photo previews across catalogue cards and product pages

Owner: B Active catalogue repair. Severity: medium, customer-facing product imagery. Related record: issue #48 and the September 15 colour editor release.

## Reproduction and cause

On the Sculpt Leggings listing card, clicking a colour navigated to the product while the card photo stayed pink. The same colour-plus-size selection on the product page did change its image, so that earlier check did not cover colour-only previews. Current Sculpt assignments have seven colours, each with one exact published variation image and no saved photo approval. The listing script was enqueued only on product pages and initialized only related cards. The existing catalogue preview resolver required a separate approval even where all matching sizes already used one photo.

The broader live audit found the same missing approval for Eyelet Off White and Court Classic Set Lavender. Warm-Up Jacket Magenta uses two different published size photos; it requires a merchant-chosen representative and is intentionally not inferred. Held products remain held.

## Acceptance and safeguards

- A reviewed custom image wins. Otherwise all published variations for that exact colour must agree on one valid, locally owned image attachment before an automatic preview appears.
- Missing images, different size images, wildcard colours, no matching variation, private or password-protected products, and catalogue holds fail closed.
- The same resolver feeds product colour-only previews, listing cards and related cards. A complete colour-and-size selection still uses WooCommerce's own variation image.
- Cards initialize on shop, collection, search and dynamically inserted lists; a failed image load keeps the product link usable. Keyboard, modified-click and Select options behaviour remain intact.
- The editor distinguishes reviewed custom, automatic, and manual-review-needed photo states. A selected custom photo is the image shown beside its approval checkbox, even while an automatic storefront photo is active.
- No product, stock, price, variation or approval metadata is written by this change.

## Verification and delivery

PHP resolver/editor and JavaScript card/gallery tests cover precedence, ambiguity, image and access failures, rapid/keyboard clicks, collection cards and cloned/dynamic cards. A network-isolated WooCommerce clone checks new-product colour saves, shared photo inference, added/changed size photos, save/reopen and competing edits with a rolled-back transaction. Independent read-only review caught and cleared the editor approval-target mismatch and copied card marker failure before release.

Deployment receipt, exact source hashes, anonymous mobile and desktop checks, cache readback, monitoring and rollback outcome are appended after live delivery. Rollback is source-only and conditional on no later file change. Product data is preserved.
