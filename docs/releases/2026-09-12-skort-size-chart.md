# Category-specific size guidance

Issue [#74](https://github.com/john8barry/bactiveph_com/issues/74) tracks the incorrect shared garment chart. Severity: medium. Owner: B Active / John Barry.

## Change and source

John supplied the authoritative skort chart on September 11, 2026. The previous S/M/L/XL Bust/Waist/Hips table came from placeholder build-guide content and is removed from the shared renderer.

- Only products assigned to the live `skorts` category receive the skort table.
- All six numeric sizes (4, 6, 8, 10, 12, 14), all 30 measurements, and the measuring/tolerance instructions are transcribed exactly, including inner length `10.0` for size 14.
- No conversion to store letter-size variations is invented. The chart directs shoppers to contact B Active to confirm their matching size.
- Other/unknown categories receive sizing help without a measurement table. Their non-JavaScript link reaches the standalone guide's other-styles section.
- The standalone guide clearly labels skorts and separates help for other garments.
- Product variations, inventory, catalog records, payments, and shared custom CSS/JavaScript are unchanged.

## Acceptance and verification

- Both PHP source mirrors lint and match.
- Eleven size-guide tests pass: dialog behavior, exact table transcription, category resolution, unknown-category fallback, and guarded standalone-page rendering.
- Independent read-only review found no blocking transcription, routing, accessibility, or security findings.
- The Impeccable UI skill informed the contained mobile table, readable measurement instructions, and wider desktop dialog; its final detector returned no findings.
- Local browser verification covers desktop and 390-pixel mobile containment, native dialog dismissal, and focus return.
- Authenticated production preflight confirms 19 published products, exactly four in `skorts`, and an existing Contact page.

## Release control

Production target: `https://bactiveph.com`, active child theme `blocksy-child`. John's existing approval to fix the size guide and get it live covers this narrow correction. A serialized theme-only writer window was released by the payment task. Normal payment callbacks/recovery may continue; no stable-order assumption is made.

Deploy only the size-guide section of the current live `functions.php` and `assets/css/size-guide.css`. Preserve unrelated live bytes, including a pre-existing whitespace difference from Git. Require fresh six-component UpdraftPlus backup with private off-server checksum/archive verification, exact live preimage checks, PHP lint, cache invalidation, and independent public readback before declaring this release live.

Release result and deployed hashes will be recorded on issue #74 after installation.

## Remaining dependency

The tops and other garment charts, and the numeric-to-letter skort size conversion, have not been supplied. Issue #74 stays open for those owner inputs. Do not reintroduce the placeholder chart or infer measurements from product names.

## Rollback

Keep the private pre-release two-file snapshot and full UpdraftPlus backup in the project recovery directory. If these files are unchanged since deployment, restore the exact two preimages atomically and invalidate only the selected site's page cache. If later theme edits exist, apply only the inverse size-guide patch to the latest files; do not overwrite later work. Restoring the previous chart restores known incorrect shared guidance, so rollback is for a functional regression, not an approved sizing source.
