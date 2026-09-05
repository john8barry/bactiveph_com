# PayMongo production recovery

Work record: [#2](https://github.com/john8barry/bactiveph_com/issues/2),
[PR #4](https://github.com/john8barry/bactiveph_com/pull/4).
Owner: Fix PayMongo production payments, task
`01a070a0-166f-7ea1-8558-94b88f35a074`.

John explicitly authorized implementation of the complete recovery plan.
The intended outcome is production checkout accepting QRPh, Maya, ShopeePay,
BPI Direct Debit, and UBP Direct Debit, with existing COD policy retained.
John performs the exact-total live payment tests. This document is a progress
record, not a claim that payment activation or live acceptance is complete.

## Verified starting failure

Authenticated server inspection found no PayMongo plugin on production,
no new-gateway settings or protected runtime constants, and disabled legacy
gateway settings. Staging retained the old Cynder gateway only. The test API
returned all five approved capability identifiers and one disabled legacy
staging webhook. Production uses classic checkout and PHP currency.

Source tests had passed, but the new gateway had never been installed or
configured. The canonical workspace was dirty and behind remote; its contents
are preserved. The isolated payment checkout incorporates remote main
`f5f6c043a96b35df331a7c004f59fd0da29bc625`, including the accepted footer release.
Both active design tasks initially confirmed they had no server writer scope.
The subsequently authorized homepage staging change receives a separate,
serialized writer window before the payment repair is installed.

## Completed containment

The four exact helpers covered by [#9](https://github.com/john8barry/bactiveph_com/issues/9)
were moved through trusted SFTP to an owner-only directory outside the public
webroot. No helper was executed. Original hashes were checked immediately
before each move; private server and off-server copies remain recoverable.

Independent verification at 2026-09-05 09:21:04 UTC confirmed all source paths
absent, all four hashes/sizes correct, private directory mode 0700 and file
modes 0600. All four former public routes returned 404; home, shop, cart, and
login returned 200. The production root error log was unchanged from before
containment. This clears that specific live-key installation gate; it does not
close the wider historical security investigation.

## Validation and release status

- Initial reconciled source: PHP 8.1, 8.2, and 8.3 each passed 1,064 contract
  checks, with all 13 plugin/test PHP files passing PHP 8.2 lint. Repair
  validation below supersedes these counts.
- Runtime package contains exactly 11 files, excluding tests and credentials;
  current secret-value and credential-pattern scans pass.
- Fresh production and staging backups are separately verified off-server.
  No staging database will be imported into production.
- Sandbox transaction matrix, live credentials/capabilities, signed delivery,
  live payments, email receipt, refund verification, public issuance, and
  production monitoring remain pending until recorded below.

## Staging integration finding

The initial 11-file artifact (SHA-256
`5fa083451fa096091e0b8d4853d763a6c18d44e9416a34f2d5242e7538179ca4`)
was installed on staging after a fresh six-component, 122,400,017-byte backup
passed off-server checksums and archive integrity. Runtime hashes, manager-only
defaults, recovery scheduling, and preserved configuration/theme files were
independently read back.

The first normal WooCommerce settings save exposed a High-severity launch
blocker before settings storage or provider provisioning: recovery discovery
generated 17 HPOS metadata joins. Authenticated database inspection found that
query still running after 331 seconds; a scheduled scan repeated it. The exact
stalled settings process and two identified staging query connections were
stopped. No new webhook or Checkout Session was issued; production payment
settings were not changed.

The repair preserves all 17 discovery keys in one key-IN/EXISTS clause. It also
translates the scan through WooCommerce's supported CPT query hook because that
datastore ignores the original top-level meta_query. Database errors now fail
the recovery scan instead of being interpreted as an empty queue. Existing
incident discovery, pagination, payment locks, signed validation, and controlled
drains remain intact. Independent review approved the query repair; a disposable
real WordPress/WooCommerce regression checks both datastores before promotion.

The initial artifact is superseded and must not be deployed to production.
Local regression evidence for the repair:

- PHP 8.1, 8.2, and 8.3 each pass 1,075 contract checks. All 14 PHP files pass
  lint; the payment workflow passes actionlint.
- Disposable real WordPress/WooCommerce/MariaDB: HPOS passes 62 checks, first
  settings save 0.077 seconds, maximum one metadata join; CPT passes 35 checks,
  first save 0.069 seconds. Both discover the exact 70 payment candidates over
  two pages, preserve every marker, and reject a real database query failure.
  The runner names and removes all disposable containers, sanitizes timeouts,
  and reports overall success only after independent resource cleanup.
- Both environment backups are now verified off-server: staging 122,400,017
  bytes and production 329,690,615 bytes, six components each. Every checksum
  and gzip/ZIP integrity check passed. Backups contain private data and are
  excluded from the repository.

The next control point is a successful bounded first settings save and provider
callback readback using a newly checksummed repair artifact, followed by the
five-method sandbox matrix.

## Recovery controls

New issuance remains restricted to store managers during verification. Keep
callbacks and scheduled reconciliation active while any payment is unresolved.
Rollback stops new PayMongo issuance and preserves eligible COD, then reconciles
outstanding sessions before removing runtime or credentials. Never restore an
older database over new orders or restore the quarantined unsafe helpers as
part of an ordinary payment rollback.

Use the [payment runbook](../paymongo-production-runbook.md) for the transaction
matrix, protected configuration, manual provider-side refund procedure, and
exact acceptance gates. Each live method needs provider/order/stock/email
evidence; a browser return, source test, merge, or successful upload is
insufficient.
