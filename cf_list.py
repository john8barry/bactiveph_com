import requests
import json
import os
from dotenv import load_dotenv

load_dotenv()
API_TOKEN = os.environ.get("CLOUDFLARE_API_TOKEN", "")
headers = {
    "Authorization": f"Bearer {API_TOKEN}",
    "Content-Type": "application/json"
}

# 1. Get Zone ID
res = requests.get("https://api.cloudflare.com/client/v4/zones?name=bactiveph.com", headers=headers)
data = res.json()
if "result" in data and len(data["result"]) > 0:
    zone_id = data["result"][0]["id"]
    print("Zone ID:", zone_id)
else:
    print("Zone not found:", data)
    exit(1)

# 2. Get Rulesets
res = requests.get(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets", headers=headers)
rulesets = res.json().get("result", [])
for rs in rulesets:
    if rs["phase"] == "http_ratelimit":
        print("Rate Limiting Ruleset:", rs["id"])
        r = requests.get(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{rs['id']}", headers=headers)
        rules = r.json().get("result", {}).get("rules", [])
        print(json.dumps(rules, indent=2))
