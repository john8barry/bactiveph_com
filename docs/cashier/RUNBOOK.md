# B Active cashier implementation and release runbook

## Current scope and evidence boundary

The plugin adds an authenticated tablet cashier at `/?bactive_cashier=1`, backed by WooCommerce orders and the existing PayMongo Hosted Checkout integration. It supports PHP, one Davao store, cash and the gateway's configured digital rails. Email confirmations are optional. Staff issue registered handwritten invoices. No SMS, printer, split payments, offline sales, price overrides or cashier refunds are provided.

Implementation and synthetic test evidence do not establish a live launch. Record the exact tested/deployed revision, environment and outcome in the project record. Do not describe QRPh, Maya, ShopeePay or GrabPay as tested merely because they appear in a synthetic screenshot or gateway configuration.

## Install and configure

1. Reconcile current remote source, deployed target and the approved release record. Preserve unrelated work. Back up the destination files and relevant database schema before a production change.
2. Install `wordpress/wp-content/plugins/bactive-cashier` using the approved narrow deployment process. Keep the existing PayMongo plugin and its workers running.
3. Activate the cashier plugin. Activation creates the restricted **B Active Sales Associate** role and the sale-claim table. **New sales default to disabled.** The owner has authorized deployment and a payment test.
4. Confirm PHP currency, global stock management, compatible WooCommerce/HPOS schema, and healthy existing PayMongo checkout/callback/recovery behavior. Confirm each sellable variation has valid tracked stock, an exact size/color and no backorders. The owner has confirmed that stock counts are verified. Do not guess a count for any variation still missing a usable tracked quantity.
5. Create or approve an individual staff account per associate and assign **B Active Sales Associate**. Never share administrator credentials. The role does not grant stock, price, refund or payment-settings access.
6. Have the registered handwritten invoice book ready. Staff issue an invoice for every sale and record its number in the order. The optional email is a payment confirmation, not a tax invoice.
7. Finish the payment, email and staff checks below, then enable **Enable new in-store sales** in **B Active cashier → Setup & daily reconciliation**. The owner has authorized launch, payment testing and inbox-delivery testing; no accountant/RDO confirmation is required by this rollout procedure.

## Verification before release

Record individual outcomes and evidence; use **not run** for missing checks.

- Restricted accounts cannot reach stock, settings or refunds, or another associate's sale. Unauthenticated requests and missing/invalid nonces fail.
- Correct product/variation, available quantity, taxes and final total. No delivery charge or COD fee on an in-store sale.
- Cash received cannot be below total. Change is correct. Double taps/retries do not create another order or collect again.
- Pending and unresolved payments block goods handover and cash switching. Only authoritative paid and verified stock state enables handover.
- Two associates and online checkout compete safely for the final unit. Unpaid stock remains reserved while a payment could still settle. Verified cancellation releases the appropriate hold once.
- Same browser reload, new browser/server active-sale recovery, interrupted order creation, request failure and callback delay.
- All four configured digital methods: success, failure, cancellation/expiry and delayed/duplicate callbacks, within available sandbox capabilities. Explicitly record unavailable sandbox rails.
- Invoice is required, recorded and preserved. Completion does not reduce stock twice. No cashier refund controls appear.
- Optional email, accepted/failed status and resend; verify receipt at the authorized test inbox separately.
- Existing online checkout, PayMongo order protection, signed callbacks and recovery workers continue working.
- Tablet portrait/landscape, readable QR and phone-camera path, keyboard access, sign-out and account isolation.

**Payment testing, in plain English:** use PayMongo’s test mode to check that a customer can open the payment page, pay, and have the store order update correctly. Also check what happens when payment fails or arrives late. Then make the authorized real payment and match it to the order, stock change and confirmation email. Confirm that the email actually arrived in the test inbox. Record which methods were tested and any method PayMongo cannot simulate; a simulated payment is not proof that a real payment worked.

## Daily reconciliation

1. Open **Setup & daily reconciliation**. It lists the latest 100 in-store orders with associate, status, total, payment method and invoice. It is not a complete cash-drawer ledger or automatic end-of-day totals report. Use WooCommerce orders for older records and the correct shift/date range.
2. Resolve all pending or review sales with provider and WooCommerce evidence. Do not infer collection from a generic WooCommerce status alone.
3. Match each completed sale to its handwritten invoice. Investigate missing or duplicated invoice references.
4. Compare confirmed cash sales and recorded received/change amounts with cash counted. Reconcile the opening float, payouts and other cash movements separately.
5. Compare digital orders to PayMongo transactions. Order amounts are gross; provider fees and payouts are separate reconciliation entries.
6. Verify stock exceptions and record the cause and manager action. Do not change stock to hide an unresolved payment or duplicate order.
7. Retain sanitized shift evidence and unresolved order references in the project/store record. Do not place customer email addresses or payment URLs in public issues.

## Pending and recovery handling

Associates refresh or resume the existing sale. The browser retains only an opaque sale UUID; the server also locates the associate's active sale. A same-screen retry of an uncertain creation request sends the identical original payload and UUID. A reload without a recoverable basket stops for manager review rather than guessing a replacement.

If an unpaid sale has never begun digital payment and **Cancel this unpaid sale** is available, staff may cancel through that control. Once digital issuance has begun, manager resolution must use the existing gateway's verified cancellation/expiry and recovery safeguards. Do not manually switch the order to cash, delete its claim, release its reserved stock or create a replacement order while payment remains possible. No new cashier-side refund or forced-release procedure is provided.

For an unresolved partial cash/payment/stock operation, pause and inspect the exact order, claim and stock evidence. Never repeat payment or stock actions just to clear the message. Escalate a code-level recovery to the project owner with the order reference and sanitized evidence.

## Pause and rollback

Uncheck **Enable new in-store sales** in cashier setup and save. Independently confirm that new sale creation is blocked and existing sales remain accessible.

Keep the cashier plugin, PayMongo plugin and recovery workers active while outstanding sales exist. Disabling new sales is the first rollback action; deactivating/deleting the plugin or dropping its table can remove safeguards or recovery access. Retain orders, claims, invoices and stock records. A file rollback requires the exact backed-up revision, schema compatibility review and independent readback; never restore the entire database over newer sales.

## Staff materials and screenshots

Staff source documents are `STAFF-GUIDE.md` and `COUNTER-CHECKLIST.md`. The PDF and editable Word guide include real captures of the implemented training screen, sanitized synthetic examples and visible training labels. Every rendered page was visually inspected; see ARTIFACT-QA.md. A screenshot is workflow documentation, not payment-provider test evidence.

Official workflow references: [WooCommerce order management](https://woocommerce.com/document/managing-orders/view-edit-or-add-an-order/), [WooCommerce order statuses](https://woocommerce.com/document/managing-orders/order-statuses/), [PayMongo Hosted Checkout](https://docs.paymongo.com/docs/payment-channels-hosted-checkout), [PayMongo go-live checklist](https://docs.paymongo.com/docs/payment-channels-go-live-checklist).
