# B Active approved header navigation

John approved the ivory and sage header mockup on 2026-09-06 with “Looks good, do it.” The header becomes compact and more legible, with the existing B Active logo, Shop/category access, Pickleball Looks, About and Contact, plus Search, Account and Bag utilities. Mobile receives the approved expandable shopping navigation.

- Owner: current B Active design task, 01a070a1-6da5-7413-956e-eae9c8af96a1.
- Severity: design improvement, normal priority.
- Scope: header-only markup, styles, interaction and narrow loader. Preserve page content, hero, Sage footer, commerce, payment and email behavior.
- Acceptance: approved composition on desktop/mobile, original logo, 44px controls, visible focus, keyboard and touch navigation, usable search, correct category/account/cart destinations, no duplicate accessible header or horizontal overflow.
- Typography dependency: approved PR #28 supplies Rajdhani 600 headings/navigation with existing Inter body and utility controls. The combined header/typography package passed staging and typography-owner acceptance.
- Repository start: origin/main b16557f525c9ae0be3d703bef803b2a2c9f410cc; isolated branch codex/sage-header-navigation. Canonical main was dirty and diverged at the start; preserve all unrelated work.
- Source milestone: [PR #35](https://github.com/john8barry/bactiveph_com/pull/35) merged at ff9eb2a; [CI run 34020824226](https://github.com/john8barry/bactiveph_com/actions/runs/34020824226) passed.
- Production target: https://bactiveph.com, Blocksy child theme. Live: activation completed at 2026-09-06 11:59:33 UTC; initial verification passed at 12:00:11 UTC and bounded monitoring passed at 12:07:15 UTC.
- Dependencies: payment coordinator controls the serialized host queue. Staging is accepted and production monitoring is complete; the deployment owner reported TERMINAL with all SSH sessions closed.
- Existing gaps: dependency alerts tracked by issue #7, credentials issue #9; code scanning returns no analysis available. Latest reviewed workflows successful; no new dependencies planned.
- Rollback: exact destination originals and a fresh complete backup are preserved. Disable the new loader first to restore the incumbent header; remove only the exact typography addition from fresh functions.php while preserving the accepted care change. Never restore an old whole functions file or database.
- Next control point: commit the final release records and reconcile issue #27 against the completed live acceptance evidence.

## Visual contract

Use the approved compact ivory header with sage baseline, generous horizontal grouping and enlarged existing logo. Navigation uses the independently approved brand heading token. Utility icons use one consistent outline stroke. Desktop categories open below Shop; mobile categories are initially visible when the main menu opens. Preserve the existing hero and footer.

Issue: https://github.com/john8barry/bactiveph_com/issues/27

## Direction contract

THESIS: A compact ivory header gives B Active shoppers a clear route to the collection while making the existing athletic logo prominent.

OWN-WORLD: Preserve the approved original B Active mark, ivory #f9f7f4, ink #242222 and sage #829677 rules. Approved Rajdhani 600 carries primary navigation; Inter carries categories, search and utility labels.

STORY: Recognize B Active, open Shop or a primary destination, and reach search, account or the shopping bag immediately. Mobile opens directly to the collection list.

FIRST VIEWPORT: One logo, four primary destinations, and three aligned outline utilities above the unchanged page content. Mobile centers the logo between menu and bag controls; its expanded menu remains a natural scrollable page.

FORM: The user-approved desktop header and mobile menu comp, 2026-09-06, is binding. This is a narrow extension of the established brand and shipped Sage footer; no new visual-world selection. Actual hero imagery/content and all footer/commerce/provider settings remain outside header scope.

## Local acceptance evidence

- Standalone PHP guard/markup checks pass: incomplete bundle and wrong destination/theme/admin/customizer retain the original header; search uses GET with unique device labels; no duplicate header landmark. PHP 8.3 lint and JavaScript syntax pass.
- Impeccable detector ran once over changed markup/CSS/JS and returned no findings.
- Browser captures: 1440x900, 1280x720, 390x900, 320x900 and 1124x916 mockup-width checkpoint. Desktop Shop/search open by keyboard; Escape closes and returns focus. Mobile categories open initially; Escape closes menu. No horizontal overflow at 390/320.
- All 13 header destinations returned 200 through ordinary public GET on 2026-09-06, including the existing category redirects. Original logo thumbnails are valid; an initial local-preview asset failure was resolved by setting no-referrer in the preview harness only.
- Independent source integration review confirmed Blocksy rows-render preserves parent header/shell/body/drawers; typography owner accepted exact Rajdhani/Inter selector mapping.
- Independent finish review requested larger logo matching approved comp. Corrected scoped image 88px desktop / 74px mobile plus anchor height:auto; at 1124px the logo bottom is 98px within the 110px header. Recaptured same evidence for final verdict.
- Local PHP 8.3 CLI was installed through Homebrew for offline checks; no PHP service started. No production or staging connection has occurred during local preparation.

Final independent visual verdict: both requested fixes (logo scale and persistence) resolved; all eight captures valid. Final code/security review accepted the corrected title fallback and found no remaining source findings. These are local review results, not live deployment proof.

A narrow Sage header workflow runs PHP syntax, guard/markup regressions, JavaScript syntax and source-mirror checks. No application dependency was added.

## Staging acceptance

The coordinator's receipts record activation on 2026-09-06 at 11:16:35 UTC. Staging received the combined eight header/typography assets and a fresh functions.php result (hash prefix ee8107), preserving the independently accepted care-line change. Resumption retained seven inert files already installed, reconciled the care change, and applied only the typography addition to fresh functions.php. The release helper now invokes PHP 8.2 directly. Rollback preserves the care change and never restores an old whole functions file.

- Destination receipt hash prefix 49144ed: four normal pages returned HTTP 200 with exactly one header; all six public asset responses matched expected bytes; no PHP warning/fatal output or new error-log entries in the verification interval.
- Commerce-after receipt hash prefix b7ad759: passed.
- Visual acceptance receipt hash prefix 9ca78ac: actual staging desktop at 1440px and mobile at 390px/320px passed menu, Shop, Search, Escape/focus, at least 44px targets and no horizontal overflow checks.
- Independent screenshot reviewer: passed. Typography owner: passed.

These staging receipts do not close the production acceptance criteria. The [release record](../releases/2026-09-06-sage-header.md) carries the deployment sequence, rollback and next control point.

## Production activation

The coordinator's receipts record activation on 2026-09-06 at 11:59:33 UTC and initial verification at 12:00:11 UTC. All eight bundle files matched expected bytes. The typography-only addition to fresh functions.php changed its hash prefix from 0f356 to SHA-256 f892a3bc1325dea32b72245a96357c4aa910ba3094b9d410f8c7b5334f2634c9. Footer, compact hero and the associated MU file remained unchanged (hash prefixes ebaacea, c88f46 and 96c868 respectively).

- Backup receipt 698e47 and snapshot receipt 2943eb9 are hash prefixes: six components, 330,462,434 total bytes, full SHA-256 and CRC/gzip integrity checks passed; independent backup verification passed.
- Deployment receipt hash prefix 01eb92d; initial verification receipt hash prefix 8d77819: four normal pages returned HTTP 200 with exactly one header; six public asset responses matched expected bytes; no PHP warning/fatal output or new error-log entries during the verification interval.
- Commerce-after receipt hash prefix cf45327: bounded preservation check passed.
- Independent public typography review: eight renders over four pages at 1440px/390px passed. Independent public header screenshot review passed. Logged-in checks at 1440px/390px/320px passed navigation, Shop, Search, Escape/focus, at least 44px targets and no horizontal overflow.

## Bounded monitoring

Final verification passed on 2026-09-06 at 12:07:15 UTC after a 424.155-second monitoring interval. All four normal pages returned HTTP 200 with exactly one header; six public asset responses matched expected bytes; no error-log delta or PHP warning/fatal output was observed. The page-cache queue was empty. The bounded commerce preservation check passed again at 12:08 UTC.

Receipt hash prefixes: final verification 12b7c088, monitoring 10a5ff149, visual acceptance ffd0f64d and cache verification 935da2e4. The deployment owner reported TERMINAL and closed all SSH sessions.

Independent final coordinator readback passed at 12:09 UTC: all eight files and the typography-only functions change matched; protected commerce, order and stock state, settings, plugins, logs, footer and homepage remained unchanged. The release window was returned and all SSH connections closed. Header release acceptance is complete; this does not establish broader payment or newsletter readiness.
