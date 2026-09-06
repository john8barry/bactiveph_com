# Storefront punctuation cleanup

Work record: [issue 25](https://github.com/john8barry/bactiveph_com/issues/25). Owner: Remove en and em dashes task, 01a07596-0425-7fc0-b728-d8c69c4d8a4a. Severity: low. John authorized implementation and production publication of the approved plan.

## Behavior

Use natural punctuation in authored prose, “to” for ranges, and | between document-title parts. Preserve ordinary hyphens, prices, units, legal meaning, links and markup. Shared product size tables and fabric text are maintained in both child-theme source mirrors. A narrow include controls WordPress generated punctuation and WooCommerce price/result ranges. No parent-theme/core/plugin source changes or whole-response rewriting.

## Boundaries and coordination

Canonical checkout is dirty at 42e13c3; isolated codex/remove-site-dashes starts from remote main b16557f. Do not copy old local source over current production. Production is https://bactiveph.com, /home/waypmvhk/bactiveph.com, database waypmvhk_bactwp, Blocksy child theme. The exact site, current content/file hashes and writer window must be verified at action time.

Story removal then signup removal precede this homepage edit. Typography enqueue changes must be preserved if they land first. All host probes/writes are serialized with the payment coordinator. No staging, payment configuration, email settings, orders or customer-record updates. Existing issues 7 and 9 remain separate work with their owners; this does not qualify or activate payments. Official BIR artwork and links are preserved.

## Release procedure

1. Verify the shared fresh full off-server backup manifest and archive integrity, then capture exact affected public content/options/terms and file bytes after preceding writers finish.
2. Prepare explicit public-copy replacement manifest; inspect every before/after phrase, stable numbers and HTML/block structure. Store full snapshots privately outside Git. Check forward and reverse transformations before writing.
3. Stage only reviewed PHP under a private directory outside the web root. Lint and run CLI harnesses, including relevant existing payment/footer checks. Match destination hashes before atomic file replacement; install the include before functions.php.
4. Use the private CLI helper under an authenticated manager to preflight all objects before changing any. It checks capability, published type/slug, allowed fields, SHA-256 and replacement counts, with a second per-object check at write time. No generic database search/replace.
5. Read back content and files independently, refresh affected page caches, run the full static audit and browser interactions, and compare critical logs and preservation hashes.

## Verification

`python3 -m unittest discover -s tests -p 'test_audit_storefront_dashes.py'`

`php tests/storefront-punctuation.php`

`php tests/storefront-punctuation-manifest.php`

`python3 tools/audit_storefront_dashes.py --base-url https://bactiveph.com/ --sitemap https://bactiveph.com/sitemap.xml --url '/?s=dress&post_type=product' --url /shop/page/2/ --url /cart/ --url /checkout/ --url /my-account/`

The audit uses anonymous GET requests, crawls sitemap and safe public links, decodes entities and JSON, checks hidden/accessibility text and CSS content, excludes mutation URLs, and fails on hits or incomplete coverage. Browser tests separately cover generated/interacted text, desktop/mobile layout, product tabs, size modal, search, cart, checkout and accessible account screens. Do not submit orders. Authenticated customer screens require separate access; report gaps explicitly.

## Rollback

Retain exact pre-change content and file bytes with before/after SHA-256. Reverse only confirmed changed objects after all current values match this release's after hashes. The helper validates inverse replacement uniqueness and round-trip equality before release. After partial/uncertain failure, first reconcile each object independently, especially stopped_at; construct and review a rollback manifest subset for confirmed changed objects only. Do not replay an ambiguous write or run the whole reverse manifest against untouched objects.

Restore functions.php first, then remove the include only if no newer code references it. Refuse a stale rollback if another writer changed a target. Reapply cache refresh and acceptance checks. Never import a whole database over new orders.

## Evidence

Initial planning crawl: 61 URL fetches, 509 occurrences including repeated shared text. Source/hook review passed; ten audit unit tests passed. Production release and full acceptance pending coordinated window.
