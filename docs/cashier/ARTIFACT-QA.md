# Staff guide artifact verification

Created 2026-09-16 from the implemented local cashier training screen. Products, payments, customer email and invoice examples are synthetic. These documents do not establish production launch or payment-provider readiness.

## Outputs

- `cashier-staff-guide.docx` — editable guide, six pages when rendered.
- `cashier-staff-guide.pdf` — matching six-page PDF rendered from the DOCX.
- `counter-checklist.pdf` — one-page counter checklist.
- `manager-checklist.md` — manager opening, shift reconciliation and pause checklist.

## Verification performed

- Used the bundled primary runtime 26.909.61513 Python and packaged Documents renderer, including bundled LibreOffice/Poppler.
- Used successful document/PDF operation markers before creation.
- Viewed each source screenshot before embedding it unchanged. Panel images are actual browser captures supplied by the root agent; no image editing or simulated UI was used in document assembly.
- Rendered the DOCX with `render_docx.py --emit_pdf` and visually inspected all six page PNGs.
- Removed an inherited template title border, rendered again and inspected the corrected first page. Final pages 2–6 were byte-identical to the inspected pages.
- Confirmed six PDF guide pages and one counter checklist page with pypdf. Visually inspected the counter checklist PNG.
- Checked visible labels, step order, screenshot legibility, invoice/handover instructions, clear training captions and absence of clipping/overflow.

## Rebuild

Run `tools/cashier/build-guide.py` with the bundled Python runtime. Render the DOCX with the Documents skill's packaged `render_docx.py --emit_pdf`, inspect all page images and copy the matching PDF into this directory. The builder fails if a required screenshot is missing. Do not distribute a rebuilt file without visual inspection.

Screenshots needed: `01-choose-items.png`, `03-cash-panel.png`, `04-invoice-panel.png`, `05-digital-panel.png`, `06-complete-panel.png`.
