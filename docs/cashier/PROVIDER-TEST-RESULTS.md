# PayMongo payment test results

Verified 16 September 2026, 23:56 UTC. All four payment methods completed successfully on PayMongo's actual test checkout pages. Independent API reads confirmed each payment as `paid`, its payment intent as `succeeded`, its amount as PHP 1.00, and `livemode=false`. No real charge was made.

## Successful tests

| Method | Test checkout session | Confirmed paid transaction |
| --- | --- | --- |
| QRPh | `cs_8405bf5125f37b9ea0329289` | `pay_TVRsFSpq7nv4VwLdEg7UcDyp` |
| Maya | `cs_0ade036ca2dce8e0e04a7bc6` | `pay_TxeXoSJsDBx54deHSP1hVdXm` |
| ShopeePay | `cs_685c0b98d9fc4b4f6b9da9f1` | `pay_qrG3TNVGctUbWoXtWCxXhgKT` |
| GrabPay | `cs_082df15707597c031e18ef33` | `pay_vFzAVff41MvDFsPqorDxd2Fb` |

These were provider-only test sessions created with the test key through `POST /v2/checkout_sessions`. Results were read back through `GET /v1/checkout_sessions/{id}`. Synthetic customer details were used and provider email receipts were disabled. No WooCommerce orders or inventory reservations were attached to these sessions.

## Failure and cancellation observations

- **Maya:** the first test payment failed (`pay_6s3LM38MXVZs2MUyxUAjcBL3`). Retrying the same checkout then succeeded, as recorded above. Both payment records were independently verified in test mode.
- **ShopeePay:** cancelling in the provider test UI did **not** establish a completed cancellation. Session `cs_946d7c8e2d9156e0c2ff629f` remained active, with intent `pi_CVdQ3zEkLGL5yg4uT2EoM7bY` still `processing` and no payments. The official expiry endpoint rejected cancellation with HTTP 400, `resource_invalid_state`, because the intent was processing. Fresh readback still showed that unresolved state. A separate session was used for the successful ShopeePay test. The unresolved test has no WooCommerce order and holds no store stock.
- Successful sessions also continued to report session status `active`. Payment and payment-intent evidence determine whether money was received; session status or a browser return alone is insufficient.

The ShopeePay result demonstrates why an unresolved digital checkout must not be changed to cash or have its stock released just because a customer closed or cancelled the payment page.

## What this proves, and what remains

1. **Actual PayMongo tests:** all four configured methods accepted a checkout and completed a test payment on the provider's systems. The failure and cancellation observations above are actual provider responses.
2. **Local cashier integration tests:** the separate WooCommerce test suite uses synthetic provider responses to verify order handling, inventory, permissions, callbacks and recovery. The provider-only sessions above do not prove an end-to-end provider callback into the live cashier.
3. **Live PHP 1.00 test:** authorized but still pending at this readback. Complete a real cashier checkout, then verify its provider payment, matching WooCommerce order, stock processing and confirmation email. Test-mode results do not establish a real payment or inbox delivery.

## Official support references

- [Hosted Checkout test mode](https://docs.paymongo.com/docs/payment-channels-testing): test-key checkout sessions simulate the customer payment flow.
- [Wallet and QRPh testing](https://docs.paymongo.com/docs/payment-acceptance-testing): wallets provide test authorization/failure controls; QRPh must use its test URL. PayMongo warns that scanning a test QR with a real wallet can move real money.
- [Hosted Checkout confirmation](https://docs.paymongo.com/docs/payment-channels-hosted-checkout-quick-start): confirm payment using the provider's payment callback.
- [Transaction limits](https://docs.paymongo.com/docs/payment-acceptance-key-concepts): QRPh and the three wallets have a PHP 1.00 minimum.

No credentials, checkout URLs, customer details or raw provider responses are included in this record.

## Live payment completed — 17 September 2026

Owner completed QRPh payment for live cashier order 942. Independent PayMongo API readback confirmed exactly one paid PHP 1.00 payment, `livemode=true`, with transaction `pay_Yx1a2Y7udsUNaNPWYkwcQCJ6` matching WooCommerce. The order has one checkout attempt and the cashier API displays `paid`.

The private test product stock changed from 1 to 0; the order item records reduced stock 1 and the reservation is now 0. WooCommerce remains `processing`, correctly awaiting invoice/handover. This was a non-merchandise system test; no invoice number or goods handover was fabricated.

The automatic “B Active payment confirmation — Order #942” email was sent at 09:56:45 UTC and observed in the authorized Gmail INBOX. Its body contains the correct item, quantity and PHP 1.00 total. SPF, DKIM and DMARC passed. This verifies the actual paid-order confirmation, independently of the earlier generic mail test. Other three methods were verified in provider test mode, not by separate real-money payments.
