# B Active Agent Context

**Workflow:**
1. Commit pre-edit snapshot locally.
2. Make changes locally (acts as staging).
3. Test locally.
4. Deploy changed files to production via FTP (using scripts).
5. Flush LiteSpeed cache.
6. Commit post-edit locally.

## Project Details
- **Project:** B Active (bactiveph.com), premium women's pickleball/activewear, Davao City PH, currency PHP.
- **Stack & Hosting:** WordPress, WooCommerce 10.8.x, Blocksy + blocksy-child, Cloudflare, Namecheap/LiteSpeed, FTP/cPanel deploy.
- **Typography:** Fraunces (headings) + Inter (body), self-hosted via OMGF.

## Design Tokens (Canonical Palette)
```css
:root {
  /* Colour */
  --bactive-ivory: #FAF8F4;
  --bactive-white: #FFFFFF;
  --bactive-charcoal: #2B2A28;
  --bactive-ink: #1C1B19;
  --bactive-sage: #9CAE92;
  --bactive-sage-deep: #5E6E54;
  --bactive-rose: #D8A7A0;
  --bactive-rose-deep: #A96E66;
  --bactive-greige: #E6DFD5;
  --bactive-stone: #6E675F;
  --bactive-sale: #A9544A;

  /* Type */
  --bactive-font-head: 'Fraunces', Georgia, serif;
  --bactive-font-body: 'Inter', system-ui, sans-serif;
  --bactive-fs-base: 16px;

  /* Space (8pt grid) */
  --bactive-space-1: 8px;
  --bactive-space-2: 16px;
  --bactive-space-3: 24px;
  --bactive-space-4: 32px;
  --bactive-space-6: 48px;
  --bactive-space-8: 64px;
  --bactive-space-12: 96px;

  /* Radius / shadow / motion */
  --bactive-radius-btn: 2px;
  --bactive-radius-card: 6px;
  --bactive-shadow-card: 0 1px 3px rgba(28,27,25,.06), 0 8px 24px rgba(28,27,25,.05);
  --bactive-ease: 0.25s cubic-bezier(.4,0,.2,1);

  /* Container */
  --bactive-container: 1280px;
  --bactive-gutter: clamp(16px, 4vw, 48px);
}
```

## Voice Rules & Guardrails
- **Voice:** Friendly, motivating, expert, no-nonsense. Confident but never boastful.
- **Do:** Use words like premium, quality, accessible, fair, value, made to move, Asian fit, court-ready, buttery-soft.
- **Don't:** Never use "cheap", "sale!!", "hurry", "limited time", or ALL-CAPS shouting.
- **UI Guardrails:** No discounts, countdown timers, or fast-fashion patterns (e.g., "X viewing").
- **Colors:** Court Ivory, Midnight, Onyx, Sakura, Powder, Sagewood, Wisteria, Stone, Apricot, Almond, Bloom, Clay Red. (Never "green/pink/white").

## Active Plugins
(To be updated as installed)
- WooCommerce
- Blocksy Companion Pro
- LiteSpeed Cache
- ShortPixel Image Optimizer
- OMGF
- Wordfence
- Limit Login Attempts Reloaded
- UpdraftPlus
- PayMongo (planned)
- Rank Math SEO (planned)

## WooCommerce Attributes
- **Colour** (Swatch)
- **Size** (S, M, L, XL)
- **Features** (Built-in shorts, Ball pocket, Built-in bra, Pockets, UPF50+, Squat-proof)

## Never Do List
- NO core WordPress edits.
- NO parent Blocksy theme edits.
- NO live DB DROP/DELETE.
- NO disabling security.
- NO production deploy of checkout changes without a staging test.
