# B Active in store checkout guide

**Draft for training and screenshot assembly.** This source describes the implemented cashier page. It is not evidence of a production launch or successful live payments. Use the final approved guide after the manager confirms that the register is ready.

Use this guide when a customer is in the Davao store and a Sales Associate takes payment. Every sale must have one WooCommerce order, confirmed payment, the correct inventory update and a registered handwritten invoice.

## Before your first sale

1. Sign in with **your own B Active Sales Associate account**.
2. Open **B Active cashier → Open tablet checkout**. The direct cashier address is `https://bactiveph.com/?bactive_cashier=1` after deployment.
3. Check your name at the top. Keep the tablet connected to the internet.
4. Have the registered invoice book and cash/change ready.
5. If you see **New sales are paused**, ask your manager. A blue **Training environment** banner means the page is for training, not real customer payments.

If an existing sale appears, finish or resolve it before starting another. Signing out does not cancel it; sign back in with the same account to resume.

[SCREENSHOT A — Actual training cashier page with synthetic items; annotate staff name, search, size/color and basket.]

## Choose the correct items

1. Search the product name or SKU.
2. Check the **size, color and available quantity** against the item in your hand.
3. Tap **Add item**. Use **+** or **−** in the basket to adjust quantity. Reducing quantity to zero removes the item.
4. Offer an email payment confirmation. Enter the customer's email only if they want one. Check the spelling with them.
5. Tap **Review sale & payment**.
6. Read the final total to the customer. This is the amount to collect. Ask the manager about any incorrect price or tax before collecting money.

**Missing item or wrong stock:** Stop and ask a manager. Do not sell a similar variation, change stock yourself or create a replacement product.

[SCREENSHOT B — Actual training Ready for payment screen with a synthetic order. Mark final total and payment choices.]

## Take cash

1. Count the cash the customer gives you.
2. Enter that amount under **Amount received (PHP)**. Check the displayed change.
3. Tap **Confirm cash received** once.
4. Wait for **Payment received**. If the screen reports an error, refresh the same sale and ask a manager if it remains unresolved. Do not enter a second sale.
5. Return the correct change and finish the invoice and handover steps below.

[SCREENSHOT C — Actual training cash entry screen. Annotate amount received, change and confirmation button.]

## Take a digital payment

1. Tap **Pay digitally** once.
2. Ask the customer to scan the displayed code with their **phone camera**. It opens this order's secure payment page.
3. The customer chooses an available method there: **QRPh, Maya, ShopeePay or GrabPay**, and follows that payment service's instructions.
4. Keep the cashier screen open. It checks payment status automatically. You may tap **Refresh payment status**.
5. Keep the goods until this screen says **Payment received**.

**The first code opens a website.** It is not the QRPh payment code to scan directly in a bank or wallet app. If the customer chooses QRPh on the payment page, follow that page's instructions for completing payment.

**Do not accept a screenshot as proof of payment.** Do not collect cash or start another digital attempt for a pending sale. If the customer says they paid but this screen is still waiting, ask a manager to check the order and provider record.

[SCREENSHOT D — Actual training Waiting for payment screen. Use a clearly synthetic, nonpayable training link; annotate phone-camera instruction, pending warning and refresh button.]

## Issue the invoice and hand over the goods

1. Confirm that the cashier screen says **Payment received**.
2. Write a registered handwritten invoice for **every sale**, using the invoice book and details your manager has approved.
3. Enter its serial number under **Handwritten invoice number**.
4. Check all items, sizes and colors once more. Return any change and give the invoice to the customer.
5. Tap **Confirm invoice & hand over goods** as you hand the goods to the customer.
6. Check **Sale complete**, then tap **Start next sale**.

The optional email is an **order/payment confirmation**, not a BIR tax invoice. **Submitted to email service** means the message was accepted for sending; it does not prove arrival in the customer's inbox. If necessary, use **Resend email confirmation**. Resending does not collect payment again.

[SCREENSHOT E — Actual training Payment received screen. Mark handwritten invoice number, handover and email status.]

## When something goes wrong

| What you see | What to do |
|---|---|
| Waiting for payment | Keep the goods. Refresh the same sale. Ask a manager if unresolved. |
| Manager help needed | Stop. Quote the order number. Do not collect again or hand over goods. |
| Connection lost | Keep the page open. Reconnect and refresh the same sale. |
| Check the previous request | Tap **Resume this sale**. If offered, **Retry this same sale** repeats the original basket/reference without taking payment. Do not start a replacement sale. |
| Sale cannot be found after a reload | Ask a manager to check your active sale. Do not collect payment while its status is unknown. |
| Incorrect basket after review | Use **Cancel this unpaid sale** only when that button is available. Wait for cancellation, then start the corrected sale. Otherwise ask a manager. |
| Digital customer wants to switch to cash | Ask a manager to resolve the digital payment first. Do not take both. |
| Email sending failed | Use **Resend email confirmation**. Do not repeat checkout. |
| Return, refund, exchange or discount request | Ask a manager. These actions are not available in the cashier page. |

## Dos and donts

**Do**

- Use your own account and check the selected variation.
- Read the final total before taking payment.
- Count cash before confirming receipt.
- Wait for the cashier's payment confirmation.
- Issue a registered handwritten invoice for every sale.
- Quote the order number when asking for help.
- Sign out when finished with the tablet. An unfinished sale remains linked to your account.

**Do not**

- Share accounts or let customers operate your signed-in cashier page.
- Use a screenshot, text message or customer assurance as payment proof.
- Hand over goods while payment is pending or needs review.
- Create a replacement sale when a request or payment is uncertain.
- Switch a pending digital sale to cash.
- Use COD, a standalone payment link or a separate static QR to bypass this order.
- Adjust stock, override prices or process refunds from another screen without manager involvement.

## Screenshot production notes

Replace each bracketed instruction with a capture of the implemented screen. Use only synthetic products, customers and nonpayable training URLs. Keep the training banner visible and label each image **Training example**. Capture cash, pending digital and confirmed payment states separately. Synthetic payment states demonstrate controls; they do not demonstrate provider acceptance or live callback delivery. Inspect the rendered PDF and Word pages for readable labels, unclipped screenshots and clear page breaks before staff distribution.
