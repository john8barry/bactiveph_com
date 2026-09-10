# Public SKU privacy: production verified

Owner: B Active / John Barry. Work record: [issue 71](https://github.com/john8barry/bactiveph_com/issues/71).
Base: `92a1bae4938d67e583cdbf929f02da6d815a72e7`; branch `codex/public-sku-privacy`.

## Result and release boundary

The child theme now removes operational SKUs from customer-facing output without
changing stored product/variation SKUs, lookup tables, product getters, backend
search, imports, or administrative exports. SKU labels, blocks, variation JSON,
product/order structured data, button/cart attributes, Store API responses,
hydration, and customer HTML/plain-text email items are covered. Public SKU
queries return a generic 400; public text search does not match internal SKUs.
Store API privacy applies to guests and privileged storefront visitors, including
mixed-case routes and nested/batch responses.

No product names, prices, inventory, variation relationships, or payment settings
are changed. The module is loaded by both maintained child-theme copies. Core,
parent-theme, and plugin files remain unchanged.

**Production deployed and verified at 19:31–19:36 UTC after John approved the exact
release in the controlling task.** PR #72 merged as
`5b2aba5dfe9e0d369ed9c34f30d9bc72778a79d5` from reviewed head
`9477f54cc85da6dbb68dad780d18f630f4775a99`. The additive live functions loader,
privacy module, and media/routing changes passed destination hash readback.
The earlier candidate-only checks below are supplemented by deployed verification.

## Integrations and media

- Google Listings 3.9.3 already uses product/variation IDs in browser events and
  slug-plus-ID catalog offer IDs; no SKU getter was found in its `src` tree.
- TikTok 1.4.1 directly emits SKU-derived pixel IDs and has no mapping hook.
  Its pixel, catalog, token, and server-event settings were unconfigured at
  inspection. The module removes only that plugin's named event-emitting
  callbacks. TikTok tracking must stay inactive until an ID-based pixel/catalog
  adapter is reviewed; this release does not implement or activate that adapter.
- No SKU references were found in the current custom PayMongo, child-theme,
  MU-plugin, or MailPoet WooCommerce PHP paths inspected. No downloadable products
  or active PDF invoice plugin was identified. Future integrations/customer
  documents must pass this privacy contract before activation.
- Media planning includes historical batch-coded images even when the original
  product SKU has since been cleared. Production has **25 attachment records /
  136 image files**, including derivatives, requiring neutral filenames. IDs:
  `37, 51, 57, 58, 59, 60, 61, 62, 84, 90, 96, 97, 98, 118, 119, 161, 162, 163,
  164, 186, 258, 259, 260, 261, 262`.
- Images are copied byte-for-byte to `bactive-image-<attachment-id>[-<index>].ext`.
  Only attachment file/size metadata changes. Exact old image paths receive 301
  redirects in a marked `.htaccess` block, preserving incoming links while new
  page source and media responses use neutral URLs. Originals remain on disk for
  rollback. Production attachment GUID/title/slug/content/excerpt inspection
  found no current SKU matches; staging public media responses were also scanned.

## Verified evidence, September 10 UTC

Production identity: `https://bactiveph.com`, `waypmvhk_bactwp`, Blocksy child,
WordPress 7.1 / WooCommerce **11.1.0**. Staging identity:
`https://staging.bactiveph.com`, `waypmvhk_stg`, Blocksy child, WordPress 7.1 /
WooCommerce **10.8.1**, `blog_public=0`. Staging is older and does not have the
production Google/TikTok plugin set; it was not upgraded as part of this change.

- Fresh full staging Updraft backup at **18:30:40 UTC**, copied off-server and
  verified by **18:32:00 UTC**: database, plugins, themes, uploads, MU-plugins,
  and others. All six server/local SHA-256 comparisons and ZIP/gzip integrity
  checks passed. Manual off-server copies follow the project access runbook;
  automatic remote storage remains unconfigured.
- Local regressions: **38 privacy checks**, **10 media filesystem checks**, and
  **5 scanner tests** passed. Existing punctuation and Sage-header checks passed.
  PHP syntax, complete diff checks, and maintained source-mirror comparisons pass.
- Independent review identified and corrected management schema/AJAX regression,
  object metadata leakage, mixed-case REST bypass, partial-copy recovery, and
  mixed-state rollback handling.
- Installed-runtime tests: staging **212 checks / 19 published products / 188
  variations**; production's 11.1 candidate request **242 checks / 19 published
  products / 218 variations**. Covered guest/manager REST, hydration, authenticated
  management SKU search, getters/lookup, product/order schema, and customer/admin
  HTML/plain email and fulfillment output. **Zero saved orders; zero sent emails.**
  Stored SKU and lookup-table digests were identical before/after each run.
- Staging code and media deployed at approximately **18:40 UTC**, with destination
  hash readback. Its larger historical catalogue required **45 attachments / 219
  files**. All **219 old paths returned 301 to the intended neutral URL**, and all
  **219 neutral paths returned 200**.
- Staging crawl: **80 successful page/API responses, zero SKU findings**, no
  pending URLs. Three existing navigation destinations returned errors:
  `/collections/paddles`, `/collections/tops`, `/pickleball-looks/`. They are
  outside the image-only routing changes; this record does not claim overall
  staging link health. Noindex staging used an authenticated 57-URL content/taxonomy
  manifest instead of claiming a public sitemap exists.
- Additional anonymous API checks: normal and uppercase Store API product routes
  returned 200 with no SKU fields; explicit SKU query returned 400; SKU text search
  returned no products. All **73 staging media REST objects** were clean; requesting
  page two returned the expected out-of-range 400.
- Browser walkthrough: Court Dress options S / Court Ivory selected, add-to-cart
  succeeded, cart and checkout showed the correct product/options/amount, and
  removal left the test cart empty. No SKU labels or attributes, broken images,
  or browser console errors appeared. Checkout was not submitted.
- Interrupted-state media rollback rehearsed by restoring one known metadata
  field while retaining its new filename, then rolling back all records and
  reapplying. Both runs passed at **18:47 UTC**. No SKU-change errors appeared in
  staging's root error-log tail; other inspected PHP logs did not exist.

Private manifests, original destination snapshots, archive checksums, runtime
receipts, and HTTP audit results are retained under the operator's private
`bactiveph-public-sku-privacy` release directory, outside Git and the web root.
Do not publish backups, SKU inventories, original path maps, or customer data.

| Artifact | SHA-256 |
| --- | --- |
| Privacy module | `9508cdbc879eb098b299210aaa417bcf7794e3398430c09b82c4e8f569a0c7f5` |
| Staging functions after additive loader | `99b5242bdd7bedd11def24280a3018c2d15af66ec13c45bb9de56987263cddfc` |
| Production functions before | `84ef751a784187d8b876b1d6619475ebeda94715ee4e587f7cc0d26c5710e422` |
| Production functions candidate | `e83080ad5e10de7023755d030044e051607fd60c923924ae29b98236b8fda72c` |
| Production routing before | `1c9c36fbc22ffb57d50211267048c4b10b2c818f7a53708fff59059190d4d01d` |
| Production routing candidate | `a878aab81d9d86394da016eb2a75c2c6d8d99a35a8b5ad9a0efc7b92ed5ec258` |

## Production release receipt

- Fresh full production Updraft backup at **19:02:14 UTC**, verified off-server by
  **19:04:26 UTC**. Six components passed matching SHA-256 and ZIP/gzip integrity.
  Identity, reviewed PR head and three successful checks, live functions/routing,
  all attachment metadata and source image hashes were rechecked before deployment.
- Serialized deployment completed at **19:31:48 UTC**. Exactly 25 attachments and
  136 images received the approved neutral names. Live module/functions/routing
  hashes match the candidate table above. LiteSpeed caches were purged.
- Ordinary anonymous production crawl: **98 pages/API responses, zero SKU
  findings, zero errors, zero pending URLs**. Sitemap discovery and all published
  product pages were covered. Additional public checks confirmed uppercase Store
  API routes, blocked explicit SKU lookup, empty SKU text-search results, and all
  **127 media REST objects** without SKU findings.
- All **136 old image URLs redirect to their exact neutral URLs**; all **136 new
  URLs return 200 with byte-for-byte matching source SHA-256 checksums**.
- Deployed WooCommerce **11.1.0** runtime: **242 checks / 19 products / 218
  variations**, zero saved orders and zero sent emails. Customer HTML/plain and
  fulfillment output, authenticated management search, hydration and structured
  data checks passed. SKU/lookup checksum remains
  `a2a82d6e4e40de5f434270ba771d42b9f09beaa0a057392f2cd2cf709cf4b335`.
- Logged-in browser: Court Dress S / Black option selection, add-to-cart and
  checkout worked; no SKU labels/attributes, broken images or console errors.
  Checkout was not submitted. Only this task's added item was removed; the
  pre-existing cart item was preserved. The product screenshot was captured in
  the controlling task. Observed product-page network: 79 requests, no old
  batch-coded asset URLs or SKU-named payloads; no active commerce tracking
  requests were observed (Google Fonts was the only Google/TikTok matching host).
- At **19:36:04 UTC**, the available root PHP error log contained no post-release
  entries. WordPress debug and child-theme error logs were absent. This is bounded
  release evidence, not a guarantee about future plugin changes or customer orders.
- Private originals, six backup archives, migration manifest and deployment/audit
  receipts remain available outside Git and the public document root. The earlier
  staging rollback rehearsals apply to the exact deployed helper.

## Release procedure and rollback

1. Obtain John's exact production approval for the reviewed PR. Reconcile its
   current head/CI, production identity, functions hash, media metadata/source
   hashes, and `.htaccess` baseline. Reject drift; do not overwrite another lane.
2. Qualify a **fresh production** full Updraft/off-server backup. The earlier
   production backup is not this release's approval-time recovery evidence.
3. Snapshot current destination files and the per-attachment manifest privately.
   Upload only the linted module and an additive functions loader through strict
   SFTP. Production functions contain legitimate changes not mirrored wholesale
   in this branch: generate the reviewed additive patch from the verified live
   snapshot, never replace the entire live file with the repository copy.
4. Keep `tools/sku_media.php` and its manifest outside the web root. Run its
   `plan` mode for review, then `apply <private-manifest>` through WP-CLI at the
   exact production root. It validates site, paths, source checksums, known
   attachment states and routing, copies atomically, and verifies metadata writes.
   Preserve the exact private manifest for recovery; do not regenerate it after
   apply. Partial known before/after states may be resumed or rolled back;
   unrelated edits fail closed.
5. Purge affected page/product/image caches. Recheck ordinary public URLs,
   sitemap coverage, all neutral assets/redirects, media and Store API responses,
   browser variation/cart/checkout, and sanitized error logs. Compare SKU/lookup
   digests again. Keep the issue open through bounded post-release observation.
6. Rollback: restore the original functions snapshot after checking the deployed
   hash, then run `rollback <same-private-manifest>` for attachment/routing state.
   The inactive module and neutral copies can remain; no destructive deletion is
   needed. Purge affected caches and independently verify restored behavior.
   Never restore a whole staging database over production.

## Remaining maintenance boundaries

Production release acceptance passed. Future customer-facing integrations and
documents must retain this privacy contract. The existing
dependency backlog was re-read using the correct `john8barry` account: 19 open
alerts (10 high, 8 medium, 1 low), tracked separately in issue 7. Initial default
account alert access returned 403; that access gap was resolved without changing
the shared GitHub login. This change adds no vendor dependency or gateway activation
and does not claim to resolve that backlog or unrelated staging link defects.
