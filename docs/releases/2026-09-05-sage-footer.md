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

Evidence captures and detailed local receipts remain in the task's private evidence directory. They are preview evidence, not certification of live deployment. The scoped [design guide](../design/footer/DESIGN.md) and [asset record](../design/footer/ASSETS.md) describe the implementation.

## Rollout

The payment coordinator controls the shared-host writer queue. Hero and payment-critical email work precede this release. Do not infer host-write authority from the issue or this document.

For each environment, confirm the exact home/site URLs, active child theme, distinct staging database, indexability, current footer hashes, and protected-file hashes. Take a fresh full Updraft database/files backup and verify every component's off-server copy and integrity before installation. Retain the original footer bytes privately for rollback.

Use the trusted strict-host-key SSH/SFTP channel. Upload reviewed files outside the web root, verify hashes and PHP syntax, and install `footer-sage.php` first. Verify it before atomically switching `footer.php`. Preserve global CSS, trust bar, root footer, fonts, payment functions/JavaScript, and hero loaders/assets. Purge only known affected public URLs through the supported page-cache action; invalidate matching edge URLs only if normal public readback proves stale content.

Verify exact destination hashes and `data-bactive-footer-version="sage-2026-09-05"`, normal anonymous requests, authenticated browser rendering, desktop/mobile layout, original assets, representative page types, core checksums, and fresh error counts. Record staging acceptance before production installation. Record production readback and bounded monitoring before closing issue #14.

## Rollback

First compare current target hashes with this release and stop if another writer has changed them. Restore only the environment's captured original `footer.php` through an atomic rename, then purge the affected URLs and verify the previous footer and healthy responses. The new Sage template may remain inert; remove it only after confirming its exact bytes and original absence. Do not restore the database or overwrite unrelated theme/provider changes.

## Status

Source and local preview are ready for review. Staging and production release remain pending the serialized writer window and their separate backup and live-verification gates. This document does not claim payment, email, security-maintenance, or broader repository/production drift is resolved.
