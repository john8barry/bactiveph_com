=== B Active PayMongo Hosted Checkout ===
Contributors: bactive
Tags: woocommerce, paymongo, qrph, maya, shopeepay
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later

Production-owned PayMongo Hosted Checkout gateway for B Active.

== Description ==

Creates PayMongo v2 Checkout Sessions and redirects the customer to PayMongo's
hosted payment page. The WooCommerce cart remains on B Active; an order is
created before redirect and is fulfilled only after a verified paid event.

The payment-method allowlist is fixed to QRPh, Maya, ShopeePay, BPI Online, and
UnionBank Online. Cash on Delivery remains a separate WooCommerce gateway.
Manual bank transfer and legacy PayMongo gateways are removed from customer
checkout while this plugin is active.

Security controls include encrypted stored secrets, strict webhook signature
and timestamp verification, exact amount/currency/order/session/mode checks,
idempotent Checkout Session creation, duplicate payment claims, and fail-closed
capability and webhook readiness checks. Automated refunds are intentionally
not supported in version 1.0.0.

== Installation ==

Follow docs/paymongo-production-runbook.md in the project repository. Never put
PayMongo secret keys in this plugin, Git, logs, or browser-visible JavaScript.

== Testing ==

From the plugin directory, run:

php tests/run.php

== Changelog ==

= 1.0.0 =
* Add hardened PayMongo Hosted Checkout for the five approved active rails.
* Add signed webhook validation, replay protection, payment deduplication, and
  mode-specific endpoint isolation.
* Retain Cash on Delivery and suppress manual bank transfer and legacy PayMongo
  gateways at customer checkout.
