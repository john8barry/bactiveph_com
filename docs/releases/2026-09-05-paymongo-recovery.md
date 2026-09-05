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
Both active design tasks confirmed they have no server writer scope.

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

- Reconciled source: PHP 8.1, 8.2, and 8.3 each pass 1,064 contract checks.
- All 13 plugin/test PHP files pass PHP 8.2 lint.
- Runtime package contains exactly 11 files, excluding tests and credentials;
  current secret-value and credential-pattern scans pass.
- Fresh, separately verified production and staging backups are required before
  plugin installation. No staging database will be imported into production.
- Sandbox transaction matrix, live credentials/capabilities, signed delivery,
  live payments, email receipt, refund verification, public issuance, and
  production monitoring remain pending until recorded below.

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
