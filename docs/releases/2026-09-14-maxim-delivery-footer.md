# Maxim local-delivery footer update

Status: local candidate; not deployed. Owner: B Active footer task.
Work record: https://github.com/john8barry/bactiveph_com/issues/90.
John approved this scoped production release on 2026-09-14.
Base: 01c81c2e7e5494ebb48beca72d858466e5ad099b.
Branch: codex/maxim-delivery-footer.

## Scope and acceptance

Add Maxim Delivery next to GrabExpress beneath Davao City only.
Preserve nationwide J&T/LBC and current QR Ph, Maya, GrabPay, ShopeePay,
eligible COD and PayMongo branding. No checkout, pricing or gateway changes.
Use the existing carrier badge, proportional artwork, accessible link name
and secure new-tab attributes. Verify both theme mirrors and responsive layout.

## Artwork provenance

Official Philippines site: https://taximaxim.com/ph/en/
Official logo: https://taximaxim.com/images/logo_nr.svg
Retrieved 2026-09-14 from the site's current header CSS. Original vector paths,
colors and viewBox retained. Legacy external DOCTYPE removed defensively.
Stored locally; no third-party runtime dependency.

## Release gate and rollback

Low-risk additive presentation change. Production approval received; requires
qualified fresh off-server backup, current authenticated affected-file snapshots and
serialized deployment. Upload maxim.svg before trust-bar.php; independently
read back hashes and footer version 2026-09-14-v7, plus browser/health checks.
Rollback only trust-bar.php to its exact pre-release snapshot. Leave the unused
new SVG in place. Never restore a database or overwrite payment configuration.

## Evidence

Both theme mirrors pass seven gateway/COD scenarios and four negative layout
fixtures. PHP syntax and git whitespace checks pass; both PHP/SVG pairs match.
Impeccable detector returned no findings. Independent read-only review found no
blocking code or security findings; payment logic is unchanged.
Actual incumbent Sage CSS plus candidate partial rendered in Chromium at
1440, 1280, 768, 390 and 320px: no horizontal overflow or broken logo images.
Desktop and mobile screenshots inspected; both local badges have equal cells
(156x56 at 1440px, 132x48 at smaller widths) and proportional artwork.
Local preview evidence is in /private/tmp/bactive-maxim-8g7wxy/sage-*.png.
Production currently observed publicly at 2026-09-12-v6; authenticated revision,
staging, backup qualification and deployment remain unverified for this change.
Independent release review reran both mirrors' seven scenarios and four negative
fixtures, PHP lint, mirror equality and git whitespace checks successfully.
Remote main still matches the base above; latest five workflow results succeeded.
Security alert APIs were unavailable to the current credential. Existing unrelated
security work remains tracked in issues #7 and #9; no project-wide clearance claim.
