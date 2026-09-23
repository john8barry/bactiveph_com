# Men menu order — September 23, 2026

Status: approved for commit and production deployment; release pending.
Severity: low, requested navigation adjustment.

Men currently follows Leggings in the shared Shop collection list. Move the existing nested Men entry directly after Tops & Tanks and before Shop All on desktop and mobile. Destinations, nested items, and Bottoms visibility rules remain unchanged.

Acceptance: both rendered menus end with Tops & Tanks → Men → Shop All; existing header contracts pass. The source change is one array-entry move in `wordpress/wp-content/mu-plugins/bactiveph-sage-header.php`; existing order assertions are updated.

Production impact: none until approved deployment. Release only the MU plugin after fresh target/hash verification and an exact-file backup, then invalidate affected header caches and verify both live menus. Roll back by restoring the verified preimage only if the deployed hash still matches; otherwise reverse this entry move against the current file. No database migration is needed.

Validation: PHP syntax, all 31 header guard/markup assertions (including exact desktop/mobile order), six footer contract assertions, and `git diff --check` passed. Reviewed the complete diff; no credentials, generated assets, or unrelated source changes are included. Base: `d165b3c2`; branch: `codex/men-menu-order`.

Independent read-only review found no actionable issues. All nine existing JavaScript behavior tests also passed. John approved commit and deployment in the project task.
