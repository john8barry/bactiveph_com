# Sticky Sage header

Work record: [issue #139](https://github.com/john8barry/bactiveph_com/issues/139). Owner: John Barry / B Active.

The ivory-and-sage header stays at the viewport top and compresses after 72px of scroll: desktop 110→78px, mobile 96→68px. It expands within 16px of the top. A 220ms ease-out transition preserves the logo proportions, navigation typography, category ordering and minimum 44px targets. A stable expanded-height spacer prevents document jumps. Current size stays locked during visible-header focus or an open menu/search disclosure.

## Implementation and accessibility

Only the existing header CSS/JavaScript are deployed. Both source mirrors remain identical. A scoped fixed enhancement avoids the existing body overflow rules; no global overflow, template, PHP, database, product, order or payment changes are required. The existing filemtime asset versioning applies.

Mobile navigation and desktop panels scroll within the available viewport. ResizeObserver updates clearance as the bar animates. VisualViewport events constrain panels when a mobile keyboard changes the visible area; the layout viewport controls the below-240px normal-flow fallback. Safe-area insets, absolute/fixed WordPress admin bars, restored scroll, pageshow, native disclosure behavior, nested Escape and focus return are preserved. Reduced motion disables size transitions. Without JavaScript the original normal-flow navigation remains usable.

References: [MDN positioning](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Properties/position), [web.dev animation performance](https://web.dev/articles/animations-guide), [W3C focus clearance](https://www.w3.org/WAI/WCAG22/Techniques/css/C43).

## Validation

Run `php tests/sage-header-contract.php`, `php tests/footer-navigation-contract.php`, JavaScript syntax checks, mirror comparisons and `git diff --check`. Run behavior tests with `NODE_PATH="$PWD/tests/catalogue-runtime/node_modules" node --test tests/sage-header.test.cjs` after `npm ci --ignore-scripts --prefix tests/catalogue-runtime`. Sage CI includes these tests using the existing locked test-only jsdom runtime.

Integrated public-homepage fixture checks verified 320, 390, 768, 999, 1000, 1280 and 1440 CSS-pixel widths: exact expanded/compact heights, stable content document position, fixed top and no horizontal overflow. Portrait/landscape menus, lower mobile search, desktop Men → Tops, nested Escape, reduced motion, no-JS markup, short viewport, skip link and forward/back keyboard focus passed. Simulated WordPress admin bars produced 46→0px mobile and 32px desktop offsets. Keyboard visual-viewport geometry is covered in unit tests; physical-device keyboard behavior is not independently certified.

The Impeccable detector reports two layout-transition warnings for the intentional height transitions. These are confined to an out-of-flow header with a stable spacer. A Chromium transition sample measured approximately 2.1ms total layout, 32ms style recalculation and 2.7ms script across the sampled 286ms interval; no page-content displacement occurred. This is bounded local evidence, not a field Core Web Vitals claim. No animation dependency was added.

Existing security issues #7 and #9 remain open. Preflight found 19 existing dependency alerts in parent/inactive themes or development tooling, no open secret-scanning alerts, and no code-scanning analysis. This release neither includes those dependencies nor claims portfolio security clearance.

## Release and rollback

A fresh complete production backup must qualify before implementation: all six component kinds, successful fresh Updraft job, verified off-server sizes/hashes and ZIP/gzip integrity. This run qualified seven archives totaling 563,199,988 bytes, plus exact two-file preimages and modes in a private recovery directory.

After independent review and green required checks, obtain the exclusive `/home/waypmvhk/.bactiveph-production-release.lock` directory. Verify production identity and both current hashes against the preimages, stage only the reviewed CSS/JS outside the web root, test atomic rename support, preserve modes, then replace and read back each file. Reject drift. Keep the lock through public acceptance and five-minute monitoring.

Because the header is global and asset URLs use filemtime, purge LiteSpeed page cache once, verify ordinary public URLs emit the current asset versions, and verify served asset bytes. Do not purge unrelated object caches or Cloudflare globally. Verify homepage/shop/product/cart/checkout routing, desktop/mobile interactions, fresh critical errors and final hashes.

Rollback only when live asset hashes still equal this release: atomically restore their exact preimages, invalidate applicable page cache and repeat live checks. Preserve any later writer changes and all database/order state. A full backup is disaster recovery, not the routine UI rollback.

Production acceptance remains pending until a receipt is appended.
