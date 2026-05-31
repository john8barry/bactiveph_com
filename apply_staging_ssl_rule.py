import requests
import os
import json
from dotenv import load_dotenv

load_dotenv()
API_TOKEN = os.environ.get("CLOUDFLARE_API_TOKEN", "")
headers = {
    "Authorization": f"Bearer {API_TOKEN}",
    "Content-Type": "application/json"
}
zone_id = "71ba04a85c07a74049896f0f1580469f"

payload = {
    "name": "default",
    "kind": "zone",
    "phase": "http_config_settings",
    "rules": [
        {
            "action": "set_config",
            "action_parameters": {
                "ssl": "flexible"
            },
            "expression": "http.host eq \"staging.bactiveph.com\"",
            "description": "Flexible SSL for staging"
        }
    ]
}

res = requests.post(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets", headers=headers, json=payload)
print(res.status_code)
print(res.text)
