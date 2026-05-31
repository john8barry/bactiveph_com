# WordPress / WooCommerce / Blocksy Technical Best Practices
## B Active — Technical Research Report (2025–2026)

> **Scope:** Premium women's activewear/pickleball store on WordPress + WooCommerce 10.8.x + Blocksy child theme, hosted on Namecheap cPanel (LiteSpeed server), behind Cloudflare CDN/WAF, targeting the Philippines (Davao City). Currency: PHP (₱).

---

## 1. Performance & Core Web Vitals

### 1.1 Server Stack Reality Check

Namecheap shared/reseller hosting on `premium343.web-hosting.com` runs **LiteSpeed Web Server** (not Apache), which is confirmed by Namecheap's own knowledge base. This is important: **LiteSpeed Cache plugin is the native, highest-performance caching solution** for this server — it communicates directly with the server-level cache, unlike WP Rocket or W3 Total Cache which operate purely in PHP.

**Caching recommendation:** Install **LiteSpeed Cache** (free, wordpress.org/plugins/litespeed-cache/). Do NOT add WP Rocket or W3TC alongside it — one cache plugin only.

Real-world benchmarks show LiteSpeed Cache hits TTFB below 150ms and consistent PageSpeed scores of 98+/100 on mobile when properly configured.

### 1.2 LiteSpeed Cache Configuration for WooCommerce

Key settings (WooCommerce-aware mode auto-appears when WooCommerce is active):

| Setting | Value |
|---|---|
| Cache > Enable Cache | ON |
| Cache > Cache Logged-in Users | OFF |
| Cache > Cache Commenters | OFF |
| WooCommerce > Separate Cache for Cart | ON |
| WooCommerce > Cache Cart/Checkout | OFF (never cache) |
| Object Cache | Redis (enable via cPanel > LiteSpeed Redis Cache Manager first) |
| Browser Cache | ON |
| CSS/JS Minify & Combine | ON (test thoroughly after enabling) |
| QUIC.cloud CDN | Optional free tier for static assets |
| Image Lazy Load | ON |
| Responsive Placeholder | ON |

**Critical exclusions** — add to "Do Not Cache URIs":
- `/cart/`, `/checkout/`, `/my-account/`, `/wp-admin/`, `/wp-login.php`

Note: Namecheap cPanel Redis is available on some plans. If unavailable, use Memcached or skip object cache for now — page cache alone delivers most gains.

### 1.3 Cloudflare Configuration (2025 Rules)

**Page Rules deprecated** — as of January 6, 2025, Cloudflare no longer allows creating new Page Rules. Use **Cache Rules** instead.

**APO (Automatic Platform Optimization):**
- Available on Cloudflare Pro ($20/month) or as $5/month add-on on Free.
- Install the official Cloudflare WordPress plugin and connect via API token.
- APO caches HTML at Cloudflare edge for logged-out visitors; cache purges automatically on publish within ~30 seconds.
- **Do NOT use APO and "Cache Everything" rules simultaneously** — they conflict and cause stale carts.

**WooCommerce-specific Cache Rules (manual, Free plan):**
- Cache everything except: `/cart/*`, `/checkout/*`, `/my-account/*`, any URL containing `woocommerce_items_in_cart` or `woocommerce_cart_hash` cookies.
- Cookie-based bypass: set bypass rule for cookies `wordpress_logged_in_*`, `woocommerce_cart_hash`, `woocommerce_items_in_cart`, `wp_woocommerce_session_*`.

**Cloudflare settings for WordPress:**
- SSL/TLS: Full (Strict)
- Auto Minify: **Disable** (deprecated and removed from Cloudflare UI by mid-2025; let LiteSpeed Cache handle minification)
- Rocket Loader: **Disable** (conflicts with WooCommerce JS)
- Browser Cache TTL: 1 year for static assets
- Security Level: Medium
- Bot Fight Mode: ON
- Managed WAF Rules: Enable WordPress ruleset

**Security rule suggestions:**
- Block `/wp-login.php` access from countries outside Philippines (or use rate limiting rule: >5 requests/min = challenge)
- Challenge: `URI contains /xmlrpc.php` → Block

### 1.4 Image Optimization

**Recommended: ShortPixel Image Optimizer** (shortpixel.com)
- Best compression rates in independent 2025 testing (54% JPEG reduction vs 20–40% for competitors)
- Supports WebP AND AVIF conversion; serves AVIF to capable browsers, falls back to WebP
- Free tier: 100 images/month (sufficient for initial setup); paid from ~$4.99/month
- WooCommerce compatible; processes existing and new uploads automatically

**Alternative: Imagify** — strong AVIF support, three compression modes (lossy/lossless/smart). Good choice if ShortPixel quota is restrictive.

**WordPress core lazy loading:** WordPress 5.5+ enables native `loading="lazy"` on images. LiteSpeed Cache adds additional lazy loading for JS/CSS. Do not double-install — one lazy-load solution only.

**Font loading strategy:**
- In Blocksy Customizer > Typography: use Google Fonts with `display=swap` (Blocksy handles this).
- For performance, consider self-hosting fonts using the **OMGF (Optimize My Google Fonts)** plugin — downloads fonts to your server, eliminating the Google Fonts DNS lookup. This can save 200–400ms on mobile in the Philippines.
- Limit to 2 font families maximum; 2–3 weights each.

### 1.5 Plugin Bloat Reduction

Blocksy already includes: mega menu, header builder, footer builder, WooCommerce shop grid/filters (Pro), sticky header, scroll-to-top. Do not add separate plugins for these.

Plugins to actively avoid:
- **Elementor / Divi / WPBakery** — redundant with Blocksy/Gutenberg; massive performance hit
- **Jetpack** — bloated; use individual focused plugins instead
- **Contact Form 7 + CAPTCHA plugins** — use WPForms Lite or Fluent Forms (lighter)
- **Revolution Slider / LayerSlider** — use Blocksy/Gutenberg native blocks
- **Multiple SEO plugins** — pick ONE (Rank Math)
- **Multiple cache plugins** — pick ONE (LiteSpeed Cache)

---

## 2. Essential Plugin Stack (Recommended — Lean)

### 2.1 Core Plugin List

| Category | Plugin | Notes |
|---|---|---|
| **Cache** | LiteSpeed Cache (free) | Native LiteSpeed server integration |
| **SEO** | Rank Math SEO (free) | WooCommerce schema included free; see §5 |
| **Image Opt** | ShortPixel Image Optimizer | WebP/AVIF; paid after 100/month |
| **Analytics** | MonsterInsights Lite (free) | GA4 + WooCommerce e-commerce tracking |
| **Email Marketing** | Omnisend (free tier) | WooCommerce-native; abandoned cart automations |
| **Reviews/UGC** | Customer Reviews for WooCommerce (free, by Conversios) | Photo reviews, verified purchaser badges, email request automation |
| **Font Optimization** | OMGF – Host Google Fonts Locally (free) | Self-host fonts; remove Google Fonts DNS lookup |
| **Security** | Wordfence (existing) + Limit Login Attempts Reloaded (existing) + WPS Hide Login (existing) + Akismet (existing) | See §7 for config |
| **Backup** | UpdraftPlus (existing) | Configure remote backup to Google Drive |
| **Payments** | Payments via PayMongo for WooCommerce (free) | See §3 |
| **Shipping** | Flexible Shipping by Octolize (free) | Table-rate + free threshold; see §4 |
| **HPOS / Performance** | WooCommerce built-in HPOS | Enable in WooCommerce > Settings > Advanced > Features |
| **XML Sitemap** | Rank Math (built-in) | Disable any other sitemap generator |

### 2.2 Blocksy Pro — Is It Worth It?

**DECISION UPDATE (supersedes this section):** Staying on FREE Blocksy; NOT buying Pro (owner prefers a free, long-term stack). Shop Extra features are instead provided by free plugins + child-theme code — see Master Build Guide Step 1.6. Original analysis kept for reference.

**Original note: Yes — highly recommended for B Active.** Blocksy Pro Personal (~$49/year for 1 site) unlocks the **Shop Extra** WooCommerce extension which includes:

- **Variation Swatches** — color/image/button swatches replacing default dropdowns (critical for activewear with color variants)
- **Product Wishlist** — heart icon on products, shareable wishlist, wishlist in header
- **Quick View** — lightbox preview without leaving shop page
- **Compare** — side-by-side product comparison
- **Product Brands** — brand taxonomy with logos
- **Size Guide** — popup size guide on product pages
- **Custom Tabs** — extra tabs on product pages (e.g., Care Instructions, Sustainability)
- **Sticky Add to Cart** bar

Without Blocksy Pro, you'd need 3–5 separate plugins to achieve the same (YITH Wishlist ~$80/year, Variation Swatches plugins, Quick View plugins). Blocksy Pro replaces all of these with a single, deeply integrated, performance-optimized solution.

### 2.3 Plugins to AVOID

| Plugin | Why |
|---|---|
| YITH WooCommerce Wishlist | Replaced by Blocksy Pro Shop Extra |
| WooCommerce Variation Swatches (standalone) | Replaced by Blocksy Pro |
| Any page builder (Elementor, etc.) | Conflicts with Blocksy; massive overhead |
| Yoast SEO + WooCommerce addon | More expensive, Rank Math free does same |
| WP Rocket | Redundant with LiteSpeed Cache; costs $59/year |
| Jetpack | Too broad; use focused plugins |
| WooCommerce PDF Invoices & Packing Slips | Fine if needed; deactivate if not |
| Multiple slider plugins | Use Gutenberg blocks or Blocksy hero |

---

## 3. Philippine Payments on WooCommerce

### 3.1 Gateway Comparison

| Gateway | GCash | Maya | Cards | Bank Transfer | OTC Cash | WooCommerce Plugin | Setup Difficulty |
|---|---|---|---|---|---|---|---|
| **PayMongo** | YES | YES | YES (Visa/MC) | BPI, UnionBank | No | Official free plugin | Easy |
| **Xendit** | Via GCash | Via Maya | YES | BPI direct debit, virtual accounts | 7-Eleven, Cebuana | Official free plugin (v6.1.2) | Moderate |
| **Dragonpay** | No | No | No | All major PH banks | Bayad Center, 7-Eleven, Cebuana, LBC, M Lhuillier | Third-party integration | Complex |
| **COD** | — | — | — | — | — | WooCommerce built-in | Trivial |

### 3.2 Recommended Payment Setup for B Active

**Tier 1 (Launch):**
1. **PayMongo** (plugin: `wc-paymongo-payment-gateway` on wordpress.org)
   - Accepts: GCash, Maya, GrabPay, Visa, Mastercard, BillEase, Atome, BPI online, UnionBank online
   - Minimum transaction: ₱100
   - Currency must be PHP (already the case for B Active)
   - KYC: 2–5 business days; register at paymongo.com
   - Rates (as of 2025): ~2.5–3.5% + ₱0 for e-wallets; ~3.5% for cards
   - **Single plugin covers 90%+ of PH payment methods needed**

2. **Cash on Delivery (COD)** — WooCommerce built-in
   - Enable for Davao local delivery; consider restricting to specific shipping zones to avoid abuse
   - Add a ₱50–100 COD handling fee (WooCommerce supports this natively)

**Tier 2 (If PayMongo insufficient):**
3. **Xendit** (`woo-xendit-virtual-accounts` on wordpress.org, v6.1.2, Feb 2026)
   - Adds: virtual account bank transfers to all major PH banks, OTC via 7-Eleven/Cebuana
   - Useful if customers don't have e-wallets
   - Can run alongside PayMongo (different payment methods)

**Dragonpay:** Not recommended for launch. Requires merchant agreement, more complex integration, and PayMongo already covers the primary use cases. Consider only if you need OTC cash payments beyond what Xendit covers.

### 3.3 PayMongo Setup Steps

1. Register at paymongo.com → complete business KYC (2–5 days)
2. WP Admin > Plugins > Add New > Search "PayMongo" → Install "Payments via PayMongo for WooCommerce"
3. WooCommerce > Settings > Payments → Enable: GCash, Maya, Credit/Debit Card, GrabPay
4. Enter live API keys from PayMongo dashboard
5. Test with PayMongo sandbox keys first
6. Set minimum order for COD if enabling: WooCommerce > Settings > Shipping

---

## 4. Shipping for the Philippines

### 4.1 Courier Integration Reality

**J&T Express Philippines** has a WooCommerce plugin (listed on WordPress.org) but it requires being a J&T VIP merchant account holder and the PHP plugin is maintained primarily for Malaysia. The Philippines integration is inconsistent and unofficial integrations are unreliable.

**Lalamove** has an e-commerce connector but it targets enterprise/high-volume merchants and requires API access, not a simple WooCommerce plugin install.

**Practical conclusion:** For a new brand at launch volume, direct API integrations with couriers are unnecessary overhead. Use **manual booking** with J&T Express / Ninja Van / LBC via their web portals, and let WooCommerce handle the rate calculation.

### 4.2 Recommended Shipping Setup

**Plugin: Flexible Shipping by Octolize** (free version sufficient)
- Table-rate shipping: calculate based on cart weight, cart total, destination zone
- Built-in "X more for free shipping" progress notice
- Create multiple shipping methods per zone

**Zone Configuration:**

| Zone | Method | Rate |
|---|---|---|
| **Davao City** | Standard Delivery | ₱80 flat |
| **Davao City** | Free Shipping | ₱0 if cart ≥ ₱2,000 |
| **Mindanao (excl. Davao)** | Standard Shipping | ₱150 flat |
| **Luzon/Visayas** | Standard Shipping | ₱180 flat |
| **Rest of Philippines** | Standard Shipping | Weight-based (e.g., ₱150 + ₱30/kg over 1kg) |

**Free shipping threshold:** ₱1,500–₱2,000 is a reasonable entry point for activewear (typical item ₱800–₱2,500). Set via WooCommerce > Shipping > Add Shipping Method > Free Shipping > Minimum Order Amount.

**Davao local delivery:** Consider a "Local Pickup" option for Davao customers (WooCommerce built-in) if you have a physical presence or can arrange meetups/drop points.

**Fulfillment workflow:**
1. Order placed → PayMongo confirms payment automatically
2. Admin receives email; review order in WooCommerce
3. Manually book courier (J&T Express PH portal or Ninja Van dashboard) using customer address
4. Enter tracking number in WooCommerce order notes and update status to "Shipped"
5. WooCommerce sends automated email with tracking number (use "Shipment Tracking for WooCommerce" free plugin — add this to the stack)

**Plugin addition:** Add **Shipment Tracking for WooCommerce** (by Zorem, free) — lets you paste tracking numbers into orders and sends customers branded tracking emails.

---

## 5. SEO Technical Setup

### 5.1 Permalink Fix (Priority #1)

The site currently uses ugly `/index.php/` URLs — this must be fixed before any SEO work.

**Steps:**
1. WP Admin > Settings > Permalinks
2. Select "Post name" (`/%postname%/`)
3. Click Save Changes (WordPress auto-updates `.htaccess` — on LiteSpeed, this creates `.htaccess` rules that LiteSpeed respects)
4. WooCommerce > Settings > Permalinks: Set "Product permalink base" to `/product/` (or just blank for `/product-name/`)
5. Verify no 404s on existing pages (new site, so minimal impact)
6. Flush LiteSpeed Cache after change

**Why this matters:** Search engines and users need clean, readable URLs. `/shop/active-shorts/` ranks and shares far better than `/index.php?p=123`.

### 5.2 Rank Math SEO Configuration

**Install:** Rank Math SEO (free) from wordpress.org — 23+ schema types free, WooCommerce module included free (vs Yoast's $99/year WooCommerce add-on).

**Key WooCommerce settings:**
1. Rank Math > General Settings > WooCommerce: Enable WooCommerce module
2. Rank Math > Titles & Meta > Products: Enable "Product" schema type
3. Rank Math automatically generates JSON-LD for: Product, Offer, AggregateRating, BreadcrumbList
4. Set title template: `%title% | Buy Online Philippines | B Active`
5. Set description template pulling from product short description

**Schema types to ensure are enabled:**
- Product (price, availability, SKU, brand)
- AggregateRating (from reviews; requires Customer Reviews plugin)
- BreadcrumbList (Rank Math > General Settings > Breadcrumbs: Enable)
- Organization (Rank Math > Titles & Meta > Local SEO — enter B Active business details)

**XML Sitemap:**
- Rank Math > Sitemap Settings: Enable
- Include: Posts, Pages, Products, Product Categories
- Exclude: Cart, Checkout, My Account, Thank You
- Submit to Google Search Console

### 5.3 Structured Data Checklist

- Product schema: auto via Rank Math + WooCommerce
- Review schema: use Customer Reviews for WooCommerce plugin (outputs AggregateRating)
- Breadcrumbs: enable in Rank Math; enable in Blocksy Customizer > WooCommerce > Shop > Breadcrumbs
- LocalBusiness schema: add via Rank Math Local SEO module (Davao City address, hours)
- FAQ schema: use on size guide, shipping FAQ pages

### 5.4 Google Search Console & GA4

1. **GA4:** MonsterInsights Lite (free) → connects GA4 to WooCommerce with enhanced e-commerce tracking (product views, add-to-cart, purchases) — no code required.
2. **Search Console:** Add property in GSC > verify via Google Analytics (MonsterInsights handles the GA tag). Submit sitemap URL: `yourdomain.com/sitemap_index.xml`.
3. **Canonical URLs:** Rank Math handles these automatically for products, categories, tags.
4. **Image SEO:** Always fill Alt Text on product images — use descriptive text: "B Active FlexFit Shorts in Midnight Blue — Women's Pickleball Activewear". Rank Math reminds you in the post editor.

---

## 6. Blocksy Child Theme — Technical Best Practices

### 6.1 Child Theme Structure

The existing `blocksy-child` theme is the correct foundation. Key files:

```
blocksy-child/
├── style.css           # Must declare Template: blocksy; add custom CSS vars here
├── functions.php       # Enqueue scripts/styles; add hooks; load custom PHP
├── assets/
│   ├── css/
│   │   └── custom.css  # Main custom stylesheet (enqueued via functions.php)
│   └── js/
│       └── custom.js   # Custom JavaScript
└── woocommerce/        # WooCommerce template overrides (mirror parent paths)
```

### 6.2 Design System via CSS Variables

Blocksy generates CSS custom properties from its Customizer settings. These are output on the `:root` element. You can reference and extend them.

**Blocksy's generated variables include:**
```css
--theme-palette-color-1: /* Primary color from Global Colors */
--theme-palette-color-2: /* Secondary color */
--theme-palette-color-8: /* Text color */
--theme-font-size: /* Base font size */
--theme-line-height: /* Base line height */
```

**B Active design system — add to `style.css` or `assets/css/custom.css`:**
```css
:root {
  /* Brand palette — define here, reference everywhere */
  --bactive-primary: #[BRAND_COLOR];      /* e.g., electric coral */
  --bactive-secondary: #[BRAND_COLOR_2];  /* e.g., midnight black */
  --bactive-accent: #[ACCENT_COLOR];      /* e.g., fresh white */
  --bactive-text: #1a1a1a;
  --bactive-bg: #ffffff;

  /* Typography scale */
  --bactive-font-heading: 'YourHeadingFont', sans-serif;
  --bactive-font-body: 'YourBodyFont', sans-serif;
  --bactive-font-size-base: 16px;

  /* Spacing scale */
  --bactive-space-sm: 8px;
  --bactive-space-md: 16px;
  --bactive-space-lg: 32px;
  --bactive-space-xl: 64px;

  /* Component tokens */
  --bactive-btn-radius: 4px;
  --bactive-card-radius: 8px;
  --bactive-transition: 0.2s ease;
}
```

This pattern lets the AI agent (Gemini/Antigravity) always reference `--bactive-primary` instead of hardcoding hex values — making global rebrand trivially easy.

### 6.3 Enqueueing in functions.php

```php
function bactive_enqueue_assets() {
    // Enqueue parent theme stylesheet (required for child themes)
    wp_enqueue_style(
        'blocksy-parent-style',
        get_template_directory_uri() . '/style.css'
    );

    // Enqueue child theme custom stylesheet
    wp_enqueue_style(
        'bactive-custom-style',
        get_stylesheet_directory_uri() . '/assets/css/custom.css',
        array('blocksy-parent-style'),
        filemtime(get_stylesheet_directory() . '/assets/css/custom.css')
    );

    // Enqueue custom JS (defer it)
    wp_enqueue_script(
        'bactive-custom-js',
        get_stylesheet_directory_uri() . '/assets/js/custom.js',
        array('jquery'),
        filemtime(get_stylesheet_directory() . '/assets/js/custom.js'),
        true // load in footer
    );
}
add_action('wp_enqueue_scripts', 'bactive_enqueue_assets');
```

Using `filemtime()` as version string busts browser cache automatically on file change — critical for FTP-deployed changes.

### 6.4 Blocksy Customizer for Colors & Typography

1. Appearance > Customize > General Options > Colors
   - Set Global Color Palette: match B Active brand colors
   - Copy the CSS variable names shown (e.g., `var(--theme-palette-color-1)`) for use in custom CSS
2. Appearance > Customize > Typography
   - Set Global Font: choose Google Font or upload custom font
   - Set Heading font separately if desired
3. Appearance > Customize > WooCommerce
   - Configure shop grid: columns, card style, image ratio (recommend 3:4 portrait for activewear)
   - Enable Blocksy Pro features: Wishlist, Quick View, Swatches
4. **Export Customizer settings:** Use Blocksy's built-in Export/Import to save a backup of all Customizer settings.

### 6.5 WooCommerce Template Overrides

Copy templates from `wp-content/themes/blocksy/woocommerce/` to `wp-content/themes/blocksy-child/woocommerce/` before editing. Never edit parent theme templates.

Common override targets:
- `woocommerce/single-product/add-to-cart/variable.php` — customize variation layout
- `woocommerce/checkout/form-checkout.php` — customize checkout fields
- `woocommerce/emails/` — customize transactional email templates

Check WooCommerce version compatibility after each WooCommerce update — template overrides can go stale.

### 6.6 Blocksy Action Hooks for Customization

Blocksy exposes hooks for adding content without template overrides:
```php
// Add content after product title on shop cards
add_action('blocksy:woocommerce:product-card:title:after', function() {
    // e.g., add a "New" badge
});

// Add content before Add to Cart on single product
add_action('blocksy:woocommerce:single-product:before-add-to-cart', function() {
    // e.g., add a size guide link
});
```

Use `blocksy:` prefixed hooks for Blocksy-specific locations; use standard WooCommerce hooks (`woocommerce_after_shop_loop_item`, etc.) for WooCommerce locations.

---

## 7. Security & Maintenance Hardening

### 7.1 Current Stack Assessment

Existing security plugins are well-chosen:
- **Wordfence** — application-level WAF, malware scanner, login protection
- **Limit Login Attempts Reloaded** — brute force protection
- **WPS Hide Login** — obscures login URL (note: has had past disclosure vulnerabilities; keep updated; treat as security-by-obscurity layer, not primary defense)
- **Akismet** — spam comment filtering

### 7.2 Hardening Checklist

**WordPress Core:**
- [ ] Auto-update WordPress core minor versions: ON
- [ ] Auto-update plugins: ON (or use WP Updates Notifier to review before updating)
- [ ] Disable file editing: add `define('DISALLOW_FILE_EDIT', true);` to `wp-config.php`
- [ ] Move `wp-config.php` one directory above webroot (if host permits)
- [ ] Set `wp-config.php` permissions: 440 or 400
- [ ] Disable XML-RPC: Wordfence > Firewall > Block XML-RPC requests
- [ ] Delete default "admin" user account; use unique username
- [ ] Enable 2FA: Wordfence Login Security plugin (separate lightweight plugin) or Wordfence Premium 2FA

**Wordfence Configuration:**
- [ ] Firewall > Optimize Firewall (run the wizard to enable extended protection)
- [ ] Scan > Scan Schedule: Daily
- [ ] Live Traffic: Enable for monitoring; disable if causing DB bloat
- [ ] Email Alerts: Enable for critical issues, disable verbose login alerts
- [ ] Rate Limit Rules: Set for crawlers, 404s, humans

**Cloudflare + Wordfence layering:**
- Cloudflare WAF: blocks threats at the network edge (fast, no PHP overhead)
- Wordfence WAF: blocks threats at the WordPress application level (deeper WP context)
- These complement each other — keep both. Review Wordfence blocked IPs monthly and add the worst offenders as Cloudflare IP block rules.
- Enable Cloudflare "Under Attack Mode" for DDoS mitigation when needed

**Cloudflare Security Rules:**
- [ ] Enable WordPress managed ruleset (Security > WAF > Managed Rules)
- [ ] Rate limit `/wp-login.php`: >5 requests/min → Block (or Managed Challenge)
- [ ] Block `/xmlrpc.php`: URI equals `/xmlrpc.php` → Block
- [ ] Challenge traffic from high-abuse ASNs to admin area

**Database:**
- [ ] Change default WordPress table prefix from `wp_` to something unique (do this at install, not after — risky to change on live site)
- [ ] UpdraftPlus: schedule daily backups to remote storage (Google Drive or Dropbox); keep 30 days retention; test restore quarterly

**SSL/HTTPS:**
- [ ] Cloudflare SSL: Full (Strict) mode — requires valid SSL certificate on origin server
- [ ] Namecheap cPanel: install Let's Encrypt or Sectigo SSL (cPanel > SSL/TLS)
- [ ] Force HTTPS: via Cloudflare "Always Use HTTPS" + add to `wp-config.php`: `define('FORCE_SSL_ADMIN', true);`

### 7.3 Staging Workflow

**Options for Namecheap cPanel:**

1. **Subdomain staging:** Create `staging.bactiveph.com` as a subdomain in cPanel, install a separate WordPress there. Use UpdraftPlus Migrator to clone production → staging before major changes.

2. **InstaWP (recommended for AI agent workflow):** Create cloud staging instances that mirror production. InstaWP has a "Local Mount" feature allowing files to sync between local and staging. Free tier available.

3. **All-in-One WP Migration + subdomain:** Export production site, import to staging subdomain.

**Staging best practices:**
- Always develop/test on staging before deploying to production
- Use Cloudflare Access or HTTP password protection on staging subdomain (prevent Google from indexing staging)
- Add to staging `wp-config.php`: `define('WP_DEBUG', true); define('WP_DEBUG_LOG', true);`
- After testing, deploy to production via FTP (child theme files only — never touch WordPress core)

---

## 8. AI Coding Agent Workflow (Antigravity/Gemini over FTP/cPanel)

### 8.1 Ground Rules

1. **Child theme only.** Never edit `wp-content/themes/blocksy/` (parent). All work goes in `wp-content/themes/blocksy-child/`.
2. **Never edit WordPress core files** (`wp-admin/`, `wp-includes/`).
3. **Always test on staging** before deploying to production (see §7.3).
4. **Version control the child theme.** Initialize a Git repo inside `blocksy-child/`; commit before every change. This gives a full undo history even without a staging site.

### 8.2 Git Setup for Child Theme

```bash
cd /path/to/blocksy-child/
git init
git add .
git commit -m "Initial child theme state"
```

Before any AI agent edit:
```bash
git add -A && git commit -m "Pre-edit snapshot: $(date)"
```

After AI agent deploys:
```bash
git add -A && git commit -m "feat: [description of change]"
```

If something breaks: `git stash` or `git checkout -- .`

### 8.3 FTP Deployment Best Practices

- The AI agent should upload only **modified files** — not overwrite the entire theme on every deploy.
- Use binary comparison before uploading: only upload if the local file differs from remote (most FTP clients support this).
- After uploading PHP changes, always verify in browser + check error logs (cPanel > Error Logs).
- After uploading CSS changes, flush LiteSpeed Cache: WP Admin > LiteSpeed Cache > Manage > Purge All.

### 8.4 Context Files for AI Agent

Maintain a `AGENT_CONTEXT.md` file in the child theme root (excluded from deployment via `.ftpignore` or manually):
- Brand color palette (with exact hex values)
- CSS variable names and their meanings
- Blocksy Customizer settings summary
- Active plugin list and their purposes
- Coding conventions (indentation, naming)
- WooCommerce product attribute names (colors, sizes)

This file lets the AI agent pick up context on each session without re-reading dozens of files.

### 8.5 WP-CLI for Remote Operations

If available via cPanel SSH access (Namecheap offers SSH on higher plans):
```bash
wp cache flush
wp rewrite flush
wp plugin list --status=active
wp theme list
```

WP-CLI is far faster than navigating WP Admin for routine tasks.

### 8.6 What the AI Agent Should Never Do

- Never run `UPDATE`, `DELETE`, or `DROP` SQL queries on the live database directly
- Never install or delete plugins via FTP (use WP Admin)
- Never edit `wp-config.php` without a confirmed backup
- Never disable Wordfence or other security plugins
- Never delete media files from `wp-content/uploads/` without explicit instruction
- Never push to production without a staging test when the change touches checkout or payment flows

---

## 9. Implications for B Active

### Immediate Actions (Before Launch)

1. **Fix permalinks** (Settings > Permalinks > Post Name) — must be done first, before SEO setup
2. **Install & configure LiteSpeed Cache** with WooCommerce mode enabled
3. **Set up Cloudflare Cache Rules** (bypass cart/checkout/my-account/admin)
4. **Install Rank Math SEO** — run setup wizard; enable WooCommerce module; configure product schema
5. **Install ShortPixel** — run bulk optimization on all existing product images; enable WebP
6. **Purchase Blocksy Pro** (~$49/year) — activate Shop Extra for swatches, wishlist, quick view
7. **Register PayMongo** business account — KYC takes 2–5 days; do this early
8. **Install & configure Shipping zones** via Flexible Shipping

### Launch Plugin Stack (Final)

| Plugin | Cost |
|---|---|
| LiteSpeed Cache | Free |
| Rank Math SEO | Free |
| ShortPixel | ~$4.99/month after 100 images |
| MonsterInsights Lite | Free |
| Omnisend | Free (500 emails/day) |
| Customer Reviews for WooCommerce | Free |
| OMGF – Host Google Fonts Locally | Free |
| Wordfence | Free (Premium ~$119/year optional) |
| Limit Login Attempts Reloaded | Free |
| WPS Hide Login | Free |
| Akismet | Free (paid plan ~$10/month for business) |
| UpdraftPlus | Free (Premium ~$70/year for remote storage) |
| Payments via PayMongo | Free |
| Flexible Shipping | Free |
| Shipment Tracking for WooCommerce (Zorem) | Free |
| Blocksy Pro | ~$49/year |
| **Total recurring** | **~$54–75/month or ~$110–170/year** |

### Performance Targets

With this stack, realistic targets on Namecheap LiteSpeed hosting + Cloudflare:
- **TTFB:** < 300ms (Philippines server, logged-out visitor)
- **LCP:** < 2.5s on mobile (Core Web Vitals "Good")
- **CLS:** < 0.1 (Blocksy is naturally stable; ensure images have explicit width/height attributes)
- **INP:** < 200ms (minimize heavy JS; avoid page builders)
- **PageSpeed Mobile:** 75–90+ (achievable; shared hosting has TTFB ceiling)

### Payment Recommendation

**Start with PayMongo only.** It covers GCash + Maya + cards + GrabPay + BPI/UnionBank in one plugin. Add Xendit later only if customers request OTC/virtual account bank transfer. Add COD with ₱50 handling fee for Davao-area orders.

### SEO Priority

- Permalink fix is most urgent — cannot run effective SEO on index.php URLs
- Product schema via Rank Math will immediately enable rich results for pricing/availability
- Local SEO (Rank Math + Google Business Profile for Davao) should be set up before first content push

---

## Sources

- [LiteSpeed Cache – WordPress.org](https://en-gb.wordpress.org/plugins/litespeed-cache/)
- [Ideal LiteSpeed Cache Settings – OnlineMediaMasters](https://onlinemediamasters.com/litespeed-cache-settings/)
- [Speed Up WooCommerce with LiteSpeed Cache – WisdmLabs](https://wisdmlabs.com/blog/speed-up-woocommerce-store-with-litespeed-cache-full-settings-walkthrough/)
- [Namecheap – LiteSpeed Web Cache Manager](https://www.namecheap.com/support/knowledgebase/article.aspx/10488/22/how-to-work-with-litespeed-web-cache-manager-plugin/)
- [Cloudflare Cache Rules for WordPress 2025 – BoostedHost](https://boostedhost.com/blog/en/cloudflare-rules-for-wordpress-2025-security-cache-bypass-and-apo-tips/)
- [Cloudflare Cache Rules – WordPress/WooCommerce – Cloudflare Docs](https://developers.cloudflare.com/support/third-party-software/content-management-system-cms/caching-static-html-with-wordpresswoocommerce/)
- [Cloudflare APO FAQs](https://developers.cloudflare.com/automatic-platform-optimization/troubleshooting/faq/)
- [Caching WordPress Using Cloudflare 2026](https://h-haboubi.com/blog/web-development/caching-wordpress-using-cloudflare/)
- [ShortPixel – Image Optimizer (AVIF/WebP)](https://shortpixel.com/)
- [ShortPixel vs Imagify 2025 – OddJar](https://oddjar.com/wordpress-image-optimization-plugins-2025-comparison/)
- [WordPress Image Optimization Comparison 2025 – WPThrill](https://wpthrill.com/image-optimization-plugin-comparison/)
- [Payments via PayMongo for WooCommerce – WordPress.org](https://wordpress.org/plugins/wc-paymongo-payment-gateway/)
- [PayMongo WooCommerce Developer Docs](https://developers.paymongo.com/docs/woocommerce)
- [GCash, Maya, PayMongo Integration Guide – WebDesigner.ph](https://webdesigner.ph/articles/gcash-maya-paymongo-philippine-payment-integration-guide/)
- [Xendit Payment – WordPress.org](https://wordpress.org/plugins/woo-xendit-virtual-accounts/)
- [Xendit WooCommerce Docs](https://docs.xendit.co/docs/woocommerce)
- [Dragonpay Philippines](https://www.dragonpay.ph/)
- [J&T Express Philippines Shipping Guide – LogisticsBid](https://logisticsbid.com/ph/jt-express-philippines-guide-to-shipping-rates-and-parcel-tracking-solutions/)
- [Lalamove E-Commerce Integrations PH](https://www.lalamove.com/en-ph/business/ecommerce-integrations)
- [Flexible Shipping for WooCommerce – WordPress.org](https://wordpress.org/plugins/flexible-shipping/)
- [WooCommerce Table Rate Shipping Guide – LearnWoo](https://learnwoo.com/woocommerce-table-rate-shipping-ultimate-guide/)
- [Rank Math WooCommerce SEO Guide 2025](https://rankmath.com/blog/woocommerce-seo/)
- [Rank Math vs Yoast 2025 – WPConcern](https://wpconcern.com/rank-math-vs-yoast-seo/)
- [WooCommerce Product Schema Guide 2026 – WebAppick](https://webappick.com/how-to-add-woocommerce-product-schema/)
- [WordPress Permalink Best Settings 2026 – Jetpack](https://jetpack.com/resources/wordpress-permalinks/)
- [WooCommerce Permalinks Documentation](https://woocommerce.com/document/permalinks/)
- [Blocksy Child Theme Documentation – CreativeThemes](https://creativethemes.com/blocksy/docs/general/child-theme/)
- [Blocksy Colors Documentation](https://creativethemes.com/blocksy/docs/general-options/colors/)
- [Blocksy Typography Documentation](https://creativethemes.com/blocksy/docs/general-options/typography/)
- [Blocksy Variation Swatches Documentation](https://creativethemes.com/blocksy/docs/woocommerce/variation-swatches/)
- [Blocksy Products Wishlist Documentation](https://creativethemes.com/blocksy/docs/woocommerce/product-wishlist/)
- [Blocksy Quick View Documentation](https://creativethemes.com/blocksy/docs/woocommerce/quick-view/)
- [Blocksy Shop Extra Extension](https://creativethemes.com/blocksy/docs/extensions/woocommerce-extra/)
- [Blocksy Theme Review 2026 – WPOptimizers](https://wpoptimizers.com/blocksy-theme-review/)
- [Wordfence vs Cloudflare WAF – WP-Hosting](https://wp-hosting.co.nz/wordfence-vs-cloudflare-waf-the-ultimate-showdown-for-wordpress-protection/)
- [WordPress Security Hardening 2025 – Divimode](https://divimode.com/wordpress-security-best-practices/)
- [Hardening WordPress with Wordfence and Cloudflare – JonahMay](https://jonahmay.net/defense-in-depth-wordpress-hardening-with-wordfence-really-simple-security-cloudflare-and-pfsense-haproxy/)
- [Cloudflare WordPress Security Improvements – Cloudflare Docs](https://developers.cloudflare.com/support/third-party-software/content-management-system-cms/improving-web-security-for-content-management-systems-like-wordpress/)
- [WPS Hide Login Vulnerabilities – Wordfence Intelligence](https://www.wordfence.com/threat-intel/vulnerabilities/wordpress-plugins/wps-hide-login)
- [MonsterInsights WooCommerce GA4 Setup](https://www.monsterinsights.com/how-to-set-up-woocommerce-google-analytics/)
- [Omnisend Email Marketing – WordPress.org](https://wordpress.org/plugins/omnisend-connect/)
- [Omnisend vs Klaviyo 2026](https://www.omnisend.com/blog/omnisend-vs-klaviyo/)
- [WordPress AI Agent Best Practices – InstaWP](https://instawp.com/build-wordpress-ai-agent/)
- [CI/CD for WordPress – DoHost](https://dohost.us/index.php/2026/03/30/ci-cd-for-wordpress-modernizing-your-agencys-legacy-cms-workflow/)
- [WordPress Agent Skills – GitHub/WordPress](https://github.com/WordPress/agent-skills)
