# GCash and direct-debit footer badges

Owner: footer task. Work record: https://github.com/john8barry/bactiveph_com/issues/112.
Base: ce131500. Branch: codex/gcash-footer.

## Scope

John confirmed GCash approval and that BPI/UBP Direct Debit work, and requested
matching payment branding. Add GCash after QR Ph plus BPI Direct Debit and UBP
Direct Debit using the existing matching bank SVG assets. Preserve Maya, GrabPay, ShopeePay,
eligible COD, PayMongo, and all four delivery partners. Four desktop columns and
three mobile columns preserve proportional matching badges. No gateway, checkout,
provider, order, or payment settings are changed.

Asset source: https://www.paymongo.com/images/logos/payment-methods/gcash.svg
Retrieved September 16, 2026, unchanged 121x49 SVG from the same badge family.
Only passive vector elements and one internal clipping reference are present.

## Dependency and release gate

Payment owner reported September 16 that GCash activation is not verified and the
current integration lacks GCash selection/readiness/source recognition. Merchant
approval is not checkout activation. Production publication is held pending the
payment lane's verified activation (issue #2). This was the initial branding hold.
After reviewing the staging presentation and the activation gap, John explicitly
directed: "great, get into production now". That current approval lifts the
presentation-only merge/deployment hold for all three badges. It does not activate
payment methods or authorize this task to alter gateway/provider settings.
Native payment activation and end-to-end acceptance remain owned by issue #2;
visible branding is not evidence of working checkout support. Fresh authenticated
production readback after the bank request confirms enabled=yes but selected
issuance_methods are only qrph, paymaya, shopee_pay, grab_pay; dob and dob_ubp are
not selected. This is distinct from John's confirmation the bank methods work.
No gateway changes were made; the payment task received this current evidence.

The v9 extension reuses the same-session six-component verified backup (still
within four hours) and captures new exact v8 beforeimages before staging writes.
The BPI and UnionBank assets already exist on staging with hashes matching source;
their original artwork is unchanged.

V9 acceptance passed on six ordinary URL/viewport checks (home 1440/768/390/320,
shop 390, shipping/returns 1280): HTTP 200, all eight badges loaded, equal sizes
within rounding, original aspect ratios, no overflow. Desktop/mobile screenshots
visually accepted. Exact partial SHA256:
7ba9e91f65e4cb7cacf47ca303fd49714cb3569e8b275293a19dc4eb39b5b2a6.
Independent destination readback preserves all protected files and payment-setting
hashes, with zero new log bytes. Bank extension evidence and exact v8 rollback
preimage: /private/tmp/bactive-banks-8ceyNK, retained privately under BactivePH
Application Support/footer-releases/2026-09-16-banks. The below v8 evidence is the
preceding GCash-only stage, superseded by v9 for the visual release candidate.

Both authenticated environments have the expected home/site URL, blocksy-child,
separate databases, and staging noindex. Exact live trust-bar beforeimage matches
Maxim v7, SHA256 b8a93c633de5f392bb4b9811715ab9d9e141367328da9817d8e9d0ae7d7f00a1.
GCash SVG was absent on both targets. Preserve the later catalogue release.

## Verification

Both mirrors passed seven gateway/COD scenarios and four negative layout tests,
PHP lint, mirror equality and whitespace checks. Independent review found no
blocking code/security findings. Impeccable's one-pass detector returned no findings.
Staging acceptance passed: ordinary home at 1440, 768, 390 and 320px, shop at
390px and shipping/returns at 1280px each returned HTTP 200 and v8. All logos
loaded, six payment badges matched sizes within rounding, aspect ratios were
preserved and no horizontal overflow occurred. Desktop/mobile screenshots were
visually inspected; the original logo family and courier grouping are retained.
The initial stale v7 render was refreshed with native staging page-cache purging;
no Cloudflare, SSL, security or payment configuration changed.

Staging's fresh six-component Updraft backup passed off-server SHA256, ZIP CRC and
gzip integrity checks before installation. Independent readback confirms exact
target hashes, preserved protected files/payment settings and zero new log bytes:

- gcash.svg: 3f947f3d5cc4ac5cbb44c0c2c720bc8e36d7d6bc5c254923146e37943c721623
- trust-bar.php: ab683aafa1af7c0f2b6d1411d46e6843be3123a2e19b417c45c3846a796fa64a

Production remains v7 without GCash; ordinary homepage readback confirmed this.
There were no production writes. Native gateway activation and end-to-end GCash
checkout acceptance are UNVERIFIED and outside this presentation-only change.
Private backup/rollback evidence retained under BactivePH Application Support,
footer-releases/2026-09-16-gcash. Production needs fresh backup and drift checks
when the payment dependency is satisfied, not reuse of today's staging backup.

## Rollback

Before staging writes, require a fresh complete Updraft DB/files backup verified
off-server with SHA256 plus ZIP/gzip integrity, and exact target beforeimages.
Deploy only gcash.svg first and trust-bar.php last via guarded atomic SFTP.
Restore only the exact previous partial after checking for subsequent writers;
the unused new SVG can remain. Never restore the database for this UI change.

Private receipts: /private/tmp/bactive-gcash-Gqv3z2. No global memory files changed.
