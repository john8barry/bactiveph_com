# B Active sage header release

Issue [#27](https://github.com/john8barry/bactiveph_com/issues/27) implements the approved ivory header with a prominent original logo, clear Shop navigation and Search, Account and Bag utilities. Mobile opens a readable category menu.

## Candidate and integration

The exact runtime assets are in `2026-09-06-sage-header-bundle.json`. The package combines the four new header files with the four approved typography assets from PR #28 and the exact enqueue-only functions addition in `2026-09-06-brand-typography-manifest.json`. The header uses Blocksy's rows-render filter and does not replace the parent theme, global styles/scripts, database menu configuration, hero, footer or commerce settings.

Both child source mirrors are maintained. The header itself does not modify functions.php; the combined typography addition must be applied to the freshly read destination after concurrent punctuation work. Never deploy the repository's entire functions.php.

## Validation

PHP syntax, offline guard/markup regressions, JavaScript syntax and mirror checks pass locally. The focused GitHub workflow repeats these checks. Desktop and mobile browser review covered 1440, 1280, 1124, 390 and 320 CSS pixels, dropdown/search states and Escape/focus behavior. All 13 existing destinations returned HTTP 200. An independent visual review scored both requested fixes resolved; an independent source/security review accepted the final fallback correction.

## Release control and rollback

Runtime publication is pending an explicit environment/time window from the payment coordinator. Save exact destination originals and protected file/settings hashes, take a fresh complete backup, and verify the off-server six-component copy before any deployment.

Install all inert header and typography assets first, PHP-lint before activation, apply only the exact typography enqueue to fresh functions.php, then activate the new MU loader last. Recheck exact hashes and protected state before every switch. Production requires accepted staging readback and desktop/mobile captures of this exact bundle.

Rollback first moves the new header MU loader out of the public plugin directory, restoring Blocksy's incumbent header. Remove only the exact typography addition from the latest functions.php after proving no later writer changed it. Leave inert assets in place. Do not restore an old whole functions file or database. Reconcile any ambiguous write before retrying.

## Live receipt

Pending. A local preview or merged PR does not establish a staging or production result.
