# B Active sage header release

Issue [#27](https://github.com/john8barry/bactiveph_com/issues/27) implements the approved ivory header with a prominent original logo, clear Shop navigation and Search, Account and Bag utilities. Mobile opens a readable category menu.

## Source and integration

Source [PR #35](https://github.com/john8barry/bactiveph_com/pull/35) merged at `ff9eb2a`. [CI run 34020824226](https://github.com/john8barry/bactiveph_com/actions/runs/34020824226) passed. Staging is accepted. Production is live: activation completed at **2026-09-06 11:59:33 UTC**, initial verification passed at **12:00:11 UTC**, and bounded monitoring passed at **12:07:15 UTC**.

The exact runtime assets are in `2026-09-06-sage-header-bundle.json`. The package combines the four new header files with the four approved typography assets from PR #28 and the exact enqueue-only functions addition in `2026-09-06-brand-typography-manifest.json`. The header uses Blocksy's rows-render filter and does not replace the parent theme, global styles/scripts, database menu configuration, hero, footer or commerce settings.

Both child source mirrors are maintained. The header itself does not modify functions.php; the combined typography addition is applied to the freshly read destination after concurrent punctuation work. Never deploy the repository's entire functions.php.

## Validation

PHP syntax, offline guard/markup regressions, JavaScript syntax and mirror checks pass locally. The focused GitHub workflow repeats these checks. Desktop and mobile browser review covered 1440, 1280, 1124, 390 and 320 CSS pixels, dropdown/search states and Escape/focus behavior. All 13 existing destinations returned HTTP 200. An independent visual review scored both requested fixes resolved; an independent source/security review accepted the final fallback correction.

## Release control and rollback

Production reached activation with fresh destination originals, protected file/settings hashes and an independently verified complete backup. Bounded monitoring is complete; the deployment owner reported TERMINAL and closed all SSH sessions.

Install all inert header and typography assets first, PHP-lint before activation, apply only the exact typography enqueue to fresh functions.php, then activate the new MU loader last. Recheck exact hashes and protected state before every switch. Production requires accepted staging readback and desktop/mobile captures of this exact bundle.

Rollback first moves the new header MU loader out of the public plugin directory, restoring Blocksy's incumbent header. Remove only the exact typography addition from the latest functions.php after proving no later writer changed it. Preserve the independently accepted care-line change. Leave inert assets in place. Do not restore an old whole functions file or database. Reconcile any ambiguous write before retrying.

## Staging receipt

The coordinator's staging receipts record activation at **2026-09-06 11:16:35 UTC**. The combined eight assets and the fresh functions.php result (`ee8107` hash prefix) were installed while preserving the independently accepted care-line change. Continuation retained seven already installed inert files, reconciled the intervening care change, and applied the narrow addition to fresh functions.php. The release helper's compatibility fix invokes PHP 8.2 directly; no old whole-file functions restore was used.

- Destination verification (`49144ed` receipt hash prefix): all four normal pages returned HTTP 200 with one header; all six public asset responses matched the expected bytes; no PHP warning/fatal output or new error-log entries were observed in the verification interval.
- Commerce verification after activation (`b7ad759` receipt hash prefix): passed.
- Browser acceptance (`9ca78ac` visual receipt hash prefix): actual staging desktop at 1440px and mobile at 390px and 320px passed menu, Shop, Search, Escape, focus, at least 44px targets and no horizontal overflow checks. Independent screenshot review and the typography owner both passed the result.

These receipts establish the staging result only. The production milestone is recorded separately below.

## Production activation and initial verification

The coordinator's production receipts record activation at **2026-09-06 11:59:33 UTC** and initial verification at **12:00:11 UTC**. All eight bundle files matched their expected bytes. Only the typography enqueue was added to fresh functions.php, changing its hash prefix from `0f356` to SHA-256 `f892a3bc1325dea32b72245a96357c4aa910ba3094b9d410f8c7b5334f2634c9`. The footer (`ebaacea`), compact hero (`c88f46`) and associated MU file (`96c868`) remained unchanged; those three references are hash prefixes.

- Backup and snapshot receipt hash prefixes `698e47` and `2943eb9`: all six backup components passed full SHA-256 and CRC/gzip integrity verification, totaling 330,462,434 bytes. Independent backup verification passed.
- Deployment receipt hash prefix `01eb92d`; initial verification receipt hash prefix `8d77819`: four normal pages returned HTTP 200 with exactly one header, all six public asset responses matched expected bytes, and no PHP warning/fatal output or new error-log entries were observed in the verification interval.
- Commerce-after receipt hash prefix `cf45327`: the bounded commerce preservation check passed.
- Independent public typography review passed eight renders across four pages at 1440px and 390px. Independent header review passed actual public desktop/mobile captures. Logged-in browser checks at 1440px, 390px and 320px passed navigation, Shop, Search, Escape/focus, at least 44px targets and no horizontal overflow.

## Bounded monitoring

Final verification passed at **2026-09-06 12:07:15 UTC** after a 424.155-second monitoring interval. All four normal pages returned HTTP 200 with exactly one header, all six public asset responses matched expected bytes, and there was no error-log delta or PHP warning/fatal output. The page-cache queue was empty. The bounded commerce preservation check passed again at **12:08 UTC**.

Receipt hash prefixes: final verification `12b7c088`, monitoring `10a5ff149`, visual acceptance `ffd0f64d`, and cache verification `935da2e4`.

Independent final coordinator readback passed at 12:09 UTC: all eight files and the typography-only functions change matched; protected commerce, order and stock state, settings, plugins, logs, footer and homepage remained unchanged. The release window was returned and all SSH connections closed. Header release acceptance is complete; this does not establish broader payment or newsletter readiness.
