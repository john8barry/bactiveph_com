# Visual-only product size guides

## Request and scope

- Work record: issue #74; owner: John Barry / B Active storefront.
- Severity: medium presentation defect. The illustrated guide was followed by a second HTML chart, and the standalone fallback stacked both products' charts.
- User correction: each link should show only its matching original visual chart.
- This supersedes the image-plus-table presentation in the illustrated-guide release, not its original image assets or exact product mappings.
- Court Skort and Bubble Dress remain separate, exact-product guides. Other products keep contact guidance; no inferred category chart.

## Change

- Removed both visible HTML tables, duplicate instructions and table-only styling.
- Retained source-faithful measurements as a hidden text alternative referenced by the image link for assistive technology; it does not render a second chart.
- Product fallback links select one strictly allowlisted chart using a query parameter. The general Size Guide page offers a product chooser, not stacked charts.
- Invalid or nonscalar query values return the chooser without reflecting input.
- Original JPEG bytes and enlargement links remain unchanged.
- Production scope is only the size-guide region of functions.php and dedicated size-guide.css. Shared styling, JavaScript, catalog, stock, shipping, payments and database content are outside this change.

## Acceptance and local evidence

- Fifteen focused tests pass: one image and no HTML table per approved product and selected fallback; no chart for other styles; chooser has no chart; invalid query cases; immutable source-image hashes; native dialog open, close and fallback behavior.
- Both PHP mirrors lint and compare equal. Related shipping, SKU privacy/media and punctuation PHP regressions and 17 Python audit tests pass.
- UI detector returned no findings for changed UI files.
- Browser review at 1280x720 and 390x844: a single illustrated chart, no horizontal overflow, hidden alternative not visible. Court Skort and Bubble Dress selected fallback pages and general chooser reviewed.
- Browser accessibility tree exposes the complete Bubble Dress measurement description through the illustration link.
- Independent read-only review found no actionable issues and reran all 15 focused tests, both PHP lints, mirror comparison and diff checks.

## Deployment and rollback contract

Target: https://bactiveph.com (production, not staging).
Use the qualified same-session full Updraft database/files backup verified off-server, plus fresh exact two-file preimages.
Apply only the sizing-region delta to fresh live functions.php; preserve unrelated shipping/payment or formatting differences. Compare preimages immediately before replacement.
Verify all 19 published product routes, both selected fallback pages, the chooser and invalid selections anonymously; confirm unchanged original JPEG hashes and protected asset hashes, then inspect new error-log bytes during bounded monitoring.
Rollback only these two files to their recorded preimages if current bytes still match this release. Do not restore the database or overwrite concurrent work.

No memory files updated.

## Live release

- PR #85 source `afc8cb16a7430c27a1d6b10469e3a8fbeef0eccb`, merge `9a36bd141cfeb4dc873b1a054862bca562e2d254`; all four PR and merged-main workflows passed.
- Payment and shipping writers explicitly released the exclusive two-file sizing window. Installation completed at 05:49:45 UTC on 2026-09-12.
- All six components of the same-session 04:20 UTC Updraft backup were rehashed off-server; their remote files remained present with matching sizes. Fresh two-file preimages were captured at 05:47 UTC after the shipping release. No database, settings, catalog, inventory or order write occurred.
- functions.php SHA-256 advanced from `ec2ddcc4e013793bceeb711424cc6b2084358240c3e08048d7ea192d586070b0` to `3780b19e06ecdfcefec99e930e6a3f9366c77552f13e179735c7e85bd28ec2cc`. Its unrelated shipping content and existing whitespace difference from Git were preserved.
- Dedicated CSS SHA-256 advanced from `321a300c740001e80663fab375194e400582e183bcc9c7a60a65160daec0978a` to `3ad0fc42e2543a1e40035bbf29676a8464c71bd517d1b7bdb65511a2cdbd5aca`. Shared CSS, both JavaScript files and both original JPEGs remained byte-exact.
- Authenticated renderer readback confirms one image and zero HTML tables for each approved product, and no image for other styles. Logged-in browser checks confirm both product dialogs, Bubble Dress desktop/mobile presentation, Escape close with returned focus, the selected fallback and the general chooser.
- Anonymous readback at 05:52:36 UTC passed all 19 published product routes: exactly the Court Skort and Bubble Dress have one matching image and zero HTML tables; the other 17 retain guidance with no chart. Both query-selected fallback URLs return one image and report Cloudflare DYNAMIC; the general chooser has no chart. Both public JPEGs and versioned CSS match the expected SHA-256 bytes.
- Invalid string and array selections returned the chooser. A traversal-shaped query was rejected with HTTP 403 before application rendering; local regression tests cover that application path. The verifier initially treated this expected rejection as a failure; it now records the rejection and the unverified application route explicitly, without relaxing normal product/page checks.
- Logged-out browser interaction and actual screen-reader use were not separately exercised; anonymous HTTP and the browser accessibility tree cover those respective source contracts.
- Final independent readback at 05:53:00 UTC, 195 seconds after installation, matched all seven changed/protected file hashes. Production error_log gained zero bytes; debug.log remained absent. The writer window was explicitly released to payment and shipping, and all sizing SSH sessions were closed.
- Private exact preimages and sanitized verification receipts live under the project-local `visual-only-size-guides-20260912` recovery directory. Remote staging was removed. Rollback remains the guarded two-file restore above; never restore a database backup over live orders.
