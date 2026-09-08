# B Active imagery, colour and storefront rollout plan

Prepared September 7, 2026, America/Denver. Repository preflight: September 8, 2026, 05:07 UTC. **Planning only: this document authorizes no production change.** No new backup or restore drill has been performed for this rollout yet.

Companion files: [product work list](PRODUCT-WORK-LIST.md), [19-product verification ledger](PRODUCT-VERIFICATION.csv), [draft colour registry](COLOUR-REGISTRY-DRAFT.csv), and [product acceptance template](PRODUCT-ACCEPTANCE-TEMPLATE.md).

## Outcome and scope

Deliver the three accepted designs as one coherent shopping experience:

1. **Collection presentation:** the homepage “What everyone’s wearing” section, shop, collection/category pages and reusable product cards. Standardize image stages, visual scale, names, prices and restrained actions.
2. **Editorial homepage story:** a considered image-and-text section below products and above the footer, using approved fit/material information and supported garment imagery. Reconcile the current homepage first; do not accidentally restore previously removed founder-story, newsletter or unsupported copy.
3. **Product experience:** consistent galleries, named colour circles, sizes, fit guidance and a compact purchase area on every applicable product page. Simple and single-colour products receive appropriate presentations rather than artificial choices.

Keep the approved original logo, ivory/sage palette, Rajdhani 600 headings/navigation, compact hero and footer. Use the established body-font tokens and test their actual cascade. Preserve product URLs, IDs, intended prices, shipping/tax rules, payment configuration, legal information and existing care instructions. Separate any required catalogue correction from presentation changes so each can be reviewed and restored precisely.

**Done means:** every in-scope product passes its individual acceptance record; every offered colour has an approved representation and correct mapping; the shared designs pass desktop/mobile and commerce checks; backups and targeted restore are demonstrated; the normal public site is independently verified after release. A product marked “needs reference” or “held” prevents a claim that the entire rollout is complete.

## Evidence and current gaps

The authenticated pilot inspected 19 published products: 17 variable and 2 simple; 186 variation records with exact parent/fetched ID agreement; 60 gallery entries and 70 unique referenced image IDs. There are 30 colour terms, 24 used in the inspected catalogue, and **54 parent-listed product/colour combinations**: 51 on variable products and 3 informational colours on Match Polo. Warm-Up Jacket has no colour attribute. These counts are a dated planning baseline, not proof of 54 purchasable choices.

The pilot generated eight images and passed 64 local colour/size checks on two products at 1440px and 390px. It did **not** qualify the live WooCommerce cart or every product. Recolours flattened Strappy Bra straps despite a correction attempt. The generated dress close-up invented detail beyond the source. Keep those outputs as concepts, not approved production assets.

At planning preflight, GitHub main was `0e1c57a76048588ef81603d5e8254b899f27ff4f`; canonical local main was `42e13c3df9ebe8908b511a2aae19be2505479f02`. The tracked-worktree status read timed out. Treat canonical work as preserved and unreconciled; do not deploy from it. Recent sampled workflows passed, but that is not full release qualification. Releases/deployments API lists were empty; live deployment must be established from exact destination files and content, not an assumed Git marker. Dependabot alert read returned HTTP 403: security-alert clearance remains unknown.

Current open work includes size-guide trigger #34, dependency/security issues #7/#9, payment #2, Brevo #16, and historical homepage-removal records #24/PRs #31/#32. Revalidate their states and owners at execution. Security failures affecting this release must be resolved or explicitly isolated before mutation. Payment/newsletter work must not share a competing writer window with this release. Size-guide acceptance requires a working dialog or the existing size-guide page as a deliberate tested fallback.

## 1. Establish one delivery record and one writer

The coordinator owns the product ledger, colour registry, asset manifest, implementation integration, release sequencing and final evidence. At execution start, link this local plan into a project-scoped work item and focused PRs under the current task’s authority. Do not create a second release lane.

- Reconfirm production `https://bactiveph.com`, staging identity, active theme/plugins/WooCommerce version, live template overrides and exact deployed files/settings.
- Use an isolated `codex/` worktree from freshly inspected remote main. Identify the authoritative source among the repository’s child-theme mirrors; do not absorb dirty source or upload old whole `functions.php` files.
- Re-read the full catalogue, attachment metadata, defaults, visibility, stock management, prices, SKUs, backorders and relevant integration settings before proposing edits. Inspect originals, not thumbnail names alone.
- Refresh the Chief of Staff and project release-owner routes. Reserve one production writer window; image generation and independent reviews may run in parallel off-site.
- Create an exact proposed-change manifest: object ID, field, before value, proposed value, reason, owner, dependency and rollback action. Re-read before each mutation and stop on unexpected drift.

**Exit evidence:** named coordinator, current target identities, repo/live baseline, issue links, 19 product records and a list of unresolved factual decisions. Planning can proceed while these are open; production cannot assume them resolved.

## 2. Back up first, then prove easy restoration

Before changing production content, settings, files or media, create a dated **complete recovery package** and an independent off-server copy. This is additional to the precise product snapshots used for routine rollback.

### Complete package

- A consistent full database backup including whichever WooCommerce order-storage mode is active, products/variations, taxonomy, attachment metadata, options and scheduled work. Verify the actual storage tables; do not assume only legacy posts tables matter.
- Uploads including originals and existing generated sizes; active and required themes/plugins/MU plugins; relevant root, rewrite and WAF configuration; WordPress/PHP/database/plugin version inventory. Verify whether the backup tool includes each prerequisite rather than assuming a standard component count covers everything.
- An encrypted, access-controlled off-server copy; component sizes and SHA-256 checksums; archive-integrity results; a backup timestamp; a recovery guide; and a usable decryption key stored separately. Raw database dumps, credentials and configuration stay outside Git and public web paths.
- Exact snapshots of each touched product’s featured/gallery/variation attachment IDs, ordering, attributes/defaults, visibility and any proposed correction fields; colour term IDs/slugs; affected page content; selector/theme assets; SEO/alt text if touched.

The historical runbook reported no automatic Updraft remote destination. Recheck it. A backup job reporting success does not establish that a complete off-server restore set exists.

### Recovery rehearsal before catalogue changes

Restore the off-server package into a disposable environment and verify startup, database integrity, counts, product/variation links and original images. Record elapsed recovery time. Back up an existing staging site before replacing it, or prefer a disposable local environment.

**Contain the clone before its first request:** separate database/storage and URL, authenticated access, no indexing, live credentials removed or replaced, and outbound mail/payment/marketing/shipping/catalogue calls blocked. Stop WP-Cron, hosting cron, Action Scheduler runners and other background workers. A noindex setting or disabling only WP-Cron is insufficient. Enable only the approved test services after containment is proven.

### Easy routine restore

Build and document a reviewed restore operation with two choices: **restore one product** or **restore this release batch**. The operator supplies a release ID and product ID; the operation previews the exact reverse diff, verifies the target, then applies only those approved fields. No manual SQL reconstruction should be required.

- Restore previous image assignments, gallery order and this release’s UI/content changes. Keep original attachments/files in place.
- Do not overwrite current orders, payment records, stock, sold counts, prices or SKUs with an old product export. Snapshot these for comparison but exclude them from an imagery rollback.
- For intentional catalogue corrections, provide a separate field-specific reverse plan. If new orders reference the changed variation model, stop for reconciliation rather than blindly reversing it.
- Preserve existing product and variation IDs. Do not delete/recreate products to replace images or deduplicate records without investigating historical references.
- Use expected-value checks: restore only if the current field still equals the value this release wrote. Stop on a later writer’s change. Journal each operation; read back ambiguous results before retrying.
- Roll back code through a release switch/allowlist and exact asset patch. Preserve unrelated later changes and retain inert new assets if deleting them would increase risk.

**Required rehearsal:** in the isolated clone, apply a sample change, create a synthetic order/stock decrement and an independent price edit, then roll back the imagery. Original images must return while the newer order, stock, price and IDs remain intact. Also rehearse an interrupted restore and a later-writer conflict.

Target product rollback is an operational goal of minutes, subject to the measured drill. A full-site restore has a separate measured recovery time and can lose post-backup activity unless orders/payments are reconciled. It is an incident procedure, not the routine undo button.

Retain originals, encrypted backup and manifests for at least 30 days after accepted release, and longer while any rollback or customer/order dependency remains unresolved. No automatic media deletion. Refresh exact per-product snapshots immediately before each batch and refresh the full backup if intervening changes make the recovery set stale.

**Exit evidence:** complete verified off-server package, successful isolated restore, measured recovery times, and a tested product/batch undo procedure that preserves newer commerce data.

## 3. Reconcile catalogue truth before creating selectable colours

Inspect all 186 current rows; do not generate every possible colour/size combination automatically. “Sold out,” “not offered,” “disabled,” “missing price,” and “ambiguous record” are different states. Preserve intended prices, stock ownership and backorder policy.

Resolve these known exceptions through a concrete before/after worksheet:

- **Aria Set:** 21 colour-only variations represent five combinations; Size is absent; all 21 were sold out in the pilot snapshot. Determine whether sizes are missing or intentionally absent, which records are authoritative, and whether Bloom differs from Sakura Pink. Do not invent sizes or stock.
- **Court Skort:** duplicate Green Jasper sizes and Sakura Pink XL. Choose any retained records only after matching stock/SKU/history and intended product configuration.
- **Bubble Dress:** two Black XL records with different images; an M record with no colour; an Onyx XL row whose colour is absent from the parent. “Any colour” matching must not quietly become an exact swatch assignment.
- **Rally Skort:** Court Ivory and Onyx records lie outside parent-listed options. Resolve their intended status without automatically exposing or deleting them.
- **Strappy Bra:** four colours share a yellow reference; each needs a reviewed correct image.
- **Sculpt Leggings:** Almond and Stone share an image despite a larger gallery; visually map references.
- **Pleated Skort:** Onyx references a file named white skirt; establish actual colour/product visually and with owner confirmation.
- **Ribbed Tank/Rally Dress:** variation images differ from parent gallery sources; determine which are current.
- **Match Polo/Warm-Up Jacket:** these are simple products. Decide whether colour is informational or a real customer choice. Converting to variable products is a distinct catalogue change requiring confirmed sizes, prices, stock model and historical-ID handling.

There were 22 redundant rows, six parent-option orphan rows and one colour wildcard in the inspected data. Those are review flags, not deletion instructions. Do not infer physical stock from photos, filenames, term names or `purchasable` alone. Retire unused terms only if separately justified; term cleanup is unnecessary to deliver the design.

**Exit evidence:** one unambiguous intended selection for each offered combination; documented unavailable combinations; preserved history; product-specific decision approval only where business facts were uncertain.

## 4. Establish the colour and visual standards

### One versioned colour registry

For every used colour, record the WooCommerce term ID/slug/name, plain-English description, draft/approved status, reference source, approved sRGB swatch or texture asset, applicable products and any product-specific material/shade override.

- Keep exact WooCommerce names; do not automatically merge White/Pure White/Court Ivory or Black/Onyx. Sharing a visual swatch does not merge inventory or taxonomy.
- Use one approved base for a truly shared shade. If a supplier or fabric makes the same named colour different, use an explicit product-level override and reference.
- Use flat circles for solid colours. Use an approved image crop for a real pattern or visible texture; do not invent fabric texture just to decorate a swatch.
- Resolve inconsistent draft values before batch generation. The earlier Powder Blue pilot used `#ADCBDC`, while the later annotated board used `#B3CAEC`. Neither is a measured or approved master colour. They must not both become silent standards.
- Confirm Bloom versus Sakura Pink and the green/blue and white-family distinctions using a labelled board. Colour names alone are insufficient to determine an exact shade.
- A supplier reference or ordinary phone image can resolve a factual question; no professional shoot is required. If only an existing AI image is available, identify it as the visual baseline and record that physical fabric matching is not independently established.

### Image art direction

Create an approved reference sheet per product covering front/back geometry where known, neckline, straps and crossings, seams, band widths, pleats, hem length, pockets/shorts/lining only when supported, logos, material appearance and fit. Record which pieces form a set and whether they are sold together. Styling garments/accessories must not imply included items.

Standardize the photo language: warm neutral studio, gentle consistent daylight, plausible shadows, natural model presentation, consistent garment scale, no unintended body reshaping, and no invented garment logos. Maintain model continuity within a product’s angles and colour family where feasible; cross-product casting may vary without breaking the visual grammar.

Use a 3:4 product-image stage with deliberate subject placement. Preserve the full garment, important hem/straps and footwear when the approved composition includes them. Prefer uncropped containment or another approved presentation over chopping garments to force a ratio. A CSS image stage can standardize presentation while retaining original pixels. Lifestyle imagery uses an intentional wider composition and a separately reviewed mobile crop.

Default source target: native 1536×2048 portrait or a suitable larger original; square details; wide editorial source with mobile-safe subject placement. Export responsive derivatives sized to actual components. Keep masters; use the supported WordPress image pipeline for delivery formats, avoiding unnecessary global thumbnail regeneration. Provisional budgets: primary displayed product image around 250 KB, thumbnail around 80 KB, editorial around 400 KB where visual fidelity permits. Measure the actual result; do not degrade garment detail just to hit a number.

### What is approved once versus reviewed repeatedly

John reviews one consolidated colour/reference decision board and the representative finished product packs. The coordinator and an independent reviewer then check every product against those standards. Bring John only exceptions involving garment facts, intended inventory or a material aesthetic departure; do not require approval for every routine export.

**Exit evidence:** approved style sheet, versioned colour registry, known/unknown garment details and a reusable generation prompt template with explicit invariants.

## 5. Build imagery in controlled product packs

Start with **Court Dress** as the technical/image-layout pilot, **Flow Skort or Courtline Dress** as another coherent configuration, and **Strappy Bra** as the difficult construction/recolour test. Prepare the three colour-mockup products early, but hold their live selectors until catalogue conflicts are resolved.

For each confirmed product:

1. Select and preserve the best existing source images. Record hashes and attachment IDs; identify what can be reused, needs a new crop/presentation, requires recolouring, or is missing.
2. Produce one approved primary image for each genuinely offered colour. Use the product reference as the starting point, not a fresh independent garment design for each colour.
3. Produce supporting front/back/side images when those views are supported by reference. Aim for a useful 3–5-image product gallery; do not fill it with invented information. A colour-specific photo must not silently remain visible after selecting a different colour.
4. Produce close-ups only from visible, supported features. Existing-source detail windows or crops are preferable where enough detail exists. Do not synthesize hidden pockets, built-in shorts, lining or weave as factual product evidence. Omit or hold an unsupported detail with a recorded reason.
5. Generate a small set of court-to-café/editorial images for the homepage story once their garments pass reference review. Check garment identity, context, subject placement, crop and any styling items.
6. Compare all colours together, then compare each output to its reference at normal display size and close inspection. Check shape, colour boundaries, skin/background contamination, front/back consistency and accessories. Colour sampling can flag drift; it does not certify physical fabric accuracy.
7. Allow one initial generation pass and one focused correction pass. If the same defect persists, mark it “needs reference/correction” and change the approach rather than silently accepting drift or spending indefinitely. Keep rejected outputs out of the production manifest.
8. Produce final dimensions, responsive formats, descriptive alt text, file names and a signed-off asset manifest. Use names such as `court-dress-turquoise-front-v2`; record prompt, source/version, review result, dimensions and checksum. Never overwrite the only original.

The planning baseline is **51 primary colour images for variable products**, plus 3 informational Polo colours and unresolved Jacket options. Many can be reused. Supporting angles, details and 2–4 editorial assets are counted only after reference review. Do not order an automatic 54-image regeneration or promise unsupported views. Confirm the per-batch generation count before running it and keep a usage/attempt log; no unapproved paid services or new photo-shoot requirement.

**Exit evidence per product:** approved pack, rejected/held items separated, colour mappings complete, source and delivery files preserved, independent construction/colour review recorded.

## 6. Implement the three shared designs

Build off production and qualify the exact reviewed bundle before release. Use separate feature switches or product allowlists for gallery/swatches, collection cards and editorial content so rollback is narrow.

### Collection cards

Use consistent portrait stages and garment scale, balanced spacing, aligned product titles/prices and a restrained action. Preserve WooCommerce’s real price/range/sale state, stock and product link. Use an actual second image for hover only when available; mobile must not require hover. Decide deliberately between labelled quick colour previews or a compact swatch row leading to the product page; do not introduce quick-add without a separate selection/cart test. Show a sensible single-colour state and maintain an accessible product link.

### Editorial section

Use approved real product information with the generated lifestyle composition and supported details. Match the established ivory/sage world and concise tone. Do not reuse the rejected fabric macro as proof of material quality or restore removed copy from an old template. Confirm placement against the current homepage and existing content before changing the page object. Mobile gets an intentional image/text order and crop.

### Product gallery and selection

Use labelled colour circles and size buttons as an enhancement over the authoritative WooCommerce variation form. The server remains the authority for variation ID, price, stock and cart acceptance. Avoid a separate hard-coded inventory system.

- Colour name is always visible; selected state uses an outline and accessible state, not colour alone. Controls are keyboard-operable with visible focus and at least 44px touch targets.
- Prefer explicit colour selection unless a deliberate existing default is preserved. Do not silently choose a size.
- Colour-first and size-first selection must agree. If a new colour invalidates the selected size, clear it and explain the required selection rather than silently substituting another.
- Keep sold-out colours previewable when appropriate, clearly mark availability, and block purchase. Do not offer nonexistent combinations as if they are merely temporarily sold out.
- Single-colour products can show a named swatch without needless interaction. Simple products retain valid existing purchase behavior until any variable-product conversion is approved and tested.
- Map by product ID + colour term ID + actual variation ID; never filename order or “first matching row.” Exact intended mapping must agree with WooCommerce wildcard/default behavior.
- Update gallery, selected name, price/availability and cart image coherently. Reset, reload, back navigation and direct attribute links must not leave stale selections.
- Preserve native dropdown fallback if enhancement code fails. No new browser-to-provider endpoints are needed for swatches.
- Verify the deployed WooCommerce version and current gallery extensions. Current WooCommerce documentation describes native per-variation galleries in newer versions; use that only if the installed environment supports it. Otherwise use the existing compatible gallery or a reviewed narrow enhancement. No unrelated platform upgrade or paid gallery plugin is bundled into this work.

Fit guidance stays concise; size guide, details and current care information remain reachable. Resolve or deliberately route around the known size-guide trigger issue with an independently tested page link.

## 7. Mandatory verification for every product

Use `PRODUCT-VERIFICATION.csv` and `PRODUCT-ACCEPTANCE-TEMPLATE.md`. Each product moves through **not started → references resolved → assets accepted → staging passed → live passed → monitoring passed**. A failure or unknown remains visible; a shared template passing does not automatically pass every product.

| Gate | Required evidence for that product |
|---|---|
| Catalogue | Parent/type/URL and all variation IDs; intended colour and size matrix; stock/backorder/price baseline; duplicates, defaults and wildcard decisions resolved. |
| Imagery | Every offered colour mapped to a reviewed primary image; appropriate gallery/order; original/reference comparison; no construction drift, misleading detail, colour spill or wrong-product image. |
| Colour | Exact labels and term IDs; approved registry version; shared shade/override checked; selected ring and accessible names distinguish similar/light colours. |
| Selection | Every defined combination plus absent/disabled/sold-out combinations; colour-first and size-first; stale size reset; defaults, clear/reset, quantity and attribute links. Server agrees with intended mapping. |
| Cart | Correct existing variation ID, colour, size, image, price and quantity enter an isolated test cart; removal/re-add, refresh and unavailable selection are correct. No live paid order is needed for an imagery release. |
| Visual/mobile | Desktop 1440px and mobile 390px for all 19; shared edge checks at 320px/768px and longer colour-name cases; image crop, wrapping, gallery zoom/lightbox, swipe/touch and price/button layout. |
| Accessibility | Keyboard and focus, meaningful labels/alt text, screen-reader selection/status announcements, contrast, touch targets and no reliance on colour/hover alone. |
| Performance | All original and responsive image URLs load; correct aspect ratio; first important image not lazy-loaded; gallery below fold deferred; compare page-weight and timing to baseline on the same conditions. |
| Recovery | Fresh product before/after manifest and tested reverse path; originals retained; no unexpected stock/price/ID mutation. |
| Live | Independent authenticated field/image readback plus anonymous normal product URL, card surfaces and cart behavior; correct cached/optimized assets, no new critical errors, recorded monitoring result. |

Run an automated matrix across all approved variations and meaningful invalid combinations. On staging, exercise every distinct variation through the cart without placing orders or decrementing stock. Use synthetic transactions only for the isolated recovery/stock drill. On production, verify every offered colour and all configuration mappings; use a bounded isolated cart sample per product and every distinct behavior class, without starting payment sessions. Record which checks are staging, live readback, browser, or sample-based; do not label sampling exhaustive.

Integration checks cover shop/category/search/related-product cards, homepage grid, the editorial section, header/footer, size guide, cart and checkout rendering. Test slow/failed image loads, enhancement-script failure, multi-attribute order, product defaults, sold-out/simple cases and repeated selection. Check existing sessions after release so old cached markup and new assets do not disagree.

Run one complete batched visual review, fix the findings together, then one confirmation pass. Repeat testing only for affected changes or newly discovered failures. Independent review must see the actual rendered result and exact asset/record manifest.

**Exit evidence:** every product’s ledger row has explicit passes with evidence links, and all global interaction/regression checks pass. Product-specific exceptions have an owner and next action, not a hidden waiver.

## 8. Stage and release in small reversible batches

Do the palette, reference and conflict work first. Build the collection system, editorial section and product controls in the isolated environment with their real image packs. Keep production activation separate from local implementation.

Suggested release sequence:

1. **Canary:** Court Dress only after its image/reference and sparse availability matrix pass. If it cannot pass, use Flow Skort or Courtline Dress after their checks. Enable the shared enhancement only for the accepted product.
2. **Coherent products:** Flow Skort, Courtline Dress, Elite Dress, Varsity Dress, Serve Dress and Match Dress in sub-batches of at most three products. Do not infer XL on Match Dress.
3. **Sparse/image-reconciliation products:** Everyday Skort, Rally Dress, Ribbed Tank, Sculpt Leggings, Pleated Skort and Strappy Bra, each only after its named issues pass. Keep difficult products held rather than delaying safe preparation elsewhere.
4. **Ambiguous catalogue products:** Court Skort, Rally Skort, Bubble Dress and Aria Set after the exact data decisions are accepted. Explicitly verify the three colour-mockup designs here.
5. **Simple-product decisions:** Match Polo and Warm-Up Jacket after intended purchase behavior is confirmed. Image-only standardization can precede any separately qualified product-type conversion.
6. **Whole-site acceptance:** finalize homepage grid/editorial rollout and all collection/related/search surfaces once their featured product packs are ready. The shared design can be tested earlier behind its switch; global activation must not expose held swatches. Review the entire page rhythm together.

For each production batch:

- Reconfirm ownership, exact target and current state; obtain fresh before snapshots and verify backup/restore availability.
- Upload reviewed images as new versioned attachments and verify bytes/derivatives before assigning them. Track attachment creation in a journal. If a response is ambiguous, look up the exact result before retrying; do not create duplicate attachments blindly.
- Install reviewed inert UI assets first; lint and hash-check, then activate the narrow change last. Apply only allowlisted fields through supported APIs. No staging database import.
- Read back each product and variation after assignment; compare protected commerce fields against the just-read baseline. If legitimate sales occurred, reconcile them; never “fix” stock back to an old snapshot.
- Purge affected product/home/shop/category/search/related entries and image derivatives where needed. Verify ordinary anonymous URLs and their actual `srcset` assets; a cache-busting query alone is not enough.
- Independently perform product checks and bounded cart checks. Observe for at least 20 minutes with error-log and normal-URL checks before expanding the batch. Continue with a next-day follow-up before closing final acceptance. Schedule follow-ups only during the approved execution, not as part of this planning turn.

Stop the batch for wrong-colour imagery, ambiguous variation resolution, missing assets, unexpected ID/price/stock change, broken cart or navigation, new critical errors, significant performance regression, cache disagreement or an ambiguous write. Disable the affected enhancement or apply targeted rollback when needed; retain evidence and reconcile before continuing.

## 9. Deliverables and closeout

- Versioned colour registry covering all used terms and explicit product overrides.
- Reference sheets, approved master/delivery images and generation/review history per product.
- Product/colour/variation-to-attachment manifest, approved catalogue corrections and original snapshots.
- Tested card, editorial and product templates with source commit, PR/check/review links and release switches.
- Complete private off-server backup and restore receipt; product/batch recovery guide and rehearsal results.
- All 19 completed acceptance records with desktop/mobile evidence, exact live mappings and monitoring receipts.
- Final before/after visual overview, a concise guide for adding future products/colours, and an exceptions register with nothing silently marked finished.

Preserve original media and documentation through the retention period. Close the work item only when live acceptance and follow-up pass. Do not claim payment settlement, email delivery or broader project health from this design release.

## Decisions to resolve during execution

No new design interview is needed. The approved layout and brand direction remain the brief. Present these factual choices together as a concrete worksheet:

1. **Colour truth:** approve/correct the shade board, Bloom/Sakura distinction and any supplier/fabric overrides; identify the best available reference where current AI pictures conflict.
2. **Inventory truth:** intended Aria sizes and sold-out state; duplicate/orphan/wildcard decisions; whether Polo/Jacket need selectable variations; sparse matrices that are intentional.
3. **Image completeness:** approve representative final packs; supply or identify existing support for hidden construction, or agree to omit those unsupported views. No professional shoot required.
4. **Release readiness:** review the exact accepted package and recovery evidence under current project authority. This plan is not a deploy instruction. Avoid another generic design-approval loop; ask only about unresolved facts or authority that remains necessary.

## Sources and companion artifacts

- [Pilot report](../imagery-pilot/REPORT.md), [catalogue snapshot](../imagery-pilot/catalog.json), [variation snapshot](../imagery-pilot/variations.json), [local test results](../imagery-pilot/test-results.json).
- [Three annotated colour boards](../colour-mockups/index.html); their swatches remain draft approximations.
- Project access runbook: `/Users/johnbarry/Documents/Antigravity/bactiveph_com/docs/operations/project-access.md`, especially backup, source-mirror, isolation and limited rollback guidance.
- [WooCommerce variable products](https://woocommerce.com/document/variable-product/): variation-specific images, price/stock, defaults, wildcard and version-dependent gallery behavior.
- [WooCommerce backups](https://woocommerce.com/document/backup-wordpress-content/): store content needs both database and files. CSV/product exports complement the recovery package; they do not replace it.
- [WooCommerce CSV importer/exporter](https://woocommerce.com/document/product-csv-importer-exporter/): use supported catalogue interchange with explicit field mappings where appropriate; production corrections still require an exact reviewed manifest.

## Planning review

Independent catalogue review: PASS for counts, all 19-product coverage, exception handling and verification scope. Independent recovery review: PASS for the written backup/isolation/restore plan. These are planning reviews only; they do not clear assets, inventory, production, backup completeness or restore performance. The coordinator reconciled the findings and validated the companion ledger counts.
