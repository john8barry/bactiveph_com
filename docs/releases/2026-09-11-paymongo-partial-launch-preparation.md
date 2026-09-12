# PayMongo partial launch preparation — September 11, 2026

Issue #2 remains open. This checkpoint qualifies preparation; it does not claim
public payment activation or completion of live acceptance.

## Verified this session

- Production runtime matches the 11-file `aa12eaaa7843b675636bdb5863e9d033e05d7cf7`
  manifest. Fresh authenticated readback at 17:19 UTC confirms live mode,
  manager-only issuance and selection of QRPh, Maya and ShopeePay.
- PayMongo reports six live capabilities: `qrph`, `paymaya`, `shopee_pay`,
  `dob`, `dob_ubp` and `grab_pay`. GCash is absent from this response.
  Capability availability is not successful-payment evidence.
- The previous Maya canary was independently reconciled at 17:17 UTC:
  WooCommerce cancelled, provider checkout expired, intent cancelled, no
  payments and no stock reduction. A new test may be prepared.
- The footer owner released its production window on September 10 at
  19:33:30 UTC. The stale payment coordination reservation is reconciled.
- PR #54 at `f1a73229854db521e2c2c5ab29d0cbe6168a1208` passes PHP 8.1–8.3
  and native WooCommerce CI. Independent package review found no new GrabPay
  blocker. All 11 archive files match source; tests and deployment helpers
  are excluded and secret-pattern checks pass.
- An isolated integration of current main `2f10708228040ee5b962e5e19fec6b50c1965938`
  and the payment candidate merged without conflicts. All 1,593 contract
  checks and 21 PHP lint checks pass. The runtime package bytes are unchanged.

Runtime archive SHA-256:
`f0a61b6d85ae478a135826ed1edf63e4c6913bb11f7f22682a90c2e837c38185`.

## Execution order

1. Finish the integrated source review and GitHub release record. Take a fresh,
   verified off-server backup and exact runtime/settings snapshots. Recheck
   pending sessions and the exclusive production writer immediately before upload.
2. Install only the reviewed plugin package with manager-only issuance retained;
   independently verify every deployed file. Keep current theme/footer files.
3. Select GrabPay through the supported WooCommerce settings flow, after session
   draining succeeds. Verify selected capabilities and the exact live callback.
4. Prepare one small live checkout each for Maya, ShopeePay and GrabPay. Present
   each exact order and total to John before he authorizes and makes payment.
   Retain the prior QRPh evidence and recheck its recorded paid state.
5. For each method, reconcile provider payment, signed callback or authenticated
   recovery, WooCommerce transaction/status, amount/currency, stock once, customer
   confirmation and merchant inbox receipt. Check cancellation, duplicates,
   missed callbacks, COD switching, shipping/coupon/tax totals and populated
   mobile/desktop checkout. Complete the approved provider-side refund procedure.
6. Release only methods with complete live acceptance evidence. Verify anonymous
   checkout and eligible COD, monitor for 30 minutes, and reconcile the next day.

## Provider-dependent work

The latest recorded support reply, September 11 at 00:29 UTC, acknowledges
engineering escalation without a technical fix or ETA. Hosted Checkout bank
simulation/authorization guidance, GCash availability and the held ShopeePay
sandbox session remain unresolved. The case monitor runs every 30 minutes;
repeated acknowledgements do not qualify a method for launch.

BPI/UBP and GCash do not gate qualified partial activation. Keep their evidence
and outstanding questions in issue #2. Do not apply direct Payment Method API
bank-code advice to Hosted Checkout without validating that it applies.

## Recovery

If integrity fails, stop new PayMongo issuance and retain callbacks, credentials
and scheduled recovery. Keep eligible COD available. Reconcile outstanding
payments before reverting code; never overwrite new orders with an old database.
Follow `docs/paymongo-production-runbook.md` for the full acceptance and recovery
requirements. Private receipts remain off repository.
