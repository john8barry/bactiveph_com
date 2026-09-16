# Product photo assignment policy

New main photos, variation photos and colour previews must be opaque, upright JPEG, PNG or WebP images in 2:3 portrait proportion, minimum 800 × 1200 pixels; 1024 × 1536 is preferred. A 0.5% proportion tolerance accommodates pixel rounding. The decoder bounds files at 24 megapixels and 32 MB, also checking available PHP memory. Uploads for unrelated Media Library use remain unrestricted.

In Media Library, open the image, select **Product photo use**, inspect the complete original, and check **Framing review** only after confirming no added bars/borders, intact models and garments, and accurate colours and details. Save the attachment. The server decodes the image and binds review to the file's SHA-256 and selected role. A replacement file or different role requires another review. This is separate from a product's existing colour/variation review; both requirements continue to apply.

Select **Gallery detail only** or **Gallery comparison only** for an intentional exception. Such images need at least 800 × 800 pixels and cannot be selected as main, variation or preview portraits. The role alone grants no framing approval. Appearance and embedded-border checks require human review; dimensions alone cannot establish subject accuracy.

Invalid new assignments are rejected. Native WooCommerce and variation AJAX saves retain their former image choices while saving unrelated fields, with WooCommerce errors explaining how to correct the image. REST rejects invalid incoming image IDs before invoking the Woo callback; CSV imports report a failed row. REST image URL imports must upload and review the image through Media Library first, then assign its attachment ID. Legacy unchanged images do not block price/stock maintenance. Publishing a new/draft product checks all selected photos, including published variations; failures keep it as a draft. When correcting older invalid draft images in the classic editor, save corrections before publishing because WordPress updates publication status before WooCommerce saves its image metadata.

## Internal integration and rollback

`bactive_catalogue_photo_validate($attachment_id, $context = 'portrait', $require_review = true)` returns safe attachment data or `WP_Error`. The only other context is `gallery`. Setting `require_review` false is reserved for the framing-review workflow, never an assignment bypass.

Attachment metadata:

- `_bactive_photo_role`: `portrait` (default), `detail`, or `comparison`.
- `_bactive_photo_review`: `{schema_version: 1, sha256: <exact local file hash>, role: <role>}`.

Assignment guards cover `_thumbnail_id`, `_product_image_gallery`, `_bactive_colour_settings`, Woo product object saves, REST before-callback and pre-insert hooks, CSV pre-insert hooks and WordPress publication. Review controls require both attachment editing and product editing capabilities plus a dedicated nonce. Ordinary `update_post_meta`/`add_post_meta` paths cannot assign a newly invalid image. Direct SQL/database imports do not execute WordPress hooks and must run the same validator separately before changes.

No prior assignment or original file is removed by installing the policy. Roll back the release's theme module inclusion and editor check together using the recorded deployment backup. Retained attachment review metadata is inert if the policy is disabled. This module does not alter stock, prices, product holds, colour approval records or public API shapes.

Run `php tests/catalogue-photo-policy.php` for real image-decoder, native object, raw REST, CSV, metadata, publication and review-auth regression contracts; run the existing catalogue-settings/editor tests and independently verify native Woo routes before release.
