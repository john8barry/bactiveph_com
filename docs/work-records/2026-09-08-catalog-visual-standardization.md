# Catalogue imagery, colours and storefront standardization

User approved execution on September 7, 2026 (America/Denver). Owner: current imagery/design coordinator. Priority: medium; customer-facing design and catalogue correctness. Production target: https://bactiveph.com.

## Current status — September 12, 2026

This section supersedes the historical September 8 baseline below. Implementation remains unfinished and has not been deployed.

- Current main `01c81c2e7e5494ebb48beca72d858466e5ad099b` merged into the isolated design branch at `544a4b28`; collection/editorial implementation integrated at `be54d45b`. Canonical dirty checkout preserved. PR #50 remains draft.
- Authenticated catalogue: **19 products, 253 variations, 96 unique image references**. Existing references can support the 15 qualified candidates without new product imagery. Court Skort numeric sizes and new colours were reviewed afresh. Pleated Skort and Aria Set remain trashed.
- Fresh encrypted off-server backup completed **2026-09-12 07:53:49 UTC**, before implementation. AES-256-GCM archives cover database, site root and runtime configuration; separate private recovery key. Ciphertext hashes, authenticated decryption, gzip integrity and tar coverage verified. Manifest SHA256: `019eb832ebb641704cc81327487e1cfca62866e80db82237cbd18085278334c9`. 137 tables and 31,803 file entries. Database and files captured sequentially, not an atomic cross-resource snapshot.
- Actual contained restore passed on WP 7.1, WooCommerce 11.1.0, PHP 8.2.33, MariaDB 11.4.13. Outbound traffic blocked before WordPress boot; production users, orders, sessions, jobs and integrations sanitized. Production configuration, MU plugins and drop-ins excluded from the clone.
- Real isolated Woo cart matrix: 188 successful additions, 64 correct unavailable rejections, one known held Bubble variation 80 rejection. 43 invalid cart combinations rejected; 294 combinations gave identical lookup results in either selection order. All 253 protected commerce snapshots unchanged after drills.
- Actual targeted image rollback passed normal, partial, repeated and later-writer conflict cases, preserving synthetic later order, stock and price changes. Design-file rollback subsequently passed normal, repeated, interrupted and later-writer conflict cases in the private clone, also preserving all 253 commerce snapshots; the runner performs atomic replacement per file and requires serialized writer ownership.
- Separate collection/editorial switches and exact existing-block hash guard implemented. Product gallery styles and accessible native-select enhancements are implemented locally. Focused checks pass for native matching, fallback, reversible content placement, thumbnail keyboard activation/replacement, and both PHP modules. Card previews now use a separate explicit review flag so disabling product enhancements does not disable approved card previews. Theme margin and purchase-button cascade conflicts are corrected. These do not establish browser or live readiness.
- Private clone canary overlay enabled only Courtline 117 and Flow 154. PHP gates pass under PHP 8.2.33; HTTP confirms both product modules, two qualified collection cards, and exactly one transformed existing editorial section. Exact inner fit group SHA256: `fd70fe15ef7449aa7c5524e9f441ba922722c34959f969be9c07d5827b10b710`. Independent code review found a selected-size keyboard focus cascade defect; a specific focus-visible rule fixes it. Browser confirmation remains pending.
- Browser preview attempts using the in-app browser and Chrome both returned `net::ERR_BLOCKED_BY_CLIENT` for the isolated loopback URL. Isolation was not weakened. Responsive visual review, keyboard checks, deployed responsive images and bounded live cart checks remain outstanding.
- Security source review found no new write endpoint or external dependency in these modules. Current Dependabot API access returns 403; unresolved historical issue #7 applicability and issue #9 credential-history work are not described as cleared.

### September 12 continuation checkpoint

- Current main remains `01c81c2e7e5494ebb48beca72d858466e5ad099b`. Final design and catalogue records are committed locally through `b3f1c14c`; the remote draft PR has not received them.
- 24 JavaScript tests pass against the restored WooCommerce 11.1.0 engine, both PHP suites pass under the exact restored PHP 8.2.33, and 19 Python recovery tests pass. Root compared the four latest canary module files directly with the private runtime. Independent review findings for card-switch coupling, desktop margin and CTA cascade are resolved.
- Refreshed checklist and source manifest reconcile 19 products, 253 variation mappings and 96 original attachment hashes. The registry is entirely default-off, with 15 reviewed entries and four held entries. Court Skort Lavender is excluded from its palette.
- Chrome still renders a blocked page for the private loopback preview (`ERR_BLOCKED_BY_CLIENT`). The user was asked to open the same local page themselves to distinguish browser/tool access from application behavior. No security setting or network containment was weakened.
- Publishing the prepared commits failed: GitHub denied repository write access to `johnbarry-tpg`. Independent authenticated `/user` checks confirm that both saved credential slots (`john8barry` and `johnbarry-tpg`) identify as `johnbarry-tpg`. The user was asked to authenticate GitHub CLI as `john8barry`. No tokens or credentials are recorded here. Do not claim the remote PR is synchronized.
- Production remains unchanged. Remote CI, rendered browser qualification, serialized production release and live/next-day checks remain outstanding.

### Holds and remaining control point

Keep product 56 Bubble Dress (duplicate Black XL, missing colour, unlisted Onyx), 160 Rally Skort (unlisted colours), 238 Sculpt Leggings (shared Almond/Stone image), and 211 Ribbed Tank (variation 646 missing colour) unchanged. Court Skort's Lavender circle remains text-only until its bright pink source is reconciled; do not borrow Flow Skort's Lavender value. Bloom and Sakura Pink remain distinct.

Finish the reviewed palette/asset manifest and restore-browser qualification; independently review the final diff; then obtain a serialized production writer window. Release Courtline Dress, then Flow Skort, then shared presentation and qualified batches of at most three. Each release still requires public cached readback, 20-minute monitoring and a next-day check. No production design writer window is currently reserved.

## Historical record (superseded where noted above)

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

## Complete original-reference review

Two independent read-only lanes inspected all69 unique original attachments across the18 currently returned products (47+22 files). Findings recorded in REFERENCE-EXCEPTIONS.md and REFERENCE-CANARIES.md; root reviewed both and directly confirmed the Bubble image61 and Aria343 findings. Strong original reuse is available across much of the catalogue. Missing rear/interior views are not invented. These reference reviews do not pass product release gates.

Draft PR50 source commit f9c2cb0c2012bb6e9468bd0fd8f70e6e37cb4a76 passed Catalogue visuals and Storefront punctuation CI. No merge/deployment. Product148 remains statusunknown; colour reference basis remains pending.
