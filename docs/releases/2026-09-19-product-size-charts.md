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
