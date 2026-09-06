# Compact homepage hero with Liquid Glass

Issue [#30](https://github.com/john8barry/bactiveph_com/issues/30). John approved the desktop/tablet/mobile mockups and directed preservation of the existing Apple Liquid Glass effect. Source implementation is ready; production publication is pending a serialized writer window.

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

Coordinate a production writer window after preceding homepage and header/typography changes. Re-read exact site/root/theme identity, CSS/JS/loader hashes, current page14 content and relevant protected files. Assert the first hero column still contains only the empty spacer. Do not write against an unexpected file hash or page structure.

A fresh complete story-removal backup was independently verified locally: six components, 330439575 bytes, all SHA-256 and gzip/ZIP integrity checks passed. Its reuse requires the current bounded release agreement and fresh target snapshots; it is not a standing backup exemption.

Upload reviewed artifacts outside the web root, verify hashes and PHP lint, then atomically replace CSS first and the versioned loader last. Refresh only the homepage cache and independently read normal public asset bytes, rendered behavior and protected-file/content hashes. Check for fresh critical errors during bounded monitoring.

Rollback only the two target files from the exact pre-change snapshot, after confirming current hashes still equal this release. Restore CSS then loader, refresh the homepage and re-verify. Never restore the database or shared theme files as part of this visual rollback. Retain the separate original glass release and payment/security records.

## Artifact identities

- Original CSS: `25697e3e1c73cb1c10df10555036144b12eed7cc6dbf2837299f44f5bd5cf0ed`.
- New CSS: `c88f46ee29977cff5085fd0a405ad2939b676849cd61b022475d8eaf17745982`.
- Unchanged JavaScript: `ece9a7cb980823d5f28dbc6ce05daff2e6209fdf9beb5e365e4d9c4fe8f52c9b`.
- Original production loader: `158b00d20cbdfe326b5d638c235fd158cecb0e7a6c708946f8ac67f15c330eb2`.

Deployment receipt, remote ref, destination hashes and final live acceptance will be recorded here after publication. Existing security/dependency/payment work is not certified by this visual change.
