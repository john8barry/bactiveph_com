# B Active — Brand Style Guide
**Version 1.1 · Typography approved 2026-09-06 · Davao City, Philippines · bactiveph.com**
*Tagline: "Sportswear for every move."*

> The shareable brand book. Hand this (or the visual `B_Active_Brand_Style_Guide.html`) to anyone who touches the brand — designers, photographers, printers, social managers, manufacturers. It's drawn from the Master Build Guide (§A3–A4) and expanded. The older logo concepts in `B Active Build Kit/brand/` are historical references; the existing production logo artwork remains unchanged by this typography decision.

---

## 1. Brand essence

**Who we are.** B Active is the Philippines' first premium women's pickleball brand — beautifully designed dresses, skorts, tops, bras, sets and paddles, cut for an **Asian fit**, performance-tested for the court, and priced so quality doesn't cost a fortune. Born in Davao, made for the women who actually play.

- **Mission** — make life easier for active women and pickleball players with stylish, high-quality outfits they can count on.
- **Vision** — become the #1 destination for pickleball outfits, and the activewear brand Filipinas reach for first.
- **Audience** — women ~20–50 who play pickleball; active women more broadly (yoga, training, everyday).
- **Personality** — premium, performance-ready, stylish, confident, encouraging. *A knowledgeable friend who plays, not a faceless store.*
- **Values** — Quality first · Accessible premium · Made to move · Made for us (Asian fit) · Community.
- **Positioning** — the look and feel of premium brands, at an accessible price, designed for an Asian fit. Below imports (Lululemon/Nike PH), clearly above mass-market.

---

## 2. Logo

Historical concept forms: **lockup**, **wordmark**, **monogram** (avatars, favicon, hangtags). These earlier concepts use a Fraunces wordmark and soft sage ring; they do not define the current website typography or replace the existing production logo. Files: `B Active Build Kit/brand/bactive-logo-{wordmark,monogram,lockup}.svg`.

> Keep the existing production logo artwork unchanged. These are **historical concept files**, not replacement logos. Do not recreate the production wordmark in Rajdhani or another font.

**Colourways**
- Charcoal `#2B2A28` on Court Ivory — default.
- Ivory `#FAF8F4` on Charcoal — reversed (dark backgrounds).
- Ink `#1C1B19` on Sagewood — accent surfaces.

**Clear space & minimum size.** Keep clear space of at least the height of the “B” on all sides. Minimum width ~90px digital / 22mm print (wordmark); 24px (monogram).

**Don'ts.** Don't stretch/condense/rotate · don't recolour outside the palette · no shadows/gradients/outlines · don't place on busy photos without a scrim · don't recreate the wordmark in another font · don't crowd it.

---

## 3. Colour

Calm, warm, feminine: off-white + charcoal foundation, with **sage** and **dusty rose** as sparing accents. Body text is always charcoal on ivory/white. Sage and rose are decorative — for accent *text*/links use Deep Sage or Rose Clay (both pass WCAG AA).

| Role | Name | HEX | Usage |
|---|---|---|---|
| Background | Court Ivory | `#FAF8F4` | Page background |
| Surface | Cloud White | `#FFFFFF` | Cards, drawers |
| Body text / primary button | Charcoal | `#2B2A28` | Body copy, primary CTA fill |
| Headings | Ink | `#1C1B19` | Display & headings |
| Primary accent | Sagewood | `#9CAE92` | Accent fills, icons, badges, hover |
| Accent text (AA) | Deep Sage | `#5E6E54` | Links, accent text on light bg |
| Secondary accent | Dusty Rose | `#D8A7A0` | Highlights, decorative |
| Rose text (AA) | Rose Clay | `#A96E66` | Rose accent text on light bg |
| Divider | Greige | `#E6DFD5` | Borders, hairlines |
| Secondary text | Stone | `#6E675F` | Captions, meta |
| Sale (rare) | Clay Red | `#A9544A` | Only inside a “Court Closings” area |

**Contrast rule:** ≥ 4.5:1 for text; never set body copy in Sage/Rose; never pure black (`#000`).

---

## 4. Typography

**Permanent website standard, approved by John Barry on 2026-09-06.** Rajdhani's angular letterforms complement the existing athletic logo; Inter keeps longer text and shopping controls readable. This replaces the earlier Fraunces website heading direction and optional alternative pairing.

- **Headings, product titles & navigation: Rajdhani Semibold (600).** Use for H1–H6, product titles, and desktop/mobile navigation. Preserve each component's existing size, spacing, casing and layout unless a separate change is approved.
- **Body & UI: Inter (400/500/600).** Use for body copy, forms, prices, buttons, labels and other utility text.
- **Logo:** keep the existing artwork unchanged. Do not redraw or typeset the logo in Rajdhani. Do not apply faux italic styling or condensed type to all text.

| Style | Font | Size | Notes |
|---|---|---|---|
| H1 / Display | Rajdhani 600 | 3.0rem | line-height 1.12 |
| H2 | Rajdhani 600 | 2.25rem | section titles |
| H3 | Rajdhani 600 | 1.5rem | sub-sections |
| H4–H6 / product title | Rajdhani 600 | Component-specific | preserve responsive sizing |
| Desktop / mobile navigation | Rajdhani 600 | Component-specific | preserve casing and layout |
| Body | Inter 400 | 1.0rem (16px) | line-height 1.6 · max ~68ch |
| Forms / prices | Inter 400/500/600 | Component-specific | preserve existing weight |
| Eyebrow / label | Inter 500 | 0.72rem | UPPERCASE · letter-spacing .18em · Deep Sage |
| Button | Inter 500 | 0.8rem | UPPERCASE · letter-spacing .08em |

**Implementation:** use `--bactive-font-head: 'Rajdhani', 'Inter', sans-serif` and retain `--bactive-font-body: 'Inter', system-ui, sans-serif`. Self-host Rajdhani 600 Latin and Latin Extended WOFF2 files with `font-display: swap`. The child theme's `assets/fonts/rajdhani-OFL.txt` records the SIL Open Font License; retain it with the font assets. Keep the existing self-hosted Inter weights. The visual guide uses the same local assets.

**Decision record:** treat this pairing as the continuing website default. A future typography change requires a new explicit brand decision; do not restore Fraunces or the former Cormorant Garamond/Jost alternative from older build notes.

---

## 5. Voice & tone

Friendly and motivating; expert and no-nonsense; confident but never boastful. Specific and a little playful about the sport. We sound like a knowledgeable friend who plays — never a hype machine.

- **We say:** premium · quality · accessible · fair · value · made to move · Asian fit · court-ready · buttery-soft · designed for you.
- **We don't say:** cheap · sale!! · hurry · limited time · lowest price · #1 best · ALL-CAPS shouting · exclamation spam.

**Off-brand:** “🔥 MEGA SALE!! Cheapest pickleball dress online — BUY NOW!!! ⏰”
**On-brand:** “Move freely, look effortless. The Court Dress pairs a flattering silhouette with serious performance — built-in shorts, a ball pocket, and buttery-soft CourtSoft™ fabric.”

---

## 6. Naming systems

- **Fabrics:** **CourtSoft™** (buttery-soft, 4-way stretch, squat-proof) · **BreezeKnit™** (lightweight, breathable, wicking) · **SecondSkin™** (seamless, no-dig).
- **Collections:** **The Court Edit** (hero dresses) · **The Rally Set** (matching sets) · **Everyday Active** (tanks, bras, leggings, skorts).
- **Colour vocabulary:** Court Ivory · Midnight · Onyx · Sakura · Powder · Sagewood · Wisteria · Stone · Apricot · Almond · Bloom · Clay Red. *(Never “green/pink/white”.)*

---

## 7. Photography & art direction

Our biggest premium signal. Soft, warm, natural light; consistent grade; **real Filipina models across body types** (this *is* the Asian-fit story). Two modes: clean studio on warm off-white, and on-court lifestyle in Davao.

- **Do:** natural light · soft warm tones · mid-motion + at-rest · 5–9 images per style (incl. a Features & Details shot) · court-to-café lifestyle · generous negative space.
- **Don't:** neon/over-sharpened edits · harsh flash-only · busy backgrounds · exclusively Western casting · body-over-sport posing · 1–2 images per product.
- **Per-product shot list:** front · back · side (on-model) · fabric/seam detail · pocket/built-in-shorts detail · in-motion action · lifestyle · flat-lay.

---

## 8. Components (digital)

- **Buttons:** primary = Charcoal fill + Ivory text, 2px radius, uppercase, hover → Deep Sage; secondary = Charcoal outline.
- **Product cards:** 3:4 portrait image, 6px radius, hairline Greige border, name in Rajdhani 600, price, colour swatches, sparing Sage/Rose badge; 3-up desktop / 2-up mobile; generous whitespace.
- **Badges:** Sage “New”, Rose “Best Seller” — used sparingly (1–2 per page).
- **Announcement bar:** Court Ivory bg, Charcoal text, calm service message — no countdowns.
- **Icons:** professional SVG line icons (Lucide/Heroicons), 24px, consistent. No emoji in UI.
- **Motion:** subtle, 150–250ms fades/lifts. Focus-visible ring = 2px Deep Sage. Tap targets ≥ 44px.

---

## 9. Applications

**Historical non-website concepts.** The examples below preserve the earlier print, social and email direction for reference. The 2026-09-06 approval establishes website typography only; it does not authorize replacement logos or a rollout to these other channels.

- **Hangtag:** monogram + “B Active” (Fraunces) + “Quality you can feel” on Cloud White, Greige edge.
- **Social:** monogram as avatar; posts lead with editorial imagery + a Fraunces line; eyebrow in Sage.
- **Email signature:** `Name · B Active` (Fraunces) / `Premium Pickleball & Activewear · Davao City` / `bactiveph.com · hello@bactiveph.com`.
- **Packaging:** Court Ivory mailer/tissue, charcoal monogram stamp, “Thank you for choosing quality.” card.

---

## 10. Premium guardrails (quick reference)

| Area | Do (premium) | Don't (fast-fashion) |
|---|---|---|
| Sale language | Name it (“Court Closings”); rare | “FLASH SALE”, timers, strike-throughs |
| Colour names | Court Ivory, Sagewood, Sakura | “Green”, “Pink”, “White” |
| Imagery | 5–9 per product, lifestyle + detail | 1–2 studio-only |
| Urgency | None (or honest low-stock) | “X viewing”, “sold in 24h” |
| Voice | Calm, specific, playful | Exclamatory, ALL CAPS |
| Membership | Optional rewards, no gating | Pay-to-unlock VIP wall |

**Tagline:** *Sportswear for every move.*
**Key messages:** Premium quality, comparable to the brands you love · Designed for pickleball and the active everyday · Looks great, performs better (moisture-wicking, four-way stretch, pockets) · Fair, accessible prices · Designed with an Asian fit.

---

*Companion files: `B_Active_Brand_Style_Guide.html` (visual) · `B_Active_Master_Build_Guide.md` · `B_Active_Pricing_Strategy.md` · logo SVGs in `B Active Build Kit/brand/`. This guide is the single source of truth for brand expression; keep it in sync with the design system in the Master Build Guide (§A4).*
