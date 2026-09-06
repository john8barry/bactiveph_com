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

The payment-method allowlist is fixed to QRPh, Maya, ShopeePay, BPI Direct
Debit, and UBP Direct Debit. Cash on Delivery remains a separate WooCommerce gateway.
Manual bank transfer and legacy PayMongo gateways are removed from customer
checkout while this plugin is active.

Private verification is enabled by default: only store managers and
administrators can issue PayMongo payments until the operator explicitly
opens the gateway after acceptance. Cash on Delivery remains public.
Callbacks, cancellation and recovery stay reachable during verification.

Security controls include encrypted stored secrets, strict webhook signature
and timestamp verification, exact amount/currency/order/session/mode checks,
idempotent Checkout Session creation, duplicate payment claims, two-phase paid
state persistence, at-most-once WooCommerce effects, durable quarantine, and
fail-closed capability and webhook readiness checks. Ambiguous effects are
acknowledged by an operator without automatic replay. A resolved paid
quarantine has a separate, authenticated provider-readback action that records
the exact paid state without re-emitting stock, email, fulfillment, status, or
payment hooks. Its external armed/processing/done intent recovers either CPT or
HPOS torn-write layout and blocks credential or mode rotation until exact
readback completes. PayMongo refund creation in WooCommerce is intentionally
blocked before side effects in version 1.0.0; verified provider refunds are
recorded as private order notes only.

== Installation ==

Follow docs/paymongo-production-runbook.md in the project repository. Never put
PayMongo secret keys in this plugin, Git, logs, or browser-visible JavaScript.

== Testing ==

From the plugin directory, run:

php tests/run.php

The repository also runs a disposable real WooCommerce datastore regression:
python3 tests/run-datastore-integration.py
Pull the two pinned Docker images listed in the payment CI workflow first.
The fixture uses no real credentials, customer records, or provider payments.

== Changelog ==

= 1.0.0 =
* Preserve unresolved review holds across settings edits and retain GET recovery
  without repeated automatic expiry of held sessions or processing intents.
* Use native insert-only leases and claims, preserving concurrent winners and
  completed effects even when option caches are stale or database calls fail.
* Queue recovery independently for every order, with exact-order deduplication
  and WP-Cron fallback when Action Scheduler cannot store a job.
* Keep payment recovery discovery to one HPOS metadata join, translate CPT
  queries through its supported datastore hook, and reject database scan errors.
* Default PayMongo issuance to manager-only verification, including direct
  payment and order-pay calls; fence rollout changes with the settings drain.
* Add hardened PayMongo Hosted Checkout for the five approved active rails.
* Add signed webhook validation, replay protection, payment deduplication, and
  mode-specific endpoint isolation.
* Add two-phase settlement and at-most-once payment/review effect recovery.
* Add explicit no-effects disposition for independently verified paid
  quarantines, durable CPT/HPOS torn-write convergence, and pre-SQL settings
  create/delete guards.
* Isolate test/live incident identities, bind cancel links to exact attempts,
  retain immutable operator receipts, and recover queued incidents independently
  of order metadata. Guard signing-secret writes and deactivation with the
  serialized settings drain.
* Retain Cash on Delivery and suppress manual bank transfer and legacy PayMongo
  gateways at customer checkout.
