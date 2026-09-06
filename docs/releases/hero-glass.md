# Homepage glass treatment

John approved the staging result and authorized production release. Deployment status, backup/rollback evidence, and live verification are tracked in [issue #13](https://github.com/john8barry/bactiveph_com/issues/13).

The homepage hero uses a clearer glass panel with a fine reflective rim and a restrained pointer highlight. A press compresses only the decorative rim; the card and text stay still. The production rollout preserves the approved staging CSS and JavaScript bytes, existing copy/photo/layout, shop destination, and other theme files.

## Files and behavior

| File | SHA-256 |
| --- | --- |
| `wordpress/wp-content/themes/blocksy-child/assets/css/hero-glass.css` | `25697e3e1c73cb1c10df10555036144b12eed7cc6dbf2837299f44f5bd5cf0ed` |
| `wordpress/wp-content/themes/blocksy-child/assets/js/hero-glass.js` | `1c64ec3d743979e9cf2d55a663a2ede1846e12520a926c014d2631d75e8ebbea` |
| `wordpress/wp-content/mu-plugins/bactiveph-hero-glass.php` | `56a0f2bcd4fe1b299c00d93c002f900dfe5684e9fd755a1980e6e403babfd4ac` |
| `wordpress/wp-content/mu-plugins/bactiveph-hero-glass-staging.php` | `eb55afa1c22c61b842222bded2762e443c6f4f1cdc854437414aeaf34ea80988` |

Only the first three files are installed on production. Each loader is restricted to its exact environment root, WordPress URLs, child theme, and front page. It loads CSS after `blocksy-child-custom` and JavaScript in the footer with `defer`. If either asset is missing or the theme dependency is unavailable, neither asset is enqueued.

The CSS targets only `.home .hero-glass-card`. A solid readable panel is the default without backdrop-filter support. Reduced transparency and increased contrast use a solid background and dark text; forced colors use system colors. Reduced motion disables spatial feedback, and touch mode does not track the pointer. The script performs no network requests, storage, analytics, or provider operations, and stops animation frames when settled or inactive.

## Validation

- Staging: ordinary cached homepage and browser refresh load the exact CSS/JS bytes once; pointer, press, exit reset, keyboard focus, reduced motion/transparency, forced colors, and 390px responsive layout passed browser checks.
- Independent CSS/JS review and separate staging/production loader reviews passed. Production loader differs from staging only in environment identity/name.
- Local JavaScript syntax and whitespace/secret-pattern checks passed; staging PHP lint passed. Production lint/readback, backup, and rollback verification are release gates.
- Native touch hardware and Safari have not been certified. Staging has older hero imagery/copy than production; both environments require their own visual readback.

## Rollout and rollback

Use the documented trusted SSH/SFTP route and a serialized writer window. Verify the production home/site URLs, active child theme, current protected-file hashes, and original absence of the three target files. Obtain a fresh complete backup with verified off-server components before installation. Upload only the three reviewed files into a private directory, verify hashes and PHP syntax, then atomically install assets first and the loader last. Purge only the production homepage through the supported LiteSpeed command and verify normal public requests, asset bytes, browser behavior, and fresh errors.

Rollback changes only these new files: hash-check and move the loader out of `mu-plugins`, purge the homepage, and verify asset tags disappear while the page remains healthy. Retained CSS/JS are inert. Remove them only after checking exact hashes and original absence receipts. Never overwrite shared theme files or restore the database as part of this visual rollback.

This release does not change or certify checkout/payment behavior or close unrelated maintenance and security issues.
