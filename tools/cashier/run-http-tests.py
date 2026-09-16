#!/usr/bin/env python3
"""Exercise real anonymous HTTP webhook signatures and manager cancellation."""
import hashlib
import hmac
import json
import sys
import time
import urllib.error
import urllib.request
import urllib.parse
import runtime

state = json.loads(runtime.STATE.read_text())
script = '/reference/wp-content/plugins/bactive-cashier/tests/http-scenarios.php'
checks = []


def scenario(*args):
    out = runtime.wp(state, ['eval-file', script, *args])
    return json.loads(out.splitlines()[-1])


def check(condition, label):
    if not condition:
        raise AssertionError(label)
    checks.append(label)


def callback(case, valid=True):
    body = json.dumps(case['event'], separators=(',', ':')).encode()
    timestamp = str(int(time.time()))
    signature = hmac.new(b'whsk_synthetic_cashier_fixture_only', timestamp.encode() + b'.' + body, hashlib.sha256).hexdigest()
    if not valid:
        signature = '0' * 64
    req = urllib.request.Request(state['url'] + '/?wc-api=bactive_paymongo_live', data=body,
                                 headers={'Content-Type': 'application/json', 'Paymongo-Signature': 't=' + timestamp + ',li=' + signature})
    try:
        with urllib.request.urlopen(req, timeout=20) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()


order_pay_only = '--order-pay-only' in sys.argv
expiry_only = '--expiry-only' in sys.argv

if not expiry_only:
    for mode, expected_attempts in (('order-pay', 0), ('order-pay-digital', 1)):
        case = scenario('prepare', mode)
        before = scenario('inspect', case['key'])
        check(before['attempt_count'] == expected_attempts and before['order_status'] == 'pending',
              mode + ': pending cashier fixture has expected payment history')
        query = urllib.parse.urlencode({'order-pay': case['order_id'], 'key': case['order_pay']['order_key']})
        body = urllib.parse.urlencode({'woocommerce_pay': '1', 'woocommerce-pay-nonce': case['order_pay']['nonce'],
                                       'payment_method': 'bactive_paymongo', 'terms': '1'}).encode()
        req = urllib.request.Request(state['url'] + '/?' + query, data=body,
                                     headers={'Content-Type': 'application/x-www-form-urlencoded'})
        try:
            with urllib.request.urlopen(req, timeout=20) as response:
                status, response_body = response.status, response.read().decode()
        except urllib.error.HTTPError as error:
            status, response_body = error.code, error.read().decode()
        # This exact text belongs to before_pay_action, not the later GET guard.
        # Reaching it proves Woo accepted the nonce and exact order key.
        check(status == 403 and 'Use the private cashier to manage this in-store sale.' in response_body,
              mode + ': valid nonce public payment POST blocked before payment action')
        after = scenario('inspect', case['key'])
        unchanged = ('payment_method', 'order_status', 'attempt_count', 'attempt_hash', 'qty', 'held', 'active_owner')
        check(all(after[field] == before[field] for field in unchanged),
              mode + ': payment method, attempts, stock and register claim unchanged')

if order_pay_only:
    print(json.dumps({'passed': len(checks), 'checks': checks}, indent=2))
    sys.exit(0)

for method in (() if expiry_only else ('qrph', 'paymaya', 'shopee_pay', 'grab_pay')):
    case = scenario('prepare', method)
    status, body = callback(case, False)
    check(status == 401, method + ': forged signature rejected')
    before = scenario('inspect', case['key'])
    check(before['sale']['status'] == 'pending' and before['qty'] == 3 and before['held'] == 1,
          method + ': forged callback leaves paid state and stock untouched')
    status, body = callback(case)
    check(200 <= status < 210, method + ': signed anonymous callback accepted: ' + body)
    paid = scenario('inspect', case['key'])
    check(paid['sale']['status'] == 'paid' and paid['qty'] == 2 and paid['held'] == 0,
          method + ': payment facts, stock reduction and hold release verified')
    callback(case)
    duplicate = scenario('inspect', case['key'])
    check(duplicate['sale']['status'] == 'paid' and duplicate['qty'] == 2, method + ': duplicate callback has no duplicate stock effect')

if not expiry_only:
    case = scenario('prepare', 'cancel')
    runtime.wp(state, ['eval-file', script, 'manager', case['key']])
    cancelled = scenario('inspect', case['key'])
    check(cancelled['sale']['status'] == 'cancelled' and cancelled['qty'] == 3 and cancelled['held'] == 0
          and cancelled['active_owner'] is None, 'Manager closes unpaid provider session and releases stock and register')

case = scenario('prepare', 'cancel')
runtime.wp(state, ['option', 'update', 'bactive_cashier_fixture_provider_fault', 'expire_timeout'])
failed = False
try:
    runtime.wp(state, ['eval-file', script, 'manager', case['key']])
except RuntimeError:
    failed = True
finally:
    runtime.wp(state, ['option', 'delete', 'bactive_cashier_fixture_provider_fault'])
unresolved = scenario('inspect', case['key'])
check(failed and unresolved['sale']['status'] != 'cancelled' and unresolved['qty'] == 3
      and unresolved['held'] == 1 and unresolved['active_owner'] is not None,
      'Unverified provider expiration retains sale, stock hold and register claim')
print(json.dumps({'passed': len(checks), 'checks': checks}, indent=2))
