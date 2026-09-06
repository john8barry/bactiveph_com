# Homepage story section removal

Owner: Remove homepage section task (01a07595-f443-7763-86e0-d4d3a3942d9a). Requested content change; severity: low. Target: https://bactiveph.com/, WordPress page 14.

Remove the entire “It started with a love for the game” Gutenberg group, including image, founder paragraph, Our story button, dark background and padding. Keep the About page, image asset, adjacent groups, theme, footer and payment configuration unchanged.

Source baseline: origin/main b16557f525c9ae0be3d703bef803b2a2c9f410cc. Canonical workspace main remains 42e13c3 with unrelated dirty work; all source edits are isolated in branch codex/remove-homepage-story. The repository templates are historical and differ from WordPress; apply the deletion to authenticated live content, never upload a template over production.

Acceptance: complete group absent in authenticated page content and public desktop/mobile homepage; adjacent sections present without residual gap; all non-target content byte-identical at publication. Independent review found no positional CSS dependency. Source diff is deletion-only; git diff --check passed. Impeccable detector reports existing Fraunces font warnings in unchanged content; no typography change is included.

Production preflight: home and siteurl https://bactiveph.com, front page14, Blocksy child theme, WordPress7.1. Before post-content SHA256 09ecc6e9965faee64f87fbe3a5be12fb9cf62578b47f32c20879286bcb855909. Expected after SHA256 acf600170cab9f55f2a9a5c0dd2bd6e0547c147c4ef926dfb5ad9f04f54ba7d0. Exactly1661 bytes removed, all other bytes preserved.

Published 2026-09-06 07:38:29 UTC after verified full backup and a serialized production window. Signup removal task holds until completion, typography task is plan-only. Existing issues7 and9 remain with their owners; latest five workflows passed, no GitHub deployment/release entries exist, direct Dependabot retrieval returned403 so no clean security result is claimed.

Rollback: compare current content hash to the recorded after hash; if equal, restore the exact before content through authenticated WordPress. If later content changes exist, reinsert only the removed group after reviewing them. Never restore the whole database. Private snapshot and backup path: canonical workspace tmp/home-story-removal-2026-09-06. Do not commit backup archives.


## Verified release

Tracking: issue24. Authenticated independent readback matches the expected after hash; target text count is zero. Ordinary public homepage returned HTTP200 at1440px and390px, with target text/image absent, Asian-fit section and BIR link retained, and no horizontal overflow. Theme functions, custom CSS, footer templates, theme settings and origin error-log size were unchanged across publication and verification. No broader checkout or whole-site health claim is made.

Full production backup: six components,330439575bytes, all off-server SHA256 and ZIP/gzip integrity verified. Manifest SHA256 b1552c3fb29b2f31698a3e6402b0c109845fd96fd47a47e03fc192903dd93b80. An interrupted SFTP download was resumed without restarting the backup or repeating any content write. Exact before-content snapshot is the primary narrow rollback; WordPress revision API returned null, so no revision guarantee is claimed.

Local private evidence: tmp/home-story-removal-2026-09-06/{backup/manifest.json,publish-receipt.json,verify-receipt.json,runtime-before.json,runtime-after.json,visual-receipt.json,desktop.png,mobile.png}. About-page and theme files were not edited. The homepage content publication is independent of the repository source reconciliation PR.
