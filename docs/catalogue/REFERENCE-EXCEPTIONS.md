# Original-reference visual exceptions

Reviewed 2026-09-08: 47 attachment files across nine products, individually with
`view_image`. This is a source-image review, not physical garment, inventory,
live storefront, or exact pixel-fidelity certification. “Original” means the
downloaded attachment file; it does not establish camera provenance or rule out
prior AI editing. No source images, mappings, catalogue records or stock changed.

Evidence inputs are private `catalog-before.json`, `variations-before.json` and
`reference-manifest.json` under
`/Users/johnbarry/Library/Application Support/BactivePH/catalog-visuals/2026-09-08/`.
The manifest resolves every examined attachment ID to its local file and hash in
`references/`. Descriptive colours below are visual observations, not approved
WooCommerce shade definitions. Preserve originals even when a mapping is held.

## Product findings

| Product | Examined attachment IDs | Preserve/reuse and required control point |
|---|---|---|
| **217 Strappy Bra** | **582** | Clear pale-yellow front/back diptych; scoop neck, broad under-bust band and four narrow rounded crossing rear straps are visible. Preserve as construction baseline. It cannot represent all four named colours as currently mapped. Obtain approved colour references or qualified recolours; retain exact strap topology and rounded thickness. Prior generated recolours failed this construction detail. Interior, padding and unseen features are not established. |
| **238 Sculpt Leggings** | **559, 560, 561, 562, 563, 564** | Six usable front views of high-waisted ankle leggings: dusty pink/mauve, olive, black, pale lilac/gray, dark plum and muted blue respectively. All eight Almond/Stone variations point to dusty-pink **559**. These images do not identify which, if any, is the intended Almond or Stone. Confirm the two actual colour references before mapping or recolouring; do not expose the other photographed colours as inventory. No rear view or hidden-feature evidence in this set. |
| **211 Ribbed Tank** | **212, 494, 495** | **212** is a small front/back pink tank diptych; **494/495** provide substantially larger front/back views with compatible ribbing, racerback and crossed front under-bust panels. Reuse **494/495** as the leading presentation candidates instead of generating replacements solely to fix the low-resolution variation image. They show a different model/styling garment and do not establish exact material/fit equivalence. Confirm **494** as intended primary before changing variation image **212**. No additional recolour is indicated by the one listed colour. |
| **50 Rally Dress** | **394, 395, 396, 397, 474, 475, 477** | Preserve these front views of a sleeveless crew-neck, side-tie wrap silhouette. **394** is navy; **395/477** show the same apparent gray presentation, **396/475** the same apparent brown/taupe presentation, and **397/474** the same apparent cream presentation. Out-of-gallery IDs are therefore not themselves visible wrong-product conflicts. Reuse existing per-colour assignments pending accepted shade labels; compare manifest hashes before claiming byte duplicates or consolidating attachments. No rear/inside evidence. Paddle and ball are styling props, not included-product proof. |
| **56 Bubble Dress** | **58, 60, 61, 373, 374, 375, 376, 377, 378, 381, 384, 386** | **58/60** provide small pink/black gathered bubble-hem front references. Larger **373/384/386** show apparently repeated black presentation; **374** navy, **375** light blue, **376** white, **377/378** apparently repeated pink, **381** red. Preserve these as candidates, while resolving neckline/waist/fold consistency before calling them one exact garment standard. **Critical mapping exception: wildcard variation 80 points to 61, which visibly shows a white narrow-pleated skirt dress, not the gathered balloon hem shown by the Bubble references.** Hold that assignment; do not infer that changing its colour to White alone fixes it. Black XL duplicate IDs still require catalogue reconciliation even though **386** and **60** both show black bubble dresses. Existing files can cover six colour presentations after approval; no blanket regeneration is justified. No back/interior evidence. |
| **185 Court Skort** | **458, 459, 464, 465, 466** | Five coherent front presentations of a broad waistband over narrow pleats: navy, white, pink/lilac, light blue and pale mint respectively. They are usable candidates for the existing Navy Blue, Pure White, Sakura Pink, Oil Blue and Green Jasper mappings, but names do not certify the precise shades. Reuse before generating; preserve waistband/pleat geometry. Resolve duplicate variation rows separately. White tops, rackets and shoes are styling. No inside shorts/pockets/back view are established. |
| **160 Rally Skort** | **164, 453, 454, 455, 456** | **164** is a small black plain A-line skort front view. **453/454/455/456** provide larger muted-green, pale-pink, black and periwinkle-blue front presentations with broad waistbands and plain flared hems. Reuse candidates for parent-listed colours, subject to shade and cut approval; **456** must not be darkened simply because its term says Night Indigo. Orphan Court Ivory variation **181** points to visibly black **164**; this is a visible colour conflict. The same black reference cannot prove Onyx equals Black or authorize exposing orphan Onyx records. No rear/interior evidence. |
| **347 Aria Set** | **343, 344, 345, 346** | All four show a **two-piece halter crop top and broad-panel/flared skirt**, not a one-piece dress. **343** is off-white, **344** pink/lilac, **345** taupe/brown and **346** periwinkle/light blue. Preserve references and visible top/skirt separation. The existing alt text on **343** calling it a white activewear dress with built-in shorts is not supported by this view and needs correction in an authorized metadata change. Mapping off-white **343** across all colours is visibly unsuitable. **344** alone cannot establish separate Bloom and Sakura Pink representations. Confirm set contents and which colour each reference represents; no rear halter closure or built-in shorts evidence is available. |
| **573 Warm-Up Jacket** | **574, 575, 576, 577** | Four useful consistent front views: saturated magenta/fuchsia, beige, gray and black. Full zipper, stand collar, raglan/panel seams, fitted waist, long sleeves and small white chest logo are visible. Preserve/reuse all four; no new primary imagery is necessary merely for uniform presentation. Keep original logo and seam placement. No rear/interior/pocket-function evidence. Product remains simple without colour attributes, so imagery does not authorize selectable colours or sizes. |

## Decisions and generation boundaries

- Prioritize **Bubble image 61** and **Aria image 343 metadata** as concrete visual
  conflicts. Catalogue duplicates/wildcards remain separate unresolved records;
  this review does not choose survivor IDs or infer their stock.
- **Sculpt Leggings** needs an owner/supplier mapping between the two listed
  colours and valid references. Six photographed hues are not six offered hues.
- **Strappy Bra** needs approved colour representations that preserve the source
  construction. Other products here have substantial reusable imagery; prefer
  mapping, gallery ordering and consistent CSS presentation over regeneration.
- Where only front images exist, omit unsupported rear/interior detail instead
  of inventing it. If an additional angle is required, obtain a supporting source.
- Apparent duplicate images in this review are visual observations. Attachment
  deletion/consolidation requires hash and reference checks and separate authority.
- **Correction to earlier review:** coordinator's stored term snapshot confirms
  actual slugs **`powder`** and **`sakura`** agree with the recorded defaults.
  The earlier suspected stale-default defect was based on an incorrect assumption
  about term slugs and is withdrawn. Do not change those defaults on that basis.

This document is an independent reference-review input, not production approval
or a completed nine-product release. Per-product acceptance still requires approved
product truth, exact mappings, rendering/selection checks and live evidence.
