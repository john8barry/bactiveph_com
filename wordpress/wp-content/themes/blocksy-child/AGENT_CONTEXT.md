# B Active Agent Context

## Golden Rules
- **NEVER** touch WordPress core or the parent Blocksy theme.
- **STAGE FIRST**: Make changes on staging before production.
- **COMMIT ALWAYS**: Commit a pre-edit snapshot, work on staging, then deploy changed files only, then commit again.

## Design Tokens (DO NOT USE HARDCODED HEX VALUES)
Reference these variables in CSS:
```css
:root {
  /* Colour */
  --bactive-ivory:#FAF8F4;  --bactive-white:#FFFFFF;
  --bactive-charcoal:#2B2A28; --bactive-ink:#1C1B19;
  --bactive-sage:#9CAE92; --bactive-sage-deep:#5E6E54;
  --bactive-rose:#D8A7A0; --bactive-rose-deep:#A96E66;
  --bactive-greige:#E6DFD5; --bactive-stone:#6E675F;
  --bactive-sale:#A9544A;
  
  /* Type */
  --bactive-font-head:'Rajdhani','Inter',sans-serif;
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

## Typography Rules
- Permanent website typography approved by John Barry on 2026-09-06; recorded in `Buildout_Resources/B_Active_Brand_Style_Guide.md` and its HTML companion.
- H1–H6, product titles, desktop navigation and mobile navigation: Rajdhani Semibold (600), via `--bactive-font-head`. Preserve existing component sizing and casing.
- Body copy, forms, prices, buttons, labels and utility text: existing Inter (400/500/600).
- Self-host Rajdhani 600 Latin and Latin Extended WOFF2 with `font-display: swap`; retain `assets/fonts/rajdhani-OFL.txt` (SIL Open Font License).
- Keep existing logo artwork unchanged. No faux italic treatment and no condensed font applied to all text.
- This supersedes earlier Fraunces website headings and optional alternate pairings. Do not restore old typography from historical build notes without a new explicit brand decision.
- Eyebrows & Buttons: Inter, UPPERCASE, letter-spacing: 0.08em (or 0.18em for eyebrows).
- Colors: Charcoal (#2B2A28) for text; Ivory/White for background. Deep Sage / Rose Clay for accent text.
- No discounts, no countdown timers, no fast-fashion patterns.
