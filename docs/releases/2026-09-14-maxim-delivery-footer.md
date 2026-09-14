# Maxim local-delivery footer update

Status: staging accepted; approved production deployment pending serialized access.
Owner: B Active footer task.
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
Authenticated staging preflight confirmed its separate database and noindex;
production identity and current v6 template matched the reviewed base. Both sites
received fresh native six-component UpdraftPlus backups, verified off-server by
SHA-256 and complete ZIP CRC/gzip reads. Private backups and rollback snapshots
are retained in BactivePH Application Support under footer-releases/2026-09-14-maxim.

Staging deployed only maxim.svg and trust-bar.php; all protected file/payment
hashes are unchanged, with zero new staging log bytes on two readbacks.
Deployed SHA-256:
- maxim.svg: c814e226371c46655278aad47514b6c9d93ff7b0b53b589988a09967b68c3a00
- trust-bar.php: b8a93c633de5f392bb4b9811715ab9d9e141367328da9817d8e9d0ae7d7f00a1

Initial direct staging GET returned 200 and v7, while browser navigation
intermittently timed out or received Cloudflare 522. Later browser evidence
showed old v5 content despite exact deployed v7 file hashes. Initial ordinary
HTTPS readbacks remained stale after both native
`wp litespeed-purge all` (reported success) and origin-loopback HTTP PURGE on
`/`, `/shop/` and `/shipping-returns/` (200 responses). Direct-origin HTTPS PURGE
using the verified DNS address failed certificate verification (curl exit 51);
no TLS bypass was attempted. Subsequent native page-cache queue delivery/draining
was observed and ordinary URLs converged to v7. The individual earlier purge
attempts are not proven causes of convergence. Read-only Cloudflare inspection
confirmed both sites use the same single A record address and staging has an
existing Flexible SSL override, explaining origin transport divergence. No SSL,
DNS or configuration changes were made.

Staging browser acceptance now passes on ordinary URLs: homepage at 1440px and
390px, shop at 390px, and shipping/returns at 1280px all returned 200 and v7.
All logo images loaded, local-delivery badge cells matched, no horizontal
overflow occurred, and the incumbent payment set was preserved. Main deployment
lane inspected desktop/mobile homepage screenshots and accepted the visual result.

Production remains unchanged (Maxim absent, v6 template hash
18fe403de1ea0e2004ece45c90b3d195bc55749a8efb2a04598fb441df71967b).
Final authenticated production readback preserved protected hashes and confirmed
backup completion. Its log grew 324 bytes during backup/verification: two
uninvestigated warnings, zero fatal/parse/uncaught events. Staging had zero new
log bytes. This is not an all-logs-clean claim.

Commit f06ac4da56b7ed49dae9d4f05a0b0c09f03245ca was pushed and independently
read back through existing github-john8barry SSH access. The GitHub API auth gap
was resolved through an existing isolated project GH_CONFIG_DIR; john8barry
identity and repository write permissions were independently verified without
global authentication changes. PR https://github.com/john8barry/bactiveph_com/pull/91
is ready for normal merge following fresh exact-head checks. No required status
checks, branch protection or rulesets were reported. Staging acceptance is passed.
No direct main push or security/approval bypass was attempted.
Next: complete the normal merge, wait for the catalogue backup-only writer to
release production access, then verify qualified current backup/drift checks and
perform the already-approved narrow live deployment. No new production approval
is needed unless scope changes. Production has not been deployed as of this record.
Independent release review reran both mirrors' seven scenarios and four negative
fixtures, PHP lint, mirror equality and git whitespace checks successfully.
Remote main still matches the base above; latest five workflow results succeeded.
Recovered API access verified 19 open dependency alerts (10 high, 8 medium,
1 low) and zero open secret-scanning alerts. Code scanning remained unavailable
(no analysis found). Existing unrelated security work remains tracked in issues
#7 and #9; no project-wide clearance claim.
