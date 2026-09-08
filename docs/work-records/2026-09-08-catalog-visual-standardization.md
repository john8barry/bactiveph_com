# Catalogue imagery, colours and storefront standardization

User approved execution on September 7, 2026 (America/Denver). Owner: current imagery/design coordinator. Priority: medium; customer-facing design and catalogue correctness. Production target: https://bactiveph.com.

## Scope
Implement the three approved surfaces: collection cards, editorial homepage section, and product gallery/colour/size presentation. Preserve original logo, Rajdhani 600, sage/ivory design, WooCommerce pricing and purchase rules. Standardize imagery using product references; generated detail must not invent garment construction. Review every product independently.

## Baseline and dependencies
- Isolated branch `codex/catalog-visual-standardization`, base `0e1c57a76048588ef81603d5e8254b899f27ff4f`; canonical dirty checkout preserved.
- Authenticated catalogue snapshot 2026-09-08 05:26 UTC: 18 products / 182 variations, versus planning baseline 19 / 186. Product 148 Pleated Skort absent; status and intent unresolved. Do not recreate.
- Bounded identity read: WordPress 7.1, WooCommerce 11.1.0, Blocksy child theme. Exact release files require fresh baseline after concurrent work finishes.
- Payment task owns host hold; menu task also reports an active writer lane. No new authenticated host operations or production writes until explicit release and reserved window.
- Open dependencies #2 payment, #7 dependency alerts, #9 legacy credential containment, #16 Brevo, #24 homepage removal reconciliation, #34 size guide, #47 menu release. Existing high dependency alerts are visible; applicability is not yet cleared.

## Required acceptance gates
- [ ] Fresh complete private encrypted off-server backup with hashes and archive checks, including root/runtime prerequisites.
- [ ] Contained restore rehearsal before first WordPress request; outbound integrations and background runners disabled.
- [ ] Targeted product/batch rollback preserves newer orders, stock, prices and IDs; later-writer conflicts fail closed.
- [ ] Current catalogue decisions and exact before/after field manifest, with no inferred stock or automatic deduplication.
- [ ] Versioned colour registry and per-product source/asset manifest; unsupported imagery held.
- [ ] All three surfaces implemented behind independent switches; native WooCommerce behavior remains authoritative.
- [ ] Every offered combination and each product individually verified for visual fidelity, accessibility, responsive behavior and cart mapping.
- [ ] Independent code/design review, focused tests, secret scan, PR checks and exact release readback.
- [ ] Canary then batches of at most three; public cached pages and images verified; monitoring and rollback ready.

## Recovery and production impact
Production changes are held. Retain original attachments and snapshot only explicitly changed fields. Routine rollback restores only release-owned fields after expected-value checks; never import an old full database over newer orders. Full restore is rehearsed privately for disaster recovery. Existing payment backup is useful rehearsal input but not this release's final fresh backup.

## Next control point
Complete local UI and catalogue/recovery tooling while host owners finish; obtain explicit writer window for a fresh backup, prove recovery, then qualify the first product pack. Missing product facts remain exceptions requiring John's decision; visual approval is already established.

Tracking issue: https://github.com/john8barry/bactiveph_com/issues/48

## Local implementation milestone

- Added default-off collection/editorial modules and progressive native-Woo selectors in both theme mirrors; loader changes limited to requires.
-7 selector integration tests pass against source Woo10.8.1 and privately verified active11.1.0; PHP registry/collection gates pass;8 offline image-plan/reversal tests pass. These are not restored/live-cart tests.
- Public-markup render at1440/390px: no document overflow; all rendered selector targets at least44px. Browser font readback confirms custom Rajdhani SemiBold glyphs on product titles after correcting the local font harness.
- Recovered69 original product assets from verified private backup without changing it. Generated one editorial background candidate from attachment387; independent image review accepts editorial use subject to layout/asset acceptance.
- Backup integrity passes but root files/core, encryption and point-in-time consistency are not established. Recovery preparation remains blocked from importing/booting WordPress.
- Existing15 open dependency alerts now readable; most development dependencies, with Blocksy runtime-classified nanoid/react-router alerts. Issue7 owns triage; production applicability remains a release gate.
- Payment coordinator has queued this task's fresh backup window after menu deployment; no host grant while payment canary active.
- User colour-reference basis and absent product148 status questions pending. No assumptions promoted to product truth.

## Recovery preparation verification

Root independently reviewed the preparation script and containment templates, then applied the prepare-only operation to the existing verified package into a new private local directory. No source backup was changed; no database import, WordPress boot, Docker start or host operation occurred. The result remains PREPARED_RESTORE_BLOCKED. All19 Python tests pass (8 image-plan/reversal,11 preparation/containment). This is explicitly not the required full restore rehearsal.

Catalogue review correction: actual stored term snapshot uses Powder Blue slug powder and Sakura Pink slug sakura; defaults agree. The earlier suspected stale-default finding is withdrawn; no default correction is proposed.
