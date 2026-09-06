# B Active

B Active is a Philippine online store for activewear and pickleball products, with a Davao community and store presence. The existing storefront runs on WordPress and WooCommerce with the Blocksy child theme.

## Customers and useful outcomes

Customers browse clothing and pickleball equipment, find sizing and care information, check delivery and payment options, and contact the store. The footer makes these existing destinations easy to find after browsing a page. Shipping information distinguishes nationwide couriers from GrabExpress service in Davao City.

## Current approved design scope

John approved the light-green **Sage welcome** footer mockup and its implementation on 2026-09-05. The chosen footer combines a sage newsletter band, warm ivory brand and navigation columns, a delivery/payment strip, and readable business and legal information. Its implementation and release are tracked in [issue #14](https://github.com/john8barry/bactiveph_com/issues/14).

This is a footer refinement. It does not establish a new visual direction for the rest of the storefront. The implemented footer system is recorded in [its scoped design guide](docs/design/footer/DESIGN.md).

## Content and asset commitments

Retain the existing navigation labels and destinations, contact details, social profiles, complete B Active logo, newsletter offer, registered address, and legal links. Preserve the original courier and payment artwork and the existing trust template's conditional Cash on Delivery visibility. Do not infer gateway availability from a design mockup.

Support narrow screens, keyboard navigation, readable contrast, visible focus, and reduced motion. Do not turn functional text or controls into raster artwork.

## Newsletter integration boundary

The footer design retains the existing 5% first-order signup offer and current form behavior. Brevo is replacing MailPoet in separate integration work; this footer release neither connects MailPoet nor claims a functioning subscription flow. The Brevo task owns consent, double opt-in, offer delivery, form outcomes, and its own validation and release. Its planned integration point is `[bactive_newsletter_form source="footer"]` inside the approved signup layout.

## Delivery constraints

Stage and verify the exact footer files before production. Use the project's serialized shared-host writer queue, fresh verified off-server backups, narrow file deployment, destination readback, and file-only rollback. Preserve unrelated payment, email, hero, global CSS, and existing local work. A preview or merged PR is not evidence that the public footer is live.
