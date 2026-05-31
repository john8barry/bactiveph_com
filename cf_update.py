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
zone_id = "71ba04a85c07a74049896f0f1580469f"
ruleset_id = "f9bac73af49f43f188ba06e45d55a2a9"

new_rules = {
  "rules": [
    {
      "action": "block",
      "description": "Rate limit /wp-login.php and /wp-admin (Excluding AJAX)",
      "enabled": True,
      "expression": "(http.request.uri.path contains \"/wp-login.php\" or (http.request.uri.path contains \"/wp-admin\" and not http.request.uri.path contains \"admin-ajax.php\"))",
      "ratelimit": {
        "characteristics": [
          "ip.src",
          "cf.colo.id"
        ],
        "mitigation_timeout": 10,
        "period": 10,
        "requests_per_period": 50
      }
    }
  ]
}

res = requests.put(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{ruleset_id}", headers=headers, json=new_rules)
print(res.status_code)
print(res.text)
