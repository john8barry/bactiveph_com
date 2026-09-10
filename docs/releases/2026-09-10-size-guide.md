# Size guide repair

## Scope

Issue [#34](https://github.com/john8barry/bactiveph_com/issues/34) tracks a shared storefront defect: product-page Size Guide links changed the URL hash but did not open the existing dialog. The standalone Size Guide page also lacked the chart.

This change:

- gives every product trigger a real `/size-guide/` fallback URL;
- loads dedicated size-guide CSS and a product-only dialog script without replacing the drifted production `custom.css` or `custom.js` behavior;
- supports native dialog opening, close-button and Escape dismissal, backdrop dismissal, and focus restoration;
- renders one shared size-chart definition in both the product dialog and the standalone page;
- adds responsive overflow containment plus table caption and header scopes; and
- removes the rendered backslash from “you're.”

The existing S to XL measurement values are unchanged.

## Verification

- `php -l` passes for both mirrored child-theme `functions.php` files.
- `node --check` passes for `assets/js/size-guide.js`.
- `node --test tests/size-guide.test.cjs` passes the behavior and markup contracts.
- The Impeccable detector reports no findings for the changed UI files.
- A local browser fixture passed at desktop width and in a 390 by 844 mobile frame, including Escape and close-button focus return and horizontal table scrolling.
- Pull request [#57](https://github.com/john8barry/bactiveph_com/pull/57) merged to `main` as `2b903bd9efbb862d8de3999e116edbd6e01e49fe` after both required checks and independent review passed.
- A fresh complete UpdraftPlus production backup was created during the release and all six sets (database, plugins, themes, uploads, mu-plugins, and other files) were downloaded off-server and passed SHA-256 plus archive-integrity verification before installation.
- Production installation merged the reviewed size-guide changes into the current live `functions.php`, rather than overwriting unrelated production drift. The final hashes are `84ef751a784187d8b876b1d6619475ebeda94715ee4e587f7cc0d26c5710e422` for `functions.php`, `3a22b7eb241fae702c90591421e1df43d9822ee47f8afd98c573dc8ab58b1378` for `size-guide.css`, and `e962cbb20e56f1412f2b186f4d59cca0067394f950a6fc92582123df8e107137` for `size-guide.js`.
- The selected-site LiteSpeed page cache was invalidated through its installed page-cache-only API. The temporary one-shot delivery helper was removed immediately afterward.
- All 17 product URLs in the production product sitemap passed ordinary-URL checks for the real fallback link, dialog markup, dedicated CSS and JavaScript, and corrected apostrophe. The standalone `/size-guide/` page passed with one H1, one chart, and no product-only script or dialog.
- Live browser verification confirmed dialog open, close-button and Escape dismissal, unchanged URL, and focus restoration. Public CSS and JavaScript bytes matched the installed hashes, shared `custom.css` and `custom.js` stayed unchanged, and the bounded error-log check found no new fatal, parse, or uncaught PHP errors.

Production deployment and live readback completed on September 10, 2026.

## Rollback

Revert the release commit and apply the inverse size-guide patch to the latest production `functions.php`; remove `assets/css/size-guide.css` and `assets/js/size-guide.js`. Do not restore old whole-file copies over newer production changes. The product links will continue to reach the standalone `/size-guide/` page through their fallback URL only if the markup portion of this release remains deployed.
