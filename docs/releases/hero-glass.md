# Homepage glass treatment

The approved glass treatment and the pointer correction from [PR #18](https://github.com/john8barry/bactiveph_com/pull/18) are installed in production as of 2026-09-06 UTC. The correction's source merge is `5778889ba155c93eb12d0f49d52ee0196f481a53`. Deployment receipts, backup/rollback evidence, and independent acceptance are tracked in [issue #13](https://github.com/john8barry/bactiveph_com/issues/13).

The homepage hero uses a clearer glass panel with a fine reflective rim and a restrained pointer highlight. A press compresses only the decorative rim; the card and text stay still. The rollout preserves the approved appearance, existing copy/photo/layout, shop destination, and other theme files. A follow-up corrects pointer tracking when a delayed visibility notification reports the visible card as offscreen.

## Files and behavior

| File | SHA-256 |
| --- | --- |
| `wordpress/wp-content/themes/blocksy-child/assets/css/hero-glass.css` | `25697e3e1c73cb1c10df10555036144b12eed7cc6dbf2837299f44f5bd5cf0ed` |
| `wordpress/wp-content/themes/blocksy-child/assets/js/hero-glass.js` | `ece9a7cb980823d5f28dbc6ce05daff2e6209fdf9beb5e365e4d9c4fe8f52c9b` |
| `wordpress/wp-content/mu-plugins/bactiveph-hero-glass.php` | `158b00d20cbdfe326b5d638c235fd158cecb0e7a6c708946f8ac67f15c330eb2` |
| `wordpress/wp-content/mu-plugins/bactiveph-hero-glass-staging.php` | `24df1dc62a13f5d72dfa6b674aa90baf038e54f6190eae66dbc880631f414cbf` |

Only the first three files are installed on production. Each loader is restricted to its exact environment root, WordPress URLs, child theme, and front page. It loads CSS after `blocksy-child-custom` and JavaScript in the footer with `defer`. If either asset is missing or the theme dependency is unavailable, neither asset is enqueued.

The CSS targets only `.home .hero-glass-card`. A solid readable panel is the default without backdrop-filter support. Reduced transparency and increased contrast use a solid background and dark text; forced colors use system colors. Reduced motion disables spatial feedback, and touch mode does not track the pointer. The script performs no network requests, storage, analytics, or provider operations, and stops animation frames when settled or inactive.

Pointer input can activate the highlight without waiting for an IntersectionObserver notification. The observer's latest notification still resets offscreen activity; document visibility, pointer exit, scrolling, and accessibility preferences also stop it. Both loaders use the new script hash as the asset version so existing browser caches receive the correction.

## Validation

- Staging: ordinary cached homepage and browser refresh load the exact CSS/JS bytes once; pointer, press, exit reset, keyboard focus, reduced motion/transparency, forced colors, and 390px responsive layout passed browser checks.
- Independent CSS/JS review and separate staging/production loader reviews passed. Production loader differs from staging only in environment identity/name.
- The production correction passed ordinary desktop/mobile cached requests and browser refresh with script version `ece9a7cb9808`. Live pointer movement, press/release, exit/offscreen reset, scroll-back reentry, 390px layout, keyboard focus, reduced motion/transparency, and forced colors passed. The approved CSS bytes remained unchanged.
- Fresh production comparisons showed only the two intended files changed; protected theme files, payment settings, order snapshots, stock, and server error log remained unchanged through the bounded observation period. No payment or order was created by these checks. A focused browser observation had no runtime exceptions; a separate Cloudflare analytics beacon connection failure was observed, so this is not a claim that every third-party resource loaded.
- Run `node --test tests/hero-glass.test.cjs` for the visibility regression, observer batches, bounded animation frames, reset events, touch, and reduced-motion cases. The original script fails four cases; the corrected script passes all nine.
- Local JavaScript syntax and whitespace/secret-pattern checks passed. Destination PHP lint, backup, exact asset readback, and rollback verification are release gates.
- Native touch hardware and Safari have not been certified. Staging has older hero imagery/copy than production; both environments require their own visual readback.

## Rollout and rollback

Use the documented trusted SSH/SFTP route and a serialized writer window. Verify the production home/site URLs, active child theme, current protected-file hashes, and exact prior state of the target files. Obtain a fresh complete backup with verified off-server components before installation. Upload only reviewed files into a private directory, verify hashes and PHP syntax, then atomically install assets first and the loader last. Purge only the homepage and verify ordinary public requests, asset bytes, browser behavior, and fresh errors. A successful CLI exit alone is insufficient cache evidence; `purge_front` depends on the request's referrer and is not equivalent to `purge_frontpage`.

The authenticated LiteSpeed `purge_frontpage` action is one supported cache path. When no normal WordPress admin session is available, the verified alternative is the public front-page tag API through trusted WP-CLI: first reject any existing purge queue, require the plugin's native `LITESPEED_CLI` context, and call `LiteSpeed\Purge::add(LiteSpeed\Tag::TYPE_FRONTPAGE)`. Do not define that context manually, impersonate a browser user, or use the taxonomy-oriented `purge_tag()` method. LiteSpeed queues the site-prefixed `F` tag and delivers it through an HTTP request to that site's `wp-admin/admin-ajax.php`. Supply existing outer HTTP Basic credentials only when staging requires them. Verify both queues are drained, then check a normal homepage miss followed by a cache hit, mobile delivery, and the exact served asset hashes. This path passed on staging and production for the pointer correction.

For this correction, the coordinator explicitly permitted reuse of the preceding six-component production backup within four hours of its original timestamp. All 330,433,030 bytes were independently checksum-verified and every gzip/ZIP archive passed integrity checks. Both exact original pointer files and the production preflight were captured within 15 minutes of installation; freshness was checked again before writes. That bounded reuse decision is recorded with the release, not a standing exception for future deployments.

For the pointer correction, replace only `hero-glass.js` and each environment's loader. Reject any prior hashes other than the initial release: JavaScript `1c64ec3d743979e9cf2d55a663a2ede1846e12520a926c014d2631d75e8ebbea`, production loader `56a0f2bcd4fe1b299c00d93c002f900dfe5684e9fd755a1980e6e403babfd4ac`, and staging loader `eb55afa1c22c61b842222bded2762e443c6f4f1cdc854437414aeaf34ea80988`. Preserve private copies of both files. Roll back this correction by restoring those exact copies after verifying no subsequent writer changed the destination, then purge and read back the homepage. The approved CSS remains unchanged.

Rollback changes only these new files: hash-check and move the loader out of `mu-plugins`, purge the homepage, and verify asset tags disappear while the page remains healthy. Retained CSS/JS are inert. Remove them only after checking exact hashes and original absence receipts. Never overwrite shared theme files or restore the database as part of this visual rollback.

This release does not change or certify checkout/payment behavior or close unrelated maintenance and security issues.
