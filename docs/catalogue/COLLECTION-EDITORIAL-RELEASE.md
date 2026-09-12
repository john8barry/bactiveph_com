# Collection and editorial release interface

Both features are off until the internal `bactive_collection_visual_release` option (or its existing filter) contains a valid version. No database page rewrite is required.

```php
[
    'version' => 'catalogue-reviewed-1',
    'enabled' => false, // Collection cards only.
    'product_ids' => [117, 154], // Exact integers, explicitly reviewed products.
    'editorial' => [
        'enabled' => false, // Independent of collection cards.
        'source_sha256' => 'SHA256_OF_SERIALIZE_BLOCK_FOR_REVIEWED_FIT_GROUP',
        // Optional positive attachment ID, only after image review/upload:
        // 'attachment_id' => 123,
    ],
]
```

Capture the exact `core/group` containing the fit heading from `parse_blocks(get_post(14)->post_content)`. Review that group in full, then compute `hash('sha256', serialize_block($group))`. Do not hash page 304, which is Fabric & Care. The render hook requires front page 14 and that exact hash. A changed block, disabled feature, different page, invalid hash, or unavailable HTML processor leaves the original section unchanged.

The matching group receives `bactive-editorial-existing`. Original factual text and size-guide link remain untouched. An optional attachment swaps image attributes only if the section contains exactly one image and WordPress supplies a replacement image. No macros or new claims are generated. The original image remains when replacement cannot qualify. Disabling editorial restores original rendering immediately.

Collection colour previews use `bactive_catalog_visuals_registry()` and the shared approved palette mapper (`bactive_catalog_visuals_palette`). The per-product `reviewed` flag must be exactly `true`, independently of its product-page `enabled` flag. Held or unreviewed products receive no previews. Turning product enhancements off leaves approved card previews available when cards are enabled. Links include the real colour query argument and lead to the product page; they never perform quick add. Actual product variation attributes restrict which palette entries appear. WooCommerce keeps price, action, inventory, and cart ownership.

The legacy explicit `[bactive_editorial]` shortcode accepts a published, unprotected `page_id` CTA. Do not insert this shortcode for the homepage fit release: the render hook already restyles the existing section, preventing duplication.

## Verification

`php tests/collection-visuals.php` covers option fallback, independent toggles, exact product IDs, invalid version/palette, escaped labels and headings, published-page CTA, source hash guard, wrong-page guard, preserved copy/link, and replacement image/stale srcset handling using the bundled WordPress HTML tag processor.

After integration, inspect real restored Blocksy markup at desktop and mobile. Confirm four/two columns, preview placement outside native card links, original prices/actions, one editorial section, complete garment framing, unchanged size-guide destination, and ordinary cached production pages after release. Tests alone do not constitute browser or live acceptance.
