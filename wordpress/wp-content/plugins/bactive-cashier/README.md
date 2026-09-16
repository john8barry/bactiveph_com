# B Active Cashier 1.0.0

Private tablet checkout for one Davao store in PHP currency. Requires PHP 8.2+, WooCommerce and the existing B Active PayMongo Hosted Checkout plugin. Locally verified against WooCommerce 11.1.0 with HPOS. Real provider acceptance and production email delivery are separate release gates.

Activate the plugin, then use **B Active cashier → Setup & daily reconciliation**. New sales are disabled by default. Create individual accounts with the **B Active Sales Associate** role. The cashier opens at `/?bactive_cashier=1` after login. No customer payment credentials are collected by this plugin.

Server-side WooCommerce pricing, taxes and reservations control every sale. Cash and PayMongo use one persistent sale/order. Uncertain payments require manager review; retries do not authorize a second collection. Registered handwritten invoice numbers and confirmed payment/stock effects are required for handover.

## Release and recovery

Read `docs/cashier/RUNBOOK.md` in the source repository before installation. Verify physical stock, check a payment through PayMongo, and confirm that the payment email reaches the intended inbox before opening the register. Issue a registered handwritten invoice for every sale.

Rollback starts by pausing new sales in setup. Keep this plugin, PayMongo and recovery workers active for outstanding orders. Never delete claims, reservations or orders to resolve an uncertain payment. Deactivation is blocked while active claims or cashier holds exist. No uninstall data deletion is provided.

## Included libraries

QR code generation and the Inter/Rajdhani fonts are bundled locally with their license files under `assets`. No external font or QR service receives checkout URLs.

## Changes

1.0.0: restricted cashier, durable sale recovery, cash/change, PayMongo hosted-link QR, protected stock reservations, invoice/handover, optional email/resend, manager resolution and pause controls.
