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

res = requests.patch(f"https://api.cloudflare.com/client/v4/zones/{zone_id}/settings/ssl", headers=headers, json={"value": "strict"})
print(res.status_code)
print(res.text)
