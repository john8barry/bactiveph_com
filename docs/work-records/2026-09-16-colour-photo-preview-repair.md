# Colour photo previews across catalogue cards and product pages

Owner: B Active catalogue repair. Severity: medium, customer-facing product imagery. Related record: issue #48 and the September 15 colour editor release.

## Reproduction and cause

On the Sculpt Leggings listing card, clicking a colour navigated to the product while the card photo stayed pink. The same colour-plus-size selection on the product page did change its image, so that earlier check did not cover colour-only previews. Current Sculpt assignments have seven colours, each with one exact published variation image and no saved photo approval. The listing script was enqueued only on product pages and initialized only related cards. The existing catalogue preview resolver required a separate approval even where all matching sizes already used one photo.

The pre-release audit found the same missing approval for Eyelet Off White and Court Classic Set Lavender. At final live readback, Court Classic Lavender has a valid reviewed preview, so it does not rely on automatic inference. Warm-Up Jacket Magenta uses two different published size photos; it requires a merchant-chosen representative and is intentionally not inferred. Held products remain held.

## Acceptance and safeguards

- A reviewed custom image wins. Otherwise all published variations for that exact colour must agree on one valid, locally owned image attachment before an automatic preview appears.
- Missing images, different size images, wildcard colours, no matching variation, private or password-protected products, and catalogue holds fail closed.
- The same resolver feeds product colour-only previews, listing cards and related cards. A complete colour-and-size selection still uses WooCommerce's own variation image.
- Cards initialize on shop, collection, search and dynamically inserted lists; a failed image load keeps the product link usable. Keyboard, modified-click and Select options behaviour remain intact.
- The editor distinguishes reviewed custom, automatic, and manual-review-needed photo states. A selected custom photo is the image shown beside its approval checkbox, even while an automatic storefront photo is active.
- No product, stock, price, variation or approval metadata is written by this change.

## Verification and delivery

PHP resolver/editor and JavaScript card/gallery tests cover precedence, ambiguity, image and access failures, rapid/keyboard clicks, collection cards and cloned/dynamic cards. A network-isolated WooCommerce clone checks new-product colour saves, shared photo inference, added/changed size photos, save/reopen and competing edits with a rolled-back transaction. Independent read-only review caught and cleared the editor approval-target mismatch and copied card marker failure before release.

## Live delivery and readback (September 16, 19:00–19:10 UTC)

PR #110 passed its catalogue contract check and independent read-only code review, then merged at `2297f49fefdda1bb37aaff22d56a9bf05273f30e` from reviewed source `e3e58d66134bddd1f657318ce8b9fe9f945f7390`. Five live theme files matched the exact prior-main hashes. Their originals were privately backed up, the candidate PHP files passed syntax checks on the host, and all five installed files independently matched candidate SHA-256 hashes. Four sampled product/variation snapshots (95, 238, 573, 831) were identical before and after the source deployment. No product metadata or commerce rows were deliberately changed. LiteSpeed reported a successful full cache purge.

Fresh anonymous collection and product pages returned HTTP 200 and the new card script. Subsequent anonymous collection requests were cache hits with correct preview links. Public JS and CSS bytes exactly matched the release. On desktop and at 390×844 mobile width, all seven Sculpt collection-card clicks loaded their mapped photo without navigation, and all seven colour-only product selections loaded the same photo. Visual inspection confirmed Black, Blue, Blush Pink, Fuchsia, Gray, Olive Green and Purple photos match their colour labels. Select options still links to the product. Eyelet Off White and Court Classic Lavender worked on both their product pages and collection cards; an already-reviewed Court Dress preview also worked. Jacket Magenta had no automatic product-page overlay or card-preview data and retained a product link. A full Sculpt colour-and-size selection cleared the overlay and returned control to the native gallery; the direct WooCommerce variation endpoint returned in-stock data for Black/S. No order was placed.

The published variable-product audit covered 20 products and 77 offered colour rows: 52 reviewed, eight automatically inferred, and 17 without a colour-only preview. Sixteen of the latter belong to the existing held products 56, 160 and 211. The sole unheld exception is Warm-Up Jacket Magenta (product 573): its published size photos differ, so the merchant must choose and confirm the intended representative in **Products → Edit The Warm-Up Jacket → Product data → Colours & photos → Magenta**. Existing holds were not released. This is not a reason to alter its variation images automatically.

Private source backup, before/after hashes, four-product snapshots and conditional rollback script are under the owner's Application Support `BactivePH/colour-preview-20260916`. Rollback restores only the five release files if every current file still matches the release hash; it refuses later edits. No rollback was needed. The 10-minute live check found no failing sampled page, missing preview attachment or new browser-console error; host error-log coverage was not independently verified.
