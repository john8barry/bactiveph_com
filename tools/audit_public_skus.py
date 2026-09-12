#!/usr/bin/env python3
"""Anonymous, bounded same-origin SKU audit. Never prints SKU values or page bodies.

The private JSON inventory is an array of SKUs or an object with a `skus` array.
Exit 0: covered responses clean; 1: exposure; 2: incomplete/failed coverage.
This static audit does not replace browser and authenticated customer-flow tests.
"""
import argparse
from concurrent.futures import ThreadPoolExecutor
from html import unescape
from html.parser import HTMLParser
import hashlib
import json
from pathlib import Path
import re
import sys
from urllib.parse import urljoin, urlsplit, unquote
import xml.etree.ElementTree as ET
from audit_storefront_dashes import safe_url


def decode(text):
    for _ in range(4):
        new = unquote(unescape(text))
        new = re.sub(r'\\u([0-9a-fA-F]{4})', lambda m: chr(int(m[1], 16)), new)
        if new == text:
            break
        text = new
    return text


def inspect(text, skus):
    decoded = decode(text)
    findings = []
    for sku in skus:
        if not sku:
            continue
        # A short SKU must be a token, not coincidental bytes in a hash/script.
        pattern = re.escape(sku)
        if len(sku) <= 3:
            pattern = r'(?<![A-Za-z0-9])' + pattern + r'(?![A-Za-z0-9])'
        matches = list(re.finditer(pattern, decoded, re.I))
        if matches:
            findings.append({'kind': 'sku_value', 'identifier_hash': hashlib.sha256(sku.encode()).hexdigest()[:12], 'count': len(matches)})
    for kind, pattern in (
        ('sku_attribute', r'\bdata-(?:product[_-])?sku\s*='),
        ('sku_payload_property', r'["\'](?:_sku|sku|product_sku|variation_sku)["\']\s*:'),
        ('sku_label', r'>\s*SKU\s*:'),
    ):
        count = len(re.findall(pattern, decoded, re.I))
        if count:
            findings.append({'kind': kind, 'count': count})
    return findings


class Links(HTMLParser):
    def __init__(self):
        super().__init__(); self.links = []
    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            self.links.extend(value for key, value in attrs if key == 'href' and value)


def fetch(url, base):
    import requests  # Existing operator environment; offline regression tests need no network dependency.
    # Validate each redirect before following; never carry credentials or hit actions.
    current = url
    for _ in range(5):
        response = requests.get(current, timeout=(10, 25), allow_redirects=False,
                                headers={'User-Agent': 'BActive-SKU-Privacy-Audit/1.0'})
        if response.is_redirect:
            target = urljoin(current, response.headers.get('Location', ''))
            if urlsplit(target).netloc != urlsplit(base).netloc or not safe_url(target, base):
                raise ValueError('Unsafe redirect')
            current = target
            continue
        response.raise_for_status()
        if len(response.content) > 8_000_000:
            raise ValueError('Response too large')
        return current, response.text
    raise ValueError('Redirect limit')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-url', required=True, choices=['https://bactiveph.com', 'https://staging.bactiveph.com'])
    parser.add_argument('--sku-file', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--url-file', type=Path, help='Private authenticated WordPress URL manifest; replaces sitemap discovery on noindex staging')
    parser.add_argument('--max-pages', type=int, default=200)
    args = parser.parse_args()
    inventory = json.loads(args.sku_file.read_text())
    skus = inventory.get('skus') if isinstance(inventory, dict) else inventory
    if not isinstance(skus, list) or not skus or any(not isinstance(x, str) for x in skus):
        parser.error('A nonempty private SKU inventory is required')
    base = args.base_url
    pending = {base + p for p in ['/', '/shop/', '/shop/page/2/', '/cart/', '/checkout/', '/my-account/', '/?s=dress&post_type=product']}
    if args.url_file:
        urls = json.loads(args.url_file.read_text())
        if not isinstance(urls, list) or any(not isinstance(url, str) or not safe_url(url, base) for url in urls):
            parser.error('URL manifest must contain only safe same-origin public URLs')
        pending.update(urls)
    else:
        pending.add(base + '/wp-sitemap.xml')
    seen = set(); results = []; errors = []
    while pending and len(seen) < args.max_pages:
        batch = sorted(pending - seen)[:min(4, args.max_pages-len(seen))]
        if not batch:
            break
        pending.difference_update(batch); seen.update(batch)
        with ThreadPoolExecutor(max_workers=4) as pool:
            futures = [(url, pool.submit(fetch, url, base)) for url in batch]
            for url, future in futures:
                try:
                    final, text = future.result()
                    results.append({'url': url, 'findings': inspect(text, skus)})
                    if urlsplit(final).path.endswith('.xml'):
                        tree = ET.fromstring(text)
                        for node in tree.iter():
                            if node.tag.endswith('}loc') or node.tag == 'loc':
                                candidate = safe_url(node.text or '', base)
                                if candidate and candidate not in seen:
                                    pending.add(candidate)
                    else:
                        links = Links(); links.feed(text)
                        for href in links.links:
                            candidate = safe_url(urljoin(final, href), base)
                            if candidate and candidate not in seen:
                                pending.add(candidate)
                except Exception as error:
                    errors.append({'url': url, 'error': type(error).__name__})
    # Explicit public API reads; no cart/order mutations or privileged endpoints.
    for suffix in ['/wp-json/wc/store/v1/products?per_page=100', '/wp-json/wc/store/v1/cart']:
        url = base + suffix
        try:
            _, text = fetch(url, base)
            data = json.loads(text)
            results.append({'url': url, 'findings': inspect(text, skus)})
            if suffix.startswith('/wp-json/wc/store/v1/products') and not isinstance(data, list):
                raise ValueError('Unexpected API response')
        except Exception as error:
            errors.append({'url': url, 'error': type(error).__name__})
    remaining = len(pending - seen)
    report = {'site': base, 'pages': len(results), 'findings': sum(len(x['findings']) for x in results),
              'errors': errors, 'remaining_pages': remaining, 'results': results}
    # URLs can themselves contain identifiers. This report stays private.
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2)); args.output.chmod(0o600)
    print(json.dumps({key: report[key] for key in ['site','pages','findings','remaining_pages']} | {'errors': len(errors)}))
    return 2 if errors or remaining else 1 if report['findings'] else 0


if __name__ == '__main__':
    sys.exit(main())
