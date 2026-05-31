# 00 — START HERE · Antigravity Hand-off
### How to build the B Active website **design** with Antigravity (Gemini 3.1 Pro, High)
Version 1.0 · companion to `B_Active_Master_Build_Guide.md`

> **What this is.** The shortest path from "near-empty WordPress site" to "designed, branded, premium storefront." It tells you (1) exactly which documents to give Antigravity, and (2) the exact prompts to paste, in order, with what to check after each. Copy each grey block verbatim. The text outside the blocks is for *you*, not the agent.
>
> **Scope.** This runbook covers the **design build** (Phases 0–3 + 5 of the Master Build Guide): foundations, the brand design system, the logo, navigation, and the look of the homepage and key page templates. Commerce, products, SEO and launch come after — see "What's next" at the end.
>
> **Golden rules (true for every step):** **a fresh backup must exist before any change** (UpdraftPlus → DB + files → remote — no backup, no change); work on a **staging** copy first and only in `wp-content/themes/blocksy-child` (never WordPress core or the parent theme); **never run destructive file deletions on production via FTP** (clean up on staging or via WP-CLI, then `wp core verify-checksums`); never delete a user account without a confirmed alternate admin; **commit to Git** before and after each change; and match `B_Active_Brand_Style_Guide` exactly. Approve each terminal/FTP command deliberately. See Master Build Guide **Phase 3.5 — Recovery & Safety Gate**.

---

## PART 1 — Load these documents into Antigravity

Put all of this in the **one workspace/folder Antigravity can access** (the same place as your site code). Antigravity reads files from disk — you do **not** paste these into chat.

| # | Put this in the workspace | Why the design build needs it |
|---|---|---|
| ✅ | **The `wordpress/` site repo** (your actual WordPress install with the `blocksy-child` theme) | This is what it edits |
| ✅ | **`B_Active_Master_Build_Guide.md`** | The plan + §A4 design system, §A5 IA, §B page content |
| ✅ | **`B_Active_Brand_Style_Guide.md`** and **`B_Active_Brand_Style_Guide.html`** | The canonical look: logo, exact palette/hex, type, components, voice |
| ✅ | **`B Active Build Kit/brand/`** (the 3 logo SVGs) | The logo to install |
| ✅ | **`B Active Build Kit/product-images/`** | Real product photos to populate homepage/shop while designing |
| ➕ | `B_Active_Product_&_Pricing_Master.xlsx` + `B_Active_Pricing_Strategy.md` | Product names/prices for realistic design; full use comes in the product phase |
| ➕ | `B Active Build Kit/research/` (7 reports) | Background/UX evidence; not essential to start |

You'll also need, ready to paste when asked: **WordPress admin login, cPanel/FTP credentials** (Namecheap), and your **Cloudflare API token**. (Have these from your tech-stack `.env`.)

---

## PART 2 — The design build, step by step

Run the steps **in order**. Within each: read the *What it does*, paste the **prompt**, then do the *Check* before moving on.

---

### ▶ STEP D0 — Kickoff & plan (no changes yet)
**What it does:** points Antigravity at all the docs, has it confirm the stack and produce its own plan. **Uses:** everything in Part 1.

```
You are the lead engineer building the B Active website. This is a WordPress + WooCommerce + Blocksy (blocksy-child) site, on Namecheap/LiteSpeed behind Cloudflare. Before doing ANYTHING, read:
1) B_Active_Master_Build_Guide.md — the source of truth (esp. §A4 design system, §A5 information architecture, Part B page content, Part C phases).
2) B_Active_Brand_Style_Guide.md AND open B_Active_Brand_Style_Guide.html — the canonical brand book (logo, exact palette + hex, Fraunces/Inter type, components, voice).
3) The logo SVGs in "B Active Build Kit/brand/", the product photos in "B Active Build Kit/product-images/", and the repo's tech_stack.md / agent_capabilities.md / design_capabilities.md.

Then, in planning mode, produce implementation_plan.md and task.md covering the DESIGN build only (Master Build Guide Phases 0–3 and 5: foundations, design system, logo, navigation, homepage and key page templates). List anything you cannot yet access (FTP, WP admin, Cloudflare token).

Hard rules for the whole project: work ONLY in wp-content/themes/blocksy-child; never touch WordPress core or the parent Blocksy theme; test on staging before production; commit to Git before and after every change; reference the design tokens in Master Build Guide §A4.3 (use the CSS variables, never hardcode hex); match the brand book and its DO/DON'T guardrails (no discounts, countdowns, or fast-fashion patterns).

Do NOT make any changes yet. Show me the plan and the access gaps, and wait for my approval.
```
**Check:** it correctly identifies the stack, lists a sensible design plan, and names what access it needs. Give it the credentials it asks for.

---

### ▶ STEP D1 — Safe baseline (so design work can't break anything)
**What it does:** staging copy + Git + clean URLs + removes the default WordPress clutter. **Uses:** Master Build Guide Phase 0.3 and Phase 1.1–1.2.

```
Set up a safe baseline before any design work (propose the terminal/FTP commands for my approval):
1) Create a staging copy of the site (e.g. staging.bactiveph.com via cPanel, cloned with UpdraftPlus), password-protected and set to noindex. If a subdomain isn't possible, propose InstaWP.
2) Initialise Git inside wp-content/themes/blocksy-child and commit "Initial child theme state". From now on: commit a pre-edit snapshot, work on staging, then deploy changed files only, then commit again.
3) Settings → Permalinks → "Post name" (/%postname%/); set WooCommerce product base to /product/ and category base to /collections/. Flush rewrite + LiteSpeed cache.
4) Delete the default "Hello world!" post, the sample comment, and "Sample Page". Set Site Title "B Active" and tagline "Women's Pickleball Apparel & Activewear — Philippines" (timezone Asia/Manila).
5) Create AGENT_CONTEXT.md in the child theme root with the brand palette/tokens (Master Build Guide §A4.3), the Fraunces+Inter type rules, and the "never touch core/parent, stage first, commit always" rules. Commit it.
Report the staging URL and the initial commit hash.
```
**Check:** staging URL works, Git is initialised, and visiting a page shows clean `/...` URLs (not `/index.php/...`).

---

### ▶ STEP D2 — Install the brand design system
**What it does:** makes the whole site inherit the B Active look automatically. **Uses:** Brand Style Guide + Master Build Guide §A4.1–A4.3 (and §6.3 of `research/05` for the enqueue snippet).

```
Install the B Active design system on staging, matching B_Active_Brand_Style_Guide.html exactly.
A) In Appearance → Customize → Blocksy global defaults:
   - Colour palette: Charcoal #2B2A28 (primary/buttons & body text), Sagewood #9CAE92 (accent), link colour Deep Sage #5E6E54, headings Ink #1C1B19, site background Court Ivory #FAF8F4, borders Greige #E6DFD5. Record which --theme-palette-color-N is which in AGENT_CONTEXT.md.
   - Typography: body = Inter (400/500/600); headings = Fraunces (500/600). Type scale per Master Build Guide §A4.2 (H1 3rem … body 1rem; line-height headings 1.15 / body 1.6). Eyebrows/buttons = Inter UPPERCASE, letter-spacing .08em.
   - WooCommerce: product image ratio 3:4 portrait; 3 columns desktop / 2 mobile; card hover swaps to 2nd image.
   Export the Customizer settings and commit the export file.
B) In wp-content/themes/blocksy-child: create assets/css/custom.css starting with the full :root design-token block from Master Build Guide §A4.3, then base component styles that REFERENCE the tokens (buttons: primary = charcoal fill/ivory text, hover Deep Sage; secondary = charcoal outline; product cards; links in Deep Sage; focus-visible ring 2px Deep Sage; announcement bar; 8pt spacing). Create assets/js/custom.js (empty). Enqueue both from functions.php with filemtime() versioning (see research/05 §6.3). 
Self-host Fraunces + Inter (OMGF plugin) so there's no external font request. Test on staging that headings render in Fraunces, body in Inter, and buttons match the brand book. Commit "feat: brand design system + tokens".
```
**Check:** on staging, headings are Fraunces, body is Inter, buttons are charcoal→sage on hover, background is Court Ivory. It should look like the brand book.

---

### ▶ STEP D3 — Logo & favicon
**What it does:** installs the provided logo concept. **Uses:** `B Active Build Kit/brand/` SVGs + Brand Style Guide §2.

```
Install the B Active logo from the provided concept files in "B Active Build Kit/brand/":
- Header logo: use bactive-logo-lockup.svg (or bactive-logo-wordmark.svg if the lockup is too wide on mobile).
- Mobile logo + favicon + social icon: use bactive-logo-monogram.svg. Export PNGs at 2×/3× and a 512px favicon from the SVG as needed.
- Set these in Customize → Site Identity, replacing the current bactive_logo.jpg.
Respect the clear-space and minimum-size rules in B_Active_Brand_Style_Guide.md §2. These are concept files — keep them easy to swap if I provide refined logo files later. Commit "feat: logo + favicon".
```
**Check:** the lockup shows in the header on desktop, the monogram on mobile, and the favicon is the "B" monogram.

---

### ▶ STEP D4 — Header, navigation & footer
**What it does:** builds the site chrome and menus. **Uses:** Master Build Guide §A5 (IA) + §B0 microcopy.

```
Build the header and footer with Blocksy's Header/Footer builders, per Master Build Guide §A5.2–A5.3:
- Announcement bar (Court Ivory bg, charcoal text, dismissible) rotating the two calm messages in §B0 — no countdowns.
- Header: logo left; centre menu — Shop (simple dropdown listing the 7 categories + "Shop All" + one featured "The Court Edit" tile), Pickleball (→ /court-edit), About, Journal, Our Store; right icons — Search, Account, Wishlist (heart), Cart (with slide-out drawer). Sticky on scroll. Mobile = hamburger with the same shallow tree, 44px tap targets.
- Footer: 4 columns (Shop / Help / Brand / Stay-in-the-loop newsletter) + a bottom row with payment icons (GCash, Maya, Visa, Mastercard, COD), "Ships nationwide via J&T & Ninja Van", © 2026 B Active, Privacy · Terms.
Create the menus and assign them. Keep it shallow and premium (4–6 nav items). Commit "feat: header, nav, footer".
```
**Check:** the nav matches §A5.2, the cart opens a drawer, and the mobile hamburger works with comfortable tap targets.

---

### ▶ STEP D5 — Homepage design
**What it does:** builds the homepage section-by-section. **Uses:** Master Build Guide §B1 (copy) + §A4 (look) + product photos.

```
Build the homepage on staging using Gutenberg / Blocksy blocks (NOT a page builder), in this exact order with the copy from Master Build Guide §B1:
1 Hero (full-bleed lifestyle image, eyebrow + H1 + sub + ONE "Shop The Court Edit" CTA) → 2 Featured: The Court Edit → 3 Bestsellers (4–6) → 4 Why B Active (4 icons) → 5 Brand-story teaser → 6 The Asian-fit promise → 7 Reviews/community → 8 Newsletter → 9 Footer.
Use the design tokens and lots of Court Ivory whitespace; Fraunces headings; one CTA per section; subtle motion only. For imagery, use photos from "B Active Build Kit/product-images/" and, where a lifestyle hero is needed, generate an on-brand placeholder with your image tool (soft, warm, Filipina players on a Davao court — clearly a placeholder to replace with the real shoot). Make it mobile-first: verify hero, 2-up grids, and CTAs at 380px. Commit "feat: homepage", and show me desktop + mobile screenshots.
```
**Check:** the homepage matches §B1's section order and feels calm/premium; screenshots look right on desktop and mobile.

---

### ▶ STEP D6 — Key page templates (look & layout)
**What it does:** designs the collection (shop) page, the product page (PDP) template, and the About page — the *design*, with a few sample products so it looks real. **Uses:** Master Build Guide §B4.1 (PDP template), §B3 (collection), §B2 (About), §A4.4 components.

```
Design the core page templates on staging (layout & styling — full catalogue comes later):
A) Collection / shop page: 3-up desktop / 2-up mobile product grid with generous whitespace, premium product cards (3:4 image, Fraunces name, price, colour swatches, sparing "New/Best Seller" badge), and filters for Colour/Size/Features (Blocksy's built-in filters or the free "Filter Everything" plugin). Add a short intro-copy area at the top (placeholder from §B3).
B) Product page (PDP) template per Master Build Guide §B4.1: large gallery (5–9 images) with a "Features & Details" shot, colour/size swatches under the title, sticky Add-to-Cart, a "Size guide" link by the size selector, accordions (Description · Features & Fit · Shipping & Returns · Fabric & Care), model height/size line, and a "Complete the look" row. Populate it with ONE sample product (The Court Dress, ₱1,950) using photos from the kit so the design is realistic.
C) About page per §B2 (H1 "It started with a love for the game.", the story copy, the 5 values strip, a brand-story image, closing "Shop The Court Edit" CTA).
Match the brand book throughout. Commit "feat: collection + PDP + about templates" and show screenshots.
```
**Check:** the PDP has the gallery, swatches, sticky add-to-cart and accordions; the shop grid is 3-up/2-up and premium; About reads well.

---

### ▶ STEP D7 — Design QA
**What it does:** confirms the design is responsive, accessible, fast and on-brand before you sign off. **Uses:** Master Build Guide Phase 9.1–9.3 + the brand book.

```
Run design QA on staging and fix what you find:
- Brand match: compare every page to B_Active_Brand_Style_Guide.html — colours, Fraunces/Inter, spacing, components. Fix drift.
- Responsive: check 380px mobile, tablet, desktop on Chrome, Safari, Firefox — header/hamburger, hero, 2-up grids, PDP gallery + sticky Add-to-Cart, cart drawer. No layout breaks, no horizontal scroll.
- Accessibility (WCAG AA): text contrast ≥ 4.5:1 (Sage/Rose only as decoration; accent text uses Deep Sage/Rose Clay), alt text on images, keyboard-navigable menus/drawer, visible focus rings, 44px tap targets.
- Performance: images optimised (WebP/AVIF) with width/height set (no layout shift); fonts self-hosted. Use Chrome DevTools to confirm LCP < 2.5s, CLS < 0.1 on the homepage.
Report a punch list with before/after screenshots, then deploy the approved design from staging to production (changed files only) and purge LiteSpeed + Cloudflare cache.
```
**Check:** the punch list is resolved, it looks identical across devices/browsers, and the design is live on bactiveph.com.

---

## PART 3 — What's next (after the design looks right)
Continue in `B_Active_Master_Build_Guide.md` with the remaining phases, in order:
- **Phase 4** — build the full product catalogue (use the **Product Master** tab of `B_Active_Product_&_Pricing_Master.xlsx` for names, prices, colours, and copy).
- **Phase 6** — payments (PayMongo: GCash/Maya/cards + COD), shipping zones, cart/checkout.
- **Phase 7** — SEO (Rank Math, schema, titles/meta), GA4, Google Business Profile for Davao.
- **Phase 8–10** — Journal/content, reviews, pre-launch QA, and launch.

## Working tips with Antigravity
- One step at a time; let it finish and show screenshots before the next.
- It will propose terminal/FTP commands — **read each, approve only what matches the step.**
- If something looks off, just say so plainly ("the hero CTA should be charcoal, not sage; the spacing is too tight") — it will fix and re-deploy.
- Each new session, start with: *"Re-read AGENT_CONTEXT.md and task.md, tell me the current step, and propose the next action."*
- Keep production safe: nothing ships to bactiveph.com until it's passed on staging.
