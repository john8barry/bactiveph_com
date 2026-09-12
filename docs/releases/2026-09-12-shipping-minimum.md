# Philippine complimentary shipping minimum

Issue [#77](https://github.com/john8barry/bactiveph_com/issues/77) tracks the production request. Severity: medium customer-promise mismatch. Owner: B Active / John Barry.

## Change and scope

- Raise the WooCommerce minimum from ₱2,000 to ₱5,000 for Davao City, Mindanao, and Luzon & Visayas.
- Keep the fallback/international zone without a complimentary-shipping method.
- Align the shared product Shipping & Returns tab, mini-cart progress message, Shipping & Returns page, FAQ, and Terms.
- Treat ₱5,000 as inclusive: Philippine orders of ₱5,000 or more qualify.
- Suppress the mini-cart complimentary-shipping progress/unlock message after an international destination is selected, and remove any free-shipping rate for a non-`PH` package even if a zone is later misconfigured.

No product prices, inventory, payment settings, coupons, orders, courier rates, local pickup, COD eligibility, tax configuration, or international shipping availability are changed.

## Release control

The repository checkout was dirty and 113 commits behind `origin/main`, so implementation uses the isolated `codex/shipping-minimum-5000` worktree rebased onto `origin/main` at `20fab86`. Do not deploy the whole repository file: derive and apply only the reviewed shipping-policy hunks to the exact current destination file so unrelated live-only changes remain intact.

The private WP-CLI helper and reviewed manifest fail closed on site/database/theme identity, transactional table engines, page IDs/slugs/content hashes, exact replacement counts, domestic zone identities and exact location-set hashes, enabled free-shipping instance IDs, option hashes, discount handling, and the absence of an enabled fallback-zone free-shipping method. All six database changes run in one transaction; a write/readback failure rolls back the transaction, clears affected caches, and verifies the original records before reporting recovery. It supports check, apply, and rollback modes on the explicitly listed production and staging targets.

Action-time production preflight found that the Shipping & Returns page had independently advanced from an outdated ₱2,000 claim to an exclusive “over ₱5,000” claim after PR #79 merged. The follow-up manifest updates only that production page's exact preimage and result hashes; it still replaces one exact list item with the approved inclusive domestic policy and explicit international exclusion. The other five database preimages remain unchanged.

Stage first. Before production, obtain a fresh complete backup plus private off-server checksums, retain exact preimages for the child-theme file and six database records, verify the current production hashes again, and serialize the writer window. After applying only the shipping changes, invalidate the affected page/object caches and confirm the ordinary public URLs, the WooCommerce settings, the domestic boundary, the international negative path, and server logs.

## Rollback

Use the manifest rollback only while every destination still matches its recorded post-change hash. Restore the exact pre-release shipping hunks in the latest child-theme file rather than overwriting unrelated theme work. Rollback changes the three domestic free-shipping minimums from ₱5,000 back to ₱2,000 and restores the three exact prior public-copy records. It does not add an international shipping method.

## Live verification

Production was released on 2026-09-12 through [PR #79](https://github.com/john8barry/bactiveph_com/pull/79) (`cdf6efb`) and the exact-preimage reconciliation in [PR #82](https://github.com/john8barry/bactiveph_com/pull/82) (`f90b69b`). Before the write, a fresh six-component production backup completed under nonce `cb748067e150`; all 370,813,635 downloaded bytes passed off-server checksum and archive-integrity verification.

The serialized production write began at 05:38:39 UTC. The live child-theme file advanced from SHA-256 `b5b0282725e767677fd9b7fe60fa28667684953291fadf2f55a1e89ce9520b00` to `ec2ddcc4e013793bceeb711424cc6b2084358240c3e08048d7ea192d586070b0`, preserving the independently deployed size-guide stylesheet and product images byte-for-byte. The transactional manifest changed exactly page IDs 20, 21, and 24 plus free-shipping instances 13, 16, and 18. All three domestic methods now require a minimum of `5000`; the fallback/international zone still has no complimentary-shipping method.

The live WooCommerce boundary test confirms that ₱4,999 does not qualify, ₱5,000 qualifies in all three Philippine zones, and a ₱5,050 subtotal reduced below the minimum by a ₱51 discount does not qualify. A non-Philippine or blank destination removes complimentary-shipping rates and suppresses the cart progress message; a Philippine destination retains the eligible rate and domestic message.

An independent anonymous crawl checked the sitemap index, all seven child sitemaps, and 64 public URLs. All 19 product pages show the same inclusive Philippine-only policy, and the FAQ, Shipping & Returns, and Terms pages agree. No rendered page contains `₱2,000` or the exclusive `over ₱5,000` formulation. The Court Skort page returns 200 and shows the approved copy. Production emitted zero new PHP error-log bytes through final readback, and the production writer window was released after verification.
