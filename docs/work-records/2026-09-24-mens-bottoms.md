# Missing Men → Bottoms navigation

Severity: P2. Status: approved by John for publication; deployment pending. Related: issue #136.

Evidence: On September 24, live browser readback of /collections/men/ lists Essential Workout Shorts in Bottoms and Men. Its category link /collections/men/bottoms/ displays the shorts as its single result. Current origin/main f4a32287 checks the nonexistent anticipated slug bottoms-men instead of the existing bottoms slug. Shared desktop/mobile menu therefore omits Bottoms.

Scope: Correct only the category lookup and destination in the Sage MU plugin, and update the existing contract fixtures. Preserve catalog-visible count gating and all other menu behavior. No product or category changes.

Acceptance: Contract passes for missing, error, empty, hidden and visible products and both desktop/mobile rendered links. After approved deployment, verify Men → Bottoms on ordinary cached pages and the shorts at its destination.

Production impact: One navigation link becomes available; no checkout or database change. Production unchanged at preparation. Latest three Sage CI runs were successful; open PR titles showed no overlapping menu fix.

Release: Obtain John approval, verify live plugin preimage against current source, back up the exact file off-server, lint and atomically replace only that file, invalidate affected page cache, then verify destination, desktop/mobile menus and live hash.

Rollback: Guard against subsequent changes, restore exact backed-up plugin, lint, invalidate affected cache and verify prior menu. No database rollback.

Validation: PHP lint, 32 header contract assertions, six footer contract assertions, all nine JavaScript behavior tests, and diff whitespace checks passed. Independent read-only review found no code findings. Full diff reviewed with no credentials, generated assets, or unrelated changes.
