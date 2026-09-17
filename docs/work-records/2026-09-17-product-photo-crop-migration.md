# Product photo crop migration — 2026-09-17

Work item: [#115](https://github.com/john8barry/bactiveph_com/issues/115). Owner: B Active product-photo task. Severity: medium storefront presentation and catalogue image integrity. This record follows the [source audit](../catalogue/product-photo-audit-2026-09-16.md) and [layout/validation release](2026-09-16-product-photo-release.md).

## Live result

John authorized exact pixel cropping/resizing. Forty-one reviewed non-portrait originals were cropped to 2:3 without resampling or changing subject pixels. Source SHA-256 was verified before cropping; decoded output pixels were independently compared to each selected source rectangle. New opaque PNG attachments preserve the original files. Attachment 593 used an offset crop to keep the forearm; 592 was held because no safely framed portrait crop fits both arms.

The 41 derivatives were imported as new Media Library attachments and reviewed against their exact bytes under the live assignment validator. Sixteen published products had 151 image metadata fields changed, covering 181 featured/gallery/variation/colour-preview references. Match Dress source 473 (new attachment 979) also replaced padded featured attachment 600. The grey bands embedded in 600 are gone from the live featured photo. Product prices, stock, options, descriptions and original files were not changed.

Live checks: all 151 new metadata values read back; all 111 original SHA-256 hashes matched; all 41 derivative files and reviews matched; no price, stock, status or attribute drift; 41 derivative URLs answered HEAD 200, 16 affected product pages and 2 collection pages answered GET 200. Match Dress desktop/mobile inspection showed the 965 × 1448 new photo filling its frame; on mobile the image and frame both measured 343.1875 × 514.953125. On the Serve Dress mobile page, selecting Sakura Pink and size S loaded its 965 × 1448 reviewed variation image at 343.1875 × 514.96875, visually filling the frame. The production PHP error log had not changed since the migration; there were no new critical entries in its inspected tail. The live assignment-control file still matched the released SHA-256 `2c29931c615870c2f16943a6abb281e8438cceda850bb080b251992dd7efb80c`. All 16 products passed a conditional rollback dry run. After cache purge, monitoring completed 25 rounds over 1,206 seconds: 650 successful checks across 26 public URLs, zero failures.

## Source to reviewed attachment

| Source ID | New ID | Product ID(s) | Crop output | New SHA-256 |
|---:|---:|---|---:|---|
| 832 | 948 | 831 | 965 × 1448 | `eb13a87eab7965cea6ceb0cb4b5461d3f32ffec12e863e0dd9ab03d366dfcf08` |
| 575 | 949 | 573 | 853 × 1280 | `203d136f2180c31f18e1abe6cccb53bef76ae7644f2ddf6db7cb308136c611f8` |
| 576 | 950 | 573 | 853 × 1280 | `46a8e7fb0e92cf6b96310d0216e0ac70abc09da96022bf47cf00340ef9d7e1e8` |
| 577 | 951 | 573 | 853 × 1280 | `f25ebe62d75588119b545c503b2b73c7843fa6b6e03f188663e9817f4a637206` |
| 574 | 952 | 573 | 853 × 1280 | `a5654fe993acc23aa562e581be031604393727f89ed9f7b31c49d5f1c4e973c4` |
| 560 | 953 | 238 | 853 × 1280 | `3407d79957c499a69a0ed9e3bc403671751537d104d424f5c92423e45917ab3b` |
| 561 | 954 | 238 | 853 × 1280 | `ef6f9ba4703fd04b1ba677d7141f136081c38978c20ff759a40933dd07b9ca30` |
| 564 | 955 | 238 | 853 × 1280 | `960d1b8138c6be9a63ffd8ec724852e6012ab9074ad483c77e2734687410e4fd` |
| 827 | 956 | 238 | 965 × 1448 | `0f7ff490f81866cc6341b16c82bd6bc2f5915cd53264a525c168044e9effaeba` |
| 593 | 957 | 211 | 935 × 1402 | `0cc73b0faf583ad647dc784ac9aa2592f2e12f38d95d789437cef00b2dd4fabf` |
| 594 | 958 | 211 | 965 × 1448 | `8918f4cd359715e5aa817e604c7fefa03e844c264f5c25dc479cdadb817c4f1e` |
| 595 | 959 | 211 | 965 × 1448 | `1959777a990b33221caaa885d93bea748d80f9f88b97fb7893cfff4f64530de6` |
| 596 | 960 | 211 | 935 × 1402 | `7252b4819bc516d4e6694dda8c75401c59ef0a774bb3e0849577c96f763a0333` |
| 611 | 961 | 211 | 965 × 1448 | `1959777a990b33221caaa885d93bea748d80f9f88b97fb7893cfff4f64530de6` |
| 746 | 962 | 185 | 853 × 1280 | `ffdc384bf224decdbbd789fa3fbf721bfe1fb68b170e520ca0b7ba4512fec925` |
| 748 | 963 | 185 | 853 × 1280 | `afdc5b4e9e7f2c8d2aa26375260c34dda61b1cd820df2507b6d9067dadbe4643` |
| 743 | 964 | 185 | 853 × 1280 | `ad8adb7405e4f55ab99a26bdee0c2483cf2f021e499dc6fc22bef454774130cb` |
| 456 | 965 | 160 | 935 × 1402 | `0a9c92203b0bfa4f998085e165c25b0408ee889ba63bca809e3426f552005424` |
| 554 | 966 | 154 | 853 × 1280 | `160d2c24e5d5b80e54f0bf49fc636f146d65efcf586de3a3e4b3543ae3a436bc` |
| 541 | 967 | 154 | 853 × 1280 | `7679f66a5449f15c6d85161d5f4703b087cf1d4a1f850c19fbc9222f925bcc15` |
| 503 | 968 | 128 | 935 × 1402 | `fb484d6248025a2a1aedade7e666acad1d0f988cd8f82733e74fc4e9aa53770b` |
| 504 | 969 | 128 | 935 × 1402 | `cc95f741d71718d21ecb91743039a2a48b55af133eb6da87d383cbe9ae9c5e31` |
| 505 | 970 | 128 | 935 × 1402 | `890d32f1337fceea2743a52dfda3f9516815e24f07015640961d54e34cd25b95` |
| 502 | 971 | 128 | 935 × 1402 | `7d50d36f1ec46e8f3e9486ccbb2fa294e5c3d06854f63a0cb4ff5e775e93a7d4` |
| 463 | 972 | 117 | 935 × 1402 | `ddd66fd86bb5df328a400f8b5cf25bf5c3f4bb1739d98bdcbfdc598ae034c08c` |
| 462 | 973 | 117 | 935 × 1402 | `1c010312f36b0156c1bf8393ff68046d5a9ba3cf4a129d62f0f6b14e72b4a608` |
| 460 | 974 | 111 | 935 × 1402 | `c9b7b8dae6707a7be754150ec334cab211a4782ad077cb7331ddaf44efac87a9` |
| 450 | 975 | 95 | 935 × 1402 | `ee521f8b0ec07430f7d64e81a5b4ee1aaef91cb1e37fc10661b37aa4e262bd6a` |
| 451 | 976 | 95 | 935 × 1402 | `0de0a44d8aa0401f06cd6e949fa20feb209114913ddf1ab415ef17a8bcc17c4c` |
| 449 | 977 | 95 | 935 × 1402 | `52f5ca886ca6bb7630cf7e1bc2294a38d6e6244c02b48b2489a19b67778fb04b` |
| 472 | 978 | 89 | 965 × 1448 | `8e6970fc351d1dc74a33f4d5ddfbb008e9529e952e6e4a23dd8718532c407cab` |
| 473 | 979 | 83 | 965 × 1448 | `c73202558cb8373a0faedd9acfad69e2d5d5e43e2d04f4b866e517c922b15dee` |
| 381 | 980 | 56 | 853 × 1280 | `9c6864f2e16c1b83056e3bffd0f16e3a42c104f0620c094555b5415524b9c7bc` |
| 395 | 981 | 50 | 935 × 1402 | `30c90592847d95bfcac9fd496e27006e09949bc0bd9b2b140c9199421cb0fb1e` |
| 396 | 982 | 50 | 935 × 1402 | `bb71e328ef0f7388e5212988147faf72d7248e9280c593bceb48b3c2c03d3e93` |
| 477 | 983 | 50 | 935 × 1402 | `30c90592847d95bfcac9fd496e27006e09949bc0bd9b2b140c9199421cb0fb1e` |
| 475 | 984 | 50 | 935 × 1402 | `bb71e328ef0f7388e5212988147faf72d7248e9280c593bceb48b3c2c03d3e93` |
| 394 | 985 | 50 | 935 × 1402 | `7329f83663af4ec74627d86ef30f0146a0fbfb34d3c9150bfb2d40b9621cd3dd` |
| 390 | 986 | 36 | 935 × 1402 | `b7d446fdbd096fd8021b98553f7e257d373b7bac777cedcfcd455f80407f18c7` |
| 388 | 987 | 36 | 935 × 1402 | `1c283bc6214980c249eb9affc16ba2dc71a39dfc45d754b100bca65ed2448a67` |
| 387 | 988 | 36 | 935 × 1402 | `52eb95548ffc26101c92711da2a6a1b6b7ffa92f2a2d16acf765165a0dfaf171` |


Attachment 600 → 979 is an additional Match Dress mapping from the clean photograph at source 473. It is not a second derivative. The old 600 file and its revoked framing review remain intact for audit.

## Remaining source holds

- Attachment 592 (Ribbed Tank, product 211) is square. The narrowest ratio-tolerant crop leaves an arm against the edge; a strict 2:3 crop clips it. Keep the current assignment and obtain a safely framed portrait source or a verified background extension.
- Attachments 912/913 (Hole-In-One Set, product 911) are wide comparison images currently used by variations. Their responsive frames display at their natural ratio, while future variation assignments require portrait derivatives or approved alternative photos. Generated background extensions altered people/garment details and were rejected.
- Attachments 164 (Rally Skort) and 58/60/61 (Bubble Dress) are below the required resolution. An independent check of all 111 originals found no exact higher-resolution match. Obtain better originals or explicit merchant approval of verified same-product, same-colour alternatives. The ambiguous legacy colour mapping remains on hold.

Issue #115 stays open for these remaining source decisions. Current product pages can display the legacy sources without website-added side bars; the validator blocks unsuitable new assignments.

## Recovery

A restricted off-repository receipt retains the exact old/new image metadata values and all source/output hashes. The rollback script checks each current value against the expected derivative mapping and restores only image metadata for the affected product; it skips a product if any photo field has drifted. It does not restore the entire database, alter commerce fields, delete originals, or remove customer orders. The 16-product dry run passed. The verified pre-release Updraft backup remains a last-resort archive, not the preferred asset rollback.

The temporary import staging directory outside the web root was removed after its exact 41-file contents and imported derivative hashes were reconciled. The live Media Library derivatives and the restricted local evidence remain available for recovery.
