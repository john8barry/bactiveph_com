---
name: B Active Sage header
description: The approved ivory and sage navigation, scoped to the header only.
colors:
  sage: "#829677"
  ivory: "#f9f7f4"
  ink: "#242222"
  focus: "#40543c"
typography:
  navigation:
    fontFamily: "Rajdhani, Inter, sans-serif"
    fontSize: "19px"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "0.025em"
  mobile-navigation:
    fontFamily: "Rajdhani, Inter, sans-serif"
    fontSize: "22px"
    fontWeight: 600
    lineHeight: 1.3
  category:
    fontFamily: "Inter, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.4
  mobile-category:
    fontFamily: "Inter, sans-serif"
    fontSize: "17px"
    fontWeight: 400
    lineHeight: 1.5
  form:
    fontFamily: "Inter, sans-serif"
    fontSize: "15px"
    fontWeight: 400
    lineHeight: 1.5
  utility:
    fontFamily: "Inter, sans-serif"
    fontSize: "12px"
    fontWeight: 400
    lineHeight: 1.3
rounded:
  form-edge: "3px"
  focus-edge: "2px"
spacing:
  compact: "8px"
  small: "12px"
  medium: "18px"
  wide: "22px"
components:
  primary-navigation:
    textColor: "{colors.ink}"
    typography: "{typography.navigation}"
  primary-navigation-hover:
    textColor: "{colors.focus}"
  disclosure-panel:
    backgroundColor: "{colors.ivory}"
    textColor: "{colors.ink}"
    padding: "18px 22px"
  utility:
    textColor: "{colors.ink}"
    typography: "{typography.utility}"
  search-input:
    backgroundColor: "#fff"
    textColor: "{colors.ink}"
    typography: "{typography.form}"
    rounded: "{rounded.form-edge}"
    padding: "10px 12px"
    height: "48px"
  search-button:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.ivory}"
    rounded: "{rounded.form-edge}"
    padding: "12px"
  search-button-hover:
    backgroundColor: "{colors.focus}"
    textColor: "{colors.ivory}"
---

# Design System: B Active Sage header

## Overview

**Creative North Star: "Ivory and sage"**

The approved header gives the original athletic B Active logo a prominent place on a warm ivory surface. Sage rules organize the navigation, while clear groups and generous horizontal space keep shopping destinations and everyday utilities easy to find. The controls are restrained, readable and direct.

This guide records the implemented header candidate and the approved typography dependency. It extends the established brand and Sage footer without establishing a new visual system for page content. The [work record](../../work-records/2026-09-06-sage-header.md) owns the direction contract, acceptance evidence and release state; local source and screenshots do not establish deployment.

**Key Characteristics:**

- Original configured logo, complete and uncropped.
- Warm ivory ground, charcoal text and sage rules.
- Rajdhani primary navigation paired with Inter categories, forms and utilities.
- Native expandable menus, visible focus and generous touch targets.

## Colors

Warm neutrals carry the surface and its text; sage supplies the fine organizing lines.

### Primary

- **Sage:** the header baseline, Shop underline, mobile section rules and search-field border.
- **Deep green focus:** link hover, keyboard focus, text selection and search-button hover.

### Neutral

- **Warm ivory:** the header and its disclosure panels; the search button uses it for its icon.
- **Charcoal ink:** navigation, outline icons, form text and the search-button fill.

**The Header Boundary Rule.** Keep the ivory surface and sage rules scoped to this header; preserve the page and footer systems around it.

The sidecar's tonal ramps are synthesized swatch aids only. They are not additional implemented palette tokens.

## Typography

**Navigation Font:** Rajdhani, with Inter and sans-serif fallbacks.
**Body and Utility Font:** Inter, with a sans-serif fallback.

Rajdhani gives primary destinations the athletic character approved in the separate brand typography work. Inter keeps the longer category names, search form and small utility labels easy to scan. The header consumes the shared heading token; the [brand typography stylesheet](../../../wordpress/wp-content/themes/blocksy-child/assets/css/brand-typography.css) supplies Rajdhani at weight 600 through local font assets and is enqueued by the child theme before the header stylesheet.

### Hierarchy

- **Navigation:** uppercase desktop primary destinations and the Shop summary, using the navigation role.
- **Mobile navigation:** larger uppercase primary destinations and the Shop summary. The Shop summary adds subtle tracking (0.02em).
- **Category:** sentence-case links within the desktop Shop panel; the mobile-category role increases their size and line height on phones.
- **Form:** search label and field text. The label rises to medium weight (500).
- **Utility:** Search, Account and Bag labels beneath the desktop outline icons.

**The Two Roles Rule.** Use the shared Rajdhani heading token for primary destinations; retain Inter for categories, search and utility labels.

## Layout

Desktop places the logo, primary navigation and utilities in three horizontal groups. The inner row is capped at 1464px and otherwise uses 92% width. It has a minimum height of 108px, 10px vertical padding and a 28px gap between groups; the sage baseline adds 2px below it. Navigation gaps grow with the viewport from 22px to 44px. Utilities use an 18px gap.

The configured logo fits a 156px by 88px image box with containment. Its anchor keeps automatic height and no maximum height, so the complete mark remains within the row. At desktop widths from 1000px through 1120px, the row uses 94% width, its group gap tightens to 18px, the logo box narrows to 138px, and utility gaps reduce to 12px.

Blocksy owns the desktop/mobile row visibility. The header interaction breakpoint is 1000px. On mobile, the menu control sits left, the original logo is centered in a 128px by 74px contained box, and the bag control sits right. The closed row is 94px tall plus its 2px baseline. Opening the menu expands the normal page flow; it does not create a separate fixed overlay. The mobile panel uses 26px top, 24px side and 32px bottom padding.

Desktop Shop and search panels open below their controls with an 18px gap. Shop is 264px wide; search is 350px wide and aligns toward the utility group. Mobile exposes the full collection list when the main menu first opens, then places primary destinations, account access and search below it.

**The Complete Mark Rule.** Contain the original logo at its intrinsic proportions and retain an automatically sized anchor; never crop the mark to fit a fixed anchor height.

## Elevation & Depth

The closed header is opaque and flat. Its baseline and section rules separate content without a glass treatment. Desktop disclosure panels use one soft ambient shadow to sit over the page. Mobile navigation remains in document flow and uses rules rather than elevation.

- **Disclosure shadow** (`0 12px 28px #2422221f`): shared by the desktop Shop and search panels.
- **Navigation rule** (`inset 0 -2px` in sage): the Shop summary and recognized current primary destinations. This is a line treatment, not panel elevation.

## Shapes

The header and disclosure panels use plain rectangular surfaces. The search field and submit button have gently rounded form edges; the focus treatment has its own small radius. Icons share a 24px outline canvas, 1.5px strokes, rounded caps and rounded joins. Chevron icons use an 18px canvas and rotate when their disclosure opens.

## Components

### Brand

The logo is the incumbent asset configured in WordPress and rendered by Blocksy. Reuse that renderer rather than duplicating the asset or recreating the wordmark. A configured image suppresses the adjacent text title; when an image is absent, Blocksy's text fallback remains available. No new raster asset belongs to this header release.

### Primary navigation and Shop

The primary row retains Shop, Pickleball Looks, About and Contact. Shop is a native disclosure with eight existing storefront destinations, beginning with Shop all. Its baseline remains visible while closed. Category links underline on hover; primary links and summaries change to deep green. Recognized current primary pages retain their sage rule.

Desktop disclosure behavior permits one panel at a time. Clicking outside or moving focus outside an open panel closes it. Escape closes the active disclosure and returns focus to its summary. Native disclosure controls preserve access when scripting is unavailable.

### Utilities

Desktop pairs a consistent outline icon with each Search, Account and Bag label. Each utility target is at least 48px wide and 60px high. Account and Bag retain their WooCommerce destinations. On mobile the bag remains immediately available in a 48px square target, while account and search appear inside the menu.

### Mobile menu

The native menu summary uses a 48px-wide target across the full closed row height. Its menu icon becomes a close icon while expanded, with corresponding accessible text. Collection links have at least 48px height; primary destinations have at least 50px height. The nested Shop collection disclosure begins open. Escape from within the expanded mobile menu closes the main menu and returns focus to its summary.

Crossing the 1000px interaction breakpoint closes open disclosures. If focus was in the header, it moves to the newly active desktop logo link or mobile menu summary.

### Search

The search form presents a visible label, a required search field and an outline-icon submit button. The field has a white fill, sage border and deep green caret. Its placeholder uses muted gray-green (`#5c6158`). The ink button changes to deep green on hover and has a minimum 48px square target. The form submits the ordinary WordPress GET search to the home URL; device-specific label IDs keep the two rendered variants distinct.

### Focus and motion

Links, summaries, buttons and inputs use a deep green 2px keyboard-focus outline with 5px offset. Desktop navigation and categories have at least 44px target height. Link and summary color transitions take 160ms with ease-out only when reduced motion is not requested. Opening menus remains immediate.

The implementation lives in the [guarded loader](../../../wordpress/wp-content/mu-plugins/bactiveph-sage-header.php), [header template](../../../wordpress/wp-content/themes/blocksy-child/template-parts/header-sage.php), [scoped stylesheet](../../../wordpress/wp-content/themes/blocksy-child/assets/css/header-sage.css) and [interaction script](../../../wordpress/wp-content/themes/blocksy-child/assets/js/header-sage.js). The loader replaces Blocksy's rendered rows while retaining its outer header, document shell and native drawer canvas.

## Do's and Don'ts

### Do:

- **Do** preserve the complete WordPress-configured logo and its text fallback.
- **Do** keep primary destinations in Rajdhani and supporting navigation and controls in Inter.
- **Do** retain native disclosure semantics, visible focus and at least 44px interactive targets.
- **Do** let the expanded mobile menu occupy normal page flow with every existing destination available.
- **Do** keep header changes isolated from hero content, footer styling and commerce behavior.

### Don't:

- **Don't** recreate, crop or duplicate the original brand asset to reproduce the mockup.
- **Don't** add a glass backdrop or turn the header's plain navigation into a card grid.
- **Don't** require JavaScript or hover alone to reach the Shop links or mobile menu.
- **Don't** promote surrounding hero artwork, hero typography or footer layout into header rules.
