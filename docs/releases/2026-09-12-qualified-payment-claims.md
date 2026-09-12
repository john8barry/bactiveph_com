# Qualified payment availability claims

Issue #2 launches QRPh, Maya, ShopeePay and GrabPay alongside eligible COD.
BPI, UnionBank and GCash remain excluded pending separate qualification.

## Candidate changes

Both maintained child-theme trust-bar templates show only the four qualified
payment marks, optional COD and the separate PayMongo processor mark. Existing
courier marks, links, typography, assets and footer structure are preserved.
The desktop payment grid uses five columns; the mobile three-column grid stays.
The template marker is `2026-09-12-v6`.

The legacy FAQ source now names those same methods and describes COD as
conditional. This source correction is not a production FAQ update: do not run
`update_faq.php`, which replaces a whole page. Read the current database page,
snapshot it and replace only its payment answer through the supported editor.
The live checkout trust text and policy pages require independent content
readback; their current database content is not established by this source diff.
Historical root-level deployment snapshots are not production artifacts.

## Verification and release

The template contract runs seven scenarios for each mirror, checks that banks,
GCash and card claims are absent, and tests four negative layout mutations.
PHP lint and mirror equality must pass. The root release owner deploys only the
reviewed trust-bar template onto fresh verified production preimages after the
shared theme writer releases its window. No shipping, COD logic, gateway,
credentials, sizing or database schema changes are included.

After public payment activation, verify desktop/mobile footer output and current
FAQ, policy and checkout claims. Revert only changed template bytes and content
fields if needed; never restore an old database over new customer orders.

This document records a candidate, not a deployment or public-launch receipt.
