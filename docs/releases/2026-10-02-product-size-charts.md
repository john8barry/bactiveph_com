# October product size-chart update

Work record: issue [#74](https://github.com/john8barry/bactiveph_com/issues/74). Owner: John Barry / B Active. Medium sizing-guidance correction. The supplied Men's T-Shirt chart has different measurements from the shared polo chart previously shown on Everyday Active Tee; Essential Workout Shorts also needs its supplied chart. The three confirmed men's associations are live and verified. The two women's chart associations remain pending exact product identities.

## Confirmed products and original sources

Current published catalogue readback identifies the three men's products below. Each selected original is 853×1280 pixels and is copied without editing or recompression into the child theme's `assets/images/size-guides/` directory.

| Product | ID | Exact slug | Chart key | Supplied file ending | SHA-256 |
| --- | --- | --- | --- | --- | --- |
| Everyday Active Tee | 1079 | `everyday-active-tee` | `mens-tee` | `04.03.22.jpeg` | `a798443704d5adf34377a468ad802cbd717c1f54914ccb5c72429c6ed72be12c` |
| Everyday Active Polo | 1117 | `every-active-polo` | `mens-polo-tee` | `04.03.27.jpeg` | `a873c0e9161028a868126d8189ea179c5435abfe38ead75f80482a4c5cd06d7a` |
| Essential Workout Shorts | 1133 | `essential-workout-shorts` | `mens-shorts` | `04.03.21.jpeg` | `d67e65357474a15b6ecd7dcb21ddd18e54e350b6239c6e9dd927e2f22d3dd563` |

Source filenames begin `photo_2026-10-02 `; shipped assets use `<chart-key>-illustrated-20261002.jpg`. The new polo file preserves the prior chart's measurements but is not byte-identical to the September 21 JPEG. Its existing `mens-polo-tee` fallback URL remains usable and now belongs only to the polo. The chooser and heading say Men's Polo; the original image and its alternative description retain the supplied Polo / Tee wording. The old JPEG remains intact for scoped recovery. No men's-bottoms chart or asset exists in the inspected starting source at `bcded33021c0153ebcbc8e8a999fdb7dff411233`; the fresh production preflight confirmed this starting state.

Everyday Active Tee now gets its own Length/Bust/Hem Width/Sleeve Length chart, including size S values `68, 96, 96, 22.5`. The polo retains its Bust/Shoulder Width/Sleeve Length/Cuff chart, including size S values `98, 43, 20.5, 34`. Shorts retain the source's Length/Waist/Hip/Leg Opening values and its “one side, laid flat” leg-opening instruction without doubling or recalculating values.

Each exact product and selected fallback displays one original image, a full-size link and the complete hidden text alternative. Existing charts, invalid-query behavior and contact guidance for unmatched products remain intact. CSS, JavaScript, catalogue records, purchasable size options, inventory, prices, orders and payments are outside this change.

## Pending women's charts

Neither supplied women's product name appeared in the authenticated catalogue: 27 published products, two private test products, and no draft, pending or scheduled products. Confirm an existing product ID and slug from authenticated catalogue evidence or John before associating either chart. Do not map them to a similar title, add an unmatched public chooser entry or create a product from the image alone.

| Supplied chart | File ending | SHA-256 | Status |
| --- | --- | --- | --- |
| Clubhouse Zip Polo | `04.03.24.jpeg` | `adb1c2d8fa1985633ba2a2c41bbd256a0ba4a122b58ff784628c3e325fd599bc` | Original and all 30 measurements transcribed privately; no product association |
| Banded Waist Zip Polo | `04.03.25.jpeg` | `cf54503a521ad8f1a736810513104b71a45aaf088124961dcdbeefca0fc79c41` | Original and all 18 measurements transcribed privately; no product association |

Their source sizes S/M/L/XL/2XL/3XL, labels, measuring instructions and tolerance notes must remain unchanged. Completion of the men's work does not complete these two pending associations.

## Verification, release and rollback

All 25 focused size-guide tests pass after the final heading change. They cover original-image hashes, exact measurement transcription, the tee/polo split, preserved polo fallback URL, shorts routing, exclusion of unrelated/lookalike products, one chart per selected page, chooser links, invalid/nonscalar queries and unchanged dialog behavior. Both PHP mirrors lint and match; JavaScript syntax and diff whitespace checks pass. The writer verified that source outside the sizing block, CSS and JavaScript remain unchanged.

## Production receipt — October 2, 2026

Implementation [PR #155](https://github.com/john8barry/bactiveph_com/pull/155), source `4150b7998b892824fcb0d34d0fc97825725f7ccf`, merged as `e5547d7648c0b58ae60671b1c77fac05d164d746`. All five PR checks and all five main-branch checks passed. Independent source and release-helper review found no unresolved issues.

The verified target was `https://bactiveph.com`, active `blocksy-child`. Installation completed at Unix time `1790944595`. Only three new JPEGs and the sizing change in live `functions.php` were installed, images first and PHP last, after fresh destination-hash checks and server PHP lint. Installed PHP SHA-256: `f7ae6844980fe5b0c67285a66b55d979a1b899d413c410ffb5dc97baa39e1cf2`. All 43 protected theme files retained their baseline hashes, including production's separate `custom.css` and `footer-sage.php` changes. The dirty primary checkout was preserved.

A fresh successful Updraft backup (nonce `072ecb1f132f`, timestamp `1790942750`) contains seven archives covering six components, 568,646,513 bytes. Every archive passed remote SHA-256/size and gzip/ZIP integrity checks and was rehashed before installation. The full archives remain on the server: the attempted off-server transfer was incomplete because of transport throughput. The exact 24,922-byte PHP rollback preimage is separately saved off-server with SHA-256 `14826e1d72080b4ddbc6d4bb4ef72c315265a22cd59d7069f7650e7528beacfa`. This release used the explicitly scoped remote-full-backup plus off-server-file-preimage gate, not a claim of a complete off-server site backup.

After purging only the three affected product URLs and `/size-guide/`, independent public checks passed for all 27 published product routes, all ten standalone chart selections and three invalid/chooser cases. There are ten exact chart/product associations and 17 products retaining generic sizing help. All three live JPEG hashes match the supplied originals. Authenticated before/after catalogue fingerprints matched for all 27 products, with no additions, removals or changed catalogue fields.

Live browser checks at 1280×900 and 390×844 confirmed all three updated dialogs and standalone pages show their matching images. Desktop dialogs scroll; Escape and close restore focus to the Size Guide trigger. Mobile charts fit without horizontal overflow. The full-size T-shirt link opened the original 853×1280 JPEG in a new tab; all three full-size assets also passed independent HTTP/hash checks. Existing dialog JavaScript and sizing CSS were unchanged. One transient browser connection failure recovered in a fresh tab.

The post-release log/readback check at Unix time `1790945044`, 449 seconds after installation, confirmed the four deployed hashes and all protected files. `error_log` had 324 new bytes and zero new fatal/parse/uncaught entries; `wp-content/debug.log` was absent. No new critical PHP errors were found during that interval.

Private deployment, public-verification, catalogue comparison, backup qualification and monitor receipts are retained in the local `BactivePH/size-charts-20261002` application-support directory. No credentials or raw logs are included here.

## Guarded rollback

The release helper's `rollback` restores only the exact saved PHP preimage while current PHP matches the installed hash above. It then removes only this batch's three JPEGs, each guarded by its expected SHA-256. It refuses unknown later-writer bytes; if a destination has changed, inspect a fresh readback and reverse only this sizing delta instead. Purge the same four URLs and repeat public route/hash and PHP-error checks after any rollback. No database rollback is part of this change; preserve all commerce data and the separate live CSS/footer changes.

Keep issue #74 open for the two unmatched women's charts and remaining catalogue charts. The men's release does not complete those associations.
