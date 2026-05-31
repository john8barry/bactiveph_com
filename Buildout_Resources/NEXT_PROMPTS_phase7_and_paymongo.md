# Next: Phase 7 (SEO/Analytics/Local) + PayMongo setup
Source of truth: `B_Active_Master_Build_Guide.md` Phase 7 + `research/06_seo_keyword_strategy.md`.
Global rules: staging first · backup before changes · secrets in `.env` via `env_loader` · prove with raw output/screenshots · label FAILED/UNVERIFIED honestly · don't report done without proof.

---

## PROMPT — Phase 7: SEO, Analytics & Local (plan-first)

```
Phase 7 (SEO, Analytics, Local SEO) on STAGING. PLAN FIRST: show me the title/meta template list and the GBP category plan, wait for my OK, then execute in chunks with proof.

Source of truth: B_Active_Master_Build_Guide.md Phase 7 + research/06_seo_keyword_strategy.md.

1) RANK MATH SEO: install + activate Rank Math (free). Run setup. Enable the WooCommerce module → Product schema (Product, Offer, AggregateRating, BreadcrumbList) as JSON-LD. Enable Breadcrumbs. Enable the Local SEO module with B Active's Davao NAP (I'll provide exact address/phone — use [confirm] placeholders until then). Enable FAQ schema on /faq and the size guide.
2) TITLES & META: apply the per-page titles + meta from the Content Library (homepage, About, each of the 7 collections, Our Store) and the patterns in research/06. One H1 per page; primary keyword near the front. Paste a table of page → title → meta.
3) ANALYTICS: install MonsterInsights Lite; connect GA4 with enhanced WooCommerce e-commerce tracking (view, add-to-cart, checkout, purchase). I'll provide the GA4 ID — use a placeholder until then and flag it.
4) SEARCH CONSOLE + SITEMAP: confirm the Rank Math sitemap (/sitemap_index.xml) generates and excludes cart/checkout/my-account/thank-you. (I'll verify the site in GSC at launch.)
5) IMAGE ALT TEXT + INTERNAL LINKS: sweep product/collection/page images for descriptive, keyword-aware alt text; confirm each collection page has its 200–300 word intro; add the internal links per research/06 (collection↔collection, "complete the look" on PDPs).

IMPORTANT: production stays in coming-soon and noindex until launch (Phase 10). Staging must stay noindex/blocked. Do NOT submit anything to Google for staging.
Prove: screenshots of Rank Math Product schema validating (Google Rich Results Test), a sample PDP <head> showing title/meta/JSON-LD, and the sitemap. Report page→title→meta table.
```

---

## PayMongo — getting your TEST keys (you, ~15 min; needed to wire GCash/Maya/cards)

You don't need this for Phase 7 — do it whenever. Test keys work before full KYC; live keys need KYC approval (2–5 days) and go in only at launch (Phase 10).

1. Go to **dashboard.paymongo.com** → sign up (business email, basic business info). The brand: B Active, women's activewear, Davao City.
2. Once in, find the **mode toggle** (Test / Live) — switch to **TEST mode**.
3. Go to **Developers → API Keys** (or **Settings → API Keys**).
4. Copy the two TEST keys:
   - **Public key** — starts `pk_test_...`
   - **Secret key** — starts `sk_test_...`
5. Put them in the project `.env` (never in a script):
   ```
   PAYMONGO_PUBLIC_KEY_TEST=pk_test_xxxxxxxx
   PAYMONGO_SECRET_KEY_TEST=sk_test_xxxxxxxx
   ```
6. Tell the agent: *"Real PayMongo test keys are in .env (PAYMONGO_PUBLIC_KEY_TEST / PAYMONGO_SECRET_KEY_TEST). Wire the PayMongo plugin to read them, enable GCash/Maya/Cards in test mode, and place a sandbox GCash test order — screenshot the payment-method list at checkout and the completed order."*
7. For **GCash/Maya** specifically, PayMongo may require enabling those payment methods in your dashboard (Settings → Payment Methods) — toggle them on. **BNPL (BillEase/Atome)** needs a separate merchant agreement; skip for launch.
8. **Live keys at launch:** after KYC is approved, repeat in LIVE mode for `pk_live_`/`sk_live_`, add as PAYMONGO_*_LIVE, and switch the plugin off test mode — this is a Phase 10 step, with one real ₱ transaction + refund to confirm.
```
