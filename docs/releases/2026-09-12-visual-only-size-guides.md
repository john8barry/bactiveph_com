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
- Independent read-only review found no actionable issues and reran all 15 focused tests, both PHP lints, mirror comparison and diff checks. Live deployment and monitoring remain pending the serialized production writer queue.

## Deployment and rollback contract

Target: https://bactiveph.com (production, not staging).
Use the qualified same-session full Updraft database/files backup verified off-server, plus fresh exact two-file preimages.
Apply only the sizing-region delta to fresh live functions.php; preserve unrelated shipping/payment or formatting differences. Compare preimages immediately before replacement.
Verify all 19 published product routes, both selected fallback pages, the chooser and invalid selections anonymously; confirm unchanged original JPEG hashes and protected asset hashes, then inspect new error-log bytes during bounded monitoring.
Rollback only these two files to their recorded preimages if current bytes still match this release. Do not restore the database or overwrite concurrent work.

No memory files updated.
