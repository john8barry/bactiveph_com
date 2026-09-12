# Homepage signup section removal — 2026-09-06

Owner: homepage signup removal task `01a07596-b90a-7960-ab67-1d5b71b91987`.
Severity: requested presentation change, low operational risk.

John requested removal of the complete “Be the first to know” homepage section shown in his screenshot. Remove its heading, discount copy, Join button, and enclosing spacing block.

## Scope and acceptance

- Remove exactly one signup group from WordPress front page 14.
- Preserve every byte outside that group, including the concurrent story-section removal.
- Verify authenticated content readback and public desktop/mobile layout; no empty section remains.
- Keep footer, BIR registration, payment, signup integrations, and other pages outside this change.

## Source and release

Base: origin/main `b16557f525c9ae0be3d703bef803b2a2c9f410cc`.
Branch: `codex/remove-homepage-signup`. The canonical main checkout has unrelated dirty work and is preserved.
`homepage.html` and `homepage_updated.html` have the corresponding signup group removed; historical HTML captures remain historical. These templates are not used for a full live-page overwrite.

Production is database-backed WordPress at https://bactiveph.com. No deployed Git marker or GitHub deployment is available; exact content SHA-256 is the release identity.

Existing dependency alerts remain owned by issue #7; legacy credential containment remains owned by issue #9. No authentication or payment code is deployed by this content deletion.

## Release gate and rollback

Production content update and authenticated readback passed. Public desktop/mobile browser verification passed.
Rollback restores only the removed group into the current page after a fresh hash/ownership check; never restore a whole old page over subsequent edits or import a full database.

## Review evidence

Independent read-only review passed the two-template diff and guarded production-update script. On initial live content the extractor removes exactly 777 bytes, preserving the enclosing outer group closure. Final expected hashes will be computed from a fresh snapshot after the story writer finishes.

`git diff --check` passes. Impeccable detector ran in degraded regex mode because optional HTML parser packages are unavailable; findings concern incumbent Fraunces typography, which this removal does not change. Authenticated readback and rendered desktop/mobile checks are the acceptance evidence.

Preflight independently authenticated production home/siteurl `https://bactiveph.com`, database `waypmvhk_bactwp`, front page `14`, active theme `blocksy-child`. Existing open repository security/dependency findings were reviewed and remain in their owner lanes.

## Production receipt

- Root granted an exclusive 15-minute production page-14 writer window after independent story-removal verification. No staging, theme, settings, payment, email, or order writes.
- Fresh full backup: six components, 330439575 bytes. This lane independently verified every local SHA-256 and ZIP/gzip integrity against the story task manifest, SHA-256 `b1552c3fb29b2f31698a3e6402b0c109845fd96fd47a47e03fc192903dd93b80`.
- Before content SHA-256: `acf600170cab9f55f2a9a5c0dd2bd6e0547c147c4ef926dfb5ad9f04f54ba7d0`.
- After content SHA-256: `d1bc7de18e471d32c652d00d1140379802d8341461d8d983abb3c9e105784d4c`.
- Removed exactly 777 bytes. Every byte outside the signup group is preserved, including the prior story removal.
- Authenticated WordPress REST readback independently matched the expected after content exactly.
- Private before/removed/after snapshots and receipts: canonical workspace `tmp/home-signup-removal-2026-09-06/`. These are content-only snapshots; the complete site backup remains private outside this source branch.
- Story source reconciliation is PR #31; integrate both non-overlapping source changes to preserve both removals.

## Live visual verification and closeout

Public homepage returned HTTP 200 at 1440px desktop and 390px mobile. Both passes confirmed the target heading, offer, and Join control absent from main content; the fit section, footer, BIR link and prior story removal remain. No horizontal overflow or browser page errors. Screenshots were visually inspected after scrolling to the former section location. Ordinary responses were current; no cache purge was required.

All network/browser processes closed; production writer window returned to the root coordinator. Root independently owns protected payment snapshot and server-log readback; this lane claims only the observed page-content and public browser evidence.

Exact snapshot rollback is available; restore the removed group only if current page hash still matches this release, or rebase the insertion on a fresh page so later edits survive.

## Independent coordinator acceptance

The root coordinator independently accepted the release in `signup-root-acceptance-1788680857.json`. This lane read that receipt: page-14 SHA matches `d1bc7de18e471d32c652d00d1140379802d8341461d8d983abb3c9e105784d4c`, story and signup counts are zero, protected production state is unchanged, and all connections are closed. The protected comparison covers configuration, theme files, payment settings, orders, stock, and the error-log hash. No raw protected payload is included in this public record.

The signup window is closed. Later homepage edits must use their own fresh snapshot and preserve both removals. Source reconciliation: PR #32 alongside story-removal PR #31.
