import importlib.util
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools'))
from audit_public_skus import inspect, decode


class PrivacyAudit(unittest.TestCase):
    def test_encoded_values(self):
        for text in ['{"sku":"TEST-123"}', '{&quot;sku&quot;:&quot;TEST-123&quot;}',
                     r'{"sku":"TEST\u002d123"}', 'TEST%2D123', 'src="/Batch_TEST-123_1.png"', 'src="/prefixTEST-123suffix.png"']:
            self.assertTrue(any(f['kind'] == 'sku_value' for f in inspect(text, ['TEST-123'])))

    def test_short_identifier_boundaries(self):
        self.assertEqual(inspect('abcdefD29fedcba', ['D29']), [])
        self.assertTrue(inspect('/Batch_D29_1.png', ['D29']))

    def test_empty_fields_still_fail(self):
        self.assertTrue(inspect('{"sku":""}', ['TEST-123']))
        self.assertTrue(inspect('<a data-product_sku="">', ['TEST-123']))

    def test_no_raw_identifiers_in_findings(self):
        self.assertNotIn('TEST-123', str(inspect('{"sku":"TEST-123"}', ['TEST-123'])))

    def test_unrelated_ids_and_generic_library_code(self):
        self.assertEqual(inspect('{"id":123,"name":"Court Dress"}; product.sku;', ['TEST-123']), [])


if __name__ == '__main__':
    unittest.main()
