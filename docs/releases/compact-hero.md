# Compact homepage hero with Liquid Glass

Issue [#30](https://github.com/john8barry/bactiveph_com/issues/30). John approved the desktop/tablet/mobile mockups and directed preservation of the existing Apple Liquid Glass effect. Published to production on 6 September 2026 from PR [#33](https://github.com/john8barry/bactiveph_com/pull/33), merge `295084315db2242bfeab321faaadccb47943a84b`. Ordinary cached-page delivery and desktop/tablet/mobile rendering are verified.

## Change and scope

The current cover has an 802px minimum height, oversized card padding and heading margin, and empty paragraphs introduced by existing nested paragraph markup. This change appends homepage-only responsive CSS to the existing hero stylesheet. It suppresses only empty paragraphs, removes the unused empty column, moves the existing headline first visually, and compacts text/button spacing. No WordPress content or shared theme files change.

At the tested viewports, cover height becomes 504px at 1440x900, 460px at 834x1112, and 566px at 390x844. The phone photo stays above the copy and overlaps the card by 24px, retaining photo detail under the top of the glass; below that, the dark backdrop gives a quieter glass appearance. Larger screens use the photo as the full backdrop with a shaded reading area. Content can grow rather than being clipped on narrow or enlarged layouts.

The complete original material/accessibility CSS remains an exact byte prefix, and hero-glass.js is byte-identical. Blur, rim, reflection, pointer/press feedback, stationary text, reduced motion/transparency, high contrast and forced colors remain. Both existing loaders update only their CSS version. The production release changes only assets/css/hero-glass.css and mu-plugins/bactiveph-hero-glass.php. The staging loader is source-aligned but is not part of this production installation.

Global typography remains authoritative: no font-family override is introduced. Checks include the approved incoming Rajdhani Semibold display font; body/controls retain Inter. Story/signup removal, punctuation, header and typography releases remain separate and are coordinated through the existing host owner. No homepage database, functions.php, base custom.css, footer, legal, payment or customer changes are included.

## Validation

- Existing nine glass interaction tests pass: `node --test tests/hero-glass.test.cjs`.
- Chromium at 320, 390, 720, 740, 834 and 1440px: no horizontal overflow or CTA clipping; touch targets at least 48px. CTA is within the first viewport at the requested desktop/tablet/mobile sizes. Smaller/shorter views may scroll to preserve readable content.
- Pointer movement changes reflection coordinates; reduced motion stops tracking. Reduced transparency uses the original opaque light surface with dark text and no blur. Original rim and reflection pseudo-elements remain present.
- Independent review confirmed the original CSS prefix/JS preservation, narrowly scoped selectors, and loader-only version changes. Impeccable mechanical detector found no new issues.
- Desktop/tablet/mobile screenshots were inspected. No real-device or Safari certification is claimed.

## Release and rollback

The production writer window followed the story/signup removal and punctuation changes. Header/typography publication follows this release; compatibility was checked in its local candidate. For a future release, coordinate a new production writer window. Re-read exact site/root/theme identity, CSS/JS/loader hashes, current page14 content and relevant protected files. Assert the first hero column still contains only the empty spacer. Do not write against an unexpected file hash or page structure.

A fresh complete story-removal backup was independently verified locally: six components, 330439575 bytes, all SHA-256 and gzip/ZIP integrity checks passed. Its reuse requires the current bounded release agreement and fresh target snapshots; it is not a standing backup exemption.

Upload reviewed artifacts outside the web root, verify hashes and PHP lint, then atomically replace CSS first and the versioned loader last. Refresh only the homepage cache and independently read normal public asset bytes, rendered behavior and protected-file/content hashes. Check for fresh critical errors during bounded monitoring.

Rollback only the two target files from the exact pre-change snapshot, after confirming current hashes still equal this release. Restore CSS then loader, refresh the homepage and re-verify. Never restore the database or shared theme files as part of this visual rollback. Retain the separate original glass release and payment/security records.

## Artifact identities

- Original CSS: `25697e3e1c73cb1c10df10555036144b12eed7cc6dbf2837299f44f5bd5cf0ed`.
- New CSS: `c88f46ee29977cff5085fd0a405ad2939b676849cd61b022475d8eaf17745982`.
- Unchanged JavaScript: `ece9a7cb980823d5f28dbc6ce05daff2e6209fdf9beb5e365e4d9c4fe8f52c9b`.
- Original production loader: `158b00d20cbdfe326b5d638c235fd158cecb0e7a6c708946f8ac67f15c330eb2`.
- New production loader: `96c868ca0e10ebda2997fe48281d04b7de78f8c468dff798238a6f772888400f`.

## Production receipt

Authenticated readback confirmed the exact CSS/loader identities above, unchanged original JavaScript, and unchanged protected CSS, JavaScript, MU plugins, functions, footer, theme settings and page14 content. Page14 remained `1f0a120db564934c968b6f61334188d000cb8c0cf2bee8c7630129bd7d22c7e0`; functions remained `0f35633e699ff2868d27f490a0f48a1216d68841dfc8f92fa88fbb435e3051cb`.

At 08:25 UTC, two ordinary homepage requests returned HTTP200/LiteSpeed HIT with the new CSS version and unchanged JS version. Served asset bytes matched the deployment hashes. Live Chromium screenshots confirmed 504px desktop, 460px tablet and 565.86px phone cover heights, no horizontal overflow or CTA clipping, 48px buttons within the first viewport, and preserved BIR links. Pointer tracking and reduced motion/transparency passed on the actual public page. The live heading font at this checkpoint remains Inter; incoming Rajdhani/header behavior was checked locally, not represented as already live.

The initial native frontpage-tag and exact-URL purge attempts did not refresh the ordinary page. Python HTTP200 from admin-ajax was a browser-check document and was not accepted as transport proof. A corrected, coordinator-approved attempt first warmed an ordinary Chromium browser through its normal challenge, verified WordPress HTTP400/body `0`, queued only the exact home URL once, and immediately transported it through that same browser. Ordinary homepage version readback then passed. No broad purge, protection disablement or homepage content write was used.

The final authenticated monitor readback at 08:26:10 UTC, 70 seconds after successful public asset verification, found no new critical log entries and unchanged protected state. Both purge queues were drained. Connections closed and the production writer lane returned to the host coordinator.

At 10:23 UTC, the host coordinator independently accepted the production release. Its fresh readback confirmed the exact new CSS and loader, unchanged protected files, configuration, plugins, payment settings, order identities, transactions, stock and logs. The private acceptance receipt is `coordination/compact-hero-root-acceptance.json` in the payment-recovery evidence directory.

Private local receipts are in `tmp/compact-hero-release-20260906/`: release manifest, fresh target snapshots, backup verification, install/readback receipts, browser purge intent/response, public asset hashes, live checks and screenshots. Keep these local; full backup contents are not publication artifacts. Existing security/dependency/payment work is not certified by this visual change.
