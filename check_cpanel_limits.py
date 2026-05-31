import env_loader
import os
import requests
from dotenv import load_dotenv

load_dotenv()

CPANEL_HOST = "premium343.web-hosting.com"
CPANEL_USER = os.environ.get('CPANEL_USER')
CPANEL_TOKEN = os.environ.get('CPANEL_API_TOKEN')

headers = {
    'Authorization': f'cpanel {CPANEL_USER}:{CPANEL_TOKEN}'
}
base_url = f"https://{CPANEL_HOST}:2083/execute"

# Get account limits
res = requests.get(f"{base_url}/StatsBar/get_stats", headers=headers, params={"display": "subdomains|databases"})
print("Stats:")
print(res.json())

# Check existing subdomains
res = requests.get(f"{base_url}/SubDomain/listsubdomains", headers=headers)
print("\nSubdomains:")
print(res.json())
