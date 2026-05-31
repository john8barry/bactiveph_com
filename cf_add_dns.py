import os
import requests
from dotenv import load_dotenv

load_dotenv()

API_TOKEN = os.environ.get("CLOUDFLARE_API_TOKEN")
ZONE_ID = "71ba04a85c07a74049896f0f1580469f"  # from earlier
IP = "199.188.201.232"

headers = {
    "Authorization": f"Bearer {API_TOKEN}",
    "Content-Type": "application/json"
}

# Add DNS record
dns_url = f"https://api.cloudflare.com/client/v4/zones/{ZONE_ID}/dns_records"
dns_data = {
    "type": "A",
    "name": "staging",
    "content": IP,
    "ttl": 1,
    "proxied": True
}

res = requests.post(dns_url, headers=headers, json=dns_data)
print(res.json())
