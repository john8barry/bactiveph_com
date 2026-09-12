# Philippine complimentary shipping minimum

Issue [#77](https://github.com/john8barry/bactiveph_com/issues/77) tracks the production request. Severity: medium customer-promise mismatch. Owner: B Active / John Barry.

## Change and scope

- Raise the WooCommerce minimum from ₱2,000 to ₱5,000 for Davao City, Mindanao, and Luzon & Visayas.
- Keep the fallback/international zone without a complimentary-shipping method.
- Align the shared product Shipping & Returns tab, mini-cart progress message, Shipping & Returns page, FAQ, and Terms.
- Treat ₱5,000 as inclusive: Philippine orders of ₱5,000 or more qualify.
- Suppress the mini-cart complimentary-shipping progress/unlock message after an international destination is selected.

No product prices, inventory, payment settings, coupons, orders, courier rates, local pickup, COD eligibility, tax configuration, or international shipping availability are changed.

## Release control

The repository checkout was dirty and 113 commits behind `origin/main`, so implementation uses the isolated `codex/shipping-minimum-5000` worktree based on `origin/main` at `cf0fc63`. Production's child-theme `functions.php` differs from `origin/main` only because merged size-chart issue #74 has not been installed there. Do not deploy the whole repository file: apply only the reviewed shipping-policy hunks to the exact current destination file.

The private WP-CLI helper and reviewed manifest fail closed on site/database/theme identity, page IDs/slugs/content hashes, exact replacement counts, domestic zone identities, enabled free-shipping instance IDs, option hashes, and the absence of an enabled fallback-zone free-shipping method. It supports check, apply, and rollback modes on the explicitly listed production and staging targets.

Stage first. Before production, obtain a fresh complete backup plus private off-server checksums, retain exact preimages for the child-theme file and six database records, verify the current production hashes again, and serialize the writer window. After applying only the shipping changes, invalidate the affected page/object caches and confirm the ordinary public URLs, the WooCommerce settings, the domestic boundary, the international negative path, and server logs.

## Rollback

Use the manifest rollback only while every destination still matches its recorded post-change hash. Restore the exact pre-release shipping hunks in the latest child-theme file rather than overwriting unrelated theme work. Rollback changes the three domestic free-shipping minimums from ₱5,000 back to ₱2,000 and restores the three exact prior public-copy records. It does not add an international shipping method.
