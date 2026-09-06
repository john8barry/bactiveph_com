#!/usr/bin/env python3
"""Read-only, anonymous storefront punctuation audit (Python standard library).

Example: python3 tools/audit_storefront_dashes.py --base-url https://bactiveph.com \
  --url '/?s=shirt' --url /shop/page/2/ --url /cart/ --url /my-account/

Exit 0 means the discovered static public surface passed; 1 means punctuation
hits; 2 means incomplete coverage or a fetch/parse error. This does not execute
JavaScript, authenticate, submit forms, follow action URLs, or inspect customer
records. Browser verification remains necessary for dynamic and gated screens.
"""

import argparse
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass
from html import unescape
from html.parser import HTMLParser
import json
import re
import sys
from urllib.error import HTTPError
from urllib.parse import parse_qsl, unquote, urlencode, urljoin, urlsplit, urlunsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener
import xml.etree.ElementTree as ET


DASHES = re.compile("[\u2013\u2014]")
SAFE_QUERY = {"s", "paged", "page", "product-page", "orderby", "post_type"}
BLOCKED_PATH = re.compile(
    r"/(?:wp-admin|wp-json|wp-login\.php|wp-cron\.php|xmlrpc\.php|"
    r"wp-comments-post\.php|logout|customer-logout|order-pay|order-received|"
    r"add-payment-method|delete-payment-method|set-default-payment-method|"
    r"add-to-cart|remove-item|empty-cart|downloads|view-order)(?:/|$)", re.I
)
ASSET_SUFFIX = re.compile(
    r"\.(?:jpg|jpeg|png|gif|webp|avif|svg|ico|pdf|zip|mp4|webm|mp3|woff2?|ttf|js)$", re.I
)
CSS_CONTENT = re.compile(r"\bcontent\s*:\s*((?:\"(?:\\.|[^\"])*\"|'(?:\\.|[^'])*'|[^;}\"'])*)", re.I)
CSS_STRING = re.compile(r'''"((?:\\.|[^"\\])*)"|'((?:\\.|[^'\\])*)' ''', re.X)


def safe_url(url, base, *, asset=False):
    """Return a canonical safe same-origin GET URL, or None. Unknown queries deny."""
    parts = urlsplit(urljoin(base, url))
    origin = urlsplit(base)
    if (parts.scheme, parts.netloc) != (origin.scheme, origin.netloc):
        return None
    if parts.username or parts.password or BLOCKED_PATH.search(unquote(parts.path)):
        return None
    if unquote(parts.path).lower().endswith(".php") and parts.path != "/index.php":
        return None
    pairs = parse_qsl(parts.query, keep_blank_values=True)
    allowed_query = SAFE_QUERY | ({"ver"} if asset and parts.path.endswith(".css") else set())
    if any(key not in allowed_query for key, _ in pairs):
        return None
    if any(key == "post_type" and value != "product" for key, value in pairs):
        return None
    if not asset and (ASSET_SUFFIX.search(parts.path) or parts.path.endswith(".css")):
        return None
    return urlunsplit((parts.scheme, parts.netloc, parts.path or "/", urlencode(sorted(pairs)), ""))


def decoded(value):
    # Handle literal double-encoded entities without interpreting arbitrary escapes.
    for _ in range(3):
        new = unescape(value)
        if new == value:
            break
        value = new
    return value


def finding(location, value):
    value = decoded(value)
    matches = list(DASHES.finditer(value))
    if not matches:
        return None
    first = matches[0].start()
    return {"location": location, "count": len(matches),
            "en_dashes": value.count("\u2013"), "em_dashes": value.count("\u2014"),
            "context": re.sub(r"\s+", " ", value[max(0, first - 55):first + 65])}


def css_findings(css, location):
    results = []
    # Comments cannot render. Parse all quoted strings in a content declaration,
    # including multiple strings and a quoted fallback inside attr().
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    for match in CSS_CONTENT.finditer(css):
        for string in CSS_STRING.finditer(match.group(1)):
            value = string.group(1) if string.group(1) is not None else string.group(2)
            value = re.sub(r"\\([0-9a-fA-F]{1,6})(?:\s)?",
                           lambda m: chr(int(m.group(1), 16)) if int(m.group(1), 16) <= 0x10FFFF else "\ufffd", value)
            hit = finding(location, value)
            if hit:
                results.append(hit)
    return results


def json_findings(value, path="json"):
    results = []
    if isinstance(value, str):
        hit = finding(path, value)
        if hit:
            results.append(hit)
    elif isinstance(value, list):
        for index, item in enumerate(value):
            results.extend(json_findings(item, f"{path}[{index}]"))
    elif isinstance(value, dict):
        for key, item in value.items():
            results.extend(json_findings(item, f"{path}.{key}"))
    return results


class PageParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.hits, self.links, self.stylesheets, self.errors = [], [], [], []
        self.special = None
        self.buffer = []
        self.json_script = False
        self.in_title = False

    def record(self, location, value):
        hit = finding(location, value)
        if hit:
            self.hits.append(hit)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "a" and attrs.get("href"):
            self.links.append(attrs["href"])
        if tag == "link" and "stylesheet" in attrs.get("rel", "").split() and attrs.get("href"):
            self.stylesheets.append(attrs["href"])
        for key in ("alt", "aria-label", "aria-description", "aria-valuetext", "title", "placeholder"):
            if attrs.get(key):
                self.record(f"{tag}@{key}", attrs[key])
        if tag == "meta" and attrs.get("content"):
            self.record("meta@" + attrs.get("name", attrs.get("property", "content")), attrs["content"])
        if tag == "input" and attrs.get("type", "").lower() in {"submit", "button", "reset"}:
            self.record("input@value", attrs.get("value", ""))
        if attrs.get("style"):
            self.hits.extend(css_findings(attrs["style"], f"{tag}@style:content"))
        if tag in {"script", "style"}:
            self.special, self.buffer = tag, []
            self.json_script = attrs.get("type", "").lower().split(";")[0] in {"application/ld+json", "application/json"}
        if tag == "title":
            self.in_title = True

    def handle_endtag(self, tag):
        if tag == self.special:
            raw = "".join(self.buffer)
            if tag == "style":
                self.hits.extend(css_findings(raw, "style:content"))
            elif self.json_script:
                try:
                    self.hits.extend(json_findings(json.loads(raw)))
                except (ValueError, RecursionError):
                    self.errors.append("Malformed JSON script; string coverage incomplete")
            self.special, self.buffer = None, []
        if tag == "title":
            self.in_title = False

    def handle_data(self, data):
        if self.special:
            self.buffer.append(data)
        else:
            self.record("title" if self.in_title else "text", data)

    def close(self):
        super().close()
        if self.special:
            self.errors.append("Unclosed script/style element; coverage incomplete")


@dataclass
class Response:
    url: str
    final_url: str
    status: int
    kind: str
    body: str
    error: str = ""


class SafeRedirect(HTTPRedirectHandler):
    def __init__(self, base):
        self.base = base

    def redirect_request(self, request, fp, code, msg, headers, newurl):
        if not safe_url(newurl, self.base, asset=True):
            raise ValueError("Redirect outside allowed anonymous public URLs")
        return super().redirect_request(request, fp, code, msg, headers, newurl)


def fetch(url, base, timeout=25):
    try:
        opener = build_opener(SafeRedirect(base))
        req = Request(url, headers={"User-Agent": "BactivePH-Punctuation-Audit/1.0", "Accept": "text/html,application/xml,text/css;q=0.9,*/*;q=0.1"})
        with opener.open(req, timeout=timeout) as response:
            raw = response.read(5_000_001)
            if len(raw) > 5_000_000:
                raise ValueError("Response exceeds 5 MB coverage limit")
            return Response(url, response.url, response.status, response.headers.get_content_type(),
                            raw.decode(response.headers.get_content_charset() or "utf-8", errors="strict"))
    except HTTPError as exc:
        return Response(url, exc.url, exc.code, "", "", "HTTP error")
    except Exception as exc:
        # Never print response bodies or server diagnostics on failure.
        return Response(url, url, 0, "", "", type(exc).__name__ + ": fetch failed")


def discover_sitemap(base, explicit, max_urls):
    pages, errors, sitemap_seen = set(), [], set()
    if explicit:
        pending = list(explicit)
    else:
        robots = fetch(urljoin(base, "/robots.txt"), base)
        pending = re.findall(r"^Sitemap:\s*(\S+)", robots.body, re.M | re.I) if not robots.error else []
        if not pending:
            for path in ("/wp-sitemap.xml", "/sitemap_index.xml", "/sitemap.xml"):
                candidate = urljoin(base, path)
                result = fetch(candidate, base)
                if not result.error and ("<sitemapindex" in result.body or "<urlset" in result.body):
                    pending = [candidate]
                    break
    if not pending:
        return pages, ["No accessible public sitemap discovered"], []
    while pending:
        target = safe_url(pending.pop(), base, asset=True)
        if not target:
            errors.append("Sitemap URL outside safe same-origin scope")
            continue
        if target in sitemap_seen:
            continue
        sitemap_seen.add(target)
        if len(sitemap_seen) > max_urls:
            errors.append("Sitemap count cap reached")
            break
        response = fetch(target, base)
        if response.error:
            errors.append(f"Sitemap fetch failed: {target} ({response.status})")
            continue
        try:
            root = ET.fromstring(response.body)
            kind = root.tag.rsplit("}", 1)[-1]
            if kind not in {"sitemapindex", "urlset"}:
                raise ValueError("Unexpected sitemap root")
            for child in root:
                for entry in child:
                    if entry.tag.rsplit("}", 1)[-1] != "loc" or not entry.text:
                        continue
                    if kind == "sitemapindex":
                        pending.append(entry.text.strip())
                    else:
                        allowed = safe_url(entry.text.strip(), base)
                        if allowed:
                            pages.add(allowed)
                        else:
                            errors.append(f"Sitemap includes out-of-scope URL: {target}")
        except (ET.ParseError, ValueError):
            errors.append(f"Sitemap parse failed: {target}")
    return pages, errors, sorted(sitemap_seen)


def audit(base, urls, sitemaps, max_urls=1000, workers=3):
    pending, errors, discovered = discover_sitemap(base, sitemaps, max_urls)
    pending.add(base)
    for url in urls:
        allowed = safe_url(url, base)
        if allowed:
            pending.add(allowed)
        else:
            errors.append("Explicit URL rejected by anonymous GET safety policy")
    seen, records, stylesheet_urls = set(), [], set()
    with ThreadPoolExecutor(max_workers=workers) as pool:
        while pending and len(seen) < max_urls:
            batch = sorted(pending)[:min(workers, max_urls - len(seen))]
            pending.difference_update(batch)
            seen.update(batch)
            for response in pool.map(lambda url: fetch(url, base), batch):
                record = {"url": response.url, "final_url": response.final_url, "status": response.status,
                          "redirected": response.url != response.final_url, "findings": [], "errors": []}
                if response.error:
                    record["errors"].append(response.error)
                elif response.kind not in {"text/html", "application/xhtml+xml"}:
                    record["errors"].append("Expected HTML public page")
                else:
                    parser = PageParser()
                    parser.feed(response.body)
                    parser.close()
                    record["findings"], record["errors"] = parser.hits, parser.errors
                    for link in parser.links:
                        allowed = safe_url(urljoin(response.final_url, link), base)
                        if allowed and allowed not in seen:
                            pending.add(allowed)
                    for link in parser.stylesheets:
                        allowed = safe_url(urljoin(response.final_url, link), base, asset=True)
                        if allowed:
                            stylesheet_urls.add(allowed)
                records.append(record)
        if pending:
            errors.append(f"URL cap reached with {len(pending)} pending pages; coverage incomplete")
        css_limit = max_urls - len(seen)
        if len(stylesheet_urls) > css_limit:
            errors.append("Combined page/stylesheet URL cap reached; CSS coverage incomplete")
        for response in pool.map(lambda url: fetch(url, base), sorted(stylesheet_urls)[:max(0, css_limit)]):
            css_error = response.error or ("Expected CSS stylesheet" if response.kind != "text/css" else "")
            records.append({"url": response.url, "final_url": response.final_url, "status": response.status,
                            "redirected": response.url != response.final_url,
                            "findings": css_findings(response.body, "stylesheet:content") if not css_error else [],
                            "errors": [css_error] if css_error else []})
    count = sum(hit["count"] for record in records for hit in record["findings"])
    incomplete = bool(errors or any(record["errors"] for record in records))
    return {"base_url": base, "coverage": "incomplete" if incomplete else "complete_static_public",
            "limitations": ["No JavaScript execution or interaction", "No authenticated or customer-specific screens",
                            "External-origin assets and unsafe/action URLs excluded"],
            "sitemaps": discovered, "urls_checked": len(records), "dash_count": count,
            "errors": errors, "results": records}, 2 if incomplete else (1 if count else 0)


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--base-url", default="https://bactiveph.com/")
    parser.add_argument("--url", action="append", default=[], help="Additional safe anonymous GET URL")
    parser.add_argument("--sitemap", action="append", default=[])
    parser.add_argument("--max-urls", type=int, default=1000)
    parser.add_argument("--workers", type=int, choices=(1, 2, 3), default=3)
    args = parser.parse_args()
    base = safe_url(args.base_url, args.base_url)
    if not base or urlsplit(base).scheme not in {"https", "http"} or args.max_urls < 1:
        parser.error("Valid public HTTP(S) base URL and positive max-urls required")
    report, code = audit(base, args.url, args.sitemap, args.max_urls, args.workers)
    print(json.dumps(report, indent=2, ensure_ascii=True))
    return code


if __name__ == "__main__":
    sys.exit(main())
