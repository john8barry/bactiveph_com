# Next prompts — Footer + Homepage, then Phase 6
Paste to Antigravity in order. Run A fully (both chunks, with review between) before B.
Source of truth: `B_Active_Master_Build_Guide.md` (§A5.3 footer, §B1 homepage, Phase 6) + `B_Active_Brand_Style_Guide`.
Global rules every time: staging only · backup before changes · child theme active · secrets from `.env` via `env_loader` (never hardcode) · delete throwaway scripts · prove with screenshots, label FAILED/UNVERIFIED honestly.

---

## PROMPT A — Finish footer + homepage (run first)

```
Two leftover Phase 5 items on STAGING (coming-soon stays OFF so I can review; production untouched; backup exists before changes; child theme is active). Do them as TWO separate chunks, stopping for my review between them.

CHUNK 1 — FOOTER (do this first, then stop and show me):
- Build the real footer per B_Active_Master_Build_Guide.md §A5.3: 4 columns — (1) Shop: the 7 categories; (2) Help: Shipping & Returns, Size Guide, FAQ, Contact, Fabric & Care; (3) Brand: About, Journal, Our Store; (4) Stay in the loop: newsletter email field + IG/FB/TikTok icons.
- Bottom bar: payment icons (GCash, Maya, Visa, Mastercard, COD), "Ships nationwide via J&T & Ninja Van", © 2026 B Active, Privacy · Terms. Confirm the old "WordPress Theme by CreativeThemes" line is GONE (build the real footer, don't just hide it).
- PROOF: a headless screenshot scrolled to the actual footer (saved to Buildout_Resources as footer_real.png) clearly showing the 4 columns + bottom bar. Then stop.

CHUNK 2 — HOMEPAGE (only after I approve Chunk 1):
- The homepage currently shows only the hero + "Why B Active?" then empty space. Build the full 9-section flow from §B1, in order, using the exact copy there: 1 Hero (one CTA) · 2 Featured: The Court Edit · 3 Bestsellers (4–6 products) · 4 Why B Active (4 icons) · 5 Brand-story teaser · 6 The Asian-fit promise · 7 Reviews/community · 8 Newsletter · 9 Footer.
- Use Gutenberg/Blocksy blocks (no page builder), the design tokens, Court Ivory whitespace, Fraunces headings, charcoal CTAs, one CTA per section. Pull product imagery from the catalog; use the brand placeholder hero already in assets/images if needed.
- PROOF: full-page desktop AND 380px mobile screenshots (saved to Buildout_Resources) showing all sections rendered.

Rules: any helper script reads secrets from .env via env_loader — never hardcode; delete throwaway scripts when done. Don't report "done" until the screenshots actually show it. If something can't apply, say so plainly. End with: What changed · Proof · Unverified/failed · Rollback.
```

---

## PROMPT B — Phase 6: payments, shipping, checkout (run after homepage passes)

```
Phase 6 (Payments, Shipping, Checkout) on STAGING only — this is back-end config, sandbox/test mode only, NO live payment keys, do not touch production. Backup exists before changes. Work in chunks, PLAN FIRST: before configuring, show me the proposed shipping zones + rates table and the payment-method list, and WAIT for my approval. Then execute and prove each with raw output/screenshots.

Source of truth: B_Active_Master_Build_Guide.md Phase 6 (§ payments/shipping/checkout) and research/02 + research/05.

1) PAYMENTS (PayMongo plugin, TEST keys only): enable GCash, Maya, GrabPay, Cards. Enable Cash on Delivery via WooCommerce with a ₱50 COD fee and a cap (no COD over ₱2,500). Currency PHP. Do NOT enter live keys — I'll do that at launch. Place payment-method logos at checkout.
2) SHIPPING (Flexible Shipping, free): zones + rates — Davao City ₱80 (free ≥ ₱2,000) + Local Pickup ₱0; Mindanao (excl. Davao) ₱150; Luzon & Visayas ₱180; Rest of PH weight-based. Site-wide free-shipping threshold ₱2,000 with the cart "₱X away from free shipping" notice. Install Shipment Tracking (Zorem).
3) CHECKOUT/CART: guest checkout ON (no forced account); minimal PH-address fields; slide-out cart drawer with the free-ship progress bar + "Thank you for choosing quality" line + "Checkout securely" button; checkout reassurance row (secure checkout · payment methods · 7-day size-exchange). Confirm tax handling with me (likely prices-as-final / not VAT-registered — ask before enabling tax).
4) TEST: place 1 sandbox order each for GCash (test) and COD; confirm correct totals, the ₱2,000 free-ship trigger, COD fee+cap, order-confirmation + "shipped (with tracking)" emails fire, stock decrements.

PROOF: screenshots of the checkout page (payment methods + reassurance row), the cart drawer with the progress bar, the shipping-zones admin table, and the test-order confirmations. Don't report "done" without them. Rules: secrets in .env via env_loader, delete throwaway scripts, honest FAILED/UNVERIFIED labels.
```

---

### After Phase 6 (for reference, not yet)
Phase 7 = SEO/analytics/GBP · Phase 8 = Journal/reviews · Phase 9 = pre-launch QA (incl. the real Davao photoshoot swap) · Phase 10 = launch (this is when live PayMongo keys go in + a tiny real test order). Production stays in coming-soon until Phase 10.
