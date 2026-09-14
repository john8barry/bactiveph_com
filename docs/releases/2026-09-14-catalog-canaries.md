# Catalogue visual canaries — 14 September 2026

Courtline Dress117 and Flow Skort154 are enabled on production. Other product enhancements, collection cards and editorial presentation remain off while verification continues. This is not full catalogue completion.

## Source and destination

- PR50 merged as7e5718bcd76afb8718b745ca2412da6565933773 after allfive checks passed.
- Exact target: https://bactiveph.com, child theme blocksy-child.
- Six release files: functions.php; inc/catalog-visuals.php; inc/collection-visuals.php; assets/css/catalog-visuals.css; assets/css/collection-visuals.css; assets/js/catalog-visuals.js.
- Authenticated file readback matches the reviewed candidate. Both original release options were absent. Maxim trust-bar and SVG hashes match their separate release and remain unchanged.
- Product option version: catalog-canary-20260914. Current enabled products:117 and154. Collection option remains absent.

## Recovery evidence

The September14 full off-server backup covers database, site files and required configuration, encrypted with AES-256-GCM and integrity verified. The September12 isolated actual restore, full variation/cart checks and targeted rollback rehearsal remain the recovery proof; the newer full capture has not itself been restored.

An additional exact-file package was captured12:31UTC after the Maxim release. Its encrypted SHA256 is e82d978edfcffecaef5370231cbf7dfdc101a4ebb71c542dee8e4031d479f14c. Key is stored separately,0600. Exact-batch apply, rollback and interrupted rollback passed locally. Source files are installed before functions.php; rollback reverses the order. Activation checks allsix deployed hashes, rejects a constant override, validates exact product/term IDs and verifies the effective registry. Intended registry values are saved before remote writes so an uncertain response remains recoverable.

Rollback first restores the release-owned option's original absence only when its current value matches a recorded intended registry. Then restore the loader and prior files with expected-hash checks, deleting only release-created files that still match. Stop for any later writer conflict. Never import the backup database over newer orders, stock or prices.

## Verification to date

| Product | Private evidence | Live evidence | Remaining |
|---|---|---|---|
| Courtline117 | Eight selectable pairs; original gallery; desktop/mobile; keyboard, native fallback and sizehelp; correct Jujube Red/M/qty1/₱3,300 cart, removed | Ordinary unauthenticated URL200; reviewed modules/hashes; matching Jujube photograph;375px mobile with no overflow;>=44px selectors; anonymous variation125 cart sample correct and removed | Twenty-minute monitor passed (1,204.78 seconds,25 checks); next-day check pending |
| Flow154 | All12 settled pairs select matching gallery; reset disables purchasing; keyboard thumbnails, native fallback, sizehelp; desktop/mobile; Lavender/M/qty1/₱1,500 cart correct and removed | Ordinary public cart: variation555, Lavender/M/qty1/₱1,500 correct and removed; allsix file hashes match; mobile375px no overflow | Twenty-minute monitor running; first-slide reset image regression below; nextday |
| Elite95 |12 pairs, original image loading, desktop/mobile; Pure White/XL/qty1/₱3,000 cart correct and removed | Not enabled | Finish individual accessibility/shared-layout checks and later batch release |
| Varsity111 | Four sizes, keyboard selection, desktop/mobile; Green/XL/qty1/₱3,300 cart correct and removed | Not enabled | Finish individual fallback/shared-layout checks and later batch release |

No orders or payments were started. All253 variation backend checks and43 invalid-combination rejection cases were previously exercised in the isolated restore. Current production verification uses bounded independent carts only. Initial anonymous-cart harness assertions counted duplicate responsive remove links twice; deduplication corrected the harness and the final sample was removed successfully.

The protected exceptions remain Bubble56, Rally Skort160, Sculpt Leggings238 and Ribbed Tank211; no affected mappings changed. Pleated148 and Aria347 remain excluded while trashed. Existing general size-help content on Courtline/Flow is preserved, rather than substituting another product's chart.

Private operational evidence is held under the BactivePH catalog-release-20260914 directory outside the repository. Sanitized project updates: issue48. Public monitor does not replace next-day verification or qualify unreleased products.

## Follow-up findings

Live Flow colour switching exposed a remaining image-delivery defect: returning from Lavender to Black restores Blocksy's responsive first-slide markup and allows a500px derivative on mobile. The variation payload contains the correct full original, and cart selection remains correct. Root cause is Blocksy's separate blocksy_original_image payload, which restores the first slide. The follow-up normalizes both image payloads independently; private desktop colour-return and mobile reset/reselect checks now retain the853px original without srcset. PHP contracts and independent review passed. Production fix is pending. It does not change source attachments or commerce data.

Card price alignment follow-up integrated as cec68468 (reviewed source8b5f1d15); final browser fixture passed7/7 cases including mobile, mixed native cards and unknown children. This follow-up is not yet deployed.
