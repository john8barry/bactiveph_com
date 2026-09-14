"""Synthetic offline checks, not proof of a restored WooCommerce site."""
import copy
import importlib.util
import json
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location("catalog_visuals", Path(__file__).resolve().parents[1] / "tools/catalog_visuals.py")
cv = importlib.util.module_from_spec(spec)
spec.loader.exec_module(cv)


class CatalogueVisualTests(unittest.TestCase):
    def setUp(self):
        self.context = {"target": cv.TARGET, "release_id": "test-20260908"}
        self.before = [{"id": 36, "images": [{"id": 387}, {"id": 388}],
                        "variations": [{"id": 49, "image": {"id": 387}}]}]
        self.proposed = copy.deepcopy(self.before)
        self.proposed[0]["images"] = [{"id": 600}, {"id": 388}]
        self.proposed[0]["variations"][0]["image"]["id"] = 601
        self.plan = cv.build_plan(self.before, self.proposed, **self.context)

    def test_roundtrip_and_newer_commerce_context_preserved(self):
        current = copy.deepcopy(self.proposed)
        current[0].update(price="999", stock_quantity=7, orders=[{"id": 9999}], sku="new")
        current[0]["variations"][0].update(price="998", stock_quantity=2)
        untouched = copy.deepcopy(current)
        reverse = cv.reverse_plan(self.plan, current, **self.context)
        self.assertEqual(current, untouched)
        self.assertEqual(reverse["operations"][0]["after"], [387, 388])
        self.assertEqual(reverse["operations"][1]["after"], 387)
        self.assertNotIn("stock", json.dumps(reverse))
        self.assertNotIn("9999", json.dumps(reverse))

    def test_batch_conflict_returns_nothing_and_mutates_nothing(self):
        current = copy.deepcopy(self.proposed)
        current[0]["variations"][0]["image"]["id"] = 999
        untouched = copy.deepcopy(current)
        with self.assertRaises(cv.ValidationError):
            cv.reverse_plan(self.plan, current, **self.context)
        self.assertEqual(current, untouched)

    def test_interrupted_reverse_idempotent(self):
        current = copy.deepcopy(self.proposed)
        current[0]["images"] = copy.deepcopy(self.before[0]["images"])
        reverse = cv.reverse_plan(self.plan, current, **self.context)
        self.assertEqual(len(reverse["operations"]), 1)
        self.assertEqual(len(reverse["already_restored"]), 1)
        done = cv.reverse_plan(self.plan, self.before, **self.context)
        self.assertEqual(done["operations"], [])
        self.assertEqual(len(done["already_restored"]), 2)

    def test_unowned_input_fields_rejected_even_if_unchanged(self):
        for field in ["stock_quantity", "price", "orders", "sku", "attributes", "name", "secret"]:
            with self.subTest(field=field):
                before = copy.deepcopy(self.before)
                before[0][field] = "private"
                with self.assertRaises(cv.ValidationError):
                    cv.build_plan(before, before, **self.context)

    def test_bad_ids_objects_and_types(self):
        for bad in [None, True, 0, -1, "49", 49.0]:
            with self.subTest(bad=bad):
                proposed = copy.deepcopy(self.proposed)
                proposed[0]["variations"][0]["id"] = bad
                with self.assertRaises(cv.ValidationError):
                    cv.build_plan(self.before, proposed, **self.context)
        for transform in [lambda p: p.append(copy.deepcopy(p[0])),
                          lambda p: p[0].pop("id"),
                          lambda p: p[0].update(images="invalid"),
                          lambda p: p[0]["variations"][0].update(id=36),
                          lambda p: p[0]["variations"][0].update(id=50),
                          lambda p: p[0]["images"].append({"id": 600})]:
            proposed = copy.deepcopy(self.proposed)
            transform(proposed)
            with self.assertRaises(cv.ValidationError):
                cv.build_plan(self.before, proposed, **self.context)

    def test_tampered_plan_and_wrong_ownership_rejected(self):
        for transform in [lambda p: p["operations"].append(copy.deepcopy(p["operations"][0])),
                          lambda p: p["operations"][0].update(field="price"),
                          lambda p: p["operations"][0].update(product_id=37),
                          lambda p: p.update(release_id="other"),
                          lambda p: p.update(target="https://example.com")]:
            plan = copy.deepcopy(self.plan)
            transform(plan)
            with self.assertRaises(cv.ValidationError):
                cv.reverse_plan(plan, self.proposed, **self.context)
        current = copy.deepcopy(self.proposed)
        current[0]["variations"] = []
        with self.assertRaises(cv.ValidationError):
            cv.reverse_plan(self.plan, current, **self.context)

    def test_reorder_and_empty_gallery_are_exact(self):
        proposed = copy.deepcopy(self.before)
        proposed[0]["images"].reverse()
        plan = cv.build_plan(self.before, proposed, **self.context)
        self.assertEqual(plan["operations"][0]["after"], [388, 387])
        proposed[0]["images"] = []
        plan = cv.build_plan(self.before, proposed, **self.context)
        self.assertEqual(plan["operations"][0]["after"], [])

    def test_duplicate_json_fields_and_target_rejected(self):
        with self.assertRaises(cv.ValidationError):
            json.loads('{"id":36,"id":37}', object_pairs_hook=cv.unique_keys)
        with self.assertRaises(cv.ValidationError):
            cv.build_plan(self.before, self.proposed, target="https://example.com", release_id="test")


if __name__ == "__main__":
    unittest.main()
