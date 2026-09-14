# Catalogue card row alignment

Tracking: [catalogue visual standardization, issue 48](https://github.com/john8barry/bactiveph_com/issues/48).

Owner: the catalogue release task. This follow-up is local only; no provider write, release-option change, product edit, push or deployment is included.

## Problem and scope

The approved collection plan requires aligned product titles and prices. In the refreshed Flow related grid, the coordinating task measured the Courtline card's action row 85px above the three cards without colour previews. The native card order is figure, title, category metadata, actions, then optional previews. An automatic top margin on the actions bottom-aligns the entire remaining stack, so preview height changes the price position.

Both mirrored `collection-visuals.css` files now remove that automatic margin. Uniform enhanced grids share five content tracks with CSS subgrid, independently for each four-column desktop or two-column narrow row. Optional category/preview slots and wrapping titles/colour names use the space required by that row. The Blocksy type-2 figure/action width expansion is reset inside this shared layout so those children fit their card.

The subgrid rule activates only when every direct grid child is an enhanced card with the known native child structure. Mixed native/enhanced grids and cards with additional native fields keep flex layout. Browsers without subgrid support use the same flex fallback. No content is hidden or reordered. PHP, colour review gates, product IDs, prices, cart behavior and release settings are unchanged.

## Verification

- Base: merged PR 50, `7e5718bcd76afb8718b745ca2412da6565933773`.
- `php tests/collection-visuals.php`: activation, palette, escaping, content preservation and independent related-card release gates passed.
- Both CSS mirrors match byte for byte. Impeccable layout detector returns no findings.
- The self-contained browser fixture `tests/collection-visuals.layout.html` loads shipped Blocksy/child CSS and fonts with synthetic data. It covers 1440, 1024, 768, 390 and 320px frame viewports, eight cards over multiple rows, missing category/previews, wrapped names, and mixed/extra-native-child fallback. It makes no commerce or third-party request.
- Initial rendered pass measured 0px action and price spread at all five widths, with no row overlap. It exposed the inherited type-2 child width expansion, producing horizontal overflow. The final CSS correction confines those children to the card width.
- Final fresh Chrome load confirms **7/7 passed** for CSS SHA-256 `6df1e0d54c4e488be7ba5efc4fddd5b09c702c19eeee1b23606d7d1b5f7f6d63`. All five shared frame widths have 0px price/action spread and no row or preview overlap. Scroll/client widths match exactly: 1425/1425, 1009/1009, 753/753, 375/375 and 305/305px respectively (the iframe's vertical scrollbar uses 15px). This resolves the earlier inherited-width overflow and browser-readback limitation.
- Both guard cases pass: a native card mixed into the grid and an additional unknown native child keep every card on flex, without horizontal overflow or row overlap. These fallback cases preserve content flow; they do not guarantee shared price alignment. Older browsers without subgrid support were not separately run.
- The fixture's optional baseline diagnostic now forces its flex override past the guarded selector's specificity. It simulates the earlier flex/auto-margin mechanism, rather than a complete before-release CSS snapshot. Fresh browser readback confirms flex display and fails all five shared alignment cases: price/action spread is 129, 129, 85, 129 and 173px respectively. This is a test-only correction; deployable CSS is unchanged.

## Next control point and rollback

The local fixture is cleared. The coordinating task owns restored real-product desktop/mobile acceptance, integration and guarded release. Fixture measurements do not prove deployed WordPress behavior or live acceptance.

Release only the reviewed CSS mirrors through the catalogue task's existing guarded process. Roll back by restoring their preceding exact bytes after expected-hash checks. No database or commerce rollback is needed.
