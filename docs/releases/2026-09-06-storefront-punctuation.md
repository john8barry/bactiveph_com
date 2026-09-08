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

Initial planning crawl: 61 URL fetches, 509 occurrences including repeated shared text. Source/hook review passed. Production publication and verification completed on 2026-09-06. The exact results and limitations are recorded below.

## Reviewed content and release candidate

Fresh authenticated inventory covered 225 published pages/posts/products/variations and 26 terms. The explicit manifest changes 27 objects with 103 reviewed replacements: privacy3, home14, shipping20, FAQ21, terms24, fabric304, posts309/311/317, 17 product excerpts and blogdescription. No variation or term required a write. Every replacement preserves digits, HTML tags, ordinary hyphens and exact inverse transformation. Independent review corrections were applied before dryrun.

Typography source merged to main7305f76 before its separate runtime release. Production functions still matched949a12f435e8ce6efec5b8301465f80017143e3cb410ef920eedb5d893383b56. This runtime patch applies only punctuation changes to those live bytes, producing0f35633e699ff2868d27f490a0f48a1216d68841dfc8f92fa88fbb435e3051cb. The later typography/header writer must preserve that patch when installing its separately authorized enqueue/assets. Source mirrors include the merged typography enqueue; do not mistake the full merged source file for this bounded runtime payload.

Private PHP8.2 lint and both CLI harnesses passed using the deployed WordPress formatting source. GitHub PHP8.3 checks passed for final candidate 8b53d6b in run 34020378007. PR 29 merged as 4a3611f68ce52322a2c8bd571c2806209b9248c1. Root's production-only window was returned at 2026-09-06T08:06:54Z, before its 08:13:47Z expiry. All SSH and browser connections were closed and the six private staged files and their empty directories were removed. Full backup manifest b1552c3fb29b2f31698a3e6402b0c109845fd96fd47a47e03fc192903dd93b80 independently verified locally. Exact snapshots and runtime evidence remain private under canonical tmp/site-dashes-20260906.

Existing published privacy copy still references HitPay and Mailchimp; this punctuation-only pass preserves those substantive claims and routes their reconciliation separately to the payment/marketing owners.


## Production acceptance

All 27 changed objects independently matched their expected after hashes. A second authenticated inventory found zero decoded en/em dashes in titles, content and excerpts across 225 published records or site name/description. The retained homepage has neither removed section and hashes to `1f0a120db564934c968b6f61334188d000cb8c0cf2bee8c7630129bd7d22c7e0`.

The recursive public crawl followed eight sitemap files and all discovered safe public links, checking 86 HTML pages and 24 stylesheets. Its result was `complete_static_public`, zero dash hits and zero errors. The repeatable audit now includes the two anonymous newsletter utility pages while rejecting unsubscribe/token/action URLs and excluding Cloudflare infrastructure. Twelve audit unit tests pass. HTML entities, accessible and hidden text, metadata, structured JSON and variation data, and generated CSS content were included.

Desktop (1440px) and mobile (390px) browser checks covered homepage, shop, pagination, search, product tabs, logged-out account, cart and checkout. No new JavaScript errors, dash hits or horizontal overflow were found. A single available variation was added to a temporary anonymous cart; populated cart and real checkout passed at both widths. The cart was cleared afterward and no order was submitted.

The existing size-guide trigger did not open its dialog. The size table was inspected at both widths by opening the existing dialog in the test browser only; its ranges and layout passed. The trigger and existing escaped apostrophe are tracked separately in [issue 34](https://github.com/john8barry/bactiveph_com/issues/34). This release does not claim that the normal size-guide click flow passed. Customer-authenticated account screens and private customer content were not inspected.

Independent post-deployment readback at approximately 08:03Z confirmed unchanged gateway settings, active plugins, protected configuration/files, order state and IDs, stock, theme settings, BIR artwork, footer, trust bar and custom CSS. Error-log size remained 27,499 bytes during the bounded deployment and browser-verification period. Current-site LiteSpeed page caches were purged. No staging or payment/email/provider settings changed.

Runtime file SHA-256 values:

| File | SHA-256 |
| --- | --- |
| functions.php before | 949a12f435e8ce6efec5b8301465f80017143e3cb410ef920eedb5d893383b56 |
| functions.php after | 0f35633e699ff2868d27f490a0f48a1216d68841dfc8f92fa88fbb435e3051cb |
| inc/storefront-punctuation.php | fe0da11b6245ed3046d7e79bdd5a0d920a0e7f3c2ba7102dfe22b4db852061e7 |

Private evidence is retained under `tmp/site-dashes-20260906`: `backup-reference.json`, `content-publish.json`, `file-publish.json`, `independent-verify.json`, `audit-final.json`, `browser-report.json`, `cart-report.json`, `final-readback.json` and `terminal-release.json`. Full snapshots and browser artifacts remain outside Git. The payment coordinator received the terminal release for independent acceptance before the next host writer.
