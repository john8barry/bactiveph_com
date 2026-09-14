# Maxim local-delivery footer update

Status: live accepted; exact-file and independent browser verification passed.
Owner: B Active footer task.
Work record: https://github.com/john8barry/bactiveph_com/issues/90.
John approved this scoped production release on 2026-09-14.
Base: 01c81c2e7e5494ebb48beca72d858466e5ad099b.
Branch: codex/maxim-delivery-footer.
Released source: eee7283a8d9b9878d6c10e9b070e0c09800a4480 (PR #91).

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

Low-risk additive presentation change. Production approval, qualified fresh
off-server backup, authenticated affected-file snapshots and serialized deployment
were completed. maxim.svg was uploaded before trust-bar.php; independent hashes,
footer version 2026-09-14-v7 and browser/health checks verified the release.
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

Production deployed only the same two files; independent authenticated SSH
readback matches the hashes above and confirms protected file/payment settings
unchanged. Production browser checks passed all four ordinary routes/widths:
homepage 1440/390, shop 390, shipping/returns 1280, each HTTP 200 and v7, all
logos loaded, equal local-delivery cells, no overflow and preserved payments.
Main visually accepted desktop/mobile homepage screenshots. An independent
reviewer also verified fresh ordinary homepage and `/product/the-court-dress/`
responses at 200/v7 with Maxim, and accepted the visual result.

The deployment helper uploaded both files, then its guarded purge step exited 1
because production LiteSpeed Cache is inactive (CLI false, class false, version
null, active false). No plugin/configuration change or repeat deployment was
performed. Independent file and browser readbacks establish successful delivery;
no purge was needed for the live result. The helper exit alone is not a success
receipt and was not treated as one.

Final authenticated health checks preserved the deployed and protected hashes.
Eight warnings accumulated since the pre-backup baseline, all classified from
sanitized inspection as `Constant DOING_CRON already defined` at WP-CLI
Runner.php line 1231; zero fatal/parse/uncaught events and no observed frontend
critical errors. This is not an all-logs-clean claim. The earlier 324-byte/two-
warning observation was an intermediate backup-window snapshot. Staging had
zero new log bytes at its recorded verification point.
Rollback preimage remains the original v6 partial, SHA-256
18fe403de1ea0e2004ece45c90b3d195bc55749a8efb2a04598fb441df71967b.

Commit f06ac4da56b7ed49dae9d4f05a0b0c09f03245ca was pushed and independently
read back through existing github-john8barry SSH access. The GitHub API auth gap
was resolved through an existing isolated project GH_CONFIG_DIR; john8barry
identity and repository write permissions were independently verified without
global authentication changes. PR https://github.com/john8barry/bactiveph_com/pull/91
merged normally at 2026-09-14T10:37:09Z as the released source above after fresh
exact-head checks. No required status checks, branch protection or rulesets were
reported. Staging and production acceptance passed.
No direct main push or security/approval bypass was attempted.
Warning classification is complete; production writer access was released back
to the catalogue task and all SSH sessions ended. Close issue #90 after this final
evidence record merges. No further deployment is required.
Independent release review reran both mirrors' seven scenarios and four negative
fixtures, PHP lint, mirror equality and git whitespace checks successfully.
Remote main was independently read back at the released feature merge; both live
artifacts match that source. Latest five workflows inspected before release succeeded.
Recovered API access verified 19 open dependency alerts (10 high, 8 medium,
1 low) and zero open secret-scanning alerts. Code scanning remained unavailable
(no analysis found). Existing unrelated security work remains tracked in issues
#7 and #9; no project-wide clearance claim.
