# In store cashier implementation

Owner: John Barry. Implementation task: B Active tablet cashier and staff guide.
Status: implementation, local synthetic qualification and staff artifacts complete; provider qualification and operational rollout pending. Not deployed or approved for live use.

## Scope and baseline

User approved the custom tablet cashier, restricted associate accounts, cash and the existing four PayMongo methods, email first, registered handwritten invoices, and screenshot staff guide. No SMS or printer integration. No live payment test is authorized yet. Accountant/RDO confirmation of the invoice book, tax treatment and cashier registration is a prelaunch requirement.

Canonical dirty checkout preserved at `/Users/johnbarry/Documents/Antigravity/bactiveph_com`. Isolated implementation is `/Users/johnbarry/.codex/worktrees/bactiveph-cashier`, branch `codex/in-store-cashier`, based on remote main `5cb35011afa1dd6f0baa03181360d89f08ece833`.

Authenticated production readback on 2026-09-16: target `https://bactiveph.com`, WooCommerce11.1.0; enabled gateways `bactive_paymongo` and `cod`; issuance methods `qrph`, `paymaya`, `shopee_pay`, `grab_pay`; cashier plugin absent. Production gateway source SHA256 `016025fc4b49bd3e98c07f6f9ced99c945f40bd7d5f0d58066ea36c2ad97e822` matches this checkout. This is a payment-file match, not proof that the entire site matches Git.

Open GitHub issues include #2 payment qualification, #9 legacy credential containment and #7 dependency triage. Existing open PRs are unrelated to cashier. Recent inspected Actions runs were successful. High/medium dependency alerts remain; this task does not clear them. Reconcile these release risks again before any deployment.

## Implementation

Separate `bactive-cashier` plugin; default new-sale gate disabled. Dedicated associate capability, individual account identity, nonce and ownership checks. Woo order CRUD/HPOS; durable UUID claim and one active sale per associate; server prices and store tax location; physical, tracked, fully specified variants only. Dedicated cash method is unavailable in public checkout.

PayMongo session issuance and signed settlement reuse the existing protected gateway unchanged. Cashier explicitly reacquires its shared lock after gateway return. No digital-to-cash switch. Paid display requires consistent provider evidence and completed stock effects. Handwritten invoice serial and handover evidence are required for completion.

Stock uses Woo's atomic reservation plus a sentinel expiry and query compatibility adapter so interrupted settlement cannot release inventory while still payable. Reservation release verifies stock effects or verified cancellation. Managers can verify unpaid provider closure and release a register claim. Cash effects interrupted after recording are never replayed automatically. Pause new sales for rollback; keep both plugins and recovery workers active.

Optional plain-text email confirmation uses existing WordPress transport; accepted-for-send is not inbox delivery. In-store standard Woo customer emails are suppressed to avoid duplicate or misleading invoice emails.

## Evidence and limits

Disposable Docker environment uses real WordPress/Woo/HPOS and synthetic products/accounts. PayMongo API transport and email are intercepted; no real money, provider sessions, or customer messages are created. Screenshots show the actual implementation in this training environment. A successful synthetic method test does not prove live wallet handoff, live callback delivery, or external email delivery.

See `docs/cashier/RUNBOOK.md` for installation, restricted roles, release gates, recovery and rollback. Test results and artifact QA will be recorded at closeout. Production remains unchanged.

## Qualification results

- Real WooCommerce 11.1.0 / HPOS integration: 66 assertions passed in the disposable WordPress 7.0 fixture. Production WordPress 7.1 compatibility still requires release verification.
- Anonymous HTTP callback tests: 22 checks passed across QRPh, Maya, ShopeePay and GrabPay with synthetic signed, forged and duplicate callbacks, verified manager cancellation and unverified-expiry hold retention.
- Public order-pay regression: six additional HTTP checks passed. Valid nonce/key POSTs for fresh and digital-pending cashier orders returned the early guard's 403; method, attempt hash/count, status, stock and active claim stayed unchanged. The default HTTP runner now includes all 28 checks; the 22 callback checks and six new boundary checks were verified in separate runs.
- Concurrency: all three scenarios passed. Same UUID produced one order; two associates competing for the last unit produced one reservation; two associates plus ordinary Woo checkout produced one winner (online 200, associates 409/409), physical stock 1 and held stock 1.
- Frontend regression: seven cases passed, including immutable retry payload, pending handover/cash blocking, invoice requirement and opaque UUID-only storage.
- Existing PayMongo regression suite: 1,679 checks passed. PHP lint and whitespace checks passed. Scoped secret review found only clearly synthetic fixture keys; no production credentials in the new implementation or release package.
- Security review fixes: gateway lock reacquisition, crash-safe reservation visibility, guarded manager recovery, physical-goods completion status, anonymous callback compatibility and early public order-pay POST denial. Final reviewer found no remaining material findings after fixes.
- Browser walkthrough: actual cash/change, paid/invoice/handover, digital QR/pending, callback-confirmed payment and completion; portrait and landscape tablet layouts. No real wallet app or physical tablet hardware was tested.
- Added CI workflow using pinned actions/images and fresh fixture enablement. This workflow has not been pushed or executed by GitHub; local tests are the evidence.

## Deliverables

`docs/cashier/cashier-staff-guide.pdf` and `.docx`: six pages with actual sanitized training screenshots. `counter-checklist.pdf`: one page. `manager-checklist.md`: opening, reconciliation and pause procedure. Every rendered guide/checklist page visually inspected; see ARTIFACT-QA.md.

`artifacts/cashier/bactive-cashier-1.0.0.zip`: 15 production files, excludes all tests and fixture code. `manifest.json` records archive and per-file SHA256 values. Source stays on the isolated branch; no commit, push, merge or deployment has occurred.

## Outstanding launch controls

1. John/accountant/RDO: confirm invoice book, VAT classification and cashier registration requirements. The setup acknowledgment must represent a real review.
2. Store manager: supply individual associate identities and verified counts for untracked/invalid-stock variants. No live users or stock were changed.
3. Project owner: reconcile issues #2/#7/#9, current source and production; authorize the exact release and retain destination backup/rollback evidence.
4. Payment qualification: use actual PayMongo sandbox where supported, then obtain explicit live test authorization for method and amount. Record any unavailable sandbox rail. Verify real phone handoff, callbacks, stock and settlement for the four intended methods.
5. Email: authorized recipient and observed inbox delivery through the existing transactional service. Synthetic mail acceptance and resend tests do not establish deliverability.
6. Launch only after these controls pass. Default new-sale gate remains disabled on installation. Pause new sales for rollback and preserve outstanding order/payment/stock recovery.

No production mutation occurred in this task. The canonical checkout's unrelated local work was preserved. This record is the local work item until authorized GitHub/release reconciliation.

Closeout remote readback: origin/main remains `5cb35011afa1dd6f0baa03181360d89f08ece833`, matching the implementation base. New files remain local and uncommitted in the isolated worktree. Temporary browser preview closed and synthetic runtime removed after verification.

## Live release continuation — user authorization

John authorized getting this live, payment testing and inbox-delivery testing; stated stock counts are verified and explicitly removed the accountant/RDO confirmation requirement. Removed that setup checkbox and its enablement dependency. Existing handwritten invoice procedure remains. Current release instructions in docs/cashier/RUNBOOK.md supersede the earlier launch-control list above.

Authenticated production preflight: WordPress 7.1, WooCommerce 11.1.0, PHP 8.2.33, existing four PayMongo rails live, cashier absent, gateway source hash unchanged. Email delivery test through production SMTP2GO reached the authorized connected Gmail inbox; SPF, DKIM and DMARC passed. This confirms the mail transport; the actual paid-order confirmation still awaits the real payment test.
