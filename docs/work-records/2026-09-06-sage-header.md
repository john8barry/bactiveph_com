# B Active approved header navigation

John approved the ivory and sage header mockup on 2026-09-06 with “Looks good, do it.” The header becomes compact and more legible, with the existing B Active logo, Shop/category access, Pickleball Looks, About and Contact, plus Search, Account and Bag utilities. Mobile receives the approved expandable shopping navigation.

- Owner: current B Active design task, 01a070a1-6da5-7413-956e-eae9c8af96a1.
- Severity: design improvement, normal priority.
- Scope: header-only markup, styles, interaction and narrow loader. Preserve page content, hero, Sage footer, commerce, payment and email behavior.
- Acceptance: approved composition on desktop/mobile, original logo, 44px controls, visible focus, keyboard and touch navigation, usable search, correct category/account/cart destinations, no duplicate accessible header or horizontal overflow.
- Typography dependency: issue #26 owner is preparing independently approved Rajdhani 600 headings/navigation with existing Inter body and utility controls. Coordinate one runtime writer after content/dash tasks.
- Repository start: origin/main b16557f525c9ae0be3d703bef803b2a2c9f410cc; isolated branch codex/sage-header-navigation. Canonical main is dirty and diverged; preserve all unrelated work.
- Production target: https://bactiveph.com, Blocksy child theme. Current destination revision must be verified under an explicit host window; source is not proof of live state.
- Dependencies: payment coordinator controls host queue. Staging held by payments; production story removal then signup and dash cleanup. All SSH/SFTP/host probes held until explicit environment/time grant.
- Existing gaps: dependency alerts tracked by issue #7, credentials issue #9; code scanning returns no analysis available. Latest reviewed workflows successful; no new dependencies planned.
- Rollback: save and verify exact pre-write destination files/settings; install new scoped files before switching loader, disable loader first to restore incumbent header. Fresh complete backup and verified off-server integrity precede runtime writes. Exact manifest and rollback will be reviewed before requesting a release window.
- Next control point: finish isolated implementation and independent review, then commit/PR and request serialized staging/production release.

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
