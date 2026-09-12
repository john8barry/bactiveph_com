# Email drafts for account setup

These reviewed templates are rendered by `scripts/render_brevo_templates.py`. Sender and Reply-To: B Active <hello@bactiveph.com>. Keep the Free-plan Brevo branding added by the provider. Do not activate workflows until end-to-end acceptance.

| File | Subject | Event | Exact stage |
|---|---|---|---|
| [doi.html](doi.html) | Confirm your B Active signup | Native DOI request only; never promotional | None |
| [welcome.html](welcome.html) | Welcome to the B Active club | ba_welcome_ready | `welcome` |
| [cart-2h.html](cart-2h.html) | Still thinking it over? | ba_cart_reminder_ready | `2h` |
| [cart-24h.html](cart-24h.html) | A little reminder from B Active | ba_cart_reminder_ready | `24h` |
| [care.html](care.html) | A little care goes a long way | ba_post_purchase_ready | `care` |
| [review.html](review.html) | How are your B Active pieces? | ba_post_purchase_ready | `review` |
| [winback.html](winback.html) | Your next court day starts here | ba_winback_ready | `90d` |

The templates use the B Active logo stored in Brevo's account-owned content library and the permanent sage, ivory and charcoal palette. The storefront copy of the logo is the canonical input, but Gmail's image proxy cannot retrieve it through the storefront's bot protection. Inbox-safe font fallbacks preserve legibility where web fonts are blocked. The review request collects feedback by reply; it does not assume a product review URL. Care is keyed to recorded payment and does not claim delivery. Cart links use the public cart URL and contain no session restoration or payment secrets. All marketing recipients must still pass local consent and current eligibility checks at dispatch.

In Brevo, verify the precise stage values emitted by the final adapter and route one immediate email per event. Do not add a second delay or enable a separate automatic final-confirmation email that duplicates welcome. Keep workflows paused during import and template testing.

The DOI button uses the [documented doubleoptin variable](https://help.brevo.com/hc/en-us/articles/4402386448530--Manual-Personalize-your-messages-with-dynamic-content-Brevo-Template-Language); marketing footers use the [documented unsubscribe variable](https://help.brevo.com/hc/en-us/articles/209553645-Insert-a-custom-unsubscribe-link-in-your-emails). Provider readback matches all seven local templates. Desktop Gmail rendering and inbox receipt passed for the final Welcome test; unsubscribe execution, mobile rendering, and workflow-triggered delivery remain acceptance gates.
