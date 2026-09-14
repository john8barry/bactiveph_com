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

Live Flow colour switching exposed a remaining image-delivery defect: returning from Lavender to Black restores Blocksy's responsive first-slide markup and allows a500px derivative on mobile. The variation payload contains the correct full original, and cart selection remains correct. Root cause is Blocksy's separate blocksy_original_image payload, which restores the first slide. The follow-up normalizes both image payloads independently; private desktop colour-return and mobile reset/reselect checks now retain the853px original without srcset. PHP contracts and independent review passed. PR93 merged as e9b5fd175c27cbf059623f9d6cc3573a6cb98945; both follow-up files are deployed with matching hashes. Live desktop colour-return and mobile reset preserve the853px original; ordinary unauthenticated payload readback passes all12 variations. It does not change source attachments or commerce data.

Card price alignment follow-up integrated as cec68468 (reviewed source8b5f1d15); final browser fixture passed7/7 cases including mobile, mixed native cards and unknown children. This follow-up file is deployed; shared collection switch remains off.

## Next qualified work

Elite95, Varsity111 and Serve89 completed their private keyboard, native fallback and existing size-help dialog checks. Match83, Rally Dress50 and Court Dress36 passed available combination checks and bounded private carts (White/L/₱2,700; Beige/M/₱2,250; Turquoise Blue/M/₱2,800), all quantity1 and removed. Rally's sold-out Gray and S options remain disabled; Navy Blue/M and Court Dress Black/M correctly remain unavailable. Remaining per-product visual evidence is tracked in the checklist.

The editorial WebP derivative is91,130bytes at1448×1086, independently reviewed against its source. The original master is retained. Private desktop/mobile composition, unchanged copy and actual sizing-link destination passed. Shared homepage card prices align in the private preview. Editorial and shared presentation are not yet live.

## Shared presentation live

The shared card presentation and existing homepage editorial section are now enabled as shared-20260914 after the follow-up20-minute canary monitor passed46 checks over1,205.59 seconds. New editorial attachment799 is separately imported; no product image assignments changed. Ordinary homepage and exact asset-hash readback pass. Desktop card action rows align; mobile remains two columns without horizontal overflow. Desktop/mobile editorial render and preserved factual copy pass. New shared20-minute monitoring and next-day verification remain pending.

Shared rollback conditionally removes only this release-owned collection option and retains the unreferenced new editorial asset. It rejects later option/product-registry edits. Actual private activation, rollback, idempotence and competing-writer rejection passed with product, variation and order records unchanged.

## Remaining-product qualification progress

Warm-Up Jacket, Everyday Tee, Sculpt Romper and Strappy Bra completed private colour/size availability, keyboard, dropdown fallback, size-help and isolated cart samples; every sample was removed. Court Skort preserves numeric labels and its illustrated chart; White size 10 cart sample passed and was removed. Its complete AJAX browser matrix remains pending.

The first batch stage stopped before provider writes because Woo returned the main attachment ID as a string while the reviewed manifest uses integers. Normalizing attachment IDs preserves the exact gallery ordering and hash checks. Fresh production readback then passed and an encrypted before-option backup was created for first-v2-20260914. Actual private compare-and-swap activation/rollback, later-writer refusal and unchanged commerce checks passed.

A transient gallery mismatch during viewport switching was rechecked. Settled Court Skort resizing and a fresh Strappy Bra White/M immediate resize both preserved the matching slide. No production code change has been made for this observation.

## First product batch live

The shared presentation monitor passed at 13:56:33 UTC: 84 ordinary GET checks over 1205.43 seconds, zero failures. Elite 95, Varsity 111 and Serve 89 were then enabled through the guarded option compare-and-swap. Fresh encrypted backup SHA-256: `a41b0b925d0f10d2afb187440b7fa5e98a26d6f60553795e41976b5ffe8280c7`. Effective registry readback passed; image assignments and commerce fields were not written.

All three passed independent anonymous live cart checks: Elite variation 100 / Black M / PHP 3000; Varsity 114 / Green M / PHP 3300; Serve 92 / Sakura Pink M / PHP 2300. Each cart contained quantity one and the exact native variation ID; all test items were removed without orders or payments. Desktop/mobile browser checks confirmed original images, matching selected colours, enabled purchase controls, no horizontal overflow at 375 pixels and SKU privacy. The first-batch 20-minute monitor remains running; next-day verification remains pending.

Fresh Strappy Bra immediate resizing tests passed in both directions after settlement. The earlier transient observation did not reproduce; no code change was made. Court Skort full AJAX browser matrix remains pending because rapid test clicks can precede the native lookup completion. Its numeric chart and White 10 isolated cart sample passed; do not infer a catalogue defect from the harness timing.

## Image performance follow-up

A bounded public asset audit confirmed full source URLs/dimensions and no scaled gallery srcsets on the five live enhanced products. Flow originals total about 133 KB. Some other accepted PNGs are 1.61–2.30 MB each; potential unique originals total about 4.48 MB for Courtline, 1.65 MB Serve, 6.87 MB Elite and 2.30 MB Varsity. These are potential asset totals, not measured browser transfers or Core Web Vitals. First gallery images retain lazy loading without explicit fetch priority. Follow-up: produce reviewed optimized derivatives at the same dimensions while retaining masters, and assess first-image loading priority. Do not reduce gallery resolution to the former 600-pixel variants. This optimization remains unfinished.
