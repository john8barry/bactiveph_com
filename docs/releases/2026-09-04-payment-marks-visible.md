# Footer payment marks: visible inventory and matching COD tile

Work record: [issue #5](https://github.com/john8barry/bactiveph_com/issues/5). Severity: medium, missing customer-facing payment branding. Owner: footer-branding task. Gateway activation remains owned by [#2](https://github.com/john8barry/bactiveph_com/issues/2) / [PR #4](https://github.com/john8barry/bactiveph_com/pull/4).

## User correction and scope

The user explicitly confirmed QR Ph, Maya, ShopeePay, BPI Online and UnionBank Online, separate PayMongo processor branding, and generic COD. V1 hid the entire online payment group until the new gateway was ready. This caused the reported missing footer logos.

V2 keeps all five approved marks and the processor visible regardless of gateway readiness. Until the gateway is available, a truthful note says: "Online payments are being set up. Check available options at checkout." Readiness continues to govern that note; it does not govern the brand inventory. Actual checkout availability, provider configuration, payment execution, COD eligibility/fees, orders and shipping rules are unchanged.

The COD mark is now a passive, locally hosted SVG: the same 121x49 rounded-pill silhouette as the five official PayMongo payment-method assets, with a white money icon and COD lettering. All six use the same 108x44 tile and intrinsic aspect ratio, in a three-column grid. COD still renders only when its existing gateway is enabled. PayMongo is a separate processor wordmark, not a seventh customer payment method. J&T/LBC and all BIR/business identity content are preserved.

## Reconciliation and verification

- Baseline: remote main `a029911a6f52ccddbe08a8857ebf925d7dc2ea01` (v1 PR #6). Branch: `codex/footer-payment-marks-visible`. Work is isolated from the canonical checkout's unrelated dirty changes.
- Both checked-in theme mirrors have identical partial/COD asset bytes. The only deployable changes are `template-parts/trust-bar.php` and `assets/images/payments/cod.svg` in the active child theme.
- The unchanged CLI guard and the modified regression harness run in local PHP 8.2 WebAssembly, with the documented CLI SAPI name. All seven scenarios pass: ready, not-ready, missing-gateway, gateway-error, manager-error, all-disabled and no-commerce. Exact logo count/order, setup-note visibility, COD enablement, forbidden marks and courier presence are asserted.
- Independent read-only review found no release-blocking source/security/accessibility issue in the bounded diff. The new SVG contains no scripts, handlers, external references or executable content. No new application dependencies are introduced.
- Public homepage readback returned HTTP200 with no v2 marker: **the requested follow-up is not live**.
- Browser-rendered desktop/mobile verification remains pending. Local headless Chrome could not start in this sandbox, and the supported browser rejected the local preview URL under its URL policy. No alternate browser-policy workaround was attempted. Static review and generated HTML are not visual acceptance.
- Existing dependency alerts remain tracked by #7; this presentation-only change does not modify those packages.

## Deployment blocker and next control point

No staging or production mutations occurred for v2. The session blocks the hosting domain `premium343.web-hosting.com`; the exact network policy response says it is not on the allowlist for the current sandbox mode. Approval policy is `never`. Hosting identity, current source snapshots and a fresh full Updraft backup cannot be verified in this session. Do not reuse the historical v1 backup as v2's pre-write backup or claim staging passed.

Resume with hosting-network and browser-preview access available. Reconcile remote main and the current PayMongo writer lane before any mutation; the app's attempted coordination message was denied by its approval policy and must not be treated as delivered. Take fresh full Updraft DB/files backups, copy all components off-server, compare hashes, and validate the archives separately for staging and production.

Deploy only the two allowlisted theme files after comparing their exact pre-change hashes and preserving the latest shared-footer/functions/BIR source. Validate staging at 390px, 768px and 1440px in both ready and unavailable states; verify all images, proportions, wrapping, keyboard focus and no overflow. Verify actual checkout without submitting an order. Only then deploy to the exact production site, invalidate page/edge caches and independently read back the v2 marker, nine image URLs, page health and unchanged payment policy. Keep #5 open until live acceptance.

## Rollback

Snapshot the exact active partial before writing and restore only that file if the release fails, after checking that no subsequent writer changed it. The new unreferenced COD asset may remain harmlessly. Purge page/edge caches and verify the restored marker and checkout. Preserve any later payment-gateway or business-identity release. Full Updraft recovery is reserved for broader recovery. No database changes are part of v2.
