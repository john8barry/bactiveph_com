# B Active — Master Brand & Website Build Guide
### The complete, phased development plan for **bactiveph.com**
**Prepared for execution with Google Antigravity (Gemini 3.1 Pro, High).**
Version 1.0 · 31 May 2026 · Owner: The Perry Group

---

> **What this document is.** A single, self-contained playbook to take B Active from a near-empty WordPress install to a launch-ready, premium, conversion-optimised online store and brand. It contains the strategy (the *why*), the complete content for every page (the *what*), and a phase-by-phase build plan with copy-paste prompts for your AI coding agent (the *how*).
>
> **How it is organised.**
> - **Part A — Knowledge Base.** Strategy, brand, design system, information architecture, product catalogue. *Antigravity should read all of Part A first as project context.*
> - **Part B — Content Library.** Every line of finished, ready-to-paste copy for every page.
> - **Part C — The Phased Build Plan.** Ten ordered phases. Each step has plain-English instructions and, where useful, a **prompt in a code block** to paste into Antigravity. Instructions on how to use each prompt sit *outside* the code block, immediately above it.
> - **Appendices.** Supporting kit, research index, input checklist, glossary.

---

## ☑️ How to use this guide with Antigravity

This guide is written to be handed directly to Google Antigravity. Follow this workflow:

1. **Seed the project.** Put this file at the root of the project workspace Antigravity has access to (alongside the `wordpress/` repo described in your tech-stack doc). Also place the **B Active Build Kit** folder (seven research reports + product images + the `brand/` logo SVGs), plus `B_Active_Brand_Style_Guide.md`/`.html`, `B_Active_Product_&_Pricing_Master.xlsx`, and `B_Active_Pricing_Strategy.md`, there. **For a focused, copy-paste design build, follow `00_START_HERE_Antigravity_Handoff.md`** (the manifest + ordered prompts).
2. **Give Antigravity its first instruction** using the **Kickoff Prompt** in *Phase 0, Step 0.1*. It tells the agent to read this guide, read the research, and produce its own `implementation_plan.md` and `task.md` (Antigravity's planning-mode artifacts).
3. **Work phase by phase, in order.** Do not skip ahead — later phases depend on earlier ones (e.g. SEO depends on the permalink fix; product pages depend on the design system). Within a phase, run the prompts top to bottom.
4. **Prompts are in code blocks.** Copy the whole block into Antigravity. The text *above* each block tells you when to run it, what to have ready, and what to verify afterward.
5. **Approve terminal commands deliberately.** Antigravity will ask permission before running shell/FTP/deploy commands (this is by design). Read what it proposes; approve only what matches the step.
6. **Stage, then ship.** Every change is made in the `blocksy-child` theme and tested on a **staging copy** before it touches production. Antigravity must never edit WordPress core or the parent Blocksy theme.
7. **Keep `AGENT_CONTEXT.md` current.** Phase 0 creates a short context file the agent re-reads each session so it never loses the brand palette, conventions, or plugin list.

A note on division of labour: Antigravity edits **code, theme files, and config it can reach over FTP/SSH/WP-CLI**. Some steps are **WordPress admin actions** (installing a plugin, setting permalinks, entering API keys) that a human does in `wp-admin`, or that the agent does if it has admin/WP-CLI access. Each step is labelled **[Agent]**, **[Admin]**, or **[You — external]** (accounts, KYC, photography) so nothing falls through the cracks.

---

# PART A — KNOWLEDGE BASE

## A1. Locked decisions & corrections (read first)

These were confirmed at the start of the project. They override anything that conflicts elsewhere, including the original Content Pack.

| # | Decision | Detail |
|---|---|---|
| 1 | **Platform = WordPress + WooCommerce + Blocksy** | The Content Pack casually suggested "Shopify or Squarespace." Ignore that. The site is already built on **WordPress + WooCommerce 10.8.x + the `blocksy-child` theme**, behind Cloudflare, on Namecheap cPanel/LiteSpeed hosting. We build on this stack. |
| 2 | **Domain = `bactiveph.com`** (one "p") | The live site resolves at **https://bactiveph.com**. The Content Pack's `bactivepph.com` (double "p") was a typo and does **not** resolve. |
| 3 | **Support email = `hello@bactiveph.com`** | Correct the Content Pack's `hello@bactivepph.com` everywhere. |
| 4 | **Visual direction = Soft Feminine Premium** | Muted **sage** + **dusty-rose** accents over **off-white/ivory** and **charcoal**. Wellness-forward, feminine, approachable premium. Full system in §A4. |
| 5 | **Returns = 7-day size exchange** | Free/paid size swaps within 7 days, unworn with tags; no change-of-mind refunds. (This replaces the brief's "no returns," which kills online conversion and fails some processors' requirements.) |
| 6 | **Launch strategy = Full premium build before launch** | We complete the whole premium experience — design, content, photography, SEO — before going live. The phased plan reflects this; there is no "ship a rough MVP" step. |
| 7 | **Pickleball-first positioning** | Lead with premium women's pickleball wear, then broaden to general activewear. This is the ownable, uncontested space (see §A2). |
| 8 | **Currency = PHP (₱).** Market = Philippines, HQ/store = **Davao City**, shipping nationwide. |

**Cleanup required on the live site** (it is currently a default install): delete the "Hello world!" post, the "Sample Page," the default comment, and the `Swagger4469`/`admin`-style author exposure; replace placeholder branding. Handled in Phase 1.

---

## A2. Strategic foundation (why this brand wins)

*Full sourcing in the seven research reports in `./B Active Build Kit/research/`. This is the synthesis.*

### The opportunity is real and time-sensitive
- **Pickleball is booming in the Philippines.** Registered players grew from a few hundred in 2020 to **~60,000 in 2025**, projected **200,000+ by 2030**; the national federation is Olympic-recognised, with **270+ clubs** and **1,000+ courts** nationwide. Pickleball apparel is the **fastest-growing sports-apparel category globally (~18% CAGR)**.
- **Davao is a genuine hub, not an afterthought.** The city has multiple established venues and an active, tournament-playing community across Mindanao. B Active's physical store is positioned to be **the** pickleball-apparel destination in the city.
- **The women's gap is documented and wide open.** The only dedicated PH pickleball retailer is equipment-led and male-skewed; its sole women's bottom is an **imported skort at ~₱4,250** with limited sizing. **No Filipino brand owns premium women's pickleball apparel.** B Active can be first.
- **"Asian fit" is a real product advantage, not a slogan.** Players buying US brands (Lululemon, Alo) face genuine fit/proportion problems. We make this a headline feature.

### The positioning
> **B Active is the Philippines' first premium women's pickleball brand** — beautifully designed dresses, skorts, tops, bras, sets and paddles, cut for an **Asian fit**, performance-tested for the court, and priced so quality doesn't cost a fortune. Born in Davao, made for the women who actually play.

This sits deliberately **between** two failing options for our customer: generic, ill-fitting mass brands (Decathlon) and expensive, wrong-fit imports (Lululemon/Alo at PH prices + shipping). We are the **accessible-premium, locally-designed, women-first, pickleball-first** choice.

### Channel & growth strategy (informs the build)
- **DTC site (bactiveph.com) is the brand home and source of truth** — premium experience, full margin, owned data, SEO. This guide builds it.
- **Marketplaces & social are acquisition arms, not the brand:** a Shopee store for reach and a **TikTok Shop** presence (live-selling is *the* fashion channel in PH) feed customers back to the brand. Build after launch.
- **Community is the moat.** Doubles culture means women buy and play in groups; word-of-mouth in tight social circles is the #1 acquisition channel. Plan an **ambassador program**, **tournament sponsorship**, and a partnership approach to **Davao's own #1-ranked Filipina player** (a ready-made, locally-resonant ambassador — confirm interest before any public claim).

### The premium line we must not cross
Our reference brands (Lululemon, Alo, Vuori, Beyond Yoga, Athleta, Stack, Pillar) win on calm confidence, editorial restraint, technical credibility and community. The brands we must **not** resemble (Shein, Fabletics, Halara, Fashion Nova, Forever 21, plus the mass-sport feel of Nike/Reebok) lose the premium signal through manipulation and clutter. The single biggest risk to B Active's positioning is **launching with discounts**. We do not. Full do/don't table in §A4.6.

---

## A3. Brand foundations

*Drawn from your completed Content Pack, tightened and extended with the research. Keep what fits; everything is editable.*

**Brand name:** B Active  ·  **Founder:** Marnie  ·  **Origin of name:** an invitation — to move, to play, to live more actively. (The "B" also nods to **Barry**, the family behind the brand.)

**Elevator pitch.** Premium-quality activewear and pickleball dresses for women — designed to perform on the court and look effortless off it, at a price that doesn't ask you to choose between quality and value.

**Brand story (for the About page).**
> It started with a love for the game. When our founder, Marnie, fell for pickleball, she ran into a frustrating problem: she couldn't find activewear that was genuinely good quality and beautiful to look at without paying a premium price — and what she could find was never quite cut for her body. So she decided to make it herself.
>
> That's the idea behind the name. *B Active* is a little nudge — to move, to play, to live a healthier, more active life. (The "B" is also for Barry, the family name behind the brand.) Today, B Active makes premium-feeling pickleball dresses, skorts, sets and activewear that are built to perform and made to flatter — designed with an Asian fit, for the women who actually play. Born on the courts of Davao, made for women across the Philippines.
>
> Our promise is simple: **quality you can feel, in every stitch.**

**Mission.** To make life a little easier for the growing community of pickleball players and active women — by giving them stylish, high-quality outfits they can count on.

**Vision.** To become the number-one destination for pickleball outfits, and the activewear brand Filipina women reach for first.

**Core values (5).**
1. **Quality first** — we obsess over fabric, fit and finish.
2. **Accessible premium** — a premium feel at a fair price, always.
3. **Made to move** — designed for real performance, on court and beyond.
4. **Made for us** — an Asian fit, designed for Filipina bodies.
5. **Community** — built for and with the pickleball community.

**Positioning statement.** For active women who love to play, B Active delivers the look and feel of premium brands at an accessible price, designed for an Asian fit — because looking and feeling your best on the court shouldn't cost a fortune.

**Brand personality.** Premium, performance-ready, stylish, functional, confident — with an encouraging, friendly edge. We sound like a knowledgeable friend who plays, not a faceless store.

**Who we're for.**
- **Primary:** women, roughly 20–50, who play pickleball; they want outfits that perform and look great and care about quality and value.
- **Also for:** active women and fitness enthusiasts more broadly (yoga, training, everyday).
- **Their frustration:** it's hard to find good-quality, fashionable athletic wear — especially pickleball dresses — that fits well and doesn't cost a premium.

**Voice & tone.** Friendly and motivating; expert and no-nonsense; confident but never boastful. Lean on **premium** and **quality**; **never** say "cheap" — say *accessible*, *fair*, or *value*. Be specific and playful about the sport; avoid hype, urgency and exclamation-mark marketing.

**Tagline.** Primary: **"Sportswear for every move."** (Approved direction from the brief.)
Supporting lines, used contextually: *"Look good. Play good." · "Quality you can feel." · "Made to move. Made for you."*

**Key messages (use across the site).**
1. Premium quality, comparable to the brands you already love.
2. Designed for pickleball — and the active everyday.
3. Looks great, performs better: moisture-wicking, four-way stretch, buttery-soft, with pockets.
4. Fair, accessible prices.
5. Designed with an **Asian fit** — made for our bodies.

### A3.1 Naming systems (a premium signal, at near-zero cost)
The premium brands all use proprietary **fabric**, **collection** and **colour** names. We adopt the same — it reads as R&D and craft, and it costs only words.

**Signature fabrics (use on PDPs and the Fabric/Product Guide):**
- **CourtSoft™** — our buttery-soft, four-way-stretch main fabric. Squat-proof, second-skin feel.
- **BreezeKnit™** — lightweight, breathable, moisture-wicking knit for hot-court days.
*(Optional third: **SecondSkin™** — seamless, no-dig construction for bras/tanks.)*

**Collections (merchandising groups, not categories):**
- **The Court Edit** — the hero pickleball dresses.
- **The Rally Set** — matching two-piece sets.
- **Everyday Active** — tanks, bras, leggings, skorts for on and off court.

**Colour vocabulary (replace generic names everywhere):**
| Generic | B Active name | | Generic | B Active name |
|---|---|---|---|---|
| Off-white | **Court Ivory** | | Navy | **Midnight** |
| Black | **Onyx** | | Powder blue | **Powder** |
| Pink | **Sakura** | | Olive/forest | **Sagewood** |
| Purple | **Wisteria** | | Grey | **Stone** |
| Apricot | **Apricot** | | Tan/brown | **Almond** |
| Fuchsia | **Bloom** | | Red | **Clay Red** |

## A4. Visual design system — "Soft Feminine Premium"

The whole site should feel calm, spacious and confident: lots of off-white space, soft natural tones, an elegant serif paired with a clean sans, and **sage + dusty-rose used sparingly as accents** (never loud). Think Beyond Yoga/Vuori calm with Alo's editorial polish — feminine, not girly.

### A4.1 Colour palette (with exact tokens)

| Role | Name | HEX | Usage |
|---|---|---|---|
| Background | Court Ivory | `#FAF8F4` | Page background |
| Surface | Cloud White | `#FFFFFF` | Cards, drawers, modals |
| Primary text / primary button | Charcoal | `#2B2A28` | Body text, primary CTA fill |
| Headings | Ink | `#1C1B19` | Display & headings |
| Primary accent | Sagewood | `#9CAE92` | Accent fills, badges, icons, hover states |
| Accent (dark, AA text-safe) | Deep Sage | `#5E6E54` | Links, accent text on ivory (passes AA) |
| Secondary accent | Dusty Rose | `#D8A7A0` | Highlights, secondary badges, decorative |
| Secondary accent (dark) | Rose Clay | `#A96E66` | Rose text/links on ivory (passes AA) |
| Divider / border | Greige | `#E6DFD5` | Hairlines, card borders, input borders |
| Secondary text | Stone | `#6E675F` | Captions, meta, placeholder |
| Sale (use rarely) | Clay Red | `#A9544A` | Only inside the "Court Closings" sale area |

**Contrast rules (WCAG AA, non-negotiable):**
- Body text is **Charcoal on Ivory/White** only.
- Sage and Dusty Rose are **decorative/large-element** colours — do **not** set body text in them. For accent *text* or links on a light background, use **Deep Sage `#5E6E54`** or **Rose Clay `#A96E66`** (both ≥ 4.5:1).
- Primary button = **Charcoal fill + Court Ivory text** (high contrast, premium). Hover → Deep Sage fill. Secondary button = Charcoal 1px outline on transparent; hover → Sagewood 10% fill.
- Minimum 4.5:1 for text, 3:1 for large text/UI; never pure black (`#000`) — use Ink/Charcoal.

### A4.2 Typography
- **Display & headings: `Fraunces`** (variable serif — soft, fashionable, warm). Weights 400/500/600; enable optical sizing on large display.
- **Body & UI: `Inter`** (clean, highly legible). Weights 400/500/600.
- **Eyebrows / labels / buttons:** Inter, UPPERCASE, `letter-spacing: 0.08em`, weight 500.
- *Alternative more-delicate pairing (if preferred): `Cormorant Garamond` headings + `Jost` body.*
- **Type scale (rem):** H1 3.0 / H2 2.25 / H3 1.5 / H4 1.25 / body 1.0 (16px base) / small 0.875. Line-height: headings 1.15, body 1.6. Max body measure ~68ch.
- **Performance:** self-host both fonts (OMGF plugin), 2 families max, `font-display: swap`.

### A4.3 Design tokens (single source of truth — goes in the child theme)
These CSS custom properties are the contract between design and code. Antigravity references the token, never a raw hex. Full install in Phase 2.

```css
:root{
  /* Colour */
  --bactive-ivory:#FAF8F4;  --bactive-white:#FFFFFF;
  --bactive-charcoal:#2B2A28; --bactive-ink:#1C1B19;
  --bactive-sage:#9CAE92; --bactive-sage-deep:#5E6E54;
  --bactive-rose:#D8A7A0; --bactive-rose-deep:#A96E66;
  --bactive-greige:#E6DFD5; --bactive-stone:#6E675F;
  --bactive-sale:#A9544A;
  /* Type */
  --bactive-font-head:'Fraunces',Georgia,serif;
  --bactive-font-body:'Inter',system-ui,sans-serif;
  --bactive-fs-base:16px;
  /* Space (8pt grid) */
  --bactive-space-1:8px; --bactive-space-2:16px; --bactive-space-3:24px;
  --bactive-space-4:32px; --bactive-space-6:48px; --bactive-space-8:64px; --bactive-space-12:96px;
  /* Radius / shadow / motion */
  --bactive-radius-btn:2px; --bactive-radius-card:6px;
  --bactive-shadow-card:0 1px 3px rgba(28,27,25,.06),0 8px 24px rgba(28,27,25,.05);
  --bactive-ease:0.25s cubic-bezier(.4,0,.2,1);
  --bactive-container:1280px; --bactive-gutter:clamp(16px,4vw,48px);
}
```

### A4.4 Components & UI rules
- **Buttons:** generous padding (`14px 28px`), 2px radius, uppercase label, smooth `--bactive-ease` transitions; clear hover + focus-visible ring (`2px Deep Sage`). Always `cursor:pointer`.
- **Product cards:** 3:4 portrait image, name (Inter 500), price, **colour swatches**, star rating; hover swaps to second image; quick-add on hover (desktop). Generous whitespace.
- **Grid density:** desktop 3 per row, tablet 2, **mobile 2** (never 1-up). Collection pages paginate, never infinite-scroll.
- **Icons:** professional SVG line icons only (Lucide/Heroicons). **No emoji in UI.** Consistent 24px viewbox.
- **Imagery:** soft, consistent, generous bleed. Rounded 6px on contained images; full-bleed heroes.
- **Motion:** subtle and quick (150–250ms). Fades and small lifts; no bouncy or attention-grabbing animation.
- **Accessibility:** 44×44px min tap targets; visible focus states; alt text on every image; keyboard-navigable menus, drawer and checkout.

### A4.5 Photography & art direction
Photography is the single highest-leverage premium signal. Direction:
- **Two modes, consistently lit:** (1) clean studio on a warm off-white/greige seamless (matches Court Ivory); (2) **on-court lifestyle in Davao** — real courts, natural light, mid-motion (serve, dink, reach) and at-rest.
- **Cast Filipina models** across realistic body types; this *is* the Asian-fit story. Avoid exclusively Western/idealised casting.
- **Per product: 5–9 images** — front/back/side on-model, a detail/close-up (fabric, pocket, seam), an in-motion action shot, a lifestyle/"court-to-café" shot, and a flat-lay. Brands with 6+ assets per product see materially higher conversion.
- **Always include a "Features & Details" image** showing the built-in shorts, ball pocket and bra construction.
- Warm, soft, low-contrast grade; nothing neon or over-sharpened.

> **Interim:** the supplier catalogue photos in `./B Active Build Kit/product-images/` (mapped to SKUs in §A6) are usable to build and stage every PDP now. Because we are doing a *full premium build before launch*, schedule a branded Davao shoot to replace/augment them before go-live (Phase 9 gate). Antigravity can also generate lifestyle/hero/section imagery with its `generate_image` tool in the brand palette as a bridge.

### A4.6 The DO / DON'T guardrails (premium vs fast-fashion)
Apply these on every screen. (Condensed from the competitor teardown — full version in `research/03_competitor_teardown.md`.)

| Area | DO (premium) | DON'T (fast-fashion) |
|---|---|---|
| Navigation | 4–6 items; "Pickleball" first-class; "Our Story" present | 10+ categories; marketplace breadth |
| Hero | One full-bleed image/video, **one** CTA | Multiple competing CTAs, banners, pop-ups on load |
| Sale language | Name it (e.g. **"Court Closings"**); discounts rare | "FLASH SALE", countdown timers, strike-through everywhere |
| Colour names | Court Ivory, Sagewood, Sakura | "Green", "Pink", "White" |
| Product grid | 2–3/row, generous whitespace | 4–5/row, crammed |
| Images | 5–9 per product, lifestyle + detail + flat-lay | 1–2 studio-only |
| Badges | "New" / "Best Seller" sparingly | "HOT/TRENDING/-50%" on every card |
| Urgency | None (or honest low-stock) | "X people viewing", "sold in last 24h" |
| Membership | Optional rewards, no gating | Fabletics-style VIP wall / forced subscription |
| Trust | Founder story, guarantee block, real reviews, Davao origin | Stock testimonials, fake endorsements |
| Checkout | Guest checkout, GCash/Maya/COD/cards | Forced account, card-only |
| Voice | Calm, specific, playful | Exclamatory, urgent, hyperbolic |

---

## A5. Information architecture & navigation

Catalogue is focused (~30 styles + paddles), so keep IA shallow and confident.

### A5.1 Sitemap
```
Home
├── Shop  (All products; filter by category, colour, feature)
│   ├── Pickleball Dresses        → /collections/pickleball-dresses   ⭐ hero
│   ├── Skorts                    → /collections/skorts
│   ├── Tops & Tanks              → /collections/tops
│   ├── Sports Bras               → /collections/sports-bras
│   ├── Leggings                  → /collections/leggings
│   ├── Sets                      → /collections/sets
│   └── Pickleball Paddles        → /collections/paddles
├── The Court Edit  (editorial collection landing — hero dresses)   → /court-edit
├── About / Our Story            → /about
├── Journal  (blog)              → /journal
├── Our Store (Davao)            → /our-store          (local-SEO landing + GBP URL)
├── Size Guide                   → /size-guide
├── Help
│   ├── Shipping & Returns        → /shipping-returns
│   ├── FAQ                       → /faq
│   ├── Contact                   → /contact
│   └── Fabric & Care Guide       → /fabric-guide
└── Account · Cart · Checkout · Search
Footer also: Privacy Policy, Terms, Ambassador Program (later)
```

### A5.2 Primary navigation (header)
**Left/centre nav:** `Shop ▾`  ·  `Pickleball` (→ The Court Edit)  ·  `About`  ·  `Journal`  ·  `Our Store`
**Right icons:** Search · Account · Wishlist (heart) · Cart
- `Shop ▾` opens a **simple dropdown** (not a busy mega-menu) listing the seven categories + a "Shop All" and one featured image tile ("The Court Edit").
- Slim **announcement bar** above header rotating two calm service messages (see §B microcopy). No countdowns.
- Sticky header on scroll; mobile = hamburger with the same shallow tree, tap-friendly.

### A5.3 Footer
Four columns + payment/trust row:
1. **Shop** (categories) · 2. **Help** (Shipping & Returns, Size Guide, FAQ, Contact, Fabric & Care) · 3. **Brand** (About, Journal, Our Store, Ambassador) · 4. **Stay in the loop** (newsletter signup + socials).
Bottom row: payment icons (GCash, Maya, Visa, Mastercard, COD), "Ships nationwide via J&T / Ninja Van", © line, Privacy/Terms.

### A5.4 URL & taxonomy rules
- Permalinks = **Post name** (`/%postname%/`); product base `/product/`; category base `/collections/` (set in Phase 1/3). **Never** ship the current `/index.php/...` URLs.
- WooCommerce **categories** = the seven Shop groups above. **Collections** ("The Court Edit", "The Rally Set", "Everyday Active") = WooCommerce **tags** or curated pages, used for merchandising.
- Product attributes (global): **Colour** (with swatches) and **Size** (S–XL). Features (Built-in shorts, Ball pocket, Built-in bra, Pockets, UPF50+) as a custom attribute used for filtering — a Lululemon-style premium filter.

---

## A6. Product catalogue (real SKUs → launch range)

Extracted from **`Barry Active.xlsx`** and **verified against the product photos**. Names are proposed (premium, editable). **Prices shown are the RECOMMENDED RETAIL** from the pricing model, with the old SRP in (parentheses). The **single source of truth for product data, costs and live margins is `B_Active_Product_&_Pricing_Master.xlsx`**; the rationale is in `B_Active_Pricing_Strategy.md`. Images are in `./B Active Build Kit/product-images/` (pattern `Batch_SKU_row_n.png`).

> **Photo-review corrections (vs. an earlier draft):** `CK1237` and `ADCK1583` are **leggings** (not tanks); `D29` and `D31` are **skorts** (not dresses); `YY9187` is a **textured eyelet dress**. These are corrected below and in the spreadsheet.

### A6.1 Pickleball Dresses (The Court Edit) — 8 styles
| SKU | Proposed name | Rec. retail ₱ (was) | Colourways | Image file |
|---|---|---|---|---|
| YY9141 | **The Court Dress** (pleated, built-in bra) ⭐ | 1,950 (1,750) | Court Ivory, Wisteria, Stone | `1stBatch_YY9141_6_5.png` |
| YY8793 | **The Rally Dress** (scoop-back, side-tie) | 1,890 (1,500) | Midnight | `1stBatch_YY8793_2_1.png` |
| YY4001 | **The Bubble Dress** (bubble hem) | 2,190 (1,750) | Court Ivory, Sakura, Powder, Wisteria, Onyx | `1stBatch_YY4001_8_7.png`, `2ndBatch_YY4001_22..27.png` |
| AS5019 | **The Match Dress** (zip-front, striped trim) | 2,450 (2,300) | Court Ivory | `2ndBatch_AS5019_36_36.png` |
| AS5028 | **The Serve Dress** (racerback, pleated) | 2,450 (2,300) | Sakura | `2ndBatch_AS5028_37_37.png` |
| YY9187 | **The Eyelet Dress** (textured, drop-waist) | 2,450 (2,450) | Onyx, Court Ivory, Powder | `2ndBatch_YY9187_10..12.png` |
| AS818 | **The Varsity Dress** (collared, contrast trim) | 2,650 (2,450) | Sagewood | `2ndBatch_AS818_33_33.png` |
| AS811 | **The Ace Dress** (contrast trim, flared) | 2,650 (2,450) | Apricot, Clay Red | `2ndBatch_AS811_34_34.png`, `_35_35.png` |

### A6.2 Skorts — 5 styles
| SKU | Proposed name | Rec. retail ₱ (was) | Colourways | Image |
|---|---|---|---|---|
| DK21-015 | **The Everyday Skort** (flared) | 990 (780) | Sakura, Stone, Court Ivory, Onyx | `2ndBatch_DK21-015_3..5.png` |
| DK251204445 | **The Pleated Skort** (high-waist pleated) | 1,090 (750) | Onyx (+ TBC) | `1stBatch_DK251204445_5_4.png` |
| DK240420367 | **The Flow Skort** (diagonal seam, piped) | 1,090 (780) | Court Ivory (+ TBC) | `1stBatch_DK240420367_3_2.png` |
| D31 | **The Breeze Skort** (A-line, BreezeKnit™) | 1,250 (895) | Night Indigo, Meadow Green, Sakura, Onyx, Court Ivory | `2ndBatch_D31_18..21.png` |
| D29 | **The Court Skort** (premium pleated) | 1,290 (895) | Midnight, Court Ivory, Oil Blue, Sakura, Green Jasper | `2ndBatch_D29_13..17.png` |

### A6.3 Tops & Tanks
| SKU | Proposed name | Rec. retail ₱ (was) | Colourways | Image |
|---|---|---|---|---|
| WX1506 | **The Ribbed Tank** (racerback crop) | 895 (595) | Sakura (+ TBC) | `1stBatch_WX1506_4_3.png` |

### A6.3b Sports Bras
| SKU | Proposed name | Rec. retail ₱ (was) | Colourways | Image |
|---|---|---|---|---|
| ADWX1249 | **The Strappy Bra** (multi-strap back) | 950 (680) | Apricot, Powder, Onyx, Court Ivory | `2ndBatch_ADWX1249_6..9.png` |

### A6.3c Leggings
| SKU | Proposed name | Rec. retail ₱ (was) | Colourways | Image |
|---|---|---|---|---|
| ADCK1583 | **The Sculpt Legging** (contour, squat-proof) | 1,090 (730) | Almond, Stone (+ TBC) | `1stBatch_ADCK1583_9_8.png` |
| CK1237 | **The Core Legging** (high-waist) | 1,190 (780) | Wisteria (+ TBC) | `1stBatch_CK1237_7_6.png` |

*Additional SKUs on the sheet's `draft` tab (S106, S238, S261, S351, S51, C34, C35, YY3092, YY8594, YY4501, YY9506) have **no extracted photos** — add names, copy and pricing once their images and final specs are confirmed.*

### A6.4 Sets (The Rally Set)
| SKU | Proposed name | Rec. retail ₱ (was) | Colourways | Image |
|---|---|---|---|---|
| C36 | **The Halter Set** (halter bra + leggings) | 2,490 (2,450) | Sakura, Court Ivory, Almond, Bloom, Powder, Onyx | `2ndBatch_C36_28..32.png` |

### A6.5 Pickleball Paddles
| Item | Rec. retail ₱ (was) | Notes |
|---|---|---|
| Single paddle — Samurai Black / Chinese Red / Elite Blue | 1,890 (1,800) | A small, curated range to complete the "everything to play" story |
| Paddle set (2) | 3,490 (3,000–3,500) | Bundle option |

### A6.6 Launch range guidance
The launch range = **8 dresses + 5 skorts + 2 leggings + 1 tank + 1 bra + 1 set + paddles** (18 apparel styles, each in its colourways). Lead with **The Court Edit** (dresses) and the set as your profit-drivers; the skorts/leggings/tank/bra are accessible entry pieces that build the basket. Resist over-listing — depth of colour and photography per style beats breadth. **Final names, costs, recommended prices and live margins are maintained in `B_Active_Product_&_Pricing_Master.xlsx` (single source of truth); pricing logic is in `B_Active_Pricing_Strategy.md`.** For any remaining/`draft`-tab SKUs, use the **product-copy generation prompt (Phase 4, Step 4.5)** once photos and specs exist.

# PART B — CONTENT LIBRARY (paste-ready)

> Every block below is finished copy. Phase C steps tell Antigravity which block goes where. Edit freely — placeholders in `[brackets]` need a real value before launch (collected in Appendix 2).

## B0. Global microcopy

**Announcement bar (rotates, calm — no countdowns):**
- `Complimentary shipping on orders over ₱2,000 — nationwide.`
- `Now playing: The Court Edit. New pickleball dresses, designed for an Asian fit.`

**Email capture (popup, fires after ~30s or scroll, first visit only; single field):**
> **Join the club.**
> Get **5% off** your first order, plus first access to new drops and Davao court days.
> `[email field]`  → **Get my 5%**
> *No spam — just good things. Unsubscribe anytime.*

**Cart drawer:**
- Empty: `Your cart is feeling light. Let's fix that. → Shop The Court Edit`
- Free-ship progress: `You're ₱[X] away from free shipping` *(pair with a small sage SVG icon — no emoji in UI)*
- Trust line near checkout button: `Thank you for choosing quality.`
- Button: `Checkout securely`

**Checkout reassurance row:** `Secure checkout` · `GCash · Maya · Cards · COD` · `7-day size-exchange guarantee`

**404 page:** `That page took a rest day.` / `The page you're looking for isn't here — let's get you back in the game.` → buttons: *Shop The Court Edit* · *Home*

**Search empty state:** `No matches — yet. Try "dress", "skort", or "set", or browse The Court Edit.`

---

## B1. Homepage

**SEO title:** `B Active — Women's Pickleball Apparel & Activewear Philippines`
**Meta description:** `Premium women's pickleball dresses, skorts, sets & activewear — designed for an Asian fit. Born in Davao, shipped nationwide. Quality you can feel.`
**H1 (visually in hero):** `Premium pickleball wear, made to move with you.`

**Section 1 — Hero** (full-bleed on-court lifestyle image; one CTA)
- Eyebrow: `THE COURT EDIT`
- Headline: `Premium pickleball wear, made to move with you.`
- Sub: `Dresses, skorts and sets designed to perform on the court and look effortless off it — cut for an Asian fit, priced to make sense.`
- Button: `Shop The Court Edit` → /court-edit
- (Secondary text link: `Discover B Active →` /about)

**Section 2 — Featured collection: The Court Edit**
- Eyebrow: `NEW ON THE COURT`
- Heading: `The Court Edit`
- Body: `Our signature pickleball dresses — buttery-soft CourtSoft™ fabric, built-in shorts with a ball pocket, and a flattering cut that holds its shape through every rally.`
- Button: `Shop pickleball dresses` → /collections/pickleball-dresses

**Section 3 — Bestsellers**
- Heading: `What everyone's wearing`
- Body: `The pieces our community reaches for first.`
- (Dynamic: 4–6 best-sellers) · Button: `Shop bestsellers` → /shop

**Section 4 — Why B Active** (four icons + labels)
1. **Premium quality** — `Fabric, fit and finish we obsess over.`
2. **Made to move** — `Four-way stretch, moisture-wicking, pockets that hold a ball.`
3. **An Asian fit** — `Designed for our bodies — not adapted from someone else's.`
4. **Fair prices** — `The premium feel, without the premium price tag.`

**Section 5 — Brand-story teaser**
- Heading: `It started with a love for the game.`
- Body: `B Active was born in Davao when our founder couldn't find pickleball wear that was beautiful, well-made, well-priced — and actually cut for her. So she made it.`
- Button: `Our story` → /about

**Section 6 — The Asian-fit promise** (differentiator block)
- Heading: `Designed for an Asian fit.`
- Body: `Most activewear is cut for a Western frame and "adjusted" for the rest of us. We start from our own measurements, so straps sit right, lengths land where they should, and nothing digs or gapes. True to size, S–XL.`
- Button: `See the size guide` → /size-guide

**Section 7 — Reviews / community**
- Heading: `Loved by players like you`
- Body: `Real reviews and tagged photos from the B Active community.`
- (UGC + star ratings)

**Section 8 — Newsletter**
- Heading: `Be the first to know`
- Body: `Join the club for 5% off your first order, new drops, and Davao court days.`
- `[email field]` → `Join`

**Section 9 — Footer** (per §A5.3)

---

## B2. About / Our Story  (`/about`)

**SEO title:** `Our Story — The Philippines' Premium Women's Pickleball Brand | B Active`
**Meta:** `B Active is a women's pickleball and activewear brand born in Davao City. Premium quality, an Asian fit, and fair prices. This is our story.`
**Page title (H1):** `It started with a love for the game.`

> When our founder, Marnie, fell for pickleball, she ran into a problem every player here knows: you either pay a fortune for imported activewear that still doesn't fit quite right, or you settle for something generic that wasn't made with you in mind. Beautiful, well-made, well-priced pickleball wear — cut for a Filipina body — simply didn't exist.
>
> So she made it.
>
> **B Active** is a little nudge — to move, to play, to live a healthier, more active life. (The "B" is also for **Barry**, the family behind the brand.) We design premium-feeling pickleball dresses, skorts, sets and activewear that are built to perform and made to flatter — with an **Asian fit**, performance fabrics, and the details that matter on court: built-in shorts, ball pockets, four-way stretch, buttery-soft feel.
>
> We're proud to be **born in Davao** — on the same courts where the city's growing community of women play, compete and cheer each other on — and to ship to active women across the Philippines.
>
> Our promise is simple: **quality you can feel, in every stitch.**

**Values strip** (the five from §A3, as icon + one-liner).
**Closing CTA:** `Ready to play? → Shop The Court Edit`

---

## B3. Collection page intros (≥200 words each — needed for SEO; trimmed here, expand in build)

**Pickleball Dresses** (`/collections/pickleball-dresses`)
*SEO title:* `Pickleball Dresses Philippines | Premium Women's Activewear | B Active`
*H1:* `Pickleball Dresses for Women in the Philippines`
*Intro:* `Meet The Court Edit — pickleball dresses designed to move with you and flatter you, on court and off. Each dress pairs a clean, confident silhouette with serious performance: buttery-soft, four-way-stretch CourtSoft™ fabric, sweat-wicking comfort, built-in shorts with a ball pocket, and supportive built-in bras on selected styles. Cut for an Asian fit and offered in S–XL, our dresses are made for real Filipina players — so straps stay put, hems land right, and nothing digs through a long rally. Whether you're chasing your first win at the local court or heading straight from a match to merienda, a B Active dress keeps up and looks effortless doing it. Premium feel, court-tested, fairly priced. Free shipping on orders over ₱2,000, with a 7-day size-exchange guarantee so you can find your perfect fit with confidence.`

**Skorts** (`/collections/skorts`) — *H1:* `Skorts for Pickleball & Tennis` — intro leads on pleated skort + built-in shorts, pockets, no-ride-up, Asian fit, court-to-café.
**Tops & Tanks** (`/collections/tops`) — *H1:* `Tops & Tanks for the Active Everyday` — ribbed/seamless, buttery-soft, layer over a bra or wear alone.
**Sports Bras** (`/collections/sports-bras`) — *H1:* `Sports Bras — Support, Coverage, Comfort` — support levels, removable pads, SecondSkin™ no-dig.
**Leggings** (`/collections/leggings`) — *H1:* `Leggings Made to Move` — squat-proof, high-waist, pockets.
**Sets** (`/collections/sets`) — *H1:* `Matching Sets — The Rally Set` — effortless, mix-and-match.
**Paddles** (`/collections/paddles`) — *H1:* `Pickleball Paddles` — a small, curated range to complete your kit.

*(Full 200–300-word intros for every collection are generated in Phase 3, Step 3.4 via the prompt provided there.)*

---

## B4. Product detail pages

### B4.1 Master PDP template (every product follows this)
```
[Collection] · [Category]
H1: [Product Name]
Price: ₱[price]
Colour: [named swatches]   Size: [S–XL] · True to size (Asian fit) → Size guide link

Short description (1–2 benefit-led lines):
[One conversational, sport-specific line + one benefit line.]

Key features (bullets):
• CourtSoft™ / BreezeKnit™ — [performance benefit]
• Four-way stretch · moisture-wicking · buttery-soft
• Built-in shorts ([X]" inseam) with ball pocket  [dresses/skorts]
• Built-in [light/medium] support bra, removable pads  [where applicable]
• [UPF 50+ if applicable]
• [Total length]" total length (measured at size S)
• Asian fit — designed for Filipina court athletes

Fit & sizing: True to size (Asian fit), S–XL. Model is [height] cm, wearing size [M].
Fabric: [X]% Nylon, [Y]% Spandex.
Care: Gentle hand wash only. Wash in cold water with like colours, then hang to dry. Do not machine wash, tumble dry, use fabric softener, bleach, or iron.

[Add to cart]   ·   [♡ Wishlist]
Accordions: Description · Features & Fit · Shipping & Returns (7-day size exchange) · Fabric & Care
"Complete the look": [matching bra/skort/paddle]
```

### B4.2 Finished hero PDP copy

**The Court Dress — YY9141 — ₱1,950** *(hero)*
- Short: `Move freely, look effortless. Our signature pleated pickleball dress pairs a flattering silhouette with serious performance.`
- Features: `CourtSoft™ four-way stretch • Sweat-wicking, buttery-soft • Built-in shorts (4" inseam) with ball pocket • Built-in light-support bra with removable pads • Pleated skirt that holds its shape • Asian fit, S–XL`
- Fit: `True to size (Asian fit), S–XL. Model is 165 cm, wears M.`
- Colours: Court Ivory · Wisteria · Stone

**The Rally Dress — YY8793 — ₱1,890**
- Short: `Open-back ease, on-court confidence. A scoop-back dress with a side tie that skims and flatters.`
- Features: `CourtSoft™ four-way stretch • Sweat-wicking • Built-in shorts with ball pocket • Flattering scoop back • Asian fit, S–XL`
- Fit: `True to size (Asian fit), S–XL. Model is 168 cm, wears M.`
- Colour: Midnight

**The Bubble Dress — YY4001 — ₱2,190**
- Short: `The dress everyone asks about. A sculpted bodice meets a playful bubble hem — court-ready, café-ready.`
- Features: `CourtSoft™ four-way stretch • Built-in shorts with ball pocket • Built-in bra, removable pads • Statement bubble hem • Asian fit, S–XL`
- Fit: `True to size (Asian fit), S–XL. Model is 165 cm, wears M.`
- Colours: Court Ivory · Sakura · Powder · Wisteria · Onyx

**The Varsity Dress — AS818 — ₱2,650**
- Short: `Preppy meets performance. A collared dress with crisp contrast trim and a pleated skirt that means business.`
- Features: `CourtSoft™ four-way stretch • Collared, contrast-trim detail • Built-in shorts with ball pocket • Built-in support bra • Asian fit, S–XL`
- Fit: `True to size (Asian fit), S–XL. Model is 167 cm, wears M.`
- Colour: Sagewood

**The Halter Set — C36 — ₱2,490** *(The Rally Set)*
- Short: `One decision, head-to-toe. A flattering halter bra and high-waist leggings that move as one.`
- Features: `CourtSoft™ four-way stretch, squat-proof • Halter bra with removable pads • High-waist leggings with side pockets • Mix-and-match with The Court Edit • Asian fit, S–XL`
- Fit: `True to size (Asian fit), S–XL. Model is 166 cm, wears M.`
- Colours: Sakura · Court Ivory · Almond · Bloom · Powder · Onyx

**The Pleated Skort — DK251204445 — ₱1,090**
- Short: `Swing freely, stay covered. A high-waist pleated skort with secure built-in shorts.`
- Features: `CourtSoft™ four-way stretch • Built-in shorts (no ride-up) with pocket • High, flattering waistband • Asian fit, S–XL`
- Fit: `True to size (Asian fit), S–XL. Model is 165 cm, wears M.`
- Colour: Onyx

**The Ribbed Tank — WX1506 — ₱895**
- Short: `Your everyday MVP. A ribbed racerback crop that layers over any bra and wears solo just as well.`
- Features: `Buttery-soft ribbed knit • Racerback cut for free movement • Crop length pairs with high-waist skorts/leggings • Asian fit, S–XL`
- Fit: `True to size (Asian fit), S–XL. Model is 167 cm, wears M.`
- Colour: Sakura

*(The **full set of all 19 styles** — names, colourways, finished short descriptions and feature bullets — is maintained in the **Product Master** tab of `B_Active_Product_&_Pricing_Master.xlsx` (single source of truth). The examples above set the on-brand structure; for any `draft`-tab SKUs that don't have photos yet, use the Phase 4, Step 4.5 prompt.)*

---

## B5. Size Guide  (`/size-guide`)

*SEO title:* `Size Guide — Asian Fit, S–XL | B Active`
*H1:* `Find your fit`
Intro: `B Active is designed with an Asian fit and runs true to size. If you're between sizes, size up for a relaxed feel or stay true for a closer fit. Still unsure? Our 7-day size-exchange guarantee has you covered.`

**How to measure:** Bust — around the fullest part. Waist — the narrowest part of your torso. Hips — the fullest part. Keep the tape level and snug, not tight.

**Size chart (cm)** — *[confirm against your tech pack; placeholder values to replace]*
| Size | Bust | Waist | Hips |
|---|---|---|---|
| S | 80–84 | 62–66 | 86–90 |
| M | 85–89 | 67–71 | 91–95 |
| L | 90–95 | 72–77 | 96–101 |
| XL | 96–101 | 78–83 | 102–107 |

Note: `Each product page lists the model's height and the size she's wearing for reference.` · FAQ link · Exchange link.

---

## B6. Shipping & Returns  (`/shipping-returns`)

*H1:* `Shipping & Returns`

**Shipping**
- `We ship nationwide across the Philippines via J&T Express and Ninja Van.`
- `Complimentary shipping on orders over ₱2,000.` Below that: `Metro Davao ₱80 · Mindanao ₱150 · Luzon & Visayas ₱180.` *(confirm finals)*
- `Orders are processed within 1–2 business days. You'll get a tracking number by email once your parcel ships.`
- `Davao City: same-day/next-day local delivery and in-store pickup available — choose at checkout.`

**Payments**
- `Pay easily with GCash, Maya, credit/debit card, or Cash on Delivery (COD). All transactions are secure.`

**Returns & exchanges — our 7-day size-exchange guarantee**
- `We want you in the right size. If your fit isn't perfect, we accept size exchanges within 7 days of delivery for unworn items with tags attached and original packaging.`
- `We don't accept change-of-mind returns, and for hygiene reasons we can't exchange sports bras or worn items.`
- `To start an exchange, email hello@bactiveph.com with your order number — we'll guide you through it.`
- `Received something faulty or incorrect? Contact us within 7 days and we'll make it right at no cost to you.`

---

## B7. Our Store — Davao  (`/our-store`)  *(local-SEO landing + Google Business Profile URL)*

*SEO title:* `Visit Us — Activewear & Pickleball Store in Davao City | B Active`
*Meta:* `B Active is Davao City's premium women's pickleball and activewear store. Visit us, or shop online with nationwide shipping. GCash, Maya, card & COD.`
*H1:* `Find us in Davao City`
Body: `B Active is proud to call Davao home. Visit our store to feel the fabrics, find your fit, and talk pickleball with people who actually play. Can't make it in? We ship nationwide.`
- `Address: [full street address], Davao City, Davao del Sur, Philippines` *(confirm)*
- `Hours: [days/times]` · `Phone/Viber: +63 [number]` · `Email: hello@bactiveph.com`
- Embedded Google Map · `Get directions` button.
- `In the area to play? Ask us about the best courts and community sessions around the city.` (links to the Journal's Davao courts guide once published)

---

## B8. Fabric & Care Guide  (`/fabric-guide`)  *(trust/education — premium signal)*

*H1:* `Fabric & care`
- **CourtSoft™** — `Our signature four-way-stretch knit: buttery-soft, squat-proof, sweat-wicking and built to hold its shape. The everyday hero of The Court Edit.`
- **BreezeKnit™** — `Lightweight and breathable for hot-court days — moves air, moves sweat, keeps you cool.`
- **Care basics:** `Gentle hand wash only. Wash in cold water with like colours, then hang to dry. Do not machine wash, tumble dry, use fabric softener, bleach, or iron.`

---

## B9. FAQ  (`/faq`)  *(also good for FAQ schema)*

- **Do you ship nationwide?** `Yes — across the Philippines via J&T Express and Ninja Van, with free shipping over ₱2,000.`
- **What payment methods do you accept?** `GCash, Maya, credit/debit cards, and Cash on Delivery.`
- **What is "Asian fit"?** `Our patterns are designed from Filipina measurements rather than adapted from Western sizing, so straps, lengths and waistbands sit where they should. We run true to size, S–XL.`
- **Can I exchange for a different size?** `Yes — within 7 days of delivery, for unworn items with tags. See Shipping & Returns.`
- **Do the dresses have built-in shorts and a bra?** `Most do. Each product page lists exactly what's built in, the inseam length, and the support level.`
- **Are there pockets?** `Yes — our dresses and skorts include a ball pocket; several styles add a phone pocket. Check the product page.`
- **Do you have a physical store?** `Yes, in Davao City — see Our Store.`
- **How do I track my order?** `You'll receive a tracking link by email when your order ships.`

---

## B10. Contact  (`/contact`)
*H1:* `We're happy to help`
`Questions about fit, an order, or pickleball in general? Reach us and a real person will get back to you.`
- Form: Name · Email · Order # (optional) · Message.
- `Email: hello@bactiveph.com` · `Viber/Phone: +63 [number]` · `Socials: [IG] · [FB] · [TikTok]`
- `We usually reply within one business day.`

---

## B11. Journal (blog) — launch plan + first post

**Pillars:** A) *Pickleball in the Philippines* (sport authority) · B) *Activewear & Style* (product authority). Publish these **five BOFU/high-intent posts first** (full list of 18 in `research/06_seo_keyword_strategy.md`):
1. **What to Wear for Pickleball: The Complete Outfit Guide for Filipina Women** — *KW: what to wear for pickleball Philippines*
2. **Pickleball Dress vs. Tennis Dress: What's the Difference?** — *KW: pickleball dress vs tennis dress*
3. **The Best Pickleball Courts in Davao City** — *KW: pickleball courts Davao City* (local-SEO powerhouse; verify venue details before publishing)
4. **The Ultimate Pickleball Outfit Checklist for Women** — *KW: pickleball outfit women Philippines*
5. **B Active Sizing Guide: Find Your Perfect Asian Fit** — *KW: activewear sizing Philippines* (reduces returns)

**First post — ready outline + intro (expand in Phase 8):**
*Title:* `What to Wear for Pickleball: The Complete Outfit Guide for Filipina Women (2026)`
*Intro:* `Pickleball is the most fun you'll have on a court — and what you wear shouldn't get in the way of your game or your confidence. Whether you're heading to your first session at a Davao court or you're already chasing tournament wins, here's exactly what to wear for pickleball, why it matters, and how to build a kit that performs and looks great.`
*Sections:* The dress vs. skort-and-top question · What "performance fabric" actually means · Why built-in shorts and pockets matter · Support: choosing the right bra · The Asian-fit difference · Shoes & socks · Your starter kit (links to The Court Edit, skorts, bras, paddles) · FAQ.
*Internal links:* pickleball-dresses, skorts, sports-bras, paddles, size-guide. *CTA:* `Shop The Court Edit`.

---

## B12. Policy pages (stubs to finalise)
- **Privacy Policy** — standard PH-appropriate privacy notice (data collected, cookies, GA4, payment processors PayMongo, contact). Generate in Phase 7; have a human/legal review.
- **Terms of Service** — sales terms, pricing in PHP, shipping, the 7-day exchange policy, IP, governing law (Philippines).
- **Returns Policy** — the §B6 exchange text, expanded.
*(These need a human review; mark clearly and don't treat AI drafts as legal advice.)*

# PART C — THE PHASED BUILD PLAN

**Sequence matters.** Phases run in order; steps within a phase run top to bottom. Legend: **[Agent]** Antigravity does it in code/config · **[Admin]** done in `wp-admin` (agent if it has access, else you) · **[You — external]** accounts, KYC, photography, legal.

**Phase map**
0. Foundations & access → 1. Technical baseline & hardening → 2. Brand & design system → 3. IA & navigation → 4. Catalogue & products → 5. Page content & build → 6. Commerce configuration → 7. SEO & analytics → 8. Content & community → 9. Pre-launch QA (the premium gate) → 10. Launch & growth.

---

## PHASE 0 — Foundations & access

**Objective:** give the agent its context, a safe place to work (staging + Git), and kick off the external accounts that have lead times.

### Step 0.1 — Kickoff [Agent]
Run this **first**, before anything else. It points Antigravity at this guide and the research, and asks it to produce its own planning artifacts. Have this file and the `B Active Build Kit/` folder in the workspace.

```
You are the lead engineer building the B Active e-commerce website. Read these before doing anything:
1) B_Active_Master_Build_Guide.md (the source of truth — strategy, brand, design system, IA, content, phased plan)
2) B_Active_Brand_Style_Guide.md (+ open B_Active_Brand_Style_Guide.html) — the canonical brand book: logo, palette, type, voice, components — and the logo concept SVGs in "B Active Build Kit/brand/" (lockup, wordmark, monogram)
3) Every file in "B Active Build Kit/research/" (seven market/UX/tech/pricing reports), plus B_Active_Product_&_Pricing_Master.xlsx (products, costs, recommended prices, margins) and B_Active_Pricing_Strategy.md
4) The repo's tech_stack.md, agent_capabilities.md, design_capabilities.md

Then, using your planning mode:
- Produce implementation_plan.md that mirrors Phases 0–10 of the build guide, broken into checkable tasks, with dependencies noted.
- Produce task.md to track progress, starting at Phase 0.
- Confirm the stack you detect (WordPress + WooCommerce + Blocksy child theme, Cloudflare, Namecheap/LiteSpeed) and list anything you cannot yet access (FTP creds, WP admin, Cloudflare token, API keys).
- Do NOT make any changes yet. Output the plan and the access gaps, and wait for my approval.

Hard rules for this whole project:
- BEFORE any change, ensure a fresh full backup exists (UpdraftPlus → database + files → remote storage). No backup, no change.
- Work on a STAGING copy first; deploy to production only after review. NEVER run destructive file operations (deletes/cleanup) directly on production via FTP — remove temp scripts on staging or via WP-CLI, then run `wp core verify-checksums`. Never delete WordPress core or plugin files manually.
- Work ONLY in wp-content/themes/blocksy-child. Never edit WordPress core or the parent Blocksy theme.
- Commit to Git before and after each change. Use the design tokens in the guide (§A4.3) — reference CSS variables, never hardcode hex.
- Match the brand voice (§A3) and the DO/DON'T guardrails (§A4.6). No discounts, countdowns, or fast-fashion patterns, ever.
- Never delete a user account without confirming an alternate admin exists; secure admins with 2FA. (See Phase 3.5.)
```

### Step 0.2 — Access & credentials [You — external]
Make sure the agent (and you) have, stored in the project `.env` per your tech-stack doc (never commit secrets):
- WordPress admin login (create a fresh admin user; **delete/rename the default `admin`/`Swagger4469` exposure**).
- cPanel + FTP credentials (Namecheap), and SSH/WP-CLI if your plan supports it.
- Cloudflare API token (scope: DNS + Cache Rules) — the repo's `cf_*.py` scripts use this.
- Domain confirmed: `bactiveph.com`.

### Step 0.3 — Staging + version control [Agent]
Use after access is confirmed. Antigravity will propose terminal commands — approve them.

```
Set up a safe working environment:
1) Create a staging copy of the site. Preferred: a "staging.bactiveph.com" subdomain in cPanel with its own WordPress, cloned from production using UpdraftPlus (or All-in-One WP Migration). Password-protect staging and set it to discourage indexing (noindex). If a subdomain isn't possible, propose InstaWP cloud staging.
2) Initialise Git inside wp-content/themes/blocksy-child: git init, add a sensible .gitignore (exclude .env, node_modules, OS files), and commit "Initial child theme state".
3) Establish the workflow you will follow every time: commit a pre-edit snapshot → make change on staging → test → deploy changed files only via FTP → flush LiteSpeed cache → commit post-edit. 
Document this workflow at the top of AGENT_CONTEXT.md (created next). Show me the staging URL and the initial commit hash.
```

### Step 0.4 — Seed `AGENT_CONTEXT.md` [Agent]
This short file is re-read each session so the agent never loses the essentials.

```
Create AGENT_CONTEXT.md in the blocksy-child theme root (exclude from FTP deploy). Populate it from the build guide:
- Project: B Active (bactiveph.com), premium women's pickleball/activewear, Davao City PH, currency PHP.
- Stack & hosting facts (WordPress, WooCommerce 10.8.x, Blocksy + blocksy-child, Cloudflare, Namecheap/LiteSpeed, FTP/cPanel deploy).
- The full design-token block from guide §A4.3 (colours, fonts, spacing) — this is the canonical palette.
- Typography: Fraunces (headings) + Inter (body), self-hosted via OMGF.
- Voice rules (§A3) and DO/DON'T guardrails (§A4.6).
- Active plugin list (fill in as installed) and each plugin's purpose.
- WooCommerce attribute names (Colour, Size, Features) and the B Active colour vocabulary (§A3.1).
- The "never do" list (no core edits, no live DB DROP/DELETE, no disabling security, no production deploy of checkout changes without a staging test).
Keep it under ~2 pages. Commit it.
```

### Step 0.5 — Start the long-lead external accounts [You — external]
Begin now; they unblock later phases:
- **PayMongo** business account → complete KYC (2–5 business days). Needed in Phase 6.
- **Google Business Profile** for the Davao store → request **video verification**. Needed in Phase 7.
- **Google Analytics 4** property + **Google Search Console** access. Phase 7.
- **Email tool** (Omnisend free tier recommended) account. Phase 6/8.
- *(No paid theme or add-on needed — Blocksy's core is free and actively maintained. We use free plugins + light child-theme code instead of Blocksy Pro; see Step 1.6.)*
- Decide the **physical store NAP** exactly (name/address/phone) — used identically everywhere (§B7).

---

## PHASE 1 — Technical baseline & hardening

**Objective:** a clean, fast, secure, correctly-configured WordPress/WooCommerce foundation before any design or content. **Do the permalink fix first.**

### Step 1.1 — Fix permalinks (priority #1) [Admin/Agent]
The live site uses ugly `/index.php/...` URLs; SEO can't proceed until this is fixed.

```
On staging first, then production:
1) Settings → Permalinks → select "Post name" (/%postname%/). Save.
2) WooCommerce → Settings → Advanced → "Product permalink base" set to Custom: /product/ ; category base /collections/ (this matches the guide's IA so collection URLs read /collections/pickleball-dresses/).
3) Confirm .htaccess updated (LiteSpeed respects it). Visit several URLs to confirm no 404s (new site, low risk).
4) Flush LiteSpeed cache and flush rewrite rules (wp rewrite flush if WP-CLI is available).
Report the before/after URL format.
```

### Step 1.2 — Core WordPress & WooCommerce settings + cleanup [Admin/Agent]
```
Configure base settings on staging, then production:
WordPress:
- Settings → General: Site title "B Active", tagline "Women's Pickleball Apparel & Activewear — Philippines", timezone Asia/Manila, site language English (US).
- Delete the default "Hello world!" post, the default comment, and the "Sample Page". Remove the default author exposure (create a proper admin user, set display name; do not expose "Swagger4469"/"admin").
- Discourage search engines = OFF on production (must be crawlable at launch); ON for staging.
WooCommerce:
- Currency: Philippine Peso (₱ PHP), symbol left, 2 decimals, comma thousands separator.
- Selling location: Philippines; default customer location: Davao City.
- Enable HPOS (Settings → Advanced → Features → High-Performance Order Storage).
- Accounts & privacy: allow GUEST checkout (do NOT force account creation); allow account creation at checkout and on "My account".
- Enable product reviews; show "verified owner" label; enable star ratings.
- Set placeholder image to a brand-neutral Court Ivory placeholder.
Commit and report.
```

### Step 1.3 — Performance stack [Admin/Agent]
Namecheap runs **LiteSpeed**, so LiteSpeed Cache is the native choice. One cache plugin only.

```
Install and configure the performance stack on staging, validate, then production:
1) LiteSpeed Cache (free): enable cache; DO NOT cache logged-in users; enable "Separate cache for cart"; NEVER cache /cart/, /checkout/, /my-account/, /wp-admin/, /wp-login.php (add to Do-Not-Cache URIs). Enable browser cache, image lazy-load, responsive placeholders. Turn on CSS/JS minify (test thoroughly after — disable any that breaks layout/JS). If cPanel offers Redis/Memcached object cache, enable it; otherwise page cache alone is fine.
2) ShortPixel Image Optimizer: connect API key; enable WebP + AVIF with fallback; set to optimise new uploads automatically; run bulk optimise after products are imported (Phase 4).
3) OMGF (Host Google Fonts Locally): self-host Fraunces + Inter; remove external Google Fonts requests. Limit to these 2 families.
Do NOT install WP Rocket, W3TC, Jetpack, or any page builder (Elementor/Divi) — they conflict or bloat. Report PageSpeed before/after on the homepage.
```

### Step 1.4 — Cloudflare configuration [Agent]
Page Rules are deprecated (Jan 2025) — use **Cache Rules**.

```
Configure Cloudflare for WordPress/WooCommerce (use the repo's cf_*.py scripts or the dashboard):
- SSL/TLS: Full (Strict). Ensure a valid origin certificate (Let's Encrypt/Sectigo in cPanel). Enable "Always Use HTTPS".
- Cache Rules: cache static assets aggressively; BYPASS cache for /cart/*, /checkout/*, /my-account/*, /wp-admin/*, and any request with cookies woocommerce_cart_hash, woocommerce_items_in_cart, wp_woocommerce_session_*, wordpress_logged_in_*.
- Disable Rocket Loader (breaks WooCommerce JS). Do not enable Cloudflare Auto-Minify (deprecated; LiteSpeed handles minify).
- Browser Cache TTL: 1 year for static assets. Security level: Medium. Bot Fight Mode: ON. Enable the WordPress Managed WAF ruleset.
- Security rules: rate-limit /wp-login.php (>5 req/min → managed challenge); block /xmlrpc.php.
- Optional: APO ($5/mo) for edge HTML caching — if enabled, do NOT also run a "Cache Everything" rule (conflicts → stale carts).
Report the rules you created.
```

### Step 1.5 — Security hardening [Agent/Admin]
```
Harden security (Wordfence, Limit Login, WPS Hide Login, Akismet are already installed):
- wp-config.php (back up first): add define('DISALLOW_FILE_EDIT', true); and define('FORCE_SSL_ADMIN', true);
- Wordfence: run the firewall optimisation wizard; schedule daily scans; block XML-RPC; keep WPS Hide Login updated (treat as obscurity only). Enable 2FA for admin accounts.
- Limit Login Attempts Reloaded: sensible lockout thresholds.
- UpdraftPlus: schedule DAILY backups (DB + files) to remote storage (Google Drive/Dropbox), 30-day retention; run one backup now and confirm it restores on staging.
Never disable security plugins. Report the hardening checklist completed.
```

### Step 1.6 — WooCommerce feature stack (free — no paid add-on) [Admin/Agent]
We stay on **free Blocksy** (one of the most-installed, actively-maintained free WooCommerce themes). Do **not** buy Blocksy Pro — provide the few premium shop features with free, well-maintained tools, kept lean:
```
Do NOT purchase Blocksy Pro. Add the premium shop features with free tools:
- Variation swatches (colour + size): install "Variation Swatches for WooCommerce" (by GetWooPlugins — free, 300k+ installs).
- Sticky Add-to-Cart, the Size-Guide popup, and custom product tabs (Care, Fabric): build these into the blocksy-child theme as lightweight custom code that references the design tokens (§A4.3) — no plugin, faster site.
- Wishlist + Quick View (optional, not launch-critical): if wanted, use free "TI WooCommerce Wishlist" + a free quick-view plugin; otherwise defer.
- Product filtering (Colour/Size/Features): use Blocksy's built-in filters or the free "Filter Everything" plugin / native WooCommerce filter blocks.
Confirm swatches render on the shop grid and PDP. Keep the total plugin count lean (the tech research warns against bloat).
```

---

## PHASE 2 — Brand & design system

**Objective:** translate §A4 into the live theme so every later page inherits the look automatically.

### Step 2.1 — Blocksy Global Defaults (colours & type) [Admin/Agent]
```
In Appearance → Customize, set Blocksy's global defaults to the B Active system (guide §A4.1–A4.2; canonical reference = B_Active_Brand_Style_Guide.html/.md):
- Colors → Global palette: Color1 = Charcoal #2B2A28 (primary/buttons), Color2 = Sagewood #9CAE92 (accent), set link colour to Deep Sage #5E6E54, text = Charcoal, headings = Ink #1C1B19, site background = Court Ivory #FAF8F4, borders = Greige #E6DFD5. Record which --theme-palette-color-N maps to which brand colour in AGENT_CONTEXT.md.
- Typography → Base font = Inter (400/500/600); Headings font = Fraunces (500/600), with the type scale in §A4.2 (H1 3rem … body 1rem, line-heights heading 1.15 / body 1.6). Buttons & eyebrows = Inter uppercase, letter-spacing 0.08em.
- WooCommerce: shop image ratio 3:4 portrait; product card hover = swap to 2nd image; 3 columns desktop / 2 mobile.
Export the Customizer settings (Blocksy export) and commit the export file to the repo.
```

### Step 2.2 — Install design tokens + enqueue in child theme [Agent]
This makes the tokens the real contract. Use exactly the block from §A4.3.

```
In wp-content/themes/blocksy-child:
1) Create assets/css/custom.css. At the top, paste the full :root design-token block from build guide §A4.3 (colours, fonts, spacing, radius, shadow, motion, container). Then add base component styles that REFERENCE the tokens (never raw hex): buttons (primary = charcoal fill/ivory text, hover deep sage; secondary = charcoal outline), product cards, headings, links (deep sage), focus-visible ring (2px deep sage), announcement bar, section spacing using the 8pt scale.
2) Create assets/js/custom.js (empty stub for now).
3) In functions.php, enqueue the parent style, then the child custom.css (dependency on parent), and custom.js in the footer — use filemtime() as the version for automatic cache-busting on FTP deploy (see research 05 §6.3 for the exact enqueue snippet).
Test on staging that tokens render and buttons match the spec. Commit "feat: design tokens + base components".
```

### Step 2.3 — Logo, favicon, buttons & global components [Admin/Agent]
```
- Upload the B Active logo, replacing the current bactive_logo.jpg. Use the **provided concept SVGs** in "B Active Build Kit/brand/": `bactive-logo-lockup.svg` or `bactive-logo-wordmark.svg` for the header, and `bactive-logo-monogram.svg` for mobile + favicon/social avatar. (These are concepts — if the owner supplies refined/final logo files, use those instead.) Export PNGs at 2×/3× from the SVGs for any raster needs.
- Set the site icon/favicon (Customize → Site Identity).
- Apply the button, form-field, and card styles globally so WooCommerce buttons (Add to cart, Checkout) inherit the brand styles.
- Verify focus states and 44px tap targets. Commit.
```

### Step 2.4 — Brand imagery bridge (optional but recommended) [Agent]
Until the Davao photoshoot, generate on-brand hero/section imagery so the build looks premium.

```
Using your image-generation tool, create a small set of on-brand, photorealistic lifestyle images in the B Active palette (soft, warm, off-white/sage tones, natural light, Filipina women playing pickleball outdoors and relaxing court-side, premium editorial feel — NOT neon, NOT busy). Produce: 1 homepage hero (wide), 1 "Court Edit" feature image, 1 brand-story image, 1 "Asian fit" section image. Save to the theme's assets/images/ with descriptive names and add to AGENT_CONTEXT as placeholders to be replaced by the real shoot before launch (Phase 9 gate). Keep file sizes optimised.
```

---

## PHASE 3 — Information architecture & navigation

**Objective:** build the shallow, confident IA from §A5 — pages, menus, header, footer, taxonomy.

### Step 3.1 — Create all pages [Admin/Agent]
```
Create these pages (draft) with the URLs from guide §A5.1, so navigation and SEO can be wired now (content added in Phase 5):
Home, Shop, The Court Edit (/court-edit), About (/about), Journal (/journal), Our Store (/our-store), Size Guide (/size-guide), Shipping & Returns (/shipping-returns), FAQ (/faq), Contact (/contact), Fabric & Care (/fabric-guide), Privacy Policy, Terms, Returns Policy.
Set Home as the static front page (Settings → Reading) and Journal as the posts page. Confirm WooCommerce system pages (Cart, Checkout, My account) exist with clean permalinks. Commit.
```

### Step 3.2 — Header & announcement bar [Admin/Agent]
```
Using Blocksy's Header Builder, build the header per guide §A5.2:
- Row 1 (announcement bar): rotating two messages from §B0 (calm, no countdown), Court Ivory background, Charcoal text, dismissible.
- Row 2 (main): logo left; primary menu centre — Shop (dropdown), Pickleball (→ /court-edit), About, Journal, Our Store; right icons — Search, Account, Wishlist (heart), Cart (with count + slide-out drawer).
- "Shop" dropdown = simple dropdown listing the 7 categories + "Shop All" + one featured tile linking to The Court Edit. NOT a busy mega-menu.
- Sticky header on scroll. Mobile: hamburger with the same shallow tree, 44px tap targets, search accessible.
Create the "Primary Menu" and assign it. Commit.
```

### Step 3.3 — Footer [Admin/Agent]
```
Using Blocksy's Footer Builder, build the footer per guide §A5.3:
4 columns — Shop (categories) · Help (Shipping & Returns, Size Guide, FAQ, Contact, Fabric & Care) · Brand (About, Journal, Our Store, Ambassador [placeholder]) · Stay in the loop (newsletter email field + IG/FB/TikTok icons).
Bottom bar: payment icons (GCash, Maya, Visa, Mastercard, COD), "Ships nationwide via J&T & Ninja Van", © 2026 B Active, Privacy · Terms.
Use the newsletter copy from §B0. Commit.
```

### Step 3.4 — Collections taxonomy + collection intro copy [Agent]
```
1) Create WooCommerce product categories matching §A5.1: Pickleball Dresses, Skorts, Tops & Tanks, Sports Bras, Leggings, Sets, Pickleball Paddles (slugs under /collections/). Create tags/curated groups for the merchandising collections: The Court Edit, The Rally Set, Everyday Active.
2) For EACH of the 7 category pages, write a unique 200–300 word SEO intro in the B Active voice, using the keyword-to-page map (research 06) and the seeds in guide §B3. Each intro: primary keyword in the first sentence, 2–3 secondary keywords woven in naturally, 1–2 internal links to related categories or the relevant Journal post, and the free-shipping + 7-day-exchange reassurance line. Place intro copy where Blocksy supports category descriptions (top short intro + optional longer block below the grid). 
Maintain the premium voice — no hype, no discounts. Commit and list the URLs.
```

## PHASE 3.5 — Recovery & Safety Gate (honour throughout; clear before Phase 4)

**Why this exists:** during the first design build, work was done directly on production with no backup, and a cleanup step accidentally deleted core/security files (`wp-comments-post.php`, `wp-activate.php`, `wordfence-waf.php`). No customer harm (store was in coming-soon mode and Cloudflare's WAF held), but it must not recur. These are hard gates, not suggestions.

**The five rules (non-negotiable):**
1. **Backup before every change.** A fresh UpdraftPlus backup (database + files, to remote storage) must exist before any modification. No backup, no change. Keep ≥ 7 days; test a restore once.
2. **Staging first.** All work happens on `staging.bactiveph.com` (cPanel subdomain, password-protected + noindex). Production updates only by deploying reviewed, changed files — never by editing live.
3. **No destructive FTP on production.** Never delete or "clean up" files directly on the live server over FTP. Remove temporary deployment scripts on staging or via WP-CLI, and always run `wp core verify-checksums` afterward to catch any missing core file.
4. **Protect admin access.** Keep ≥ 2 admin accounts you control; never delete the owner's account; secure each with a strong password + 2FA (Wordfence Login Security); don't expose login usernames publicly (below).
5. **Have a rollback path.** If anything breaks, restore the latest UpdraftPlus backup. If a missing `wordfence-waf.php` causes a fatal error, remove the `auto_prepend_file` line in `.user.ini`/`php.ini` to restore the site, then reinstall Wordfence.

**Gate checklist — clear all before starting Phase 4:**
- [ ] A verified, restorable UpdraftPlus backup exists (and runs daily).
- [ ] `wp core verify-checksums` passes (no missing/modified core files; deleted files restored).
- [ ] Wordfence healthy (regenerated `wordfence-waf.php`, firewall optimised); Cloudflare WAF active.
- [ ] `staging.bactiveph.com` exists and is the working environment.
- [ ] Owner admin secured (strong password + 2FA); a second backup admin exists; login username not exposed.
- [ ] Category taxonomy corrected to the seven in §A5.1; tags = The Court Edit / The Rally Set / Everyday Active.
- [ ] Homepage renders the designed Home page (static front page set), not the blog.

**Securing the admin account without deleting it:** the owner's account stays. To stop leaking its login name — WP Admin → Users → [account]: set a public Display name that is *not* the login; change the author slug so `/author/<login>/` no longer reveals it (`wp user update <login> --user_nicename=b-active`); in Wordfence → All Options, enable "Prevent discovery of usernames through '?author=N' scans and the REST API"; add 2FA via Wordfence Login Security. Create one additional admin (non-obvious username) as a backup.

## PHASE 4 — Catalogue & products

**Objective:** a beautifully merchandised catalogue with premium PDPs, real products, swatches, and on-brand copy for every SKU.

### Step 4.1 — Global attributes & swatches [Admin/Agent]
```
Create global product attributes in WooCommerce:
- Colour (type: colour swatch via the free "Variation Swatches for WooCommerce" plugin) — add the B Active colour vocabulary terms using the names from §A3.1 with these swatch hex values (Court Ivory #FAF8F4, Midnight #1F2A44, Onyx #1C1B19, Sakura #E9C3CA, Powder #BFD2E0, Sagewood #9CAE92, Wisteria #B9A7C9, Stone #9A958C, Apricot #E7B58E, Almond #C9B7A4, Bloom #C65F8E, Clay Red #A9544A). 
- Size (button swatch): S, M, L, XL.
- Features (used for filtering, Lululemon-style): Built-in shorts, Ball pocket, Built-in bra, Pockets, UPF50+, Squat-proof.
Confirm swatches render on product cards and PDPs. Commit.
```

### Step 4.2 — PDP layout configuration [Admin/Agent]
```
Configure the single-product (PDP) template via Blocksy WooCommerce + the free Variation Swatches plugin + child-theme code, to match guide §B4.1 and the premium UX research (report 04):
- Image gallery: vertical thumbnails / large main image; support 5–9 images; zoom on hover; show a dedicated "Features & Details" image. (No gallery = no sale — enforce min 3 images later.)
- Variation swatches for Colour + Size directly under the title.
- "Sticky Add to Cart" bar appears once the main button scrolls out of view (mobile + desktop).
- A "Size guide" link directly beside the size selector that opens the size-guide modal (custom modal built into the child theme).
- Custom product tabs/accordions: Description · Features & Fit · Shipping & Returns (7-day size exchange) · Fabric & Care.
- "Complete the Look" cross-sell block (link dress → matching bra/skort/paddle).
- Show model height + size worn, fabric %, and the Asian-fit line prominently.
Commit and screenshot a sample PDP.
```

### Step 4.3 — Create products + upload images [Admin/Agent]
The supplier photos are in `B Active Build Kit/product-images/` (mapped to SKUs in §A6).

```
Create the launch products from build-guide §A6 (start with The Court Edit dresses, then skorts, tanks/bras, The Halter Set, paddles). For each product:
- Use the product name, SKU, category, colourways and **recommended retail price** from §A6 / the Product Master tab of B_Active_Product_&_Pricing_Master.xlsx (single source of truth). Only a few colourways/specs marked "TBC" need confirming.
- Upload the matching images from "B Active Build Kit/product-images/" (filename pattern Batch_SKU_row_n.png). Set a clean featured image; add gallery images; assign colour variants to the right swatch where multiple colourways exist.
- Set the Colour + Size attributes and create variations (S–XL per colour). Mark in-stock.
- Tag with the right merchandising collection (The Court Edit / The Rally Set / Everyday Active) and Features attributes.
- Fill SEO alt text on every image ("The Court Dress in Court Ivory — women's pickleball dress, B Active").
Leave descriptions empty for now (next steps). These are interim supplier photos — flag for replacement at the Phase 9 photography gate. Commit and list created products.
```

### Step 4.4 — Hero PDP copy [Agent]
```
Write/paste the finished PDP copy for the hero products exactly as in build-guide §B4.2 (The Court Dress, The Rally Dress, The Bubble Dress, The Varsity Dress, The Halter Set, The Pleated Skort, The Ribbed Tank). Use the §B4.1 structure: short benefit-led description, feature bullets, fit & sizing with model height/size, fabric %, care. Fill the custom tabs (Features & Fit, Fabric & Care). Keep the brand voice. Commit.
```

### Step 4.5 — Generate copy for ALL remaining SKUs [Agent]
This is the "all content for all pages" workhorse. Run it once the launch list is final.

```
Generate on-brand copy for every remaining product in the catalogue (build-guide §A6 + the "draft" tab SKUs once confirmed). For EACH product output, fill the WooCommerce fields:
- Product name (use §A6 proposed names; propose names for any new SKUs in the same "The ___ Dress/Skort/Set" style).
- Short description: 1 conversational, pickleball/sport-specific line + 1 benefit line.
- Long description / feature bullets following the §B4.1 template: lead with CourtSoft™ or BreezeKnit™; include four-way stretch / moisture-wicking / buttery-soft; built-in shorts + inseam + ball pocket for dresses/skorts; built-in bra + support level + removable pads where applicable; UPF50+ only if true; total length at size S; the Asian-fit line.
- Fabric % (use 76% Nylon / 24% Spandex as the default unless the sourcing sheet says otherwise — flag assumptions), care instructions, and a model height + size-worn line (use a sensible 165–168 cm / size M default and flag for confirmation).
- A unique 150–300 word description (never copy supplier text) for SEO + Product schema.
- SEO title and meta per build-guide §B / research 06 patterns.
Rules: premium voice; no hype, no discounts, no fake claims; if you're unsure of a real attribute (e.g., whether a style has a built-in bra), mark it [confirm] rather than inventing. Produce the copy as a table I can review, then apply to the products after my OK. Commit.
```

### Step 4.6 — Filtering & size guide [Admin/Agent]
```
- Enable collection-page filtering by Colour, Size, and Features (Built-in shorts, Ball pocket, Built-in bra, Pockets, UPF50+) using Blocksy's built-in filters or the free "Filter Everything" plugin — a premium, Lululemon-style filter experience.
- Build the Size Guide modal content from build-guide §B5 (Asian-fit chart, how-to-measure) and link it from every PDP and the /size-guide page.
- Confirm sort options (Featured, Newest, Price) and that the grid is 3-up desktop / 2-up mobile with generous spacing. Commit.
```

---

## PHASE 5 — Page content & build

**Objective:** build every page using the Content Library (Part B). Premium layout, generous whitespace, one clear action per section.

### Step 5.1 — Homepage [Agent]
```
Build the homepage using build-guide §B1, in this exact section order with the provided copy:
1 Hero (full-bleed lifestyle image, eyebrow + H1 + sub + single "Shop The Court Edit" CTA) → 2 Featured: The Court Edit → 3 Bestsellers (dynamic 4–6) → 4 Why B Active (4 icons) → 5 Brand-story teaser → 6 The Asian-fit promise → 7 Reviews/community (UGC + ratings) → 8 Newsletter → 9 Footer.
Use Gutenberg blocks / Blocksy content blocks (NOT a page builder). Use the design tokens; lots of Court Ivory whitespace; Fraunces headings; one CTA per section; subtle motion only. Use the Phase 2 hero/section imagery as placeholders. Mobile-first: verify the hero, grids (2-up), and CTAs on a 380px viewport. Commit and screenshot desktop + mobile.
```

### Step 5.2 — About / Our Story [Agent]
```
Build /about from build-guide §B2: H1 "It started with a love for the game.", the founder/brand-story copy, the 5 values strip (icon + one-liner), the brand-story image, and the closing "Shop The Court Edit" CTA. Calm editorial layout, generous spacing. Add the SEO title/meta from §B2. Commit.
```

### Step 5.3 — The Court Edit landing [Agent]
```
Build /court-edit as an editorial collection landing (the "Pickleball" nav target): a hero line ("The Court Edit — pickleball dresses, designed for an Asian fit"), a short editorial intro, the dresses grid (pulled from the Pickleball Dresses category + The Court Edit tag), a "Why our dresses" mini-feature row (CourtSoft™, built-in shorts + ball pocket, Asian fit), and a cross-link to skorts/sets/paddles. Premium, image-led. Commit.
```

### Step 5.4 — Support & info pages [Agent]
```
Build these pages from the Content Library, with their SEO titles/metas:
- /size-guide (§B5, incl. the chart + how-to-measure; wire the modal)
- /shipping-returns (§B6 — confirm the shipping rates with me before publishing)
- /fabric-guide (§B8)
- /faq (§B9 — also enable FAQ schema in Phase 7)
- /contact (§B10 — working contact form to hello@bactiveph.com; add Viber/phone + socials)
- /our-store (§B7 — Davao landing with address, hours, embedded Google Map, in-store pickup note; this is the GBP website URL)
Commit and list URLs.
```

---

## PHASE 6 — Commerce configuration

**Objective:** payments, shipping, checkout and transactional comms that fit the PH market and feel trustworthy. (COD is preferred by ~⅔ of PH shoppers but carries 20–40% return-to-sender risk — mitigations below.)

### Step 6.1 — Payments: PayMongo + COD [Admin/Agent]
```
Once PayMongo KYC is approved:
- Install "Payments via PayMongo for WooCommerce". Enter LIVE API keys (test with sandbox keys first). Enable GCash, Maya, GrabPay, and Visa/Mastercard. Confirm currency PHP and min transaction ₱100.
- Enable Cash on Delivery (WooCommerce built-in) but RESTRICT it to sensible shipping zones; add a ₱50 COD handling fee; consider capping COD to orders under ₱2,500.
- COD risk mitigations: enable an order-confirmation step (SMS/Viber or email confirmation before dispatch) and show a small "prepay with GCash and save the COD fee" nudge.
- Place payment-method logos (GCash/Maya/Visa/MC/COD) in the footer and at checkout (trust).
Run a sandbox test order for each method. Report results.
```

### Step 6.2 — Shipping zones + free-ship threshold [Admin/Agent]
```
Install "Flexible Shipping" (Octolize, free) and configure zones (confirm final rates with me):
- Davao City: Standard ₱80; Free if cart ≥ ₱2,000; also enable Local Pickup (₱0) and a local same-day/next-day option (Lalamove/Grab handled manually).
- Mindanao (excl. Davao): ₱150.
- Luzon & Visayas: ₱180.
- Rest of PH: weight-based fallback.
Set a site-wide Free Shipping threshold at ₱2,000 and surface a "₱X away from free shipping" progress bar in the cart drawer (§B0). Install "Shipment Tracking for WooCommerce" (Zorem) so tracking numbers can be added to orders and emailed to customers. Report the zone table.
```

### Step 6.3 — Checkout, cart drawer & tax [Admin/Agent]
```
- Confirm guest checkout is ON; minimise checkout fields (PH address format; make company optional/removed); enable express GCash/Maya at the top of checkout.
- Cart: slide-out drawer with the free-shipping progress bar, the "Thank you for choosing quality" trust line, and a "Checkout securely" button (§B0).
- Tax: configure per your registration status — if not VAT-registered, set prices as final/tax-inclusive and disable tax display; confirm with me. 
- Add the checkout reassurance row (Secure checkout · payment methods · 7-day size-exchange guarantee).
Test the full cart→checkout flow on staging. Commit.
```

### Step 6.4 — Transactional emails [Agent]
```
Brand the WooCommerce transactional emails (override templates in blocksy-child/woocommerce/emails/ — copy from parent first): B Active logo, Court Ivory/Charcoal palette, Fraunces/Inter, warm friendly tone. Set the "from" name "B Active" and email hello@bactiveph.com. Customise: order confirmation, processing, shipped (include tracking), and exchange instructions. Add the "quality you can feel" sign-off. Send test emails and confirm rendering on mobile. Commit.
```

### Step 6.5 — Email marketing welcome flow [Admin/Agent]
```
Connect Omnisend (free tier) to WooCommerce. Build:
- A signup form/popup matching §B0 (fires ~30s or on scroll, first visit, single email field, 5% off).
- A 3-email welcome automation: (1) "Welcome to the club + your 5% code", (2) the brand story + The Court Edit, (3) the Asian-fit promise + size-guide + best-sellers. Voice per §A3.
- An abandoned-cart automation (gentle, 1–2 emails, no countdowns).
Generate unique 5%-off codes (one-time per subscriber). Keep design on-brand. Report the automations created.
```

---

## PHASE 7 — SEO & analytics

**Objective:** technical SEO, schema, per-page metadata, analytics, and Davao local SEO. (Permalinks were already fixed in Phase 1.)

### Step 7.1 — Rank Math setup + schema [Admin/Agent]
```
Install Rank Math SEO (free) and run the setup wizard:
- Enable the WooCommerce module (free in Rank Math; do not use Yoast). Enable Product schema → outputs JSON-LD Product, Offer, AggregateRating, BreadcrumbList automatically.
- Enable Breadcrumbs (and enable Blocksy breadcrumbs on shop/product). 
- Set title/meta templates from research 06: Homepage, Category, Product, Blog patterns (see §B + research). Default product title "%title% — %key feature% | B Active Philippines".
- Enable the Local SEO module: business name, Davao address (exact NAP from §B7), hours, geo — outputs LocalBusiness schema.
- Enable FAQ schema on /faq and the size guide.
Validate output with Google's Rich Results Test. Commit.
```

### Step 7.2 — Per-page titles & meta [Agent]
```
Apply the SEO title + meta description for every page using the copy already written in Content Library Part B (Homepage §B1, About §B2, each collection §B3, Our Store §B7, etc.) and the keyword-to-page map in research 06. For products, ensure each has a unique meta from Step 4.5. Confirm one H1 per page, logical H2/H3 structure, and the primary keyword near the front of each title. Report a table of page → title → meta.
```

### Step 7.3 — Analytics, Search Console & sitemap [Admin/Agent]
```
- Install MonsterInsights Lite; connect GA4 with enhanced WooCommerce e-commerce tracking (product views, add-to-cart, checkout, purchase).
- Verify the site in Google Search Console (via the GA connection); submit the Rank Math sitemap (/sitemap_index.xml). Confirm Cart/Checkout/My-account/Thank-you are excluded from the sitemap.
- Set canonical handling (Rank Math default). Confirm production is crawlable (Discourage search engines = OFF).
Report GA4 + GSC connection status.
```

### Step 7.4 — Google Business Profile + local citations [You — external/Admin]
```
Using research 06 §2 as the checklist:
- Claim & video-verify the Google Business Profile for the Davao store. Primary category "Sporting Goods Store"; secondary: Women's Clothing Store, Sports Apparel Store, Athletic Wear Retailer, Clothing Store. Enable attributes (in-store shopping, in-store pickup, online/nationwide delivery, GCash/card accepted, women-led).
- Upload 10+ photos (storefront, interior, on-court lifestyle), write the 250-word keyword-rich description, set /our-store as the GBP website URL, pre-populate 5–8 Q&As, and start weekly posts.
- Lock the exact NAP (§B7) and submit identically to Tier-1 directories: Facebook Business, Foursquare, Yelp PH, Waze, Yellow Pages PH, plus Pickleheads (as an associated retailer).
```

### Step 7.5 — Image alt-text & on-page pass [Agent]
```
Sweep all product, collection, and page images for descriptive, keyword-aware alt text (e.g., "Filipina player wearing The Court Dress in Sagewood on a Davao pickleball court"). Add internal links per research 06 §3.4 (collection ↔ collection, blog → collection, "complete the look" on PDPs). Confirm each collection page has its 200–300 word intro. Report any images missing alt text.
```

## PHASE 8 — Content & community

**Objective:** seed topical authority and social proof so the brand launches with substance, not an empty shell.

### Step 8.1 — Journal setup + first 5 posts [Agent]
```
Set up the Journal (/journal) with two categories: "Pickleball PH" and "Style & Fit". Write and publish the 5 priority posts from build-guide §B11 (full list of 18 in research 06), each 900–1,500 words, in the B Active voice, with: a clear H1 with the primary keyword near the front, H2/H3 structure, internal links to the relevant collections + size guide, a featured image (generate on-brand if needed), SEO title/meta, and a closing "Shop The Court Edit" CTA.
IMPORTANT for "The Best Pickleball Courts in Davao City": do NOT invent venue names, court counts, or addresses — list only details you can verify from the research file or that I provide, and clearly mark anything unverified as [confirm] for me to fill. Produce drafts for my review before publishing. 
```

### Step 8.2 — Reviews / UGC [Admin/Agent]
```
Install "Customer Reviews for WooCommerce" (free): enable photo reviews, verified-buyer badges, and a post-purchase review-request email (sent ~7–10 days after delivery). Add a star-rating display to product cards and the homepage "Loved by players like you" section. Do NOT fabricate reviews — leave the system ready to collect real ones from launch, and seed the homepage block with genuine quotes only once you have them (or hide it until you do). Commit.
```

### Step 8.3 — Welcome & nurture content [Agent]
```
Finalise the Omnisend welcome + abandoned-cart email content from Phase 6.5 with polished, on-brand copy and imagery. Add a simple monthly "Journal + new arrivals" newsletter template the owner can reuse. Keep it calm and editorial — no countdowns or urgency. Provide the copy for my review.
```

---

## PHASE 9 — Pre-launch QA (the premium gate)

**Objective:** because we're doing a *full premium build before launch*, nothing ships until it passes here. Antigravity can use its **Chrome DevTools** skill for performance/a11y and live page checks.

### Step 9.1 — Performance / Core Web Vitals [Agent]
```
Run performance QA on staging using Chrome DevTools + PageSpeed Insights on Home, a collection page, and a PDP (mobile + desktop). Targets: LCP < 2.5s, INP < 200ms, CLS < 0.1, PageSpeed mobile 75–90+. Fix offenders: ensure all images are ShortPixel-optimised (WebP/AVIF) with explicit width/height (prevents CLS), fonts self-hosted (OMGF), LiteSpeed cache + Cloudflare cache rules working, no render-blocking JS from leftover plugins. Re-test and report before/after scores.
```

### Step 9.2 — Accessibility (WCAG AA) [Agent]
```
Audit for WCAG 2.1 AA: text contrast ≥ 4.5:1 (verify Sage/Rose are used only as decoration, accent text uses Deep Sage/Rose Clay), all images have alt text, all interactive elements are keyboard-navigable with visible focus, tap targets ≥ 44px, the cart drawer and checkout are operable by keyboard and screen reader, form fields have labels. Fix issues and report the checklist.
```

### Step 9.3 — Mobile & cross-browser [Agent]
```
Verify on a 380px mobile viewport and on Chrome, Safari, and Firefox: header/hamburger, hero, 2-up product grids, PDP gallery + sticky add-to-cart, size-guide modal, cart drawer, and checkout. Confirm no layout breaks, no horizontal scroll, tap targets comfortable. Report with screenshots.
```

### Step 9.4 — Commerce test transactions [Agent/You]
```
On staging (or with PayMongo test mode): place test orders paying via GCash, card, and COD. Confirm: correct PHP totals, shipping rates per zone, free-shipping threshold at ₱2,000, COD fee + restriction, order emails (confirmation + shipped w/ tracking), stock decrement, and that a size-exchange request routes to hello@bactiveph.com. Document each result; fix any failure before launch.
```

### Step 9.5 — Content, links & schema validation [Agent]
```
Final content QA: proofread every page for typos, voice consistency, and the DO/DON'T guardrails (no stray discounts/countdowns). Check all internal/external links (no 404s). Confirm one H1 per page. Validate Product, Breadcrumb, LocalBusiness, and FAQ schema with Google's Rich Results Test. Confirm SEO titles/metas are set on every page and product. Confirm the email is hello@bactiveph.com everywhere (no "bactivepph"). Report a punch list.
```

### Step 9.6 — Photography gate [You — external]
Replace the interim supplier/AI images with the **branded Davao shoot** (per §A4.5: Filipina models, on-court + studio, 5–9 images per product incl. a Features & Details shot). This is the biggest premium lever — do not skip it for launch. The agent re-optimises and swaps images as they arrive.

### Step 9.7 — Security & backup verification [Agent]
```
Final security pass: run a Wordfence scan (clean), confirm Cloudflare WAF + rate-limits active, confirm DISALLOW_FILE_EDIT + FORCE_SSL_ADMIN set, 2FA on admin, and that UpdraftPlus has a recent successful remote backup that restores. Confirm staging is noindex/blocked and production is crawlable. Report.
```

---

## PHASE 10 — Launch & growth

**Objective:** go live cleanly, then grow with the channels and community plays the research identified.

### Step 10.1 — Go-live [Agent/Admin]
```
Launch checklist (run in order):
1) Final backup of production. 2) Deploy all approved staging changes to production via FTP (changed files only); import the Customizer export if needed. 3) Re-run the permalink flush + LiteSpeed purge + Cloudflare cache purge. 4) Set production "Discourage search engines" = OFF. 5) Switch PayMongo to LIVE keys; do one tiny real transaction and refund it. 6) Re-test checkout (GCash + COD) on production. 7) Submit sitemap in Search Console; request indexing of Home + key collections. 8) Confirm GA4 is receiving live events. 9) Final smoke test on mobile. 
Report go-live status with timestamps and the production commit hash.
```

### Step 10.2 — Launch announcement [You/Agent]
Announce via the email list (welcome segment), Instagram, Facebook, and the Davao pickleball community groups. On-brand, no discount required — lead with the story ("the Philippines' first premium women's pickleball brand, born in Davao") and The Court Edit. Draft copy can be generated in the brand voice; keep it calm and proud.

### Step 10.3 — Post-launch monitoring [Agent — schedulable]
Antigravity can set a recurring check (its scheduling skill): weekly pull of GA4 + Search Console highlights, Core Web Vitals, any Wordfence alerts, and stock levels, summarised to you. Watch first-week checkout funnels closely.

### Step 10.4 — Growth roadmap (first 90 days) [You — strategic]
Prioritised from the research:
1. **Google Business Profile momentum** — weekly posts, solicit reviews after every sale, respond within 48h. Fastest local ROI.
2. **Ambassador & community** — launch the **B Active Court Collective**; approach **Davao's #1-ranked Filipina player** (identified in research 01) and respected local club figures (confirm interest before any public claim). Outfit a few credible players.
3. **Tournament presence** — sponsor or set up a courtside booth at a Davao/Mindanao event in 2026; this is direct access to core buyers + earned media.
4. **TikTok Shop + Shopee** — open both as acquisition arms; **live-selling is the dominant fashion channel in PH**. Funnel buyers back to the brand site for full experience and data.
5. **Editorial PR** — pitch PH fashion/lifestyle outlets (Preview, Spot.ph, Mega, Pretty Me, ZALORA's blog) on the "first premium PH women's pickleball brand" story for high-authority backlinks.
6. **Content cadence** — 1–2 Journal posts/week working through the 18-topic list (research 06); refresh top performers quarterly.
7. **Email** — monthly Journal + new-arrivals send; nurture the 5%-off subscribers.
8. **Range expansion** — add depth (colourways, the `draft`-tab SKUs) based on what sells; consider seasonal "drops" framed editorially (never as clearance).

---

# APPENDICES

## Appendix 1 — Supporting kit & research index
In `./B Active Build Kit/`:
- `research/01_pickleball_ph_davao.md` — PH/Davao pickleball market, demographics, the women's gap, ambassador angle.
- `research/02_ph_ecommerce_payments_logistics.md` — market size, Shopee/TikTok/Lazada, GCash/COD/BNPL, couriers, free-ship norms, local competitors.
- `research/03_competitor_teardown.md` — Lululemon/Alo/Vuori/Beyond Yoga/Athleta/Stack/Pillar emulate-patterns + Shein/Fabletics/etc avoid-patterns + DO/DON'T + pickleball PDP conventions.
- `research/04_premium_dtc_ux.md` — homepage/PLP/PDP/cart specs, photography, CWV, accessibility, with hard numbers.
- `research/05_wp_woo_blocksy_tech.md` — performance, plugin stack, PayMongo/shipping, Blocksy child-theme patterns, security, AI-agent workflow.
- `research/06_seo_keyword_strategy.md` — keyword-to-page map, 18 blog topics, Davao local SEO, NAP, citations.
- `research/07_ph_pricing_benchmarks.md` — PH retail price bands by category & tier, margin norms, PH price psychology.
- `product-images/` — 45 supplier product photos mapped to SKUs (interim PDP imagery).
- `brand/` — logo concept SVGs (`bactive-logo-lockup.svg`, `-wordmark.svg`, `-monogram.svg`).
- Source data: `Barry Active.xlsx` (sourcing/pricing) and `B_Active_Content_Pack.docx` (original brief).

At the workspace root (alongside this guide):
- `00_START_HERE_Antigravity_Handoff.md` — file manifest + copy-paste prompt runbook (start here to build the design).
- `B_Active_Brand_Style_Guide.html` / `.md` — the visual brand book + editable twin (logo, palette, type, voice, components).
- `B_Active_Product_&_Pricing_Master.xlsx` — **single source of truth** for products, costs, recommended prices and live margins.
- `B_Active_Pricing_Strategy.md` — the pricing rationale and playbook for you as owners.

## Appendix 2 — Inputs still needed from you (collect before/while building)
| # | Input | Needed for | Default if you don't specify |
|---|---|---|---|
| 1 | Confirm final launch SKU list (prices already set) | Phase 4 | Recommended prices in the pricing spreadsheet |
| 2 | Final logo files (or approve the concept) | Phase 2 | Concept SVGs in `B Active Build Kit/brand/` |
| 3 | Brand fonts confirmation | Phase 2 | Fraunces + Inter |
| 4 | Exact store NAP (address, hours, phone/Viber) | Phases 5, 7 | Placeholders flagged |
| 5 | Final shipping rates + free-ship threshold | Phase 6 | ₱80/150/180 + free ≥ ₱2,000 |
| 6 | Tax/VAT registration status | Phase 6 | Treat prices as final/tax-inclusive |
| 7 | Real size-chart measurements (cm) | Phase 4/5 | Placeholder chart in §B5 |
| 8 | Care instructions per fabric | Phase 4 | Standard activewear care |
| 9 | Branded Davao photoshoot assets | Phase 9 gate | Interim supplier/AI images |
| 10 | Social handles (IG/FB/TikTok) | Phases 3, 10 | Placeholders |
| 11 | Ambassador confirmations (players) | Phase 10 | Hold public claims until confirmed |
| 12 | Legal review of Privacy/Terms/Returns | Phase 7 | AI drafts marked "review required" |

## Appendix 3 — Working with Antigravity: etiquette & recurring prompts
- **Every session starts** with: `Re-read AGENT_CONTEXT.md and task.md, tell me the current phase/step, and propose the next action.`
- **Before any change:** `Commit a pre-edit snapshot, make the change on staging, and show me a diff + screenshot before deploying to production.`
- **Use the right tools:** planning mode for multi-step work; **UI/UX Pro Max** targeting *e-commerce / fashion* with keywords *"premium, soft, feminine, editorial, minimal"* when generating component styles (but always reconcile to the tokens in §A4.3); **Chrome DevTools** for CWV/a11y; **image generation** for interim imagery only.
- **Keep it lean:** no page builders, one cache plugin, one SEO plugin. If a task seems to need a new plugin, ask first.
- **When unsure about a fact** (a product attribute, a venue, a price): mark `[confirm]` and ask — never invent.

## Appendix 4 — Glossary
- **PDP / PLP** — product detail page / product listing (collection) page.
- **CWV** — Core Web Vitals (LCP, INP, CLS): Google's UX speed metrics.
- **HPOS** — High-Performance Order Storage (WooCommerce's faster order tables).
- **NAP** — Name, Address, Phone (must be identical across the web for local SEO).
- **BOFU/MOFU/TOFU** — bottom/middle/top of funnel (purchase-intent → awareness).
- **COD / RTS** — cash on delivery / return-to-sender (failed COD delivery).
- **GBP** — Google Business Profile (the Maps/Search business listing).

---

### Final word
B Active is entering a real, fast-growing market with a genuine, defensible wedge: **the first premium, women-first, Asian-fit, Davao-born pickleball brand in the Philippines.** The product is already strong; this guide turns it into a brand experience that looks and feels worthy of the price — and avoids every fast-fashion tell that would undercut it. Build it in order, hold the premium line, and let the community do the rest.

*— End of guide —*




