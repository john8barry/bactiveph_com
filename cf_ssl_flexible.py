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

# We will create a Page Rule to set SSL to Flexible for staging.bactiveph.com/*
payload = {
    "targets": [
        {
            "target": "url",
            "constraint": {
                "operator": "matches",
                "value": "staging.bactiveph.com/*"
            }
        }
    ],
    "actions": [
        {
            "id": "ssl",
            "value": "flexible"
        }
    ],
    "priority": 1,
    "status": "active"
}

res = requests.post(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/pagerules", headers=headers, json=payload)
print(res.status_code)
print(res.text)
