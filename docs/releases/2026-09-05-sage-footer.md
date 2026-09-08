# Sage welcome footer

John approved the light-green footer mockup and authorized applying it. [Issue #14](https://github.com/john8barry/bactiveph_com/issues/14) tracks the source, release window, backup receipts, destination verification, and final live acceptance.

## Change and scope

The site-wide footer gains a sage signup band, warm ivory brand/navigation columns, a balanced delivery/payment strip, and readable legal text. Existing navigation, public contact details, logo, social profiles, delivery distinctions, payment artwork, registered address, and legal links remain. The original trust template and conditional COD behavior are unchanged.

The footer's CSS is scoped inside the new template so the release does not overwrite the divergent live global stylesheet, payment functions, JavaScript, root footer, or hero assets. Both repository mirrors contain the same two footer templates. Production installs only the child theme's two `template-parts` files.

| File | SHA-256 |
| --- | --- |
| `template-parts/footer-sage.php` | `6e03b8048bf197cbae05e1e52b92c3e92e448048106a2fd463fcdc533f35a7e4` |
| `template-parts/footer.php` | `7b6855c920ebaed93ce0d6aba7d55dd96d66a8cdc56899324565c4b210ac0129` |

The user confirmed MailPoet is being replaced by Brevo. This release preserves the current form behavior and 5% first-order offer; it does not establish a mailing-list connection. The separate Brevo task will integrate `[bactive_newsletter_form source="footer"]` at the existing signup form while retaining the approved appearance and validating its own outcomes.

## Validation completed before release

- PHP syntax passed and the candidate rendered through the live WordPress context in a read-only invocation without warnings.
- Browser previews at 1672px, 1280px, and 390px had no horizontal overflow and loaded all 13 original image assets. Desktop and overlapping mobile captures cover the full footer.
- Heading/body fonts, email type validation, keyboard focus, readable contrast, and 44px mobile navigation targets passed. No signup submission or provider email was sent.
- Independent code review found all 21 incumbent destinations and complete source content preserved; includes, escaping, CSS scope, and conditional COD were checked.
- Independent visual review accepted the approved composition and responsive adaptations. Its sole persistence finding was missing product documentation, supplied in this change. The exact final verdict is recorded in issue #14.
- The one-pass Impeccable detector returned three generic font warnings for the approved existing Fraunces/Inter pairing; those fonts are intentionally retained.

Preview captures, actual staging/production captures, and detailed receipts remain separately identified in the task's private evidence directory. The scoped [design guide](../design/footer/DESIGN.md) and [asset record](../design/footer/ASSETS.md) describe the implementation.

## Rollout

The payment coordinator controls the shared-host writer queue. Hero and payment-critical email work precede this release. Do not infer host-write authority from the issue or this document.

For each environment, confirm the exact home/site URLs, active child theme, distinct staging database, indexability, current footer hashes, and protected-file hashes. Take a fresh full Updraft database/files backup and verify every component's off-server copy and integrity before installation. Retain the original footer bytes privately for rollback.

Use the trusted strict-host-key SSH/SFTP channel. Upload reviewed files outside the web root, verify hashes and PHP syntax, and install `footer-sage.php` first. Verify it before atomically switching `footer.php`. Preserve global CSS, trust bar, root footer, fonts, payment functions/JavaScript, and hero loaders/assets. Invalidate the selected WordPress site's page cache through the installed `LiteSpeed\Purge::purge_all_lscache()` method: the footer appears on every page, including paginated catalog and archive routes. This page-cache-only method avoids the broader `litespeed_purge_all` action. Deliver its CLI purge queue through an uncached HTTP request before normal public readback, and independently recheck the existing checkout/callback cache exclusions. Invalidate matching edge URLs only if normal public readback proves stale content.

The reviewed backup path uses Updraft's one-shot full backup with automatic resumptions and backup-report email suppressed for that invocation. It verifies the exact backup time, nonce, label, six-component archive inventory, off-server hashes, and archive integrity. Interrupted outcomes require reconciliation before any retry. The helper rejects optimized Python, expired or wrong-scope writer windows, protected-file drift, and cross-origin verification; production also requires exact staging verification and visual acceptance.

Verify exact destination hashes and `data-bactive-footer-version="sage-2026-09-05"`, normal anonymous requests, authenticated browser rendering, desktop/mobile layout, original assets, representative page types, core checksums, and fresh error counts. Record staging acceptance before production installation. Record production readback and bounded monitoring before closing issue #14.

## Rollback

First compare current target hashes with this release and stop if another writer has changed them. Restore only the environment's captured original `footer.php` through an atomic rename, then invalidate the selected site's page cache and verify the previous footer and healthy responses. A recorded rollback that already restored the original wrapper is verified and finalized without repeating the rename. The new Sage template may remain inert; remove it only after confirming its exact bytes and original absence. Do not restore the database or overwrite unrelated theme/provider changes.

## Status

The reviewed source and design documentation merged in [PR #17](https://github.com/john8barry/bactiveph_com/pull/17), main revision `cf6a7fb923c7329ab03514e9f8aaa2bdd7a1d6ce`; release documentation followed in [PR #19](https://github.com/john8barry/bactiveph_com/pull/19).

Staging was deployed and visually accepted on September 6, 2026 at 04:58 UTC. Both installed hashes match the table above. Home, shop, BIR registration, and a product page each returned HTTP 200 with one Sage footer and no new PHP warnings, fatal errors, or error-log bytes. Protected theme/MU files and the coordinator's commerce baseline matched after deployment. The user-requested staging Wordfence deactivation was preserved. The staging writer lane was returned at 05:21 UTC.

Actual 1280px desktop and 390px phone captures passed direct and independent visual review. All 13 original image assets loaded, no horizontal overflow was found, and mobile navigation targets measured at least 44px. Two overlapping phone captures cover the complete footer. The staging page-cache queue was consumed. Repeated ordinary cart, configured checkout, and test-callback GETs retained private/no-store exclusions with no cache HIT or challenge; the callback rejected GET with the expected HTTP 405. Empty-cart redirects establish cache and route behavior only.

The full staging backup (six components, 122,512,020 bytes) is verified off-server. After the coordinator's separate Wordfence change, a fresh state snapshot was captured and the existing backup retained under its explicit four-hour freshness authorization.

Production installed the exact two approved templates at 05:55 UTC on September 6, 2026, after its own full six-component backup was verified off-server and independently cleared. That backup totals 330,433,030 bytes; every component passed size, SHA-256, and full ZIP/gzip integrity checks. Original wrapper bytes are retained for the narrow rollback above.

Normal anonymous requests to the home, shop, BIR registration, and Court Dress product pages returned HTTP 200 with exactly one Sage footer. Authenticated production browser captures at 1280px desktop and 390px phone passed direct and independent visual inspection. All 13 original images loaded, neither viewport overflowed horizontally, phone navigation targets measured at least 44px, and the Join button retained a visible 2px keyboard-focus outline. Two phone captures overlap by 417.5px and cover the entire footer.

The production page-cache queue was consumed. Repeated ordinary cart and empty-checkout GETs retained no-store exclusions, with no cache HIT or challenge; the checkout redirected to the cart as expected for an empty session. The separate payment callback is not installed on production and was not probed. Post-deployment protected-file and commerce readback matched the coordinator's baseline, including production Wordfence remaining active.

Final production verification at 06:02 UTC, approximately 400 seconds after installation, again confirmed the exact file hashes, four healthy page responses, one footer per page, preserved files, and zero new PHP error-log bytes, warnings, or fatal errors. Independent visual review returned `ship`. All footer SSH/SFTP connections and remote jobs are terminal, and the production writer lane has been explicitly returned to the payment coordinator.

These receipts do not establish mailing-list integration, populated checkout or provider-payment acceptance, security-maintenance completion, or broader repository/production synchronization. Those remain with their owning tasks.
