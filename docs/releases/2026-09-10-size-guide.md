# Size guide repair

## Scope

Issue [#34](https://github.com/john8barry/bactiveph_com/issues/34) tracks a shared storefront defect: product-page Size Guide links changed the URL hash but did not open the existing dialog. The standalone Size Guide page also lacked the chart.

This change:

- gives every product trigger a real `/size-guide/` fallback URL;
- loads a dedicated, product-only dialog script without replacing the drifted production `custom.js` cart behavior;
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

Production deployment and live readback remain required before closing issue #34.

## Rollback

Revert the release commit and redeploy the previous child-theme `functions.php` and `custom.css`; remove `assets/js/size-guide.js`. The product links will continue to reach the standalone `/size-guide/` page through their fallback URL only if the markup portion of this release remains deployed.
