---
name: B Active Sage footer
description: The approved Sage welcome footer, scoped to the footer only.
colors:
  sage: "#99ab90"
  ivory: "#f9f7f4"
  ink: "#242222"
  rule: "#e4dcd2"
  focus: "#40543c"
typography:
  headline:
    fontFamily: "Fraunces, Georgia, serif"
    fontSize: "clamp(36px, 3.35vw, 56px)"
    fontWeight: 400
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Fraunces, Georgia, serif"
    fontSize: "clamp(23px, 1.7vw, 28px)"
    fontWeight: 400
    lineHeight: 1.35
    letterSpacing: "-0.02em"
  body:
    fontFamily: "Inter, sans-serif"
    fontSize: "clamp(16px, 1.2vw, 20px)"
    lineHeight: 1.5
  caption:
    fontFamily: "Inter, sans-serif"
    fontSize: "clamp(13px, 1vw, 16px)"
rounded:
  form-edge: "3px"
  courier: "7px"
spacing:
  small: "12px"
  medium: "24px"
  large: "32px"
  wide: "40px"
components:
  join-button:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.ivory}"
    padding: "18px 24px"
  join-button-hover:
    backgroundColor: "{colors.focus}"
    textColor: "{colors.ivory}"
  email-input:
    backgroundColor: "{colors.ivory}"
    textColor: "{colors.ink}"
    padding: "20px 24px"
    height: "72px"
---

# Design System: B Active Sage footer

## Overview

**Creative North Star: "Sage welcome"**

The user-approved footer has a welcoming sage invitation above a warm ivory body. Generous space and serif headings separate the signup, store information, delivery, payments, and legal text without enclosing every group in a card.

This guide records the implemented footer only. It is not a replacement for the storefront's other visual decisions. Source of truth is `template-parts/footer-sage.php`; the existing trust template supplies courier and payment content. These implementation records do not establish that the candidate has been deployed.

**Key Characteristics:**
- A broad sage signup field and warm ivory information area.
- Fraunces headings paired with Inter text and controls.
- Original brand, courier, social, and payment artwork.
- Open columns and fine rules, with readable mobile reflow.

## Colors

Sage provides the invitation's broad color field; warm neutrals carry the dense information below.

### Primary
- **Sage:** newsletter background.
- **Deep green focus:** interactive hover and keyboard focus.

### Neutral
- **Warm ivory:** footer ground and email input.
- **Charcoal ink:** text, form border, and Join button.
- **Warm rule:** information-group dividers.

**The Footer Boundary Rule.** Keep these values scoped to the Sage footer; do not replace the site's global color variables to reproduce this surface.

## Typography

**Display Font:** Fraunces, with Georgia and serif fallbacks.
**Body Font:** Inter, with a sans-serif fallback.

The serif gives the invitation and column headings a distinct hierarchy, while the body remains direct and readable. Existing local font assets are reused.

### Hierarchy
- **Headline:** the signup invitation, fluid across desktop widths and reduced on small screens.
- **Title:** uppercase navigation group headings with regular weight.
- **Body:** contact, offer, navigation, and form text.
- **Caption:** payment attribution and legal information; legal copy uses extra line spacing and wraps in full.

## Layout

The inner container is capped at 1464px and otherwise occupies 87.5% of the available width, changing to 20px side margins at the phone breakpoint. The signup uses a 1.3:1 two-column grid. The main footer uses four columns, with a slightly wider brand column followed by Shop, Help, and Brand. Shared gaps tighten below 1439px.

The trust strip places shipping and payments beside each other at wide widths, separated by a fine vertical rule. At 1279px and below it stacks. Signup and main-column changes occur at 899px and 599px; on phones the brand block spans the width, Shop and Help sit in two columns, Brand follows, and payment marks use three columns. Mobile navigation, contact, and social targets are at least 44px high.

**The Content Continuity Rule.** Responsive changes reflow the existing content; they do not remove destinations, shorten the registered address, or merge nationwide and Davao-only delivery labels.

## Elevation & Depth

The footer is flat. Broad color fields, space, and thin rules provide separation. The form and controls have no shadows; their hover and focus states use color and outlines.

## Shapes

The email field and Join button form one continuous rectangular control with gently rounded outside edges. Original courier badges retain their modest rounding. Logos preserve their full intrinsic proportions and use containment rather than cropping.

## Components

### Signup

The headline and offer sit beside one connected email field and Join button. The field height and button minimum height step from 72px to 60px at the tablet breakpoint, then 56px on phones. The button changes to deep green on hover. Signup controls and ordinary footer links show a 2px focus outline with 4px offset. Color transitions take 160ms with ease-out; reduced-motion preferences remove transitions.

The recorded form appearance does not certify subscription functionality. The Brevo integration owns submission, validation messages, consent, and success/error behavior.

### Navigation and contact

Navigation uses real headings and lists. Hover changes the link color and adds an underline. Contact links pair readable text with small decorative outline SVGs. Social links reuse the original image assets within 44px targets.

### Delivery and payments

Preserve original marks, complete labels, and the separate PayMongo attribution. The existing trust template determines which payment marks are shown, including conditional Cash on Delivery. A six-column desktop grid becomes a three-column mobile grid. Courier links retain the trust template's existing hover border and 2px focus outline with 3px offset.

### Legal text

The complete registered address and copyright form a centered final block above the bottom edge. Privacy and Terms remain normal keyboard-accessible links; narrow screens wrap the text without clipping.

## Do's and Don'ts

### Do:
- **Do** preserve the approved sage/ivory composition and heading/body contrast.
- **Do** keep original logos, their colors, complete geometry, and intrinsic proportions.
- **Do** keep the trust template's payment conditions and delivery distinctions.
- **Do** verify the complete footer at wide and narrow widths after integration changes.

### Don't:
- **Don't** replace global theme styles to reproduce this footer.
- **Don't** use the mockup as a flattened production image or as a source for redrawing logos.
- **Don't** document the disconnected form as a working subscription system.
- **Don't** treat this footer guide as a new visual mandate for unrelated pages.
