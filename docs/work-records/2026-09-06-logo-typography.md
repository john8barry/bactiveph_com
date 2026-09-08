# Logo-aligned website typography

Status: John approved publication on 2026-09-06; source and release preparation in progress. Live deployment remains pending.
Tracking: https://github.com/john8barry/bactiveph_com/issues/26.
Owner: Match logo font style, task 01a07597-56c1-7eb3-afed-353b9671f773.
Severity: low visual inconsistency. Request: harmonize the website typography with the sporty B Active logo. John accepted the website-font direction and requested it become the permanent brand standard.

## Evidence and scope

The screenshot and current public homepage show Fraunces 400 in the sage footer's explicitly overridden h2/h3, while global production headings use Inter. This candidate uses Rajdhani 600 for headings and desktop/mobile navigation, retaining Inter body, inputs, prices, and buttons. It changes no logo, copy, colors, layout, forms, or transaction behavior.

Isolated branch: codex/logo-aligned-typography, based on origin/main b16557f. Canonical main at 42e13c3 has unrelated dirty work and was preserved. The current public CSS is not the repository CSS: do not deploy the source custom.css or entire theme.

Read-only SSH verified production Home/Site URL https://bactiveph.com, database waypmvhk_bactwp, and active child theme blocksy-child under /home/waypmvhk/bactiveph.com. Preflight file hashes:

- functions.php: 949a12f435e8ce6efec5b8301465f80017143e3cb410ef920eedb5d893383b56
- assets/css/custom.css: b507b3165692e450fa385133954878ad44628509233c3a3817146c37a846ff57
- template-parts/footer.php: 7b6855c920ebaed93ce0d6aba7d55dd96d66a8cdc56899324565c4b210ac0129

These are dated preflight observations, not locks or release authorization. The concurrent dash cleanup owns separate functions.php edits. Re-read the latest live file and apply only the new enqueue function; never overwrite with this repository's older full file.

## Implementation

- New assets/css/brand-typography.css overrides both ordinary headings and the sage footer's later important rules.
- New self-hosted Rajdhani 600 Latin and Latin Extended WOFF2 files total 16,608 bytes; font-display swap, fallback Inter/sans-serif. Preserve the accompanying SIL OFL license.
- functions.php in both repository mirrors enqueues the new stylesheet after existing styles at priority 30. Missing CSS leaves existing typography intact.
- Existing Fraunces delivery is untouched to avoid unrelated cleanup and support rollback.

Font sources: https://fonts.google.com/specimen/Rajdhani and Google Fonts CSS API; license from https://raw.githubusercontent.com/google/fonts/main/ofl/rajdhani/OFL.txt.

## Validation

Browser preview routes a saved public homepage document and only the candidate stylesheet/fonts locally. No remote pages or assets were altered. Verified 1440px, 390px, and 320px: all 17 visible headings resolve to Rajdhani 600, font load succeeds, footer body remains Inter, no horizontal page overflow. Desktop and mobile screenshots inspected after scrolling to load lazy images. This proves candidate rendering, not a deployed result.

Independent source/DOM review confirmed heading specificity and found two navigation selectors that initially matched no elements; corrected to body[data-header] .ct-header .menu > li > a and body #offcanvas .mobile-menu a, with separate DOM/computed-font verification.

PHP lint on both source mirrors via authenticated remote PHP stdin: No syntax errors detected in Standard input code. No remote file was written or executed. CSS type detector returned []; git diff --check passed. No payments, emails, subscriptions or order flows exercised.

Evidence directory on this Mac: /Users/johnbarry/.codex/visualizations/2026/09/06/01a07597-56c1-7eb3-afed-353b9671f773/typography-preview. Includes public before.html/CSS, responsive screenshots, verification.json, nav-verification.json, and rendering script.

## Release and rollback control

John confirmed the preview and authorized production publication. Next control point: reviewed source and the coordinator-granted combined header/typography writer window. Project guardrail section 7 explicitly requires human approval before production deployment. Staging verification and fresh verified backup remain required before any server changes. Coordinate the serialized host window with Fix PayMongo production payments and the dash cleanup owner; no writer lane is held here.

Release only the new CSS/fonts/license and an enqueue-only patch against freshly read functions.php. Verify staging, then approved production with authenticated file hashes, ordinary public asset URLs, computed fonts on home/shop/product/help/footer at desktop/mobile, cache readback, and error monitoring. Do not close the durable record before those acceptance checks pass.

Rollback: remove only the new enqueue function/hook from the latest live functions.php after verifying no later writer superseded it; purge affected caches and confirm original typography. Added inert font/CSS assets can remain. Do not restore a full database or an old whole functions.php.

Open unrelated security issues #7/#9 remain owned by maintenance/payment lanes. Dependabot API read in this session returned 403, so no clean-security claim is made. No GitHub release/deployment objects were present in the queried API. COS and relevant site lanes received the local-only milestone. At John's explicit request, permanent memory update note saved at /Users/johnbarry/.codex/memories/extensions/ad_hoc/notes/2026-09-06T07-31-48Z-bactive-approved-typography.md. Brand-guide Markdown/HTML and theme agent context updated to the approved type roles.
