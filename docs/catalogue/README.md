# Offline catalogue image plans

`tools/catalog_visuals.py` plans image assignments and previews guarded reversal.
It has no network access, upload operation, WordPress mutation executor or write
command. Output is JSON on stdout. It never prints supplied commerce context.

Inputs are nonempty JSON arrays of narrowly projected product objects:

```json
[{"id":36,"images":[{"id":387},{"id":388}],"variations":[{"id":49,"image":{"id":387}}]}]
```

The first product image is featured; remaining images are the ordered gallery.
An empty product image list explicitly represents no featured/gallery images.
Variation image IDs must be positive integers; clearing a variation image to a
fallback is deliberately unsupported. IDs and parent ownership cannot change.
All other fields, duplicate objects, duplicate JSON keys and invalid types are
rejected. Project full snapshots into this exact shape before planning; do not
send full snapshots to the plan command. Changed attributes, prices, names, SKUs,
stock, orders or other settings are outside this tool's scope.

```sh
python3 tools/catalog_visuals.py --target https://bactiveph.com --release-id example-20260908 plan --before before.json --proposed proposed.json
python3 tools/catalog_visuals.py --target https://bactiveph.com --release-id example-20260908 reverse-preview --plan plan.json --current current.json
```

Current state uses the same image projection and nested variation objects, but
may include unrelated product/variation commerce fields. These fields are ignored
and never mutated or included in the reverse plan. Image objects still accept
only their ID, so raw REST snapshots require projection here too.

Reversal validates every affected object before returning the complete preview.
A field equal to the release's after value can be restored; a field already equal
to its before value is reported as already restored. Any other value, missing ID
or ownership mismatch rejects the whole batch. This supports previewing an
interrupted restore without overwriting later changes. It does not guarantee an
atomic future REST operation: any executor must re-read and revalidate immediately
before writing, serialize the writer, journal results and independently read back.
An offline manifest is not proof of ownership or deployment authority; validate
it against the trusted release record before any future execution. Reverse output
is a review receipt with an `already_restored` section, not an executable command.

Run `python3 -m unittest discover -s tests -p test_catalog_visuals.py`. These are
synthetic offline checks only. They do not demonstrate a restored WooCommerce
site, live cart behavior, complete backups, or a successful on-host recovery drill.
