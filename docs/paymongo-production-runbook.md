# PayMongo Hosted Checkout production runbook

This runbook is the payment authority for B Active. It supersedes older build
notes that mention GCash, cards, GrabPay, Atome, or manual bank transfer.

## Approved customer payment methods

- PayMongo Hosted Checkout: QRPh, Maya, ShopeePay, BPI Online, and UnionBank
  Online only.
- WooCommerce Cash on Delivery remains available under the existing fee and
  order-value rules.
- WooCommerce manual bank transfer (`bacs`) is not offered.
- Legacy PayMongo WooCommerce gateways are not offered.

WooCommerce owns the catalog, cart, customer details, shipping, taxes, coupons,
and order. Clicking **Checkout securely** creates a pending WooCommerce order
and redirects the customer to `https://checkout.paymongo.com`. PayMongo owns
only the hosted payment-method selection and authorization screen. A browser
return is never treated as payment proof; the order is paid only after a valid
signed `checkout_session.payment.paid` webhook.

## Release artifact

The production plugin is
`wordpress/wp-content/plugins/bactive-paymongo-hosted-checkout`. Package that
directory without repository metadata, tests are allowed but not required in
the deployed zip, and record the zip SHA-256 in GitHub issue #2 before upload.
Use the identical tested artifact on staging and production.

Requirements:

- WordPress 6.8 or newer
- WooCommerce with PHP currency
- PHP 8.1 or newer with OpenSSL
- Classic WooCommerce checkout; Checkout Blocks are declared incompatible
- HTTPS with the checkout and webhook URL excluded from page caching

## Secret handling

Never store `sk_*` or `whsk_*` values in Git, shell history, screenshots, issue
comments, logs, frontend JavaScript, or deployment artifacts. Enter keys only
in the authenticated WooCommerce payment settings page, or define the following
server-side constants in protected WordPress configuration:

- `BACTIVE_PAYMONGO_TEST_SECRET_KEY`
- `BACTIVE_PAYMONGO_LIVE_SECRET_KEY`
- `BACTIVE_PAYMONGO_TEST_WEBHOOK_SECRET`
- `BACTIVE_PAYMONGO_LIVE_WEBHOOK_SECRET`

Values saved through WooCommerce are encrypted with AES-256-GCM using the site's
WordPress authentication salts. A database copied to a site with different
salts cannot decrypt them and the gateway fails closed.

## Pre-deployment gates

1. Confirm the production and staging URLs, WordPress/WooCommerce/PHP versions,
   classic checkout, current payment gateways, current deployed plugin version,
   and current PayMongo live/test webhook inventory.
2. Take and read back a fresh database backup and a `wp-content` backup.
3. Run `php tests/run.php` under PHP 8.1, 8.2, and 8.3; lint every PHP file.
4. Scan the complete diff and package for secrets, unexpected binaries,
   generated artifacts, and dependencies. This plugin has no third-party runtime
   dependencies.
5. Confirm PayMongo displays all five approved live capabilities as Active.

## Sandbox activation and transaction matrix

1. Upload and activate the exact plugin artifact on staging.
2. Open WooCommerce > Settings > Payments > PayMongo Hosted Checkout.
3. Enable the gateway, keep Sandbox mode enabled, enter the test secret key,
   and save. Saving performs an authenticated capability check and provisions
   one test webhook for:
   `https://staging.bactiveph.com/?wc-api=bactive_paymongo_test`.
4. Confirm customer checkout lists only Pay online securely and eligible Cash on
   Delivery; manual bank transfer and legacy PayMongo gateways must be absent.
5. Place an independent sandbox order through each method. Also execute at least
   one explicit failure/abandonment path. Confirm exact totals, a single stock
   reduction, one Woo transaction ID, the expected private order note, and no
   secrets or customer data in WooCommerce logs.

PayMongo's sandbox controls:

- Maya and ShopeePay: use the PayMongo test redirect and select Authorize for
  success or Fail for a negative test.
- QRPh: do not scan or pay the displayed QR code. Use PayMongo's `test_url`
  simulation control; test QR codes can otherwise represent a real payment.
- BPI: choose account `***0001`; OTP `123456` succeeds, `654321` is invalid,
  and `000000` is expired.
- UnionBank: OTP `111111` succeeds; `222222` through `666666` exercise failure
  cases.

For every success, validate the signed webhook and order independently in both
PayMongo and WooCommerce. A redirect or thank-you page alone is not evidence.

## Production cutover

1. Reconfirm fresh backups and rollback ownership.
2. Upload the exact sandbox-tested artifact to production and activate it.
3. In PayMongo live mode, reconfirm QRPh, Maya, ShopeePay, BPI Direct Debit, and
   UBP Direct Debit are Active. Do not infer capabilities from sandbox.
4. Enter the live secret key, switch Sandbox mode off, enable the gateway, and
   save. The live webhook must be exactly:
   `https://bactiveph.com/?wc-api=bactive_paymongo_live`, enabled, and subscribed
   only to `checkout_session.payment.paid`.
5. Confirm the public FAQ, checkout reassurance copy, and footer list only the
   five approved PayMongo methods plus COD.
6. Confirm production checkout creates a pending order and redirects only to an
   HTTPS `checkout.paymongo.com` URL. Stop before authorizing real money until
   the named operator approves the exact canary amount and order.
7. After canary approval, pay one small real order, then independently verify
   the PayMongo payment ID/method/amount/status, webhook delivery, WooCommerce
   transaction ID/status/order note, stock change, customer email, and absence
   of new critical PHP/Woo/Cloudflare errors.

Do not mark the GitHub issue complete until the exact live canary is proven.

## Monitoring and reconciliation

- PayMongo retries unsuccessful webhook deliveries, but disabled endpoints do
  not replay missed events. Check webhook status and recent delivery results
  during cutover and after any outage.
- Treat any quarantined event/admin notice as a fulfillment stop. Compare the
  PayMongo payment and Checkout Session with the WooCommerce order before a
  human changes order state.
- Never ask a customer to pay again while an earlier session is ambiguous.
- Programmatic WooCommerce refunds are disabled in version 1.0.0. Issue an
  eligible refund in PayMongo under a two-person operational check, then record
  the result in WooCommerce. Confirm method-specific refund rules first.

## Rollback

1. Disable PayMongo Hosted Checkout in WooCommerce; leave COD enabled.
2. Confirm manual bank transfer remains disabled in WooCommerce, then verify
   public checkout immediately shows COD only and no legacy gateways.
3. Disable the matching PayMongo webhook endpoint to stop deliveries if the
   plugin is being removed. Do not delete payment/order audit metadata.
4. Restore the previous plugin/theme artifact only if code rollback is needed;
   restore the database only for confirmed data corruption.
5. Reconcile every PayMongo session/payment created during the cutover window
   before resuming fulfillment.
