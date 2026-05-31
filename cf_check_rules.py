import requests
import os
from dotenv import load_dotenv
import json

load_dotenv()
API_TOKEN = os.environ.get("CLOUDFLARE_API_TOKEN", "")
headers = {
    "Authorization": f"Bearer {API_TOKEN}",
    "Content-Type": "application/json"
}
zone_id = "eb6e4e5bc244a0ddce57a66b9be57ed5"

print("--- Page Rules ---")
resp = requests.get(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/pagerules", headers=headers)
print(json.dumps(resp.json(), indent=2))

print("\\n--- Rulesets (Transform Rules, Redirects, etc) ---")
resp = requests.get(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets", headers=headers)
rulesets = resp.json().get('result', [])
for rs in rulesets:
    if rs.get('phase') in ['http_request_transform', 'http_request_dynamic_redirect', 'http_request_redirect']:
        rs_id = rs['id']
        r = requests.get(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{rs_id}", headers=headers)
        print(f"Phase {rs.get('phase')}:", json.dumps(r.json(), indent=2))
