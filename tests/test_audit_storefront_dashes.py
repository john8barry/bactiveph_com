"""Regression tests for detection and side-effect-free URL selection."""
import importlib.util
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

PATH = Path(__file__).resolve().parents[1] / "tools" / "audit_storefront_dashes.py"
SPEC = importlib.util.spec_from_file_location("dash_audit", PATH)
audit = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = audit
SPEC.loader.exec_module(audit)


class DetectionTests(unittest.TestCase):
    def test_variation_descriptions_and_json_escapes(self):
        page = audit.PageParser()
        page.feed('''<form data-product_variations='[{"variation_description":"Light \\u2014 breathable","price_html":"10 &amp;ndash; 20"}]'></form>''')
        self.assertEqual(sum(hit["count"] for hit in page.hits), 2)
        self.assertFalse(page.errors)

    def test_entities_attributes_hidden_and_json(self):
        page = audit.PageParser()
        page.feed('''<title>Shop &ndash; B Active</title><meta name="description" content="fit &#8212; fun">
          <div hidden>10&#x2013;20</div><img alt="top &mdash; black">
          <script type="application/ld+json">{"name":"Top \\u2014 Black","offers":[{"description":"1 &ndash; 2"}]}</script>
          <script>const irrelevant = "\u2014";</script><style>.x { color: red; /* \u2014 */ }</style>''')
        self.assertEqual(sum(hit["count"] for hit in page.hits), 6)
        self.assertFalse(page.errors)

    def test_css_content_and_ordinary_hyphens(self):
        page = audit.PageParser()
        page.feed('''<style>.range::after {content: "\\2013 "} .other {content: '\u2014';}</style>
          <b style="content: '&mdash;'">zip-up, size 10-20</b>''')
        self.assertEqual(sum(hit["count"] for hit in page.hits), 3)
        self.assertIsNone(audit.finding("text", "zip-up 10-20"))

    def test_bad_json_is_coverage_error(self):
        page = audit.PageParser()
        page.feed('<script type="application/ld+json">{broken}</script>')
        self.assertEqual(len(page.errors), 1)

    def test_multiple_css_strings_and_comments(self):
        hits = audit.css_findings('/* content: "\u2014"; */ .x { content: "range" "\\2013 " / "\u2014"; }', "css")
        self.assertEqual(sum(hit["count"] for hit in hits), 2)


class URLSafetyTests(unittest.TestCase):
    base = "https://bactiveph.com/"

    def test_anonymous_newsletter_pages_only(self):
        for value in ("subscriptions", "captcha"):
            self.assertIsNotNone(audit.safe_url('/?mailpoet_page=' + value, self.base))
        for url in ('/?mailpoet_page=unsubscribe', '/?mailpoet_page=subscriptions&token=secret',
                    '/cdn-cgi/l/email-protection'):
            self.assertIsNone(audit.safe_url(url, self.base))

    def test_actions_tracking_credentials_and_external_denied(self):
        for url in ("/?add-to-cart=12", "/cart/?remove_item=abc", "/?_wpnonce=abc", "/?action=delete",
                    "/wp-admin/", "/%77p-admin/", "/wp-json/", "/my-account/customer-logout/", "/checkout/order-pay/12/",
                    "https://elsewhere.example/", "https://user:pass@bactiveph.com/", "/?unknown=1"):
            with self.subTest(url=url):
                self.assertIsNone(audit.safe_url(url, self.base))

    def test_anonymous_regressions_allowed(self):
        for url in ("/?s=shirt&post_type=product", "/shop/page/2/", "/cart/", "/checkout/", "/my-account/"):
            with self.subTest(url=url):
                self.assertIsNotNone(audit.safe_url(url, self.base))
        self.assertEqual(audit.safe_url("/shop/#tab", self.base), self.base + "shop/")
        self.assertIsNotNone(audit.safe_url("/wp-content/style.css?ver=1", self.base, asset=True))
        self.assertIsNone(audit.safe_url("/wp-content/style.css?ver=1&action=delete", self.base, asset=True))

    def test_cap_is_incomplete_and_does_not_fetch_action(self):
        called = []
        def fetch(url, base):
            called.append(url)
            return audit.Response(url, url, 200, "text/html", '<a href="/?add-to-cart=1">Buy</a><a href="/next/">Next</a>')
        with patch.object(audit, "discover_sitemap", return_value=(set(), [], [self.base + "sitemap.xml"])), patch.object(audit, "fetch", side_effect=fetch):
            report, code = audit.audit(self.base, [], [], max_urls=1)
        self.assertEqual(code, 2)
        self.assertEqual(report["coverage"], "incomplete")
        self.assertEqual(called, [self.base])

    def test_unsafe_redirect_denied_before_follow(self):
        handler = audit.SafeRedirect(self.base)
        with self.assertRaises(ValueError):
            handler.redirect_request(None, None, 302, "", {}, self.base + "?add-to-cart=1")

    def test_recursive_sitemap_and_hit_exit(self):
        fixtures = {
            self.base + "sitemap.xml": ('application/xml', '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://bactiveph.com/pages.xml</loc></sitemap></sitemapindex>'),
            self.base + "pages.xml": ('application/xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://bactiveph.com/shop/</loc></url></urlset>'),
            self.base: ('text/html', '<p>Welcome</p>'),
            self.base + "shop/": ('text/html', '<p>1&ndash;10</p>'),
        }
        def fetch(url, base):
            kind, body = fixtures[url]
            return audit.Response(url, url, 200, kind, body)
        with patch.object(audit, "fetch", side_effect=fetch):
            report, code = audit.audit(self.base, [], [self.base + "sitemap.xml"])
        self.assertEqual(code, 1)
        self.assertEqual(report["coverage"], "complete_static_public")
        self.assertEqual(report["dash_count"], 1)
        self.assertEqual(len(report["sitemaps"]), 2)

    def test_fetch_failure_is_incomplete_even_without_dashes(self):
        with patch.object(audit, "discover_sitemap", return_value=(set(), [], [])), patch.object(audit, "fetch", return_value=audit.Response(self.base, self.base, 503, "", "", "HTTP error")):
            report, code = audit.audit(self.base, [], [])
        self.assertEqual(code, 2)
        self.assertEqual(report["dash_count"], 0)


if __name__ == "__main__":
    unittest.main()
