#!/usr/bin/env python3
"""Render the reviewed B Active Brevo email templates."""

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "docs" / "operations" / "brevo-email-drafts"
# Use Brevo's content-library copy. Gmail's image proxy is blocked by the
# storefront's bot protection, while this account-owned CDN URL renders there.
LOGO = "https://img.mailinblue.com/12062675/images/content_library/original/6a9fb5b537f5ebb18d44b9dc.png"
ADDRESS = (
    "Unit No. C07, Lombardy Bldg., Palmetto Place, Purok 16, Gem Village, "
    "Ma-a, Talomo District, 8000 City of Davao, Davao del Sur, Philippines"
)


TEMPLATES = {
    "doi.html": {
        "title": "Confirm your B Active signup",
        "preheader": "One click to confirm your email.",
        "heading": "Confirm your signup",
        "paragraphs": [
            "Please confirm that you would like to join the B Active newsletter.",
            "By confirming, you agree: “I’d like B Active emails about new drops, offers and my shopping activity. I can unsubscribe anytime.”",
            "If you did not request this, ignore this email. You will not be subscribed.",
        ],
        "button": ("Confirm my signup", "{{ doubleoptin }}"),
        "marketing": False,
    },
    "welcome.html": {
        "title": "Welcome to the B Active club",
        "preheader": "Your first-order 5% code is inside.",
        "heading": "Made for your next move",
        "paragraphs": [
            "Thanks for joining us. From court days to everyday movement, we’re glad you’re here.",
            "Use <strong>{{ params.coupon_code }}</strong> for 5% off your first B Active order.",
            "One use per customer, on your first order only. Cannot be combined with another coupon.",
        ],
        "button": ("Shop B Active", "{{ params.shop_url }}"),
        "marketing": True,
    },
    "cart-2h.html": {
        "title": "Still thinking it over?",
        "preheader": "Take another look at B Active.",
        "heading": "Your next move is yours",
        "paragraphs": [
            "You left a few favourites in your bag. Take another look when you’re ready.",
            "Need help choosing a size? Reply to this email and we’ll help.",
        ],
        "button": ("View your bag", "{{ params.cart_url }}"),
        "marketing": True,
    },
    "cart-24h.html": {
        "title": "A little reminder from B Active",
        "preheader": "We’re here if you need a hand.",
        "heading": "Ready when you are",
        "paragraphs": [
            "Just a reminder about the pieces you were browsing. If you’re still deciding, we’re here to help.",
            "Questions about fit or care? Reply to this email.",
        ],
        "button": ("Take another look", "{{ params.cart_url }}"),
        "marketing": True,
    },
    "care.html": {
        "title": "A little care goes a long way",
        "preheader": "A quick note for your B Active pieces.",
        "heading": "Keep your favourites ready",
        "paragraphs": [
            "Thank you for your order. Here’s a simple way to get the best from your pieces: follow the care label on each item before washing.",
            "Sort similar colours together and check the label before using heat or putting an item in the dryer.",
            "If you’re unsure about care for a particular piece, reply to this email. We’re happy to help.",
        ],
        "button": ("Visit B Active", "{{ params.shop_url }}"),
        "marketing": True,
    },
    "review.html": {
        "title": "How are your B Active pieces?",
        "preheader": "We’d love to hear what you think.",
        "heading": "Tell us how it feels",
        "paragraphs": [
            "We hope your B Active pieces have found a place in your routine.",
            "How is the fit? What do you love, and what could be better? Reply to this email with your feedback. We read it and appreciate it.",
        ],
        "button": None,
        "marketing": True,
    },
    "winback.html": {
        "title": "Your next court day starts here",
        "preheader": "Come see what’s happening at B Active.",
        "heading": "Good to see you again",
        "paragraphs": [
            "It’s been a little while. Whenever you’re ready for your next move, come take a look at B Active.",
            "Need help finding something? Reply and tell us what you’re looking for.",
        ],
        "button": ("Explore B Active", "{{ params.shop_url }}"),
        "marketing": True,
    },
}


def render(spec):
    paragraphs = "\n".join(
        f'                <p style="margin:0 0 20px">{paragraph}</p>'
        for paragraph in spec["paragraphs"]
    )
    button = ""
    if spec["button"]:
        label, url = spec["button"]
        button = f"""
                <table role="presentation" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:28px 0">
                  <tr><td align="center" bgcolor="#242222" style="background-color:#242222;border-radius:3px">
                    <a href="{url}" style="display:inline-block;padding:14px 24px;color:#F9F7F4;font:700 16px/1 Arial,'Helvetica Neue',sans-serif;text-decoration:none">{label}</a>
                  </td></tr>
                </table>"""
    consent = ""
    unsubscribe = ""
    if spec["marketing"]:
        consent = (
            '                <p style="margin:28px 0 0;color:#5a5652;font-size:12px;line-height:1.6">'
            "You’re receiving this because you confirmed that you’d like B Active marketing emails.</p>"
        )
        unsubscribe = (
            ' · <a href="{{ unsubscribe }}" style="color:#242222;text-decoration:underline">Unsubscribe</a>'
        )
    return f"""<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{spec['title']}</title></head>
<body bgcolor="#F9F7F4" style="margin:0;padding:0;background-color:#F9F7F4;color:#242222;font:16px/1.6 Arial,'Helvetica Neue',sans-serif">
  <div style="display:none!important;max-height:0;max-width:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all">{spec['preheader']}</div>
  <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" bgcolor="#F9F7F4" style="width:100%;border-collapse:collapse;background-color:#F9F7F4">
    <tr><td align="center" style="padding:24px 12px">
      <table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" bgcolor="#F9F7F4" style="width:100%;max-width:600px;border-collapse:collapse;background-color:#F9F7F4">
        <tr><td align="center" bgcolor="#99AB90" style="background-color:#99AB90;padding:22px 24px">
          <a href="https://bactiveph.com/" style="display:inline-block;text-decoration:none">
            <img src="{LOGO}" width="260" alt="B Active — sportswear for every move" style="display:block;width:260px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none">
          </a>
        </td></tr>
        <tr><td bgcolor="#F9F7F4" style="background-color:#F9F7F4;padding:36px 28px 28px">
          <h1 style="margin:0 0 24px;color:#242222;font:700 32px/1.15 'Arial Narrow','Helvetica Neue',Arial,sans-serif">{spec['heading']}</h1>
{paragraphs}
{button}
                <p style="margin:32px 0 0">The B Active team</p>
{consent}
                <p style="margin:24px 0 0;border-top:1px solid #E4DCD2;padding-top:22px;color:#5a5652;font-size:12px;line-height:1.6">
                  B Active<br>{ADDRESS}<br>
                  <a href="mailto:hello@bactiveph.com" style="color:#242222;text-decoration:underline">hello@bactiveph.com</a><br>
                  <a href="https://bactiveph.com/privacy/" style="color:#242222;text-decoration:underline">Privacy policy</a>{unsubscribe}
                </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
"""


def main():
    for name, spec in TEMPLATES.items():
        (OUT / name).write_text(render(spec), encoding="utf-8")


if __name__ == "__main__":
    main()
