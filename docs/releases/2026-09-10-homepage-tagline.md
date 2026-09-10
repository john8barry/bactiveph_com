# Homepage court-to-café tagline correction

Work record: [issue 58](https://github.com/john8barry/bactiveph_com/issues/58). Severity: low. John authorized implementation and production publication on 2026-09-10.

## Behavior and scope

Replace the production homepage hero line `Court-To-café Luxury.` with `From the Court to the Café`. The approved wording uses the definite articles, title case, the acute accent in `café`, and no terminal period. The replacement also removes invalid nested paragraph markup from this tagline block while retaining the existing inline style hook and rendered hero behavior.

The authenticated WordPress inventory identified two underlying public content records containing a court-to-café phrase. Page 14 contains the malformed hero line. Product 95 contains the already-correct running sentence `from the court to the café` and is a preservation target, not a write target. The product excerpt is rendered in the product summary, Features & Fit tab, and product JSON-LD.

No CSS, typography, navigation, product, payment, email, checkout, staging, or provider setting is in scope. Internal build references using `court-to-café` as a compound modifier are valid and remain unchanged.

## Release procedure

1. Reconfirm production identity, page 14 publication/slug, exact content hash, single replacement count, product 95 preservation hash, active writer window, and a recoverable off-server backup.
2. Run the PHP manifest regressions and the Impeccable detector. Inspect the full diff and secret scan before commit.
3. Stage the existing private WP-CLI manifest helper and this manifest outside the web root. Run check mode before apply mode. Never expose the helper as an HTTP endpoint.
4. Apply the one-object manifest, independently read back page 14, and refresh only the homepage cache path.
5. Verify the ordinary production homepage, the preserved product excerpt, the sitemap/public-link crawl, desktop/mobile rendering, and browser console.

## Rollback

Retain the exact pre-change page 14 content privately. Roll back only if page 14 still matches the manifest's after hash; the existing helper then reverses the replacement and verifies the original hash. Never import a whole database or overwrite another writer's later content.

## Evidence

Pre-change page 14 `post_content` SHA-256: `1f0a120db564934c968b6f61334188d000cb8c0cf2bee8c7630129bd7d22c7e0`.

Expected post-change SHA-256: `a7ac16d9cf1e29c70b0faa82bb3fcfc5429758804e09969bf60c79bf93e5524d`.

Production publication and final verification evidence will be added after the live readback succeeds.
