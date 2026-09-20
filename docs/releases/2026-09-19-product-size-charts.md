# Seven product-specific illustrated size charts

Issue [#74](https://github.com/john8barry/bactiveph_com/issues/74). Owner: John Barry / B Active. Medium storefront guidance improvement: five additional styles need their supplied charts, the current Bubble Dress product has a different slug, and the owner supplied refreshed Court Skort and Bubble Dress originals.

## Approved associations and source provenance

John approved the following exact product associations. Product display names do not determine URLs or sizing. Each filename below ends in `-illustrated-20260919.jpg` and lives in the child theme's `assets/images/size-guides/` directory. All seven selected originals are 853 by 1280 pixels and are copied without editing or recompression.

| Chart / filename prefix | Product ID | Exact product slug | Supplied image | SHA-256 |
| --- | --- | --- | --- | --- |
| Court Skort / `court-skort` | 185 | `the-court-skort` | 1 | `341ebb2b36beefa5ac339db20f64cbf87925b06b419ddaa63a35c4ccf7f4f4dc` |
| Strappy Bra / `strappy-bra` | 217 | `the-strappy-bra` | 3 | `db7ad53c2283b43d720c2275b29faab279f8e6d6dbf7be99d34633bcf95cf324` |
| Bubble Dress / `bubble-dress` | 1010 | `bubble-dress` | 4 | `3ff8944b83406662e4f94fef60529f37172acaf26c277cea68f4ac717b585785` |
| Match Dress / `match-dress` | 83 | `the-match-dress` | 5 | `ba42bf1d802427f80e10bc4bd24c231511f1fcfebffe6e3834292a78cca2af62` |
| Serve Dress / `serve-dress` | 89 | `the-serve-dress` | 6 | `780f5994fe93e494080d10020c8225d14d0ee027288a04ff19c7c634dbd7c7f9` |
| Elite Dress / `elite-dress` | 95 | `the-eyelet-dress` | 7 | `2fd6f034828fb829936a3efd8f4dac1d64b304f7905032a2cd0b90e1db0cb897` |
| Courtline Dress / `courtline-dress` | 117 | `the-ace-dress` | 8 | `9d24a369872e87363460888bc4e367933c0555a222740d75bad2cd95cafcc73d` |

Image 7 is the owner's chosen Elite Dress version; the alternate layout in image 2 is omitted. The prior September 11 Court Skort and Bubble Dress assets remain byte-identical for recovery, but their visible links use the newly supplied September 19 files. The stale `the-bubble-dress` association is removed in favor of the current `bubble-dress` product.

## Behavior and limits

- A single registry supplies exact product routing, allowed fallback chart keys, chooser links and visual content. It never assigns charts by category, similar title, query-string product override or selected variation.
- Every matching Size Guide dialog and selected fallback displays exactly one original illustration, a full-size new-tab link and a complete hidden measurement description referenced by the link. There is no duplicate visible table. The general guide remains a chooser; unapproved products retain contact guidance.
- Preserve source values and terminology, including Strappy Bra's waist values, Elite Dress ranges and Slack Bottom explanation, and Courtline Dress's Pants Length label without an invented bust column.
- John explicitly approved Serve Dress's numeric chart labels 4/6/8/10/12 as supplied while its purchasable S–XL options remain unchanged. No numeric-to-letter conversion is inferred. No variations, prices, inventory, catalogue records, orders or payment behavior are changed.
- Dedicated CSS applies the existing anchor offset to every chart. Shared styling and dialog JavaScript are unchanged. Both PHP source mirrors remain identical.

## Local verification and release control

Local checks pass: 21 focused size-guide tests, PHP lint for both mirrors, mirror comparison, dialog JavaScript syntax and whitespace checks. The suite verifies seven exact mappings, all supplied measurement rows and instructions, hidden text references, selected fallback isolation, chooser links, invalid/nonscalar selections, stale/lookalike slug rejection, variation-query independence and SHA-256 integrity of selected and retained originals. Local checks do not establish production completion.

Production target: `https://bactiveph.com`, active `blocksy-child` theme. Before installation, independently confirm the live target, current product identities, current files and exclusive writer window; verify the qualified off-server backup and capture fresh scoped preimages. Stage the seven images first, then apply only the reviewed size-guide region to fresh live `functions.php` and the dedicated CSS. Compare preimages immediately before replacement and preserve all unrelated live differences.

Acceptance requires independent authenticated readback plus anonymous checks for every current published product, all seven selected fallback pages, the chooser and invalid selections. Discover the current catalogue rather than assuming the historical 19-product count. Verify source-image and deployed-file hashes, desktop/mobile containment, the complete accessible description, enlargement, dialog dismissal and focus return; inspect new critical-error output during bounded monitoring. The coordinator records commit/PR, deployment receipt, live results and remaining gaps here after those steps. Issue #74 stays open for any outstanding product-chart inputs.

## Rollback

Restore only this release's exact PHP/CSS preimages when the live bytes still match the release. If another writer has since changed either file, reverse only this size-guide delta in a fresh readback. Retain original and newly staged images unless they are verified unreferenced. Do not restore the database over live orders, alter product options or overwrite unrelated storefront work.

## Live release receipt — 2026-09-20 UTC

Implementation commit `c2335f5f7f0191d6388f03055c5c9676362d3914` shipped through [PR #125](https://github.com/john8barry/bactiveph_com/pull/125), merged as `403eea2f5a3dd6184bd1ea407b6b65d29d458475`. All five project workflows passed on the PR and merged main. Independent code review found no actionable defect. The canonical dirty checkout was preserved; implementation used an isolated worktree from current upstream.

The verified off-server backup comprises seven archives, 554,015,418 bytes, with server/local checksum agreement and archive integrity checks. Scoped PHP/CSS preimages are retained privately. Nine files were installed with fresh destination-hash guards, images first and PHP last, from 04:55:46 through 04:59:13 UTC. No database, shared CSS, dialog JavaScript or catalogue module was deployed. Live PHP SHA-256 is `edd4a88e93c255d50ab93e2f94093677c1eab559e617925c34ad6813a22006ce`; dedicated CSS is `bbf2e938f25d006753336b2056d921e753881225c1a3b08f1bee551f6f19b8b1`. All seven public JPEG hashes match the original provenance table.

Anonymous checks passed on all 22 published product pages: seven exact charts and 15 unchanged sizing-help fallbacks. All seven standalone selections, the chooser, invalid selection and nonscalar selection passed. Ordinary public URLs served current content without cache purging. Browser checks at 1280×900 and 390×844 confirmed each of the seven dialogs and standalone pages loads one complete image within the viewport. Escape and close restore trigger focus; scrolling and backdrop dismissal also passed. Full-size link destinations and original 853×1280 rendering were verified; the in-app browser did not expose a new tab after a target-blank click, so new-tab creation itself was not independently verified. Hidden descriptions were checked in rendered markup, not with a screen reader.

Authenticated file readback confirmed all nine installed hashes and retained protected files. The final log readback at 05:04:35 UTC covered 322 seconds after installation: no new bytes in the existing PHP error log, no new critical entries, and no WordPress debug log. Existing separate production shared-CSS changes remained intact. Serve Dress retained its S–XL commerce options and its supplied numeric chart without conversion.

### Independently observed catalogue changes

Only Bubble Dress 1010 differed from the initial catalogue fingerprint; all 21 other product fingerprints matched. Independent product-only investigation found Black variations created 04:45:40–04:47:00, Pure White variations created 04:49:44–04:51:12, and the parent modified at 04:52:53, before sizing deployment began. Gallery IDs expanded from `375` to `375,386,376`; Black and Pure White colour/variation options were added alongside Powder Blue and Sakura Pink. Featured image 1011 and existing Sakura/Powder variations were preserved. The responsible person or process is unverified; John/B Active owns any follow-up on these separate catalogue edits. The sizing source and deployment helper have no catalogue write path, and no catalogue rollback was performed.

The full backup captured these catalogue edits in progress; it is a verified archive, not a transactionally consistent point-in-time catalogue snapshot. Do not use it to undo commerce data. This release's recovery uses only guarded theme-file preimages. The private rollback helper refuses changed destination hashes, restores scoped PHP/CSS preimages, and removes only exact matching release assets after reverting their references. Preserve all later writers and live orders.

This seven-chart batch is complete. Issue #74 remains open for the other 15 published products' chart inputs; those remaining associations require their own confirmed source charts and release record.
